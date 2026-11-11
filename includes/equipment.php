<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Returns the equipment quantities already reserved by one calendar item. */
function calsys_equipment_for_source($source_type, $source_id, $lock = false) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT equipment_id, quantity FROM " . calsys_table('equipment_reservations') . " WHERE source_type = %s AND source_id = %d" . ($lock ? ' FOR UPDATE' : ''),
        $source_type,
        $source_id
    ));
    if ($wpdb->last_error || !is_array($rows)) {
        error_log('CommonsCal: unable to load equipment reservations: ' . $wpdb->last_error);
        return false;
    }
    $quantities = array();
    foreach ($rows as $row) {
        $quantities[(int) $row->equipment_id] = (int) $row->quantity;
    }
    return $quantities;
}

/** Returns the equipment names and quantities for staff review. */
function calsys_equipment_description($source_type, $source_id) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT e.name, r.quantity FROM " . calsys_table('equipment_reservations') . " r
        INNER JOIN " . calsys_table('equipment') . " e ON e.id = r.equipment_id
        WHERE r.source_type = %s AND r.source_id = %d ORDER BY e.name ASC",
        $source_type,
        $source_id
    ));
    if ($wpdb->last_error || !is_array($rows)) {
        error_log('CommonsCal: unable to load requested equipment: ' . $wpdb->last_error);
        return false;
    }
    $labels = array();
    foreach ($rows as $row) {
        $labels[] = $row->name . ' (' . (int) $row->quantity . ')';
    }
    return implode(', ', $labels);
}

/** Returns the maximum simultaneous quantity in a set of half-open intervals. */
function calsys_equipment_peak($reservations) {
    $points = array();
    foreach ($reservations as $reservation) {
        $points[$reservation->starts_at] = ($points[$reservation->starts_at] ?? 0) + (int) $reservation->quantity;
        $points[$reservation->ends_at] = ($points[$reservation->ends_at] ?? 0) - (int) $reservation->quantity;
    }
    ksort($points);
    $current = 0;
    $peak = 0;
    foreach ($points as $change) {
        $current += $change;
        $peak = max($peak, $current);
    }
    return $peak;
}

/** Validates the optional quantities submitted by a booking form. */
function calsys_posted_equipment() {
    if (!isset($_POST['equipment_present'])) {
        return null;
    }
    if (!isset($_POST['equipment_complete']) || $_POST['equipment_complete'] !== '1') {
        return false;
    }
    $raw = wp_unslash($_POST['equipment'] ?? array());
    if (!is_array($raw)) {
        return false;
    }
    $items = array();
    foreach ($raw as $id => $quantity) {
        if (!ctype_digit((string) $id) || (int) $id < 1 || !is_string($quantity) || !preg_match('/^\d{1,5}$/', $quantity)) {
            return false;
        }
        if ((int) $quantity > 0) {
            $items[(int) $id] = (int) $quantity;
        }
    }
    return $items;
}

