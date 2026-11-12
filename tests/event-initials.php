<?php
// Run with: php tests/event-initials.php
define('ABSPATH', __DIR__ . '/');
class EventTestRedirect extends Exception {
    public $notice;
    public function __construct($notice) { $this->notice = $notice; }
}
class EventTestDatabase {
    public $insert_id = 1;
    public $writes = array();
    public $series = array();
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return 1; }
    public function query($sql) { return 1; }
    public function insert($table, $data, $formats) { expect(count($data) === count($formats), 'Insert formats must match event fields'); if ($table === 'wp_calsys_series') { $this->series[] = $data; $this->insert_id = 42; } else { $this->writes[] = $data; } return 1; }
    public function update($table, $data, $where, $formats, $where_formats) { expect(count($data) === count($formats), 'Update formats must match event fields'); $this->writes[] = $data; return 1; }
}
function add_action() {}
function calsys_require_staff() {}
function check_admin_referer($action) {}
function calsys_action_redirect($frontend, $page, $notice, $count = 0) { throw new EventTestRedirect($notice); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return $value; }
function wp_kses_post($value) { return $value; }
function calsys_parse_datetime($value) { return str_replace('T', ' ', $value) . ':00'; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return $value; }
function calsys_event_categories() { return array('adult' => 'Adult'); }
function calsys_posted_equipment() { return array(); }
function calsys_sanitize_registration_fields($fields) { return $fields; }
function calsys_sanitize_registration_recipients($recipients) { return array(); }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function wp_timezone() { return new DateTimeZone('UTC'); }
function wp_generate_uuid4() { return 'group'; }
function wp_json_encode($value) { return json_encode($value); }
function calsys_lock_linked_room_ids() { return true; }
function calsys_sync_linked_room_reservations() { return 0; }
function calsys_sync_equipment() { return 'saved'; }
function calsys_promote_event_waitlist() { return 'ok'; }
function current_time($format) { return '2026-11-12 12:00:00'; }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/events.php';
$wpdb = new EventTestDatabase();
$post = array(
    'title' => 'Event', 'description' => '', 'starts_at' => '2026-12-01T10:00',
    'ends_at' => '2026-12-01T11:00', 'status' => 'published', 'category' => 'adult',
    'registration_enabled' => '1', 'registration_fields_present' => '1',
    'registration_recipients' => '', 'staff_initials' => ' AB ',
);
$_POST = $post;
try { calsys_save_event(); } catch (EventTestRedirect $redirect) { expect($redirect->notice === 'saved', 'Event creation failed'); }
expect($wpdb->writes[0]['staff_initials'] === 'AB', 'Creation must save sanitized initials');
$_POST = $post;
$_POST['event_id'] = '1';
$_POST['staff_initials'] = 'CD';
try { calsys_save_event(); } catch (EventTestRedirect $redirect) { expect($redirect->notice === 'saved', 'Event edit failed'); }
expect($wpdb->writes[1]['staff_initials'] === 'CD', 'Editing must save the current staff initials');
foreach (array('', '   ', array('AB'), str_repeat('x', 41)) as $invalid) {
    $_POST = $post;
    $_POST['staff_initials'] = $invalid;
    try { calsys_save_event(); expect(false, 'Invalid initials were accepted'); } catch (EventTestRedirect $redirect) {
        expect($redirect->notice === 'invalid' && count($wpdb->writes) === 2, 'Invalid initials must not write an event');
    }
}
$_POST = $post;
unset($_POST['staff_initials']);
try { calsys_save_event(); expect(false, 'Missing initials were accepted'); } catch (EventTestRedirect $redirect) {
    expect($redirect->notice === 'invalid' && count($wpdb->writes) === 2, 'Missing initials must not write an event');
}
$_POST = $post;
$_POST['recurring'] = '1';
$_POST['recurrence_frequency'] = 'weekly';
$_POST['recurrence_end_type'] = 'count';
$_POST['recurrence_count'] = '2';
try { calsys_save_event(); } catch (EventTestRedirect $redirect) { expect($redirect->notice === 'saved', 'Recurring event creation failed'); }
expect(count($wpdb->writes) === 4 && $wpdb->writes[2]['staff_initials'] === 'AB' && $wpdb->writes[3]['staff_initials'] === 'AB', 'All occurrences must record the staff initials');
$_POST['series_id'] = 'new';
$_POST['series_name'] = ' Reading Club ';
$_POST['register_all_recurrences'] = '1';
try { calsys_save_event(); } catch (EventTestRedirect $redirect) { expect($redirect->notice === 'saved', 'Named recurring series creation failed'); }
expect($wpdb->series[0]['name'] === 'Reading Club' && $wpdb->writes[4]['series_id'] === 42 && $wpdb->writes[5]['series_id'] === 42, 'New series must be attached to all created occurrences');
expect($wpdb->writes[4]['register_all_recurrences'] === 1 && $wpdb->writes[5]['register_all_recurrences'] === 1, 'Recurring registration option must be saved on each occurrence');
$_POST = $post;
$_POST['event_id'] = '1';
$_POST['series_id'] = '1';
try { calsys_save_event(); } catch (EventTestRedirect $redirect) { expect($redirect->notice === 'saved', 'Attaching an existing series failed'); }
expect($wpdb->writes[6]['series_id'] === 1 && $wpdb->writes[6]['register_all_recurrences'] === 0, 'Existing series and unchecked recurrence option must be saved');
$_POST['series_id'] = 'new';
$_POST['series_name'] = ' ';
try { calsys_save_event(); expect(false, 'Empty series name was accepted'); } catch (EventTestRedirect $redirect) {
    expect($redirect->notice === 'invalid' && count($wpdb->series) === 1 && count($wpdb->writes) === 7, 'Invalid series must not be created');
}
echo "Event initials passed.\n";
