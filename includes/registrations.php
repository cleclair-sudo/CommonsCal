<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Registers the selected event and any eligible related events. */
function calsys_register_event() {
    check_admin_referer('calsys_register_event');
    global $wpdb;

    $event_id = absint($_POST['event_id'] ?? 0);
    if (!isset($_POST['name'], $_POST['email']) || !is_string($_POST['name']) || !is_string($_POST['email'])) {
        calsys_frontend_redirect('invalid_registration');
    }
    $name = sanitize_text_field(wp_unslash($_POST['name']));
    $email = sanitize_email(wp_unslash($_POST['email']));
    $event = $event_id ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . calsys_table('events') . " WHERE id = %d AND status = 'published'",
        $event_id
    )) : null;
    if (!$event) {
        calsys_frontend_redirect('invalid_registration');
    }
    $multi_requested = !empty($_POST['series_events']) || !empty($_POST['series_select_all']) || !empty($_POST['all_recurrences']);
    if (!calsys_get_settings()['event_registration']['enabled'] || (empty($event->registration_enabled) && !$multi_requested)) {
        calsys_frontend_redirect('registration_disabled');
    }
    if (!calsys_event_registration_is_open($event->starts_at) && !$multi_requested) {
        calsys_frontend_redirect('registration_closed');
    }
    if ($name === '' || strlen($name) > 200 || !is_email($email) || strlen($email) > 200) {
        calsys_frontend_redirect('invalid_registration');
    }
    $event_fields = calsys_decode_registration_fields($event->registration_fields ?? '');
    $posted_answers = isset($_POST['registration_fields']) ? wp_unslash($_POST['registration_fields']) : array();
    $answers = calsys_validate_registration_answers($event_fields, $posted_answers);
    if ($answers === false) {
        calsys_frontend_redirect('invalid_registration');
    }
    $notification_settings = calsys_get_settings()['notifications'];
    $reminder_opt_in = $notification_settings['email_event_reminders'] &&
        isset($_POST['reminder_opt_in']) && is_scalar($_POST['reminder_opt_in']) &&
        wp_unslash($_POST['reminder_opt_in']) === '1';

    $extra_ids = $_POST['series_events'] ?? array();
    if (!is_array($extra_ids) || count($extra_ids) > 1000) {
        calsys_frontend_redirect('invalid_registration');
    }
    $ids = array($event_id);
    foreach ($extra_ids as $raw_id) {
        if (!is_string($raw_id) || !preg_match('/^[1-9][0-9]*$/', $raw_id)) {
            calsys_frontend_redirect('invalid_registration');
        }
        $ids[] = (int) $raw_id;
    }
    if (isset($_POST['series_select_all'])) {
        if ($_POST['series_select_all'] !== '1' || empty($event->series_id)) {
            calsys_frontend_redirect('invalid_registration');
        }
        $series_events = $wpdb->get_results($wpdb->prepare(
            "SELECT id, starts_at, registration_fields FROM " . calsys_table('events') . "
            WHERE series_id = %d AND status = 'published' AND registration_enabled = 1 ORDER BY starts_at, id",
            $event->series_id
        ));
        if (!is_array($series_events) || $wpdb->last_error) {
            error_log('CommonsCal: unable to load series events for registration: ' . $wpdb->last_error);
            calsys_frontend_redirect('invalid_registration');
        }
        foreach ($series_events as $series_event) {
            if (calsys_event_registration_is_open($series_event->starts_at) &&
                calsys_decode_registration_fields($series_event->registration_fields ?? '') === $event_fields) {
                $ids[] = (int) $series_event->id;
            }
        }
    }
    if (isset($_POST['all_recurrences'])) {
        if ($_POST['all_recurrences'] !== '1' || empty($event->register_all_recurrences) || empty($event->recurrence_group)) {
            calsys_frontend_redirect('invalid_registration');
        }
        $recurrence_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM " . calsys_table('events') . " WHERE recurrence_group = %s AND status = 'published' AND starts_at > %s ORDER BY starts_at, id",
            $event->recurrence_group,
            current_time('mysql')
        ));
        if (!is_array($recurrence_ids) || $wpdb->last_error) {
            error_log('CommonsCal: unable to load recurring events for registration: ' . $wpdb->last_error);
            calsys_frontend_redirect('invalid_registration');
        }
        $ids = array_merge($ids, array_map('intval', $recurrence_ids));
    }
    $ids = array_values(array_unique($ids));
    $results = array();
    foreach ($ids as $id) {
        $results[] = calsys_register_one_event(
            $id, $event, $name, $email, $event_fields, $answers, $reminder_opt_in,
            $id === $event_id && ($_POST['join_waitlist'] ?? '') === '1',
            $id !== $event_id && isset($_POST['all_recurrences'])
        );
    }
    if (count($results) === 1) {
        calsys_frontend_redirect($results[0]['status']);
    }
    $token = wp_generate_uuid4();
    if (!set_transient('calsys_registration_result_' . $token, $results, 10 * MINUTE_IN_SECONDS)) {
        error_log('CommonsCal: unable to store multi-event registration results');
        calsys_frontend_redirect('registration_results_unavailable');
    }
    calsys_frontend_redirect('registration_results', array('calsys_registration_result' => $token));
}
add_action('admin_post_calsys_register_event', 'calsys_register_event');
add_action('admin_post_nopriv_calsys_register_event', 'calsys_register_event');

