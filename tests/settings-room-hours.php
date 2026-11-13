<?php
// Run with: php tests/settings-room-hours.php
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', __FILE__);

class TestSettingsRedirect extends Exception {
    public $notice;
    public function __construct($notice) { $this->notice = $notice; }
}
function add_action() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function __($text) { return $text; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return esc_attr($value); }
function esc_textarea($value) { return esc_html($value); }
function wp_json_encode($value) { return json_encode($value); }
function esc_html_e($value) { echo esc_html($value); }
function esc_attr_e($value) { echo esc_attr($value); }
function checked($value) { if ($value) { echo 'checked="checked"'; } }
function selected($value, $expected) { if ((string) $value === (string) $expected) { echo 'selected="selected"'; } }
function admin_url($path) { return 'https://example.org/wp-admin/' . $path; }
function home_url($path) { return 'https://example.org' . $path; }
function add_query_arg($key, $value, $url) { return $url . '?' . urlencode($key) . '=' . urlencode($value); }
function wp_nonce_field() {}
function submit_button($label) { echo '<button type="submit">' . esc_html($label) . '</button>'; }
function current_time($format) { return '2026-11'; }
function calsys_admin_rooms_settings_panel() {}
function calsys_admin_equipment_settings() {}
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return $value; }
function sanitize_text_field($value) { return trim($value); }
function sanitize_textarea_field($value) { return trim($value); }
function sanitize_hex_color($value) { return preg_match('/^#[a-f0-9]{6}$/i', $value) ? $value : null; }
function sanitize_email($value) { return $value; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function wp_unslash($value) { return $value; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value) { $GLOBALS['options'][$name] = $value; return true; }
function calsys_require_staff() {}
function check_admin_referer($action) { $GLOBALS['nonce_action'] = $action; }
function calsys_admin_redirect($page, $notice) { throw new TestSettingsRedirect($notice); }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
function save_settings() {
    try {
        calsys_save_settings();
    } catch (TestSettingsRedirect $redirect) {
        return $redirect->notice;
    }
    throw new RuntimeException('Settings save did not redirect');
}

require __DIR__ . '/../includes/room-hours.php';
require __DIR__ . '/../includes/core.php';
require __DIR__ . '/../includes/admin-settings.php';

if (($argv[1] ?? '') === 'reload') {
    $options = json_decode(stream_get_contents(STDIN), true);
    $rules = calsys_get_settings()['booking_rules'];
    expect($rules['weekly_hours'][1] === array('mode' => 'open', 'open' => '09:00', 'close' => '17:00'), 'Reloading settings must retain weekly hours');
    expect($rules['holiday_hours'][0]['annual'] === 1 && $rules['holiday_hours'][0]['open'] === '10:00', 'Reloading settings must retain special hours');
    $email = calsys_get_settings()['email_settings'];
    expect($email['from_email'] === 'calendar@example.org' && $email['logo_placement'] === 'bottom' && $email['templates']['event_reminder']['subject'] === 'Coming up: {event}', 'Reloading settings must retain email preferences');
    expect(calsys_get_settings()['notifications']['email_event_registrant'] === 0, 'An explicitly disabled confirmation preference must stay disabled');
    $_GET = array();
    ob_start();
    calsys_admin_settings_page();
    $html = ob_get_clean();
    preg_match('/<input name="notifications\[email_event_registrant\]"[^>]*>/', $html, $confirmation);
    preg_match('/<input name="notifications\[email_event_reminders\]"[^>]*>/', $html, $reminder);
    expect(strpos($confirmation[0], 'checked') === false && strpos($reminder[0], 'checked') !== false, 'The displayed controls must reflect the independently saved settings');
    exit(0);
}

$options = array('admin_email' => 'staff@example.org');
$defaults = calsys_default_settings();
expect($defaults['notifications']['email_event_registrant'] === 1, 'New settings must enable immediate registration confirmations by default');
$_POST = array(
    'calendar_colors' => $defaults['calendar_colors'],
    'staff_types' => array(),
    'event_categories' => array(),
    'booking_rules' => $defaults['booking_rules'],
    'event_registration' => $defaults['event_registration'],
    'calendar_display' => array('week_start_day' => 'site', 'initial_month' => 'current'),
    'notifications' => array('staff_email' => 'staff@example.org'),
    'privacy' => $defaults['privacy'],
    'room_schedule_present' => '1',
    'weekly_hours' => array_fill(0, 7, array('mode' => 'unrestricted', 'open' => '', 'close' => '')),
    'holiday_hours' => array(array('date' => '2026-12-25', 'mode' => 'open', 'open' => '10:00', 'close' => '13:00', 'annual' => '1')),
);
$_POST['event_registration']['default_fields_present'] = '1';
foreach ($defaults['staff_types'] as $key => $type) {
    $_POST['staff_types'][] = array('key' => $key, 'label' => $type['label'], 'color' => $type['color']);
}
foreach ($defaults['event_categories'] as $key => $category) {
    $_POST['event_categories'][] = array('key' => $key, 'label' => $category['label'], 'color' => $category['color']);
}
$_POST['email_settings'] = $defaults['email_settings'];
$_POST['email_settings']['from_email'] = 'calendar@example.org';
$_POST['email_settings']['logo_placement'] = 'bottom';
$_POST['email_settings']['templates']['event_reminder'] = array('subject' => 'Coming up: {event}', 'body' => 'Hi {name}, {datetime}');
$_POST['notifications']['email_event_reminders'] = '1';
$_POST['notifications']['email_event_registrant'] = '1';
$_POST['weekly_hours'][1] = array('mode' => 'open', 'open' => '09:00', 'close' => '17:00');
$_POST['weekly_hours'][0] = array('mode' => 'closed', 'open' => '', 'close' => '');

expect(save_settings() === 'settings_saved', 'Valid opening hours must save');
expect($nonce_action === 'calsys_save_settings', 'Settings save must check its nonce');
$saved = $options['calsys_settings'];
expect($saved['notifications']['email_event_registrant'] === 1 && $saved['notifications']['email_event_reminders'] === 1, 'Both event email settings must save when enabled');
unset($_POST['notifications']['email_event_registrant']);
expect(save_settings() === 'settings_saved', 'Disabling the confirmation must save');
$saved = $options['calsys_settings'];
expect($saved['booking_rules']['weekly_hours'][1] === $_POST['weekly_hours'][1], 'Weekly opening hours must persist');
expect($saved['booking_rules']['weekly_hours'][0]['mode'] === 'closed', 'Closed weekdays must persist');
expect($saved['booking_rules']['holiday_hours'][0]['annual'] === 1, 'Recurring special hours must persist');
expect($saved['notifications']['email_event_reminders'] === 1 && $saved['email_settings'] === $_POST['email_settings'], 'Reminder, sender, logo, and email templates must persist');
expect(calsys_room_is_within_hours('2026-12-21', '09:00', '17:00', $saved['booking_rules']), 'Saved hours must permit matching bookings');
expect(!calsys_room_is_within_hours('2026-12-21', '08:00', '09:00', $saved['booking_rules']), 'Saved hours must reject bookings outside the interval');
expect(calsys_room_is_within_hours('2027-12-25', '10:00', '13:00', $saved['booking_rules']), 'Saved recurring special hours must apply');

$process = proc_open(array(PHP_BINARY, __FILE__, 'reload'), array(
    0 => array('pipe', 'r'),
    1 => array('pipe', 'w'),
    2 => array('pipe', 'w'),
), $pipes);
expect(is_resource($process), 'Could not start settings reload test');
fwrite($pipes[0], json_encode($options));
fclose($pipes[0]);
$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
expect(proc_close($process) === 0, 'Settings reload failed: ' . $output);

$_POST['weekly_hours'][1]['mode'] = 'unrestricted';
expect(save_settings() === 'settings_invalid_hours', 'Times for an unrestricted day must produce an error');
expect($options['calsys_settings'] === $saved, 'Invalid hours must not overwrite saved settings');
$_POST['weekly_hours'][1]['mode'] = 'open';
$_POST['holiday_hours'][0]['mode'] = 'closed';
expect(save_settings() === 'settings_invalid_hours', 'Times for a closed special date must produce an error');
expect($options['calsys_settings'] === $saved, 'Invalid special hours must not overwrite saved settings');
$_POST['holiday_hours'][0]['mode'] = 'open';
$_POST['email_settings']['templates']['room_denied']['body'] = 'No comment placeholder';
expect(save_settings() === 'settings_invalid_email_templates', 'Room decisions must preserve a place for comments');
expect($options['calsys_settings'] === $saved, 'Invalid email templates must not overwrite saved settings');
$_POST['email_settings']['templates']['room_denied']['body'] = '{message}';
$_POST['notifications']['email_event_registrant'] = '1';
unset($_POST['notifications']['email_event_reminders']);
expect(save_settings() === 'settings_saved', 'Each email option must be independently configurable');
expect($options['calsys_settings']['notifications']['email_event_registrant'] === 1 &&
    $options['calsys_settings']['notifications']['email_event_reminders'] === 0, 'Saving one option must not enable the other');
$_GET = array();
ob_start();
calsys_admin_settings_page();
$html = ob_get_clean();
expect(strpos($html, 'href="#calsys-settings-event-emails"') !== false &&
    strpos($html, 'id="calsys-settings-event-emails"') < strpos($html, 'id="calsys-settings-calendars"'), 'Event email settings must be easy to find at the top');
expect(substr_count($html, 'name="notifications[email_event_registrant]"') === 1 &&
    substr_count($html, 'name="notifications[email_event_reminders]"') === 1, 'Each email setting must have exactly one checkbox');
preg_match('/<section[^>]*id="calsys-settings-event-emails"[^>]*>(.*?)<\/section>/s', $html, $section);
expect(strpos($section[1], 'notifications[email_event_registrant]') !== false &&
    strpos($section[1], 'notifications[email_event_reminders]') !== false &&
    strpos($section[1], 'Registrants must opt in') !== false &&
    strpos($section[1], 'type="submit"') !== false, 'The event email panel must contain both controls, opt-in guidance, and a save button');
echo "Settings room hours passed.\n";
