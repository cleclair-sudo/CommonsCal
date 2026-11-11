<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Returns the fully prefixed database table name for a plugin table. */
function calsys_table($name) {
    global $wpdb;
    return $wpdb->prefix . 'calsys_' . $name;
}

/** Grants calendar-management access to the default administrator and editor roles. */
function calsys_grant_role_capabilities() {
    foreach (array('administrator', 'editor') as $role_name) {
        $role = get_role($role_name);
        if ($role && !$role->has_cap('manage_calendar_system')) {
            $role->add_cap('manage_calendar_system');
        }
    }
}

/** Creates or upgrades plugin tables and initializes capabilities and scheduled jobs. */
function calsys_activate() {
    global $wpdb;

    $previous_db_version = absint(get_option('calsys_db_version', 0));
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $collate = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE " . calsys_table('events') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(200) NOT NULL,
        description text NOT NULL,
        image_id bigint(20) unsigned NOT NULL DEFAULT 0,
        starts_at datetime NOT NULL,
        ends_at datetime NOT NULL,
        capacity int(10) unsigned NOT NULL DEFAULT 0,
        show_registration_counts tinyint(1) NULL DEFAULT NULL,
        category varchar(40) NOT NULL DEFAULT 'adult',
        registration_fields longtext NOT NULL,
        registration_enabled tinyint(1) NOT NULL DEFAULT 0,
        registration_recipients longtext NOT NULL,
        recurrence_group varchar(36) NOT NULL DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'draft',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY status_starts_at (status, starts_at)
    ) $collate;");

    dbDelta("CREATE TABLE " . calsys_table('registrations') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        name varchar(200) NOT NULL,
        email varchar(200) NOT NULL,
        registration_fields longtext NOT NULL,
        reminder_opt_in tinyint(1) NOT NULL DEFAULT 0,
        reminder_sent_at datetime NULL,
        registered_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY event_id_email (event_id, email),
        KEY reminder_due (reminder_opt_in, reminder_sent_at)
    ) $collate;");

    dbDelta("CREATE TABLE " . calsys_table('rooms') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(200) NOT NULL,
        capacity int(10) unsigned NOT NULL DEFAULT 1,
        active tinyint(1) NOT NULL DEFAULT 1,
        color varchar(7) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        KEY active (active)
    ) $collate;");

    dbDelta("CREATE TABLE " . calsys_table('waitlist') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        name varchar(200) NOT NULL,
        email varchar(200) NOT NULL,
        registration_fields longtext NOT NULL,
        reminder_opt_in tinyint(1) NOT NULL DEFAULT 0,
        joined_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY event_id (event_id)
    ) $collate;");

    dbDelta("CREATE TABLE " . calsys_table('bookings') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        room_id bigint(20) unsigned NOT NULL,
        requester_name varchar(200) NOT NULL,
        requester_email varchar(200) NOT NULL,
        request_reason text NOT NULL,
        booking_date date NOT NULL,
        starts_at time NOT NULL,
        ends_at time NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        source_type varchar(20) NOT NULL DEFAULT '',
        source_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY room_schedule (room_id, booking_date, starts_at, ends_at, status),
        KEY source_link (source_type, source_id)
    ) $collate;");

    dbDelta("CREATE TABLE " . calsys_table('staff_calendar') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(200) NOT NULL,
        entry_type varchar(20) NOT NULL DEFAULT 'other',
        starts_at datetime NOT NULL,
        ends_at datetime NOT NULL,
        all_day tinyint(1) NOT NULL DEFAULT 0,
        room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY starts_at_ends_at (starts_at, ends_at)
    ) $collate;");

    dbDelta("CREATE TABLE " . calsys_table('equipment') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(200) NOT NULL,
        quantity int(10) unsigned NOT NULL DEFAULT 1,
        active tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY  (id)
    ) $collate;");

    dbDelta("CREATE TABLE " . calsys_table('equipment_reservations') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        equipment_id bigint(20) unsigned NOT NULL,
        source_type varchar(20) NOT NULL,
        source_id bigint(20) unsigned NOT NULL,
        starts_at datetime NOT NULL,
        ends_at datetime NOT NULL,
        quantity int(10) unsigned NOT NULL,
        status varchar(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY source_equipment (source_type, source_id, equipment_id),
        KEY availability (equipment_id, status, starts_at, ends_at)
    ) $collate;");

    foreach (array('equipment', 'equipment_reservations', 'waitlist') as $table_name) {
        $table = calsys_table($table_name);
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($found !== $table || $wpdb->last_error) {
            error_log('CommonsCal: unable to create plugin tables: ' . $wpdb->last_error);
            return;
        }
    }

    if ($previous_db_version < 5) {
        $enabled_existing_events = $wpdb->query("UPDATE " . calsys_table('events') . " SET registration_enabled = 1");
        if ($enabled_existing_events === false) {
            error_log('CommonsCal: unable to preserve event registration settings during database upgrade: ' . $wpdb->last_error);
            return;
        }
    }

    if ($previous_db_version < 9) {
        $show_counts = (int) calsys_get_settings()['calendar_display']['show_registration_counts'];
        $migrated = $wpdb->query($wpdb->prepare(
            "UPDATE " . calsys_table('events') . " SET show_registration_counts = %d WHERE show_registration_counts IS NULL",
            $show_counts
        ));
        if ($migrated === false) {
            error_log('CommonsCal: unable to preserve event count visibility during database upgrade: ' . $wpdb->last_error);
            return;
        }
    }

    if ($previous_db_version < 10 && !$wpdb->get_var("SHOW COLUMNS FROM " . calsys_table('rooms') . " LIKE 'color'")) {
        error_log('CommonsCal: unable to add room colors: ' . $wpdb->last_error);
        return;
    }

    calsys_grant_role_capabilities();
    calsys_schedule_reminder_cron();

    update_option('calsys_db_version', CALSYS_DB_VERSION);
}
register_activation_hook(CALSYS_PLUGIN_FILE, 'calsys_activate');