/** Determines whether an event may be included in this registration. */
function calsys_registration_related_event($event, $anchor, $allow_recurrence) {
    return (int) $event->id === (int) $anchor->id ||
        (!empty($anchor->series_id) && (int) $anchor->series_id === (int) ($event->series_id ?? 0)) ||
        ($allow_recurrence && !empty($anchor->register_all_recurrences) && !empty($anchor->recurrence_group) &&
            $anchor->recurrence_group === ($event->recurrence_group ?? ''));
}

/** Registers one occurrence using the existing per-event capacity and waitlist rules. */
function calsys_register_one_event($event_id, $anchor, $name, $email, $event_fields, $answers, $reminder_opt_in, $join_waitlist, $allow_recurrence) {
    global $wpdb;
    $events_table = calsys_table('events');
    $notification_settings = calsys_get_settings()['notifications'];
    $result = array('id' => $event_id, 'title' => '', 'starts_at' => '', 'status' => 'invalid_registration');
    $candidate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d AND status = 'published'", $event_id
    ));
    if (!$candidate || !calsys_registration_related_event($candidate, $anchor, $allow_recurrence)) {
        return $result;
    }
    $result['title'] = $candidate->title;
    $result['starts_at'] = $candidate->starts_at;
    if (calsys_decode_registration_fields($candidate->registration_fields ?? '') !== $event_fields) {
        return $result;
    }
    if (calsys_promote_event_waitlist($event_id) === 'error') {
        return $result;
    }
    $registrations_table = calsys_table('registrations');
    if ($wpdb->query('START TRANSACTION') === false) {
        return $result;
    }
    $locked_event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d AND status = 'published' FOR UPDATE",
        $event_id
    ));
    if (!$locked_event) {
        $wpdb->query('ROLLBACK');
        return $result;
    }
    $result['title'] = $locked_event->title;
    $result['starts_at'] = $locked_event->starts_at;
    if (!calsys_registration_related_event($locked_event, $anchor, $allow_recurrence) ||
        calsys_decode_registration_fields($locked_event->registration_fields ?? '') !== $event_fields) {
        $wpdb->query('ROLLBACK');
        return $result;
    }
    if (empty($locked_event->registration_enabled)) {
        $wpdb->query('ROLLBACK');
        $result['status'] = 'registration_disabled';
        return $result;
    }
    if (!calsys_event_registration_is_open($locked_event->starts_at)) {
        $wpdb->query('ROLLBACK');
        $result['status'] = 'registration_closed';
        return $result;
    }
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $registrations_table WHERE event_id = %d AND email = %s",
        $event_id,
        strtolower($email)
    ));
    if ($wpdb->last_error) {
        error_log('CommonsCal: unable to check event registration: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        return $result;
    }
    $waiting = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('waitlist') . " WHERE event_id = %d AND email = %s",
        $event_id,
        strtolower($email)
    ));
    if ($wpdb->last_error) {
        error_log('CommonsCal: unable to check event waitlist: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        return $result;
    }
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $registrations_table WHERE event_id = %d",
        $event_id
    ));
    if ($count === null || $count === false || $wpdb->last_error) {
        error_log('CommonsCal: unable to check event waitlist: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        return $result;
    }
    if ($existing || $waiting) {
        $wpdb->query('ROLLBACK');
        $result['status'] = 'already_registered';
        return $result;
    }
    $effective_capacity = calsys_event_capacity($locked_event->capacity);
    if ($effective_capacity > 0 && (int) $count >= $effective_capacity) {
        if (!$join_waitlist) {
            $wpdb->query('ROLLBACK');
            $result['status'] = 'event_full';
            return $result;
        }
        $inserted = $wpdb->insert(calsys_table('waitlist'), array(
            'event_id' => $event_id,
            'name' => $name,
            'email' => strtolower($email),
            'registration_fields' => wp_json_encode($answers),
            'reminder_opt_in' => $reminder_opt_in ? 1 : 0,
            'joined_at' => current_time('mysql'),
        ));
        if (!$inserted || $wpdb->query('COMMIT') === false) {
            error_log('CommonsCal: unable to save event waitlist: ' . $wpdb->last_error);
            $wpdb->query('ROLLBACK');
            return $result;
        }
        $sent = calsys_send_email(
            strtolower($email),
            sprintf(__('Waitlist joined: %s', 'community-calendar-study-rooms'), $locked_event->title),
            sprintf(__('Hello %1$s, you are on the waitlist for %2$s. We will email you if a place becomes available.', 'community-calendar-study-rooms'), $name, $locked_event->title),
            'waitlist_joined',
            array('name' => $name, 'event' => $locked_event->title, 'datetime' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $locked_event->starts_at))
        );
        $result['status'] = $sent ? 'waitlist_joined' : 'waitlist_joined_email_failed';
        return $result;
    }

    $inserted = $wpdb->insert($registrations_table, array(
        'event_id' => $event_id,
        'name' => $name,
        'email' => strtolower($email),
        'registration_fields' => wp_json_encode($answers),
        'reminder_opt_in' => $reminder_opt_in ? 1 : 0,
        'registered_at' => current_time('mysql'),
    ), array('%d', '%s', '%s', '%s', '%d', '%s'));
    if (!$inserted || $wpdb->query('COMMIT') === false) {
        error_log('CommonsCal: unable to save event registration: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        return $result;
    }
    $event = $locked_event;
    $confirmation_sent = true;
    if ($notification_settings['email_event_registrant']) {
        $confirmation_sent = calsys_send_email(
            strtolower($email),
            sprintf(__('Registration confirmed: %s', 'community-calendar-study-rooms'), $event->title),
            sprintf(
                __('Hello %1$s, your registration for %2$s is confirmed for %3$s.', 'community-calendar-study-rooms'),
                $name,
                $event->title,
                mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)
            ),
            'event_registration',
            array('name' => $name, 'event' => $event->title, 'datetime' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at))
        );
    }
    $registration_recipients = calsys_decode_registration_recipients($event->registration_recipients ?? '');
    if ($notification_settings['notify_staff_event_registrations']) {
        $registration_recipients[] = $notification_settings['staff_email'];
    }
    $registration_recipients = array_values(array_unique(array_filter($registration_recipients, 'is_email')));
    if ($registration_recipients) {
        $answer_lines = array();
        foreach ($answers as $answer) {
            $answer_lines[] = $answer['label'] . ': ' . $answer['value'];
        }
        $message = sprintf(
            __("A new registration was received.\n\nEvent: %1\$s\nRegistrant: %2\$s\nEmail: %3\$s\nDate: %4\$s", 'community-calendar-study-rooms'),
            $event->title,
            $name,
            strtolower($email),
            mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)
        );
        if ($answer_lines) {
            $message .= "\n\n" . __('Registration details:', 'community-calendar-study-rooms') . "\n" . implode("\n", $answer_lines);
        }
        calsys_send_email(
            $registration_recipients,
            sprintf(__('New registration: %s', 'community-calendar-study-rooms'), $event->title),
            $message,
            'event_registration_staff',
            array('name' => $name, 'email' => strtolower($email), 'event' => $event->title,
                'datetime' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at),
                'answers' => implode("\n", $answer_lines))
        );
    }
    $result['status'] = $confirmation_sent ? 'registration_complete' : 'registration_email_failed';
    return $result;
}

