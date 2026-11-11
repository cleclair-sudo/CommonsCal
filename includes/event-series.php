<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Applies a local wall-clock shift without changing the time around DST transitions. */
function calsys_shift_event_datetime($value, $seconds) {
    $utc = new DateTimeZone('UTC');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $utc);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        return false;
    }
    $shifted = $date->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds')->format('Y-m-d H:i:s');
    $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $shifted, wp_timezone());
    return $local && $local->format('Y-m-d H:i:s') === $shifted ? $shifted : false;
}

/** Updates a series within the caller's transaction, rolling back on any conflict. */
function calsys_update_event_series($event_id, $scope, $data, $room_id, $equipment) {
    global $wpdb;
    $events_table = calsys_table('events');
    $anchor = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id = %d FOR UPDATE", $event_id));
    if (!$anchor || !$anchor->recurrence_group) {
        return false;
    }
    $query = "SELECT * FROM $events_table WHERE recurrence_group = %s";
    $args = array($anchor->recurrence_group);
    if ($scope === 'future') {
        $query .= ' AND (starts_at > %s OR (starts_at = %s AND id >= %d))';
        $args[] = $anchor->starts_at;
        $args[] = $anchor->starts_at;
        $args[] = $event_id;
    }
    $query .= ' ORDER BY id ASC FOR UPDATE';
    $events = $wpdb->get_results($wpdb->prepare($query, $args));
    if ($wpdb->last_error || !is_array($events) || !$events) {
        return false;
    }
    $utc = new DateTimeZone('UTC');
    $old_start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $anchor->starts_at, $utc);
    $old_end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $anchor->ends_at, $utc);
    $new_start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['starts_at'], $utc);
    $new_end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['ends_at'], $utc);
    if (!$old_start || !$old_end || !$new_start || !$new_end) {
        return false;
    }
    $start_shift = $new_start->getTimestamp() - $old_start->getTimestamp();
    $end_shift = $new_end->getTimestamp() - $old_end->getTimestamp();
    $schedules = array();
    foreach ($events as $event) {
        $start = calsys_shift_event_datetime($event->starts_at, $start_shift);
        $end = calsys_shift_event_datetime($event->ends_at, $end_shift);
        if (!$start || !$end || $end <= $start || !calsys_lock_linked_room_ids('event', (int) $event->id, $room_id)) {
            return false;
        }
        $schedules[(int) $event->id] = array($start, $end);
    }
    if ($equipment) {
        $ids = array_keys($equipment);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $active = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . calsys_table('equipment') . " WHERE id IN ($placeholders) AND active = 1 ORDER BY id FOR UPDATE",
            $ids
        ));
        if ($wpdb->last_error || !is_array($active) || count($active) !== count($ids)) {
            return false;
        }
    }
    foreach ($events as $event) {
        if ($wpdb->delete(calsys_table('bookings'), array('source_type' => 'event', 'source_id' => $event->id)) === false ||
            $wpdb->delete(calsys_table('equipment_reservations'), array('source_type' => 'event', 'source_id' => $event->id)) === false) {
            return false;
        }
    }
    $updated_ids = array();
    unset($data['recurrence_group']);
    foreach ($events as $event) {
        list($data['starts_at'], $data['ends_at']) = $schedules[(int) $event->id];
        if ($wpdb->update($events_table, $data, array('id' => $event->id)) === false) {
            return false;
        }
        $room_result = calsys_sync_linked_room_reservations('event', (int) $event->id, $room_id, $data['starts_at'], $data['ends_at']);
        if ($room_result !== 0) {
            return $room_result === 1 ? 'room_conflict' : false;
        }
        $equipment_result = calsys_sync_equipment('event', (int) $event->id, $equipment, $data['starts_at'], $data['ends_at'], 'approved');
        if ($equipment_result !== 'saved') {
            return $equipment_result === 'conflict' ? 'equipment_conflict' : false;
        }
        $updated_ids[] = (int) $event->id;
    }
    return $updated_ids;
}
