<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Calculates the start and end datetimes for a recurring event series. */
function calsys_event_occurrences($starts_at, $ends_at, $frequency, $end_type, $count, $until) {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $starts_at, wp_timezone());
    $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $ends_at, wp_timezone());
    if (!$start || !$end) {
        return false;
    }
    $occurrences = array(array($starts_at, $ends_at));
    if ($frequency === 'none') {
        return $occurrences;
    }
    if (!in_array($frequency, array('daily', 'weekly', 'monthly'), true) || !in_array($end_type, array('count', 'date'), true)) {
        return false;
    }
    $end_day_offset = (int) $start->setTime(0, 0)->diff($end->setTime(0, 0))->format('%a');
    if ($end_type === 'count') {
        if ($count < 2 || $count > 100) {
            return false;
        }
        $limit = $count;
    } else {
        $until_date = DateTimeImmutable::createFromFormat('!Y-m-d', $until, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$until_date || ($errors && ($errors['warning_count'] || $errors['error_count'])) ||
            $until_date->format('Y-m-d') !== $until || $until_date <= $start->setTime(0, 0) ||
            $until_date > $start->modify('+2 years')->setTime(0, 0)) {
            return false;
        }
        $limit = 100;
    }

    for ($index = 1; $index < $limit; $index++) {
        if ($frequency === 'daily') {
            $next_start = $start->modify('+' . $index . ' days');
        } elseif ($frequency === 'weekly') {
            $next_start = $start->modify('+' . ($index * 7) . ' days');
        } else {
            $month_start = $start->modify('first day of this month')->modify('+' . $index . ' months');
            $day = min((int) $start->format('j'), (int) $month_start->format('t'));
            $next_start = $month_start->setDate((int) $month_start->format('Y'), (int) $month_start->format('n'), $day)
                ->setTime((int) $start->format('H'), (int) $start->format('i'), (int) $start->format('s'));
        }
        if ($end_type === 'date' && $next_start->format('Y-m-d') > $until) {
            break;
        }
        $next_end = $next_start->modify('+' . $end_day_offset . ' days')
            ->setTime((int) $end->format('H'), (int) $end->format('i'), (int) $end->format('s'));
        $occurrences[] = array($next_start->format('Y-m-d H:i:s'), $next_end->format('Y-m-d H:i:s'));
    }
    if ($end_type === 'date' && $occurrences[count($occurrences) - 1][0] === $starts_at && $until !== $start->format('Y-m-d')) {
        return false;
    }
    if ($end_type === 'date' && count($occurrences) === 100) {
        $probe_index = 100;
        if ($frequency === 'daily') {
            $probe = $start->modify('+' . $probe_index . ' days');
        } elseif ($frequency === 'weekly') {
            $probe = $start->modify('+' . ($probe_index * 7) . ' days');
        } else {
            $month_start = $start->modify('first day of this month')->modify('+' . $probe_index . ' months');
            $day = min((int) $start->format('j'), (int) $month_start->format('t'));
            $probe = $month_start->setDate((int) $month_start->format('Y'), (int) $month_start->format('n'), $day)
                ->setTime((int) $start->format('H'), (int) $start->format('i'), (int) $start->format('s'));
        }
        if ($probe->format('Y-m-d') <= $until) {
            return false;
        }
    }
    return $occurrences;
}

