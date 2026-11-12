<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Stops requests from users without calendar-management permission. */
function calsys_require_staff() {
    if (!current_user_can('manage_calendar_system')) {
        wp_die(esc_html__('You do not have permission to manage the calendar.', 'community-calendar-study-rooms'));
    }
}

/** Renders the dashboard overview, pending room requests, and upcoming activity. */
function calsys_admin_home_page() {
    global $wpdb;
    calsys_require_staff();
    calsys_admin_notice();

    $now = current_time('mysql');
    $today = current_time('Y-m-d');
    $tomorrow = DateTimeImmutable::createFromFormat('!Y-m-d', $today, wp_timezone())->modify('+1 day')->format('Y-m-d H:i:s');
    $events_table = calsys_table('events');
    $bookings_table = calsys_table('bookings');
    $rooms_table = calsys_table('rooms');
    $staff_table = calsys_table('staff_calendar');

    $upcoming_events_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $events_table WHERE status = %s AND starts_at >= %s",
        'published',
        $now
    ));
    $pending_requests_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table WHERE status = %s",
        'pending'
    ));
    $active_rooms_count = $wpdb->get_var("SELECT COUNT(*) FROM $rooms_table WHERE active = 1");
    $upcoming_staff_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $staff_table WHERE ends_at >= %s",
        $now
    ));
    $upcoming_events = $wpdb->get_results($wpdb->prepare(
        "SELECT title, starts_at FROM $events_table
        WHERE status = %s AND starts_at < %s AND ends_at > %s
        ORDER BY starts_at ASC",
        'published',
        $tomorrow,
        $now
    ));
    $feed_bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.room_id, b.booking_date, b.starts_at, b.ends_at, b.status, b.request_reason, r.name AS room_name
        FROM $bookings_table b
        INNER JOIN $rooms_table r ON r.id = b.room_id
        WHERE b.status IN (%s, %s) AND b.booking_date = %s AND b.ends_at > %s
        AND b.source_type NOT IN ('event', 'staff')
        ORDER BY b.booking_date ASC, b.starts_at ASC",
        'pending',
        'approved',
        $today,
        substr($now, 11, 8)
    ));
    $upcoming_staff = $wpdb->get_results($wpdb->prepare(
        "SELECT title, entry_type, starts_at, ends_at FROM $staff_table
        WHERE starts_at < %s AND ends_at > %s ORDER BY starts_at ASC",
        $tomorrow,
        $now
    ));

    if ($upcoming_events_count === false || $pending_requests_count === false || $active_rooms_count === false ||
        $upcoming_staff_count === false || $upcoming_events === null || $feed_bookings === null || $upcoming_staff === null) {
        error_log('CommonsCal: unable to load admin dashboard: ' . $wpdb->last_error);
        echo '<div class="wrap"><h1>' . esc_html__('CommonsCal', 'community-calendar-study-rooms') . '</h1><div class="notice notice-error"><p>' .
            esc_html__('The dashboard could not load calendar data. Please check the site error log.', 'community-calendar-study-rooms') .
            '</p></div></div>';
        return;
    }
    $upcoming_events_count = (int) $upcoming_events_count;
    $pending_requests_count = (int) $pending_requests_count;
    $active_rooms_count = (int) $active_rooms_count;
    $upcoming_staff_count = (int) $upcoming_staff_count;

    $activity_feed = array();
    foreach ($feed_bookings as $booking) {
        $starts_at = $booking->booking_date . ' ' . $booking->starts_at;
        $activity_feed[] = array(
            'sort_at' => $starts_at,
            'kind' => $booking->status === 'pending' ? __('Room request', 'community-calendar-study-rooms') : __('Room booking', 'community-calendar-study-rooms'),
            'title' => $booking->room_name,
            'detail' => mysql2date(get_option('date_format'), $booking->booking_date) . ' · ' .
                mysql2date(get_option('time_format'), $starts_at) . '–' .
                mysql2date(get_option('time_format'), $booking->booking_date . ' ' . $booking->ends_at) .
                ($booking->status === 'pending' && $booking->request_reason !== ''
                    ? ' · ' . $booking->request_reason
                    : ''),
            'status' => $booking->status,
            'url' => admin_url('admin.php?page=' . ($booking->status === 'pending' ? 'calsys-room-approvals' : 'calsys-rooms')),
        );
    }
    foreach ($upcoming_events as $event) {
        $activity_feed[] = array(
            'sort_at' => $event->starts_at,
            'kind' => __('Event', 'community-calendar-study-rooms'),
            'title' => $event->title,
            'detail' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at),
            'status' => '',
            'url' => admin_url('admin.php?page=calsys-rooms'),
        );
    }
    $staff_types = calsys_staff_types();
    foreach ($upcoming_staff as $entry) {
        $activity_feed[] = array(
            'sort_at' => $entry->starts_at,
            'kind' => __('Staff time', 'community-calendar-study-rooms'),
            'title' => $entry->title,
            'detail' => ($staff_types[$entry->entry_type] ?? $staff_types['other']) . ' · ' .
                mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry->starts_at),
            'status' => '',
            'url' => admin_url('admin.php?page=calsys-staff-calendar'),
        );
    }
    usort($activity_feed, function ($left, $right) {
        return strcmp($left['sort_at'], $right['sort_at']);
    });

    $calendar_url = admin_url('admin.php?page=');
    ?>
    <div class="wrap calsys-admin-page calsys-admin-home">
        <h1><?php esc_html_e('CommonsCal', 'community-calendar-study-rooms'); ?></h1>
        <p class="calsys-admin-intro"><?php esc_html_e('A quick overview of upcoming calendar activity and room requests.', 'community-calendar-study-rooms'); ?></p>

        <div class="calsys-admin-stats" aria-label="<?php esc_attr_e('Calendar overview', 'community-calendar-study-rooms'); ?>">
            <a class="calsys-admin-stat" href="<?php echo esc_url($calendar_url . 'calsys-rooms'); ?>">
                <span class="calsys-admin-stat-label"><?php esc_html_e('Upcoming events', 'community-calendar-study-rooms'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($upcoming_events_count)); ?></strong>
                <span><?php esc_html_e('Published events', 'community-calendar-study-rooms'); ?></span>
            </a>
            <a class="calsys-admin-stat is-attention" href="<?php echo esc_url($calendar_url . 'calsys-room-approvals'); ?>">
                <span class="calsys-admin-stat-label"><?php esc_html_e('Pending room requests', 'community-calendar-study-rooms'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($pending_requests_count)); ?></strong>
                <span><?php esc_html_e('Awaiting review', 'community-calendar-study-rooms'); ?></span>
            </a>
            <a class="calsys-admin-stat" href="<?php echo esc_url($calendar_url . 'calsys-rooms'); ?>">
                <span class="calsys-admin-stat-label"><?php esc_html_e('Active rooms', 'community-calendar-study-rooms'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($active_rooms_count)); ?></strong>
                <span><?php esc_html_e('Available to book', 'community-calendar-study-rooms'); ?></span>
            </a>
            <a class="calsys-admin-stat" href="<?php echo esc_url($calendar_url . 'calsys-staff-calendar'); ?>">
                <span class="calsys-admin-stat-label"><?php esc_html_e('Upcoming staff entries', 'community-calendar-study-rooms'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($upcoming_staff_count)); ?></strong>
                <span><?php esc_html_e('Meetings and time off', 'community-calendar-study-rooms'); ?></span>
            </a>
        </div>

        <section class="calsys-admin-card calsys-admin-feed-card">
            <div class="calsys-admin-card-heading">
                <div><h2><?php esc_html_e('Upcoming', 'community-calendar-study-rooms'); ?></h2>
                    <p><?php esc_html_e('Events, room bookings, and staff time still happening today.', 'community-calendar-study-rooms'); ?></p></div>
                <a class="button" href="<?php echo esc_url($calendar_url . 'calsys-rooms'); ?>"><?php esc_html_e('Manage rooms', 'community-calendar-study-rooms'); ?></a>
            </div>
            <?php if ($activity_feed) : ?>
                <ul class="calsys-admin-activity-list calsys-admin-feed">
                    <?php foreach ($activity_feed as $item) : ?>
                        <li>
                            <span class="calsys-admin-feed-kind<?php echo $item['status'] === 'pending' ? ' is-pending' : ''; ?>"><?php echo esc_html($item['kind']); ?></span>
                            <a href="<?php echo esc_url($item['url']); ?>"><strong><?php echo esc_html($item['title']); ?></strong></a>
                            <span><?php echo esc_html($item['detail']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="calsys-admin-empty"><?php esc_html_e('There are no remaining calendar items today.', 'community-calendar-study-rooms'); ?></p>
            <?php endif; ?>
        </section>

        <section class="calsys-admin-card calsys-admin-shortcuts">
            <h2><?php esc_html_e('Quick links', 'community-calendar-study-rooms'); ?></h2>
            <a class="button button-primary" href="<?php echo esc_url($calendar_url . 'calsys-events'); ?>"><?php esc_html_e('Manage events', 'community-calendar-study-rooms'); ?></a>
            <a class="button" href="<?php echo esc_url($calendar_url . 'calsys-rooms'); ?>"><?php esc_html_e('Book a room', 'community-calendar-study-rooms'); ?></a>
            <a class="button" href="<?php echo esc_url($calendar_url . 'calsys-room-approvals'); ?>"><?php esc_html_e('Room approvals', 'community-calendar-study-rooms'); ?></a>
            <a class="button" href="<?php echo esc_url($calendar_url . 'calsys-event-registrations'); ?>"><?php esc_html_e('Event registrations', 'community-calendar-study-rooms'); ?></a>
            <a class="button" href="<?php echo esc_url($calendar_url . 'calsys-staff-calendar'); ?>"><?php esc_html_e('Add staff time', 'community-calendar-study-rooms'); ?></a>
        </section>
    </div>
    <?php
}

