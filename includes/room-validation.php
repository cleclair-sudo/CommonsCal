<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Parses a submitted local datetime and returns its database representation. */
function calsys_parse_datetime($value) {
    $value = sanitize_text_field(wp_unslash($value));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i') !== $value) {
        return false;
    }
    return $date->format('Y-m-d H:i:s');
}

/** Validates room, patron, date, and time values against booking rules. */
function calsys_valid_booking_input($room_id, $name, $email, $date, $start, $end, $staff_booking = false) {
    $room = absint($room_id);
    $name = sanitize_text_field(wp_unslash($name));
    $email = sanitize_email(wp_unslash($email));
    $date = sanitize_text_field(wp_unslash($date));
    $start = sanitize_text_field(wp_unslash($start));
    $end = sanitize_text_field(wp_unslash($end));
    global $wpdb;
    $room_record = $room ? $wpdb->get_row($wpdb->prepare(
        "SELECT id, active, name FROM " . calsys_table('rooms') . " WHERE id = %d",
        $room
    )) : null;

    if (!$room_record || !$room_record->active || $name === '' || strlen($name) > 200 || !is_email($email) || strlen($email) > 200) {
        return false;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
        return false;
    }
    $booking_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    $date_errors = DateTimeImmutable::getLastErrors();
    if (!$booking_date || ($date_errors && ($date_errors['warning_count'] || $date_errors['error_count'])) || $booking_date->format('Y-m-d') !== $date || $date < current_time('Y-m-d')) {
        return false;
    }
    $start_time = DateTimeImmutable::createFromFormat('!H:i', $start, wp_timezone());
    $start_errors = DateTimeImmutable::getLastErrors();
    $end_time = DateTimeImmutable::createFromFormat('!H:i', $end, wp_timezone());
    $end_errors = DateTimeImmutable::getLastErrors();
    if (!$start_time || ($start_errors && ($start_errors['warning_count'] || $start_errors['error_count'])) || $start_time->format('H:i') !== $start ||
        !$end_time || ($end_errors && ($end_errors['warning_count'] || $end_errors['error_count'])) || $end_time->format('H:i') !== $end) {
        return false;
    }
    if ($date === current_time('Y-m-d') && $start <= current_time('H:i')) {
        return false;
    }
    if (!$staff_booking) {
        $rules = calsys_get_settings()['booking_rules'];
        if ($rules['max_advance_days'] > 0) {
            $last_bookable_day = DateTimeImmutable::createFromFormat('!Y-m-d', current_time('Y-m-d'), wp_timezone())
                ->modify('+' . (int) $rules['max_advance_days'] . ' days')
                ->format('Y-m-d');
            if ($date > $last_bookable_day) {
                return false;
            }
        }
        $booking_start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $start, wp_timezone());
        if ($rules['min_notice_hours'] > 0 &&
            $booking_start->getTimestamp() < time() + ((int) $rules['min_notice_hours'] * HOUR_IN_SECONDS)) {
            return false;
        }
        if ($rules['time_increment'] > 0) {
            $increment = (int) $rules['time_increment'];
            $start_minutes = ((int) $start_time->format('G') * 60) + (int) $start_time->format('i');
            $end_minutes = ((int) $end_time->format('G') * 60) + (int) $end_time->format('i');
            if ($start_minutes % $increment !== 0 || $end_minutes % $increment !== 0) {
                return false;
            }
        }
        if (!calsys_room_is_within_hours($date, $start, $end, $rules)) {
            return false;
        }
    }
    return array(
        'room_id' => $room,
        'room_name' => $room_record->name,
        'name' => $name,
        'email' => $email,
        'date' => $date,
        'start' => $start . ':00',
        'end' => $end . ':00',
    );
}

/** Checks whether a room has an overlapping pending or approved booking. */
function calsys_booking_conflict($room_id, $date, $start, $end, $exclude_id = 0) {
    global $wpdb;
    $table = calsys_table('bookings');
    $sql = "SELECT COUNT(*) FROM $table WHERE room_id = %d AND booking_date = %s AND status IN ('pending', 'approved') AND starts_at < %s AND ends_at > %s";
    $args = array($room_id, $date, $end, $start);
    if ($exclude_id) {
        $sql .= ' AND id != %d';
        $args[] = $exclude_id;
    }
    $conflicts = $wpdb->get_var($wpdb->prepare($sql, $args));
    if ($conflicts === null || $conflicts === false) {
        return null;
    }
    return (int) $conflicts > 0;
}

