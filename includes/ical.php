<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Escapes an iCalendar text value. */
function calsys_ical_text($value) {
    $value = wp_check_invalid_utf8((string) $value);
    $value = str_replace(array("\r\n", "\r"), "\n", $value);
    return str_replace(array('\\', ';', ',', "\n"), array('\\\\', '\\;', '\\,', '\\n'), $value);
}

/** Folds a content line without splitting a UTF-8 character. */
function calsys_ical_line($line) {
    $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
    if ($characters === false) {
        return false;
    }
    $result = '';
    $current = '';
    foreach ($characters as $character) {
        if (strlen($current) + strlen($character) > 75) {
            $result .= $current . "\r\n";
            $current = ' ';
        }
        $current .= $character;
    }
    return $result . $current . "\r\n";
}

/** Serves a subscription feed containing published events only. */
function calsys_serve_event_ical() {
    if (!isset($_GET['calsys_ical']) || $_GET['calsys_ical'] !== 'events') {
        return;
    }
    global $wpdb;
    $events = $wpdb->get_results(
        "SELECT id, title, description, starts_at, ends_at, created_at FROM " . calsys_table('events') .
        " WHERE status = 'published' ORDER BY starts_at ASC, id ASC"
    );
    if ($wpdb->last_error || !is_array($events)) {
        error_log('CommonsCal: unable to load calendar subscription: ' . $wpdb->last_error);
        status_header(500);
        wp_die(esc_html__('The event calendar could not be loaded.', 'community-calendar-study-rooms'));
    }
    $lines = array('BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//CommonsCal//Events//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . calsys_ical_text(get_bloginfo('name') . ' - Events'));
    $site_id = hash('sha256', home_url('/'));
    $utc = new DateTimeZone('UTC');
    foreach ($events as $event) {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $event->starts_at, wp_timezone());
        $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $event->ends_at, wp_timezone());
        $created = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $event->created_at, wp_timezone());
        if (!$start || !$end || !$created) {
            error_log('CommonsCal: invalid event date in calendar subscription for event ' . (int) $event->id);
            status_header(500);
            wp_die(esc_html__('The event calendar could not be loaded.', 'community-calendar-study-rooms'));
        }
        $description = html_entity_decode(wp_strip_all_tags($event->description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:commonscal-' . (int) $event->id . '-' . $site_id;
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        $lines[] = 'CREATED:' . $created->setTimezone($utc)->format('Ymd\THis\Z');
        $lines[] = 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z');
        $lines[] = 'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z');
        $lines[] = 'SUMMARY:' . calsys_ical_text($event->title);
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . calsys_ical_text($description);
        }
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    $output = '';
    foreach ($lines as $line) {
        $folded = calsys_ical_line($line);
        if ($folded === false) {
            error_log('CommonsCal: invalid text in event calendar subscription.');
            status_header(500);
            wp_die(esc_html__('The event calendar could not be loaded.', 'community-calendar-study-rooms'));
        }
        $output .= $folded;
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="commonscal-events.ics"');
    echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
add_action('init', 'calsys_serve_event_ical');
