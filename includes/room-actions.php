<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Validates and creates or updates a study room. */
function calsys_save_room() {
    calsys_require_staff();
    check_admin_referer('calsys_save_room');
    global $wpdb;

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $capacity = absint($_POST['capacity'] ?? 0);
    $room_id = absint($_POST['room_id'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;
    if ($name === '' || strlen($name) > 200 || $capacity < 1) {
        calsys_admin_redirect('calsys-settings', 'invalid');
    }
    $data = array('name' => $name, 'capacity' => $capacity, 'active' => $active);
    if ($room_id) {
        $updated = $wpdb->update(calsys_table('rooms'), $data, array('id' => $room_id), array('%s', '%d', '%d'), array('%d'));
        if ($updated === false || !$wpdb->get_var($wpdb->prepare("SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d", $room_id))) {
            calsys_admin_redirect('calsys-settings', 'invalid');
        }
    } else {
        if (!$wpdb->insert(calsys_table('rooms'), $data, array('%s', '%d', '%d'))) {
            calsys_admin_redirect('calsys-settings', 'invalid');
        }
    }
    calsys_admin_redirect('calsys-settings', 'saved');
}
add_action('admin_post_calsys_save_room', 'calsys_save_room');

/** Validates and saves a patron room request or an immediately confirmed staff booking. */
function calsys_save_booking($staff_booking = false, $frontend = false) {
    global $wpdb;

    if ($staff_booking) {
        calsys_require_staff();
        check_admin_referer(
            $frontend ? 'calsys_front_staff_booking' : 'calsys_staff_booking',
            $frontend ? '_wpnonce_booking' : '_wpnonce'
        );
    } else {
        check_admin_referer('calsys_request_room');
    }
    $request_reason = '';
    if (!$staff_booking) {
        $raw_reason = wp_unslash($_POST['request_reason'] ?? '');
        if (!is_string($raw_reason)) {
            calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
        }
        $request_reason = sanitize_textarea_field($raw_reason);
        if (strlen($request_reason) > 1000) {
            calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
        }
    }
    $booking_id = $frontend && $staff_booking ? absint($_POST['booking_id'] ?? 0) : 0;
    $equipment = calsys_posted_equipment();
    if ($equipment === false) {
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    $booking = calsys_valid_booking_input(
        $_POST['room_id'] ?? 0,
        $_POST['requester_name'] ?? '',
        $_POST['requester_email'] ?? '',
        $_POST['booking_date'] ?? '',
        $_POST['starts_at'] ?? '',
        $_POST['ends_at'] ?? '',
        $staff_booking
    );
    if (!$booking) {
        $date = $_POST['booking_date'] ?? '';
        $start = $_POST['starts_at'] ?? '';
        $end = $_POST['ends_at'] ?? '';
        if (!$staff_booking && is_string($date) && is_string($start) && is_string($end) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) &&
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) &&
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) && $start < $end &&
            !calsys_room_is_within_hours($date, $start, $end)) {
            calsys_action_redirect($frontend, 'calsys-rooms', 'outside_hours');
        }
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    $locked_room = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d AND active = 1 FOR UPDATE",
        $booking['room_id']
    ));
    if (!$locked_room) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    $has_conflict = calsys_booking_conflict($booking['room_id'], $booking['date'], $booking['start'], $booking['end'], $booking_id);
    if ($has_conflict === null) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    if ($has_conflict) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'room_conflict');
    }

    $booking_data = array(
        'room_id' => $booking['room_id'],
        'requester_name' => $booking['name'],
        'requester_email' => $booking['email'],
        'booking_date' => $booking['date'],
        'starts_at' => $booking['start'],
        'ends_at' => $booking['end'],
    );
    if ($staff_booking) {
        $booking_data['source_type'] = 'private';
        $booking_data['source_id'] = 0;
    }
    if ($booking_id) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM " . calsys_table('bookings') . " WHERE id = %d FOR UPDATE",
            $booking_id
        ));
        if (!$existing || !in_array($existing->status, array('pending', 'approved'), true)) {
            $wpdb->query('ROLLBACK');
            calsys_frontend_redirect('invalid_room_request');
        }
        $saved = $wpdb->update(
            calsys_table('bookings'),
            $booking_data,
            array('id' => $booking_id)
        );
        $booking_status = $existing->status;
        $notice = 'room_booking_saved';
    } else {
        $approval_required = (bool) calsys_get_settings()['room_approval_required'];
        $booking_data['status'] = $staff_booking || !$approval_required ? 'approved' : 'pending';
        $booking_status = $booking_data['status'];
        $booking_data['request_reason'] = $request_reason;
        $booking_data['created_at'] = current_time('mysql');
        $saved = $wpdb->insert(calsys_table('bookings'), $booking_data);
        $notice = $staff_booking || !$approval_required ? 'room_booking_saved' : 'room_request_sent';
    }
    if ($saved === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', 'invalid_room_request');
    }
    if (!$booking_id) {
        $booking_id = (int) $wpdb->insert_id;
    }
    $equipment_result = calsys_sync_equipment(
        'room',
        $booking_id,
        $equipment,
        $booking['date'] . ' ' . $booking['start'],
        $booking['date'] . ' ' . $booking['end'],
        $booking_status
    );
    if ($equipment_result !== 'saved' || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-rooms', $equipment_result === 'conflict' ? 'equipment_conflict' : 'invalid_room_request');
    }
    if (!$staff_booking) {
        $settings = calsys_get_settings();
        $booking_status = $booking_data['status'] ?? '';
        $status_text = $booking_status === 'approved'
            ? __('confirmed', 'community-calendar-study-rooms')
            : __('awaiting staff approval', 'community-calendar-study-rooms');
        $booking_summary = sprintf(
            /* translators: 1: room, 2: date, 3: start time, 4: end time, 5: booking status, 6: reason */
            __('Room: %1$s. Date: %2$s. Time: %3$s to %4$s. Status: %5$s. Reason: %6$s', 'community-calendar-study-rooms'),
            $booking['room_name'],
            $booking['date'],
            $booking['start'],
            $booking['end'],
            $status_text,
            $request_reason !== '' ? $request_reason : __('Not provided', 'community-calendar-study-rooms')
        );
        if ($settings['notifications']['notify_staff_room_requests']) {
            calsys_send_email(
                $settings['notifications']['staff_email'],
                __('New room booking request', 'community-calendar-study-rooms'),
                $booking_summary
            );
        }
        if ($settings['notifications']['email_room_requester']) {
            calsys_send_email(
                $booking['email'],
                $booking_status === 'approved'
                    ? __('Room booking confirmed', 'community-calendar-study-rooms')
                    : __('Room request received', 'community-calendar-study-rooms'),
                $booking_status === 'approved'
                    ? sprintf(__('Hello %1$s, your room booking is confirmed. %2$s', 'community-calendar-study-rooms'), $booking['name'], $booking_summary)
                    : sprintf(__('Hello %1$s, we received your room request. %2$s', 'community-calendar-study-rooms'), $booking['name'], $booking_summary)
            );
        }
    }
    if ($staff_booking && !calsys_room_is_within_hours($booking['date'], substr($booking['start'], 0, 5), substr($booking['end'], 0, 5))) {
        $notice = 'room_booking_saved_outside_hours';
    } elseif (!$frontend) {
        $notice = $staff_booking ? 'booking_saved' : 'room_request_sent';
    }
    calsys_action_redirect($frontend, 'calsys-rooms', $notice);
}

