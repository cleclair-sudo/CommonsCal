<?php
if (!defined('ABSPATH')) {
    exit;
}

function calsys_privacy_exporters($exporters) {
    $exporters['commonscal'] = array('exporter_friendly_name' => __('CommonsCal bookings and registrations', 'community-calendar-study-rooms'), 'callback' => 'calsys_export_personal_data');
    return $exporters;
}
add_filter('wp_privacy_personal_data_exporters', 'calsys_privacy_exporters');

function calsys_privacy_erasers($erasers) {
    $erasers['commonscal'] = array('eraser_friendly_name' => __('CommonsCal bookings and registrations', 'community-calendar-study-rooms'), 'callback' => 'calsys_erase_personal_data');
    return $erasers;
}
add_filter('wp_privacy_personal_data_erasers', 'calsys_privacy_erasers');

/** Exports records matching the verified email address through WordPress privacy tools. */
function calsys_export_personal_data($email, $page = 1) {
    global $wpdb;
    if (!is_email($email)) {
        return new WP_Error('calsys_invalid_email', __('A valid email address is required.', 'community-calendar-study-rooms'));
    }
    $limit = 50;
    $offset = max(0, ((int) $page - 1) * $limit);
    $tables = array(
        'room' => $wpdb->prepare(
            "SELECT b.*, r.name AS room_name FROM " . calsys_table('bookings') . " b LEFT JOIN " . calsys_table('rooms') . " r ON r.id = b.room_id WHERE b.requester_email = %s ORDER BY b.id LIMIT %d OFFSET %d",
            $email, $limit, $offset
        ),
        'registration' => $wpdb->prepare(
            "SELECT r.*, e.title AS event_title FROM " . calsys_table('registrations') . " r LEFT JOIN " . calsys_table('events') . " e ON e.id = r.event_id WHERE r.email = %s ORDER BY r.id LIMIT %d OFFSET %d",
            $email, $limit, $offset
        ),
        'waitlist' => $wpdb->prepare(
            "SELECT w.*, e.title AS event_title FROM " . calsys_table('waitlist') . " w LEFT JOIN " . calsys_table('events') . " e ON e.id = w.event_id WHERE w.email = %s ORDER BY w.id LIMIT %d OFFSET %d",
            $email, $limit, $offset
        ),
    );
    $items = array();
    $done = true;
    foreach ($tables as $kind => $query) {
        $rows = $wpdb->get_results($query);
        if ($wpdb->last_error || !is_array($rows)) {
            error_log('CommonsCal: personal data export failed: ' . $wpdb->last_error);
            return new WP_Error('calsys_export_failed', __('CommonsCal personal data could not be exported. Check the site error log.', 'community-calendar-study-rooms'));
        }
        if (count($rows) === $limit) {
            $done = false;
        }
        foreach ($rows as $row) {
            if ($kind === 'room') {
                $data = array(
                    array('name' => __('Name', 'community-calendar-study-rooms'), 'value' => $row->requester_name),
                    array('name' => __('Email', 'community-calendar-study-rooms'), 'value' => $row->requester_email),
                    array('name' => __('Room', 'community-calendar-study-rooms'), 'value' => $row->room_name),
                    array('name' => __('Date and time', 'community-calendar-study-rooms'), 'value' => $row->booking_date . ' ' . $row->starts_at . ' - ' . $row->ends_at),
                    array('name' => __('Reason', 'community-calendar-study-rooms'), 'value' => $row->request_reason),
                    array('name' => __('Status', 'community-calendar-study-rooms'), 'value' => $row->status),
                );
            } else {
                $data = array(
                    array('name' => __('Name', 'community-calendar-study-rooms'), 'value' => $row->name),
                    array('name' => __('Email', 'community-calendar-study-rooms'), 'value' => $row->email),
                    array('name' => __('Event', 'community-calendar-study-rooms'), 'value' => $row->event_title),
                    array('name' => __('Responses', 'community-calendar-study-rooms'), 'value' => $row->registration_fields),
                    array('name' => __('Reminder opt-in', 'community-calendar-study-rooms'), 'value' => $row->reminder_opt_in ? __('Yes', 'community-calendar-study-rooms') : __('No', 'community-calendar-study-rooms')),
                );
            }
            $items[] = array(
                'group_id' => 'commonscal-' . $kind,
                'group_label' => $kind === 'room' ? __('Room bookings', 'community-calendar-study-rooms') : ($kind === 'registration' ? __('Event registrations', 'community-calendar-study-rooms') : __('Event waitlist', 'community-calendar-study-rooms')),
                'item_id' => 'commonscal-' . $kind . '-' . $row->id,
                'data' => $data,
            );
        }
    }
    return array('data' => $items, 'done' => $done);
}