/** Lists pending room requests with the existing approval actions. */
function calsys_admin_room_approvals_page() {
    global $wpdb;
    calsys_require_staff();
    calsys_admin_notice();
    $requests = $wpdb->get_results(
        "SELECT b.id, b.requester_name, b.requester_email, b.request_reason, b.booking_date, b.starts_at, b.ends_at, r.name AS room_name
        FROM " . calsys_table('bookings') . " b
        INNER JOIN " . calsys_table('rooms') . " r ON r.id = b.room_id
        WHERE b.status = 'pending'
        ORDER BY b.booking_date ASC, b.starts_at ASC, b.id ASC"
    );
    ?>
    <div class="wrap calsys-admin-page">
        <h1><?php esc_html_e('Room Approvals', 'community-calendar-study-rooms'); ?></h1>
        <p class="calsys-admin-intro"><?php esc_html_e('Review pending room requests and approve or deny each one.', 'community-calendar-study-rooms'); ?></p>
        <?php if (!is_array($requests)) :
            error_log('CommonsCal: unable to load pending room requests: ' . $wpdb->last_error); ?>
            <div class="notice notice-error"><p><?php esc_html_e('Room requests could not be loaded. Please check the site error log.', 'community-calendar-study-rooms'); ?></p></div>
        <?php elseif (!$requests) : ?>
            <div class="calsys-admin-card"><p class="calsys-admin-empty"><?php esc_html_e('No room requests are waiting for approval.', 'community-calendar-study-rooms'); ?></p></div>
        <?php else : ?>
            <ul class="calsys-review-list">
                <?php foreach ($requests as $request) : ?>
                    <li class="calsys-admin-card calsys-review-card">
                        <div class="calsys-review-heading">
                            <div>
                                <span class="calsys-admin-status is-pending"><?php esc_html_e('Pending', 'community-calendar-study-rooms'); ?></span>
                                <h2><?php echo esc_html($request->room_name); ?></h2>
                                <p class="calsys-review-date"><?php echo esc_html(mysql2date(get_option('date_format'), $request->booking_date)); ?> &middot; <?php echo esc_html(mysql2date(get_option('time_format'), $request->booking_date . ' ' . $request->starts_at)); ?> &ndash; <?php echo esc_html(mysql2date(get_option('time_format'), $request->booking_date . ' ' . $request->ends_at)); ?></p>
                            </div>
                            <div class="calsys-review-actions">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('calsys_booking_decision_' . $request->id); ?>
                                    <input type="hidden" name="action" value="calsys_booking_decision">
                                    <input type="hidden" name="calsys_return_page" value="calsys-room-approvals">
                                    <input type="hidden" name="booking_id" value="<?php echo esc_attr($request->id); ?>">
                                    <label><?php esc_html_e('Comment to requester (optional)', 'community-calendar-study-rooms'); ?><br><textarea name="decision_comment" rows="2" maxlength="4000"></textarea></label><br>
                                    <button class="button button-primary" type="submit" name="decision" value="approve"><?php esc_html_e('Approve', 'community-calendar-study-rooms'); ?></button>
                                    <button class="button button-secondary" type="submit" name="decision" value="deny"><?php esc_html_e('Deny', 'community-calendar-study-rooms'); ?></button>
                                </form>
                            </div>
                        </div>
                        <dl class="calsys-review-details">
                            <div><dt><?php esc_html_e('Requested by', 'community-calendar-study-rooms'); ?></dt><dd><?php echo esc_html($request->requester_name); ?><br><a href="<?php echo esc_url('mailto:' . $request->requester_email); ?>"><?php echo esc_html($request->requester_email); ?></a></dd></div>
                            <div><dt><?php esc_html_e('Reason', 'community-calendar-study-rooms'); ?></dt><dd><?php echo $request->request_reason !== '' ? nl2br(esc_html($request->request_reason)) : '&mdash;'; ?></dd></div>
                            <?php $equipment_description = calsys_equipment_description('room', $request->id); ?>
                            <div><dt><?php esc_html_e('Equipment', 'community-calendar-study-rooms'); ?></dt><dd><?php echo $equipment_description === false ? esc_html__('Could not load equipment.', 'community-calendar-study-rooms') : ($equipment_description !== '' ? esc_html($equipment_description) : '&mdash;'); ?></dd></div>
                        </dl>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

/** Lists upcoming events or displays the registrations for the selected event. */
function calsys_admin_event_registrations_page() {
    global $wpdb;
    calsys_require_staff();
    calsys_admin_notice();
    $events_table = calsys_table('events');
    $registrations_table = calsys_table('registrations');
    $viewing_event = absint($_GET['event_id'] ?? 0);
    $list_url = admin_url('admin.php?page=calsys-event-registrations');
    $event = null;
    $registrations = array();
    $waitlist = array();
    $events = array();
    if ($viewing_event) {
        $event = $wpdb->get_row($wpdb->prepare("SELECT id, title, starts_at FROM $events_table WHERE id = %d", $viewing_event));
        if (!$wpdb->last_error && $event) {
            $registrations = $wpdb->get_results($wpdb->prepare(
                "SELECT id, event_id, name, email, registered_at, registration_fields, reminder_opt_in, reminder_sent_at FROM $registrations_table WHERE event_id = %d ORDER BY registered_at ASC",
                $viewing_event
            ));
            if (!$wpdb->last_error && is_array($registrations)) {
                $waitlist = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, event_id, name, email, joined_at AS registered_at, registration_fields, reminder_opt_in, NULL AS reminder_sent_at FROM " . calsys_table('waitlist') . " WHERE event_id = %d ORDER BY joined_at ASC, id ASC",
                    $viewing_event
                ));
            }
        }
    } else {
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.title, e.starts_at, e.status, e.capacity, (SELECT COUNT(*) FROM $registrations_table r WHERE r.event_id = e.id) AS registration_count
            FROM $events_table e WHERE e.starts_at >= %s ORDER BY e.starts_at ASC, e.id ASC",
            current_time('mysql')
        ));
    }
    $load_failed = $wpdb->last_error || !is_array($registrations) || !is_array($waitlist) || !is_array($events);
    if ($load_failed) {
        error_log('CommonsCal: unable to load event registrations: ' . $wpdb->last_error);
    }
    ?>
    <div class="wrap calsys-admin-page">
        <h1><?php esc_html_e('Event Registrations', 'community-calendar-study-rooms'); ?></h1>
        <?php if ($load_failed) : ?>
            <div class="notice notice-error"><p><?php esc_html_e('Event registrations could not be loaded. Please check the site error log.', 'community-calendar-study-rooms'); ?></p></div>
        <?php elseif ($viewing_event && !$event) : ?>
            <p><?php esc_html_e('This event could not be found.', 'community-calendar-study-rooms'); ?></p>
            <a href="<?php echo esc_url($list_url); ?>"><?php esc_html_e('Back to upcoming events', 'community-calendar-study-rooms'); ?></a>
        <?php elseif ($event) : ?>
            <p><a href="<?php echo esc_url($list_url); ?>">&larr; <?php esc_html_e('Back to upcoming events', 'community-calendar-study-rooms'); ?></a></p>
            <section class="calsys-admin-card">
                <h2><?php echo esc_html($event->title); ?></h2>
                <p><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)); ?></p>
                <?php calsys_admin_registration_table($registrations); ?>
                <h3><?php esc_html_e('Waitlist', 'community-calendar-study-rooms'); ?></h3>
                <?php calsys_admin_registration_table($waitlist, 'waitlist'); ?>
            </section>
        <?php else : ?>
            <p class="calsys-admin-intro"><?php esc_html_e('Choose an upcoming event to see the people registered and their responses. Draft events are also shown.', 'community-calendar-study-rooms'); ?></p>
            <?php if (!$events) : ?>
                <div class="calsys-admin-card"><p class="calsys-admin-empty"><?php esc_html_e('There are no upcoming events.', 'community-calendar-study-rooms'); ?></p></div>
            <?php else : ?>
                <ul class="calsys-review-list">
                    <?php foreach ($events as $upcoming_event) : $capacity = calsys_event_capacity($upcoming_event->capacity); ?>
                        <li>
                            <a class="calsys-admin-card calsys-event-registration-link" href="<?php echo esc_url(add_query_arg('event_id', $upcoming_event->id, $list_url)); ?>">
                                <span><strong><?php echo esc_html($upcoming_event->title); ?></strong><span><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $upcoming_event->starts_at)); ?><?php if ($upcoming_event->status === 'draft') : ?> &middot; <?php esc_html_e('Draft', 'community-calendar-study-rooms'); ?><?php endif; ?></span></span>
                                <span class="calsys-registration-count"><?php echo esc_html(sprintf(_n('%d registration', '%d registrations', (int) $upcoming_event->registration_count, 'community-calendar-study-rooms'), (int) $upcoming_event->registration_count)); ?><?php if ($capacity > 0) : ?> &middot; <?php echo esc_html(sprintf(__('Limit: %d', 'community-calendar-study-rooms'), $capacity)); ?><?php endif; ?> &rarr;</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

