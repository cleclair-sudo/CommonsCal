<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Validates the inclusive local-date range used by reports. */
function calsys_report_dates($start, $end) {
    if (!is_string($start) || !is_string($end)) {
        return false;
    }
    $dates = array();
    foreach (array($start, $end) as $value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) {
            return false;
        }
        $dates[] = $date;
    }
    return $dates[0] <= $dates[1] ? array($dates[0]->format('Y-m-d'), $dates[1]->format('Y-m-d')) : false;
}

/** Loads one report using local dates and the same approved-usage definition as the UI. */
function calsys_report_data($type, $start, $end) {
    global $wpdb;
    $next_day = DateTimeImmutable::createFromFormat('!Y-m-d', $end, wp_timezone())->modify('+1 day')->format('Y-m-d');
    if ($type === 'rooms') {
        $headers = array(__('Room', 'community-calendar-study-rooms'), __('Date', 'community-calendar-study-rooms'), __('Start', 'community-calendar-study-rooms'), __('End', 'community-calendar-study-rooms'), __('Booking type', 'community-calendar-study-rooms'));
        $sql = "SELECT r.name AS room, b.booking_date AS date, b.starts_at AS start_time, b.ends_at AS end_time, b.source_type AS booking_type
            FROM " . calsys_table('bookings') . " b INNER JOIN " . calsys_table('rooms') . " r ON r.id = b.room_id
            WHERE b.status = 'approved' AND b.booking_date >= %s AND b.booking_date < %s ORDER BY b.booking_date, b.starts_at, b.id";
        $args = array($start, $next_day);
    } elseif ($type === 'equipment') {
        $headers = array(__('Equipment', 'community-calendar-study-rooms'), __('Quantity', 'community-calendar-study-rooms'), __('Start', 'community-calendar-study-rooms'), __('End', 'community-calendar-study-rooms'), __('Reservation type', 'community-calendar-study-rooms'));
        $sql = "SELECT e.name AS equipment, r.quantity, r.starts_at, r.ends_at, r.source_type AS reservation_type
            FROM " . calsys_table('equipment_reservations') . " r INNER JOIN " . calsys_table('equipment') . " e ON e.id = r.equipment_id
            WHERE r.status = 'approved' AND r.starts_at < %s AND r.ends_at > %s ORDER BY r.starts_at, e.name, r.id";
        $args = array($next_day . ' 00:00:00', $start . ' 00:00:00');
    } elseif ($type === 'events') {
        $headers = array(__('Event', 'community-calendar-study-rooms'), __('Start', 'community-calendar-study-rooms'), __('End', 'community-calendar-study-rooms'), __('Category', 'community-calendar-study-rooms'), __('Status', 'community-calendar-study-rooms'), __('Registrations', 'community-calendar-study-rooms'));
        $sql = "SELECT e.title, e.starts_at, e.ends_at, e.category, e.status,
            (SELECT COUNT(*) FROM " . calsys_table('registrations') . " r WHERE r.event_id = e.id) AS registration_count
            FROM " . calsys_table('events') . " e WHERE e.starts_at < %s AND e.ends_at > %s ORDER BY e.starts_at, e.id";
        $args = array($next_day . ' 00:00:00', $start . ' 00:00:00');
    } else {
        return false;
    }
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
    if ($wpdb->last_error || !is_array($rows)) {
        error_log('CommonsCal: unable to load ' . $type . ' report: ' . $wpdb->last_error);
        return false;
    }
    return array('headers' => $headers, 'rows' => $rows);
}

/** Prevents spreadsheet programs from interpreting data cells as formulas. */
function calsys_report_csv_cell($value) {
    $value = (string) $value;
    return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'" . $value : $value;
}

