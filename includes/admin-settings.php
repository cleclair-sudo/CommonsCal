<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Enqueues admin styles and scripts for the plugin's management screens. */
function calsys_admin_enqueue($hook_suffix) {
    if (strpos($hook_suffix, 'calsys-') !== false) {
        $style_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system-admin.css';
        wp_enqueue_style(
            'calsys-admin',
            plugins_url('assets/calendar-system-admin.css', CALSYS_PLUGIN_FILE),
            array(),
            file_exists($style_path) ? (string) filemtime($style_path) : CALSYS_VERSION
        );
    }
    if (strpos($hook_suffix, 'calsys-settings') !== false) {
        $script_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system-admin.js';
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(
            'calsys-admin',
            plugins_url('assets/calendar-system-admin.js', CALSYS_PLUGIN_FILE),
            array('jquery', 'wp-color-picker', 'wp-util'),
            file_exists($script_path) ? (string) filemtime($script_path) : CALSYS_VERSION,
            true
        );
        wp_localize_script('calsys-admin', 'calsysSettings', array(
            'typeLabel' => __('Booking type name', 'community-calendar-study-rooms'),
            'typeColor' => __('Booking type color', 'community-calendar-study-rooms'),
            'removeType' => __('Remove', 'community-calendar-study-rooms'),
            'confirmRemoveType' => __('Remove this booking type?', 'community-calendar-study-rooms'),
        ));
        $public_script_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system-public.js';
        wp_enqueue_script(
            'calsys-registration-fields',
            plugins_url('assets/calendar-system-public.js', CALSYS_PLUGIN_FILE),
            array(),
            file_exists($public_script_path) ? (string) filemtime($public_script_path) : CALSYS_VERSION,
            true
        );
        wp_localize_script('calsys-registration-fields', 'calsysPublic', array(
            'registrationFieldTypes' => calsys_registration_field_types(),
            'registrationFieldLabel' => __('Field label', 'community-calendar-study-rooms'),
            'registrationFieldOptions' => __('Dropdown options (one per line)', 'community-calendar-study-rooms'),
            'registrationFieldRequired' => __('Required', 'community-calendar-study-rooms'),
            'registrationFieldRemove' => __('Remove field', 'community-calendar-study-rooms'),
            'registrationFieldType' => __('Field type', 'community-calendar-study-rooms'),
        ));
        return;
    }
    if (strpos($hook_suffix, 'calsys-staff-calendar') !== false || strpos($hook_suffix, 'calsys-rooms') !== false) {
        if (strpos($hook_suffix, 'calsys-rooms') !== false && current_user_can('manage_calendar_system')) {
            wp_enqueue_media();
        }
        $style_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system.css';
        wp_enqueue_style(
            'calsys-public-calendar',
            plugins_url('assets/calendar-system.css', CALSYS_PLUGIN_FILE),
            array(),
            file_exists($style_path) ? (string) filemtime($style_path) : CALSYS_VERSION
        );
        $script_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system-public.js';
        wp_enqueue_script(
            'calsys-staff-calendar-controls',
            plugins_url('assets/calendar-system-public.js', CALSYS_PLUGIN_FILE),
            array(),
            file_exists($script_path) ? (string) filemtime($script_path) : CALSYS_VERSION,
            true
        );
        wp_localize_script('calsys-staff-calendar-controls', 'calsysPublic', array(
            'addStaffTime' => __('Add staff time', 'community-calendar-study-rooms'),
            'editStaffTime' => __('Edit staff time', 'community-calendar-study-rooms'),
            'saveStaffEntry' => __('Save staff entry', 'community-calendar-study-rooms'),
            'saveChanges' => __('Save changes', 'community-calendar-study-rooms'),
            'lastSavedBy' => __('Last saved by: %s', 'community-calendar-study-rooms'),
            'confirmDeleteStaffEntry' => __('Delete this staff entry and its linked room reservation?', 'community-calendar-study-rooms'),
            'editRoomBooking' => __('Edit room booking', 'community-calendar-study-rooms'),
            'confirmDeleteRoomBooking' => __('Delete this room booking?', 'community-calendar-study-rooms'),
            'confirmRoomBooking' => __('Confirm booking', 'community-calendar-study-rooms'),
            'requestRoom' => __('Request a study room', 'community-calendar-study-rooms'),
            'savePublicEvent' => __('Save public event', 'community-calendar-study-rooms'),
            'chooseEventImage' => __('Choose event image', 'community-calendar-study-rooms'),
            'useEventImage' => __('Use this image', 'community-calendar-study-rooms'),
            'removeEventImage' => __('Remove image', 'community-calendar-study-rooms'),
            'invalidEventImage' => __('Please choose an image file.', 'community-calendar-study-rooms'),
            'mediaLibraryUnavailable' => __('The WordPress Media Library is not available on this page.', 'community-calendar-study-rooms'),
            'printUnavailable' => __('The printable view could not be loaded.', 'community-calendar-study-rooms'),
            'printBlocked' => __('Please allow pop-ups to print the calendar.', 'community-calendar-study-rooms'),
        ));
        return;
    }
    if (strpos($hook_suffix, 'calsys-events') !== false) {
        wp_enqueue_media();
        $script_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system-public.js';
        wp_enqueue_script(
            'calsys-event-controls',
            plugins_url('assets/calendar-system-public.js', CALSYS_PLUGIN_FILE),
            array(),
            file_exists($script_path) ? (string) filemtime($script_path) : CALSYS_VERSION,
            true
        );
        wp_localize_script('calsys-event-controls', 'calsysPublic', array(
            'registrationFieldTypes' => calsys_registration_field_types(),
            'registrationFieldLabel' => __('Field label', 'community-calendar-study-rooms'),
            'registrationFieldOptions' => __('Dropdown options (one per line)', 'community-calendar-study-rooms'),
            'registrationFieldRequired' => __('Required', 'community-calendar-study-rooms'),
            'registrationFieldRemove' => __('Remove field', 'community-calendar-study-rooms'),
            'registrationFieldType' => __('Field type', 'community-calendar-study-rooms'),
            'chooseEventImage' => __('Choose event image', 'community-calendar-study-rooms'),
            'useEventImage' => __('Use this image', 'community-calendar-study-rooms'),
            'removeEventImage' => __('Remove image', 'community-calendar-study-rooms'),
            'invalidEventImage' => __('Please choose an image file.', 'community-calendar-study-rooms'),
            'mediaLibraryUnavailable' => __('The WordPress Media Library is not available on this page.', 'community-calendar-study-rooms'),
        ));
    }
}
add_action('admin_enqueue_scripts', 'calsys_admin_enqueue');

