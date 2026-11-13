<?php
// Run with: php tests/series-display.php
define('ABSPATH', __DIR__ . '/');
function add_shortcode() {}
function __($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($value) { return esc_html($value); }
function esc_attr($value) { return esc_html($value); }
function esc_attr__($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_get_settings() {
    return array('event_registration' => array('enabled' => 1, 'form_intro' => '', 'show_privacy_notice' => false,
        'name_label' => 'Name', 'email_label' => 'Email', 'submit_label' => 'Register',
        'full_message' => 'Full', 'closed_message' => 'Closed', 'disabled_message' => 'Disabled', 'confirmation_message' => 'Confirmed',
        'reminder_label' => 'Email me a reminder'),
        'notifications' => array('email_event_reminders' => !empty($GLOBALS['reminders_enabled'])));
}
function calsys_event_capacity($capacity) { return (int) $capacity; }
function calsys_decode_registration_fields($json) { return json_decode($json, true) ?: array(); }
function calsys_event_registration_is_open($start) { return $start > '2026-11-12 12:00:00'; }
function mysql2date($format, $date) { return $date; }
function get_option($name) { return 'Y-m-d'; }
function admin_url($path) { return '/wp-admin/' . $path; }
function wp_nonce_field($action) { echo '<input type="hidden" name="nonce">'; }
function sanitize_key($value) { return $value; }
function wp_unslash($value) { return $value; }
function get_transient($key) { return $GLOBALS['results'] ?? false; }
class SeriesDisplayDatabase {
    public $last_error = '';
    public $rows = array();
    public $sql = '';
    public function prepare($sql, ...$args) { $this->sql = $sql; return $sql; }
    public function get_results($sql) { return $this->rows; }
}
function expect($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }
require __DIR__ . '/../includes/calendars.php';
$wpdb = new SeriesDisplayDatabase();
$anchor = (object) array('id' => 1, 'title' => 'First', 'starts_at' => '2026-12-01 10:00:00',
    'ends_at' => '2026-12-01 11:00:00', 'description' => '', 'capacity' => 0, 'registration_count' => 0,
    'registration_enabled' => 1, 'registration_fields' => '[]', 'series_id' => 9,
    'recurrence_group' => 'repeat', 'register_all_recurrences' => 1);
$wpdb->rows = array(
    (object) array('id' => 1, 'title' => 'First', 'starts_at' => '2026-12-01 10:00:00', 'registration_enabled' => 1, 'registration_fields' => '[]', 'series_id' => 9, 'series_name' => '<Book Club>'),
    (object) array('id' => 2, 'title' => 'Second', 'starts_at' => '2026-12-02 10:00:00', 'registration_enabled' => 1, 'registration_fields' => '[]', 'series_id' => 9, 'series_name' => '<Book Club>'),
    (object) array('id' => 3, 'title' => 'Different questions', 'starts_at' => '2026-12-03 10:00:00', 'registration_enabled' => 1, 'registration_fields' => '[{"type":"text"}]', 'series_id' => 9, 'series_name' => '<Book Club>'),
    (object) array('id' => 4, 'title' => 'Past', 'starts_at' => '2026-10-01 10:00:00', 'registration_enabled' => 1, 'registration_fields' => '[]', 'series_id' => 9, 'series_name' => '<Book Club>'),
);
$html = calsys_render_event_registration_forms(array($anchor));
expect(strpos($wpdb->sql, "e.status = 'published'") !== false, 'Series listings must exclude drafts');
expect(substr_count($html, '<details class="calsys-series-selection">') === 1 && strpos($html, 'Events in &lt;Book Club&gt;') !== false &&
    strpos($html, 'Different questions') !== false && strpos($html, 'Past') !== false, 'One series dropdown must list all published events and escape names');
expect(strpos($html, 'name="series_events[]" value="2"') !== false && strpos($html, 'name="series_events[]" value="3"') === false && strpos($html, 'name="series_events[]" value="4"') === false, 'Only open events with matching questions must be selectable');
expect(strpos($html, '<form ') < strpos($html, '<details class="calsys-series-selection">') &&
    strpos($html, 'This event') !== false && substr_count($html, 'Not available for combined registration') === 2, 'The series dropdown must be in the form and identify the current and ineligible events');
expect(strpos($html, 'data-calsys-select-series-events') !== false && strpos($html, 'name="all_recurrences"') !== false, 'Select all and recurrence registration controls are missing');
expect(strpos($html, 'name="reminder_opt_in"') === false, 'The global reminder switch must hide the opt-in when disabled');
$reminders_enabled = true;
expect(strpos(calsys_render_event_registration_forms(array($anchor)), 'name="reminder_opt_in"') !== false, 'The global reminder switch must show the opt-in when enabled');
$reminders_enabled = false;
$anchor->register_all_recurrences = 0;
expect(strpos(calsys_render_event_registration_forms(array($anchor)), 'name="all_recurrences"') === false, 'Recurring registration must be opt-in by staff');
$anchor->registration_enabled = 0;
$html = calsys_render_event_registration_forms(array($anchor));
expect(substr_count($html, '<details class="calsys-series-selection">') === 1 && strpos($html, 'Different questions') !== false &&
    strpos($html, 'name="series_events[]"') === false && strpos($html, '<form ') === false, 'Series events must remain visible without registration controls when registration is disabled');
$anchor->registration_enabled = 1;
$anchor->starts_at = '2026-10-01 10:00:00';
$html = calsys_render_event_registration_forms(array($anchor));
expect(substr_count($html, '<details class="calsys-series-selection">') === 1 && strpos($html, 'Past') !== false &&
    strpos($html, 'name="series_events[]"') === false && strpos($html, '<form ') === false, 'Closed registration must still show the series without selection controls');
$anchor->starts_at = '2026-12-01 10:00:00';
$wpdb->rows = array($wpdb->rows[0], $wpdb->rows[2]);
$html = calsys_render_event_registration_forms(array($anchor));
expect(substr_count($html, '<details class="calsys-series-selection">') === 1 && strpos($html, 'Different questions') !== false &&
    strpos($html, 'data-calsys-select-series-events') === false, 'A series without eligible choices must still be visible without select all');
$wpdb->rows = null;
expect(strpos(calsys_render_event_registration_forms(array($anchor)), 'Series events could not be loaded') !== false, 'Series lookup errors must be visible');
$_GET = array('calsys_notice' => 'registration_results', 'calsys_registration_result' => '00000000-0000-4000-8000-000000000001');
$results = array(
    array('id' => 1, 'title' => 'First <event>', 'starts_at' => '2026-12-01 10:00:00', 'status' => 'registration_email_failed'),
    array('id' => 2, 'title' => 'Second', 'starts_at' => '2026-12-02 10:00:00', 'status' => 'event_full'),
);
$notice = calsys_public_notice();
expect(strpos($notice, 'First &lt;event&gt;') !== false && strpos($notice, 'Registered; confirmation email failed') !== false && strpos($notice, 'Full; not registered') !== false, 'Per-event results must distinguish a saved registration from email failure');
$_GET = array('calsys_notice' => 'registration_email_failed');
expect(strpos(calsys_public_notice(), 'registration is complete') !== false && strpos(calsys_public_notice(), 'could not send') !== false, 'Single-event email failure must show that registration was saved');
$_GET = array('calsys_notice' => 'registration_results', 'calsys_registration_result' => '00000000-0000-4000-8000-000000000001');
unset($results);
expect(strpos(calsys_public_notice(), 'expired') !== false, 'Expired results must not look like a successful registration');
echo "Series display passed.\n";