/** Replaces a source's equipment reservations while holding inventory locks. */
function calsys_sync_equipment($source_type, $source_id, $items, $starts_at, $ends_at, $status) {
    global $wpdb;
    $reservation_table = calsys_table('equipment_reservations');
    $existing = calsys_equipment_for_source($source_type, $source_id, true);
    if ($existing === false) {
        return 'error';
    }
    if ($items === null) {
        $items = $existing;
    }
    $ids = array_values(array_unique(array_merge(array_keys($existing), array_keys($items))));
    sort($ids, SORT_NUMERIC);
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $inventory = $wpdb->get_results($wpdb->prepare(
            "SELECT id, quantity, active FROM " . calsys_table('equipment') . " WHERE id IN ($placeholders) ORDER BY id FOR UPDATE",
            $ids
        ));
        if ($wpdb->last_error || !is_array($inventory) || count($inventory) !== count($ids)) {
            error_log('CommonsCal: unable to lock equipment inventory: ' . $wpdb->last_error);
            return 'error';
        }
        foreach ($inventory as $item) {
            $quantity = $items[(int) $item->id] ?? 0;
            if (!$quantity) {
                continue;
            }
            if ((!$item->active && $quantity > ($existing[(int) $item->id] ?? 0)) || $quantity > (int) $item->quantity) {
                return 'conflict';
            }
            $overlaps = $wpdb->get_results($wpdb->prepare(
                "SELECT starts_at, ends_at, quantity FROM $reservation_table
                WHERE equipment_id = %d AND status IN ('pending', 'approved') AND starts_at < %s AND ends_at > %s
                AND NOT (source_type = %s AND source_id = %d) FOR UPDATE",
                $item->id,
                $ends_at,
                $starts_at,
                $source_type,
                $source_id
            ));
            if ($wpdb->last_error || !is_array($overlaps)) {
                error_log('CommonsCal: unable to check equipment availability: ' . $wpdb->last_error);
                return 'error';
            }
            foreach ($overlaps as $overlap) {
                $overlap->starts_at = max($starts_at, $overlap->starts_at);
                $overlap->ends_at = min($ends_at, $overlap->ends_at);
            }
            $overlaps[] = (object) array('starts_at' => $starts_at, 'ends_at' => $ends_at, 'quantity' => $quantity);
            if (calsys_equipment_peak($overlaps) > (int) $item->quantity) {
                return 'conflict';
            }
        }
    }
    if ($wpdb->delete($reservation_table, array('source_type' => $source_type, 'source_id' => $source_id)) === false) {
        error_log('CommonsCal: unable to replace equipment reservations: ' . $wpdb->last_error);
        return 'error';
    }
    foreach ($items as $id => $quantity) {
        if ($wpdb->insert($reservation_table, array(
            'equipment_id' => $id,
            'source_type' => $source_type,
            'source_id' => $source_id,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'quantity' => $quantity,
            'status' => $status,
        )) === false) {
            error_log('CommonsCal: unable to save equipment reservation: ' . $wpdb->last_error);
            return 'error';
        }
    }
    return 'saved';
}

/** Renders equipment quantity controls for a request or staff editor. */
function calsys_equipment_fields($selected = array()) {
    global $wpdb;
    $items = $wpdb->get_results("SELECT id, name, quantity, active FROM " . calsys_table('equipment') . " ORDER BY name ASC, id ASC");
    if ($wpdb->last_error || !is_array($items)) {
        error_log('CommonsCal: unable to load equipment inventory: ' . $wpdb->last_error);
        return '<p>' . esc_html__('Equipment is temporarily unavailable. Please contact staff.', 'community-calendar-study-rooms') . '</p>';
    }
    ob_start();
    ?>
    <fieldset class="calsys-equipment-fields" data-calsys-equipment-fields>
        <legend><?php esc_html_e('Equipment (optional)', 'community-calendar-study-rooms'); ?></legend>
        <input type="hidden" name="equipment_present" value="1">
        <p><?php esc_html_e('Enter the quantity needed. Availability is checked when you save.', 'community-calendar-study-rooms'); ?></p>
        <?php foreach ($items as $item) : ?>
            <?php if (!$item->active && !current_user_can('manage_calendar_system')) { continue; } ?>
            <label><?php echo esc_html($item->name); ?><?php if (!$item->active) : ?> (<?php esc_html_e('unavailable for new reservations', 'community-calendar-study-rooms'); ?>)<?php endif; ?>
                <input type="number" name="equipment[<?php echo esc_attr($item->id); ?>]" min="0" max="<?php echo esc_attr($item->quantity); ?>" step="1" value="<?php echo esc_attr($selected[(int) $item->id] ?? 0); ?>">
            </label>
        <?php endforeach; ?>
        <?php if (!$items) : ?><p><?php esc_html_e('No equipment is currently available.', 'community-calendar-study-rooms'); ?></p><?php endif; ?>
        <input type="hidden" name="equipment_complete" value="1">
    </fieldset>
    <?php
    return ob_get_clean();
}

