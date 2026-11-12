<?php
// Run with: php tests/calendar-views.php
define('ABSPATH', __DIR__ . '/');
function add_shortcode() {}
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function calsys_week_start_day() { return $GLOBALS['week_start']; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function wp_unslash($value) { return $value; }
function current_time($format) { return $format === 'Y-m' ? '2026-11' : '2026-11-11'; }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html_e($value) { echo esc_html($value); }
function esc_attr_e($value) { echo esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function __($value) { return $value; }
function current_user_can($capability) { return $GLOBALS['staff']; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_get_settings() { return array('privacy' => array('hide_guest_room_bookings' => $GLOBALS['hide_guest_room_bookings'], 'show_room_names' => $GLOBALS['show_room_names'], 'show_room_status' => true), 'booking_rules' => $GLOBALS['booking_rules']); }
function get_option($name) { return $name === 'time_format' ? 'H:i' : 'Y-m-d'; }
function wp_date($format, $timestamp, $timezone) { return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format); }
function remove_query_arg($args) { return 'https://example.org/calendar'; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function calsys_event_categories() { return array(); }
function checked($value) { if ($value) { echo 'checked'; } }
function selected($value, $expected) { if ($value === $expected) { echo 'selected'; } }
function sanitize_html_class($value) { return preg_replace('/[^a-zA-Z0-9_-]/', '', $value); }
function calsys_calendar_entry_color() { return '#123456'; }
function calsys_show_public_category_labels() { return true; }
function wp_strip_all_tags($value, $remove_breaks = false) { return strip_tags($value); }
class CalendarTestDatabase {
    public $last_error = '';
    public $query_count = 0;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return $GLOBALS['active_rooms'] ? 1 : null; }
    public function get_results($sql) {
        $this->query_count++;
        if (strpos($sql, 'SELECT DISTINCT s.id, s.name') !== false) { return array(); }
        return array((object) array(
            'id' => 1, 'room_id' => 2, 'room_name' => 'Blue room', 'room_color' => '#123456',
            'booking_date' => '2026-11-30', 'starts_at' => '10:00:00', 'ends_at' => '11:00:00',
            'status' => 'approved', 'requester_name' => 'Test', 'requester_email' => 'test@example.org',
        ));
    }
}
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/calendars.php';
require __DIR__ . '/../includes/room-hours.php';

$month = new DateTimeImmutable('2026-11-01', wp_timezone());
$date = new DateTimeImmutable('2026-11-30', wp_timezone());
$week_start = 1;
list($start, $end) = calsys_calendar_range($month, 'week', $date);
expect($start->format('Y-m-d') === '2026-11-30' && $end->format('Y-m-d') === '2026-12-07', 'Week must span month boundaries');
list($start, $end) = calsys_calendar_range($month, 'day', $date);
expect($start->format('Y-m-d') === '2026-11-30' && $end->format('Y-m-d') === '2026-12-01', 'Day range must be one calendar day');
list($start, $end) = calsys_calendar_range($month, 'month', $date);
expect($start->format('Y-m-d') === '2026-10-26' && $end->format('Y-m-d') === '2026-12-07', 'Month must preserve the six-week grid');
$week_start = 0;
list($start, $end) = calsys_calendar_range($month, 'week', new DateTimeImmutable('2026-11-04', wp_timezone()));
expect($start->format('Y-m-d') === '2026-11-01' && $end->format('Y-m-d') === '2026-11-08', 'Week must honor configured week start across DST');

$_GET = array('calsys_view' => 'week', 'calsys_date' => '2026-02-29');
list($view, $selected) = calsys_calendar_view_state($month);
expect($view === 'week' && $selected->format('Y-m-d') === '2026-11-11', 'Invalid date must fall back to today');
$_GET = array('calsys_view' => array('day'), 'calsys_date' => '2026-12-01');
list($view, $selected) = calsys_calendar_view_state($month);
expect($view === 'month' && $selected->format('Y-m-d') === '2026-12-01', 'Invalid view must fall back to month');
$fields = calsys_calendar_hidden_query_fields(array('page_id' => '6', 'filter' => array('x' => '<value>')));
expect(strpos($fields, 'name="page_id" value="6"') !== false && strpos($fields, 'name="filter[x]" value="&lt;value&gt;"') !== false, 'Query parameters must be retained and escaped');

$wpdb = new CalendarTestDatabase();
$_GET = array('calsys_recurring' => 'yes', 'calsys_series' => 'no');
$filter_sql = calsys_event_group_filter_sql();
expect(strpos($filter_sql, "e.recurrence_group <> ''") !== false && strpos($filter_sql, 'e.series_id = 0') !== false, 'Recurrence and named series filters must apply independently');
$_GET = array('calsys_recurring' => array('yes'), 'calsys_series' => 'invalid');
expect(calsys_event_group_filter_sql() === '', 'Invalid group filters must not alter the query');
$_GET = array('calsys_series' => 'id_3');
expect(strpos(calsys_event_group_filter_sql(), 'e.series_id = %d') !== false, 'A named-series filter must use a prepared ID');
$_GET = array();
$booking_rules = array(
    'weekly_hours' => array_fill(0, 7, array('mode' => 'unrestricted', 'open' => '', 'close' => '')),
    'holiday_hours' => array(),
);
$staff = false;
$logged_in = false;
$hide_guest_room_bookings = false;
$show_room_names = true;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect($entries[0]->room_color === '#123456' && $entries[0]->title === 'Blue room', 'Visible rooms must retain their individual color');
$show_room_names = false;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect($entries[0]->room_color === '' && $entries[0]->title === 'Reserved room', 'Hidden room names must not expose individual colors');
$staff = true;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect($entries[0]->room_color === '#123456' && $entries[0]->title === 'Blue room', 'Staff must see room colors even when public room names are hidden');

$staff = false;
$show_room_names = true;
$hide_guest_room_bookings = true;
$query_count = $wpdb->query_count;
expect(calsys_room_calendar_entries($month, $month->modify('+1 month')) === array(), 'Logged-out visitors must not receive room bookings when hidden');
expect($wpdb->query_count === $query_count, 'Hidden room bookings must not be queried for logged-out visitors');
$logged_in = true;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect(count($entries) === 1 && $entries[0]->title === 'Blue room', 'Signed-in non-staff must still see public bookings');
$staff = true;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect(count($entries) === 1 && $entries[0]->title === 'Blue room', 'Staff must still see room bookings when hidden from guests');

$staff = false;
$active_rooms = true;
$booking_rules['weekly_hours'][3] = array('mode' => 'closed', 'open' => '', 'close' => '');
$booking_rules['weekly_hours'][4] = array('mode' => 'open', 'open' => '09:00', 'close' => '17:00');
$booking_rules['weekly_hours'][0] = array('mode' => 'closed', 'open' => '', 'close' => '');
$booking_rules['holiday_hours'] = array(
    array('date' => '2026-11-14', 'annual' => 0, 'mode' => 'closed', 'open' => '', 'close' => ''),
    array('date' => '2026-11-15', 'annual' => 0, 'mode' => 'open', 'open' => '10:00', 'close' => '14:00'),
    array('date' => '2025-11-16', 'annual' => 1, 'mode' => 'closed', 'open' => '', 'close' => ''),
    array('date' => '2025-11-17', 'annual' => 1, 'mode' => 'closed', 'open' => '', 'close' => ''),
    array('date' => '2026-11-17', 'annual' => 0, 'mode' => 'open', 'open' => '10:00', 'close' => '14:00'),
);
$_GET = array();
$week_start = 3;
$selected = new DateTimeImmutable('2026-11-11', wp_timezone());
foreach (array('rooms', 'combined') as $calendar_type) {
    foreach (array('month', 'week', 'day') as $view) {
        $html = calsys_render_calendar('Rooms', array(), $month, $calendar_type, $view, $selected, true);
        expect(strpos($html, 'data-calsys-add-room data-date="2026-11-11"') === false, "$calendar_type $view: closed weekday must not have a request button");
        if ($view !== 'day') {
            expect(strpos($html, 'data-calsys-add-room data-date="2026-11-12"') !== false, "$calendar_type $view: open weekday must have a request button");
            expect(strpos($html, 'data-calsys-add-room data-date="2026-11-13"') !== false, "$calendar_type $view: unrestricted weekday must have a request button");
            expect(strpos($html, 'data-calsys-add-room data-date="2026-11-14"') === false, "$calendar_type $view: closed special date must not have a request button");
            expect(strpos($html, 'data-calsys-add-room data-date="2026-11-15"') !== false, "$calendar_type $view: open special date must override a closed weekday");
            expect(strpos($html, 'data-calsys-add-room data-date="2026-11-16"') === false, "$calendar_type $view: annual closure must not have a request button");
            expect(strpos($html, 'data-calsys-add-room data-date="2026-11-17"') !== false, "$calendar_type $view: exact open date must override annual closure");
            expect(strpos($html, 'data-calsys-add-room data-date="2026-11-10"') === false, "$calendar_type $view: past days must not have a request button");
        }
    }
}
$staff = true;
$html = calsys_render_calendar('Rooms', array(), $month, 'combined', 'day', $selected, true);
expect(strpos($html, 'data-calsys-add-room data-date="2026-11-11">Add</button>') !== false, 'Staff must still be able to book on closed days');
$staff = false;
$active_rooms = false;
$html = calsys_render_calendar('Rooms', array(), $month, 'rooms', 'day', new DateTimeImmutable('2026-11-12', wp_timezone()), true);
expect(strpos($html, 'data-calsys-add-room') === false, 'No room action should be shown when there are no active rooms');

$events = array(
    (object) array('id' => 1, 'title' => 'Outside month', 'starts_at' => '2026-10-28 10:00:00', 'ends_at' => '2026-10-28 11:00:00', 'all_day' => 0, 'calendar_type' => 'event'),
    (object) array('id' => 2, 'title' => 'Overlapping event', 'starts_at' => '2026-10-31 10:00:00', 'ends_at' => '2026-11-02 11:00:00', 'all_day' => 0, 'calendar_type' => 'event'),
    (object) array('id' => 3, 'title' => 'Week event', 'starts_at' => '2026-11-12 10:00:00', 'ends_at' => '2026-11-12 11:00:00', 'all_day' => 0, 'calendar_type' => 'event', 'description' => '<b>Details &amp; more</b>'),
    (object) array('id' => 4, 'title' => 'Room booking', 'starts_at' => '2026-11-12 10:00:00', 'ends_at' => '2026-11-12 11:00:00', 'all_day' => 0, 'calendar_type' => 'room'),
    (object) array('id' => 5, 'title' => 'Next month', 'starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 11:00:00', 'all_day' => 0, 'calendar_type' => 'event'),
);
$html = calsys_render_calendar('Calendar', $events, $month, 'combined', 'month', $selected, true);
preg_match('/<div class="calsys-print-events" hidden>(.*?)<\/div>/s', $html, $match);
expect(!empty($match[1]) && strpos($match[1], 'Overlapping event') !== false && strpos($match[1], 'Week event') !== false, 'Monthly print list must include events overlapping the month');
expect(strpos($match[1], 'Outside month') === false && strpos($match[1], 'Next month') === false && strpos($match[1], 'Room booking') === false, 'Monthly print list must exclude adjacent-month events and room bookings');
expect(strpos($match[1], '&lt;b&gt;') === false && strpos($match[1], 'Details') !== false, 'Event descriptions must be rendered as safe text');
$html = calsys_render_calendar('Calendar', $events, $month, 'combined', 'week', $selected, true);
preg_match('/<div class="calsys-print-events" hidden>(.*?)<\/div>/s', $html, $match);
expect(strpos($match[1], 'Week event') !== false && strpos($match[1], 'Overlapping event') === false, 'Weekly print list must use the configured week boundaries');
$week_start = 1;
$boundary_events = array(
    (object) array('id' => 6, 'title' => 'Week end', 'starts_at' => '2026-12-07 00:00:00', 'ends_at' => '2026-12-07 01:00:00', 'all_day' => 0, 'calendar_type' => 'event'),
    (object) array('id' => 7, 'title' => 'Next month in week', 'starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 11:00:00', 'all_day' => 0, 'calendar_type' => 'event'),
    (object) array('id' => 8, 'title' => 'Ends at week start', 'starts_at' => '2026-11-29 23:00:00', 'ends_at' => '2026-11-30 00:00:00', 'all_day' => 0, 'calendar_type' => 'event'),
    (object) array('id' => 9, 'title' => 'First in week', 'starts_at' => '2026-11-30 10:00:00', 'ends_at' => '2026-11-30 11:00:00', 'all_day' => 0, 'calendar_type' => 'event'),
);
$html = calsys_render_calendar('Calendar', $boundary_events, $month, 'events', 'week', $date, true);
preg_match('/<div class="calsys-print-events" hidden>(.*?)<\/div>/s', $html, $match);
expect(strpos($match[1], 'Next month in week') !== false && strpos($match[1], 'First in week') < strpos($match[1], 'Next month in week'), 'Week list must include adjacent months in chronological order');
expect(strpos($match[1], 'Week end') === false && strpos($match[1], 'Ends at week start') === false, 'Week list must exclude events outside its exclusive boundaries');
$html = calsys_render_calendar('Calendar', array(), $month, 'events', 'week', $selected, true);
expect(strpos($html, 'No events for this period.') !== false, 'An empty print list must explain that there are no events');
$html = calsys_render_calendar('Calendar', array(), $month, 'events', 'day', $selected, true);
expect(strpos($html, 'data-calsys-print="calendar"') !== false && strpos($html, 'data-calsys-print="events"') === false, 'Day view must offer calendar printing but not a month or week list');
echo "Calendar views passed.\n";