/** Validates and saves an event series and synchronizes its room reservations. */
function calsys_save_event($frontend = false) {
    calsys_require_staff();
    check_admin_referer($frontend ? 'calsys_front_save_event' : 'calsys_save_event');
    global $wpdb;

    $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
    $description = wp_kses_post(wp_unslash($_POST['description'] ?? ''));
    if (isset($_POST['image_id']) && !is_scalar($_POST['image_id'])) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $starts_at = calsys_parse_datetime($_POST['starts_at'] ?? '');
    $ends_at = calsys_parse_datetime($_POST['ends_at'] ?? '');
    $capacity = absint($_POST['capacity'] ?? 0);
    $status = sanitize_key(wp_unslash($_POST['status'] ?? 'draft'));
    $category = sanitize_key(wp_unslash($_POST['category'] ?? 'adult'));
    $categories = calsys_event_categories();
    $event_id = absint($_POST['event_id'] ?? 0);
    $equipment = calsys_posted_equipment();
    if ($equipment === false) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $image_id = absint(wp_unslash($_POST['image_id'] ?? 0));
    if (!isset($_POST['image_id']) && $event_id) {
        $saved_image_id = $wpdb->get_var($wpdb->prepare(
            "SELECT image_id FROM " . calsys_table('events') . " WHERE id = %d",
            $event_id
        ));
        if ($saved_image_id === null || $saved_image_id === false) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
        $image_id = absint($saved_image_id);
    } elseif ($image_id && !wp_attachment_is_image($image_id)) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    if (isset($_POST['registration_enabled']) && !is_scalar($_POST['registration_enabled'])) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $registration_enabled = isset($_POST['registration_enabled']) && wp_unslash($_POST['registration_enabled']) === '1';
    $saved_registration = null;
    if ($event_id && (!$registration_enabled || !isset($_POST['registration_fields_present']) || !isset($_POST['registration_recipients']))) {
        $saved_registration = $wpdb->get_row($wpdb->prepare(
            "SELECT registration_fields, registration_recipients, registration_enabled FROM " . calsys_table('events') . " WHERE id = %d",
            $event_id
        ));
        if (!$saved_registration) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
    }
    if (isset($_POST['registration_fields_present']) && $registration_enabled) {
        $posted_registration_fields = isset($_POST['registration_fields']) ? wp_unslash($_POST['registration_fields']) : array();
        $registration_fields = calsys_sanitize_registration_fields($posted_registration_fields);
        if ($registration_fields === false) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
    } elseif ($saved_registration) {
        $registration_fields = calsys_decode_registration_fields($saved_registration->registration_fields);
    } else {
        $registration_fields = array();
    }
    if (isset($_POST['registration_recipients']) && $registration_enabled) {
        if (!is_string(wp_unslash($_POST['registration_recipients']))) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
        $registration_recipients = calsys_sanitize_registration_recipients(wp_unslash($_POST['registration_recipients']));
        if ($registration_recipients === false) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
    } elseif ($saved_registration) {
        $registration_recipients = calsys_decode_registration_recipients($saved_registration->registration_recipients);
    } else {
        $registration_recipients = array();
    }
    if ($saved_registration && !isset($_POST['registration_enabled'])) {
        $registration_enabled = !empty($saved_registration->registration_enabled);
    }
    $room_id = absint($_POST['room_id'] ?? 0);
    $recurring = !$event_id && isset($_POST['recurring']);
    $frequency = $recurring ? sanitize_key(wp_unslash($_POST['recurrence_frequency'] ?? '')) : 'none';
    $end_type = $recurring ? sanitize_key(wp_unslash($_POST['recurrence_end_type'] ?? '')) : 'count';
    $occurrence_count = absint($_POST['recurrence_count'] ?? 0);
    $until = sanitize_text_field(wp_unslash($_POST['recurrence_until'] ?? ''));

    if ($title === '' || strlen($title) > 200 || !$starts_at || !$ends_at || $ends_at <= $starts_at ||
        !in_array($status, array('draft', 'published'), true) || !isset($categories[$category]) ||
        ($event_id && isset($_POST['recurring']))) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    if ($room_id) {
        $active_room = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d AND active = 1",
            $room_id
        ));
        $existing_room_id = $event_id ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT room_id FROM " . calsys_table('bookings') . " WHERE source_type = 'event' AND source_id = %d ORDER BY id ASC LIMIT 1",
            $event_id
        )) : 0;
        if (!$active_room && $existing_room_id !== $room_id) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
    }
    $occurrences = calsys_event_occurrences(
        $starts_at,
        $ends_at,
        $recurring ? $frequency : 'none',
        $end_type,
        $occurrence_count,
        $until
    );
    if ($occurrences === false) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $recurrence_group = $recurring && count($occurrences) > 1 ? wp_generate_uuid4() : '';
    $data = array(
        'title' => $title,
        'description' => $description,
        'image_id' => $image_id,
        'starts_at' => $starts_at,
        'ends_at' => $ends_at,
        'capacity' => $capacity,
        'status' => $status,
        'category' => $category,
        'registration_fields' => wp_json_encode($registration_fields),
        'registration_enabled' => $registration_enabled ? 1 : 0,
        'registration_recipients' => wp_json_encode($registration_recipients),
        'recurrence_group' => $recurrence_group,
    );
    if ($wpdb->query('START TRANSACTION') === false || !calsys_lock_linked_room_ids('event', $event_id, $room_id)) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $table = calsys_table('events');
    $skipped = 0;
    if ($event_id) {
        unset($data['recurrence_group']);
        $updated = $wpdb->update($table, $data, array('id' => $event_id), array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s'), array('%d'));
        if ($updated === false || !$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $event_id))) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
        $saved_room = calsys_sync_linked_room_reservations('event', $event_id, $room_id, $starts_at, $ends_at);
        if ($saved_room === false) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
        $skipped += $saved_room;
        $equipment_result = calsys_sync_equipment('event', $event_id, $equipment, $starts_at, $ends_at, 'approved');
        if ($equipment_result !== 'saved') {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-events', $equipment_result === 'conflict' ? 'equipment_conflict' : 'invalid');
        }
    } else {
        foreach ($occurrences as $occurrence) {
            $data['starts_at'] = $occurrence[0];
            $data['ends_at'] = $occurrence[1];
            $data['created_at'] = current_time('mysql');
            if (!$wpdb->insert($table, $data, array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s'))) {
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-events', 'invalid');
            }
            $new_event_id = (int) $wpdb->insert_id;
            $saved_room = calsys_sync_linked_room_reservations('event', $new_event_id, $room_id, $occurrence[0], $occurrence[1]);
            if ($saved_room === false) {
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-events', 'invalid');
            }
            $skipped += $saved_room;
            $equipment_result = calsys_sync_equipment('event', $new_event_id, $equipment, $occurrence[0], $occurrence[1], 'approved');
            if ($equipment_result !== 'saved') {
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-events', $equipment_result === 'conflict' ? 'equipment_conflict' : 'invalid');
            }
        }
    }
    if ($wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    calsys_action_redirect($frontend, 'calsys-events', $skipped ? 'saved_room_conflicts' : 'saved', $skipped);
}

/** Handles event saves submitted through the public calendar editor. */
function calsys_front_save_event() {
    calsys_save_event(true);
}
add_action('admin_post_calsys_save_event', 'calsys_save_event');
add_action('admin_post_calsys_front_save_event', 'calsys_front_save_event');

/** Deletes an event, its registrations, and its linked room reservations. */
function calsys_delete_event($frontend = false) {
    calsys_require_staff();
    check_admin_referer($frontend ? 'calsys_front_delete_event' : 'calsys_delete_event');
    $event_id = absint($_POST['event_id'] ?? 0);
    if (!$event_id) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }

    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false || !calsys_lock_linked_room_ids('event', $event_id)) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $registrations_deleted = $wpdb->delete(calsys_table('registrations'), array('event_id' => $event_id), array('%d'));
    $bookings_deleted = $wpdb->delete(calsys_table('bookings'), array('source_type' => 'event', 'source_id' => $event_id), array('%s', '%d'));
    $equipment_deleted = $wpdb->delete(calsys_table('equipment_reservations'), array('source_type' => 'event', 'source_id' => $event_id), array('%s', '%d'));
    $event_deleted = $wpdb->delete(calsys_table('events'), array('id' => $event_id), array('%d'));
    if ($registrations_deleted === false || $bookings_deleted === false || $equipment_deleted === false || $event_deleted !== 1 || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    calsys_action_redirect($frontend, 'calsys-events', 'event_deleted');
}

/** Handles event deletion submitted through the public calendar editor. */
function calsys_front_delete_event() {
    calsys_delete_event(true);
}
add_action('admin_post_calsys_front_delete_event', 'calsys_front_delete_event');

/** Handles event deletion submitted from the WordPress admin event manager. */
function calsys_admin_delete_event() {
    calsys_delete_event(false);
}
add_action('admin_post_calsys_delete_event', 'calsys_admin_delete_event');