/** Renders the hidden event-management screen and registration records. */
function calsys_admin_events_page() {
    global $wpdb;
    calsys_require_staff();
    calsys_admin_notice();

    $events_table = calsys_table('events');
    $categories = calsys_event_categories();
    $rooms = calsys_all_rooms();
    $editing = null;
    if (!empty($_GET['edit_event'])) {
        $editing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $events_table WHERE id = %d",
            absint($_GET['edit_event'])
        ));
    }
    $editing_room_id = $editing ? (int) $wpdb->get_var($wpdb->prepare(
        "SELECT room_id FROM " . calsys_table('bookings') . " WHERE source_type = 'event' AND source_id = %d ORDER BY id ASC LIMIT 1",
        $editing->id
    )) : 0;
    $editing_registration_fields = $editing ? calsys_decode_registration_fields($editing->registration_fields ?? '') : array();
    $editing_registration_recipients = $editing ? calsys_decode_registration_recipients($editing->registration_recipients ?? '') : array();
    $registration_defaults = calsys_get_settings()['event_registration'];
    $events = $wpdb->get_results(
        "SELECT e.*, (SELECT COUNT(*) FROM " . calsys_table('registrations') . " r WHERE r.event_id = e.id) AS registration_count
        FROM $events_table e ORDER BY e.starts_at DESC"
    );
    $registrations = array();
    $viewing_event = absint($_GET['view_registrations'] ?? 0);
    if ($viewing_event) {
        $registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_id, name, email, registered_at, registration_fields, reminder_opt_in, reminder_sent_at FROM " . calsys_table('registrations') . " WHERE event_id = %d ORDER BY registered_at ASC",
            $viewing_event
        ));
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Events', 'community-calendar-study-rooms'); ?></h1>
        <h2><?php echo $editing ? esc_html__('Edit event', 'community-calendar-study-rooms') : esc_html__('Create an event', 'community-calendar-study-rooms'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-admin-form">
            <?php wp_nonce_field('calsys_save_event'); ?>
            <input type="hidden" name="action" value="calsys_save_event">
            <input type="hidden" name="event_id" value="<?php echo esc_attr($editing ? $editing->id : 0); ?>">
            <p><label for="calsys-event-title"><?php esc_html_e('Title', 'community-calendar-study-rooms'); ?></label><br>
                <input id="calsys-event-title" name="title" type="text" class="regular-text" required maxlength="200" value="<?php echo esc_attr($editing ? $editing->title : ''); ?>"></p>
            <p><label for="calsys-event-initials"><?php esc_html_e('Your initials', 'community-calendar-study-rooms'); ?></label><br>
                <input id="calsys-event-initials" name="staff_initials" type="text" required maxlength="10">
                <?php if ($editing && !empty($editing->staff_initials)) : ?><span class="description"><?php echo esc_html(sprintf(__('Last saved by: %s', 'community-calendar-study-rooms'), $editing->staff_initials)); ?></span><?php endif; ?></p>
            <p><label for="calsys-event-description"><?php esc_html_e('Description', 'community-calendar-study-rooms'); ?></label><br>
                <textarea id="calsys-event-description" name="description" rows="5" class="large-text"><?php echo esc_textarea($editing ? $editing->description : ''); ?></textarea></p>
            <?php echo calsys_event_image_field($editing->image_id ?? 0); ?>
            <p><label for="calsys-event-start"><?php esc_html_e('Starts', 'community-calendar-study-rooms'); ?></label><br>
                <input id="calsys-event-start" name="starts_at" type="datetime-local" required value="<?php echo esc_attr($editing ? str_replace(' ', 'T', substr($editing->starts_at, 0, 16)) : ''); ?>"></p>
            <p><label for="calsys-event-end"><?php esc_html_e('Ends', 'community-calendar-study-rooms'); ?></label><br>
                <input id="calsys-event-end" name="ends_at" type="datetime-local" required value="<?php echo esc_attr($editing ? str_replace(' ', 'T', substr($editing->ends_at, 0, 16)) : ''); ?>"></p>
            <?php if ($editing && $editing->recurrence_group) : ?>
                <p><label for="calsys-event-scope"><?php esc_html_e('Apply changes to', 'community-calendar-study-rooms'); ?></label><br>
                    <select id="calsys-event-scope" name="event_scope"><option value="one"><?php esc_html_e('This event only', 'community-calendar-study-rooms'); ?></option><option value="future"><?php esc_html_e('This and future recurring events', 'community-calendar-study-rooms'); ?></option><option value="all"><?php esc_html_e('All recurring events', 'community-calendar-study-rooms'); ?></option></select>
                    <span class="description"><?php esc_html_e('All shown fields, including room and equipment, replace the values for selected events. Dates shift by the same amount; registrations stay with each event.', 'community-calendar-study-rooms'); ?></span></p>
            <?php endif; ?>
            <p><label for="calsys-event-capacity"><?php esc_html_e('Event registration limit (0 uses the global setting)', 'community-calendar-study-rooms'); ?></label><br>
                <input id="calsys-event-capacity" name="capacity" type="number" min="0" step="1" value="<?php echo esc_attr($editing ? $editing->capacity : 0); ?>"></p>
            <?php echo calsys_event_count_visibility_field($editing ? !empty($editing->show_registration_counts) : true); ?>
            <p><label for="calsys-event-status"><?php esc_html_e('Visibility', 'community-calendar-study-rooms'); ?></label><br>
                <select id="calsys-event-status" name="status">
                    <option value="draft" <?php selected($editing ? $editing->status : 'draft', 'draft'); ?>><?php esc_html_e('Draft', 'community-calendar-study-rooms'); ?></option>
                    <option value="published" <?php selected($editing ? $editing->status : 'draft', 'published'); ?>><?php esc_html_e('Published', 'community-calendar-study-rooms'); ?></option>
                </select></p>
            <p><label for="calsys-event-category"><?php esc_html_e('Category', 'community-calendar-study-rooms'); ?></label><br>
                <select id="calsys-event-category" name="category"><?php foreach ($categories as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($editing ? $editing->category : 'adult', $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></p>
            <?php echo calsys_event_series_field($editing->series_id ?? 0); ?>
            <?php echo calsys_event_registration_editor(
                $editing ? !empty($editing->registration_enabled) : !empty($registration_defaults['enabled_by_default']),
                $editing ? $editing_registration_fields : $registration_defaults['default_fields'],
                $editing_registration_recipients
            ); ?>
            <p><label for="calsys-event-room"><?php esc_html_e('Room (optional)', 'community-calendar-study-rooms'); ?></label><br>
                <select id="calsys-event-room" name="room_id"><option value=""><?php esc_html_e('No room reservation', 'community-calendar-study-rooms'); ?></option><?php foreach ($rooms as $room) : ?><option value="<?php echo esc_attr($room->id); ?>" <?php selected($editing_room_id, (int) $room->id); ?>><?php echo esc_html(calsys_room_option_label($room)); ?></option><?php endforeach; ?></select>
                <?php if (!$rooms) : ?><span class="description"><?php esc_html_e('Add a room in Settings before reserving one for an event.', 'community-calendar-study-rooms'); ?></span><?php endif; ?></p>
            <?php
            $editing_equipment = $editing ? calsys_equipment_for_source('event', $editing->id) : array();
            if ($editing_equipment === false) {
                wp_die(esc_html__('Equipment reservations could not be loaded. Please check the site error log.', 'community-calendar-study-rooms'));
            }
            echo calsys_equipment_fields($editing_equipment);
            ?>
            <?php if (!$editing) : ?>
                <p><label><input name="recurring" type="checkbox" value="1" data-calsys-toggle-recurrence> <?php esc_html_e('Repeat this event', 'community-calendar-study-rooms'); ?></label></p>
                <p data-calsys-register-recurrences hidden><label><input name="register_all_recurrences" type="checkbox" value="1"> <?php esc_html_e('Let attendees register for all recurring events', 'community-calendar-study-rooms'); ?></label></p>
                <p data-calsys-recurrence-field hidden><label><?php esc_html_e('Repeat', 'community-calendar-study-rooms'); ?><select name="recurrence_frequency"><option value="daily"><?php esc_html_e('Daily', 'community-calendar-study-rooms'); ?></option><option value="weekly"><?php esc_html_e('Weekly', 'community-calendar-study-rooms'); ?></option><option value="monthly"><?php esc_html_e('Monthly', 'community-calendar-study-rooms'); ?></option></select></label></p>
                <p data-calsys-recurrence-field hidden><label><?php esc_html_e('Stop repeating by', 'community-calendar-study-rooms'); ?><select name="recurrence_end_type" data-calsys-recurrence-end><option value="count"><?php esc_html_e('Number of occurrences', 'community-calendar-study-rooms'); ?></option><option value="date"><?php esc_html_e('End date', 'community-calendar-study-rooms'); ?></option></select></label></p>
                <p data-calsys-recurrence-field hidden data-calsys-occurrences><label><?php esc_html_e('Occurrences (maximum 100)', 'community-calendar-study-rooms'); ?><input name="recurrence_count" type="number" min="2" max="100" value="10"></label></p>
                <p data-calsys-recurrence-field hidden data-calsys-end-date><label><?php esc_html_e('Repeat through', 'community-calendar-study-rooms'); ?><input name="recurrence_until" type="date"></label></p>
            <?php elseif (!empty($editing->recurrence_group)) : ?>
                <p><label><input name="register_all_recurrences" type="checkbox" value="1" <?php checked(!empty($editing->register_all_recurrences)); ?>> <?php esc_html_e('Let attendees register for all recurring events', 'community-calendar-study-rooms'); ?></label></p>
            <?php endif; ?>
            <?php submit_button($editing ? __('Update event', 'community-calendar-study-rooms') : __('Create event', 'community-calendar-study-rooms')); ?>
            <?php if ($editing) : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=calsys-events')); ?>"><?php esc_html_e('Cancel', 'community-calendar-study-rooms'); ?></a><?php endif; ?>
        </form>

        <h2><?php esc_html_e('All events', 'community-calendar-study-rooms'); ?></h2>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e('Event', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Date', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Category', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Staff initials', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Registrations', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Visibility', 'community-calendar-study-rooms'); ?></th><th></th></tr></thead>
            <tbody>
            <?php if ($events) : foreach ($events as $event) : ?>
                <?php $effective_capacity = calsys_event_capacity($event->capacity); ?>
                <tr>
                    <td><?php echo esc_html($event->title); ?></td>
                    <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event->starts_at)); ?></td>
                    <td><?php echo esc_html($categories[$event->category] ?? $event->category); ?></td>
                    <td><?php echo esc_html($event->staff_initials ?? ''); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'calsys-events', 'view_registrations' => $event->id), admin_url('admin.php'))); ?>">
                            <?php echo esc_html($event->registration_count); ?><?php if ($effective_capacity > 0) : ?> / <?php echo esc_html($effective_capacity); ?><?php endif; ?>
                        </a>
                    </td>
                    <td><?php echo esc_html(ucfirst($event->status)); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'calsys-events', 'edit_event' => $event->id), admin_url('admin.php'))); ?>"><?php esc_html_e('Edit', 'community-calendar-study-rooms'); ?></a>
                        <form class="calsys-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this event and all of its registrations and linked room reservation? This cannot be undone.', 'community-calendar-study-rooms')); ?>');">
                            <?php wp_nonce_field('calsys_delete_event'); ?>
                            <input type="hidden" name="action" value="calsys_delete_event">
                            <input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>">
                            <?php submit_button(__('Delete', 'community-calendar-study-rooms'), 'delete small', 'submit', false); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="7"><?php esc_html_e('No events yet.', 'community-calendar-study-rooms'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if ($viewing_event) : ?>
            <?php
            $viewed_event = $wpdb->get_row($wpdb->prepare(
                "SELECT title FROM $events_table WHERE id = %d",
                $viewing_event
            ));
            ?>
            <h2><?php echo esc_html($viewed_event ? sprintf(__('Registrations: %s', 'community-calendar-study-rooms'), $viewed_event->title) : __('Event registrations', 'community-calendar-study-rooms')); ?></h2>
            <?php calsys_admin_registration_table($registrations); ?>
        <?php endif; ?>
    </div>
    <?php
}

