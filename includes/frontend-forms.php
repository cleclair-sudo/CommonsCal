<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Renders staff sign-in status and a login link that returns to the calendar. */
function calsys_staff_access_link($action = 'room') {
    calsys_disable_calendar_page_cache();

    $return_url = get_permalink(get_queried_object_id());
    if (!$return_url) {
        $return_url = home_url('/');
    }
    $month = sanitize_text_field(wp_unslash($_GET['calsys_month'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}$/', $month)) {
        $return_url = add_query_arg('calsys_month', $month, $return_url);
    }

    if (!is_user_logged_in()) {
        return '<nav class="calsys-access-links" aria-label="' . esc_attr__('Staff access', 'community-calendar-study-rooms') . '">' .
            '<a href="' . esc_url(wp_login_url($return_url)) . '">' . esc_html__('Staff login', 'community-calendar-study-rooms') . '</a>' .
            '</nav>';
    }

    if (!current_user_can('manage_calendar_system')) {
        return '<nav class="calsys-access-links" aria-label="' . esc_attr__('Staff access', 'community-calendar-study-rooms') . '">' .
            '<span class="calsys-access-status">' . esc_html__('You are signed in, but this account does not have calendar staff access.', 'community-calendar-study-rooms') . '</span>' .
            '<a href="' . esc_url(wp_logout_url($return_url)) . '">' . esc_html__('Log out', 'community-calendar-study-rooms') . '</a>' .
            '</nav>';
    }

    $output = '<nav class="calsys-access-links" aria-label="' . esc_attr__('Staff access', 'community-calendar-study-rooms') . '">';
    if ($action === 'staff') {
        $output .= '<button class="calsys-day-action" type="button" data-calsys-open-staff>' . esc_html__('Add staff time', 'community-calendar-study-rooms') . '</button>';
    } elseif ($action === 'room') {
        global $wpdb;
        $has_active_rooms = $wpdb->get_var("SELECT id FROM " . calsys_table('rooms') . " WHERE active = 1 LIMIT 1");
        if ($has_active_rooms) {
            $output .= '<button class="calsys-day-action" type="button" data-calsys-open-room>' . esc_html__('Request a room', 'community-calendar-study-rooms') . '</button>';
        }
    }
    $output .= '<a href="' . esc_url(wp_logout_url($return_url)) . '">' . esc_html__('Staff log out', 'community-calendar-study-rooms') . '</a>';
    return $output . '</nav>';
}

/** Renders the public attendee-count visibility control for an event. */
function calsys_event_count_visibility_field($show = true) {
    ob_start();
    ?>
    <p class="calsys-form-wide"><label class="calsys-checkbox-label"><input name="show_registration_counts" type="checkbox" value="1" <?php checked($show); ?>> <?php esc_html_e('Show attendee counts and capacity publicly', 'community-calendar-study-rooms'); ?></label><input type="hidden" name="show_registration_counts_present" value="1"></p>
    <?php
    return ob_get_clean();
}

/** Renders the staff-only event creation and editing dialog for public calendars. */
function calsys_front_event_form() {
    if (!current_user_can('manage_calendar_system')) {
        return '';
    }

    $categories = calsys_event_categories();
    $rooms = calsys_all_rooms();
    $registration_defaults = calsys_get_settings()['event_registration'];
    ob_start();
    ?>
    <dialog class="calsys-modal calsys-event-dialog" aria-label="<?php esc_attr_e('Event editor', 'community-calendar-study-rooms'); ?>">
        <section class="calsys-action-card calsys-event-editor">
            <div class="calsys-action-card-heading">
                <span class="calsys-action-kicker"><?php esc_html_e('Staff tools', 'community-calendar-study-rooms'); ?></span>
                <div class="calsys-dialog-title-row">
                    <h2><?php esc_html_e('Add an event', 'community-calendar-study-rooms'); ?></h2>
                    <button class="calsys-dialog-close" type="button" data-calsys-close-event aria-label="<?php esc_attr_e('Close event editor', 'community-calendar-study-rooms'); ?>">&times;</button>
                </div>
                <p><?php esc_html_e('Create an event or edit one from the calendar.', 'community-calendar-study-rooms'); ?></p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-form calsys-action-form">
                <?php wp_nonce_field('calsys_front_save_event'); ?>
                <input type="hidden" name="action" value="calsys_front_save_event">
                <input type="hidden" name="event_id" value="0">
                <div class="calsys-form-grid">
                    <p class="calsys-form-wide"><label><?php esc_html_e('Event title', 'community-calendar-study-rooms'); ?><input name="title" type="text" maxlength="200" required></label></p>
                    <p class="calsys-form-wide"><label><?php esc_html_e('Description', 'community-calendar-study-rooms'); ?><textarea name="description" rows="3"></textarea></label></p>
                    <div class="calsys-form-wide"><?php echo calsys_event_image_field(); ?></div>
                    <p><label><?php esc_html_e('Starts', 'community-calendar-study-rooms'); ?><input name="starts_at" type="datetime-local" required></label></p>
                    <p><label><?php esc_html_e('Ends', 'community-calendar-study-rooms'); ?><input name="ends_at" type="datetime-local" required></label></p>
                    <p><label><?php esc_html_e('Registration limit (0 means unlimited)', 'community-calendar-study-rooms'); ?><input name="capacity" type="number" min="0" step="1" value="0"></label></p>
                    <?php echo calsys_event_count_visibility_field(); ?>
                    <p><label><?php esc_html_e('Visibility', 'community-calendar-study-rooms'); ?><select name="status"><option value="published"><?php esc_html_e('Published', 'community-calendar-study-rooms'); ?></option><option value="draft"><?php esc_html_e('Draft', 'community-calendar-study-rooms'); ?></option></select></label></p>
                    <p><label><?php esc_html_e('Category', 'community-calendar-study-rooms'); ?><select name="category"><?php foreach ($categories as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'adult'); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></p>
                    <p class="calsys-form-wide"><label><?php esc_html_e('Room (optional)', 'community-calendar-study-rooms'); ?>
                        <select name="room_id" data-calsys-room-select>
                            <option value=""><?php esc_html_e('No room reservation', 'community-calendar-study-rooms'); ?></option>
                            <?php foreach ($rooms as $room) : ?><option value="<?php echo esc_attr($room->id); ?>"><?php echo esc_html(calsys_room_option_label($room)); ?></option><?php endforeach; ?>
                        </select>
                    </label></p>
                    <?php if (!$rooms) : ?><p class="calsys-form-wide description"><?php esc_html_e('Add a room in Settings before reserving one for an event.', 'community-calendar-study-rooms'); ?></p><?php endif; ?>
                    <div class="calsys-form-wide"><?php echo calsys_equipment_fields(); ?></div>
                    <div class="calsys-form-wide"><?php echo calsys_event_registration_editor(
                        !empty($registration_defaults['enabled_by_default']),
                        $registration_defaults['default_fields']
                    ); ?></div>
                    <p class="calsys-form-wide" data-calsys-recurrence-toggle-row><label class="calsys-checkbox-label"><input name="recurring" type="checkbox" value="1" data-calsys-toggle-recurrence> <?php esc_html_e('Repeat this event', 'community-calendar-study-rooms'); ?></label></p>
                    <p data-calsys-recurrence-field hidden><label><?php esc_html_e('Repeat', 'community-calendar-study-rooms'); ?><select name="recurrence_frequency"><option value="daily"><?php esc_html_e('Daily', 'community-calendar-study-rooms'); ?></option><option value="weekly"><?php esc_html_e('Weekly', 'community-calendar-study-rooms'); ?></option><option value="monthly"><?php esc_html_e('Monthly', 'community-calendar-study-rooms'); ?></option></select></label></p>
                    <p data-calsys-recurrence-field hidden><label><?php esc_html_e('Stop repeating by', 'community-calendar-study-rooms'); ?><select name="recurrence_end_type" data-calsys-recurrence-end><option value="count"><?php esc_html_e('Number of occurrences', 'community-calendar-study-rooms'); ?></option><option value="date"><?php esc_html_e('End date', 'community-calendar-study-rooms'); ?></option></select></label></p>
                    <p data-calsys-recurrence-field hidden data-calsys-occurrences><label><?php esc_html_e('Occurrences (maximum 100)', 'community-calendar-study-rooms'); ?><input name="recurrence_count" type="number" min="2" max="100" value="10"></label></p>
                    <p data-calsys-recurrence-field hidden data-calsys-end-date><label><?php esc_html_e('Repeat through', 'community-calendar-study-rooms'); ?><input name="recurrence_until" type="date"></label></p>
                </div>
                <div class="calsys-action-buttons">
                    <button type="submit" data-calsys-save-event><?php esc_html_e('Save event', 'community-calendar-study-rooms'); ?></button>
                    <button class="calsys-button-secondary" data-calsys-reset-event type="button"><?php esc_html_e('Clear form', 'community-calendar-study-rooms'); ?></button>
                </div>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-delete-form" data-calsys-delete-event-form hidden>
                <?php wp_nonce_field('calsys_front_delete_event'); ?>
                <input type="hidden" name="action" value="calsys_front_delete_event">
                <input type="hidden" name="event_id" value="0">
                <button class="calsys-delete-button" type="submit"><?php esc_html_e('Delete event', 'community-calendar-study-rooms'); ?></button>
                <p><?php esc_html_e('Deleting this event also deletes its registrations and linked room reservation.', 'community-calendar-study-rooms'); ?></p>
            </form>
        </section>
    </dialog>
    <?php
    return ob_get_clean();
}

/** Renders the staff-only meeting, time-off, and other schedule-entry dialog. */
function calsys_front_staff_form() {
    if (!current_user_can('manage_calendar_system')) {
        return '';
    }
    $types = calsys_staff_types();
    $rooms = calsys_all_rooms();
    ob_start();
    ?>
    <dialog class="calsys-modal calsys-staff-dialog" aria-label="<?php esc_attr_e('Staff time editor', 'community-calendar-study-rooms'); ?>">
        <section class="calsys-action-card calsys-staff-editor" id="calsys-staff-editor">
            <div class="calsys-action-card-heading">
                <span class="calsys-action-kicker"><?php esc_html_e('Staff tools', 'community-calendar-study-rooms'); ?></span>
                <div class="calsys-dialog-title-row">
                    <h2><?php esc_html_e('Add staff time', 'community-calendar-study-rooms'); ?></h2>
                    <button class="calsys-dialog-close" type="button" data-calsys-close-staff aria-label="<?php esc_attr_e('Close staff time editor', 'community-calendar-study-rooms'); ?>">&times;</button>
                </div>
                <p><?php esc_html_e('Schedule a meeting, sick time, comp time, or another staff booking.', 'community-calendar-study-rooms'); ?></p>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-form calsys-action-form">
                <?php wp_nonce_field('calsys_front_save_staff_entry'); ?>
                <input type="hidden" name="action" value="calsys_front_save_staff_entry">
                <input type="hidden" name="entry_id" value="0">
                <div class="calsys-form-grid">
                    <p><label><?php esc_html_e('Title', 'community-calendar-study-rooms'); ?><input name="title" type="text" maxlength="200" required></label></p>
                    <p><label><?php esc_html_e('Type', 'community-calendar-study-rooms'); ?><select name="entry_type"><?php foreach ($types as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></p>
                    <p><label><?php esc_html_e('Starts', 'community-calendar-study-rooms'); ?><input name="starts_at" type="datetime-local" required></label></p>
                    <p><label><?php esc_html_e('Ends', 'community-calendar-study-rooms'); ?><input name="ends_at" type="datetime-local" required></label></p>
                    <p class="calsys-form-wide"><label class="calsys-checkbox-label"><input name="all_day" type="checkbox" value="1"> <?php esc_html_e('All day (uses the start and end dates)', 'community-calendar-study-rooms'); ?></label></p>
                    <p class="calsys-form-wide"><label><?php esc_html_e('Room (optional)', 'community-calendar-study-rooms'); ?>
                        <select name="room_id" data-calsys-room-select>
                            <option value=""><?php esc_html_e('No room reservation', 'community-calendar-study-rooms'); ?></option>
                            <?php foreach ($rooms as $room) : ?><option value="<?php echo esc_attr($room->id); ?>"><?php echo esc_html(calsys_room_option_label($room)); ?></option><?php endforeach; ?>
                        </select>
                    </label></p>
                    <?php if (!$rooms) : ?><p class="calsys-form-wide description"><?php esc_html_e('Add a room in Settings before reserving one for staff time.', 'community-calendar-study-rooms'); ?></p><?php endif; ?>
                    <div class="calsys-form-wide"><?php echo calsys_equipment_fields(); ?></div>
                </div>
                <div class="calsys-action-buttons">
                    <button type="submit" data-calsys-save-staff><?php esc_html_e('Save staff entry', 'community-calendar-study-rooms'); ?></button>
                    <button class="calsys-button-secondary" data-calsys-reset-staff type="button"><?php esc_html_e('Clear form', 'community-calendar-study-rooms'); ?></button>
                </div>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-delete-form" data-calsys-delete-staff-form hidden>
                <?php wp_nonce_field('calsys_front_delete_staff_entry'); ?>
                <input type="hidden" name="action" value="calsys_front_delete_staff_entry">
                <input type="hidden" name="entry_id" value="0">
                <input type="hidden" name="calsys_frontend" value="1">
                <button class="calsys-delete-button" type="submit"><?php esc_html_e('Delete staff entry', 'community-calendar-study-rooms'); ?></button>
            </form>
        </section>
    </dialog>
    <?php
    return ob_get_clean();
}

/** Builds quarter-hour options, respecting stricter public booking increments. */
function calsys_room_time_options($increment = 0) {
    $html = '<option value="">' . esc_html__('Choose a time', 'community-calendar-study-rooms') . '</option>';
    for ($minutes = 0; $minutes < 1440; $minutes += 15) {
        if ($increment > 0 && $minutes % $increment !== 0) {
            continue;
        }
        $time = sprintf('%02d:%02d', (int) floor($minutes / 60), $minutes % 60);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', '2000-01-01 ' . $time, wp_timezone());
        $html .= '<option value="' . esc_attr($time) . '">' . esc_html(wp_date(get_option('time_format'), $date->getTimestamp(), wp_timezone())) . '</option>';
    }
    return $html;
}

/** Adds the configuration needed for live room-availability checks. */
function calsys_room_availability_attributes($staff) {
    return ' data-calsys-room-availability data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) .
        '" data-availability-nonce="' . esc_attr(wp_create_nonce('calsys_room_availability')) .
        '" data-staff-booking="' . ($staff ? '1' : '0') . '"';
}

/** Renders the live room-availability and opening-hours messages. */
function calsys_room_availability_messages() {
    return '<div class="calsys-room-availability calsys-form-wide" aria-live="polite" data-calsys-availability ' .
        'data-closed="' . esc_attr__('The room is closed on this date.', 'community-calendar-study-rooms') . '" ' .
        'data-hours="' . esc_attr__('Open hours: %1$s to %2$s.', 'community-calendar-study-rooms') . '" ' .
        'data-outside="' . esc_attr__('This booking is outside opening hours. Staff can still book it.', 'community-calendar-study-rooms') . '" ' .
        'data-conflict="' . esc_attr__('This room is already requested or booked for that time. Choose another time or room.', 'community-calendar-study-rooms') . '" ' .
        'data-error="' . esc_attr__('Room availability could not be checked. Please try again; the final check happens when you save.', 'community-calendar-study-rooms') . '"></div>';
}

/** Renders the room-request form, with staff booking and edit controls when authorized. */
function calsys_front_room_form() {
    global $wpdb;
    $rooms = $wpdb->get_results("SELECT id, name, capacity FROM " . calsys_table('rooms') . " WHERE active = 1 ORDER BY name ASC");
    if (!$rooms) {
        return '<section class="calsys-action-card"><p>' . esc_html__('Room requests are not available right now.', 'community-calendar-study-rooms') . '</p></section>';
    }
    $staff_booking = current_user_can('manage_calendar_system');
    $booking_rules = calsys_get_settings()['booking_rules'];
    $approval_required = (bool) calsys_get_settings()['room_approval_required'];
    $time_options = calsys_room_time_options($staff_booking ? 0 : (int) $booking_rules['time_increment']);
    $latest_booking_date = !$staff_booking && $booking_rules['max_advance_days'] > 0
        ? DateTimeImmutable::createFromFormat('!Y-m-d', current_time('Y-m-d'), wp_timezone())
            ->modify('+' . (int) $booking_rules['max_advance_days'] . ' days')->format('Y-m-d')
        : '';
    ob_start();
    ?>
    <dialog class="calsys-modal calsys-room-dialog" aria-label="<?php esc_attr_e('Room booking form', 'community-calendar-study-rooms'); ?>">
    <section class="calsys-action-card calsys-room-request" id="calsys-room-request">
        <div class="calsys-action-card-heading">
            <span class="calsys-action-kicker"><?php echo $staff_booking ? esc_html__('Staff tools', 'community-calendar-study-rooms') : esc_html__('Room booking', 'community-calendar-study-rooms'); ?></span>
            <h2><?php echo $staff_booking ? esc_html__('Add a booking', 'community-calendar-study-rooms') : esc_html__('Request a study room', 'community-calendar-study-rooms'); ?></h2>
            <p><?php
                if ($staff_booking) {
                    esc_html_e('Bookings are private by default. Check Public event to publish this booking on the calendar.', 'community-calendar-study-rooms');
                } elseif ($approval_required) {
                    esc_html_e('Choose a room and time. Your request will be sent to staff for approval.', 'community-calendar-study-rooms');
                } else {
                    esc_html_e('Choose a room and time. Valid requests are confirmed immediately.', 'community-calendar-study-rooms');
                }
            ?></p>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-form calsys-action-form"<?php echo calsys_room_availability_attributes($staff_booking); ?>>
            <?php if ($staff_booking) : ?>
                <?php wp_nonce_field('calsys_front_staff_booking', '_wpnonce_booking'); ?>
            <?php else : ?>
                <?php wp_nonce_field('calsys_request_room'); ?>
            <?php endif; ?>
            <?php if ($staff_booking) : wp_nonce_field('calsys_front_save_event'); endif; ?>
            <input type="hidden" name="action" value="calsys_request_room">
            <input type="hidden" name="calsys_frontend" value="1">
            <?php if ($staff_booking) : ?><input type="hidden" name="booking_id" value="0"><?php endif; ?>
            <?php if ($staff_booking) : ?><input type="hidden" name="staff_booking" value="1"><?php endif; ?>
            <?php if ($staff_booking) : ?>
                <p class="calsys-form-wide"><label class="calsys-checkbox-label"><input name="public_event" type="checkbox" value="1" data-calsys-toggle-public-event> <?php esc_html_e('Public event (visible on the public calendar)', 'community-calendar-study-rooms'); ?></label></p>
            <?php endif; ?>
            <p class="calsys-form-wide"><label><?php esc_html_e('Room', 'community-calendar-study-rooms'); ?><select name="room_id" required><option value=""><?php esc_html_e('Choose a room', 'community-calendar-study-rooms'); ?></option><?php foreach ($rooms as $room) : ?><option value="<?php echo esc_attr($room->id); ?>"><?php echo esc_html($room->name . ' (' . sprintf(_n('%d person', '%d people', (int) $room->capacity, 'community-calendar-study-rooms'), (int) $room->capacity) . ')'); ?></option><?php endforeach; ?></select></label></p>
            <?php echo calsys_equipment_fields(); ?>
            <div class="calsys-form-grid">
                <p data-calsys-private-booking-field><label><?php echo $staff_booking ? esc_html__('Contact name', 'community-calendar-study-rooms') : esc_html__('Your name', 'community-calendar-study-rooms'); ?><input name="requester_name" type="text" autocomplete="name" maxlength="200" required></label></p>
                <p data-calsys-private-booking-field><label><?php echo $staff_booking ? esc_html__('Contact email', 'community-calendar-study-rooms') : esc_html__('Email address', 'community-calendar-study-rooms'); ?><input name="requester_email" type="email" autocomplete="email" maxlength="200" required></label></p>
                <?php if (!$staff_booking) : ?>
                    <p class="calsys-form-wide"><label><?php esc_html_e('Reason for requesting room', 'community-calendar-study-rooms'); ?><textarea name="request_reason" rows="3" maxlength="1000"></textarea></label></p>
                <?php endif; ?>
                <p><label><?php esc_html_e('Date', 'community-calendar-study-rooms'); ?><input name="booking_date" type="date" min="<?php echo esc_attr(current_time('Y-m-d')); ?>" <?php if ($latest_booking_date !== '') : ?>max="<?php echo esc_attr($latest_booking_date); ?>"<?php endif; ?> required></label></p>
                <p><label><?php esc_html_e('Start time', 'community-calendar-study-rooms'); ?><select name="starts_at" data-calsys-room-time required><?php echo $time_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label></p>
                <p><label><?php esc_html_e('End time', 'community-calendar-study-rooms'); ?><select name="ends_at" data-calsys-room-time required><?php echo $time_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label></p>
                <?php echo calsys_room_availability_messages(); ?>
                <?php if ($staff_booking) : ?>
                    <div class="calsys-form-wide" data-calsys-public-event-fields hidden>
                        <div class="calsys-form-grid">
                            <p class="calsys-form-wide"><label><?php esc_html_e('Event title', 'community-calendar-study-rooms'); ?><input name="title" type="text" maxlength="200" required></label></p>
                            <p class="calsys-form-wide"><label><?php esc_html_e('Description', 'community-calendar-study-rooms'); ?><textarea name="description" rows="3"></textarea></label></p>
                            <div class="calsys-form-wide"><?php echo calsys_event_image_field(); ?></div>
                            <p><label><?php esc_html_e('Category', 'community-calendar-study-rooms'); ?><select name="category"><?php foreach (calsys_event_categories() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'adult'); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><?php esc_html_e('Registration limit (0 means unlimited)', 'community-calendar-study-rooms'); ?><input name="capacity" type="number" min="0" step="1" value="0"></label></p>
                            <?php echo calsys_event_count_visibility_field(); ?>
                            <input name="status" type="hidden" value="published">
                            <div class="calsys-form-wide"><?php echo calsys_event_registration_editor(
                                !empty(calsys_get_settings()['event_registration']['enabled_by_default']),
                                calsys_get_settings()['event_registration']['default_fields']
                            ); ?></div>
                            <p class="calsys-form-wide"><label class="calsys-checkbox-label"><input name="recurring" type="checkbox" value="1" data-calsys-toggle-recurrence> <?php esc_html_e('Repeat this event', 'community-calendar-study-rooms'); ?></label></p>
                            <p data-calsys-recurrence-field hidden><label><?php esc_html_e('Repeat', 'community-calendar-study-rooms'); ?><select name="recurrence_frequency"><option value="daily"><?php esc_html_e('Daily', 'community-calendar-study-rooms'); ?></option><option value="weekly"><?php esc_html_e('Weekly', 'community-calendar-study-rooms'); ?></option><option value="monthly"><?php esc_html_e('Monthly', 'community-calendar-study-rooms'); ?></option></select></label></p>
                            <p data-calsys-recurrence-field hidden><label><?php esc_html_e('Stop repeating by', 'community-calendar-study-rooms'); ?><select name="recurrence_end_type" data-calsys-recurrence-end><option value="count"><?php esc_html_e('Number of occurrences', 'community-calendar-study-rooms'); ?></option><option value="date"><?php esc_html_e('End date', 'community-calendar-study-rooms'); ?></option></select></label></p>
                            <p data-calsys-recurrence-field hidden data-calsys-occurrences><label><?php esc_html_e('Occurrences (maximum 100)', 'community-calendar-study-rooms'); ?><input name="recurrence_count" type="number" min="2" max="100" value="10"></label></p>
                            <p data-calsys-recurrence-field hidden data-calsys-end-date><label><?php esc_html_e('Repeat through', 'community-calendar-study-rooms'); ?><input name="recurrence_until" type="date"></label></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!$staff_booking && ($booking_rules['min_notice_hours'] > 0 || $booking_rules['time_increment'] > 0)) : ?>
                <p class="description">
                    <?php
                    $booking_guidance = array();
                    if ($booking_rules['min_notice_hours'] > 0) {
                        $booking_guidance[] = sprintf(__('Please book at least %d hours ahead.', 'community-calendar-study-rooms'), $booking_rules['min_notice_hours']);
                    }
                    if ($booking_rules['time_increment'] > 0) {
                        $booking_guidance[] = sprintf(__('Start and end times must use %d-minute increments.', 'community-calendar-study-rooms'), $booking_rules['time_increment']);
                    }
                    echo esc_html(implode(' ', $booking_guidance));
                    ?>
                </p>
            <?php endif; ?>
            <?php if (!$staff_booking) : ?><p class="calsys-privacy-note"><?php esc_html_e('We use your contact details to process your room request.', 'community-calendar-study-rooms'); ?><?php if (get_privacy_policy_url()) : ?> <a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php esc_html_e('Read our privacy policy.', 'community-calendar-study-rooms'); ?></a><?php endif; ?></p><?php endif; ?>
            <div class="calsys-action-buttons">
                <button type="submit" data-calsys-save-room><?php echo $staff_booking ? esc_html__('Confirm room booking', 'community-calendar-study-rooms') : esc_html__('Send request', 'community-calendar-study-rooms'); ?></button>
                <button class="calsys-button-secondary" data-calsys-reset-room type="button"><?php esc_html_e('Clear form', 'community-calendar-study-rooms'); ?></button>
                <button class="calsys-button-secondary" data-calsys-close-room type="button"><?php esc_html_e('Cancel', 'community-calendar-study-rooms'); ?></button>
            </div>
        </form>
        <?php if ($staff_booking) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-delete-form" data-calsys-delete-room-booking-form hidden>
                <?php wp_nonce_field('calsys_front_delete_room_booking'); ?>
                <input type="hidden" name="action" value="calsys_front_delete_room_booking">
                <input type="hidden" name="booking_id" value="0">
                <input type="hidden" name="calsys_frontend" value="1">
                <button class="calsys-delete-button" type="submit"><?php esc_html_e('Delete room booking', 'community-calendar-study-rooms'); ?></button>
            </form>
        <?php endif; ?>
    </section>
    </dialog>
    <?php
    return ob_get_clean();
}
