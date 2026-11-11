<?php
// Run with: php tests/room-conflicts.php
define('ABSPATH', __DIR__ . '/');
class BookingRedirect extends Exception {
    public $notice;
    public function __construct($notice) { $this->notice = $notice; }
}
class BookingDatabase {
    public $insert_id = 12;
    public $inserted = array();
    public function query($sql) { return 1; }
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return 1; }
    public function insert($table, $data) { $this->inserted[] = $data; return 1; }
}
function add_action() {}
function calsys_require_staff() { if (!$GLOBALS['staff_authorized']) { throw new RuntimeException('Not authorized'); } }
function check_admin_referer() {}
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function sanitize_textarea_field($value) { return $value; }
function current_time($format) { return '2026-11-11 12:00:00'; }
function calsys_posted_equipment() { return array(); }
function calsys_valid_booking_input() {
    return array('room_id' => 1, 'room_name' => 'Room', 'name' => 'Patron', 'email' => 'test@example.invalid',
        'date' => '2026-12-01', 'start' => '10:00:00', 'end' => '11:00:00');
}
function calsys_table($name) { return $name; }
function calsys_booking_conflict() { return $GLOBALS['conflict']; }
function calsys_get_settings() { return array('room_approval_required' => 1); }
function calsys_sync_equipment() { return 'saved'; }
function calsys_room_is_within_hours() { return true; }
function calsys_action_redirect($frontend, $page, $notice) { throw new BookingRedirect($notice); }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/room-actions.php';

$wpdb = new BookingDatabase();
$staff_authorized = true;
$conflict = true;
$_POST = array('room_id' => '1');
try { calsys_save_booking(true); expect(false, 'Expected staff conflict redirect'); } catch (BookingRedirect $result) {
    expect($result->notice === 'room_conflict' && !$wpdb->inserted, 'Staff conflict must require acknowledgment');
}
$_POST['confirm_room_conflict'] = '1';
try { calsys_save_booking(false); expect(false, 'Expected public conflict redirect'); } catch (BookingRedirect $result) {
    expect($result->notice === 'room_conflict' && !$wpdb->inserted, 'Public requests must not override conflicts');
}
try { calsys_save_booking(true); expect(false, 'Expected staff success redirect'); } catch (BookingRedirect $result) {
    expect($result->notice === 'booking_saved' && count($wpdb->inserted) === 1, 'Acknowledged staff double booking was not saved');
}
$staff_authorized = false;
try { calsys_save_booking(true); expect(false, 'Unauthorized staff booking'); } catch (RuntimeException $error) {}
echo "Room conflict override passed.\n";
