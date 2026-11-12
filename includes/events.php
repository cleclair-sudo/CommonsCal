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
    if (!isset($_POST['staff_initials']) || !is_string($_POST['staff_initials'])) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $staff_initials = sanitize_text_field(wp_unslash($_POST['staff_initials']));
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
    $series_choice = $_POST['series_id'] ?? null;
    if ($series_choice !== null && (!is_string($series_choice) || !preg_match('/^(?:0|new|[1-9][0-9]*)$/', $series_choice))) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $series_name = '';
    if ($series_choice === 'new') {
        if (!isset($_POST['series_name']) || !is_string($_POST['series_name'])) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
        $series_name = sanitize_text_field(wp_unslash($_POST['series_name']));
        if ($series_name === '' || strlen($series_name) > 200) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
    }
    if (isset($_POST['register_all_recurrences']) && $_POST['register_all_recurrences'] !== '1') {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $edit_scope = isset($_POST['event_scope']) && is_string($_POST['event_scope']) ? wp_unslash($_POST['event_scope']) : 'one';
    if (!in_array($edit_scope, array('one', 'future', 'all'), true) || (!$event_id && $edit_scope !== 'one')) {
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $frequency = $recurring ? sanitize_key(wp_unslash($_POST['recurrence_frequency'] ?? '')) : 'none';
    $end_type = $recurring ? sanitize_key(wp_unslash($_POST['recurrence_end_type'] ?? '')) : 'count';
    $occurrence_count = absint($_POST['recurrence_count'] ?? 0);
    $until = sanitize_text_field(wp_unslash($_POST['recurrence_until'] ?? ''));

    if ($title === '' || strlen($title) > 200 || $staff_initials === '' || strlen($staff_initials) > 40 || !$starts_at || !$ends_at || $ends_at <= $starts_at ||
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
    $room_form_booking = $frontend && !empty($_POST['staff_booking']) && !empty($_POST['public_event']);
    $allow_first_conflict = $room_form_booking && ($_POST['confirm_room_conflict'] ?? '') === '1';
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
        'staff_initials' => $staff_initials,
    );
    $formats = array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s');
    if (isset($_POST['show_registration_counts_present'])) {
        if ($_POST['show_registration_counts_present'] !== '1' ||
            (isset($_POST['show_registration_counts']) && $_POST['show_registration_counts'] !== '1')) {
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
        $data['show_registration_counts'] = isset($_POST['show_registration_counts']) ? 1 : 0;
        $formats[] = '%d';
    } elseif (!$event_id) {
        $data['show_registration_counts'] = 1;
        $formats[] = '%d';
    }
    if ($wpdb->query('START TRANSACTION') === false || !calsys_lock_linked_room_ids('event', $event_id, $room_id)) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $table = calsys_table('events');
    if ($series_choice !== null) {
        if ($series_choice === 'new') {
            if (!$wpdb->insert(calsys_table('series'), array('name' => $series_name), array('%s'))) {
                error_log('CommonsCal: unable to create event series: ' . $wpdb->last_error);
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-events', 'invalid');
            }
            $series_id = (int) $wpdb->insert_id;
        } else {
            $series_id = (int) $series_choice;
            if ($series_id && !$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . calsys_table('series') . " WHERE id = %d FOR UPDATE",
                $series_id
            ))) {
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-events', 'invalid');
            }
        }
        $data['series_id'] = $series_id;
        $formats[] = '%d';
        $register_all = isset($_POST['register_all_recurrences']);
        if ($register_all && !$recurring && (!$event_id || !$wpdb->get_var($wpdb->prepare(
            "SELECT recurrence_group FROM $table WHERE id = %d FOR UPDATE", $event_id
        )))) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-events', 'invalid');
        }
        $data['register_all_recurrences'] = $register_all ? 1 : 0;
        $formats[] = '%d';
    }
    $skipped = 0;
    $updated_ids = array();
    if ($event_id && $edit_scope !== 'one') {
        $updated_ids = calsys_update_event_series($event_id, $edit_scope, $data, $room_id, $equipment);
        if (!is_array($updated_ids)) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-events', is_string($updated_ids) ? $updated_ids : 'invalid');
        }
    } elseif ($event_id) {
        unset($data['recurrence_group']);
        array_splice($formats, 11, 1);
        $updated = $wpdb->update($table, $data, array('id' => $event_id), $formats, array('%d'));
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
        $updated_ids[] = $event_id;
    } else {
        foreach ($occurrences as $index => $occurrence) {
            $data['starts_at'] = $occurrence[0];
            $data['ends_at'] = $occurrence[1];
            $data['created_at'] = current_time('mysql');
            if (!$wpdb->insert($table, $data, array_merge($formats, array('%s')))) {
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-events', 'invalid');
            }
            $new_event_id = (int) $wpdb->insert_id;
            $saved_room = calsys_sync_linked_room_reservations('event', $new_event_id, $room_id, $occurrence[0], $occurrence[1], $index === 0 && $allow_first_conflict);
            if ($saved_room === false) {
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-events', 'invalid');
            }
            if ($saved_room && $room_form_booking && $index === 0) {
                $wpdb->query('ROLLBACK');
                calsys_action_redirect($frontend, 'calsys-rooms', 'room_conflict');
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
    $waitlist_email_failed = false;
    foreach ($updated_ids as $updated_id) {
        $waitlist_result = calsys_promote_event_waitlist($updated_id);
        if ($waitlist_result === 'error') {
            calsys_action_redirect($frontend, 'calsys-events', 'saved_waitlist_error');
        }
        $waitlist_email_failed = $waitlist_email_failed || $waitlist_result === 'email_failed';
    }
    if ($waitlist_email_failed) {
        calsys_action_redirect($frontend, 'calsys-events', 'saved_waitlist_email_failed');
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
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . calsys_table('events') . " WHERE id = %d FOR UPDATE", $event_id));
    if (!$event) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $attendees = $wpdb->get_results($wpdb->prepare(
        "SELECT name, email FROM " . calsys_table('registrations') . " WHERE event_id = %d",
        $event_id
    ));
    if ($wpdb->last_error || !is_array($attendees)) {
        error_log('CommonsCal: unable to load event attendees for cancellation: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $waiting = $wpdb->get_results($wpdb->prepare(
        "SELECT name, email FROM " . calsys_table('waitlist') . " WHERE event_id = %d",
        $event_id
    ));
    if ($wpdb->last_error || !is_array($waiting)) {
        error_log('CommonsCal: unable to load event attendees for cancellation: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $registrations_deleted = $wpdb->delete(calsys_table('registrations'), array('event_id' => $event_id), array('%d'));
    $waitlist_deleted = $wpdb->delete(calsys_table('waitlist'), array('event_id' => $event_id), array('%d'));
    $bookings_deleted = $wpdb->delete(calsys_table('bookings'), array('source_type' => 'event', 'source_id' => $event_id), array('%s', '%d'));
    $equipment_deleted = $wpdb->delete(calsys_table('equipment_reservations'), array('source_type' => 'event', 'source_id' => $event_id), array('%s', '%d'));
    $event_deleted = $wpdb->delete(calsys_table('events'), array('id' => $event_id), array('%d'));
    if ($registrations_deleted === false || $waitlist_deleted === false || $bookings_deleted === false || $equipment_deleted === false || $event_deleted !== 1 || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-events', 'invalid');
    }
    $email_failed = false;
    if ($event->starts_at > current_time('mysql')) {
        foreach (array_merge($attendees, $waiting) as $person) {
            if (!is_email($person->email)) {
                continue;
            }
            $sent = calsys_send_email(
                $person->email,
                sprintf(__('Event cancelled: %s', 'community-calendar-study-rooms'), $event->title),
                sprintf(__('Hello %1$s, %2$s scheduled for %3$s has been cancelled.', 'community-calendar-study-rooms'),
                    $person->name,
                    $event->title,
                    mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at))
            );
            $email_failed = !$sent || $email_failed;
        }
    }
    calsys_action_redirect($frontend, 'calsys-events', $email_failed ? 'event_deleted_email_failed' : 'event_deleted');
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
