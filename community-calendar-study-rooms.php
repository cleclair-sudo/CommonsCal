<?php
/**
 * Plugin Name: CommonsCal
 * Description: Publish events, accept registrations, and manage study room requests.
 * Version: 0.09.18
 * Author: Southern Maine Technology Initiative
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/cleclair-sudo/CommonsCal
 * Text Domain: community-calendar-study-rooms
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CALSYS_VERSION', '0.09.18');
define('CALSYS_DB_VERSION', '9');
define('CALSYS_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/updates.php';
require_once __DIR__ . '/includes/frontend-forms.php';
require_once __DIR__ . '/includes/admin-settings.php';
require_once __DIR__ . '/includes/admin-management.php';
require_once __DIR__ . '/includes/room-hours.php';
require_once __DIR__ . '/includes/room-validation.php';
require_once __DIR__ . '/includes/equipment.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/room-actions.php';
require_once __DIR__ . '/includes/registrations.php';
require_once __DIR__ . '/includes/calendars.php';
require_once __DIR__ . '/includes/reports.php';
