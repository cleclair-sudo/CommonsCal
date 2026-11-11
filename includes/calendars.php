<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Redirects a staff user to a plugin admin page with a result notice. */
function calsys_admin_redirect($page, $notice, $extra_args = array()) {
    wp_safe_redirect(add_query_arg(
        array_merge(array('page' => $page, 'calsys_notice' => $notice), $extra_args),
        admin_url('admin.php')
    ));
    exit;
}

/** Sends an action result to the frontend or the corresponding admin screen. */
function calsys_action_redirect($frontend, $page, $notice, $skipped = 0) {
    $extra_args = $skipped ? array('calsys_skipped' => absint($skipped)) : array();
    if ($frontend) {
        calsys_frontend_redirect($notice, $extra_args);
    }
    calsys_admin_redirect($page, $notice, $extra_args);
}

/** Returns the visitor to the referring page with a public result notice. */
function calsys_frontend_redirect($notice, $extra_args = array()) {
    $referer = wp_get_referer();
    $destination = wp_validate_redirect($referer, home_url('/'));
    wp_safe_redirect(add_query_arg(array_merge(array('calsys_notice' => $notice), $extra_args), $destination));
    exit;
}

/** Renders a translated result notice from a validated query parameter. */
function calsys_public_notice() {
    $key = sanitize_key(wp_unslash($_GET['calsys_notice'] ?? ''));
    $registration_settings = calsys_get_settings()['event_registration'];
    $messages = array(
        'registration_complete' => $registration_settings['confirmation_message'],
        'event_deleted' => __('Event deleted.', 'community-calendar-study-rooms'),
        'event_deleted_email_failed' => __('The event was deleted, but one or more cancellation emails failed. Check the site error log.', 'community-calendar-study-rooms'),
        'staff_event_deleted' => __('Staff calendar entry deleted.', 'community-calendar-study-rooms'),
        'room_booking_deleted' => __('Room booking deleted.', 'community-calendar-study-rooms'),
        'room_cancel_email_failed' => __('The room booking was cancelled, but the requester email failed. Check the site error log.', 'community-calendar-study-rooms'),
        'room_status_email_failed' => __('The room request was updated, but the requester email failed. Check the site error log.', 'community-calendar-study-rooms'),
        'registration_disabled' => $registration_settings['disabled_message'],
        'registration_closed' => $registration_settings['closed_message'],
        'already_registered' => __('That email address is already registered or on the waitlist for this event.', 'community-calendar-study-rooms'),
        'waitlist_joined' => __('You have joined the waitlist. We will email you if a place becomes available.', 'community-calendar-study-rooms'),
        'waitlist_joined_email_failed' => __('You joined the waitlist, but we could not send your confirmation email. Please contact staff.', 'community-calendar-study-rooms'),
        'saved_waitlist_error' => __('The event was saved, but its waitlist could not be updated. Check the site error log.', 'community-calendar-study-rooms'),
        'saved_waitlist_email_failed' => __('The event was saved and waitlist places were assigned, but one or more notification emails failed. Check the site error log.', 'community-calendar-study-rooms'),
        'event_full' => $registration_settings['full_message'],
        'invalid_registration' => __('Please check the registration details and try again.', 'community-calendar-study-rooms'),
        'room_request_sent' => __('Your room request has been sent to staff for approval.', 'community-calendar-study-rooms'),
        'room_booking_saved' => __('The room booking has been added to the calendar.', 'community-calendar-study-rooms'),
        'room_conflict' => __('That room and time are already requested or booked. Please choose another time.', 'community-calendar-study-rooms'),
        'outside_hours' => __('The requested time is outside opening hours or on a closed day.', 'community-calendar-study-rooms'),
        'duration_limit' => __('That request exceeds the maximum room booking duration. Please choose a shorter time.', 'community-calendar-study-rooms'),
        'room_booking_saved_outside_hours' => __('Staff booking confirmed, but it is outside opening hours.', 'community-calendar-study-rooms'),
        'invalid_room_request' => __('Please check the room request details and try again.', 'community-calendar-study-rooms'),
        'equipment_conflict' => __('The requested equipment is unavailable for that time or quantity. Please choose another time or quantity.', 'community-calendar-study-rooms'),
        'invalid' => __('Please check the submitted information and try again.', 'community-calendar-study-rooms'),
        'saved' => __('Event saved.', 'community-calendar-study-rooms'),
        'saved_room_conflicts' => '',
        'staff_event_saved' => __('Staff calendar entry saved.', 'community-calendar-study-rooms'),
        'staff_event_saved_room_conflicts' => '',
        'approved' => __('Room request approved.', 'community-calendar-study-rooms'),
        'denied' => __('Room request denied.', 'community-calendar-study-rooms'),
    );
    if (!isset($messages[$key])) {
        return '';
    }
    $message = $messages[$key];
    if ($key === 'saved_room_conflicts') {
        $skipped = absint($_GET['calsys_skipped'] ?? 0);
        $message = sprintf(_n('Event saved. Room reservation was skipped for %d conflicting occurrence.', 'Event saved. Room reservations were skipped for %d conflicting occurrences.', $skipped, 'community-calendar-study-rooms'), $skipped);
    } elseif ($key === 'staff_event_saved_room_conflicts') {
        $skipped = absint($_GET['calsys_skipped'] ?? 0);
        $message = sprintf(_n('Staff entry saved. Room reservation was skipped for %d conflicting day.', 'Staff entry saved. Room reservations were skipped for %d conflicting days.', $skipped, 'community-calendar-study-rooms'), $skipped);
    }
    return '<div class="calsys-notice" role="status">' . esc_html($message) . '</div>';
}

/** Renders a short privacy disclosure with a link to the site's policy when set. */
function calsys_privacy_note($message) {
    $html = esc_html($message);
    $policy_url = get_privacy_policy_url();
    if ($policy_url) {
        $html .= ' ' . sprintf(
            '<a href="%s">%s</a>',
            esc_url($policy_url),
            esc_html__('Read our privacy policy.', 'community-calendar-study-rooms')
        );
    }
    return '<p class="calsys-privacy-note">' . $html . '</p>';
}