/** Renders registration records and saved custom-field responses. */
function calsys_admin_registration_table($registrations, $kind = 'registration') {
    ?>
    <div class="calsys-registration-table-scroll">
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Name', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Email', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Registration details', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Reminder', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Registered', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Actions', 'community-calendar-study-rooms'); ?></th></tr></thead>
                <tbody>
                <?php if ($registrations) : foreach ($registrations as $registration) : ?>
                    <tr>
                        <td><?php echo esc_html($registration->name); ?></td>
                        <td><a href="<?php echo esc_url('mailto:' . $registration->email); ?>"><?php echo esc_html($registration->email); ?></a></td>
                        <td>
                            <?php $answers = json_decode($registration->registration_fields, true); ?>
                            <?php if (is_array($answers) && $answers) : ?>
                                <?php foreach ($answers as $answer) : if (!is_array($answer)) { continue; } ?>
                                    <strong><?php echo esc_html($answer['label'] ?? ''); ?>:</strong> <?php echo esc_html($answer['value'] ?? ''); ?><br>
                                <?php endforeach; ?>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td><?php
                            if (!$registration->reminder_opt_in) {
                                esc_html_e('No', 'community-calendar-study-rooms');
                            } elseif ($registration->reminder_sent_at) {
                                esc_html_e('Sent', 'community-calendar-study-rooms');
                            } else {
                                esc_html_e('Opted in', 'community-calendar-study-rooms');
                            }
                        ?></td>
                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $registration->registered_at)); ?></td>
                        <td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Cancel this attendee and email them?', 'community-calendar-study-rooms')); ?>');">
                            <?php wp_nonce_field('calsys_cancel_attendee_' . $registration->event_id . '_' . $registration->id . '_' . $kind); ?>
                            <input type="hidden" name="action" value="calsys_cancel_event_attendee">
                            <input type="hidden" name="event_id" value="<?php echo esc_attr($registration->event_id); ?>">
                            <input type="hidden" name="attendee_id" value="<?php echo esc_attr($registration->id); ?>">
                            <input type="hidden" name="kind" value="<?php echo esc_attr($kind); ?>">
                            <button type="submit" class="button"><?php esc_html_e('Cancel', 'community-calendar-study-rooms'); ?></button>
                        </form></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="6"><?php echo $kind === 'waitlist' ? esc_html__('No one is on the waitlist.', 'community-calendar-study-rooms') : esc_html__('No registrations for this event yet.', 'community-calendar-study-rooms'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
    </div>
    <?php
}

