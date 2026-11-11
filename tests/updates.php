<?php
// Run with: php tests/updates.php
define('ABSPATH', __DIR__ . '/');
define('CALSYS_PLUGIN_FILE', '/plugins/community-calendar-study-rooms/community-calendar-study-rooms.php');
define('HOUR_IN_SECONDS', 3600);

function add_filter() {}
function add_action() {}
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function wp_remote_get($url, $args) { global $response; return $response; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($value) { return $value['code']; }
function wp_remote_retrieve_body($value) { return $value['body']; }
function set_site_transient($name, $value, $expiration) { global $error; $error = $value; }
function delete_site_transient($name) { global $error; $error = false; }
function get_site_transient($name) { global $updates; return $updates; }
function trailingslashit($value) { return rtrim($value, '/') . '/'; }
function untrailingslashit($value) { return rtrim($value, '/'); }
function __($value) { return $value; }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
class WP_Error {
    public function __construct($code, $message) {}
    public function get_error_message() { return 'Simulated failure'; }
}
class Plugin_Upgrader {
    public $new_plugin_data;
    public $result;
    public function check_package($source) { return $this->result ?? $source; }
}
class TestFilesystem {
    public $exists = true;
    public function exists($path) { return $this->exists; }
}
require __DIR__ . '/../includes/updates.php';

$plugin = plugin_basename(CALSYS_PLUGIN_FILE);
$release = array(
    'tag_name' => 'v0.09.19', 'draft' => false, 'prerelease' => false,
    'assets' => array(array(
        'name' => 'CommonsCal-0.09.19.zip', 'state' => 'uploaded',
        'browser_download_url' => 'https://github.com/cleclair-sudo/CommonsCal/releases/download/v0.09.19/CommonsCal-0.09.19.zip',
    )),
);
$response = array('code' => 200, 'body' => json_encode($release));
$error = true;
$result = calsys_github_update(false, array(), $plugin);
expect($result['version'] === '0.09.19' && $result['package'] === $release['assets'][0]['browser_download_url'] && !$error, 'Valid release not offered');
expect(calsys_github_update('other', array(), 'other/plugin.php') === 'other', 'Another plugin was modified');

$release['assets'][0]['browser_download_url'] = 'https://example.invalid/plugin.zip';
$response['body'] = json_encode($release);
expect(calsys_github_update(false, array(), $plugin) === false && $error, 'Untrusted package URL accepted');
$release['prerelease'] = true;
$response['body'] = json_encode($release);
expect(calsys_github_update(false, array(), $plugin) === false, 'Prerelease accepted');
$response = new WP_Error('', '');
expect(calsys_github_update(false, array(), $plugin) === false && $error, 'HTTP errors not reported');

$wp_filesystem = new TestFilesystem();
$upgrader = new Plugin_Upgrader();
$upgrader->new_plugin_data = array('Name' => 'CommonsCal', 'Version' => '0.09.19', 'UpdateURI' => 'https://github.com/cleclair-sudo/CommonsCal');
$updates = (object) array('response' => array($plugin => (object) array('new_version' => '0.09.19')));
$source = '/tmp/community-calendar-study-rooms/';
$hook = array('action' => 'update', 'plugin' => $plugin);
expect(calsys_check_update_package($source, '', $upgrader, $hook) === $source, 'Valid update package rejected');
$upgrader->new_plugin_data['Version'] = '0.09.18';
expect(is_wp_error(calsys_check_update_package($source, '', $upgrader, $hook)), 'Mismatched package version accepted');
expect(is_wp_error(calsys_check_update_package($source, '', $upgrader, array('plugin' => $plugin))), 'Mismatched bulk update accepted');
expect(is_wp_error(calsys_check_update_package('/tmp/wrong-folder/', '', $upgrader, $hook)), 'Incorrect package folder accepted');
$upgrader->result = new WP_Error('', '');
expect(is_wp_error(calsys_check_update_package($source, '', $upgrader, $hook)), 'Incompatible package accepted');
echo "GitHub updates passed.\n";
