<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Provides WordPress with the latest published GitHub release, when available. */
function calsys_github_update($update, $plugin_data, $plugin_file) {
    if ($plugin_file !== plugin_basename(CALSYS_PLUGIN_FILE)) {
        return $update;
    }

    $response = wp_remote_get('https://api.github.com/repos/cleclair-sudo/CommonsCal/releases/latest', array(
        'timeout' => 10,
        'headers' => array('Accept' => 'application/vnd.github+json'),
    ));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        $detail = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
        error_log('CommonsCal: GitHub update check failed: ' . $detail);
        set_site_transient('calsys_update_error', 1, 12 * HOUR_IN_SECONDS);
        return false;
    }

    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($release) || !isset($release['tag_name'], $release['assets'], $release['draft'], $release['prerelease']) ||
        !is_string($release['tag_name']) || !preg_match('/^v?(\d+(?:\.\d+){1,3})$/', $release['tag_name'], $matches) ||
        !is_array($release['assets']) || $release['draft'] !== false || $release['prerelease'] !== false) {
        error_log('CommonsCal: GitHub returned invalid release information.');
        set_site_transient('calsys_update_error', 1, 12 * HOUR_IN_SECONDS);
        return false;
    }

    $version = $matches[1];
    $filename = 'CommonsCal-' . $version . '.zip';
    $package = 'https://github.com/cleclair-sudo/CommonsCal/releases/download/' . rawurlencode($release['tag_name']) . '/' . $filename;
    foreach ($release['assets'] as $asset) {
        if (is_array($asset) && ($asset['name'] ?? '') === $filename &&
            ($asset['browser_download_url'] ?? '') === $package && ($asset['state'] ?? '') === 'uploaded') {
            delete_site_transient('calsys_update_error');
            return array(
                'slug' => dirname($plugin_file),
                'version' => $version,
                'url' => 'https://github.com/cleclair-sudo/CommonsCal/releases',
                'package' => $package,
            );
        }
    }
    error_log('CommonsCal: the latest GitHub release is missing the expected plugin ZIP.');
    set_site_transient('calsys_update_error', 1, 12 * HOUR_IN_SECONDS);
    return false;
}
add_filter('update_plugins_github.com', 'calsys_github_update', 10, 3);

/** Rejects an update package unless it is the expected, compatible plugin release. */
function calsys_check_update_package($source, $remote_source, $upgrader, $hook_extra) {
    if (is_wp_error($source) || ($hook_extra['action'] ?? 'update') !== 'update' ||
        ($hook_extra['plugin'] ?? '') !== plugin_basename(CALSYS_PLUGIN_FILE)) {
        return $source;
    }

    global $wp_filesystem;
    if (!($upgrader instanceof Plugin_Upgrader) ||
        basename(untrailingslashit($source)) !== dirname(plugin_basename(CALSYS_PLUGIN_FILE)) ||
        !$wp_filesystem->exists(trailingslashit($source) . 'community-calendar-study-rooms.php')) {
        return new WP_Error('calsys_invalid_update', __('The CommonsCal update ZIP has an invalid folder or plugin file.', 'community-calendar-study-rooms'));
    }
    $checked = $upgrader->check_package($source);
    if (is_wp_error($checked)) {
        return $checked;
    }
    $updates = get_site_transient('update_plugins');
    $expected = $updates->response[plugin_basename(CALSYS_PLUGIN_FILE)]->new_version ?? '';
    $data = $upgrader->new_plugin_data;
    if (!is_array($data) || ($data['Name'] ?? '') !== 'CommonsCal' ||
        ($data['Version'] ?? '') !== $expected ||
        ($data['UpdateURI'] ?? '') !== 'https://github.com/cleclair-sudo/CommonsCal') {
        return new WP_Error('calsys_invalid_update', __('The CommonsCal update ZIP does not match the advertised release.', 'community-calendar-study-rooms'));
    }
    return $source;
}
add_filter('upgrader_source_selection', 'calsys_check_update_package', 10, 4);

/** Shows update-check failures to administrators on WordPress update screens. */
function calsys_update_error_notice() {
    if (!current_user_can('update_plugins') || !get_site_transient('calsys_update_error')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, array('plugins', 'plugins-network', 'update-core', 'update-core-network'), true)) {
        return;
    }
    echo '<div class="notice notice-warning"><p>' .
        esc_html__('CommonsCal could not check GitHub for updates. Ensure the repository is public and its latest release includes the plugin ZIP, or update by uploading a ZIP.', 'community-calendar-study-rooms') .
        '</p></div>';
}
add_action('admin_notices', 'calsys_update_error_notice');
add_action('network_admin_notices', 'calsys_update_error_notice');