/** Renders room creation, editing, availability, and directory controls in Settings. */
function calsys_admin_rooms_settings_panel() {
    global $wpdb;
    calsys_require_staff();
    $rooms_table = calsys_table('rooms');
    $editing = null;
    if (!empty($_GET['edit_room'])) {
        $editing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $rooms_table WHERE id = %d",
            absint($_GET['edit_room'])
        ));
    }
    $rooms = $wpdb->get_results("SELECT * FROM $rooms_table ORDER BY name ASC");
    if (!is_array($rooms)) {
        error_log('CommonsCal: unable to load room directory in settings: ' . $wpdb->last_error);
        echo '<section class="calsys-admin-card"><div class="notice notice-error"><p>' .
            esc_html__('The room directory could not be loaded. Please check the site error log.', 'community-calendar-study-rooms') .
            '</p></div></section>';
        return;
    }
    ?>
    <section class="calsys-admin-card calsys-settings-rooms" id="calsys-settings-rooms">
        <div class="calsys-admin-card-heading">
            <div><h2><?php esc_html_e('Rooms', 'community-calendar-study-rooms'); ?></h2>
                <p><?php esc_html_e('Add rooms, change capacity and availability, or update your room directory. Rooms with booking history cannot be deleted.', 'community-calendar-study-rooms'); ?></p></div>
        </div>
        <div class="calsys-settings-room-grid">
            <div>
                <h3><?php echo $editing ? esc_html__('Edit room', 'community-calendar-study-rooms') : esc_html__('Add a room', 'community-calendar-study-rooms'); ?></h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-admin-form">
                    <?php wp_nonce_field('calsys_save_room'); ?>
                    <input type="hidden" name="action" value="calsys_save_room">
                    <input type="hidden" name="room_id" value="<?php echo esc_attr($editing ? $editing->id : 0); ?>">
                    <p><label for="calsys-settings-room-name"><?php esc_html_e('Room name', 'community-calendar-study-rooms'); ?></label><br>
                        <input id="calsys-settings-room-name" name="name" type="text" class="regular-text" required maxlength="200" value="<?php echo esc_attr($editing ? $editing->name : ''); ?>"></p>
                    <p><label for="calsys-settings-room-capacity"><?php esc_html_e('Capacity', 'community-calendar-study-rooms'); ?></label><br>
                        <input id="calsys-settings-room-capacity" name="capacity" type="number" min="1" step="1" required value="<?php echo esc_attr($editing ? $editing->capacity : 1); ?>"></p>
                    <p><label for="calsys-settings-room-color"><?php esc_html_e('Calendar color', 'community-calendar-study-rooms'); ?></label><br>
                        <input id="calsys-settings-room-color" name="color" type="text" class="calsys-color-picker" value="<?php echo esc_attr($editing ? $editing->color : ''); ?>">
                        <span class="description"><?php esc_html_e('Optional. Clear the color to use the default room booking color.', 'community-calendar-study-rooms'); ?></span></p>
                    <p><label><input name="active" type="checkbox" value="1" <?php checked($editing ? (int) $editing->active : 1, 1); ?>> <?php esc_html_e('Available for new requests', 'community-calendar-study-rooms'); ?></label></p>
                    <?php submit_button($editing ? __('Update room', 'community-calendar-study-rooms') : __('Add room', 'community-calendar-study-rooms')); ?>
                    <?php if ($editing) : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=calsys-settings#calsys-settings-rooms')); ?>"><?php esc_html_e('Cancel', 'community-calendar-study-rooms'); ?></a><?php endif; ?>
                </form>
            </div>
            <div>
                <h3><?php esc_html_e('Room directory', 'community-calendar-study-rooms'); ?></h3>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Room', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Capacity', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Status', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Actions', 'community-calendar-study-rooms'); ?></th></tr></thead>
                    <tbody>
                    <?php if ($rooms) : foreach ($rooms as $room) : ?>
                        <tr>
                            <td><?php echo esc_html($room->name); ?></td>
                            <td><?php echo esc_html($room->capacity); ?></td>
                            <td><?php echo $room->active ? esc_html__('Available', 'community-calendar-study-rooms') : esc_html__('Unavailable', 'community-calendar-study-rooms'); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'calsys-settings', 'edit_room' => $room->id), admin_url('admin.php')) . '#calsys-settings-rooms'); ?>"><?php esc_html_e('Edit', 'community-calendar-study-rooms'); ?></a>
                                <form class="calsys-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this room? Rooms with existing bookings cannot be deleted.', 'community-calendar-study-rooms')); ?>');">
                                    <?php wp_nonce_field('calsys_delete_room_' . $room->id); ?>
                                    <input type="hidden" name="action" value="calsys_delete_room">
                                    <input type="hidden" name="room_id" value="<?php echo esc_attr($room->id); ?>">
                                    <?php submit_button(__('Delete', 'community-calendar-study-rooms'), 'delete small', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="4"><?php esc_html_e('No rooms have been added yet.', 'community-calendar-study-rooms'); ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php
}