/** Validates attendee answers against an event's custom registration fields. */
function calsys_validate_registration_answers($fields, $raw_answers) {
    if (!is_array($raw_answers)) {
        return false;
    }
    $answers = array();
    foreach (array_values($fields) as $index => $field) {
        $raw_value = $raw_answers[$index] ?? '';
        if (is_array($raw_value) || is_object($raw_value)) {
            return false;
        }
        $value = is_scalar($raw_value) ? (string) wp_unslash($raw_value) : '';
        if ($field['type'] === 'checkbox') {
            if ($value !== '' && $value !== '1') {
                return false;
            }
            if (!empty($field['required']) && $value !== '1') {
                return false;
            }
            $value = $value === '1' ? __('Yes', 'community-calendar-study-rooms') : __('No', 'community-calendar-study-rooms');
        } elseif ($field['type'] === 'textarea') {
            $value = sanitize_textarea_field($value);
        } else {
            $value = sanitize_text_field($value);
        }
        if (strlen($value) > 1000 || (!empty($field['required']) && $value === '')) {
            return false;
        }
        if ($field['type'] === 'email' && $value !== '' && !is_email($value)) {
            return false;
        }
        if ($field['type'] === 'select' && $value !== '' && !in_array($value, $field['options'], true)) {
            return false;
        }
        $answers[] = array('label' => $field['label'], 'value' => $value);
    }
    return $answers;
}