/** Applies database upgrades and role capabilities when WordPress loads the plugin. */
function calsys_maybe_upgrade() {
    calsys_grant_role_capabilities();
    if (get_option('calsys_db_version') !== CALSYS_DB_VERSION) {
        calsys_activate();
    }
}
add_action('plugins_loaded', 'calsys_maybe_upgrade');

/** Clears scheduled reminders and removes plugin capabilities on deactivation. */
function calsys_deactivate() {
    wp_clear_scheduled_hook('calsys_send_due_event_reminders');
    wp_clear_scheduled_hook('calsys_privacy_cleanup');
    foreach (array('administrator', 'editor') as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('manage_calendar_system');
        }
    }
}
register_deactivation_hook(CALSYS_PLUGIN_FILE, 'calsys_deactivate');

/** Loads the plugin stylesheet on public pages. */
function calsys_enqueue_styles() {
    $style_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system.css';
    wp_enqueue_style(
        'calsys-public',
        plugins_url('assets/calendar-system.css', CALSYS_PLUGIN_FILE),
        array(),
        file_exists($style_path) ? (string) filemtime($style_path) : CALSYS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'calsys_enqueue_styles');

/** Loads public calendar controls and supplies translated labels to JavaScript. */
function calsys_enqueue_public_script() {
    if (current_user_can('manage_calendar_system')) {
        wp_enqueue_media();
    }
    $script_path = plugin_dir_path(CALSYS_PLUGIN_FILE) . 'assets/calendar-system-public.js';
    wp_enqueue_script(
        'calsys-public',
        plugins_url('assets/calendar-system-public.js', CALSYS_PLUGIN_FILE),
        array(),
        file_exists($script_path) ? (string) filemtime($script_path) : CALSYS_VERSION,
        true
    );
    wp_localize_script('calsys-public', 'calsysPublic', array(
        'addEvent' => __('Add an event', 'community-calendar-study-rooms'),
        'editEvent' => __('Edit event', 'community-calendar-study-rooms'),
        'addStaffTime' => __('Add staff time', 'community-calendar-study-rooms'),
        'editStaffTime' => __('Edit staff time', 'community-calendar-study-rooms'),
        'addRoomBooking' => __('Add a booking', 'community-calendar-study-rooms'),
        'requestRoom' => __('Request a study room', 'community-calendar-study-rooms'),
        'editRoomBooking' => __('Edit room booking', 'community-calendar-study-rooms'),
        'confirmRoomBooking' => __('Confirm booking', 'community-calendar-study-rooms'),
        'savePublicEvent' => __('Save public event', 'community-calendar-study-rooms'),
        'saveRoomBooking' => __('Save room booking', 'community-calendar-study-rooms'),
        'sendRequest' => __('Send request', 'community-calendar-study-rooms'),
        'saveEvent' => __('Save event', 'community-calendar-study-rooms'),
        'saveStaffEntry' => __('Save staff entry', 'community-calendar-study-rooms'),
        'saveChanges' => __('Save changes', 'community-calendar-study-rooms'),
        'confirmDeleteEvent' => __('Delete this event and its registrations and linked room reservation? This cannot be undone.', 'community-calendar-study-rooms'),
        'confirmDeleteStaffEntry' => __('Delete this staff entry and its linked room reservation?', 'community-calendar-study-rooms'),
        'confirmDeleteRoomBooking' => __('Delete this room booking?', 'community-calendar-study-rooms'),
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
    ));
}
add_action('wp_enqueue_scripts', 'calsys_enqueue_public_script');
add_action('init', 'calsys_schedule_reminder_cron');

/** Renders the image selector and preview used in event editing forms. */
function calsys_event_image_field($image_id = 0) {
    $image_id = absint($image_id);
    $image = $image_id ? wp_get_attachment_image(
        $image_id,
        'medium',
        false,
        array('class' => 'calsys-event-image-preview-img', 'loading' => 'lazy')
    ) : '';
    ob_start();
    ?>
    <fieldset class="calsys-event-image-field">
        <legend><?php esc_html_e('Event image', 'community-calendar-study-rooms'); ?></legend>
        <input type="hidden" name="image_id" value="<?php echo esc_attr($image_id); ?>" data-calsys-event-image-id>
        <div class="calsys-event-image-preview" data-calsys-event-image-preview><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <button class="button" type="button" data-calsys-select-event-image><?php esc_html_e('Choose image', 'community-calendar-study-rooms'); ?></button>
        <button class="button" type="button" data-calsys-remove-event-image <?php if (!$image) : ?>hidden<?php endif; ?>><?php esc_html_e('Remove image', 'community-calendar-study-rooms'); ?></button>
        <p class="description"><?php esc_html_e('Choose an image from the WordPress Media Library. It appears with this event on the website.', 'community-calendar-study-rooms'); ?></p>
    </fieldset>
    <?php
    return ob_get_clean();
}

/** Schedules the recurring job that sends opted-in event reminders. */
function calsys_schedule_reminder_cron() {
    if (!calsys_get_settings()['notifications']['email_event_reminders']) {
        if (wp_next_scheduled('calsys_send_due_event_reminders')) {
            wp_clear_scheduled_hook('calsys_send_due_event_reminders');
        }
        return;
    }
    if (wp_next_scheduled('calsys_send_due_event_reminders')) {
        return;
    }
    $scheduled = wp_schedule_event(time() + 300, 'hourly', 'calsys_send_due_event_reminders', array(), true);
    if (is_wp_error($scheduled)) {
        error_log('CommonsCal: unable to schedule event reminders: ' . $scheduled->get_error_message());
    } elseif (!$scheduled) {
        error_log('CommonsCal: unable to schedule event reminders.');
    }
}

/** Returns the supported custom event-registration field types. */
function calsys_registration_field_types() {
    return array(
        'text' => __('Short text', 'community-calendar-study-rooms'),
        'textarea' => __('Paragraph text', 'community-calendar-study-rooms'),
        'email' => __('Email', 'community-calendar-study-rooms'),
        'phone' => __('Phone', 'community-calendar-study-rooms'),
        'select' => __('Dropdown', 'community-calendar-study-rooms'),
        'checkbox' => __('Checkbox', 'community-calendar-study-rooms'),
    );
}

/** Validates and sanitizes custom registration fields submitted in event settings. */
function calsys_sanitize_registration_fields($raw_fields) {
    if (!is_array($raw_fields) || count($raw_fields) > 20) {
        return false;
    }
    $valid_types = calsys_registration_field_types();
    $fields = array();
    foreach ($raw_fields as $raw_field) {
        if (!is_array($raw_field) ||
            !is_string($raw_field['label'] ?? null) ||
            !is_string($raw_field['type'] ?? null) ||
            (isset($raw_field['required']) && !is_scalar($raw_field['required'])) ||
            (isset($raw_field['options']) && !is_string($raw_field['options']) && !is_array($raw_field['options']))) {
            return false;
        }
        $label = sanitize_text_field($raw_field['label']);
        $type = sanitize_key($raw_field['type']);
        if ($label === '' || strlen($label) > 100 || !isset($valid_types[$type])) {
            return false;
        }
        $options = array();
        if ($type === 'select') {
            $raw_options = $raw_field['options'] ?? '';
            if ((is_string($raw_options) && strlen($raw_options) > 3000) ||
                (is_array($raw_options) && count($raw_options) > 20)) {
                return false;
            }
            $option_values = is_string($raw_options) ? preg_split('/\r\n|\r|\n/', $raw_options) : $raw_options;
            foreach ($option_values as $raw_option) {
                if (!is_string($raw_option)) {
                    return false;
                }
                $option = sanitize_text_field($raw_option);
                if ($option !== '' && !in_array($option, $options, true)) {
                    $options[] = $option;
                }
            }
            if (!$options || count($options) > 20 || max(array_map('strlen', $options)) > 100) {
                return false;
            }
        }
        $fields[] = array(
            'label' => $label,
            'type' => $type,
            'required' => !empty($raw_field['required']) ? 1 : 0,
            'options' => $options,
        );
    }
    return $fields;
}

/** Decodes stored registration-field JSON into validated field definitions. */
function calsys_decode_registration_fields($json) {
    $fields = json_decode((string) $json, true);
    if (!is_array($fields)) {
        return array();
    }
    $sanitized = calsys_sanitize_registration_fields($fields);
    return $sanitized === false ? array() : $sanitized;
}

/** Renders the editable custom-field builder for event registration forms. */
function calsys_registration_fields_editor($fields = array(), $name_prefix = 'registration_fields', $present_name = 'registration_fields_present') {
    ob_start();
    ?>
    <fieldset class="calsys-registration-fields-editor">
        <legend><?php esc_html_e('Registration form fields', 'community-calendar-study-rooms'); ?></legend>
        <p class="description"><?php esc_html_e('Add up to 20 custom fields. Attendee answers are visible only to staff.', 'community-calendar-study-rooms'); ?></p>
        <?php if ($present_name !== '') : ?><input type="hidden" name="<?php echo esc_attr($present_name); ?>" value="1"><?php endif; ?>
        <div data-calsys-registration-fields data-next-index="<?php echo esc_attr(count($fields)); ?>" data-name-prefix="<?php echo esc_attr($name_prefix); ?>" data-default-fields="<?php echo esc_attr(wp_json_encode(array_values($fields))); ?>">
            <?php foreach (array_values($fields) as $index => $field) : ?>
                <?php calsys_registration_field_editor_row($index, $field, $name_prefix); ?>
            <?php endforeach; ?>
        </div>
        <button class="button" type="button" data-calsys-add-registration-field><?php esc_html_e('Add registration field', 'community-calendar-study-rooms'); ?></button>
    </fieldset>
    <?php
    return ob_get_clean();
}

/** Renders the event registration toggle, custom fields, and email recipients. */
function calsys_event_registration_editor($enabled = false, $fields = array(), $recipients = array()) {
    ob_start();
    ?>
    <fieldset class="calsys-event-registration-settings">
        <legend><?php esc_html_e('Online registration', 'community-calendar-study-rooms'); ?></legend>
        <label class="calsys-checkbox-label">
            <input name="registration_enabled" type="checkbox" value="1" data-calsys-toggle-registration <?php checked($enabled); ?>>
            <?php esc_html_e('Enable online registration', 'community-calendar-study-rooms'); ?>
        </label>
        <div data-calsys-registration-settings <?php if (!$enabled) : ?>hidden<?php endif; ?>>
            <?php echo calsys_registration_fields_editor($fields); ?>
            <p><label><?php esc_html_e('Email addresses for registration updates (one per line)', 'community-calendar-study-rooms'); ?>
                <textarea name="registration_recipients" rows="3" maxlength="2000"><?php echo esc_textarea(implode("\n", $recipients)); ?></textarea>
            </label></p>
            <p class="description"><?php esc_html_e('These addresses receive an email for each new registration. Leave blank for no event-specific email updates.', 'community-calendar-study-rooms'); ?></p>
        </div>
    </fieldset>
    <?php
    return ob_get_clean();
}

/** Validates and sanitizes event-specific registration notification addresses. */
function calsys_sanitize_registration_recipients($raw_recipients) {
    if (!is_string($raw_recipients) || strlen($raw_recipients) > 2000) {
        return false;
    }
    $recipients = array();
    foreach (preg_split('/[\r\n,;]+/', $raw_recipients) as $raw_email) {
        $email = sanitize_email(trim($raw_email));
        if ($email === '') {
            continue;
        }
        if (!is_email($email)) {
            return false;
        }
        $email = strtolower($email);
        if (!in_array($email, $recipients, true)) {
            $recipients[] = $email;
        }
        if (count($recipients) > 10) {
            return false;
        }
    }
    return $recipients;
}

/** Decodes and validates the stored event-specific notification address list. */
function calsys_decode_registration_recipients($raw_recipients) {
    $recipients = json_decode((string) $raw_recipients, true);
    if (!is_array($recipients)) {
        return array();
    }
    $sanitized = calsys_sanitize_registration_recipients(implode("\n", array_filter($recipients, 'is_string')));
    return $sanitized === false ? array() : $sanitized;
}

/** Renders one registration-field row in the event editor. */
function calsys_registration_field_editor_row($index, $field = array(), $name_prefix = 'registration_fields') {
    $types = calsys_registration_field_types();
    $type = $field['type'] ?? 'text';
    $name = $name_prefix . '[' . $index . ']';
    ?>
    <div class="calsys-registration-field-row" data-calsys-registration-field-row>
        <label><?php esc_html_e('Field label', 'community-calendar-study-rooms'); ?>
            <input name="<?php echo esc_attr($name); ?>[label]" type="text" maxlength="100" value="<?php echo esc_attr($field['label'] ?? ''); ?>" required>
        </label>
        <label><?php esc_html_e('Field type', 'community-calendar-study-rooms'); ?>
            <select name="<?php echo esc_attr($name); ?>[type]" data-calsys-registration-field-type>
                <?php foreach ($types as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
        </label>
        <label data-calsys-registration-field-options <?php if ($type !== 'select') : ?>hidden<?php endif; ?>><?php esc_html_e('Dropdown options (one per line)', 'community-calendar-study-rooms'); ?>
                <textarea name="<?php echo esc_attr($name); ?>[options]" rows="2" <?php if ($type === 'select') : ?>required<?php endif; ?>><?php echo esc_textarea(implode("\n", $field['options'] ?? array())); ?></textarea>
        </label>
        <label class="calsys-registration-field-required"><input name="<?php echo esc_attr($name); ?>[required]" type="checkbox" value="1" <?php checked(!empty($field['required'])); ?>> <?php esc_html_e('Required', 'community-calendar-study-rooms'); ?></label>
        <button class="button" type="button" data-calsys-remove-registration-field><?php esc_html_e('Remove field', 'community-calendar-study-rooms'); ?></button>
    </div>
    <?php
}

/** Returns default values for calendar display, booking, and notification settings. */
function calsys_default_settings() {
    return array(
        'calendar_colors' => array(
            'events' => '#1d5f8a',
            'rooms' => '#99500b',
            'staff' => '#59458d',
        ),
        'staff_types' => array(
            'meeting' => array('label' => __('Meeting', 'community-calendar-study-rooms'), 'color' => '#59458d'),
            'sick_time' => array('label' => __('Sick time', 'community-calendar-study-rooms'), 'color' => '#9c3434'),
            'comp_time' => array('label' => __('Comp time', 'community-calendar-study-rooms'), 'color' => '#287a58'),
            'time_off' => array('label' => __('Time off', 'community-calendar-study-rooms'), 'color' => '#a14d68'),
            'other' => array('label' => __('Other', 'community-calendar-study-rooms'), 'color' => '#59458d'),
        ),
        'event_categories' => array(
            'teen' => __('Teen', 'community-calendar-study-rooms'),
            'adult' => __('Adult', 'community-calendar-study-rooms'),
            'children' => __('Children', 'community-calendar-study-rooms'),
        ),
        'booking_rules' => array(
            'max_advance_days' => 0,
            'min_notice_hours' => 0,
            'max_duration_minutes' => 0,
            'time_increment' => 0,
            'opening_time' => '',
            'closing_time' => '',
        ),
        'room_approval_required' => 1,
        'event_registration' => array(
            'enabled' => 1,
            'max_capacity' => 0,
            'cutoff_minutes' => 0,
            'enabled_by_default' => 0,
            'form_intro' => '',
            'name_label' => __('Your name', 'community-calendar-study-rooms'),
            'email_label' => __('Email address', 'community-calendar-study-rooms'),
            'submit_label' => __('Register', 'community-calendar-study-rooms'),
            'privacy_notice' => __('We use your contact details and registration answers to manage your event registration.', 'community-calendar-study-rooms'),
            'confirmation_message' => __('Your registration is complete.', 'community-calendar-study-rooms'),
            'reminder_label' => __('Email me a reminder 24 hours before this event.', 'community-calendar-study-rooms'),
            'closed_message' => __('Registration is closed.', 'community-calendar-study-rooms'),
            'full_message' => __('Registration is full.', 'community-calendar-study-rooms'),
            'disabled_message' => __('Online registration is not available for this event.', 'community-calendar-study-rooms'),
            'show_privacy_notice' => 1,
            'default_fields' => array(),
        ),
        'calendar_display' => array(
            'week_start_day' => 'site',
            'initial_month' => 'current',
            'show_registration_counts' => 1,
            'show_category_labels' => 1,
        ),
        'notifications' => array(
            'staff_email' => sanitize_email(get_option('admin_email')),
            'notify_staff_room_requests' => 0,
            'email_room_requester' => 0,
            'email_event_registrant' => 0,
            'notify_staff_event_registrations' => 0,
            'email_event_reminders' => 0,
        ),
        'privacy' => array(
            'show_room_names' => 1,
            'show_room_status' => 1,
            'retention_days' => 0,
        ),
    );
}

/** Validates and sanitizes one editable registration-form text setting. */
function calsys_registration_setting_text($registration, $key, $default, $max_length) {
    $value = $registration[$key] ?? $default;
    if (!is_string($value)) {
        return $default;
    }
    $value = sanitize_text_field($value);
    return $value !== '' && strlen($value) <= $max_length ? $value : $default;
}

/** Loads saved settings, merges defaults, and caches the result for this request. */
function calsys_get_settings() {
    static $settings_cache = null;
    if ($settings_cache !== null) {
        return $settings_cache;
    }

    $defaults = calsys_default_settings();
    $saved = get_option('calsys_settings', array());
    if (!is_array($saved)) {
        $saved = array();
    }

    $settings = array(
        'calendar_colors' => $defaults['calendar_colors'],
        'staff_types' => $defaults['staff_types'],
        'event_categories' => $defaults['event_categories'],
        'booking_rules' => $defaults['booking_rules'],
        'room_approval_required' => $defaults['room_approval_required'],
        'event_registration' => $defaults['event_registration'],
        'calendar_display' => $defaults['calendar_display'],
        'notifications' => $defaults['notifications'],
        'privacy' => $defaults['privacy'],
    );
    if (!empty($saved['calendar_colors']) && is_array($saved['calendar_colors'])) {
        foreach ($defaults['calendar_colors'] as $key => $default_color) {
            $saved_color = $saved['calendar_colors'][$key] ?? '';
            $color = is_string($saved_color) ? sanitize_hex_color($saved_color) : false;
            if ($color) {
                $settings['calendar_colors'][$key] = $color;
            }
        }
    }
    if (!empty($saved['staff_types']) && is_array($saved['staff_types'])) {
        $settings['staff_types'] = array();
        foreach ($saved['staff_types'] as $key => $type) {
            $key = sanitize_key($key);
            if (!$key || !is_array($type)) {
                continue;
            }
            $raw_label = $type['label'] ?? '';
            $raw_color = $type['color'] ?? '';
            if (!is_string($raw_label) || !is_string($raw_color)) {
                continue;
            }
            $label = sanitize_text_field($raw_label);
            $color = sanitize_hex_color($raw_color);
            if ($label !== '' && $color) {
                $settings['staff_types'][$key] = array('label' => $label, 'color' => $color);
            }
        }
        if (!$settings['staff_types']) {
            $settings['staff_types'] = $defaults['staff_types'];
        }
    }
    if (isset($saved['event_categories']) && is_array($saved['event_categories'])) {
        $settings['event_categories'] = array();
        foreach ($saved['event_categories'] as $key => $label) {
            $key = sanitize_key($key);
            if (!is_string($label)) {
                continue;
            }
            $label = sanitize_text_field($label);
            if ($key !== '' && $label !== '' && strlen($label) <= 60) {
                $settings['event_categories'][$key] = $label;
            }
        }
        if (!$settings['event_categories']) {
            $settings['event_categories'] = $defaults['event_categories'];
        }
    }

    $rules = is_array($saved['booking_rules'] ?? null) ? $saved['booking_rules'] : array();
    $opening_time = is_string($rules['opening_time'] ?? null) ? $rules['opening_time'] : '';
    $closing_time = is_string($rules['closing_time'] ?? null) ? $rules['closing_time'] : '';
    if (($opening_time === '') !== ($closing_time === '') ||
        ($opening_time !== '' && (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $opening_time) ||
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $closing_time) || $opening_time >= $closing_time))) {
        $opening_time = '';
        $closing_time = '';
    }
    $legacy_hours = $opening_time === ''
        ? array('mode' => 'unrestricted', 'open' => '', 'close' => '')
        : array('mode' => 'open', 'open' => $opening_time, 'close' => $closing_time);
    $legacy_week = array_fill(0, 7, $legacy_hours);
    $schedule = calsys_normalize_room_schedule(
        $rules['weekly_hours'] ?? $legacy_week,
        $rules['holiday_hours'] ?? array()
    );
    if ($schedule === false) {
        error_log('CommonsCal: invalid saved room opening hours; public bookings are closed until corrected.');
        $schedule = array('weekly' => array_fill(0, 7, array('mode' => 'closed', 'open' => '', 'close' => '')), 'holidays' => array());
    }
    $settings['booking_rules'] = array(
        'max_advance_days' => min(730, max(0, absint($rules['max_advance_days'] ?? $defaults['booking_rules']['max_advance_days']))),
        'min_notice_hours' => min(8760, max(0, absint($rules['min_notice_hours'] ?? $defaults['booking_rules']['min_notice_hours']))),
        'max_duration_minutes' => min(1440, max(0, absint($rules['max_duration_minutes'] ?? $defaults['booking_rules']['max_duration_minutes']))),
        'time_increment' => in_array(absint($rules['time_increment'] ?? 0), array(0, 5, 10, 15, 20, 30, 60), true)
            ? absint($rules['time_increment'] ?? 0) : 0,
        'opening_time' => $opening_time,
        'closing_time' => $closing_time,
        'weekly_hours' => $schedule['weekly'],
        'holiday_hours' => $schedule['holidays'],
    );
    $settings['room_approval_required'] = isset($saved['room_approval_required'])
        ? (int) (bool) $saved['room_approval_required']
        : $defaults['room_approval_required'];

    $registration = is_array($saved['event_registration'] ?? null) ? $saved['event_registration'] : array();
    $settings['event_registration'] = array(
        'enabled' => isset($registration['enabled']) ? (int) (bool) $registration['enabled'] : $defaults['event_registration']['enabled'],
        'max_capacity' => min(10000, max(0, absint($registration['max_capacity'] ?? $defaults['event_registration']['max_capacity']))),
        'cutoff_minutes' => min(525600, max(0, absint($registration['cutoff_minutes'] ?? $defaults['event_registration']['cutoff_minutes']))),
        'enabled_by_default' => isset($registration['enabled_by_default']) ? (int) (bool) $registration['enabled_by_default'] : $defaults['event_registration']['enabled_by_default'],
        'form_intro' => is_string($registration['form_intro'] ?? null) && strlen($registration['form_intro']) <= 1000
            ? sanitize_textarea_field($registration['form_intro'])
            : $defaults['event_registration']['form_intro'],
        'name_label' => calsys_registration_setting_text($registration, 'name_label', $defaults['event_registration']['name_label'], 100),
        'email_label' => calsys_registration_setting_text($registration, 'email_label', $defaults['event_registration']['email_label'], 100),
        'submit_label' => calsys_registration_setting_text($registration, 'submit_label', $defaults['event_registration']['submit_label'], 60),
        'privacy_notice' => calsys_registration_setting_text($registration, 'privacy_notice', $defaults['event_registration']['privacy_notice'], 500),
        'confirmation_message' => calsys_registration_setting_text($registration, 'confirmation_message', $defaults['event_registration']['confirmation_message'], 500),
        'reminder_label' => calsys_registration_setting_text($registration, 'reminder_label', $defaults['event_registration']['reminder_label'], 200),
        'closed_message' => calsys_registration_setting_text($registration, 'closed_message', $defaults['event_registration']['closed_message'], 300),
        'full_message' => calsys_registration_setting_text($registration, 'full_message', $defaults['event_registration']['full_message'], 300),
        'disabled_message' => calsys_registration_setting_text($registration, 'disabled_message', $defaults['event_registration']['disabled_message'], 300),
        'show_privacy_notice' => isset($registration['show_privacy_notice']) ? (int) (bool) $registration['show_privacy_notice'] : $defaults['event_registration']['show_privacy_notice'],
        'default_fields' => calsys_sanitize_registration_fields($registration['default_fields'] ?? array()) ?: array(),
    );

    $display = is_array($saved['calendar_display'] ?? null) ? $saved['calendar_display'] : array();
    $week_start_day = $display['week_start_day'] ?? $defaults['calendar_display']['week_start_day'];
    if ($week_start_day !== 'site' && (!is_numeric($week_start_day) || (int) $week_start_day < 0 || (int) $week_start_day > 6)) {
        $week_start_day = 'site';
    }
    $settings['calendar_display'] = array(
        'week_start_day' => $week_start_day === 'site' ? 'site' : (int) $week_start_day,
        'initial_month' => in_array($display['initial_month'] ?? '', array('current', 'next'), true)
            ? $display['initial_month'] : $defaults['calendar_display']['initial_month'],
        'show_registration_counts' => isset($display['show_registration_counts']) ? (int) (bool) $display['show_registration_counts'] : $defaults['calendar_display']['show_registration_counts'],
        'show_category_labels' => isset($display['show_category_labels']) ? (int) (bool) $display['show_category_labels'] : $defaults['calendar_display']['show_category_labels'],
    );

    $notifications = is_array($saved['notifications'] ?? null) ? $saved['notifications'] : array();
    $raw_notification_email = $notifications['staff_email'] ?? $defaults['notifications']['staff_email'];
    $notification_email = is_string($raw_notification_email) ? sanitize_email($raw_notification_email) : '';
    $settings['notifications'] = array(
        'staff_email' => is_email($notification_email) ? $notification_email : $defaults['notifications']['staff_email'],
        'notify_staff_room_requests' => isset($notifications['notify_staff_room_requests']) ? (int) (bool) $notifications['notify_staff_room_requests'] : $defaults['notifications']['notify_staff_room_requests'],
        'email_room_requester' => isset($notifications['email_room_requester']) ? (int) (bool) $notifications['email_room_requester'] : $defaults['notifications']['email_room_requester'],
        'email_event_registrant' => isset($notifications['email_event_registrant']) ? (int) (bool) $notifications['email_event_registrant'] : $defaults['notifications']['email_event_registrant'],
        'notify_staff_event_registrations' => isset($notifications['notify_staff_event_registrations']) ? (int) (bool) $notifications['notify_staff_event_registrations'] : $defaults['notifications']['notify_staff_event_registrations'],
        'email_event_reminders' => isset($notifications['email_event_reminders']) ? (int) (bool) $notifications['email_event_reminders'] : $defaults['notifications']['email_event_reminders'],
    );

    $privacy = is_array($saved['privacy'] ?? null) ? $saved['privacy'] : array();
    $settings['privacy'] = array(
        'show_room_names' => isset($privacy['show_room_names']) ? (int) (bool) $privacy['show_room_names'] : $defaults['privacy']['show_room_names'],
        'show_room_status' => isset($privacy['show_room_status']) ? (int) (bool) $privacy['show_room_status'] : $defaults['privacy']['show_room_status'],
        'retention_days' => min(3650, absint($privacy['retention_days'] ?? $defaults['privacy']['retention_days'])),
    );
    $settings_cache = $settings;
    return $settings_cache;
}