/** Parses the requested calendar month and falls back to the configured default. */
function calsys_calendar_month() {
    $month = sanitize_text_field(wp_unslash($_GET['calsys_month'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = current_time('Y-m');
        if (calsys_get_settings()['calendar_display']['initial_month'] === 'next') {
            $month = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', wp_timezone())
                ->modify('+1 month')->format('Y-m');
        }
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m') !== $month) {
        return DateTimeImmutable::createFromFormat('!Y-m-d', current_time('Y-m') . '-01', wp_timezone());
    }
    return $date;
}

/** Selects the public calendar view and its reference date. */
function calsys_calendar_view_state($month) {
    $view = isset($_GET['calsys_view']) && is_string($_GET['calsys_view']) ? sanitize_key(wp_unslash($_GET['calsys_view'])) : 'month';
    if (!in_array($view, array('day', 'week', 'month'), true)) {
        $view = 'month';
    }
    $raw_date = isset($_GET['calsys_date']) && is_string($_GET['calsys_date']) ? wp_unslash($_GET['calsys_date']) : '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw_date, wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $raw_date) {
        $date = $month;
        if ($month->format('Y-m') === current_time('Y-m')) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', current_time('Y-m-d'), wp_timezone());
        }
    }
    return array($view, $date);
}

/** Returns the inclusive start and exclusive end of a calendar view. */
function calsys_calendar_range($month, $view, $date) {
    $start = ($view === 'month' ? $month : $date)->setTime(0, 0, 0);
    if ($view !== 'day') {
        $offset = ((int) $start->format('w') - calsys_week_start_day() + 7) % 7;
        $start = $start->modify('-' . $offset . ' days');
    }
    $days = $view === 'day' ? 1 : ($view === 'week' ? 7 : 42);
    return array($start, $start->modify('+' . $days . ' days'));
}

/** Preserves unrelated query parameters when a GET filter form is submitted. */
function calsys_calendar_hidden_query_fields($values, $prefix = '') {
    $html = '';
    foreach ($values as $key => $value) {
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            $html .= calsys_calendar_hidden_query_fields($value, $name);
        } elseif (is_scalar($value)) {
            $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        }
    }
    return $html;
}

