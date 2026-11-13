<?php
// Run with: php tests/newsletter.php (requires DOM, mbstring, GD, and Composer dependencies).
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', dirname(__DIR__) . '/community-calendar-study-rooms.php');
function add_action() {}
function __($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false); }
function esc_html__($value) { return esc_html($value); }
function esc_html_e($value) { echo esc_html($value); }
function esc_attr($value) { return esc_html($value); }
function wp_unslash($value) { return $value; }
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function wp_date($format, $time, $timezone) { return (new DateTimeImmutable('@' . $time))->setTimezone($timezone)->format($format); }
function get_option($key) { return $key === 'date_format' ? 'Y-m-d' : 'g:i a'; }
function get_bloginfo($key) { return 'Library & Friends'; }
function get_theme_mod($key) { return $GLOBALS['logo_id']; }
function absint($value) { return abs((int) $value); }
function calsys_table($name) { return 'wp_calsys_' . $name; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function get_attached_file($id) { return $GLOBALS['attachments'][$id] ?? false; }
function wp_attachment_is_image($id) { return isset($GLOBALS['attachments'][$id]); }
function wp_tempnam($name) { $file = tempnam(sys_get_temp_dir(), 'cal'); $GLOBALS['temporary_files'][] = $file; return $file; }
function wp_delete_file($file) { unlink($file); }
function wp_get_image_editor($path) { return new NewsletterImageEditor($path); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function calsys_require_staff() { if (empty($GLOBALS['staff'])) { wp_die('Forbidden'); } }
function check_admin_referer($action) { if (empty($GLOBALS['nonce'])) { wp_die('Invalid nonce'); } }
function wp_die($message) { throw new RuntimeException($message); }
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class NewsletterImageEditor {
    private $image;
    public function __construct($path) { $this->image = imagecreatefrompng($path); }
    public function get_size() { return array('width' => imagesx($this->image), 'height' => imagesy($this->image)); }
    public function resize($width, $height, $crop) { return true; }
    public function save($path, $mime) {
        if (!empty($GLOBALS['alter_image_format'])) {
            $path = substr($path, 0, -4) . '.webp';
        }
        $GLOBALS['temporary_files'][] = $path;
        imagepng($this->image, $path);
        return array('path' => $path);
    }
}
class NewsletterDatabase {
    public $last_error = '';
    public $args = array();
    public $sql = '';
    public $rows = array();
    public function prepare($sql, ...$args) { $this->sql = $sql; $this->args = $args; return $sql; }
    public function get_results($sql) {
        if ($this->last_error) { return false; }
        return array_values(array_filter($this->rows, function ($row) {
            return $row->status === 'published' && $row->starts_at < $this->args[0] && $row->ends_at > $this->args[1];
        }));
    }
}
function expect($condition, $message) { if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); } }
require __DIR__ . '/../includes/newsletter.php';

foreach (array('', '2026-13', '2026-2', '0000-01', '9999-12', array('2026-02')) as $invalid) {
    expect(calsys_newsletter_month($invalid) === false, 'Invalid months must be rejected');
}
list($start, $end) = calsys_newsletter_month('2026-02');
expect($start->format('Y-m-d H:i:s') === '2026-02-01 00:00:00' && $end->format('Y-m-d H:i:s') === '2026-03-01 00:00:00', 'Month boundaries must use local dates');
$wpdb = new NewsletterDatabase();
$wpdb->rows = array(
    (object) array('id' => 1, 'title' => "Story & craft - Caf\xc3\xa9", 'description' => '<p>First line</p><p>Second <strong>line</strong> &amp; more</p>', 'starts_at' => '2026-02-01 10:00:00', 'ends_at' => '2026-02-01 11:00:00', 'image_id' => 2, 'status' => 'published'),
    (object) array('id' => 2, 'title' => 'Overnight', 'description' => 'Across the boundary', 'starts_at' => '2026-01-31 23:00:00', 'ends_at' => '2026-02-01 01:00:00', 'image_id' => 0, 'status' => 'published'),
    (object) array('id' => 3, 'title' => 'Draft', 'description' => '', 'starts_at' => '2026-02-02 10:00:00', 'ends_at' => '2026-02-02 11:00:00', 'image_id' => 0, 'status' => 'draft'),
    (object) array('id' => 4, 'title' => 'Next month', 'description' => '', 'starts_at' => '2026-03-01 00:00:00', 'ends_at' => '2026-03-01 01:00:00', 'image_id' => 0, 'status' => 'published'),
);
$events = calsys_newsletter_events($start, $end);
expect(count($events) === 2 && strpos($wpdb->sql, "status = 'published'") !== false && strpos($wpdb->sql, 'ORDER BY starts_at ASC, id ASC') !== false, 'Query must select published overlapping events in chronological order');
expect($wpdb->args === array('2026-03-01 00:00:00', '2026-02-01 00:00:00'), 'Query must use exclusive month boundaries');
$html = calsys_newsletter_html($start, $events, array(1 => 'logo', 2 => 'event-image'), 1);
expect(strpos($html, 'Library &amp; Friends') !== false && strpos($html, 'Story &amp; craft') !== false, 'Site and event names must be escaped');
expect(strpos($html, '2026-02-01 10:00 am - 2026-02-01 11:00 am') !== false && strpos($html, 'First line<br') !== false && strpos($html, 'Second line &amp; more') !== false, 'The newsletter must include event times and separate description paragraphs');
expect(strpos($html, 'src="logo"') !== false && strpos($html, 'src="event-image"') !== false, 'The logo and event images must be included');
expect(strpos($html, 'Draft') === false && strpos($html, 'Next month') === false, 'Draft and out-of-month events must be excluded');