/** Registers the calendar dashboard, management pages, and hidden event manager. */
function calsys_admin_menu() {
    add_menu_page(
        __('CommonsCal', 'community-calendar-study-rooms'),
        __('CommonsCal', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-home',
        'calsys_admin_home_page',
        'dashicons-calendar-alt'
    );
    add_submenu_page(
        'calsys-home',
        __('Home', 'community-calendar-study-rooms'),
        __('Home', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-home',
        'calsys_admin_home_page'
    );
    add_submenu_page(
        null,
        __('Events', 'community-calendar-study-rooms'),
        __('Events', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-events',
        'calsys_admin_events_page'
    );
    add_submenu_page(
        'calsys-home',
        __('Rooms & Events', 'community-calendar-study-rooms'),
        __('Rooms & Events', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-rooms',
        'calsys_admin_rooms_page'
    );
    add_submenu_page(
        'calsys-home',
        __('Room Approvals', 'community-calendar-study-rooms'),
        __('Room Approvals', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-room-approvals',
        'calsys_admin_room_approvals_page'
    );
    add_submenu_page(
        'calsys-home',
        __('Event Registrations', 'community-calendar-study-rooms'),
        __('Event Registrations', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-event-registrations',
        'calsys_admin_event_registrations_page'
    );
    add_submenu_page(
        'calsys-home',
        __('Reports', 'community-calendar-study-rooms'),
        __('Reports', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-reports',
        'calsys_admin_reports_page'
    );
    add_submenu_page(
        'calsys-home',
        __('Staff calendar', 'community-calendar-study-rooms'),
        __('Staff calendar', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-staff-calendar',
        'calsys_admin_staff_calendar_page'
    );
    add_submenu_page(
        'calsys-home',
        __('Settings', 'community-calendar-study-rooms'),
        __('Settings', 'community-calendar-study-rooms'),
        'manage_calendar_system',
        'calsys-settings',
        'calsys_admin_settings_page'
    );
}
add_action('admin_menu', 'calsys_admin_menu');

/** Renders one holiday-hours exception row. */
function calsys_holiday_hours_row($index, $holiday) {
    ?>
    <tr class="calsys-holiday-row">
        <td><input type="date" name="holiday_hours[<?php echo esc_attr($index); ?>][date]" value="<?php echo esc_attr($holiday['date']); ?>" aria-label="<?php esc_attr_e('Holiday date', 'community-calendar-study-rooms'); ?>" required></td>
        <td><label><input type="checkbox" name="holiday_hours[<?php echo esc_attr($index); ?>][annual]" value="1" <?php checked($holiday['annual']); ?>> <?php esc_html_e('Every year', 'community-calendar-study-rooms'); ?></label></td>
        <td><select name="holiday_hours[<?php echo esc_attr($index); ?>][mode]" aria-label="<?php esc_attr_e('Holiday availability', 'community-calendar-study-rooms'); ?>"><option value="closed" <?php selected($holiday['mode'], 'closed'); ?>><?php esc_html_e('Closed', 'community-calendar-study-rooms'); ?></option><option value="open" <?php selected($holiday['mode'], 'open'); ?>><?php esc_html_e('Custom hours', 'community-calendar-study-rooms'); ?></option></select></td>
        <td><input type="time" name="holiday_hours[<?php echo esc_attr($index); ?>][open]" value="<?php echo esc_attr($holiday['open']); ?>" aria-label="<?php esc_attr_e('Holiday opens', 'community-calendar-study-rooms'); ?>"> &ndash; <input type="time" name="holiday_hours[<?php echo esc_attr($index); ?>][close]" value="<?php echo esc_attr($holiday['close']); ?>" aria-label="<?php esc_attr_e('Holiday closes', 'community-calendar-study-rooms'); ?>"></td>
        <td><button class="button calsys-remove-holiday" type="button"><?php esc_html_e('Remove', 'community-calendar-study-rooms'); ?></button></td>
    </tr>
    <?php
}

/** Renders the settings page for calendar colors, categories, and booking rules. */
function calsys_admin_settings_page() {
    calsys_require_staff();
    calsys_admin_notice();
    $settings = calsys_get_settings();
    ?>
    <div class="wrap calsys-admin-page calsys-admin-settings">
        <h1><?php esc_html_e('Calendar settings', 'community-calendar-study-rooms'); ?></h1>
        <p class="calsys-admin-intro"><?php esc_html_e('Manage calendars, rooms, registration forms, booking rules, and what visitors can see. Changes take effect when you save.', 'community-calendar-study-rooms'); ?></p>
        <nav class="calsys-settings-nav" aria-label="<?php esc_attr_e('Settings sections', 'community-calendar-study-rooms'); ?>">
            <a href="#calsys-settings-calendars"><?php esc_html_e('Calendars', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-bookings"><?php esc_html_e('Bookings', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-registrations"><?php esc_html_e('Registrations', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-display"><?php esc_html_e('Display', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-notifications"><?php esc_html_e('Notifications', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-privacy"><?php esc_html_e('Privacy', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-shortcodes"><?php esc_html_e('Shortcodes', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-rooms"><?php esc_html_e('Rooms', 'community-calendar-study-rooms'); ?></a>
            <a href="#calsys-settings-equipment"><?php esc_html_e('Equipment', 'community-calendar-study-rooms'); ?></a>
        </nav>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('calsys_save_settings'); ?>
            <input type="hidden" name="action" value="calsys_save_settings">

            <section class="calsys-settings-card" id="calsys-settings-calendars">
            <h2><?php esc_html_e('Calendar colors', 'community-calendar-study-rooms'); ?></h2>
            <p><?php esc_html_e('These colors mark calendar entries and make the three calendars easier to distinguish.', 'community-calendar-study-rooms'); ?></p>
            <table class="form-table" role="presentation">
                <tbody>
                <?php
                $calendar_labels = array(
                    'events' => __('Event calendar entries', 'community-calendar-study-rooms'),
                    'rooms' => __('Room booking entries', 'community-calendar-study-rooms'),
                    'staff' => __('Default staff entry color', 'community-calendar-study-rooms'),
                );
                foreach ($calendar_labels as $key => $label) :
                    ?>
                    <tr>
                        <th scope="row"><label for="calsys-color-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td><input id="calsys-color-<?php echo esc_attr($key); ?>" class="calsys-color-picker" name="calendar_colors[<?php echo esc_attr($key); ?>]" type="text" value="<?php echo esc_attr($settings['calendar_colors'][$key]); ?>" data-default-color="<?php echo esc_attr(calsys_default_settings()['calendar_colors'][$key]); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Staff booking types', 'community-calendar-study-rooms'); ?></h2>
            <p><?php esc_html_e('Add or rename categories such as meetings, sick time, comp time, and time off. Each category has its own calendar color.', 'community-calendar-study-rooms'); ?></p>
            <table class="widefat striped calsys-types-table">
                <thead><tr><th><?php esc_html_e('Type', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Calendar color', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Identifier', 'community-calendar-study-rooms'); ?></th><th></th></tr></thead>
                <tbody id="calsys-staff-types">
                <?php $index = 0; foreach ($settings['staff_types'] as $key => $type) : ?>
                    <tr class="calsys-type-row">
                        <td>
                            <input type="hidden" name="staff_types[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($key); ?>">
                            <input class="regular-text" name="staff_types[<?php echo esc_attr($index); ?>][label]" type="text" maxlength="60" required value="<?php echo esc_attr($type['label']); ?>" aria-label="<?php esc_attr_e('Booking type name', 'community-calendar-study-rooms'); ?>">
                        </td>
                        <td><input class="calsys-color-picker" name="staff_types[<?php echo esc_attr($index); ?>][color]" type="text" value="<?php echo esc_attr($type['color']); ?>" data-default-color="#59458d" aria-label="<?php esc_attr_e('Booking type color', 'community-calendar-study-rooms'); ?>"></td>
                        <td><code><?php echo esc_html($key); ?></code></td>
                        <td><button class="button calsys-remove-type" type="button"><?php esc_html_e('Remove', 'community-calendar-study-rooms'); ?></button></td>
                    </tr>
                    <?php $index++; endforeach; ?>
                </tbody>
            </table>
            <p><button class="button" id="calsys-add-type" type="button"><?php esc_html_e('Add booking type', 'community-calendar-study-rooms'); ?></button></p>
            <p class="description"><?php esc_html_e('A type already used by a staff calendar entry cannot be removed. Its existing entries keep their category.', 'community-calendar-study-rooms'); ?></p>
            </section>

            <section class="calsys-settings-card">
            <h2><?php esc_html_e('Event categories', 'community-calendar-study-rooms'); ?></h2>
            <p><?php esc_html_e('Choose the categories available for events. Categories already used by saved events cannot be removed.', 'community-calendar-study-rooms'); ?></p>
            <table class="widefat striped calsys-categories-table">
                <thead><tr><th><?php esc_html_e('Category name', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Identifier', 'community-calendar-study-rooms'); ?></th><th></th></tr></thead>
                <tbody id="calsys-event-categories">
                <?php $category_index = 0; foreach ($settings['event_categories'] as $key => $label) : ?>
                    <tr class="calsys-category-row">
                        <td>
                            <input type="hidden" name="event_categories[<?php echo esc_attr($category_index); ?>][key]" value="<?php echo esc_attr($key); ?>">
                            <input class="regular-text" name="event_categories[<?php echo esc_attr($category_index); ?>][label]" type="text" maxlength="60" required value="<?php echo esc_attr($label); ?>" aria-label="<?php esc_attr_e('Event category name', 'community-calendar-study-rooms'); ?>">
                        </td>
                        <td><code><?php echo esc_html($key); ?></code></td>
                        <td><button class="button calsys-remove-category" type="button"><?php esc_html_e('Remove', 'community-calendar-study-rooms'); ?></button></td>
                    </tr>
                <?php $category_index++; endforeach; ?>
                </tbody>
            </table>
            <p><button class="button" id="calsys-add-category" type="button"><?php esc_html_e('Add event category', 'community-calendar-study-rooms'); ?></button></p>
            </section>

            <section class="calsys-settings-card" id="calsys-settings-bookings">
            <h2><?php esc_html_e('Room booking rules', 'community-calendar-study-rooms'); ?></h2>
            <p><?php esc_html_e('These limits apply to public room requests. Staff may book outside opening hours, but will see a warning.', 'community-calendar-study-rooms'); ?></p>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><label for="calsys-max-advance"><?php esc_html_e('Booking window', 'community-calendar-study-rooms'); ?></label></th>
                    <td><input id="calsys-max-advance" name="booking_rules[max_advance_days]" type="number" min="0" max="730" value="<?php echo esc_attr($settings['booking_rules']['max_advance_days']); ?>"> <?php esc_html_e('days ahead (0 = no limit)', 'community-calendar-study-rooms'); ?></td></tr>
                <tr><th scope="row"><label for="calsys-min-notice"><?php esc_html_e('Minimum notice', 'community-calendar-study-rooms'); ?></label></th>
                    <td><input id="calsys-min-notice" name="booking_rules[min_notice_hours]" type="number" min="0" max="8760" value="<?php echo esc_attr($settings['booking_rules']['min_notice_hours']); ?>"> <?php esc_html_e('hours before a booking (0 = no limit)', 'community-calendar-study-rooms'); ?></td></tr>
                <tr><th scope="row"><label for="calsys-max-duration"><?php esc_html_e('Maximum booking duration', 'community-calendar-study-rooms'); ?></label></th>
                    <td><input id="calsys-max-duration" name="booking_rules[max_duration_minutes]" type="number" min="0" max="1440" step="1" value="<?php echo esc_attr($settings['booking_rules']['max_duration_minutes']); ?>"> <?php esc_html_e('minutes per public booking (0 = no limit)', 'community-calendar-study-rooms'); ?></td></tr>
                <tr><th scope="row"><label for="calsys-time-increment"><?php esc_html_e('Time increments', 'community-calendar-study-rooms'); ?></label></th>
                    <td><select id="calsys-time-increment" name="booking_rules[time_increment]">
                        <?php foreach (array(0 => __('Any time', 'community-calendar-study-rooms'), 5 => __('5 minutes', 'community-calendar-study-rooms'), 10 => __('10 minutes', 'community-calendar-study-rooms'), 15 => __('15 minutes', 'community-calendar-study-rooms'), 20 => __('20 minutes', 'community-calendar-study-rooms'), 30 => __('30 minutes', 'community-calendar-study-rooms'), 60 => __('1 hour', 'community-calendar-study-rooms')) as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['booking_rules']['time_increment'], $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select><p class="description"><?php esc_html_e('Both start and end times must align to this increment.', 'community-calendar-study-rooms'); ?></p></td></tr>
                <tr><th scope="row"><?php esc_html_e('Request approval', 'community-calendar-study-rooms'); ?></th>
                    <td><label><input name="room_approval_required" type="checkbox" value="1" <?php checked($settings['room_approval_required']); ?>> <?php esc_html_e('Require staff approval for public room requests', 'community-calendar-study-rooms'); ?></label>
                        <p class="description"><?php esc_html_e('When unchecked, valid public room requests are confirmed immediately.', 'community-calendar-study-rooms'); ?></p></td></tr>
            </tbody></table>
            <h3><?php esc_html_e('Weekly opening hours', 'community-calendar-study-rooms'); ?></h3>
            <p class="description"><?php esc_html_e('Choose Open and enter both times for specific hours. Choose Unrestricted or Closed for days without specific hours. Public room requests must fit within these hours.', 'community-calendar-study-rooms'); ?></p>
            <table class="widefat striped" id="calsys-weekly-hours"><thead><tr><th><?php esc_html_e('Day', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Availability', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Opening and closing times', 'community-calendar-study-rooms'); ?></th></tr></thead><tbody>
                <?php foreach (array(__('Sunday', 'community-calendar-study-rooms'), __('Monday', 'community-calendar-study-rooms'), __('Tuesday', 'community-calendar-study-rooms'), __('Wednesday', 'community-calendar-study-rooms'), __('Thursday', 'community-calendar-study-rooms'), __('Friday', 'community-calendar-study-rooms'), __('Saturday', 'community-calendar-study-rooms')) as $day => $day_label) : $hours = $settings['booking_rules']['weekly_hours'][$day]; ?>
                <tr><th scope="row"><?php echo esc_html($day_label); ?></th><td><select name="weekly_hours[<?php echo esc_attr($day); ?>][mode]" aria-label="<?php echo esc_attr(sprintf(__('%s availability', 'community-calendar-study-rooms'), $day_label)); ?>">
                    <option value="unrestricted" <?php selected($hours['mode'], 'unrestricted'); ?>><?php esc_html_e('Unrestricted', 'community-calendar-study-rooms'); ?></option>
                    <option value="open" <?php selected($hours['mode'], 'open'); ?>><?php esc_html_e('Open', 'community-calendar-study-rooms'); ?></option>
                    <option value="closed" <?php selected($hours['mode'], 'closed'); ?>><?php esc_html_e('Closed', 'community-calendar-study-rooms'); ?></option>
                </select></td><td><input type="time" name="weekly_hours[<?php echo esc_attr($day); ?>][open]" value="<?php echo esc_attr($hours['open']); ?>" aria-label="<?php echo esc_attr(sprintf(__('%s opens', 'community-calendar-study-rooms'), $day_label)); ?>"> &ndash; <input type="time" name="weekly_hours[<?php echo esc_attr($day); ?>][close]" value="<?php echo esc_attr($hours['close']); ?>" aria-label="<?php echo esc_attr(sprintf(__('%s closes', 'community-calendar-study-rooms'), $day_label)); ?>"></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <h3><?php esc_html_e('Holiday and special hours', 'community-calendar-study-rooms'); ?></h3>
            <p class="description"><?php esc_html_e('Special dates override weekly hours. A specific date takes precedence over a yearly exception. Overnight hours are not supported.', 'community-calendar-study-rooms'); ?></p>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Date', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Repeat', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Availability', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Opening and closing times', 'community-calendar-study-rooms'); ?></th><th></th></tr></thead>
                <tbody id="calsys-holiday-hours"><?php foreach ($settings['booking_rules']['holiday_hours'] as $index => $holiday) { calsys_holiday_hours_row($index, $holiday); } ?></tbody>
            </table>
            <p><button type="button" class="button" id="calsys-add-holiday"><?php esc_html_e('Add holiday or special date', 'community-calendar-study-rooms'); ?></button></p>
            <input type="hidden" name="room_schedule_present" value="1">
            </section>

            <section class="calsys-settings-card" id="calsys-settings-registrations">
            <h2><?php esc_html_e('Event registration', 'community-calendar-study-rooms'); ?></h2>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><?php esc_html_e('Registration', 'community-calendar-study-rooms'); ?></th>
                    <td><label><input name="event_registration[enabled]" type="checkbox" value="1" <?php checked($settings['event_registration']['enabled']); ?>> <?php esc_html_e('Allow public event registrations', 'community-calendar-study-rooms'); ?></label></td></tr>
                <tr><th scope="row"><?php esc_html_e('New events', 'community-calendar-study-rooms'); ?></th>
                    <td><label><input name="event_registration[enabled_by_default]" type="checkbox" value="1" <?php checked($settings['event_registration']['enabled_by_default']); ?>> <?php esc_html_e('Enable registration by default when creating an event', 'community-calendar-study-rooms'); ?></label></td></tr>
                <tr><th scope="row"><label for="calsys-max-event-capacity"><?php esc_html_e('Maximum attendees', 'community-calendar-study-rooms'); ?></label></th>
                    <td><input id="calsys-max-event-capacity" name="event_registration[max_capacity]" type="number" min="0" max="10000" value="<?php echo esc_attr($settings['event_registration']['max_capacity']); ?>"> <?php esc_html_e('per event (0 = no global limit)', 'community-calendar-study-rooms'); ?>
                        <p class="description"><?php esc_html_e('This works alongside an event-specific capacity. The lower limit is used.', 'community-calendar-study-rooms'); ?></p></td></tr>
                <tr><th scope="row"><label for="calsys-registration-cutoff"><?php esc_html_e('Registration cutoff', 'community-calendar-study-rooms'); ?></label></th>
                    <td><input id="calsys-registration-cutoff" name="event_registration[cutoff_minutes]" type="number" min="0" max="525600" value="<?php echo esc_attr($settings['event_registration']['cutoff_minutes']); ?>"> <?php esc_html_e('minutes before event starts (0 = until it starts)', 'community-calendar-study-rooms'); ?></td></tr>
                <tr><th scope="row"><label for="calsys-registration-intro"><?php esc_html_e('Form instructions', 'community-calendar-study-rooms'); ?></label></th>
                    <td><textarea id="calsys-registration-intro" name="event_registration[form_intro]" rows="3" maxlength="1000" class="large-text"><?php echo esc_textarea($settings['event_registration']['form_intro']); ?></textarea><p class="description"><?php esc_html_e('Optional text shown above registration fields for every event.', 'community-calendar-study-rooms'); ?></p></td></tr>
                <?php foreach (array(
                    'name_label' => array(__('Name field label', 'community-calendar-study-rooms'), 100),
                    'email_label' => array(__('Email field label', 'community-calendar-study-rooms'), 100),
                    'submit_label' => array(__('Submit button text', 'community-calendar-study-rooms'), 60),
                    'privacy_notice' => array(__('Privacy notice', 'community-calendar-study-rooms'), 500),
                    'confirmation_message' => array(__('Success message', 'community-calendar-study-rooms'), 500),
                    'reminder_label' => array(__('Reminder opt-in label', 'community-calendar-study-rooms'), 200),
                    'closed_message' => array(__('Registration-closed message', 'community-calendar-study-rooms'), 300),
                    'full_message' => array(__('Registration-full message', 'community-calendar-study-rooms'), 300),
                    'disabled_message' => array(__('Registration-unavailable message', 'community-calendar-study-rooms'), 300),
                ) as $registration_key => $registration_field) : ?>
                    <tr><th scope="row"><label for="calsys-<?php echo esc_attr($registration_key); ?>"><?php echo esc_html($registration_field[0]); ?></label></th>
                        <td><input id="calsys-<?php echo esc_attr($registration_key); ?>" class="large-text" name="event_registration[<?php echo esc_attr($registration_key); ?>]" type="text" maxlength="<?php echo esc_attr($registration_field[1]); ?>" value="<?php echo esc_attr($settings['event_registration'][$registration_key] ?? ''); ?>"></td></tr>
                <?php endforeach; ?>
                <tr><th scope="row"><?php esc_html_e('Optional form content', 'community-calendar-study-rooms'); ?></th>
                    <td><label><input name="event_registration[show_privacy_notice]" type="checkbox" value="1" <?php checked($settings['event_registration']['show_privacy_notice']); ?>> <?php esc_html_e('Show the privacy notice on registration forms', 'community-calendar-study-rooms'); ?></label></td></tr>
            </tbody></table>
            <h3><?php esc_html_e('Default custom fields for new events', 'community-calendar-study-rooms'); ?></h3>
            <p class="description"><?php esc_html_e('These fields are copied into new event forms. You can customize the fields for each event afterward. Existing events are not changed.', 'community-calendar-study-rooms'); ?></p>
            <?php echo calsys_registration_fields_editor(
                $settings['event_registration']['default_fields'],
                'event_registration[default_fields]',
                'event_registration[default_fields_present]'
            ); ?>
            </section>

            <section class="calsys-settings-card" id="calsys-settings-display">
            <h2><?php esc_html_e('Calendar display', 'community-calendar-study-rooms'); ?></h2>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><label for="calsys-week-start"><?php esc_html_e('Week starts on', 'community-calendar-study-rooms'); ?></label></th>
                    <td><select id="calsys-week-start" name="calendar_display[week_start_day]">
                        <option value="site" <?php selected($settings['calendar_display']['week_start_day'], 'site'); ?>><?php esc_html_e('WordPress site setting', 'community-calendar-study-rooms'); ?></option>
                        <?php foreach (array(__('Sunday', 'community-calendar-study-rooms'), __('Monday', 'community-calendar-study-rooms'), __('Tuesday', 'community-calendar-study-rooms'), __('Wednesday', 'community-calendar-study-rooms'), __('Thursday', 'community-calendar-study-rooms'), __('Friday', 'community-calendar-study-rooms'), __('Saturday', 'community-calendar-study-rooms')) as $day => $label) : ?>
                            <option value="<?php echo esc_attr($day); ?>" <?php selected($settings['calendar_display']['week_start_day'], $day); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select></td></tr>
                <tr><th scope="row"><label for="calsys-initial-month"><?php esc_html_e('Initial calendar month', 'community-calendar-study-rooms'); ?></label></th>
                    <td><select id="calsys-initial-month" name="calendar_display[initial_month]">
                        <option value="current" <?php selected($settings['calendar_display']['initial_month'], 'current'); ?>><?php esc_html_e('Current month', 'community-calendar-study-rooms'); ?></option>
                        <option value="next" <?php selected($settings['calendar_display']['initial_month'], 'next'); ?>><?php esc_html_e('Next month', 'community-calendar-study-rooms'); ?></option>
                    </select></td></tr>
                <tr><th scope="row"><?php esc_html_e('Public event details', 'community-calendar-study-rooms'); ?></th>
                    <td><label><input name="calendar_display[show_category_labels]" type="checkbox" value="1" <?php checked($settings['calendar_display']['show_category_labels']); ?>> <?php esc_html_e('Show event category labels', 'community-calendar-study-rooms'); ?></label></td></tr>
            </tbody></table>
            </section>

            <section class="calsys-settings-card" id="calsys-settings-notifications">
            <h2><?php esc_html_e('Email notifications', 'community-calendar-study-rooms'); ?></h2>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><label for="calsys-staff-email"><?php esc_html_e('Staff notification address', 'community-calendar-study-rooms'); ?></label></th>
                    <td><input id="calsys-staff-email" class="regular-text" name="notifications[staff_email]" type="email" required value="<?php echo esc_attr($settings['notifications']['staff_email']); ?>"></td></tr>
                <tr><th scope="row"><?php esc_html_e('Send email when', 'community-calendar-study-rooms'); ?></th>
                    <td><label><input name="notifications[notify_staff_room_requests]" type="checkbox" value="1" <?php checked($settings['notifications']['notify_staff_room_requests']); ?>> <?php esc_html_e('A public room request is received', 'community-calendar-study-rooms'); ?></label><br>
                        <label><input name="notifications[email_room_requester]" type="checkbox" value="1" <?php checked($settings['notifications']['email_room_requester']); ?>> <?php esc_html_e('A room requester receives a submission confirmation', 'community-calendar-study-rooms'); ?></label><br>
                        <label><input name="notifications[email_event_registrant]" type="checkbox" value="1" <?php checked($settings['notifications']['email_event_registrant']); ?>> <?php esc_html_e('An event registrant receives a confirmation', 'community-calendar-study-rooms'); ?></label><br>
                        <label><input name="notifications[notify_staff_event_registrations]" type="checkbox" value="1" <?php checked($settings['notifications']['notify_staff_event_registrations']); ?>> <?php esc_html_e('Staff receives an email for each new event registration', 'community-calendar-study-rooms'); ?></label><br>
                        <label><input name="notifications[email_event_reminders]" type="checkbox" value="1" <?php checked($settings['notifications']['email_event_reminders']); ?>> <?php esc_html_e('Enable attendee opt-in reminder emails 24 hours before events', 'community-calendar-study-rooms'); ?></label>
                        <p class="description"><?php esc_html_e('Reminder emails are sent only to registrants who check the opt-in box. Delivery uses WordPress scheduled tasks and your website host mail configuration; failed sends are recorded in the PHP error log.', 'community-calendar-study-rooms'); ?></p></td></tr>
            </tbody></table>
            </section>

            <section class="calsys-settings-card" id="calsys-settings-privacy">
            <h2><?php esc_html_e('Public booking privacy', 'community-calendar-study-rooms'); ?></h2>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><?php esc_html_e('Room calendar details', 'community-calendar-study-rooms'); ?></th>
                    <td><label><input name="privacy[hide_guest_room_bookings]" type="checkbox" value="1" <?php checked($settings['privacy']['hide_guest_room_bookings']); ?>> <?php esc_html_e('Hide room bookings from logged-out visitors', 'community-calendar-study-rooms'); ?></label><br>
                        <label><input name="privacy[show_room_names]" type="checkbox" value="1" <?php checked($settings['privacy']['show_room_names']); ?>> <?php esc_html_e('Show room names to visitors', 'community-calendar-study-rooms'); ?></label><br>
                        <label><input name="privacy[show_room_status]" type="checkbox" value="1" <?php checked($settings['privacy']['show_room_status']); ?>> <?php esc_html_e('Show pending/approved status to visitors', 'community-calendar-study-rooms'); ?></label>
                        <p class="description"><?php esc_html_e('When bookings are hidden, logged-out visitors still see published events and can request rooms. Signed-in users continue to see public bookings. Requester names, emails, and event registrant contact details remain private to staff.', 'community-calendar-study-rooms'); ?></p></td></tr>
                <tr><th scope="row"><label for="calsys-retention-days"><?php esc_html_e('Personal data retention', 'community-calendar-study-rooms'); ?></label></th>
                    <td><input id="calsys-retention-days" name="privacy[retention_days]" type="number" min="0" max="3650" step="1" value="<?php echo esc_attr($settings['privacy']['retention_days']); ?>"> <?php esc_html_e('days after a booking or event ends (0 = disabled)', 'community-calendar-study-rooms'); ?>
                    <p class="description"><?php esc_html_e('When enabled, past booking and registration contact details and answers are permanently anonymized; past waitlist entries are removed. Totals and room usage are retained. Cleanup runs through WordPress scheduled tasks.', 'community-calendar-study-rooms'); ?></p></td></tr>
            </tbody></table>
            </section>

            <section class="calsys-settings-card" id="calsys-settings-shortcodes">
            <h2><?php esc_html_e('Calendar shortcodes', 'community-calendar-study-rooms'); ?></h2>
            <p><?php esc_html_e('Public event calendar subscription URL:', 'community-calendar-study-rooms'); ?> <a href="<?php echo esc_url(add_query_arg('calsys_ical', 'events', home_url('/'))); ?>"><?php echo esc_html(add_query_arg('calsys_ical', 'events', home_url('/'))); ?></a></p>
            <p class="description"><?php esc_html_e('Subscribe in a calendar app to receive published events. Room bookings, registrations, and staff entries are not included.', 'community-calendar-study-rooms'); ?></p>
            <p><?php esc_html_e('Copy the shortcode for the calendar or event cards you want to place on a page. The room bookings calendar also includes upcoming published events. Visitors see public entries only; private staff bookings remain private.', 'community-calendar-study-rooms'); ?></p>
            <div class="calsys-shortcode-options">
                <div><strong><?php esc_html_e('Events only', 'community-calendar-study-rooms'); ?></strong><code>[calendar_events]</code></div>
                <div><strong><?php esc_html_e('Room bookings and events', 'community-calendar-study-rooms'); ?></strong><code>[calendar_rooms]</code></div>
                <div><strong><?php esc_html_e('Events and room bookings', 'community-calendar-study-rooms'); ?></strong><code>[calendar_combined]</code></div>
                <div><strong><?php esc_html_e('Upcoming event cards', 'community-calendar-study-rooms'); ?></strong><code>[calendar_upcoming_events]</code><span class="description"><?php esc_html_e('Use count="4" or count="5" for more cards.', 'community-calendar-study-rooms'); ?></span></div>
            </div>
            </section>

            <?php submit_button(__('Save calendar settings', 'community-calendar-study-rooms')); ?>
        </form>
        <?php calsys_admin_rooms_settings_panel(); ?>
        <?php calsys_admin_equipment_settings(); ?>
        <script type="text/html" id="tmpl-calsys-staff-type">
            <tr class="calsys-type-row">
                <td>
                    <input type="hidden" name="staff_types[<%= data.index %>][key]" value="">
                    <input class="regular-text" name="staff_types[<%= data.index %>][label]" type="text" maxlength="60" required value="" aria-label="<?php esc_attr_e('Booking type name', 'community-calendar-study-rooms'); ?>">
                </td>
                <td><input class="calsys-color-picker" name="staff_types[<%= data.index %>][color]" type="text" value="#59458d" data-default-color="#59458d" aria-label="<?php esc_attr_e('Booking type color', 'community-calendar-study-rooms'); ?>"></td>
                <td><?php esc_html_e('Created when saved', 'community-calendar-study-rooms'); ?></td>
                <td><button class="button calsys-remove-type" type="button"><?php esc_html_e('Remove', 'community-calendar-study-rooms'); ?></button></td>
            </tr>
        </script>
        <script type="text/html" id="tmpl-calsys-event-category">
            <tr class="calsys-category-row">
                <td>
                    <input type="hidden" name="event_categories[<%= data.index %>][key]" value="">
                    <input class="regular-text" name="event_categories[<%= data.index %>][label]" type="text" maxlength="60" required value="" aria-label="<?php esc_attr_e('Event category name', 'community-calendar-study-rooms'); ?>">
                </td>
                <td><?php esc_html_e('Created when saved', 'community-calendar-study-rooms'); ?></td>
                <td><button class="button calsys-remove-category" type="button"><?php esc_html_e('Remove', 'community-calendar-study-rooms'); ?></button></td>
            </tr>
        </script>
        <template id="calsys-holiday-template"><?php calsys_holiday_hours_row('INDEX', array('date' => '', 'annual' => 0, 'mode' => 'closed', 'open' => '', 'close' => '')); ?></template>
    </div>
    <?php
}

/** Validates and saves calendar settings submitted by an authorized staff user. */
function calsys_save_settings() {
    calsys_require_staff();
    check_admin_referer('calsys_save_settings');

    $defaults = calsys_default_settings();
    $posted_colors = isset($_POST['calendar_colors']) && is_array($_POST['calendar_colors'])
        ? wp_unslash($_POST['calendar_colors'])
        : array();
    $colors = array();
    foreach (array_keys($defaults['calendar_colors']) as $key) {
        $posted_color = $posted_colors[$key] ?? '';
        $color = is_string($posted_color) ? sanitize_hex_color($posted_color) : false;
        if (!$color) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $colors[$key] = $color;
    }

    $posted_types = isset($_POST['staff_types']) && is_array($_POST['staff_types'])
        ? wp_unslash($_POST['staff_types'])
        : array();
    if (count($posted_types) > 20) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $types = array();
    foreach ($posted_types as $row) {
        if (!is_array($row)) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $raw_label = $row['label'] ?? '';
        $raw_color = $row['color'] ?? '';
        $raw_key = $row['key'] ?? '';
        if (!is_string($raw_label) || !is_string($raw_color) || !is_string($raw_key)) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $label = sanitize_text_field($raw_label);
        $color = sanitize_hex_color($raw_color);
        $key = sanitize_key($raw_key);
        if ($key === '') {
            $key = substr(sanitize_key(str_replace('-', '_', sanitize_title($label))), 0, 20);
        }
        if ($label === '' || strlen($label) > 60 || !$color || $key === '') {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        if (strlen($key) > 20) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $base_key = $key;
        $suffix = 2;
        while (isset($types[$key])) {
            $suffix_text = '_' . $suffix;
            $key = substr($base_key, 0, 20 - strlen($suffix_text)) . $suffix_text;
            $suffix++;
        }
        $types[$key] = array('label' => $label, 'color' => $color);
    }

    if (!$types) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }

    $posted_categories = isset($_POST['event_categories']) && is_array($_POST['event_categories'])
        ? wp_unslash($_POST['event_categories'])
        : array();
    if (count($posted_categories) > 40) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $categories = array();
    foreach ($posted_categories as $row) {
        if (!is_array($row) || !is_string($row['label'] ?? null) || !is_string($row['key'] ?? null)) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $label = sanitize_text_field($row['label']);
        $key = sanitize_key($row['key']);
        if ($key === '') {
            $key = substr(sanitize_key(str_replace('-', '_', sanitize_title($label))), 0, 40);
        }
        if ($label === '' || strlen($label) > 60 || $key === '') {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $base_key = $key;
        $suffix = 2;
        while (isset($categories[$key])) {
            $suffix_text = '_' . $suffix;
            $key = substr($base_key, 0, 40 - strlen($suffix_text)) . $suffix_text;
            $suffix++;
        }
        $categories[$key] = $label;
    }
    if (!$categories) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }

    $posted_rules = isset($_POST['booking_rules']) && is_array($_POST['booking_rules'])
        ? wp_unslash($_POST['booking_rules'])
        : array();
    foreach (array('max_advance_days', 'min_notice_hours', 'max_duration_minutes', 'time_increment', 'opening_time', 'closing_time') as $rule_key) {
        if (isset($posted_rules[$rule_key]) && !is_scalar($posted_rules[$rule_key])) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
    }
    $max_advance_days = absint($posted_rules['max_advance_days'] ?? 0);
    $min_notice_hours = absint($posted_rules['min_notice_hours'] ?? 0);
    $time_increment = absint($posted_rules['time_increment'] ?? 0);
    $old_settings = calsys_get_settings();
    $raw_max_duration = $posted_rules['max_duration_minutes'] ?? $old_settings['booking_rules']['max_duration_minutes'];
    if (!is_scalar($raw_max_duration) || !preg_match('/^\d+$/', (string) $raw_max_duration) || (int) $raw_max_duration > 1440) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $max_duration_minutes = (int) $raw_max_duration;
    $opening_time = sanitize_text_field($posted_rules['opening_time'] ?? $old_settings['booking_rules']['opening_time']);
    $closing_time = sanitize_text_field($posted_rules['closing_time'] ?? $old_settings['booking_rules']['closing_time']);
    $valid_increments = array(0, 5, 10, 15, 20, 30, 60);
    if ($max_advance_days > 730 || $min_notice_hours > 8760 ||
        !in_array($time_increment, $valid_increments, true) ||
        (($opening_time === '') !== ($closing_time === '')) ||
        ($opening_time !== '' && (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $opening_time) ||
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $closing_time) || $opening_time >= $closing_time))) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    if (isset($_POST['room_schedule_present'])) {
        $schedule = calsys_normalize_room_schedule(
            wp_unslash($_POST['weekly_hours'] ?? null),
            wp_unslash($_POST['holiday_hours'] ?? array()),
            true
        );
        if ($_POST['room_schedule_present'] !== '1' || $schedule === false) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid_hours');
        }
    } else {
        $schedule = array('weekly' => $old_settings['booking_rules']['weekly_hours'], 'holidays' => $old_settings['booking_rules']['holiday_hours']);
    }

    $posted_registration = isset($_POST['event_registration']) && is_array($_POST['event_registration'])
        ? wp_unslash($_POST['event_registration'])
        : array();
    foreach (array(
        'max_capacity', 'cutoff_minutes', 'enabled', 'enabled_by_default', 'form_intro',
        'name_label', 'email_label', 'submit_label', 'privacy_notice', 'confirmation_message',
        'reminder_label', 'closed_message', 'full_message', 'disabled_message',
        'show_privacy_notice', 'default_fields_present'
    ) as $registration_key) {
        if (isset($posted_registration[$registration_key]) && !is_scalar($posted_registration[$registration_key])) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
    }
    if (!isset($posted_registration['default_fields_present'])) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $registration_text_limits = array(
        'form_intro' => 1000,
        'name_label' => 100,
        'email_label' => 100,
        'submit_label' => 60,
        'privacy_notice' => 500,
        'confirmation_message' => 500,
        'reminder_label' => 200,
        'closed_message' => 300,
        'full_message' => 300,
        'disabled_message' => 300,
    );
    $registration_form_text = array();
    foreach ($registration_text_limits as $key => $limit) {
        $raw_value = $posted_registration[$key] ?? '';
        if (!is_string($raw_value) || strlen($raw_value) > $limit) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $sanitized_value = $key === 'form_intro'
            ? sanitize_textarea_field($raw_value)
            : sanitize_text_field($raw_value);
        if ($key !== 'form_intro' && $sanitized_value === '') {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $registration_form_text[$key] = $sanitized_value;
    }
    $posted_default_fields = $posted_registration['default_fields'] ?? array();
    if (!is_array($posted_default_fields)) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $default_registration_fields = calsys_sanitize_registration_fields($posted_default_fields);
    if ($default_registration_fields === false) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $max_capacity = absint($posted_registration['max_capacity'] ?? 0);
    $cutoff_minutes = absint($posted_registration['cutoff_minutes'] ?? 0);
    if ($max_capacity > 10000 || $cutoff_minutes > 525600) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }

    $posted_display = isset($_POST['calendar_display']) && is_array($_POST['calendar_display'])
        ? wp_unslash($_POST['calendar_display'])
        : array();
    foreach (array('week_start_day', 'initial_month', 'show_category_labels') as $display_key) {
        if (isset($posted_display[$display_key]) && !is_scalar($posted_display[$display_key])) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
    }
    $week_start_day = $posted_display['week_start_day'] ?? 'site';
    if (!is_string($week_start_day) ||
        ($week_start_day !== 'site' && (!ctype_digit($week_start_day) || (int) $week_start_day > 6))) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $initial_month = sanitize_key($posted_display['initial_month'] ?? 'current');
    if (!in_array($initial_month, array('current', 'next'), true)) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }

    $posted_notifications = isset($_POST['notifications']) && is_array($_POST['notifications'])
        ? wp_unslash($_POST['notifications'])
        : array();
    foreach (array('staff_email', 'notify_staff_room_requests', 'email_room_requester', 'email_event_registrant', 'notify_staff_event_registrations', 'email_event_reminders') as $notification_key) {
        if (isset($posted_notifications[$notification_key]) && !is_scalar($posted_notifications[$notification_key])) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
    }
    $staff_email = sanitize_email($posted_notifications['staff_email'] ?? '');
    if (!is_email($staff_email)) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    $posted_privacy = isset($_POST['privacy']) && is_array($_POST['privacy'])
        ? wp_unslash($_POST['privacy'])
        : array();
    foreach (array('hide_guest_room_bookings', 'show_room_names', 'show_room_status', 'retention_days') as $privacy_key) {
        if (isset($posted_privacy[$privacy_key]) && !is_scalar($posted_privacy[$privacy_key])) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        $raw_retention_days = $posted_privacy['retention_days'] ?? $old_settings['privacy']['retention_days'];
        if (!is_scalar($raw_retention_days) || !preg_match('/^\d+$/', (string) $raw_retention_days) || (int) $raw_retention_days > 3650) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
    }

    $settings = array(
        'calendar_colors' => $colors,
        'staff_types' => $types,
        'event_categories' => $categories,
        'booking_rules' => array(
            'max_advance_days' => $max_advance_days,
            'min_notice_hours' => $min_notice_hours,
            'max_duration_minutes' => $max_duration_minutes,
            'time_increment' => $time_increment,
            'opening_time' => $opening_time,
            'closing_time' => $closing_time,
            'weekly_hours' => $schedule['weekly'],
            'holiday_hours' => $schedule['holidays'],
        ),
        'room_approval_required' => isset($_POST['room_approval_required']) ? 1 : 0,
        'event_registration' => array(
            'enabled' => isset($posted_registration['enabled']) ? 1 : 0,
            'max_capacity' => $max_capacity,
            'cutoff_minutes' => $cutoff_minutes,
            'enabled_by_default' => isset($posted_registration['enabled_by_default']) ? 1 : 0,
            'form_intro' => $registration_form_text['form_intro'],
            'name_label' => $registration_form_text['name_label'],
            'email_label' => $registration_form_text['email_label'],
            'submit_label' => $registration_form_text['submit_label'],
            'privacy_notice' => $registration_form_text['privacy_notice'],
            'confirmation_message' => $registration_form_text['confirmation_message'],
            'reminder_label' => $registration_form_text['reminder_label'],
            'closed_message' => $registration_form_text['closed_message'],
            'full_message' => $registration_form_text['full_message'],
            'disabled_message' => $registration_form_text['disabled_message'],
            'show_privacy_notice' => isset($posted_registration['show_privacy_notice']) ? 1 : 0,
            'default_fields' => $default_registration_fields,
        ),
        'calendar_display' => array(
            'week_start_day' => $week_start_day === 'site' ? 'site' : (int) $week_start_day,
            'initial_month' => $initial_month,
            'show_registration_counts' => $old_settings['calendar_display']['show_registration_counts'],
            'show_category_labels' => isset($posted_display['show_category_labels']) ? 1 : 0,
        ),
        'notifications' => array(
            'staff_email' => $staff_email,
            'notify_staff_room_requests' => isset($posted_notifications['notify_staff_room_requests']) ? 1 : 0,
            'email_room_requester' => isset($posted_notifications['email_room_requester']) ? 1 : 0,
            'email_event_registrant' => isset($posted_notifications['email_event_registrant']) ? 1 : 0,
            'notify_staff_event_registrations' => isset($posted_notifications['notify_staff_event_registrations']) ? 1 : 0,
            'email_event_reminders' => isset($posted_notifications['email_event_reminders']) ? 1 : 0,
        ),
        'privacy' => array(
            'hide_guest_room_bookings' => isset($posted_privacy['hide_guest_room_bookings']) ? 1 : 0,
            'show_room_names' => isset($posted_privacy['show_room_names']) ? 1 : 0,
            'show_room_status' => isset($posted_privacy['show_room_status']) ? 1 : 0,
            'retention_days' => (int) $raw_retention_days,
        ),
    );
    global $wpdb;
    $kept_in_use_type = false;
    foreach ($old_settings['staff_types'] as $key => $type) {
        if (isset($types[$key])) {
            continue;
        }
        $usage_result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . calsys_table('staff_calendar') . " WHERE entry_type = %s",
            $key
        ));
        if ($usage_result === null || $usage_result === false) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        if ((int) $usage_result > 0) {
            $types[$key] = $type;
            $kept_in_use_type = true;
        }
    }
    $settings['staff_types'] = $types;
    foreach ($old_settings['event_categories'] as $key => $label) {
        if (isset($categories[$key])) {
            continue;
        }
        $usage_result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . calsys_table('events') . " WHERE category = %s",
            $key
        ));
        if ($usage_result === null || $usage_result === false) {
            calsys_admin_redirect('calsys-settings', 'settings_invalid');
        }
        if ((int) $usage_result > 0) {
            $categories[$key] = $label;
        }
    }
    $settings['event_categories'] = $categories;
    update_option('calsys_settings', $settings);
    if (get_option('calsys_settings') !== $settings) {
        calsys_admin_redirect('calsys-settings', 'settings_invalid');
    }
    calsys_admin_redirect('calsys-settings', $kept_in_use_type ? 'settings_saved_types_in_use' : 'settings_saved');
}
add_action('admin_post_calsys_save_settings', 'calsys_save_settings');

/** Displays a translated admin notice for a recognized action result. */
function calsys_admin_notice() {
    if (empty($_GET['calsys_notice'])) {
        return;
    }

    $notices = array(
        'saved' => __('Changes saved.', 'community-calendar-study-rooms'),
        'approved' => __('Room request approved.', 'community-calendar-study-rooms'),
        'denied' => __('Room request denied.', 'community-calendar-study-rooms'),
        'room_status_email_failed' => __('The room request was updated, but the requester email failed. Check the site error log.', 'community-calendar-study-rooms'),
        'booking_saved' => __('Staff booking confirmed.', 'community-calendar-study-rooms'),
        'conflict' => __('That room and time conflict with another pending or approved booking.', 'community-calendar-study-rooms'),
        'room_conflict' => __('That room and time are already requested or booked. Please choose another time.', 'community-calendar-study-rooms'),
        'outside_hours' => __('The requested time is outside opening hours or on a closed day.', 'community-calendar-study-rooms'),
        'duration_limit' => __('That request exceeds the maximum room booking duration. Please choose a shorter time.', 'community-calendar-study-rooms'),
        'attendee_cancelled' => __('The attendee was cancelled. Any available places were filled from the waitlist.', 'community-calendar-study-rooms'),
        'attendee_cancelled_email_failed' => __('The attendee was cancelled, but one or more cancellation or waitlist emails failed. Check the site error log.', 'community-calendar-study-rooms'),
        'saved_waitlist_error' => __('The event was saved, but its waitlist could not be updated. Check the site error log.', 'community-calendar-study-rooms'),
        'saved_waitlist_email_failed' => __('The event was saved and waitlist places were assigned, but one or more notification emails failed. Check the site error log.', 'community-calendar-study-rooms'),
        'room_booking_saved_outside_hours' => __('Staff booking confirmed, but it is outside opening hours.', 'community-calendar-study-rooms'),
        'invalid' => __('Please check the submitted information and try again.', 'community-calendar-study-rooms'),
        'invalid_room_request' => __('Please check the room request details and try again.', 'community-calendar-study-rooms'),
        'staff_event_saved' => __('Staff calendar entry saved.', 'community-calendar-study-rooms'),
        'staff_event_saved_room_conflicts' => __('Staff time saved, but one or more room reservation periods overlapped existing bookings.', 'community-calendar-study-rooms'),
        'saved_room_conflicts' => '',
        'staff_event_deleted' => __('Staff calendar entry deleted.', 'community-calendar-study-rooms'),
        'event_deleted' => __('Event deleted.', 'community-calendar-study-rooms'),
        'event_deleted_email_failed' => __('The event was deleted, but one or more cancellation emails failed. Check the site error log.', 'community-calendar-study-rooms'),
        'room_deleted' => __('Room deleted.', 'community-calendar-study-rooms'),
        'room_in_use' => __('This room cannot be deleted because it is used by existing bookings or staff calendar entries. Mark it unavailable instead.', 'community-calendar-study-rooms'),
        'equipment_in_use' => __('The quantity cannot be lower than the amount already reserved at the same time.', 'community-calendar-study-rooms'),
        'equipment_conflict' => __('The requested equipment is unavailable for that time or quantity.', 'community-calendar-study-rooms'),
        'settings_saved' => __('Calendar settings saved.', 'community-calendar-study-rooms'),
        'settings_saved_types_in_use' => __('Settings saved. Booking types used by existing entries were kept.', 'community-calendar-study-rooms'),
        'settings_invalid_hours' => __('Settings were not saved. Choose Open or Custom hours and enter valid opening and closing times, or clear the times for closed and unrestricted days. Check special dates for duplicates.', 'community-calendar-study-rooms'),
        'settings_invalid' => __('Settings were not saved. Check the booking limits and hours, registration capacity and cutoff, email address, colors, booking types, and event categories.', 'community-calendar-study-rooms'),
    );
    $key = sanitize_key(wp_unslash($_GET['calsys_notice']));
    if (isset($notices[$key])) {
        $message = $notices[$key];
        if ($key === 'saved_room_conflicts') {
            $skipped = absint($_GET['calsys_skipped'] ?? 0);
            $message = sprintf(_n('Event saved. Room reservation was skipped for %d conflicting occurrence.', 'Event saved. Room reservations were skipped for %d conflicting occurrences.', $skipped, 'community-calendar-study-rooms'), $skipped);
        } elseif ($key === 'staff_event_saved_room_conflicts') {
            $skipped = absint($_GET['calsys_skipped'] ?? 0);
            $message = sprintf(_n('Staff entry saved. Room reservation was skipped for %d conflicting day.', 'Staff entry saved. Room reservations were skipped for %d conflicting days.', $skipped, 'community-calendar-study-rooms'), $skipped);
        }
        printf('<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html($message));
    }
}