/** Sends due reminders to opted-in registrants and records successful sends. */
function calsys_send_due_event_reminders() {
    if (!calsys_get_settings()['notifications']['email_event_reminders']) {
        return;
    }
    global $wpdb;
    $now = current_time('mysql');
    $deadline = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $now, wp_timezone())
        ->modify('+24 hours')->format('Y-m-d H:i:s');
    $last_start = '';
    $last_id = 0;
    do {
        $cursor = $last_start === '' ? '' : $wpdb->prepare(
            ' AND (e.starts_at > %s OR (e.starts_at = %s AND r.id > %d))',
            $last_start, $last_start, $last_id
        );
        $due = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.name, r.email, e.title, e.starts_at
            FROM " . calsys_table('registrations') . " r
            INNER JOIN " . calsys_table('events') . " e ON e.id = r.event_id
            WHERE r.reminder_opt_in = 1 AND r.reminder_sent_at IS NULL
                AND e.status = 'published' AND e.starts_at > %s AND e.starts_at <= %s $cursor
            ORDER BY e.starts_at ASC, r.id ASC LIMIT 500",
            $now,
            $deadline
        ));
        if (!is_array($due) || $wpdb->last_error !== '') {
            error_log('CommonsCal: unable to load pending event reminders: ' . $wpdb->last_error);
            return;
        }
        foreach ($due as $registration) {
            $last_start = $registration->starts_at;
            $last_id = (int) $registration->id;
            $claimed = $wpdb->update(
                calsys_table('registrations'),
                array('reminder_sent_at' => $now),
                array('id' => $registration->id, 'reminder_sent_at' => null),
                array('%s'),
                array('%d', '%s')
            );
            if ($claimed === false) {
                error_log('CommonsCal: unable to claim an event reminder: ' . $wpdb->last_error);
                continue;
            }
            if ($claimed !== 1) {
                continue;
            }
            $sent = calsys_send_email(
                $registration->email,
                sprintf(__('Reminder: %s is coming up', 'community-calendar-study-rooms'), $registration->title),
                sprintf(
                    __('Hello %1$s, this is a reminder that %2$s starts on %3$s.', 'community-calendar-study-rooms'),
                    $registration->name,
                    $registration->title,
                    mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $registration->starts_at)
                ),
                'event_reminder',
                array('name' => $registration->name, 'event' => $registration->title,
                    'datetime' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $registration->starts_at))
            );
            if (!$sent) {
                $reset = $wpdb->update(
                    calsys_table('registrations'),
                    array('reminder_sent_at' => null),
                    array('id' => $registration->id),
                    array('%s'),
                    array('%d')
                );
                if ($reset === false) {
                    error_log('CommonsCal: unable to retry a failed event reminder: ' . $wpdb->last_error);
                }
            }
        }
    } while (count($due) === 500);
}
add_action('calsys_send_due_event_reminders', 'calsys_send_due_event_reminders');