/** Anonymizes confirmed records and removes waitlist entries for a verified email. */
function calsys_erase_personal_data($email, $page = 1) {
    global $wpdb;
    if (!is_email($email)) {
        return array('items_removed' => false, 'items_retained' => true, 'messages' => array(__('A valid email address is required.', 'community-calendar-study-rooms')), 'done' => true);
    }
    $queries = array(
        $wpdb->prepare("UPDATE " . calsys_table('bookings') . " SET requester_name = '', requester_email = '', request_reason = '' WHERE requester_email = %s LIMIT 100", $email),
        $wpdb->prepare("UPDATE " . calsys_table('registrations') . " SET name = '', email = '', registration_fields = '[]', reminder_opt_in = 0, reminder_sent_at = NULL WHERE email = %s LIMIT 100", $email),
        $wpdb->prepare("DELETE FROM " . calsys_table('waitlist') . " WHERE email = %s LIMIT 100", $email),
    );
    $removed = false;
    $done = true;
    foreach ($queries as $query) {
        $result = $wpdb->query($query);
        if ($result === false) {
            error_log('CommonsCal: personal data erasure failed: ' . $wpdb->last_error);
            return array('items_removed' => $removed, 'items_retained' => true, 'messages' => array(__('CommonsCal data could not be fully erased. Check the site error log.', 'community-calendar-study-rooms')), 'done' => true);
        }
        $removed = $removed || $result > 0;
        if ($result === 100) {
            $done = false;
        }
    }
    return array('items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => $done);
}

function calsys_schedule_privacy_cleanup() {
    if (!wp_next_scheduled('calsys_privacy_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'calsys_privacy_cleanup');
    }
}
add_action('init', 'calsys_schedule_privacy_cleanup');

/** Anonymizes expired records in bounded batches; zero retention disables cleanup. */
function calsys_privacy_cleanup() {
    $days = calsys_get_settings()['privacy']['retention_days'];
    if (!$days) {
        return;
    }
    global $wpdb;
    $cutoff = (new DateTimeImmutable('now', wp_timezone()))->modify('-' . (int) $days . ' days');
    $sources = array(
        array('bookings', $wpdb->prepare("SELECT id FROM " . calsys_table('bookings') . " WHERE booking_date < %s AND (requester_email <> '' OR requester_name <> '' OR request_reason <> '') ORDER BY id LIMIT 500", $cutoff->format('Y-m-d'))),
        array('registrations', $wpdb->prepare("SELECT r.id FROM " . calsys_table('registrations') . " r INNER JOIN " . calsys_table('events') . " e ON e.id = r.event_id WHERE e.ends_at < %s AND (r.email <> '' OR r.name <> '' OR r.registration_fields <> '[]') ORDER BY r.id LIMIT 500", $cutoff->format('Y-m-d H:i:s'))),
        array('waitlist', $wpdb->prepare("SELECT w.id FROM " . calsys_table('waitlist') . " w INNER JOIN " . calsys_table('events') . " e ON e.id = w.event_id WHERE e.ends_at < %s ORDER BY w.id LIMIT 500", $cutoff->format('Y-m-d H:i:s'))),
    );
    foreach ($sources as $source) {
        list($kind, $select) = $source;
        $ids = $wpdb->get_col($select);
        if ($wpdb->last_error || !is_array($ids)) {
            error_log('CommonsCal: unable to select expired personal data: ' . $wpdb->last_error);
            continue;
        }
        if (!$ids) {
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        if ($kind === 'bookings') {
            $sql = "UPDATE " . calsys_table($kind) . " SET requester_name = '', requester_email = '', request_reason = '' WHERE id IN ($placeholders) AND booking_date < %s";
            $ids[] = $cutoff->format('Y-m-d');
        } elseif ($kind === 'registrations') {
            $sql = "UPDATE " . calsys_table($kind) . " r INNER JOIN " . calsys_table('events') . " e ON e.id = r.event_id SET r.name = '', r.email = '', r.registration_fields = '[]', r.reminder_opt_in = 0, r.reminder_sent_at = NULL WHERE r.id IN ($placeholders) AND e.ends_at < %s";
            $ids[] = $cutoff->format('Y-m-d H:i:s');
        } else {
            $sql = "DELETE w FROM " . calsys_table($kind) . " w INNER JOIN " . calsys_table('events') . " e ON e.id = w.event_id WHERE w.id IN ($placeholders) AND e.ends_at < %s";
            $ids[] = $cutoff->format('Y-m-d H:i:s');
        }
        if ($wpdb->query($wpdb->prepare($sql, $ids)) === false) {
            error_log('CommonsCal: unable to clean expired personal data: ' . $wpdb->last_error);
        }
    }
}
add_action('calsys_privacy_cleanup', 'calsys_privacy_cleanup');

function calsys_privacy_policy_content() {
    if (function_exists('wp_add_privacy_policy_content')) {
        wp_add_privacy_policy_content('CommonsCal', '<p>' . esc_html__('CommonsCal stores room-request names, email addresses and reasons, as well as event registration and waitlist contact details and form responses. Staff use these details to manage bookings and events. Site administrators can export or erase records through WordPress privacy tools and can configure automatic anonymization of older records.', 'community-calendar-study-rooms') . '</p>');
    }
}
add_action('admin_init', 'calsys_privacy_policy_content');