/** Handles room bookings created by staff in the admin management screen. */
function calsys_staff_booking() {
    calsys_save_booking(true);
}
add_action('admin_post_calsys_staff_booking', 'calsys_staff_booking');

/** Handles a room request submitted through a public booking form. */
function calsys_public_room_booking() {
    $frontend = !empty($_POST['calsys_frontend']);
    $staff_booking = $frontend && !empty($_POST['staff_booking']);
    if ($staff_booking && !empty($_POST['public_event'])) {
        if (!absint($_POST['room_id'] ?? 0)) {
            calsys_frontend_redirect('invalid');
        }
        $date = sanitize_text_field(wp_unslash($_POST['booking_date'] ?? ''));
        $start = sanitize_text_field(wp_unslash($_POST['starts_at'] ?? ''));
        $end = sanitize_text_field(wp_unslash($_POST['ends_at'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
            !preg_match('/^\d{2}:\d{2}$/', $start) ||
            !preg_match('/^\d{2}:\d{2}$/', $end)) {
            calsys_frontend_redirect('invalid');
        }
        $starts_at = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $start, wp_timezone());
        $ends_at = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $end, wp_timezone());
        $date_errors = DateTimeImmutable::getLastErrors();
        if (!$starts_at || !$ends_at || ($date_errors && ($date_errors['warning_count'] || $date_errors['error_count'])) ||
            $starts_at->format('Y-m-d H:i') !== $date . ' ' . $start ||
            $ends_at->format('Y-m-d H:i') !== $date . ' ' . $end) {
            calsys_frontend_redirect('invalid');
        }
        if ($ends_at <= $starts_at) {
            $ends_at = $ends_at->modify('+1 day');
        }
        $_POST['starts_at'] = $starts_at->format('Y-m-d\TH:i');
        $_POST['ends_at'] = $ends_at->format('Y-m-d\TH:i');
        calsys_save_event(true);
    }
    calsys_save_booking($staff_booking, $frontend);
}
add_action('admin_post_calsys_request_room', 'calsys_public_room_booking');
add_action('admin_post_nopriv_calsys_request_room', 'calsys_public_room_booking');

