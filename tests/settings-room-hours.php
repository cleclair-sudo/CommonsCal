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
    exit(0);
}

$options = array('admin_email' => 'staff@example.org');
$defaults = calsys_default_settings();
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
foreach ($defaults['event_categories'] as $key => $label) {
    $_POST['event_categories'][] = array('key' => $key, 'label' => $label);
}
$_POST['weekly_hours'][1] = array('mode' => 'open', 'open' => '09:00', 'close' => '17:00');
$_POST['weekly_hours'][0] = array('mode' => 'closed', 'open' => '', 'close' => '');

expect(save_settings() === 'settings_saved', 'Valid opening hours must save');
expect($nonce_action === 'calsys_save_settings', 'Settings save must check its nonce');
$saved = $options['calsys_settings'];
expect($saved['booking_rules']['weekly_hours'][1] === $_POST['weekly_hours'][1], 'Weekly opening hours must persist');
expect($saved['booking_rules']['weekly_hours'][0]['mode'] === 'closed', 'Closed weekdays must persist');
expect($saved['booking_rules']['holiday_hours'][0]['annual'] === 1, 'Recurring special hours must persist');
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
echo "Settings room hours passed.\n";
