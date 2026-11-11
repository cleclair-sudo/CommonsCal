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
function __($value) { return $value; }
function current_user_can($capability) { return $GLOBALS['staff']; }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_get_settings() { return array('privacy' => array('show_room_names' => $GLOBALS['show_room_names'], 'show_room_status' => true)); }
class CalendarTestDatabase {
    public $last_error = '';
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql) {
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
$staff = false;
$show_room_names = true;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect($entries[0]->room_color === '#123456' && $entries[0]->title === 'Blue room', 'Visible rooms must retain their individual color');
$show_room_names = false;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect($entries[0]->room_color === '' && $entries[0]->title === 'Reserved room', 'Hidden room names must not expose individual colors');
$staff = true;
$entries = calsys_room_calendar_entries($month, $month->modify('+1 month'));
expect($entries[0]->room_color === '#123456' && $entries[0]->title === 'Blue room', 'Staff must see room colors even when public room names are hidden');
echo "Calendar views passed.\n";