/** Renders an accessible six-week calendar grid with entries and staff controls. */
function calsys_render_calendar($title, $events, $month, $calendar_type, $view = 'month', $selected_date = null, $enable_views = false) {
    global $wpdb;
    $timezone = wp_timezone();
    $has_active_rooms = !in_array($calendar_type, array('rooms', 'combined'), true) ||
        (bool) $wpdb->get_var("SELECT id FROM " . calsys_table('rooms') . " WHERE active = 1 LIMIT 1");
    $selected_date = $selected_date ?? $month;
    list($grid_start, $range_end) = calsys_calendar_range($month, $view, $selected_date);
    $grid_end = $range_end->modify('-1 second');
    $columns = $view === 'day' ? 1 : 7;
    $rows = $view === 'month' ? 6 : 1;
    $events_by_date = array();
    $equipment_by_entry = array();

    foreach ($events as $event) {
        $starts_at = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $event->starts_at, $timezone);
        $ends_at = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $event->ends_at, $timezone);
        if (!$starts_at || !$ends_at || $ends_at < $grid_start || $starts_at > $grid_end) {
            continue;
        }
        $type = $event->calendar_type ?? 'event';
        if (current_user_can('manage_calendar_system') && in_array($type, array('event', 'staff', 'room'), true)) {
            $key = $type . ':' . $event->id;
            if (!isset($equipment_by_entry[$key])) {
                $equipment_by_entry[$key] = calsys_equipment_for_source($type, $event->id);
                if ($equipment_by_entry[$key] === false) {
                    return '<div class="calsys-notice" role="status">' .
                        esc_html__('Calendar equipment could not be loaded. Please contact the site administrator.', 'community-calendar-study-rooms') . '</div>';
                }
            }
        }

        $first_day = $starts_at->setTime(0, 0, 0);
        $last_day = $ends_at->setTime(0, 0, 0);
        if (!$event->all_day && $ends_at->format('H:i:s') === '00:00:00' && $ends_at > $starts_at) {
            $last_day = $last_day->modify('-1 day');
        }
        if ($first_day < $grid_start) {
            $first_day = $grid_start;
        }
        if ($last_day > $grid_end) {
            $last_day = $grid_end;
        }

        for ($day = $first_day; $day <= $last_day; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $events_by_date[$key][] = array(
                'event' => $event,
                'starts_at' => $starts_at,
                'ends_at' => $ends_at,
            );
        }
    }

    $previous_date = $view === 'month' ? $month->modify('-1 month') : $selected_date->modify($view === 'week' ? '-7 days' : '-1 day');
    $next_date = $view === 'month' ? $month->modify('+1 month') : $selected_date->modify($view === 'week' ? '+7 days' : '+1 day');
    $calendar_url = remove_query_arg('calsys_notice');
    $previous_args = array('calsys_month' => $previous_date->format('Y-m'));
    $next_args = array('calsys_month' => $next_date->format('Y-m'));
    if ($enable_views) {
        $previous_args += array('calsys_view' => $view, 'calsys_date' => $previous_date->format('Y-m-d'));
        $next_args += array('calsys_view' => $view, 'calsys_date' => $next_date->format('Y-m-d'));
    }
    $period_label = $view === 'day'
        ? wp_date(get_option('date_format'), $selected_date->getTimestamp(), $timezone)
        : ($view === 'week'
            ? sprintf(__('%1$s – %2$s', 'community-calendar-study-rooms'), wp_date('M j', $grid_start->getTimestamp(), $timezone), wp_date('M j, Y', $grid_end->getTimestamp(), $timezone))
            : wp_date('F Y', $month->getTimestamp(), $timezone));
    $week_start = DateTimeImmutable::createFromFormat('!Y-m-d', '2024-01-07', $timezone);
    $week_start = $week_start->modify('+' . calsys_week_start_day() . ' days');
    ob_start();
    ?>
    <section class="calsys-calendar calsys-calendar-<?php echo esc_attr($calendar_type); ?><?php echo $enable_views ? ' calsys-public-calendar calsys-view-' . esc_attr($view) : ''; ?>" aria-label="<?php echo esc_attr($title); ?>">
        <div class="calsys-calendar-heading">
            <h2><?php echo esc_html($title); ?></h2>
            <nav class="calsys-calendar-navigation" aria-label="<?php esc_attr_e('Calendar navigation', 'community-calendar-study-rooms'); ?>">
                <a href="<?php echo esc_url(add_query_arg($previous_args, $calendar_url)); ?>" aria-label="<?php esc_attr_e('Previous period', 'community-calendar-study-rooms'); ?>"><?php esc_html_e('Previous', 'community-calendar-study-rooms'); ?></a>
                <span aria-live="polite"><?php echo esc_html($period_label); ?></span>
                <a href="<?php echo esc_url(add_query_arg($next_args, $calendar_url)); ?>" aria-label="<?php esc_attr_e('Next period', 'community-calendar-study-rooms'); ?>"><?php esc_html_e('Next', 'community-calendar-study-rooms'); ?></a>
            </nav>
        </div>
        <?php if (in_array($calendar_type, array('events', 'combined'), true)) :
            $selected_categories = calsys_selected_event_categories();
            $categories = calsys_event_categories();
            $filter_url = remove_query_arg(array('calsys_notice', 'calsys_month', 'calsys_date', 'calsys_view', 'calsys_category'));
            $clear_url = remove_query_arg(array('calsys_notice', 'calsys_category'));
            $filter_query = array();
            parse_str((string) wp_parse_url($filter_url, PHP_URL_QUERY), $filter_query);
            ?>
            <form class="calsys-category-filter" method="get" action="<?php echo esc_url($filter_url); ?>" data-calsys-auto-submit>
                <?php echo calsys_calendar_hidden_query_fields($filter_query); ?>
                <?php if ($enable_views) : ?>
                    <input type="hidden" name="calsys_month" value="<?php echo esc_attr($month->format('Y-m')); ?>">
                    <input type="hidden" name="calsys_date" value="<?php echo esc_attr($selected_date->format('Y-m-d')); ?>">
                    <input type="hidden" name="calsys_view" value="<?php echo esc_attr($view); ?>">
                <?php elseif (!empty($_GET['calsys_month'])) : ?><input type="hidden" name="calsys_month" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['calsys_month']))); ?>"><?php endif; ?>
                <fieldset class="calsys-category-filter-options">
                    <legend><?php esc_html_e('Filter by category', 'community-calendar-study-rooms'); ?></legend>
                    <p class="calsys-category-filter-help"><?php esc_html_e('Choose one or more categories. Leave them all unchecked to show every event.', 'community-calendar-study-rooms'); ?></p>
                    <div class="calsys-category-checkboxes">
                        <?php foreach ($categories as $key => $label) : ?>
                            <label class="calsys-category-choice">
                                <input type="checkbox" name="calsys_category[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $selected_categories, true)); ?>>
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="calsys-category-filter-actions">
                    <button type="submit"><?php esc_html_e('Apply filters', 'community-calendar-study-rooms'); ?></button>
                    <?php if ($selected_categories) : ?><a href="<?php echo esc_url($clear_url); ?>"><?php esc_html_e('Clear filters', 'community-calendar-study-rooms'); ?></a><?php endif; ?>
                </div>
                <?php if ($enable_views) : ?>
                    <nav class="calsys-view-switcher" aria-label="<?php esc_attr_e('Calendar view', 'community-calendar-study-rooms'); ?>">
                        <span><?php esc_html_e('View', 'community-calendar-study-rooms'); ?></span>
                        <div class="calsys-view-options">
                        <?php foreach (array('day' => __('Day', 'community-calendar-study-rooms'), 'week' => __('Week', 'community-calendar-study-rooms'), 'month' => __('Month', 'community-calendar-study-rooms')) as $view_key => $view_label) : ?>
                            <a href="<?php echo esc_url(add_query_arg(array('calsys_view' => $view_key, 'calsys_date' => $selected_date->format('Y-m-d'), 'calsys_month' => $selected_date->format('Y-m')), $calendar_url)); ?>"<?php if ($view === $view_key) : ?> aria-current="page"<?php endif; ?>><?php echo esc_html($view_label); ?></a>
                        <?php endforeach; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        <div class="calsys-calendar-scroll">
            <table class="calsys-calendar-table" role="table">
                <caption class="screen-reader-text"><?php echo esc_html(sprintf(__('%1$s for %2$s', 'community-calendar-study-rooms'), $title, $period_label)); ?></caption>
                <thead><tr>
                <?php for ($column = 0; $column < $columns; $column++) : $weekday = $view === 'day' ? $selected_date : $week_start->modify('+' . $column . ' days'); ?>
                    <th scope="col"><span class="calsys-weekday-short"><?php echo esc_html(wp_date('D', $weekday->getTimestamp(), $timezone)); ?></span><span class="calsys-weekday-long"><?php echo esc_html(wp_date('l', $weekday->getTimestamp(), $timezone)); ?></span></th>
                <?php endfor; ?>
                </tr></thead>
                <tbody>
                <?php for ($week = 0; $week < $rows; $week++) : ?>
                    <tr role="row">
                    <?php for ($column = 0; $column < $columns; $column++) :
                        $date = $grid_start->modify('+' . (($week * $columns) + $column) . ' days');
                        $date_key = $date->format('Y-m-d');
                        $classes = array('calsys-calendar-day');
                        if ($date->format('Y-m') !== $month->format('Y-m')) {
                            $classes[] = 'is-outside-month';
                        }
                        if ($date_key === current_time('Y-m-d')) {
                            $classes[] = 'is-today';
                        }
                        ?>
                        <td role="cell" class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                            <time class="calsys-calendar-date" datetime="<?php echo esc_attr($date_key); ?>"><span class="calsys-date-compact"><?php echo esc_html($date->format('j')); ?></span><span class="calsys-date-mobile"><?php echo esc_html(wp_date('l, M j', $date->getTimestamp(), $timezone)); ?></span></time>
                            <?php if (in_array($calendar_type, array('rooms', 'combined'), true) && $has_active_rooms && $date_key >= current_time('Y-m-d')) : ?>
                                <button class="calsys-day-action" type="button" data-calsys-add-room data-date="<?php echo esc_attr($date_key); ?>"><?php echo esc_html(current_user_can('manage_calendar_system') ? __('Add', 'community-calendar-study-rooms') : __('Request', 'community-calendar-study-rooms')); ?></button>
                            <?php endif; ?>
                            <?php if ($calendar_type === 'staff' && current_user_can('manage_calendar_system')) : ?>
                                <button class="calsys-day-action" type="button" data-calsys-add-staff data-date="<?php echo esc_attr($date_key); ?>"><?php esc_html_e('Add', 'community-calendar-study-rooms'); ?></button>
                            <?php endif; ?>
                            <?php if (!empty($events_by_date[$date_key])) : ?>
                                <ul class="calsys-calendar-entries">
                                <?php foreach ($events_by_date[$date_key] as $entry) :
                                    $event = $entry['event'];
                                    $event_type = sanitize_html_class($event->calendar_type ?? 'event');
                                    if (!empty($event->entry_type)) {
                                        $event_type .= '-' . sanitize_html_class($event->entry_type);
                                    }
                                    $start_label = $entry['starts_at']->format('Y-m-d') === $date_key
                                        ? wp_date(get_option('time_format'), $entry['starts_at']->getTimestamp(), $timezone)
                                        : '';
                                    $end_label = $entry['ends_at']->format('Y-m-d') === $date_key && $entry['ends_at']->format('H:i:s') !== '00:00:00'
                                        ? wp_date(get_option('time_format'), $entry['ends_at']->getTimestamp(), $timezone)
                                        : '';
                                    if (!empty($event->all_day)) {
                                        $time_label = __('All day', 'community-calendar-study-rooms');
                                    } elseif ($start_label && $end_label) {
                                        $time_label = $start_label . ' - ' . $end_label;
                                    } elseif ($start_label) {
                                        $time_label = $start_label;
                                    } elseif ($end_label) {
                                        $time_label = sprintf(__('Until %s', 'community-calendar-study-rooms'), $end_label);
                                    } else {
                                        $time_label = __('Continues', 'community-calendar-study-rooms');
                                    }
                                    $detail = $event->calendar_detail ?? '';
                                    $entry_color = calsys_calendar_entry_color($event->calendar_type ?? 'event', $event->entry_type ?? '', $event->room_color ?? '');
                                    ?>
                                    <li class="calsys-calendar-entry is-<?php echo esc_attr($event_type); ?>" style="<?php echo esc_attr('--calsys-entry-color: ' . $entry_color); ?>">
                                        <span class="calsys-calendar-entry-time"><?php echo esc_html($time_label); ?></span>
                                        <?php if (($event->calendar_type ?? '') === 'event') : ?>
                                            <button class="calsys-calendar-entry-title calsys-event-title-link" type="button" data-calsys-open-event-details="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></button>
                                        <?php else : ?>
                                            <span class="calsys-calendar-entry-title"><?php echo esc_html($event->title); ?></span>
                                        <?php endif; ?>
                                        <?php if ($detail !== '') : ?>
                                            <span class="calsys-calendar-entry-detail"><?php echo esc_html($detail); ?></span>
                                        <?php endif; ?>
                                        <?php if (($event->calendar_type ?? '') === 'event' && !empty($event->category_label) && calsys_show_public_category_labels()) : ?>
                                            <span class="calsys-calendar-entry-category"><?php echo esc_html($event->category_label); ?></span>
                                        <?php endif; ?>
                                        <?php if (current_user_can('manage_calendar_system') && ($event->calendar_type ?? '') === 'event') : ?>
                                            <button class="calsys-entry-action" type="button" data-calsys-edit-event
                                                data-event-id="<?php echo esc_attr($event->id); ?>"
                                                data-recurrence-group="<?php echo esc_attr($event->recurrence_group ?? ''); ?>"
                                                data-title="<?php echo esc_attr($event->title); ?>"
                                                data-description="<?php echo esc_attr($event->description ?? ''); ?>"
                                                data-image-id="<?php echo esc_attr($event->image_id ?? 0); ?>"
                                                data-equipment="<?php echo esc_attr(wp_json_encode($equipment_by_entry['event:' . $event->id])); ?>"
                                                data-image-url="<?php echo esc_url($event->image_id ? wp_get_attachment_image_url($event->image_id, 'thumbnail') : ''); ?>"
                                                data-image-alt="<?php echo esc_attr($event->image_id ? get_post_meta($event->image_id, '_wp_attachment_image_alt', true) : ''); ?>"
                                                data-start="<?php echo esc_attr(str_replace(' ', 'T', substr($event->starts_at, 0, 16))); ?>"
                                                data-end="<?php echo esc_attr(str_replace(' ', 'T', substr($event->ends_at, 0, 16))); ?>"
                                                data-capacity="<?php echo esc_attr($event->capacity ?? 0); ?>"
                                                data-show-registration-counts="<?php echo esc_attr(!empty($event->show_registration_counts) ? '1' : '0'); ?>"
                                                data-category="<?php echo esc_attr($event->category ?? 'adult'); ?>"
                                                data-registration-fields="<?php echo esc_attr(wp_json_encode(calsys_decode_registration_fields($event->registration_fields ?? ''))); ?>"
                                                data-registration-enabled="<?php echo esc_attr(!empty($event->registration_enabled) ? '1' : '0'); ?>"
                                                data-registration-recipients="<?php echo esc_attr(wp_json_encode(calsys_decode_registration_recipients($event->registration_recipients ?? ''))); ?>"
                                                data-room-id="<?php echo esc_attr($event->room_id ?? 0); ?>"
                                                data-status="<?php echo esc_attr($event->status ?? 'published'); ?>"><?php esc_html_e('Edit', 'community-calendar-study-rooms'); ?></button>
                                        <?php elseif (current_user_can('manage_calendar_system') && ($event->calendar_type ?? '') === 'staff') : ?>
                                            <button class="calsys-entry-action" type="button" data-calsys-edit-staff
                                                data-entry-id="<?php echo esc_attr($event->id); ?>"
                                                data-equipment="<?php echo esc_attr(wp_json_encode($equipment_by_entry['staff:' . $event->id])); ?>"
                                                data-title="<?php echo esc_attr($event->title); ?>"
                                                data-entry-type="<?php echo esc_attr($event->entry_type ?? 'other'); ?>"
                                                data-room-id="<?php echo esc_attr($event->room_id ?? 0); ?>"
                                                data-start="<?php echo esc_attr(str_replace(' ', 'T', substr($event->starts_at, 0, 16))); ?>"
                                                data-end="<?php echo esc_attr(str_replace(' ', 'T', substr($event->ends_at, 0, 16))); ?>"
                                                data-all-day="<?php echo esc_attr(!empty($event->all_day) ? '1' : '0'); ?>"><?php esc_html_e('Edit', 'community-calendar-study-rooms'); ?></button>
                                        <?php elseif (current_user_can('manage_calendar_system') && ($event->calendar_type ?? '') === 'room' && ($event->status ?? '') === 'pending') : ?>
                                            <?php foreach (array('approve' => __('Approve', 'community-calendar-study-rooms'), 'deny' => __('Deny', 'community-calendar-study-rooms')) as $decision => $label) : ?>
                                                <form class="calsys-entry-decision" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                                    <?php wp_nonce_field('calsys_front_booking_decision_' . $event->id); ?>
                                                    <input type="hidden" name="action" value="calsys_booking_decision">
                                                    <input type="hidden" name="calsys_frontend" value="1">
                                                    <input type="hidden" name="booking_id" value="<?php echo esc_attr($event->id); ?>">
                                                    <input type="hidden" name="decision" value="<?php echo esc_attr($decision); ?>">
                                                    <button class="calsys-entry-action" type="submit"><?php echo esc_html($label); ?></button>
                                                </form>
                                            <?php endforeach; ?>
                                            <button class="calsys-entry-action" type="button" data-calsys-edit-room
                                                data-booking-id="<?php echo esc_attr($event->id); ?>"
                                                data-equipment="<?php echo esc_attr(wp_json_encode($equipment_by_entry['room:' . $event->id])); ?>"
                                                data-room-id="<?php echo esc_attr($event->room_id ?? 0); ?>"
                                                data-date="<?php echo esc_attr(substr($event->starts_at, 0, 10)); ?>"
                                                data-start="<?php echo esc_attr(substr($event->starts_at, 11, 5)); ?>"
                                                data-end="<?php echo esc_attr(substr($event->ends_at, 11, 5)); ?>"
                                                data-name="<?php echo esc_attr($event->requester_name ?? ''); ?>"
                                                data-email="<?php echo esc_attr($event->requester_email ?? ''); ?>"><?php esc_html_e('Edit', 'community-calendar-study-rooms'); ?></button>
                                        <?php elseif (current_user_can('manage_calendar_system') && ($event->calendar_type ?? '') === 'room') : ?>
                                            <button class="calsys-entry-action" type="button" data-calsys-edit-room
                                                data-booking-id="<?php echo esc_attr($event->id); ?>"
                                                data-equipment="<?php echo esc_attr(wp_json_encode($equipment_by_entry['room:' . $event->id])); ?>"
                                                data-room-id="<?php echo esc_attr($event->room_id ?? 0); ?>"
                                                data-date="<?php echo esc_attr(substr($event->starts_at, 0, 10)); ?>"
                                                data-start="<?php echo esc_attr(substr($event->starts_at, 11, 5)); ?>"
                                                data-end="<?php echo esc_attr(substr($event->ends_at, 11, 5)); ?>"
                                                data-name="<?php echo esc_attr($event->requester_name ?? ''); ?>"
                                                data-email="<?php echo esc_attr($event->requester_email ?? ''); ?>"><?php esc_html_e('Edit', 'community-calendar-study-rooms'); ?></button>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/** Returns valid, unique event categories selected in the current request. */