/** Returns opening hours and a privacy-preserving room conflict check. */
function calsys_check_room_availability() {
    check_ajax_referer('calsys_room_availability', 'nonce');
    $date = isset($_POST['date']) && is_string($_POST['date']) ? wp_unslash($_POST['date']) : '';
    $hours = calsys_room_hours_for_date($date);
    if ($hours === false) {
        wp_send_json_error(array('message' => __('Please choose a valid date.', 'community-calendar-study-rooms')), 400);
    }
    $room = isset($_POST['room_id']) && is_string($_POST['room_id']) ? wp_unslash($_POST['room_id']) : '';
    $start = isset($_POST['start']) && is_string($_POST['start']) ? wp_unslash($_POST['start']) : '';
    $end = isset($_POST['end']) && is_string($_POST['end']) ? wp_unslash($_POST['end']) : '';
    $conflict = null;
    if ($room !== '' && $start !== '' && $end !== '') {
        if (!ctype_digit($room) || (int) $room < 1 ||
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) ||
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) || $start >= $end) {
            wp_send_json_error(array('message' => __('Choose a valid room and time range.', 'community-calendar-study-rooms')), 400);
        }
        global $wpdb;
        $active = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d AND active = 1", (int) $room));
        if ($wpdb->last_error) {
            error_log('CommonsCal: unable to check room availability: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Room availability could not be checked. Please try again.', 'community-calendar-study-rooms')), 500);
        }
        if (!$active) {
            wp_send_json_error(array('message' => __('That room is unavailable. Please choose another.', 'community-calendar-study-rooms')), 400);
        }
        $exclude_id = current_user_can('manage_calendar_system') ? absint($_POST['booking_id'] ?? 0) : 0;
        $conflict = calsys_booking_conflict((int) $room, $date, $start . ':00', $end . ':00', $exclude_id);
        if ($conflict === null) {
            error_log('CommonsCal: unable to check room conflicts: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Room availability could not be checked. Please try again.', 'community-calendar-study-rooms')), 500);
        }
    }
    wp_send_json_success(array(
        'hours' => array('mode' => $hours['mode'], 'open' => $hours['open'], 'close' => $hours['close']),
        'conflict' => $conflict,
    ));
}
add_action('wp_ajax_calsys_room_availability', 'calsys_check_room_availability');
add_action('wp_ajax_nopriv_calsys_room_availability', 'calsys_check_room_availability');

/** Splits a datetime range into daily intervals suitable for room reservations. */
function calsys_schedule_segments($starts_at, $ends_at) {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $starts_at, wp_timezone());
    $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $ends_at, wp_timezone());
    if (!$start || !$end || $end <= $start) {
        return false;
    }
    if ($end->format('H:i:s') === '00:00:00') {
        $end = $end->modify('-1 second');
    }
    $segments = array();
    for ($day = $start->setTime(0, 0); $day <= $end->setTime(0, 0); $day = $day->modify('+1 day')) {
        $date = $day->format('Y-m-d');
        $segment_start = $date === $start->format('Y-m-d') ? $start : $day;
        $segment_end = $date === $end->format('Y-m-d') ? $end : $day->setTime(23, 59, 59);
        $segments[] = array(
            'date' => $date,
            'start' => $segment_start->format('H:i:s'),
            'end' => $segment_end->format('H:i:s'),
        );
    }
    return $segments;
}

/** Locks rooms linked to a source booking to prevent concurrent conflicting updates. */
function calsys_lock_linked_room_ids($source_type, $source_id, $new_room_id = 0) {
    global $wpdb;
    $room_ids = array();
    $existing_room_ids = array();
    if ($source_id) {
        $existing = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT room_id FROM " . calsys_table('bookings') . " WHERE source_type = %s AND source_id = %d",
            $source_type,
            $source_id
        ));
        if ($existing === null) {
            return false;
        }
        $existing_room_ids = array_map('absint', $existing);
        $room_ids = $existing_room_ids;
    }
    if ($new_room_id) {
        $room_ids[] = absint($new_room_id);
    }
    $room_ids = array_values(array_unique(array_filter($room_ids)));
    sort($room_ids, SORT_NUMERIC);
    if (!$room_ids) {
        return true;
    }
    $placeholders = implode(',', array_fill(0, count($room_ids), '%d'));
    $locked = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM " . calsys_table('rooms') . " WHERE id IN ($placeholders) ORDER BY id FOR UPDATE",
        $room_ids
    ));
    if (count($locked) !== count($room_ids) || ($new_room_id && !in_array((string) $new_room_id, array_map('strval', $locked), true))) {
        return false;
    }
    return !$new_room_id || in_array(absint($new_room_id), $existing_room_ids, true) || (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d AND active = 1 FOR UPDATE",
        $new_room_id
    )) === $new_room_id;
}

/** Reconciles linked room reservations with the current source schedule. */
function calsys_sync_linked_room_reservations($source_type, $source_id, $room_id, $starts_at, $ends_at) {
    global $wpdb;
    $bookings_table = calsys_table('bookings');
    if ($wpdb->delete($bookings_table, array('source_type' => $source_type, 'source_id' => $source_id), array('%s', '%d')) === false) {
        return false;
    }
    if (!$room_id) {
        return 0;
    }
    $segments = calsys_schedule_segments($starts_at, $ends_at);
    if ($segments === false) {
        return false;
    }
    foreach ($segments as $segment) {
        $conflict = calsys_booking_conflict($room_id, $segment['date'], $segment['start'], $segment['end']);
        if ($conflict === null) {
            return false;
        }
        if ($conflict) {
            return 1;
        }
    }
    foreach ($segments as $segment) {
        $inserted = $wpdb->insert($bookings_table, array(
            'room_id' => $room_id,
            'requester_name' => $source_type === 'event' ? __('Calendar event', 'community-calendar-study-rooms') : __('Staff calendar', 'community-calendar-study-rooms'),
            'requester_email' => sanitize_email(get_option('admin_email')),
            'request_reason' => '',
            'booking_date' => $segment['date'],
            'starts_at' => $segment['start'],
            'ends_at' => $segment['end'],
            'status' => 'approved',
            'source_type' => $source_type,
            'source_id' => $source_id,
            'created_at' => current_time('mysql'),
        ), array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'));
        if ($inserted === false) {
            return false;
        }
    }
    return 0;
}