/** Adds or updates equipment without invalidating existing reservations. */
function calsys_save_equipment() {
    calsys_require_staff();
    check_admin_referer('calsys_save_equipment');
    global $wpdb;
    $id = absint($_POST['equipment_id'] ?? 0);
    $name = isset($_POST['name']) && is_string($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $raw_quantity = $_POST['quantity'] ?? '';
    if ($name === '' || strlen($name) > 200 || !is_string($raw_quantity) || !preg_match('/^\d{1,5}$/', $raw_quantity)) {
        calsys_admin_redirect('calsys-settings', 'invalid');
    }
    $quantity = (int) $raw_quantity;
    $active = isset($_POST['active']) ? 1 : 0;
    if ($wpdb->query('START TRANSACTION') === false) {
        calsys_admin_redirect('calsys-settings', 'invalid');
    }
    if ($id) {
        $locked = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . calsys_table('equipment') . " WHERE id = %d FOR UPDATE", $id));
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT starts_at, ends_at, quantity FROM " . calsys_table('equipment_reservations') . "
            WHERE equipment_id = %d AND status IN ('pending', 'approved') AND ends_at > %s FOR UPDATE",
            $id,
            current_time('mysql')
        ));
        if (!$locked || $wpdb->last_error || !is_array($reservations)) {
            $wpdb->query('ROLLBACK');
            error_log('CommonsCal: unable to update equipment inventory: ' . $wpdb->last_error);
            calsys_admin_redirect('calsys-settings', 'invalid');
        }
        foreach ($reservations as $reservation) {
            $reservation->starts_at = max($reservation->starts_at, current_time('mysql'));
        }
        if (calsys_equipment_peak($reservations) > $quantity) {
            $wpdb->query('ROLLBACK');
            calsys_admin_redirect('calsys-settings', 'equipment_in_use');
        }
        $saved = $wpdb->update(calsys_table('equipment'), array('name' => $name, 'quantity' => $quantity, 'active' => $active), array('id' => $id));
    } else {
        $saved = $wpdb->insert(calsys_table('equipment'), array('name' => $name, 'quantity' => $quantity, 'active' => $active));
    }
    if ($saved === false || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        error_log('CommonsCal: unable to save equipment inventory: ' . $wpdb->last_error);
        calsys_admin_redirect('calsys-settings', 'invalid');
    }
    calsys_admin_redirect('calsys-settings', 'saved');
}
add_action('admin_post_calsys_save_equipment', 'calsys_save_equipment');

/** Renders the equipment inventory controls in Settings. */
function calsys_admin_equipment_settings() {
    global $wpdb;
    $items = $wpdb->get_results("SELECT id, name, quantity, active FROM " . calsys_table('equipment') . " ORDER BY name ASC, id ASC");
    if ($wpdb->last_error || !is_array($items)) {
        error_log('CommonsCal: unable to load equipment settings: ' . $wpdb->last_error);
        echo '<div class="notice notice-error"><p>' . esc_html__('Equipment inventory could not be loaded. Please check the site error log.', 'community-calendar-study-rooms') . '</p></div>';
        return;
    }
    ?>
    <section class="calsys-admin-card" id="calsys-settings-equipment">
        <h2><?php esc_html_e('Equipment', 'community-calendar-study-rooms'); ?></h2>
        <p><?php esc_html_e('Add items such as projectors, laptops, and clickers. Set the number available; uncheck an item to prevent new reservations without deleting its history.', 'community-calendar-study-rooms'); ?></p>
        <?php foreach (array_merge($items, array((object) array('id' => 0, 'name' => '', 'quantity' => 1, 'active' => 1))) as $item) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="calsys-equipment-inventory-row">
                <?php wp_nonce_field('calsys_save_equipment'); ?>
                <input type="hidden" name="action" value="calsys_save_equipment">
                <input type="hidden" name="equipment_id" value="<?php echo esc_attr($item->id); ?>">
                <label><?php esc_html_e('Item', 'community-calendar-study-rooms'); ?><input type="text" name="name" maxlength="200" required value="<?php echo esc_attr($item->name); ?>"></label>
                <label><?php esc_html_e('Quantity', 'community-calendar-study-rooms'); ?><input type="number" name="quantity" min="0" max="99999" step="1" required value="<?php echo esc_attr($item->quantity); ?>"></label>
                <label class="calsys-equipment-active"><input type="checkbox" name="active" value="1" <?php checked($item->active); ?>> <?php esc_html_e('Available', 'community-calendar-study-rooms'); ?></label>
                <button class="button <?php echo $item->id ? '' : 'button-primary'; ?>" type="submit"><?php echo $item->id ? esc_html__('Save item', 'community-calendar-study-rooms') : esc_html__('Add item', 'community-calendar-study-rooms'); ?></button>
            </form>
        <?php endforeach; ?>
    </section>
    <?php
}