function calsys_selected_event_categories() {
    $raw_selected = wp_unslash($_GET['calsys_category'] ?? array());
    $categories = calsys_event_categories();
    if (!is_array($raw_selected)) {
        $raw_selected = array($raw_selected);
    }
    $selected = array();
    foreach ($raw_selected as $value) {
        if (!is_string($value)) {
            continue;
        }
        $key = sanitize_key($value);
        if (isset($categories[$key]) && !in_array($key, $selected, true)) {
            $selected[] = $key;
        }
    }
    return $selected;
}

/** Builds a prepared SQL fragment limiting results to selected event categories. */
function calsys_event_category_sql($column = 'e.category') {
    global $wpdb;
    $categories = calsys_selected_event_categories();
    if (!$categories) {
        return '';
    }
    $placeholders = implode(', ', array_fill(0, count($categories), '%s'));
    return $wpdb->prepare(" AND $column IN ($placeholders)", $categories);
}

/** Whether room-booking listings are hidden for the current visitor. */
function calsys_hide_guest_room_bookings() {
    return !is_user_logged_in() && !empty(calsys_get_settings()['privacy']['hide_guest_room_bookings']);
}

/** Loads visible room bookings for a calendar range without exposing patron data publicly. */
function calsys_room_calendar_entries($grid_start, $grid_end) {
    global $wpdb;
    if (calsys_hide_guest_room_bookings()) {
        return array();
    }
    $staff_access = current_user_can('manage_calendar_system');
    $staff_columns = $staff_access ? ', b.requester_name, b.requester_email' : '';
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.id, b.room_id, r.name AS room_name, r.color AS room_color, b.booking_date, b.starts_at, b.ends_at, b.status $staff_columns
        FROM " . calsys_table('bookings') . " b
        INNER JOIN " . calsys_table('rooms') . " r ON r.id = b.room_id
        WHERE b.status IN ('pending', 'approved')
        AND b.source_type <> 'event'
        " . ($staff_access ? '' : "AND b.source_type <> 'private'") . "
        AND b.booking_date >= %s AND b.booking_date < %s
        ORDER BY b.booking_date ASC, b.starts_at ASC",
        $grid_start->format('Y-m-d'),
        $grid_end->format('Y-m-d')
    ));
    if (!is_array($bookings)) {
        error_log('CommonsCal: unable to load room bookings for calendar: ' . $wpdb->last_error);
        return false;
    }

    $settings = calsys_get_settings();
    $entries = array();
    foreach ($bookings as $booking) {
        $entries[] = (object) array(
            'id' => $booking->id,
            'room_id' => $booking->room_id,
            'room_color' => $staff_access || $settings['privacy']['show_room_names'] ? $booking->room_color : '',
            'title' => $staff_access || $settings['privacy']['show_room_names']
                ? $booking->room_name
                : __('Reserved room', 'community-calendar-study-rooms'),
            'starts_at' => $booking->booking_date . ' ' . $booking->starts_at,
            'ends_at' => $booking->booking_date . ' ' . $booking->ends_at,
            'all_day' => 0,
            'calendar_type' => 'room',
            'status' => $booking->status,
            'requester_name' => $staff_access ? $booking->requester_name : '',
            'requester_email' => $staff_access ? $booking->requester_email : '',
            'calendar_detail' => !$staff_access && !$settings['privacy']['show_room_status']
                ? ''
                : ($booking->status === 'pending'
                    ? __('Request pending approval', 'community-calendar-study-rooms')
                    : __('Booked', 'community-calendar-study-rooms')),
        );
    }
    return $entries;
}

