<?php
// Run with: php tests/series-registration.php
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
class RegistrationRedirect extends Exception {
    public $notice;
    public $args;
    public function __construct($notice, $args) { $this->notice = $notice; $this->args = $args; }
}
class SeriesRegistrationDatabase {
    public $last_error = '';
    public $events = array();
    public $registrations = array();
    public $promoted = array();
    public function prepare($sql, ...$args) { return array($sql, $args); }
    public function get_row($query) {
        $event = $this->events[(int) $query[1][0]] ?? null;
        if (!$event || $event->status !== 'published') { return null; }
        if (strpos($query[0], 'starts_at >') !== false && $event->starts_at <= current_time('mysql')) { return null; }
        return $event;
    }
    public function get_col($query) {
        $group = $query[1][0];
        $ids = array();
        foreach ($this->events as $event) {
            if ($event->recurrence_group === $group && $event->status === 'published' && $event->starts_at > current_time('mysql')) {
                $ids[] = $event->id;
            }
        }
        return $ids;
    }
    public function get_results($query) {
        return array_values(array_filter($this->events, function ($event) use ($query) {
            return $event->series_id === (int) $query[1][0] && $event->status === 'published' && $event->registration_enabled;
        }));
    }
    public function get_var($query) {
        $event_id = (int) $query[1][0];
        if (strpos($query[0], 'COUNT(*)') !== false) {
            return count(array_filter($this->registrations, function ($registration) use ($event_id) { return $registration['event_id'] === $event_id; }));
        }
        if (strpos($query[0], 'waitlist') !== false) { return null; }
        foreach ($this->registrations as $registration) {
            if ($registration['event_id'] === $event_id && $registration['email'] === $query[1][1]) { return 1; }
        }
        return null;
    }
    public function query($sql) { return 1; }
    public function insert($table, $data, $formats = null) { $this->registrations[] = $data; return 1; }
}
function add_action() {}
function check_admin_referer($action) {}
function calsys_frontend_redirect($notice, $args = array()) { throw new RegistrationRedirect($notice, $args); }
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim($value); }
function sanitize_email($value) { return trim($value); }
function wp_unslash($value) { return $value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_get_settings() { return array('event_registration' => array('enabled' => true), 'notifications' => array('email_event_reminders' => false, 'email_event_registrant' => false, 'notify_staff_event_registrations' => false)); }
function calsys_event_registration_is_open($start) { return $start > current_time('mysql'); }
function calsys_decode_registration_fields($json) { return json_decode($json, true) ?: array(); }
function calsys_decode_registration_recipients($json) { return array(); }
function calsys_event_capacity($capacity) { return (int) $capacity; }
function calsys_promote_event_waitlist($id) { $GLOBALS['wpdb']->promoted[] = $id; return 'saved'; }
function wp_json_encode($value) { return json_encode($value); }
function current_time($format) { return '2026-11-12 12:00:00'; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; }
function set_transient($key, $value, $expiration) { $GLOBALS['registration_results'] = $value; return !empty($GLOBALS['transient_succeeds']); }
function __($value) { return $value; }
function expect($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }
function event($id, $series, $group, $fields = '[]', $capacity = 0) {
    return (object) array('id' => $id, 'title' => 'Event ' . $id, 'starts_at' => '2026-12-01 10:00:00',
        'status' => 'published', 'series_id' => $series, 'recurrence_group' => $group,
        'registration_fields' => $fields, 'registration_enabled' => 1, 'register_all_recurrences' => 1,
        'registration_recipients' => '[]', 'capacity' => $capacity);
}
require __DIR__ . '/../includes/registrations.php';
$wpdb = new SeriesRegistrationDatabase();
$transient_succeeds = true;
$wpdb->events = array(
    1 => event(1, 10, 'repeat'), 2 => event(2, 10, 'repeat'), 3 => event(3, 10, '', '[]', 1),
    4 => event(4, 10, '', '[{"label":"Required","type":"text","required":true,"options":[]}]'),
    5 => event(5, 11, ''), 6 => event(6, 0, 'repeat'),
);
$wpdb->registrations[] = array('event_id' => 3, 'email' => 'other@example.org');
$_POST = array('event_id' => '1', 'name' => 'Pat', 'email' => 'pat@example.org',
    'series_events' => array('2', '3', '4', '5'), 'all_recurrences' => '1');
try { calsys_register_event(); expect(false, 'Expected redirect'); } catch (RegistrationRedirect $redirect) {
    expect($redirect->notice === 'registration_results', 'Multi-event registration must report per-event results');
}
$statuses = array();
foreach ($registration_results as $result) { $statuses[$result['id']] = $result['status']; }
expect($statuses[1] === 'registration_complete' && $statuses[2] === 'registration_complete' && $statuses[6] === 'registration_complete', 'Eligible events must register');
expect($statuses[3] === 'event_full' && $statuses[4] === 'invalid_registration' && $statuses[5] === 'invalid_registration', 'Full, mismatched and unrelated events must fail separately');
expect(count($wpdb->registrations) === 4 && !in_array(4, $wpdb->promoted, true) && !in_array(5, $wpdb->promoted, true), 'Rejected events must not be modified');
$_POST = array('event_id' => '1', 'name' => 'Sam', 'email' => 'sam@example.org', 'series_select_all' => '1');
try { calsys_register_event(); expect(false, 'Expected select-all redirect'); } catch (RegistrationRedirect $redirect) {
    expect($redirect->notice === 'registration_results', 'Select all must report its results');
}
$statuses = array();
foreach ($registration_results as $result) { $statuses[$result['id']] = $result['status']; }
expect(isset($statuses[1], $statuses[2], $statuses[3]) && !isset($statuses[4], $statuses[5], $statuses[6]), 'Select all must include only open, matching events in the named series');
expect($statuses[1] === 'registration_complete' && $statuses[2] === 'registration_complete' && $statuses[3] === 'event_full', 'Select all must preserve partial failures');
$wpdb->events[1]->registration_enabled = 0;
$_POST = array('event_id' => '1', 'name' => 'Taylor', 'email' => 'taylor@example.org', 'series_events' => array('2'));
try { calsys_register_event(); expect(false, 'Expected partial-results redirect'); } catch (RegistrationRedirect $redirect) {
    expect($redirect->notice === 'registration_results', 'A disabled anchor must not prevent registration for other selected events');
}
expect($registration_results[0]['status'] === 'registration_disabled' && $registration_results[1]['status'] === 'registration_complete', 'Changed anchor status must be reported without blocking eligible events');
$wpdb->events[1]->registration_enabled = 1;
$_POST = array('event_id' => '1', 'name' => 'Pat', 'email' => 'pat@example.org');
try { calsys_register_event(); expect(false, 'Expected duplicate redirect'); } catch (RegistrationRedirect $redirect) {
    expect($redirect->notice === 'already_registered', 'Single-event duplicate behavior must remain unchanged');
}
$wpdb->events[1]->register_all_recurrences = 0;
$_POST['all_recurrences'] = '1';
try { calsys_register_event(); expect(false, 'Expected invalid redirect'); } catch (RegistrationRedirect $redirect) {
    expect($redirect->notice === 'invalid_registration', 'All-occurrences registration requires staff permission');
}
$transient_succeeds = false;
$_POST = array('event_id' => '1', 'name' => 'Casey', 'email' => 'casey@example.org', 'series_events' => array('2'));
try { calsys_register_event(); expect(false, 'Expected result storage failure redirect'); } catch (RegistrationRedirect $redirect) {
    expect($redirect->notice === 'registration_results_unavailable', 'Result storage failures must warn that registrations may have succeeded');
}
echo "Series registration passed.\n";