/** Deletes an unused room after checking that no booking or staff entry references it. */
function calsys_delete_room() {
    calsys_require_staff();
    $room_id = absint($_POST['room_id'] ?? 0);
    check_admin_referer('calsys_delete_room_' . $room_id);
    if (!$room_id) {
        calsys_admin_redirect('calsys-settings', 'invalid');
    }

    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false || !$wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d FOR UPDATE",
        $room_id
    ))) {
        $wpdb->query('ROLLBACK');
        calsys_admin_redirect('calsys-settings', 'invalid');
    }
    $has_bookings = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('bookings') . " WHERE room_id = %d LIMIT 1",
        $room_id
    ));
    $has_staff_entries = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . calsys_table('staff_calendar') . " WHERE room_id = %d LIMIT 1",
        $room_id
    ));
    if ($has_bookings || $has_staff_entries) {
        $wpdb->query('ROLLBACK');
        calsys_admin_redirect('calsys-settings', 'room_in_use');
    }
    $deleted = $wpdb->delete(calsys_table('rooms'), array('id' => $room_id), array('%d'));
    if ($deleted !== 1 || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_admin_redirect('calsys-settings', 'invalid');
    }
    calsys_admin_redirect('calsys-settings', 'room_deleted');
}
add_action('admin_post_calsys_delete_room', 'calsys_delete_room');