/** Builds an events-only, rooms-only, or combined public calendar and its dialogs. */
function calsys_event_calendar_view($dialog_events = array(), $calendar_mode = 'combined') {
    global $wpdb;
    if (!in_array($calendar_mode, array('events', 'rooms', 'combined'), true)) {
        $calendar_mode = 'combined';
    }
    $month = calsys_calendar_month();
    list($view, $selected_date) = calsys_calendar_view_state($month);
    $month = $selected_date->modify('first day of this month');
    list($grid_start, $grid_end) = calsys_calendar_range($month, $view, $selected_date);
    $events = array();
    if (in_array($calendar_mode, array('events', 'rooms', 'combined'), true)) {
        $category_sql = calsys_event_category_sql();
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.title, e.description, e.starts_at, e.ends_at, e.capacity, e.status, e.category, e.registration_fields,
            e.registration_enabled, e.registration_recipients, e.image_id, e.show_registration_counts, e.recurrence_group,
            (SELECT b.room_id FROM " . calsys_table('bookings') . " b WHERE b.source_type = 'event' AND b.source_id = e.id ORDER BY b.id ASC LIMIT 1) AS room_id,
            0 AS all_day, 'event' AS calendar_type,
            (SELECT COUNT(*) FROM " . calsys_table('registrations') . " r WHERE r.event_id = e.id) AS registration_count
            FROM " . calsys_table('events') . " e
            WHERE e.status = 'published' AND e.starts_at < %s AND e.ends_at >= %s $category_sql
            ORDER BY e.starts_at ASC",
            $grid_end->format('Y-m-d H:i:s'),
            $grid_start->format('Y-m-d H:i:s')
        ));
        if (!is_array($events)) {
            error_log('CommonsCal: unable to load calendar events: ' . $wpdb->last_error);
            return '<div class="calsys-calendar-widget calsys-calendar-widget-events">' .
                '<div class="calsys-notice" role="status">' .
                esc_html__('The event calendar is temporarily unavailable. Please contact the site administrator.', 'community-calendar-study-rooms') .
                '</div></div>';
        }
    }
    $room_entries = array();
    if (in_array($calendar_mode, array('rooms', 'combined'), true)) {
        $room_entries = calsys_room_calendar_entries($grid_start, $grid_end);
        if ($room_entries === false) {
            return '<div class="calsys-calendar-widget calsys-calendar-widget-events">' .
                '<div class="calsys-notice" role="status">' .
                esc_html__('The calendar is temporarily unavailable. Please contact the site administrator.', 'community-calendar-study-rooms') .
                '</div></div>';
        }
    }
    $calendar_entries = array_merge($events, $room_entries);
    foreach ($events as $event) {
        $categories = calsys_event_categories();
        $event->category_label = $categories[$event->category] ?? '';
        $event->calendar_detail = calsys_show_public_registration_counts($event)
            ? sprintf(
                _n('%d registered', '%d registered', (int) $event->registration_count, 'community-calendar-study-rooms'),
                (int) $event->registration_count
            )
            : '';
    }
    $all_dialog_events = array();
    $events_for_dialogs = in_array($calendar_mode, array('events', 'rooms', 'combined'), true)
        ? array_merge($events, is_array($dialog_events) ? $dialog_events : array())
        : array();
    foreach ($events_for_dialogs as $dialog_event) {
        $all_dialog_events[(int) $dialog_event->id] = $dialog_event;
    }
    $output = '<div class="calsys-calendar-widget calsys-calendar-widget-' . esc_attr($calendar_mode) . '">';
    $output .= calsys_public_notice();
    $output .= calsys_staff_access_link($calendar_mode === 'rooms' ? 'room' : ($calendar_mode === 'events' ? 'events' : 'combined'));
    $calendar_title = $calendar_mode === 'events' || calsys_hide_guest_room_bookings()
        ? __('Events', 'community-calendar-study-rooms')
        : ($calendar_mode === 'rooms'
            ? __('Room bookings and upcoming events', 'community-calendar-study-rooms')
            : __('Events and room bookings', 'community-calendar-study-rooms'));
    $render_mode = $calendar_mode === 'rooms' ? 'combined' : $calendar_mode;
    $output .= calsys_render_calendar($calendar_title, $calendar_entries, $month, $render_mode, $view, $selected_date, true);
    $output .= '<p><a href="' . esc_url(add_query_arg('calsys_ical', 'events', home_url('/'))) . '">' .
        esc_html__('Subscribe to published events (calendar URL)', 'community-calendar-study-rooms') . '</a></p>';
    $output .= calsys_render_event_registration_forms(array_values($all_dialog_events));
    if (current_user_can('manage_calendar_system')) {
        $output .= calsys_front_event_form();
    }
    if (in_array($calendar_mode, array('rooms', 'combined'), true)) {
        $output .= calsys_front_room_form();
    }
    return $output . '</div>';
}