/** Returns configured staff-calendar type keys and their display labels. */
function calsys_staff_types() {
    $settings = calsys_get_settings();
    $types = array();
    foreach ($settings['staff_types'] as $key => $type) {
        $types[$key] = $type['label'];
    }
    return $types;
}

/** Returns configured event-category labels keyed by category identifier. */
function calsys_event_categories() {
    $settings = calsys_get_settings();
    return $settings['event_categories'];
}

/** Retrieves active rooms for new public or staff bookings. */
function calsys_active_rooms() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT id, name, capacity FROM " . calsys_table('rooms') . " WHERE active = 1 ORDER BY name ASC"
    );
}

/** Retrieves all rooms, including those unavailable for new bookings. */
function calsys_all_rooms() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT id, name, capacity, active FROM " . calsys_table('rooms') . " ORDER BY active DESC, name ASC"
    );
}

/** Formats a room name and capacity for selection controls. */
function calsys_room_option_label($room) {
    return $room->name . (empty($room->active) ? ' (' . __('inactive', 'community-calendar-study-rooms') . ')' : '');
}

/** Returns the configured calendar week-start day as a weekday index. */
function calsys_week_start_day() {
    $display = calsys_get_settings()['calendar_display'];
    return $display['week_start_day'] === 'site'
        ? (int) get_option('start_of_week')
        : (int) $display['week_start_day'];
}