$attachments = array();
$temporary_files = array();
foreach (array(1 => array(255, 0, 0), 2 => array(0, 0, 255)) as $id => $color) {
    $path = tempnam(sys_get_temp_dir(), 'cal-test');
    $image = imagecreatetruecolor(16, 16);
    imagefill($image, 0, 0, imagecolorallocate($image, $color[0], $color[1], $color[2]));
    imagepng($image, $path);
    unset($image);
    $attachments[$id] = $path;
}
$logo_id = 1;
$pdf = calsys_newsletter_pdf($start, $end);
expect(is_string($pdf) && strpos($pdf, '%PDF-') === 0, 'The export must generate an actual PDF');
expect(substr_count($pdf, '/Subtype /Image') >= 2, 'The PDF must embed the logo and event image');
foreach ($temporary_files as $path) { expect(!file_exists($path), 'Temporary image files must be removed'); }
$original_rows = $wpdb->rows;
$wpdb->rows = array();
$logo_id = 0;
$empty_pdf = calsys_newsletter_pdf($start, $end);
expect(is_string($empty_pdf) && strpos($empty_pdf, '%PDF-') === 0, 'A month without events or a logo must still produce a PDF');
expect(strpos(calsys_newsletter_html($start, array(), array(), 0), 'No published events for this month.') !== false, 'An empty month must be explained');
$many = clone $original_rows[0];
$many->image_id = 0;
$many->description = 'STARTMARKER ' . str_repeat('Newsletter event description. ', 100) . ' ENDMARKER';
for ($i = 0; $i < 25; $i++) { $wpdb->rows[] = clone $many; }
$many_pdf = calsys_newsletter_pdf($start, $end);
expect(is_string($many_pdf) && preg_match_all('/\/Type\s*\/Page\b/', $many_pdf) > 1, 'Long newsletters must span multiple pages');
$wpdb->rows = $original_rows;
$logo_id = 1;
$alter_image_format = true;
expect(is_wp_error(calsys_newsletter_image(1)), 'An unexpected image output format must produce an error');
foreach ($temporary_files as $path) { expect(!file_exists($path), 'Unexpected image output files must be removed'); }
$alter_image_format = false;
$missing_image = $attachments[2];
unset($attachments[2]);
expect(is_wp_error(calsys_newsletter_pdf($start, $end)), 'Unavailable images must produce an error');
unlink($missing_image);
foreach ($attachments as $path) { unlink($path); }
$wpdb->last_error = 'simulated database failure';
expect(is_wp_error(calsys_newsletter_events($start, $end)), 'Database failures must produce an error');

$staff = false;
$nonce = true;
$_POST = array('month' => '2026-02');
try { calsys_export_newsletter(); expect(false, 'Non-staff must be rejected'); } catch (RuntimeException $error) { expect($error->getMessage() === 'Forbidden', 'Non-staff must be rejected before export'); }
$staff = true;
$nonce = false;
try { calsys_export_newsletter(); expect(false, 'Invalid nonce must be rejected'); } catch (RuntimeException $error) { expect($error->getMessage() === 'Invalid nonce', 'Invalid nonce must be rejected'); }
$nonce = true;
$_POST = array('month' => array('2026-02'));
try { calsys_export_newsletter(); expect(false, 'Invalid month must be rejected'); } catch (RuntimeException $error) { expect($error->getMessage() === 'Choose a valid newsletter month.', 'Invalid month must be rejected'); }
echo "Newsletter PDF passed.\n";