/** WordPress shortcode callback for the unified event and room calendar. */
function calsys_event_calendar_shortcode() {
    return calsys_event_calendar_view();
}
add_shortcode('calendar_event_calendar', 'calsys_event_calendar_shortcode');

/** Renders only public events with their event detail and registration dialogs. */
function calsys_events_calendar_shortcode() {
    return calsys_event_calendar_view(array(), 'events');
}
add_shortcode('calendar_events', 'calsys_events_calendar_shortcode');

/** Renders public room bookings without event entries. */
function calsys_rooms_calendar_shortcode() {
    return calsys_event_calendar_view(array(), 'rooms');
}
add_shortcode('calendar_rooms', 'calsys_rooms_calendar_shortcode');

/** Renders the combined events and public room bookings calendar. */
function calsys_combined_calendar_shortcode() {
    return calsys_event_calendar_view(array(), 'combined');
}
add_shortcode('calendar_combined', 'calsys_combined_calendar_shortcode');

/** Renders a compact list of three to five upcoming event cards. */
function calsys_upcoming_events_shortcode($attributes = array()) {
    global $wpdb;
    $attributes = shortcode_atts(array('count' => 3), $attributes, 'calendar_upcoming_events');
    $count = absint($attributes['count']);
    $count = min(5, max(3, $count ?: 3));
    $category_sql = calsys_event_category_sql();
    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT e.*, (SELECT COUNT(*) FROM " . calsys_table('registrations') . " r WHERE r.event_id = e.id) AS registration_count
        FROM " . calsys_table('events') . " e
        WHERE e.status = %s AND e.starts_at > %s $category_sql
        ORDER BY e.starts_at ASC LIMIT %d",
        'published',
        current_time('mysql'),
        $count
    ));
    if (!is_array($events)) {
        error_log('CommonsCal: unable to load upcoming event cards: ' . $wpdb->last_error);
        return '<div class="calsys-upcoming-events"><div class="calsys-notice" role="status">' .
            esc_html__('Upcoming events are temporarily unavailable. Please contact the site administrator.', 'community-calendar-study-rooms') .
            '</div></div>';
    }
    if (!$events) {
        return '<div class="calsys-upcoming-events">' . calsys_public_notice() . '<p>' .
            esc_html__('There are no upcoming events right now.', 'community-calendar-study-rooms') .
            '</p></div>';
    }

    ob_start();
    ?>
    <div class="calsys-upcoming-events">
        <?php echo calsys_public_notice(); ?>
        <div class="calsys-upcoming-events-grid">
            <?php foreach ($events as $event) : ?>
                <article class="calsys-upcoming-event-card">
                    <?php if (!empty($event->image_id)) : ?>
                        <?php echo wp_get_attachment_image((int) $event->image_id, 'medium', false, array('class' => 'calsys-upcoming-event-image', 'loading' => 'lazy')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                    <div class="calsys-upcoming-event-content">
                        <p class="calsys-upcoming-event-date"><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)); ?></p>
                        <h3><button type="button" class="calsys-event-title-link" data-calsys-open-event-details="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></button></h3>
                        <?php if (!empty($event->description)) : ?>
                            <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($event->description), 18)); ?></p>
                        <?php endif; ?>
                        <button class="calsys-upcoming-event-action" type="button" data-calsys-open-event-details="<?php echo esc_attr($event->id); ?>"><?php esc_html_e('Event details', 'community-calendar-study-rooms'); ?></button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php echo calsys_render_event_registration_forms($events); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('calendar_upcoming_events', 'calsys_upcoming_events_shortcode');

