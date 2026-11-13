<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Returns the local start and exclusive end of a valid newsletter month. */
function calsys_newsletter_month($month) {
    if (!is_string($month) || !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $month) ||
        (int) substr($month, 0, 4) < 1000 || $month === '9999-12') {
        return false;
    }
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', wp_timezone());
    if (!$start || $start->format('Y-m') !== $month) {
        return false;
    }
    return array($start, $start->modify('+1 month'));
}

/** Loads every published event overlapping the selected local month. */
function calsys_newsletter_events($start, $end) {
    global $wpdb;
    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT id, title, description, starts_at, ends_at, image_id
        FROM " . calsys_table('events') . "
        WHERE status = 'published' AND starts_at < %s AND ends_at > %s
        ORDER BY starts_at ASC, id ASC",
        $end->format('Y-m-d H:i:s'),
        $start->format('Y-m-d H:i:s')
    ));
    if ($wpdb->last_error || !is_array($events)) {
        error_log('CommonsCal: unable to load newsletter events: ' . $wpdb->last_error);
        return new WP_Error('newsletter_events', __('The newsletter events could not be loaded. Please check the site error log.', 'community-calendar-study-rooms'));
    }
    return $events;
}

/** Converts a local WordPress image into a safe, self-contained PDF image. */
function calsys_newsletter_image($attachment_id) {
    $path = get_attached_file($attachment_id);
    if (!wp_attachment_is_image($attachment_id) || !is_string($path) || !is_readable($path)) {
        error_log('CommonsCal: newsletter image is unavailable for attachment ' . $attachment_id);
        return new WP_Error('newsletter_image', __('A newsletter image could not be loaded. Check the site logo and event images in the Media Library.', 'community-calendar-study-rooms'));
    }
    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) {
        error_log('CommonsCal: unable to open newsletter image ' . $attachment_id . ': ' . $editor->get_error_message());
        return new WP_Error('newsletter_image', __('A newsletter image could not be processed. Check the site error log.', 'community-calendar-study-rooms'));
    }
    $size = $editor->get_size();
    if ($size['width'] > 600 || $size['height'] > 600) {
        $resized = $editor->resize(600, 600, false);
        if (is_wp_error($resized)) {
            error_log('CommonsCal: unable to resize newsletter image ' . $attachment_id . ': ' . $resized->get_error_message());
            return new WP_Error('newsletter_image', __('A newsletter image could not be processed. Check the site error log.', 'community-calendar-study-rooms'));
        }
    }
    if (!function_exists('wp_tempnam')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $temp = wp_tempnam('calsys-newsletter');
    if (!$temp || !is_file($temp)) {
        error_log('CommonsCal: unable to create a temporary newsletter image file.');
        return new WP_Error('newsletter_image', __('A temporary newsletter image could not be created. Check the site error log.', 'community-calendar-study-rooms'));
    }
    wp_delete_file($temp);
    if (file_exists($temp) || !@mkdir($temp, 0700)) {
        error_log('CommonsCal: unable to create a private newsletter image directory.');
        return new WP_Error('newsletter_image', __('A temporary newsletter image could not be created. Check the site error log.', 'community-calendar-study-rooms'));
    }
    $png = $temp . DIRECTORY_SEPARATOR . 'image.png';
    $files_to_delete = array($png);
    try {
        $saved = $editor->save($png, 'image/png');
        if (is_array($saved) && isset($saved['path']) && is_string($saved['path']) &&
            realpath(dirname($saved['path'])) === realpath($temp) &&
            preg_match('/^image\.[a-z0-9]+$/i', basename($saved['path']))) {
            $files_to_delete[] = $saved['path'];
        }
        if (is_wp_error($saved) || !is_array($saved) || empty($saved['path']) ||
            !is_readable($png) || realpath($saved['path']) !== realpath($png)) {
            $detail = is_wp_error($saved) ? $saved->get_error_message() : 'Image file was not saved.';
            error_log('CommonsCal: unable to convert newsletter image ' . $attachment_id . ': ' . $detail);
            return new WP_Error('newsletter_image', __('A newsletter image could not be processed. Check the site error log.', 'community-calendar-study-rooms'));
        }
        $data = file_get_contents($png);
        if ($data === false) {
            error_log('CommonsCal: unable to read converted newsletter image ' . $attachment_id);
            return new WP_Error('newsletter_image', __('A newsletter image could not be read. Check the site error log.', 'community-calendar-study-rooms'));
        }
        return 'data:image/png;base64,' . base64_encode($data);
    } finally {
        foreach (array_unique($files_to_delete) as $file) {
            if (file_exists($file)) {
                wp_delete_file($file);
                if (file_exists($file)) {
                    error_log('CommonsCal: unable to remove temporary newsletter image: ' . $file);
                }
            }
        }
        if (!@rmdir($temp)) {
            error_log('CommonsCal: unable to remove temporary newsletter directory: ' . $temp);
        }
    }
}