/** Renders room management, the booking calendar, and request approval controls. */
function calsys_admin_rooms_page() {
    global $wpdb;
    calsys_require_staff();
    calsys_admin_notice();

    $rooms_table = calsys_table('rooms');
    $month = calsys_calendar_month();
    $grid_start = $month->modify('-' . (((int) $month->format('w') - calsys_week_start_day() + 7) % 7) . ' days');
    $grid_end = $grid_start->modify('+42 days');
    $room_calendar_entries = calsys_room_calendar_entries($grid_start, $grid_end);
    $room_calendar_load_failed = $room_calendar_entries === false;
    $room_calendar_events = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.title, e.description, e.image_id, e.starts_at, e.ends_at, e.capacity, e.status, e.category, e.registration_fields,
        e.registration_enabled, e.registration_recipients, e.show_registration_counts, e.recurrence_group, e.staff_initials, e.series_id, e.register_all_recurrences,
        (SELECT b.room_id FROM " . calsys_table('bookings') . " b WHERE b.source_type = 'event' AND b.source_id = e.id ORDER BY b.id ASC LIMIT 1) AS room_id,
        0 AS all_day, 'event' AS calendar_type,
        (SELECT COUNT(*) FROM " . calsys_table('registrations') . " r WHERE r.event_id = e.id) AS registration_count
        FROM " . calsys_table('events') . " e
        WHERE e.status = %s AND e.starts_at < %s AND e.ends_at >= %s " . calsys_event_category_sql() . calsys_event_group_filter_sql() . "
        ORDER BY e.starts_at ASC",
        'published',
        $grid_end->format('Y-m-d H:i:s'),
        $grid_start->format('Y-m-d H:i:s')
    ));
    if (is_array($room_calendar_events)) {
        $categories = calsys_event_categories();
        foreach ($room_calendar_events as $event) {
            $event->category_label = $categories[$event->category] ?? '';
            $event->calendar_detail = calsys_show_public_registration_counts($event)
                ? sprintf(
                    _n('%d registered', '%d registered', (int) $event->registration_count, 'community-calendar-study-rooms'),
                    (int) $event->registration_count
                )
                : '';
        }
        $room_calendar_entries = array_merge($room_calendar_entries ?: array(), $room_calendar_events);
    }
    $rooms = $wpdb->get_results("SELECT * FROM $rooms_table ORDER BY name ASC");
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, r.name AS room_name FROM " . calsys_table('bookings') . " b
        INNER JOIN $rooms_table r ON r.id = b.room_id
        ORDER BY (b.status = 'pending') DESC, (b.booking_date >= %s) DESC, b.booking_date ASC, b.starts_at ASC LIMIT 200",
        current_time('Y-m-d')
    ));
    $active_room_count = $wpdb->get_var("SELECT COUNT(*) FROM $rooms_table WHERE active = 1");
    $pending_booking_count = $wpdb->get_var("SELECT COUNT(*) FROM " . calsys_table('bookings') . " WHERE status = 'pending'");
    $upcoming_booking_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM " . calsys_table('bookings') . " WHERE status = %s AND booking_date >= %s",
        'approved',
        current_time('Y-m-d')
    ));
    if (!is_array($rooms) || !is_array($bookings) || !is_array($room_calendar_events) || $room_calendar_load_failed ||
        $active_room_count === false || $pending_booking_count === false || $upcoming_booking_count === false) {
        error_log('CommonsCal: unable to load room management data: ' . $wpdb->last_error);
        echo '<div class="wrap"><h1>' . esc_html__('Rooms', 'community-calendar-study-rooms') . '</h1><div class="notice notice-error"><p>' .
            esc_html__('Room data could not be loaded. Please check the site error log.', 'community-calendar-study-rooms') .
            '</p></div></div>';
        return;
    }
    $active_room_count = (int) $active_room_count;
    $pending_booking_count = (int) $pending_booking_count;
    $upcoming_booking_count = (int) $upcoming_booking_count;
    ?>
    <div class="wrap calsys-admin-page calsys-admin-rooms">
        <h1><?php esc_html_e('Rooms & Events', 'community-calendar-study-rooms'); ?></h1>
        <p class="calsys-admin-intro"><?php esc_html_e('Manage room availability, review requests, and create reservations for patrons.', 'community-calendar-study-rooms'); ?></p>
        <div class="calsys-admin-stats">
            <div class="calsys-admin-stat"><span class="calsys-admin-stat-label"><?php esc_html_e('Available rooms', 'community-calendar-study-rooms'); ?></span><strong><?php echo esc_html(number_format_i18n($active_room_count)); ?></strong></div>
            <div class="calsys-admin-stat is-attention"><span class="calsys-admin-stat-label"><?php esc_html_e('Requests to review', 'community-calendar-study-rooms'); ?></span><strong><?php echo esc_html(number_format_i18n($pending_booking_count)); ?></strong></div>
            <div class="calsys-admin-stat"><span class="calsys-admin-stat-label"><?php esc_html_e('Upcoming confirmed bookings', 'community-calendar-study-rooms'); ?></span><strong><?php echo esc_html(number_format_i18n($upcoming_booking_count)); ?></strong></div>
        </div>

        <section class="calsys-admin-card calsys-admin-calendar-card">
            <div class="calsys-admin-card-heading">
                <div><h2><?php esc_html_e('Room booking calendar', 'community-calendar-study-rooms'); ?></h2>
                    <p><?php esc_html_e('Review room bookings alongside upcoming events, or select a day to add a booking.', 'community-calendar-study-rooms'); ?></p></div>
            </div>
            <div class="calsys-calendar-widget calsys-calendar-widget-rooms">
                <?php if (isset($_GET['calsys_notice']) && in_array(wp_unslash($_GET['calsys_notice']), array('registration_results', 'registration_results_unavailable', 'registration_complete', 'invalid_registration', 'already_registered', 'event_full', 'registration_disabled', 'registration_closed', 'waitlist_joined', 'waitlist_joined_email_failed'), true)) : ?>
                    <?php echo calsys_public_notice(); ?>
                <?php endif; ?>
                <?php echo calsys_render_calendar(__('Room bookings and upcoming events', 'community-calendar-study-rooms'), $room_calendar_entries, $month, 'combined'); ?>
                <?php echo calsys_render_event_registration_forms($room_calendar_events); ?>
                <?php echo calsys_front_event_form(); ?>
                <?php echo calsys_front_room_form(); ?>
            </div>
        </section>

        <section class="calsys-admin-card calsys-admin-booking-form-card">
        <h2><?php esc_html_e('Create a patron booking', 'community-calendar-study-rooms'); ?></h2>
        <p><?php esc_html_e('Staff bookings are confirmed immediately. Public room requests remain pending until approved.', 'community-calendar-study-rooms'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-admin-form calsys-admin-booking-form"<?php echo calsys_room_availability_attributes(true); ?>>
            <?php wp_nonce_field('calsys_staff_booking'); ?>
            <input type="hidden" name="action" value="calsys_staff_booking">
            <p><label for="calsys-booking-room"><?php esc_html_e('Room', 'community-calendar-study-rooms'); ?></label><br>
                <select id="calsys-booking-room" name="room_id" required>
                    <option value=""><?php esc_html_e('Select a room', 'community-calendar-study-rooms'); ?></option>
                    <?php foreach ($rooms as $room) : if ($room->active) : ?>
                        <option value="<?php echo esc_attr($room->id); ?>"><?php echo esc_html($room->name); ?></option>
                    <?php endif; endforeach; ?>
                </select></p>
            <p><label for="calsys-booking-name"><?php esc_html_e('Patron name', 'community-calendar-study-rooms'); ?></label><br><input id="calsys-booking-name" name="requester_name" type="text" required maxlength="200"></p>
            <p><label for="calsys-booking-email"><?php esc_html_e('Patron email', 'community-calendar-study-rooms'); ?></label><br><input id="calsys-booking-email" name="requester_email" type="email" required maxlength="200"></p>
            <p><label for="calsys-booking-date"><?php esc_html_e('Date', 'community-calendar-study-rooms'); ?></label><br><input id="calsys-booking-date" name="booking_date" type="date" required></p>
            <p><label for="calsys-booking-start"><?php esc_html_e('Start time', 'community-calendar-study-rooms'); ?></label><br><input id="calsys-booking-start" name="starts_at" type="time" required></p>
            <p><label for="calsys-booking-end"><?php esc_html_e('End time', 'community-calendar-study-rooms'); ?></label><br><input id="calsys-booking-end" name="ends_at" type="time" required></p>
            <?php echo calsys_room_availability_messages(true); ?>
            <div class="calsys-form-wide"><?php echo calsys_equipment_fields(); ?></div>
            <?php submit_button(__('Confirm staff booking', 'community-calendar-study-rooms')); ?>
        </form>
        </section>

        <section class="calsys-admin-card">
        <div class="calsys-admin-card-heading"><div><h2><?php esc_html_e('Requests and bookings', 'community-calendar-study-rooms'); ?></h2><p><?php esc_html_e('Pending requests are listed first; upcoming reservations follow.', 'community-calendar-study-rooms'); ?></p></div></div>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e('Room', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Patron', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Reason', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Date and time', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Status', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Actions', 'community-calendar-study-rooms'); ?></th></tr></thead>
            <tbody>
            <?php if ($bookings) : foreach ($bookings as $booking) : ?>
                <tr>
                    <td><?php echo esc_html($booking->room_name); ?></td>
                    <td><?php echo esc_html($booking->requester_name); ?><br><a href="<?php echo esc_url('mailto:' . $booking->requester_email); ?>"><?php echo esc_html($booking->requester_email); ?></a></td>
                    <td><?php echo $booking->request_reason !== '' ? nl2br(esc_html($booking->request_reason)) : '&mdash;'; ?></td>
                    <td><?php echo esc_html(mysql2date(get_option('date_format'), $booking->booking_date)); ?>, <?php echo esc_html(mysql2date(get_option('time_format'), $booking->booking_date . ' ' . $booking->starts_at)); ?> - <?php echo esc_html(mysql2date(get_option('time_format'), $booking->booking_date . ' ' . $booking->ends_at)); ?></td>
                    <td><?php echo esc_html(ucfirst($booking->status)); ?></td>
                    <td>
                    <?php if ($booking->status === 'pending') : ?>
                        <form class="calsys-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('calsys_booking_decision_' . $booking->id); ?>
                            <input type="hidden" name="action" value="calsys_booking_decision">
                            <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                            <label><?php esc_html_e('Comment to requester (optional)', 'community-calendar-study-rooms'); ?><br><textarea name="decision_comment" rows="2" maxlength="4000"></textarea></label><br>
                            <button class="button button-secondary button-small" type="submit" name="decision" value="approve"><?php esc_html_e('Approve', 'community-calendar-study-rooms'); ?></button>
                            <button class="button button-secondary button-small" type="submit" name="decision" value="deny"><?php esc_html_e('Deny', 'community-calendar-study-rooms'); ?></button>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="6"><?php esc_html_e('No room requests or bookings yet.', 'community-calendar-study-rooms'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </section>
    </div>
    <?php
}

/** Renders the staff schedule calendar, entry form, and upcoming staff entries. */
function calsys_admin_staff_calendar_page() {
    global $wpdb;
    calsys_require_staff();
    calsys_admin_notice();

    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . calsys_table('staff_calendar') . "
        WHERE ends_at >= %s
        ORDER BY starts_at ASC LIMIT 200",
        current_time('mysql')
    ));
    $month = calsys_calendar_month();
    $grid_start = $month->modify('-' . (((int) $month->format('w') - calsys_week_start_day() + 7) % 7) . ' days');
    $grid_end = $grid_start->modify('+42 days');
    $calendar_entries = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, entry_type, starts_at, ends_at, all_day, room_id
        FROM " . calsys_table('staff_calendar') . "
        WHERE starts_at < %s AND ends_at >= %s
        ORDER BY starts_at ASC",
        $grid_end->format('Y-m-d H:i:s'),
        $grid_start->format('Y-m-d H:i:s')
    ));
    if (!is_array($entries) || !is_array($calendar_entries)) {
        error_log('CommonsCal: unable to load staff calendar admin page: ' . $wpdb->last_error);
        echo '<div class="wrap"><h1>' . esc_html__('Staff calendar', 'community-calendar-study-rooms') . '</h1><div class="notice notice-error"><p>' .
            esc_html__('Staff schedule data could not be loaded. Please check the site error log.', 'community-calendar-study-rooms') .
            '</p></div></div>';
        return;
    }
    $types = calsys_staff_types();
    foreach ($calendar_entries as $entry) {
        $entry->calendar_type = 'staff';
        $entry->calendar_detail = $types[$entry->entry_type] ?? __('Other', 'community-calendar-study-rooms');
    }
    ?>
    <div class="wrap calsys-admin-page calsys-admin-staff">
        <h1><?php esc_html_e('Staff calendar', 'community-calendar-study-rooms'); ?></h1>
        <p class="calsys-admin-intro"><?php esc_html_e('A private schedule for staff meetings, sick time, time off, and other internal entries. Select a day to add an entry or choose an existing entry to edit it.', 'community-calendar-study-rooms'); ?></p>
        <section class="calsys-admin-card calsys-admin-calendar-card">
            <div class="calsys-calendar-widget calsys-calendar-widget-staff">
                <div class="calsys-admin-calendar-toolbar">
                    <span><?php esc_html_e('Only staff with calendar access can view or manage this schedule.', 'community-calendar-study-rooms'); ?></span>
                    <button type="button" class="button button-primary" data-calsys-open-staff><?php esc_html_e('Add staff entry', 'community-calendar-study-rooms'); ?></button>
                </div>
                <?php echo calsys_render_calendar(__('Staff schedule', 'community-calendar-study-rooms'), $calendar_entries, $month, 'staff'); ?>
                <?php echo calsys_front_staff_form(); ?>
            </div>
        </section>

        <section class="calsys-admin-card">
        <div class="calsys-admin-card-heading"><div><h2><?php esc_html_e('Upcoming entries', 'community-calendar-study-rooms'); ?></h2><p><?php esc_html_e('Entries currently scheduled, ordered by start time.', 'community-calendar-study-rooms'); ?></p></div></div>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e('Entry', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('Type', 'community-calendar-study-rooms'); ?></th><th><?php esc_html_e('When', 'community-calendar-study-rooms'); ?></th><th></th></tr></thead>
            <tbody>
            <?php if ($entries) : foreach ($entries as $entry) : ?>
                <tr>
                    <td><?php echo esc_html($entry->title); ?></td>
                    <td><?php echo esc_html($types[$entry->entry_type] ?? $types['other']); ?></td>
                    <td>
                        <?php if ($entry->all_day) : ?>
                            <?php echo esc_html(mysql2date(get_option('date_format'), $entry->starts_at)); ?>
                            <?php if (substr($entry->starts_at, 0, 10) !== substr($entry->ends_at, 0, 10)) : ?>
                                - <?php echo esc_html(mysql2date(get_option('date_format'), $entry->ends_at)); ?>
                            <?php endif; ?>
                            (<?php esc_html_e('All day', 'community-calendar-study-rooms'); ?>)
                        <?php else : ?>
                            <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry->starts_at)); ?>
                            - <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry->ends_at)); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form class="calsys-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this staff calendar entry?', 'community-calendar-study-rooms')); ?>');">
                            <?php wp_nonce_field('calsys_delete_staff_entry_' . $entry->id); ?>
                            <input type="hidden" name="action" value="calsys_delete_staff_entry">
                            <input type="hidden" name="entry_id" value="<?php echo esc_attr($entry->id); ?>">
                            <?php submit_button(__('Delete', 'community-calendar-study-rooms'), 'delete small', 'submit', false); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="4"><?php esc_html_e('No upcoming staff entries.', 'community-calendar-study-rooms'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </section>
    </div>
    <?php
}

