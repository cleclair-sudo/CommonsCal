<?php
// Run with: php tests/admin-workflows.php
define('ABSPATH', __DIR__ . '/');

class TestDatabase {
    public $prefix = 'wp_';
    public $last_error = '';
    public $queries = array();
    public $result = array();
    public $row = null;
    public $updated = null;
    public $inserted = null;
    public function prepare($sql, ...$args) { $this->queries[] = array($sql, $args); return $sql; }
    public function get_results($sql) { $this->queries[] = $sql; return $this->result; }
    public function get_row($sql) { $this->queries[] = $sql; return $this->row; }
    public function get_var($sql) { return 1; }
    public function query($sql) { return 1; }
    public function update($table, $data, $where, $formats = null, $where_formats = null) { $this->updated = $data; return 1; }
    public function insert($table, $data, $formats = null) { $this->inserted = $data; return 1; }
}
class TestRedirect extends Exception {
    public $page;
    public $notice;
    public function __construct($page, $notice) { $this->page = $page; $this->notice = $notice; }
}
function add_action() {}
function add_menu_page($title, $label, $capability, $slug) {
    $GLOBALS['main_menu'] = array($title, $label, $capability, $slug);
}
function add_submenu_page($parent, $title, $label, $capability, $slug, $callback) {
    $GLOBALS['menus'][$slug] = array($capability, $callback);
}
function current_user_can($capability) { return $GLOBALS['allowed']; }
function wp_die($message) { throw new RuntimeException($message); }
function __($text) { return $text; }
function _n($single, $plural, $count) { return $count === 1 ? $single : $plural; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_html__($text) { return esc_html($text); }
function esc_html_e($text) { echo esc_html($text); }
function esc_attr($text) { return esc_html($text); }
function esc_js($text) { return esc_html($text); }
function esc_attr_e($text) { echo esc_attr($text); }
function esc_url($text) { return esc_html($text); }
function admin_url($path) { return '/wp-admin/' . $path; }
function add_query_arg($key, $value, $url) { return $url . '&' . $key . '=' . $value; }
function wp_nonce_field($action) { echo '<input type="hidden" name="nonce" value="' . esc_attr($action) . '">'; }
function check_admin_referer($action) { $GLOBALS['nonce_action'] = $action; }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_event_capacity($capacity) { return (int) $capacity; }
function number_format_i18n($number) { return (string) $number; }
function current_time($format) { return $format === 'Y-m-d' ? '2026-11-11' : '2026-11-11 12:00:00'; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function calsys_staff_types() { return array('other' => 'Other'); }
function get_option($name) { return $name === 'date_format' ? 'Y-m-d' : 'H:i'; }
function mysql2date($format, $date) { return date($format, strtotime($date)); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return $value; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim($value); }
function sanitize_hex_color($value) { return preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $value) ? $value : null; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function calsys_send_email($recipient, $subject, $message) { $GLOBALS['sent_emails'][] = array($recipient, $subject, $message); return $GLOBALS['email_succeeds']; }
function calsys_admin_redirect($page, $notice) { throw new TestRedirect($page, $notice); }
function calsys_booking_conflict() { return $GLOBALS['conflict']; }
function calsys_equipment_description() { return 'Projector (1)'; }
function calsys_action_redirect($frontend, $page, $notice) { throw new TestRedirect($page, $notice); }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
function render($callback) { ob_start(); $callback(); return ob_get_clean(); }

require __DIR__ . '/../includes/admin-management.php';
require __DIR__ . '/../includes/admin-settings.php';
require __DIR__ . '/../includes/room-actions.php';

$allowed = true;
$conflict = false;
$wpdb = new TestDatabase();
$menus = array();
calsys_admin_menu();
expect($main_menu === array('CommonsCal', 'CommonsCal', 'manage_calendar_system', 'calsys-home'), 'Main menu branding or compatibility changed');
foreach (array('calsys-room-approvals', 'calsys-event-registrations') as $slug) {
    expect(isset($menus[$slug]) && $menus[$slug][0] === 'manage_calendar_system', "$slug menu is missing or unprotected");
}

$wpdb->result = array((object) array(
    'id' => 17, 'room_name' => '<Room>', 'requester_name' => 'Alex',
    'requester_email' => 'alex@example.org', 'request_reason' => 'Group study',
    'booking_date' => '2026-12-01', 'starts_at' => '10:00:00', 'ends_at' => '11:00:00',
));
$html = render('calsys_admin_room_approvals_page');
expect(strpos($html, '&lt;Room&gt;') !== false && strpos($html, 'Group study') !== false, 'Request details are missing or unescaped');
expect(strpos($html, 'Projector (1)') !== false, 'Requested equipment is missing');
expect(strpos($html, 'calsys_booking_decision_17') !== false && strpos($html, 'value="approve"') !== false && strpos($html, 'value="deny"') !== false, 'Approval actions are missing');
expect(strpos($wpdb->queries[0], "b.status = 'pending'") !== false, 'Approvals must contain only pending requests');
$wpdb->result = array();
expect(strpos(render('calsys_admin_room_approvals_page'), 'No room requests are waiting') !== false, 'Missing empty approvals state');
$wpdb->result = null;
expect(strpos(render('calsys_admin_room_approvals_page'), 'could not be loaded') !== false, 'Missing approvals database error');

$wpdb->result = array((object) array('id' => 4, 'title' => 'Story time', 'starts_at' => '2026-12-01 10:00:00', 'status' => 'draft', 'capacity' => 20, 'registration_count' => 1));
$_GET = array();
$wpdb->queries = array();
$html = render('calsys_admin_event_registrations_page');
expect(strpos($html, 'Story time') !== false && strpos($html, 'event_id=4') !== false && strpos($html, 'Draft') !== false, 'Upcoming event list is incomplete');
expect(strpos($html, '1 registration') !== false && strpos($html, 'Limit: 20') !== false, 'Registration count is incorrect');
expect(strpos($html, 'alex@example.org') === false, 'Event list must not include registration details');
expect(strpos($wpdb->queries[0][0], 'e.starts_at >= %s') !== false && $wpdb->queries[0][1][0] === current_time('mysql'), 'Event list must be limited to upcoming events');

$_GET = array('event_id' => 4);
$wpdb->row = (object) array('id' => 4, 'title' => 'Story time', 'starts_at' => '2026-12-01 10:00:00');
$wpdb->result = array((object) array('id' => 1, 'event_id' => 4, 'name' => 'Alex', 'email' => 'alex@example.org', 'registered_at' => '2026-11-11 12:00:00', 'registration_fields' => '[{"label":"Question","value":"<Answer>"}]', 'reminder_opt_in' => 0, 'reminder_sent_at' => null));
$html = render('calsys_admin_event_registrations_page');
expect(strpos($html, 'alex@example.org') !== false && strpos($html, 'Question') !== false && strpos($html, '&lt;Answer&gt;') !== false, 'Registration responses are missing or unescaped');
expect(strpos($wpdb->queries[count($wpdb->queries) - 2][0], 'WHERE event_id = %d') !== false && $wpdb->queries[count($wpdb->queries) - 2][1][0] === 4, 'Registrations must be limited to the selected event');
$wpdb->result = array();
expect(strpos(render('calsys_admin_event_registrations_page'), 'No registrations for this event yet') !== false, 'Missing empty registration state');
$wpdb->row = null;
expect(strpos(render('calsys_admin_event_registrations_page'), 'could not be found') !== false, 'Missing unknown event state');
$_GET = array();
$wpdb->result = null;
expect(strpos(render('calsys_admin_event_registrations_page'), 'could not be loaded') !== false, 'Missing registrations database error');

$allowed = false;
try { render('calsys_admin_room_approvals_page'); expect(false, 'Unauthorized approvals access'); } catch (RuntimeException $error) { ob_end_clean(); }
try { render('calsys_admin_event_registrations_page'); expect(false, 'Unauthorized registrations access'); } catch (RuntimeException $error) { ob_end_clean(); }
$allowed = true;

$_GET = array();
$wpdb->queries = array();
$wpdb->result = array();
$home = render('calsys_admin_home_page');
expect(strpos($home, '<h2>Upcoming</h2>') !== false && strpos($home, 'no remaining calendar items today') !== false, 'Dashboard upcoming empty state is missing');
$prepared = array_values(array_filter($wpdb->queries, 'is_array'));
$event_queries = array_values(array_filter($prepared, function ($query) { return strpos($query[0], 'SELECT title, starts_at FROM wp_calsys_events') !== false; }));
$room_queries = array_values(array_filter($prepared, function ($query) { return strpos($query[0], 'SELECT b.room_id, b.booking_date') !== false; }));
$staff_queries = array_values(array_filter($prepared, function ($query) { return strpos($query[0], 'SELECT title, entry_type') !== false; }));
expect(count($event_queries) === 1 && $event_queries[0][1] === array('published', '2026-11-12 00:00:00', '2026-11-11 12:00:00'), 'Upcoming events must include only items remaining today');
expect(count($room_queries) === 1 && $room_queries[0][1] === array('pending', 'approved', '2026-11-11', '12:00:00'), 'Upcoming room bookings must be limited to today and not ended');
expect(count($staff_queries) === 1 && $staff_queries[0][1] === array('2026-11-12 00:00:00', '2026-11-11 12:00:00'), 'Upcoming staff time must include only items remaining today');

$wpdb->row = (object) array('room_id' => 2, 'booking_date' => '2026-12-01', 'starts_at' => '10:00:00', 'ends_at' => '11:00:00', 'requester_name' => 'Test', 'requester_email' => 'test@example.org', 'source_type' => '');
$sent_emails = array();
$email_succeeds = true;
foreach (array('approve' => 'approved', 'deny' => 'denied') as $decision => $status) {
    $_POST = array('booking_id' => '17', 'decision' => $decision, 'calsys_return_page' => 'calsys-room-approvals');
    try { calsys_booking_decision(); expect(false, 'Expected redirect'); } catch (TestRedirect $redirect) {
        expect($redirect->page === 'calsys-room-approvals' && $redirect->notice === $status && $wpdb->updated['status'] === $status, 'Decision failed');
        expect($nonce_action === 'calsys_booking_decision_17', 'Wrong decision nonce');
    }
}
expect(count($sent_emails) === 2 && strpos($sent_emails[0][1], 'approved') !== false && strpos($sent_emails[1][1], 'declined') !== false, 'Decisions must notify the requester');
$email_succeeds = false;
$_POST = array('booking_id' => '17', 'decision' => 'approve', 'calsys_return_page' => 'calsys-room-approvals');
try { calsys_booking_decision(); expect(false, 'Expected redirect'); } catch (TestRedirect $redirect) {
    expect($redirect->notice === 'room_status_email_failed', 'Email failures must be reported after the decision is saved');
}
$email_succeeds = true;
$conflict = true;
$_POST = array('booking_id' => '17', 'decision' => 'approve', 'calsys_return_page' => 'calsys-room-approvals');
try { calsys_booking_decision(); expect(false, 'Expected conflict'); } catch (TestRedirect $redirect) {
    expect($redirect->page === 'calsys-room-approvals' && $redirect->notice === 'conflict', 'Conflict was not reported');
}
$_POST['calsys_return_page'] = 'another-page';
try { calsys_booking_decision(); expect(false, 'Expected conflict'); } catch (TestRedirect $redirect) {
    expect($redirect->page === 'calsys-rooms', 'Unexpected redirect page');
}

$_POST = array('name' => 'Room A', 'capacity' => '5', 'active' => '1', 'color' => '#a1b2c3');
try { calsys_save_room(); } catch (TestRedirect $redirect) { expect($redirect->notice === 'saved', 'Valid room color must save'); }
expect($wpdb->inserted['color'] === '#a1b2c3', 'Room creation must persist the chosen color');
$_POST['room_id'] = '1';
$_POST['color'] = '';
try { calsys_save_room(); } catch (TestRedirect $redirect) { expect($redirect->notice === 'saved', 'Clearing a room color must save'); }
expect($wpdb->updated['color'] === '', 'Clearing a room color must restore the default');
unset($_POST['color']);
try { calsys_save_room(); } catch (TestRedirect $redirect) { expect($redirect->notice === 'saved', 'Older room forms must still save'); }
expect(!array_key_exists('color', $wpdb->updated), 'Older forms must preserve existing room colors');
$_POST['color'] = 'not-a-color';
$wpdb->updated = null;
try { calsys_save_room(); } catch (TestRedirect $redirect) { expect($redirect->notice === 'invalid', 'Invalid room colors must be rejected'); }
expect($wpdb->updated === null, 'Invalid room colors must not be saved');
$_POST['color'] = array('#123456');
try { calsys_save_room(); } catch (TestRedirect $redirect) { expect($redirect->notice === 'invalid', 'Non-string room colors must be rejected'); }

echo "Admin workflows passed.\n";