/** Builds the newsletter HTML using only escaped event content and converted images. */
function calsys_newsletter_html($start, $events, $images, $logo_id) {
    $date_format = get_option('date_format');
    $time_format = get_option('time_format');
    $timezone = wp_timezone();
    ob_start();
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><style>
        @page { margin: 40px; }
        body { font-family: DejaVu Sans, sans-serif; color: #202c38; font-size: 10pt; line-height: 1.45; }
        .logo { max-width: 180px; max-height: 75px; }
        h1 { font-size: 22pt; margin: 12px 0 2px; }
        .month { color: #526372; margin: 0 0 20px; }
        article { border-top: 1px solid #d5dde3; padding-top: 12px; margin-bottom: 18px; }
        h2 { color: #1d5f8a; font-size: 15pt; margin: 0 0 5px; }
        .time { font-weight: bold; margin: 0 0 8px; }
        .event-image { max-width: 240px; max-height: 160px; margin: 0 0 8px; }
        .description { margin: 0; }
    </style></head><body>
    <?php if ($logo_id && isset($images[$logo_id])) : ?><img class="logo" src="<?php echo esc_attr($images[$logo_id]); ?>" alt=""><?php endif; ?>
    <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
    <p class="month"><?php echo esc_html(sprintf(__('Events for %s', 'community-calendar-study-rooms'), wp_date('F Y', $start->getTimestamp(), $timezone))); ?></p>
    <?php if (!$events) : ?><p><?php esc_html_e('No published events for this month.', 'community-calendar-study-rooms'); ?></p><?php endif; ?>
    <?php foreach ($events as $event) :
        $event_start = new DateTimeImmutable($event->starts_at, $timezone);
        $event_end = new DateTimeImmutable($event->ends_at, $timezone);
        $start_label = wp_date($date_format . ' ' . $time_format, $event_start->getTimestamp(), $timezone);
        $end_label = wp_date($date_format . ' ' . $time_format, $event_end->getTimestamp(), $timezone);
        ?>
        <article>
            <h2><?php echo esc_html($event->title); ?></h2>
            <p class="time"><?php echo esc_html(sprintf(__('%1$s - %2$s', 'community-calendar-study-rooms'), $start_label, $end_label)); ?></p>
            <?php if (!empty($event->image_id) && isset($images[(int) $event->image_id])) : ?><img class="event-image" src="<?php echo esc_attr($images[(int) $event->image_id]); ?>" alt=""><?php endif; ?>
            <?php if ($event->description !== '') :
                $description = preg_replace('/<\s*(?:br\s*\/?|\/(?:p|div|li|h[1-6]|blockquote))\s*>/i', "\n", $event->description);
                ?><p class="description"><?php echo nl2br(esc_html(wp_strip_all_tags($description))); ?></p><?php
            endif; ?>
        </article>
    <?php endforeach; ?>
    </body></html>
    <?php
    return ob_get_clean();
}

/** Generates the selected month's PDF or returns a visible error. */
function calsys_newsletter_pdf($start, $end) {
    foreach (array('dom', 'mbstring', 'gd') as $extension) {
        if (!extension_loaded($extension)) {
            error_log('CommonsCal: newsletter PDF requires PHP extension ' . $extension);
            return new WP_Error('newsletter_extension', __('PDF generation requires the PHP DOM, mbstring, and GD extensions. Please contact the site administrator.', 'community-calendar-study-rooms'));
        }
    }
    $autoload = dirname(CALSYS_PLUGIN_FILE) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        error_log('CommonsCal: newsletter PDF dependencies are missing.');
        return new WP_Error('newsletter_dependency', __('The PDF library is missing. Reinstall the complete CommonsCal ZIP.', 'community-calendar-study-rooms'));
    }
    $events = calsys_newsletter_events($start, $end);
    if (is_wp_error($events)) {
        return $events;
    }
    $logo_id = absint(get_theme_mod('custom_logo'));
    $image_ids = $logo_id ? array($logo_id) : array();
    foreach ($events as $event) {
        if (!empty($event->image_id)) {
            $image_ids[] = (int) $event->image_id;
        }
    }
    $images = array();
    foreach (array_unique($image_ids) as $image_id) {
        $image = calsys_newsletter_image($image_id);
        if (is_wp_error($image)) {
            return $image;
        }
        $images[$image_id] = $image;
    }
    try {
        require_once $autoload;
        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml(calsys_newsletter_html($start, $events, $images, $logo_id), 'UTF-8');
        $pdf->setPaper('letter');
        $pdf->render();
        $output = $pdf->output();
        if (!is_string($output) || strpos($output, '%PDF-') !== 0) {
            throw new RuntimeException('PDF renderer returned invalid output.');
        }
        return $output;
    } catch (Throwable $error) {
        error_log('CommonsCal: unable to generate newsletter PDF: ' . $error->getMessage());
        return new WP_Error('newsletter_pdf', __('The newsletter PDF could not be generated. Please check the site error log.', 'community-calendar-study-rooms'));
    }
}

/** Downloads a staff-only newsletter for one validated month. */
function calsys_export_newsletter() {
    calsys_require_staff();
    check_admin_referer('calsys_export_newsletter');
    $month = isset($_POST['month']) && is_string($_POST['month']) ? wp_unslash($_POST['month']) : '';
    $range = calsys_newsletter_month($month);
    if (!$range) {
        wp_die(esc_html__('Choose a valid newsletter month.', 'community-calendar-study-rooms'));
    }
    $pdf = calsys_newsletter_pdf($range[0], $range[1]);
    if (is_wp_error($pdf)) {
        wp_die(esc_html($pdf->get_error_message()));
    }
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="commonscal-newsletter-' . $month . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
add_action('admin_post_calsys_export_newsletter', 'calsys_export_newsletter');