/** Validates and saves a staff schedule entry and its optional room reservation. */
function calsys_save_staff_entry($frontend = false) {
    calsys_require_staff();
    check_admin_referer($frontend ? 'calsys_front_save_staff_entry' : 'calsys_save_staff_entry');
    global $wpdb;

    $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
    $entry_type = sanitize_key(wp_unslash($_POST['entry_type'] ?? 'other'));
    $starts_at = calsys_parse_datetime($_POST['starts_at'] ?? '');
    $ends_at = calsys_parse_datetime($_POST['ends_at'] ?? '');
    $all_day = isset($_POST['all_day']);
    $entry_id = absint($_POST['entry_id'] ?? 0);
    $equipment = calsys_posted_equipment();
    if ($equipment === false) {
        calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
    }
    $room_id = absint($_POST['room_id'] ?? 0);
    $valid_types = array_keys(calsys_staff_types());
    if ($title === '' || strlen($title) > 200 || !$starts_at || !$ends_at ||
        !in_array($entry_type, $valid_types, true)) {
        calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
    }
    if ($room_id) {
        $room_is_active = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . calsys_table('rooms') . " WHERE id = %d AND active = 1",
            $room_id
        ));
        $existing_room_id = $entry_id ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT room_id FROM " . calsys_table('bookings') . " WHERE source_type = 'staff' AND source_id = %d ORDER BY id ASC LIMIT 1",
            $entry_id
        )) : 0;
        if (!$room_is_active && $existing_room_id !== $room_id) {
            calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
        }
    }

    if ($all_day) {
        $start_date = substr($starts_at, 0, 10);
        $end_date = substr($ends_at, 0, 10);
        if ($end_date < $start_date) {
            calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
        }
        $starts_at = $start_date . ' 00:00:00';
        $ends_at = $end_date . ' 23:59:59';
    } elseif ($ends_at <= $starts_at) {
        calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
    }

    $data = array(
        'title' => $title,
        'entry_type' => $entry_type,
        'starts_at' => $starts_at,
        'ends_at' => $ends_at,
        'all_day' => $all_day ? 1 : 0,
        'room_id' => $room_id,
    );
    if ($wpdb->query('START TRANSACTION') === false || !calsys_lock_linked_room_ids('staff', $entry_id, $room_id)) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
    }
    $staff_table = calsys_table('staff_calendar');
    if ($entry_id) {
        $updated = $wpdb->update($staff_table, $data, array('id' => $entry_id), array('%s', '%s', '%s', '%s', '%d', '%d'), array('%d'));
        if ($updated === false || !$wpdb->get_var($wpdb->prepare("SELECT id FROM $staff_table WHERE id = %d", $entry_id))) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
        }
        $saved_room = calsys_sync_linked_room_reservations('staff', $entry_id, $room_id, $starts_at, $ends_at);
    } else {
        $data['created_at'] = current_time('mysql');
        if (!$wpdb->insert($staff_table, $data, array('%s', '%s', '%s', '%s', '%d', '%d', '%s'))) {
            $wpdb->query('ROLLBACK');
            calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
        }
        $entry_id = (int) $wpdb->insert_id;
        $saved_room = calsys_sync_linked_room_reservations('staff', $entry_id, $room_id, $starts_at, $ends_at);
    }
    $equipment_result = $saved_room === false ? 'error' : calsys_sync_equipment('staff', $entry_id, $equipment, $starts_at, $ends_at, 'approved');
    if ($saved_room === false || $equipment_result !== 'saved' || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-staff-calendar', $equipment_result === 'conflict' ? 'equipment_conflict' : 'invalid');
    }
    calsys_action_redirect($frontend, 'calsys-staff-calendar', $saved_room ? 'staff_event_saved_room_conflicts' : 'staff_event_saved', $saved_room);
}
add_action('admin_post_calsys_save_staff_entry', 'calsys_save_staff_entry');

/** Handles the staff-entry save action submitted from a public calendar page. */
function calsys_front_save_staff_entry() {
    calsys_save_staff_entry(true);
}
add_action('admin_post_calsys_front_save_staff_entry', 'calsys_front_save_staff_entry');

/** Deletes a staff schedule entry and any room reservation linked to it. */
function calsys_delete_staff_entry() {
    calsys_require_staff();
    $frontend = !empty($_POST['calsys_frontend']);
    $entry_id = absint($_POST['entry_id'] ?? 0);
    check_admin_referer($frontend ? 'calsys_front_delete_staff_entry' : 'calsys_delete_staff_entry_' . $entry_id);
    if (!$entry_id) {
        calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
    }

    global $wpdb;
    if ($wpdb->query('START TRANSACTION') === false || !calsys_lock_linked_room_ids('staff', $entry_id)) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
    }
    $bookings_deleted = $wpdb->delete(calsys_table('bookings'), array('source_type' => 'staff', 'source_id' => $entry_id), array('%s', '%d'));
    $equipment_deleted = $wpdb->delete(calsys_table('equipment_reservations'), array('source_type' => 'staff', 'source_id' => $entry_id), array('%s', '%d'));
    $deleted = $wpdb->delete(calsys_table('staff_calendar'), array('id' => $entry_id), array('%d'));
    if ($bookings_deleted === false || $equipment_deleted === false || $deleted === false || $deleted === 0 || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        calsys_action_redirect($frontend, 'calsys-staff-calendar', 'invalid');
    }
    calsys_action_redirect($frontend, 'calsys-staff-calendar', 'staff_event_deleted');
}
add_action('admin_post_calsys_delete_staff_entry', 'calsys_delete_staff_entry');
add_action('admin_post_calsys_front_delete_staff_entry', 'calsys_delete_staff_entry');
