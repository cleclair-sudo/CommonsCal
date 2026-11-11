<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Moves waiting attendees into available places while the event row is locked. */
function calsys_promote_waitlist_locked($event) {
    global $wpdb;
    if ($event->status !== 'published' || empty($event->registration_enabled) || !calsys_event_registration_is_open($event->starts_at)) {
        return array();
    }
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . calsys_table('registrations') . " WHERE event_id = %d",
        $event->id
    ));
    if ($count === null || $count === false) {
        return false;
    }
    $capacity = calsys_event_capacity($event->capacity);
    $waiting = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . calsys_table('waitlist') . " WHERE event_id = %d ORDER BY joined_at ASC, id ASC FOR UPDATE",
        $event->id
    ));
    if ($wpdb->last_error || !is_array($waiting)) {
        return false;
    }
    $promoted = array();
    foreach ($waiting as $person) {
        if ($capacity > 0 && (int) $count >= $capacity) {
            break;
        }
        $inserted = $wpdb->insert(calsys_table('registrations'), array(
            'event_id' => $event->id,
            'name' => $person->name,
            'email' => $person->email,
            'registration_fields' => $person->registration_fields,
            'reminder_opt_in' => $person->reminder_opt_in,
            'registered_at' => current_time('mysql'),
        ));
        if (!$inserted || $wpdb->delete(calsys_table('waitlist'), array('id' => $person->id), array('%d')) !== 1) {
            return false;
        }
        $promoted[] = $person;
        $count++;
    }
    return $promoted;
}

/** Notifies people whose waitlist places were confirmed. */
function calsys_notify_promoted_waitlist($event, $promoted) {
    $all_sent = true;
    foreach ($promoted as $person) {
        $sent = calsys_send_email(
            $person->email,
            sprintf(__('Registration confirmed: %s', 'community-calendar-study-rooms'), $event->title),
            sprintf(
                __('Hello %1$s, a place opened and you are now registered for %2$s on %3$s.', 'community-calendar-study-rooms'),
                $person->name,
                $event->title,
                mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)
            )
        );
        $all_sent = $sent && $all_sent;
    }
    return $all_sent;
}

/** Promotes eligible waitlisted attendees after a capacity or event change. */
function calsys_promote_event_waitlist($event_id) {
    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false) {
        return 'error';
    }
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . calsys_table('events') . " WHERE id = %d FOR UPDATE",
        $event_id
    ));
    $promoted = $event ? calsys_promote_waitlist_locked($event) : false;
    if ($promoted === false || $wpdb->query('COMMIT') === false) {
        error_log('CommonsCal: unable to promote event waitlist: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        return 'error';
    }
    return calsys_notify_promoted_waitlist($event, $promoted) ? 'saved' : 'email_failed';
}

/** Cancels one registration or waitlist entry and fills any available places. */
function calsys_cancel_event_attendee() {
    calsys_require_staff();
    $event_id = absint($_POST['event_id'] ?? 0);
    $attendee_id = absint($_POST['attendee_id'] ?? 0);
    $kind = isset($_POST['kind']) && is_string($_POST['kind']) ? wp_unslash($_POST['kind']) : '';
    check_admin_referer('calsys_cancel_attendee_' . $event_id . '_' . $attendee_id . '_' . $kind);
    if (!$event_id || !$attendee_id || !in_array($kind, array('registration', 'waitlist'), true)) {
        calsys_admin_redirect('calsys-event-registrations', 'invalid');
    }
    global $wpdb;
    $extra = array('event_id' => $event_id);
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_admin_redirect('calsys-event-registrations', 'invalid', $extra);
    }
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . calsys_table('events') . " WHERE id = %d FOR UPDATE",
        $event_id
    ));
    $table = calsys_table($kind === 'registration' ? 'registrations' : 'waitlist');
    $person = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND event_id = %d FOR UPDATE",
        $attendee_id,
        $event_id
    ));
    if (!$event || !$person || $wpdb->delete($table, array('id' => $attendee_id, 'event_id' => $event_id), array('%d', '%d')) !== 1) {
        $wpdb->query('ROLLBACK');
        calsys_admin_redirect('calsys-event-registrations', 'invalid', $extra);
    }
    $promoted = $kind === 'registration' ? calsys_promote_waitlist_locked($event) : array();
    if ($promoted === false || $wpdb->query('COMMIT') === false) {
        error_log('CommonsCal: unable to cancel event registration or promote waitlist: ' . $wpdb->last_error);
        $wpdb->query('ROLLBACK');
        calsys_admin_redirect('calsys-event-registrations', 'invalid', $extra);
    }
    $cancel_sent = !is_email($person->email) || calsys_send_email(
        $person->email,
        sprintf(__('Cancellation: %s', 'community-calendar-study-rooms'), $event->title),
        sprintf(
            $kind === 'registration'
                ? __('Hello %1$s, your registration for %2$s on %3$s has been cancelled by staff.', 'community-calendar-study-rooms')
                : __('Hello %1$s, your waitlist entry for %2$s on %3$s has been cancelled by staff.', 'community-calendar-study-rooms'),
            $person->name,
            $event->title,
            mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)
        )
    );
    $promotions_sent = calsys_notify_promoted_waitlist($event, $promoted);
    calsys_admin_redirect('calsys-event-registrations', $cancel_sent && $promotions_sent ? 'attendee_cancelled' : 'attendee_cancelled_email_failed', $extra);
}
add_action('admin_post_calsys_cancel_event_attendee', 'calsys_cancel_event_attendee');

/** Reconciles waitlists after global capacity changes, in bounded hourly batches. */
function calsys_reconcile_waitlists() {
    $settings = calsys_get_settings()['event_registration'];
    if (empty($settings['enabled'])) {
        return;
    }
    global $wpdb;
    $threshold = (new DateTimeImmutable('now', wp_timezone()))->modify('+' . (int) $settings['cutoff_minutes'] . ' minutes')->format('Y-m-d H:i:s');
    $count = "(SELECT COUNT(*) FROM " . calsys_table('registrations') . " r WHERE r.event_id = e.id)";
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT e.id FROM " . calsys_table('events') . " e INNER JOIN " . calsys_table('waitlist') . " w ON w.event_id = e.id
        WHERE e.status = 'published' AND e.registration_enabled = 1 AND e.starts_at > %s
        AND (e.capacity = 0 OR $count < e.capacity) AND (%d = 0 OR $count < %d)
        ORDER BY e.id ASC LIMIT 100",
        $threshold,
        (int) $settings['max_capacity'],
        (int) $settings['max_capacity']
    ));
    if ($wpdb->last_error || !is_array($ids)) {
        error_log('CommonsCal: unable to reconcile event waitlists: ' . $wpdb->last_error);
        return;
    }
    foreach ($ids as $id) {
        calsys_promote_event_waitlist((int) $id);
    }
}
add_action('calsys_send_due_event_reminders', 'calsys_reconcile_waitlists');
