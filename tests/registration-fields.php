<?php
// Run with: php tests/registration-fields.php
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', __FILE__);

function add_action() {}
function add_shortcode() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function __($text) { return $text; }
function esc_html__($text) { return esc_html($text); }
function esc_html_e($text) { echo esc_html($text); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_textarea($text) { return esc_html($text); }
function selected($value, $expected) { if ($value === $expected) { echo 'selected'; } }
function checked($value) { if ($value) { echo 'checked'; } }

require __DIR__ . '/../includes/core.php';
require __DIR__ . '/../includes/calendars.php';

function expect($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

foreach (array('text', 'textarea', 'email', 'phone', 'select', 'checkbox') as $type) {
    foreach (array(false, true) as $required) {
        $html = calsys_registration_form_fields(array(array(
            'label' => 'Example',
            'type' => $type,
            'required' => $required,
            'options' => array('First'),
        )));
        expect((bool) preg_match('/\srequired(?:\s|>)/', $html) === $required, "$type: incorrect required attribute");
    }
}

foreach (array('text', 'select') as $type) {
    ob_start();
    calsys_registration_field_editor_row(0, array('type' => $type, 'options' => array('First')));
    $html = ob_get_clean();
    preg_match('/<textarea[^>]*>/', $html, $match);
    expect(isset($match[0]) && (bool) preg_match('/\srequired(?:\s|>)/', $match[0]) === ($type === 'select'), "$type: incorrect dropdown options requirement");
}

echo "Registration field rendering passed.\n";