/** Renders configured custom fields in a public event registration form. */
function calsys_registration_form_fields($fields) {
    if (!$fields) {
        return '';
    }
    ob_start();
    ?>
    <div class="calsys-registration-custom-fields">
        <?php foreach (array_values($fields) as $index => $field) :
            $name = 'registration_fields[' . $index . ']';
            $required = !empty($field['required']);
            ?>
            <p class="calsys-form-wide">
                <?php if ($field['type'] === 'checkbox') : ?>
                    <label class="calsys-checkbox-label"><input name="<?php echo esc_attr($name); ?>" type="checkbox" value="1" <?php if ($required) : ?>required<?php endif; ?>> <?php echo esc_html($field['label']); ?></label>
                <?php else : ?>
                    <label><?php echo esc_html($field['label']); ?>
                <?php if ($field['type'] === 'textarea') : ?>
                    <textarea name="<?php echo esc_attr($name); ?>" rows="3" maxlength="1000" <?php if ($required) : ?>required<?php endif; ?>></textarea>
                <?php elseif ($field['type'] === 'select') : ?>
                    <select name="<?php echo esc_attr($name); ?>" <?php if ($required) : ?>required<?php endif; ?>>
                        <option value=""><?php esc_html_e('Choose an option', 'community-calendar-study-rooms'); ?></option>
                        <?php foreach ($field['options'] as $option) : ?><option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option><?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input name="<?php echo esc_attr($name); ?>" type="<?php echo esc_attr($field['type'] === 'email' ? 'email' : ($field['type'] === 'phone' ? 'tel' : 'text')); ?>" maxlength="500" <?php if ($required) : ?>required<?php endif; ?>>
                <?php endif; ?>
                    </label>
                <?php endif; ?>
            </p>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** Renders event detail dialogs and registration forms for calendar events. */
function calsys_render_event_registration_forms($events) {
    $registration_settings = calsys_get_settings()['event_registration'];
    ob_start();
    foreach ($events as $event) {
        $capacity = calsys_event_capacity($event->capacity);
        $full = $capacity > 0 && (int) $event->registration_count >= $capacity;
        $registration_available = !empty($event->registration_enabled) && !empty($registration_settings['enabled']);
        echo '<dialog class="calsys-modal calsys-registration-dialog" data-calsys-registration-dialog="' . esc_attr($event->id) . '" aria-label="' . esc_attr(sprintf(__('Details for %s', 'community-calendar-study-rooms'), $event->title)) . '">';
        echo '<section class="calsys-action-card calsys-event-registration">';
        echo '<div class="calsys-action-card-heading"><div class="calsys-dialog-title-row"><h2>' . esc_html($event->title) . '</h2>';
        echo '<button class="calsys-dialog-close" type="button" data-calsys-close-registration aria-label="' . esc_attr__('Close event details', 'community-calendar-study-rooms') . '">&times;</button></div>';
        echo '<p class="calsys-event-date">' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)) . ' - ' . esc_html(mysql2date(get_option('time_format'), $event->ends_at)) . '</p></div>';
        if (!empty($event->image_id)) {
            echo '<div class="calsys-event-detail-image">' . wp_get_attachment_image((int) $event->image_id, 'large', false, array('loading' => 'lazy')) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        if ($event->description !== '') {
            echo '<div class="calsys-event-description">' . wpautop(wp_kses_post($event->description)) . '</div>';
        }
        if ($registration_settings['form_intro'] !== '') {
            echo '<p class="calsys-registration-intro">' . esc_html($registration_settings['form_intro']) . '</p>';
        }
        if (!$registration_available) {
            echo '<p class="calsys-registration-full">' . esc_html($registration_settings['disabled_message']) . '</p>';
        } elseif (!calsys_event_registration_is_open($event->starts_at)) {
            echo '<p class="calsys-registration-full">' . esc_html($registration_settings['closed_message']) . '</p>';
        } else {
            if ($full) {
                echo '<p class="calsys-registration-full">' . esc_html($registration_settings['full_message']) . ' ' .
                    esc_html__('Join the waitlist to be registered automatically if a place becomes available.', 'community-calendar-study-rooms') . '</p>';
            }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="calsys-form calsys-action-form">';
            wp_nonce_field('calsys_register_event');
            echo '<input type="hidden" name="action" value="calsys_register_event">';
            echo '<input type="hidden" name="event_id" value="' . esc_attr($event->id) . '">';
            if ($full) {
                echo '<input type="hidden" name="join_waitlist" value="1">';
            }
            if ($registration_settings['show_privacy_notice']) {
                echo calsys_privacy_note($registration_settings['privacy_notice']);
            }
            echo '<div class="calsys-form-grid"><p><label>' . esc_html($registration_settings['name_label']) . '<input name="name" type="text" autocomplete="name" maxlength="200" required></label></p>';
            echo '<p><label>' . esc_html($registration_settings['email_label']) . '<input name="email" type="email" autocomplete="email" maxlength="200" required></label></p></div>';
            echo calsys_registration_form_fields(calsys_decode_registration_fields($event->registration_fields));
            if (calsys_get_settings()['notifications']['email_event_reminders']) {
                echo '<p class="calsys-form-wide"><label class="calsys-checkbox-label"><input name="reminder_opt_in" type="checkbox" value="1"> ' .
                    esc_html($registration_settings['reminder_label']) . '</label></p>';
            }
            echo '<div class="calsys-action-buttons"><button type="submit">' . esc_html($full ? __('Join waitlist', 'community-calendar-study-rooms') : $registration_settings['submit_label']) . '</button></div>';
            echo '</form>';
        }

        echo '</section></dialog>';
    }
    return ob_get_clean();
}