/** Resolves an event's capacity against the site-wide registration limit. */
function calsys_event_capacity($event_capacity) {
    $global_capacity = (int) calsys_get_settings()['event_registration']['max_capacity'];
    $event_capacity = (int) $event_capacity;
    if ($global_capacity > 0 && ($event_capacity === 0 || $global_capacity < $event_capacity)) {
        return $global_capacity;
    }
    return $event_capacity;
}

/** Determines whether online registration remains open for an event. */
function calsys_event_registration_is_open($starts_at) {
    $registration = calsys_get_settings()['event_registration'];
    if (!$registration['enabled']) {
        return false;
    }
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $starts_at, wp_timezone());
    if (!$start || $start->getTimestamp() <= time()) {
        return false;
    }
    return $start->getTimestamp() - ((int) $registration['cutoff_minutes'] * 60) > time();
}

/** Indicates whether public calendar entries should show registration counts. */
function calsys_show_public_registration_counts($event) {
    return current_user_can('manage_calendar_system') ||
        !empty($event->show_registration_counts);
}

/** Indicates whether public calendar entries should show event categories. */
function calsys_show_public_category_labels() {
    return current_user_can('manage_calendar_system') ||
        (bool) calsys_get_settings()['calendar_display']['show_category_labels'];
}

/** Sends a plain-text plugin notification email to a validated recipient. */
function calsys_send_email($recipient, $subject, $message) {
    $sent = wp_mail($recipient, $subject, $message);
    if (!$sent) {
        error_log('CommonsCal: email delivery failed.');
    }
    return $sent;
}

/** Returns the configured display color for an event, room, or staff entry. */
function calsys_calendar_entry_color($calendar_type, $entry_type = '', $room_color = '') {
    $settings = calsys_get_settings();
    if ($calendar_type === 'staff') {
        if (isset($settings['staff_types'][$entry_type]['color'])) {
            return $settings['staff_types'][$entry_type]['color'];
        }
        return $settings['calendar_colors']['staff'];
    }
    if ($calendar_type === 'room') {
        return is_string($room_color) && sanitize_hex_color($room_color)
            ? sanitize_hex_color($room_color) : $settings['calendar_colors']['rooms'];
    }
    return $settings['calendar_colors']['events'];
}

/** Prevents caching calendar pages whose content depends on staff permissions. */
function calsys_disable_calendar_page_cache() {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    if (!headers_sent()) {
        nocache_headers();
    }
}