/** Approves or denies a pending room request after permission and conflict checks. */
function calsys_booking_decision() {
    $frontend = !empty($_POST['calsys_frontend']);
    $admin_page = isset($_POST['calsys_return_page']) && $_POST['calsys_return_page'] === 'calsys-room-approvals'
        ? 'calsys-room-approvals' : 'calsys-rooms';
    calsys_require_staff();
    $booking_id = absint($_POST['booking_id'] ?? 0);
    check_admin_referer(($frontend ? 'calsys_front_booking_decision_' : 'calsys_booking_decision_') . $booking_id);
    $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
    if (!$booking_id || !in_array($decision, array('approve', 'deny'), true)) {
        calsys_action_redirect($frontend, $admin_page, 'invalid');
    }

    global $wpdb;
    $table = calsys_table('bookings');
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_action_redirect($frontend, $admin_page, 'invalid');
    }
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND status = 'pending' FOR UPDATE", $booking_id));
    if (!$booking) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, $admin_page, 'invalid');
    }
    if ($decision === 'approve') {
        $wpdb->get_var($wpdb->prepare("SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d FOR UPDATE", $booking->room_id));
        $has_conflict = calsys_booking_conflict($booking->room_id, $booking->booking_date, $booking->starts_at, $booking->ends_at, $booking_id);
        if ($has_conflict === null) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, $admin_page, 'invalid');
        }
        if ($has_conflict) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, $admin_page, 'conflict');
        }
    }
    $status = $decision === 'approve' ? 'approved' : 'denied';
    $updated = $wpdb->update($table, array('status' => $status), array('id' => $booking_id, 'status' => 'pending'), array('%s'), array('%d', '%s'));
    $equipment_updated = $wpdb->update(calsys_table('equipment_reservations'), array('status' => $status), array('source_type' => 'room', 'source_id' => $booking_id));
    if ($updated === false || $equipment_updated === false || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, $admin_page, 'invalid');
    }
    calsys_action_redirect($frontend, $admin_page, $decision === 'approve' ? 'approved' : 'denied');
}
add_action('admin_post_calsys_booking_decision', 'calsys_booking_decision');

/** Deletes an existing room booking submitted from a staff calendar dialog. */
function calsys_front_delete_room_booking() {
    calsys_require_staff();
    check_admin_referer('calsys_front_delete_room_booking');
    $booking_id = absint($_POST['booking_id'] ?? 0);
    if (!$booking_id) {
        calsys_frontend_redirect('invalid_room_request');
    }

    global $wpdb;
    $bookings_table = calsys_table('bookings');
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_frontend_redirect('invalid_room_request');
    }
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT id, room_id, source_type, source_id FROM $bookings_table WHERE id = %d FOR UPDATE",
        $booking_id
    ));
    if (!$booking) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_room_request');
    }
    $locked_room = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d FOR UPDATE",
        $booking->room_id
    ));
    if (!$locked_room) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_room_request');
    }
    if ($booking->source_type === 'staff') {
        $staff_updated = $wpdb->update(
            calsys_table('staff_calendar'),
            array('room_id' => 0),
            array('id' => $booking->source_id, 'room_id' => $booking->room_id),
            array('%d'),
            array('%d', '%d')
        );
        if ($staff_updated === false) {
            $wpdb->query('ROLLBACK');
            calsys_frontend_redirect('invalid_room_request');
        }
    }
    if ($wpdb->delete(calsys_table('equipment_reservations'), array('source_type' => 'room', 'source_id' => $booking_id)) === false ||
        $wpdb->delete($bookings_table, array('id' => $booking_id), array('%d')) !== 1 ||
        $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_frontend_redirect('invalid_room_request');
    }
    calsys_frontend_redirect('room_booking_deleted');
}
add_action('admin_post_calsys_front_delete_room_booking', 'calsys_front_delete_room_booking');