/** Backwards-compatible shortcode callback for the unified calendar. */
function calsys_room_calendar_shortcode() {
    return calsys_event_calendar_view();
}
add_shortcode('calendar_room_calendar', 'calsys_room_calendar_shortcode');

/** WordPress shortcode callback for the staff-only calendar and entry editor. */
function calsys_staff_calendar_shortcode() {
    if (!current_user_can('manage_calendar_system')) {
        return '<div class="calsys-calendar-widget calsys-calendar-widget-staff">' .
            calsys_staff_access_link() .
            '<div class="calsys-notice" role="status">' . esc_html__('The staff calendar is available to staff only.', 'community-calendar-study-rooms') . '</div>' .
            '</div>';
    }
    global $wpdb;
    $month = calsys_calendar_month();
    $grid_start = $month->modify('-' . (((int) $month->format('w') - calsys_week_start_day() + 7) % 7) . ' days');
    $grid_end = $grid_start->modify('+42 days');
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, entry_type, starts_at, ends_at, all_day, room_id
        FROM " . calsys_table('staff_calendar') . "
        WHERE starts_at < %s AND ends_at >= %s
        ORDER BY starts_at ASC",
        $grid_end->format('Y-m-d H:i:s'),
        $grid_start->format('Y-m-d H:i:s')
    ));
    foreach ($entries as $entry) {
        $entry->calendar_type = 'staff';
        $types = calsys_staff_types();
        $entry->calendar_detail = $types[$entry->entry_type] ?? __('Other', 'community-calendar-study-rooms');
    }
    return '<div class="calsys-calendar-widget calsys-calendar-widget-staff">' .
        calsys_public_notice() .
        calsys_staff_access_link('staff') .
        calsys_render_calendar(__('Staff calendar', 'community-calendar-study-rooms'), $entries, $month, 'staff') .
        calsys_front_staff_form() .
        '</div>';
}
add_shortcode('calendar_staff_calendar', 'calsys_staff_calendar_shortcode');

/** WordPress shortcode callback that renders the upcoming event list. */
function calsys_events_shortcode() {
    global $wpdb;
    $category_sql = calsys_event_category_sql();
    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT e.*, (SELECT COUNT(*) FROM " . calsys_table('registrations') . " r WHERE r.event_id = e.id) AS registration_count
        FROM " . calsys_table('events') . " e
        WHERE e.status = %s AND e.starts_at > %s $category_sql
        ORDER BY e.starts_at ASC",
        'published',
        current_time('mysql')
    ));
    if (!is_array($events)) {
        error_log('CommonsCal: unable to load events list: ' . $wpdb->last_error);
        return '<div class="calsys-events"><div class="calsys-notice" role="status">' .
            esc_html__('Events are temporarily unavailable. Please contact the site administrator.', 'community-calendar-study-rooms') .
            '</div></div>';
    }
    ob_start();
    echo '<div class="calsys-events">';
    echo calsys_event_calendar_view($events); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    if (!current_user_can('manage_calendar_system') && !$events) {
        echo '<p>' . esc_html__('There are no upcoming events right now.', 'community-calendar-study-rooms') . '</p>';
    }
    if (!current_user_can('manage_calendar_system')) {
        $registration_settings = calsys_get_settings()['event_registration'];
        foreach ($events as $event) {
            $capacity = calsys_event_capacity($event->capacity);
            $full = $capacity > 0 && (int) $event->registration_count >= $capacity;
            echo '<article class="calsys-event" id="calsys-event-registration-' . esc_attr($event->id) . '">';
            if (!empty($event->image_id)) {
                echo wp_get_attachment_image((int) $event->image_id, 'medium', false, array('class' => 'calsys-event-image', 'loading' => 'lazy')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '<h2><button class="calsys-event-title-link" type="button" data-calsys-open-event-details="' . esc_attr($event->id) . '">' . esc_html($event->title) . '</button></h2>';
            echo '<p class="calsys-event-date">' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)) . ' - ' . esc_html(mysql2date(get_option('time_format'), $event->ends_at)) . '</p>';
            $categories = calsys_event_categories();
            if (calsys_show_public_category_labels() && isset($categories[$event->category])) {
                echo '<p class="calsys-event-category">' . esc_html($categories[$event->category]) . '</p>';
            }
            if ($event->description !== '') {
                echo '<div class="calsys-event-description">' . wpautop(wp_kses_post($event->description)) . '</div>';
            }
            if (calsys_show_public_registration_counts($event) && $capacity > 0) {
                echo '<p>' . esc_html(sprintf(__('%1$d of %2$d places registered', 'community-calendar-study-rooms'), (int) $event->registration_count, $capacity)) . '</p>';
            }
            if (!$registration_settings['enabled'] || empty($event->registration_enabled)) {
                echo '<p>' . esc_html($registration_settings['disabled_message']) . '</p>';
            } elseif (!calsys_event_registration_is_open($event->starts_at)) {
                echo '<p>' . esc_html($registration_settings['closed_message']) . '</p>';
            } elseif ($full) {
                echo '<p>' . esc_html($registration_settings['full_message']) . '</p>';
            } else {
                echo '<button class="calsys-day-action" type="button" data-calsys-open-registration="' . esc_attr($event->id) . '">' . esc_html($registration_settings['submit_label']) . '</button>';
            }
            echo '</article>';
        }
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('calendar_system', 'calsys_events_shortcode');

/** WordPress shortcode callback that renders the public room-request form. */
function calsys_room_request_shortcode() {
    return '<div class="calsys-calendar-widget calsys-calendar-widget-rooms">' .
        calsys_public_notice() .
        calsys_staff_access_link() .
        calsys_front_room_form() .
        '</div>';
}
add_shortcode('calendar_room_request', 'calsys_room_request_shortcode');