/** Renders date-filtered room, equipment, and event report previews. */
function calsys_admin_reports_page() {
    calsys_require_staff();
    $start = isset($_GET['start']) ? wp_unslash($_GET['start']) : current_time('Y-m') . '-01';
    $end = isset($_GET['end']) ? wp_unslash($_GET['end']) : current_time('Y-m-d');
    $dates = calsys_report_dates($start, $end);
    $types = array('rooms' => __('Room usage', 'community-calendar-study-rooms'), 'equipment' => __('Equipment usage', 'community-calendar-study-rooms'), 'events' => __('Events', 'community-calendar-study-rooms'));
    ?>
    <div class="wrap calsys-admin-page">
        <h1><?php esc_html_e('Reports', 'community-calendar-study-rooms'); ?></h1>
        <p class="calsys-admin-intro"><?php esc_html_e('Choose an inclusive date range to view and download separate CSV reports. Room and equipment usage include approved reservations; events include published and draft events overlapping the range.', 'community-calendar-study-rooms'); ?></p>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="calsys-report-filter">
            <input type="hidden" name="page" value="calsys-reports">
            <label><?php esc_html_e('From', 'community-calendar-study-rooms'); ?> <input type="date" name="start" required value="<?php echo esc_attr(is_string($start) ? $start : ''); ?>"></label>
            <label><?php esc_html_e('Through', 'community-calendar-study-rooms'); ?> <input type="date" name="end" required value="<?php echo esc_attr(is_string($end) ? $end : ''); ?>"></label>
            <button type="submit" class="button button-primary"><?php esc_html_e('Show reports', 'community-calendar-study-rooms'); ?></button>
        </form>
        <?php if (!$dates) : ?>
            <div class="notice notice-error"><p><?php esc_html_e('Choose a valid date range with the start on or before the end.', 'community-calendar-study-rooms'); ?></p></div>
        <?php else : foreach ($types as $type => $title) :
            $report = calsys_report_data($type, $dates[0], $dates[1]);
            $export_url = wp_nonce_url(add_query_arg(array('action' => 'calsys_export_report', 'type' => $type, 'start' => $dates[0], 'end' => $dates[1]), admin_url('admin-post.php')), 'calsys_export_report');
            ?>
            <section class="calsys-admin-card">
                <div class="calsys-admin-card-heading"><h2><?php echo esc_html($title); ?></h2>
                    <?php if ($report !== false) : ?><a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Download CSV', 'community-calendar-study-rooms'); ?></a><?php endif; ?>
                </div>
                <?php if ($report === false) : ?>
                    <p><?php esc_html_e('This report could not be loaded. Please check the site error log.', 'community-calendar-study-rooms'); ?></p>
                <?php elseif (!$report['rows']) : ?>
                    <p class="calsys-admin-empty"><?php esc_html_e('No matching records for this date range.', 'community-calendar-study-rooms'); ?></p>
                <?php else : ?>
                    <div class="calsys-registration-table-scroll"><table class="widefat striped">
                        <thead><tr><?php foreach ($report['headers'] as $header) : ?><th scope="col"><?php echo esc_html($header); ?></th><?php endforeach; ?></tr></thead>
                        <tbody><?php foreach ($report['rows'] as $row) : ?><tr><?php foreach ($row as $value) : ?><td><?php echo esc_html($value); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                    </table></div>
                <?php endif; ?>
            </section>
        <?php endforeach; endif; ?>
    </div>
    <?php
}

/** Downloads a capability-protected CSV without exposing patron contact information. */
function calsys_export_report() {
    calsys_require_staff();
    check_admin_referer('calsys_export_report');
    $type = isset($_GET['type']) && is_string($_GET['type']) ? sanitize_key(wp_unslash($_GET['type'])) : '';
    $dates = calsys_report_dates(wp_unslash($_GET['start'] ?? ''), wp_unslash($_GET['end'] ?? ''));
    if (!$dates || !in_array($type, array('rooms', 'equipment', 'events'), true)) {
        wp_die(esc_html__('Invalid report or date range.', 'community-calendar-study-rooms'));
    }
    $report = calsys_report_data($type, $dates[0], $dates[1]);
    if ($report === false) {
        wp_die(esc_html__('The report could not be loaded. Please check the site error log.', 'community-calendar-study-rooms'));
    }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="calsys-' . $type . '-' . $dates[0] . '-to-' . $dates[1] . '.csv"');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        wp_die(esc_html__('The CSV could not be created.', 'community-calendar-study-rooms'));
    }
    fputcsv($output, array_map('calsys_report_csv_cell', $report['headers']), ',', '"', '');
    foreach ($report['rows'] as $row) {
        fputcsv($output, array_map('calsys_report_csv_cell', array_values($row)), ',', '"', '');
    }
    fclose($output);
    exit;
}
add_action('admin_post_calsys_export_report', 'calsys_export_report');
