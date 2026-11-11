<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Validates and records an event registration and sends configured notifications. */
function calsys_register_event() {
    check_admin_referer('calsys_register_event');
    global $wpdb;

    $event_id = absint($_POST['event_id'] ?? 0);
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $events_table = calsys_table('events');
    $event = $event_id ? $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d AND status = 'published'",
        $event_id
    )) : null;
    if (!$event) {
        calsys_frontend_redirect('invalid_registration');
    }
    if (!calsys_get_settings()['event_registration']['enabled'] || empty($event->registration_enabled)) {
        calsys_frontend_redirect('registration_disabled');
    }
    if (!calsys_event_registration_is_open($event->starts_at)) {
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

    $registrations_table = calsys_table('registrations');
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_frontend_redirect('invalid_registration');
    }
    $locked_event = $wpdb->get_row($wpdb->prepare(
        "SELECT id, capacity, starts_at, registration_enabled FROM $events_table WHERE id = %d AND status = 'published' AND starts_at > %s FOR UPDATE",
        $event_id,
        current_time('mysql')
    ));
    if (!$locked_event) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_registration');
    }
    if (empty($locked_event->registration_enabled)) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('registration_disabled');
    }
    if (!calsys_event_registration_is_open($locked_event->starts_at)) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('registration_closed');
    }
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $registrations_table WHERE event_id = %d AND email = %s",
        $event_id,
        strtolower($email)
    ));
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $registrations_table WHERE event_id = %d",
        $event_id
    ));
    if ($existing) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('already_registered');
    }
    $effective_capacity = calsys_event_capacity($locked_event->capacity);
    if ($effective_capacity > 0 && $count >= $effective_capacity) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('event_full');
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
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_registration');
    }
    if ($notification_settings['email_event_registrant']) {
        calsys_send_email(
            strtolower($email),
            sprintf(__('Registration confirmed: %s', 'community-calendar-study-rooms'), $event->title),
            sprintf(
                __('Hello %1$s, your registration for %2$s is confirmed for %3$s.', 'community-calendar-study-rooms'),
                $name,
                $event->title,
                mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)
            )
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
            $message
        );
    }
    calsys_frontend_redirect('registration_complete');
}
add_action('admin_post_calsys_register_event', 'calsys_register_event');
add_action('admin_post_nopriv_calsys_register_event', 'calsys_register_event');

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
    $due = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.name, r.email, e.title, e.starts_at
        FROM " . calsys_table('registrations') . " r
        INNER JOIN " . calsys_table('events') . " e ON e.id = r.event_id
        WHERE r.reminder_opt_in = 1 AND r.reminder_sent_at IS NULL
            AND e.status = 'published' AND e.starts_at > %s AND e.starts_at <= %s
        ORDER BY e.starts_at ASC LIMIT 500",
        $now,
        $deadline
    ));
    if ($due === null) {
        error_log('CommonsCal: unable to load pending event reminders: ' . $wpdb->last_error);
        return;
    }
    foreach ($due as $registration) {
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
            )
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
}
add_action('calsys_send_due_event_reminders', 'calsys_send_due_event_reminders');
