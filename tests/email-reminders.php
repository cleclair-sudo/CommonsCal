<?php
// Run with: php tests/email-reminders.php
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', __FILE__);
function add_action($hook, $callback, $priority = 10) { $GLOBALS['hooks'][$hook][$priority][] = $callback; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function __($value) { return $value; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(preg_replace('/[\r\n]+/', ' ', strip_tags($value))); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function current_time($format) { return '2026-11-12 10:00:00'; }
function mysql2date($format, $date) { return $date; }
function wp_next_scheduled($hook) { return $GLOBALS['scheduled'][$hook] ?? false; }
function wp_schedule_event($timestamp, $recurrence, $hook) { $GLOBALS['scheduled'][$hook] = $timestamp; return true; }
function is_wp_error($value) { return false; }
function get_theme_mod($name) { return 7; }
function wp_get_attachment_image_url($id, $size) { return 'https://example.org/logo.png'; }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_mail($to, $subject, $body, $headers = array()) {
    $GLOBALS['mail'][] = compact('to', 'subject', 'body', 'headers');
    return $GLOBALS['mail_succeeds'] && $to !== ($GLOBALS['fail_to'] ?? null);
}
class BatchReminderDatabase extends ReminderDatabase {
    public $cursor = 0;
    public $sent = array();
    public function prepare($sql, ...$args) {
        if (strpos($sql, 'r.id > %d') !== false) {
            $this->cursor = $args[2];
            return ' AND cursor';
        }
        if (strpos($sql, 'AND cursor') === false) { $this->cursor = 0; }
        return $sql;
    }
    public function get_results($sql) {
        $rows = array();
        for ($id = $this->cursor + 1; $id <= 501 && count($rows) < 500; $id++) {
            if (isset($this->sent[$id])) { continue; }
            $rows[] = (object) array('id' => $id, 'name' => 'Attendee', 'email' => 'person' . $id . '@example.org',
                'title' => 'Large event', 'starts_at' => '2026-11-13 09:00:00');
        }
        return $rows;
    }
    public function update($table, $values, $where, $format, $where_format) {
        $id = $where['id'];
        if (array_key_exists('reminder_sent_at', $where) && isset($this->sent[$id])) { return 0; }
        if ($values['reminder_sent_at'] === null) { unset($this->sent[$id]); } else { $this->sent[$id] = $values['reminder_sent_at']; }
        return 1;
    }
}
class TestEmailRedirect extends Exception {
    public $notice;
    public function __construct($notice) { $this->notice = $notice; }
}
function calsys_require_staff() { $GLOBALS['staff_check'] = true; }
function check_admin_referer($action) { $GLOBALS['nonce_check'] = $action; }
function calsys_admin_redirect($page, $notice) { throw new TestEmailRedirect($notice); }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
class ReminderDatabase {
    public $prefix = 'wp_';
    public $last_error = '';
    public $queries = array();
    public $sent_at = null;
    public function prepare($sql, ...$args) { $this->queries[] = array($sql, $args); return $sql; }
    public function get_var($sql) { return 'Blue room'; }
    public function get_results($sql) {
        return $this->sent_at === null ? array((object) array(
            'id' => 1, 'name' => '<Alex>', 'email' => 'alex@example.org',
            'title' => 'Story time', 'starts_at' => '2026-11-13 09:00:00',
        )) : array();
    }
    public function update($table, $values, $where, $format, $where_format) {
        if (array_key_exists('reminder_sent_at', $where) && $this->sent_at !== null) { return 0; }
        $this->sent_at = $values['reminder_sent_at'];
        return 1;
    }
}
class SqliteReminderDatabase {
    public $prefix = 'wp_';
    public $last_error = '';
    public $db;
    public function __construct() {
        $this->db = new SQLite3(':memory:');
        $this->db->exec('CREATE TABLE wp_calsys_events (id INTEGER, title TEXT, starts_at TEXT, status TEXT)');
        $this->db->exec('CREATE TABLE wp_calsys_registrations (id INTEGER, event_id INTEGER, name TEXT, email TEXT, reminder_opt_in INTEGER, reminder_sent_at TEXT)');
        $this->db->exec("INSERT INTO wp_calsys_events VALUES (1, 'Large event', '2026-11-13 09:00:00', 'published'), (2, 'Draft', '2026-11-13 09:00:00', 'draft'), (3, 'Too late', '2026-11-14 09:00:00', 'published'), (4, 'Past', '2026-11-12 09:00:00', 'published')");
        for ($id = 1; $id <= 501; $id++) {
            $this->db->exec("INSERT INTO wp_calsys_registrations VALUES ($id, 1, 'Attendee', 'person$id@example.org', 1, NULL)");
        }
        $this->db->exec("INSERT INTO wp_calsys_registrations VALUES (502, 2, 'Draft', 'draft@example.org', 1, NULL), (503, 3, 'Future', 'future@example.org', 1, NULL), (504, 4, 'Past', 'past@example.org', 1, NULL), (505, 1, 'No', 'no@example.org', 0, NULL), (506, 1, 'Sent', 'sent@example.org', 1, '2026-11-12 09:00:00')");
    }
    public function prepare($sql, ...$args) {
        return preg_replace_callback('/%[sd]/', function ($match) use (&$args) {
            $value = array_shift($args);
            return $match[0] === '%d' ? (string) (int) $value : "'" . SQLite3::escapeString($value) . "'";
        }, $sql);
    }
    public function get_results($sql) {
        $this->last_error = '';
        $result = $this->db->query($sql);
        if ($result === false) { $this->last_error = $this->db->lastErrorMsg(); return null; }
        $rows = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) { $rows[] = (object) $row; }
        return $rows;
    }
    public function update($table, $values, $where, $format, $where_format) {
        $value = $values['reminder_sent_at'] === null ? 'NULL' : "'" . SQLite3::escapeString($values['reminder_sent_at']) . "'";
        $condition = array_key_exists('reminder_sent_at', $where) ? ' AND reminder_sent_at IS NULL' : '';
        if (!$this->db->exec("UPDATE $table SET reminder_sent_at = $value WHERE id = " . (int) $where['id'] . $condition)) {
            $this->last_error = $this->db->lastErrorMsg();
            return false;
        }
        return $this->db->changes();
    }
}
require __DIR__ . '/../includes/room-hours.php';
require __DIR__ . '/../includes/core.php';
require __DIR__ . '/../includes/registrations.php';
require __DIR__ . '/../includes/waitlist.php';
require __DIR__ . '/../includes/room-actions.php';
require __DIR__ . '/../includes/admin-settings.php';

$disabled = ($argv[1] ?? '') === 'disabled';
$bottom = ($argv[1] ?? '') === 'bottom';
$defaults_only = ($argv[1] ?? '') === 'defaults';
$email_settings = calsys_default_settings()['email_settings'];
$email_settings['from_email'] = 'calendar@example.org';
$email_settings['logo_placement'] = $bottom ? 'bottom' : 'top';
$email_settings['templates']['event_reminder'] = array('subject' => 'Soon: {event}', 'body' => "Hi {name}\n{datetime}");
$email_settings['templates']['event_registration'] = array('subject' => 'Booked: {event}', 'body' => 'Hello {name}, {datetime}');
$email_settings['templates']['room_approved'] = array('subject' => 'Approved: {room}', 'body' => "Hi {name}\n{comment}");
$options = array('admin_email' => 'staff@example.org', 'date_format' => 'Y-m-d', 'time_format' => 'H:i',
    'calsys_settings' => array('email_settings' => $email_settings, 'notifications' => array('email_event_reminders' => $disabled ? 0 : 1)));
$scheduled = array();
$mail = array();
$mail_succeeds = false;
$wpdb = new ReminderDatabase();

if ($defaults_only) {
    unset($options['calsys_settings']['email_settings']);
    $mail_succeeds = true;
    calsys_send_email('person@example.org', 'Original subject', "Original\nmessage", 'event_reminder', array('name' => 'Alex'));
    expect($mail[0]['subject'] === 'Original subject' && $mail[0]['body'] === "Original\nmessage" && $mail[0]['headers'] === array(), 'Existing sites must retain their plain-text messages and WordPress sender by default');
    exit(0);
}
expect(calsys_validate_email_settings($email_settings) === $email_settings, 'Valid templates must validate');
$invalid = $email_settings;
$invalid['from_email'] = "sender@example.org\r\nBcc: other@example.org";
expect(calsys_validate_email_settings($invalid) === false, 'Header injection in sender must be rejected');
$invalid = $email_settings;
$invalid['templates']['event_reminder']['body'] = '{missing}';
expect(calsys_validate_email_settings($invalid) === false, 'Unknown placeholders must be rejected');
$invalid = $email_settings;
$invalid['templates']['room_approved']['body'] = 'Approved';
expect(calsys_validate_email_settings($invalid) === false, 'Decision templates must retain staff comments');

calsys_schedule_reminder_cron();
expect(isset($scheduled['calsys_send_due_event_reminders']), 'Hourly job must be scheduled even when reminders are disabled');
expect(in_array('calsys_reconcile_waitlists', $hooks['calsys_send_due_event_reminders'][5], true), 'Waitlists must be reconciled before reminder delivery');
calsys_schedule_reminder_cron();
expect(count($scheduled) === 1, 'Hourly job must not be duplicated');
calsys_send_due_event_reminders();
if ($disabled) {
    expect(count($mail) === 0 && count($wpdb->queries) === 0, 'Disabled reminders must not query or send to previous opt-ins');
    exit(0);
}
expect(count($mail) === 1 && $wpdb->sent_at === null, 'Failed reminder must be released for retry');
expect(strpos($wpdb->queries[0][0], 'r.reminder_opt_in = 1') !== false &&
    strpos($wpdb->queries[0][0], "e.status = 'published'") !== false &&
    $wpdb->queries[0][1] === array('2026-11-12 10:00:00', '2026-11-13 10:00:00'), 'Reminder query must select opted-in published events within 24 hours');
expect($mail[0]['to'] === 'alex@example.org' && $mail[0]['subject'] === 'Soon: Story time', 'Reminder must use the configured template and recipient');
expect((strpos($mail[0]['body'], '<img') < strpos($mail[0]['body'], 'Hi &lt;Alex&gt;')) !== $bottom, 'Configured logo placement and escaped personalized message must be rendered');
expect(in_array('From: calendar@example.org', $mail[0]['headers'], true) &&
    in_array('Content-Type: text/html; charset=UTF-8', $mail[0]['headers'], true), 'Configured sender and HTML logo must be included in the mail headers');
$mail_succeeds = true;
calsys_send_due_event_reminders();
expect(count($mail) === 2 && $wpdb->sent_at === '2026-11-12 10:00:00', 'A successful retry must record the reminder');
calsys_send_due_event_reminders();
expect(count($mail) === 2, 'Sent reminders must not be sent again');
$booking = (object) array('source_type' => '', 'requester_email' => 'alex@example.org', 'requester_name' => 'Alex',
    'room_id' => 1, 'booking_date' => '2026-11-15', 'starts_at' => '10:00', 'ends_at' => '11:00');
expect(calsys_notify_room_status($booking, 'approved', 'Please bring ID.'), 'Approved room email must be sent');
expect($mail[2]['subject'] === 'Approved: Blue room' && strpos($mail[2]['body'], 'Please bring ID.') !== false, 'Approval template must deliver staff comments');
try {
    calsys_test_email();
    expect(false, 'Test email action must redirect');
} catch (TestEmailRedirect $redirect) {
    expect($redirect->notice === 'test_email_sent' && $staff_check && $nonce_check === 'calsys_test_email', 'Test email must require staff and a nonce and report success');
}
expect($mail[3]['to'] === 'staff@example.org', 'Test email must use the saved staff notification address');
expect(calsys_send_email('person@example.org', 'Default subject', 'Default body', 'event_registration',
    array('name' => 'Pat', 'event' => 'Book club', 'datetime' => 'December 1, 10:00')), 'Registration confirmation must be sent');
expect($mail[4]['subject'] === 'Booked: Book club' && strpos($mail[4]['body'], 'Hello Pat, December 1, 10:00') !== false &&
    in_array('From: calendar@example.org', $mail[4]['headers'], true), 'Registration confirmation must use its template and configured sender');
$mail_succeeds = false;
try {
    calsys_test_email();
    expect(false, 'Failed test email must redirect');
} catch (TestEmailRedirect $redirect) {
    expect($redirect->notice === 'test_email_failed', 'A failed test email must report failure');
}
if ($bottom) { exit(0); }
$wpdb = new BatchReminderDatabase();
$mail = array();
$mail_succeeds = true;
$fail_to = 'person1@example.org';
calsys_send_due_event_reminders();
expect(count($mail) === 501 && count($wpdb->sent) === 500 && !isset($wpdb->sent[1]), 'A failed send must not block later batches of reminders');
$fail_to = null;
calsys_send_due_event_reminders();
expect(count($mail) === 502 && count($wpdb->sent) === 501, 'A later run must retry only the failed reminder');
if (class_exists('SQLite3')) {
    $wpdb = new SqliteReminderDatabase();
    $mail = array();
    $fail_to = 'person1@example.org';
    calsys_send_due_event_reminders();
    expect(count($mail) === 501 && $wpdb->db->querySingle("SELECT COUNT(*) FROM wp_calsys_registrations WHERE event_id = 1 AND id <= 501 AND reminder_sent_at IS NOT NULL") === 500, 'Real SQL must send all eligible batches and exclude ineligible registrations');
    $fail_to = null;
    calsys_send_due_event_reminders();
    expect(count($mail) === 502 && $mail[501]['to'] === 'person1@example.org', 'Real SQL must retry a failed reminder without resending successful reminders');
}

foreach (array('disabled', 'bottom', 'defaults') as $mode) {
    $process = proc_open(array(PHP_BINARY, __FILE__, $mode), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    expect(is_resource($process), 'Could not start reminder test: ' . $mode);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($process) === 0, 'Reminder test failed: ' . $mode . ': ' . $output);
}
echo "Email reminders passed.\n";
