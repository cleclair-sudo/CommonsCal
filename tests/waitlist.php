<?php
// Run with: php tests/waitlist.php
define('ABSPATH', __DIR__ . '/');
function add_action() {}
function calsys_table($name) { return 'wp_calsys_' . $name; }
function calsys_event_registration_is_open($start) { return true; }
function calsys_event_capacity($capacity) { return (int) $capacity; }
function current_time($format) { return '2029-01-01 00:00:00'; }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
class WaitlistTestDatabase {
    public $last_error = '';
    public $count = 1;
    public $waiting;
    public $inserted = array();
    public function __construct() {
        $this->waiting = array(
            (object) array('id' => 1, 'name' => 'First', 'email' => 'first@example.test', 'registration_fields' => '[]', 'reminder_opt_in' => 0),
            (object) array('id' => 2, 'name' => 'Second', 'email' => 'second@example.test', 'registration_fields' => '[]', 'reminder_opt_in' => 0),
        );
    }
    public function prepare($sql, ...$args) { return $sql; }
    public function get_var($sql) { return $this->count; }
    public function get_results($sql) { return $this->waiting; }
    public function insert($table, $data) { $this->inserted[] = $data; return 1; }
    public function delete($table, $where, $format) {
        $this->waiting = array_values(array_filter($this->waiting, function ($item) use ($where) { return $item->id !== $where['id']; }));
        return 1;
    }
}
require __DIR__ . '/../includes/waitlist.php';
$wpdb = new WaitlistTestDatabase();
$event = (object) array('id' => 9, 'status' => 'published', 'registration_enabled' => 1, 'starts_at' => '2030-01-01 10:00:00', 'capacity' => 1);
expect(calsys_promote_waitlist_locked($event) === array(), 'A full event must not promote the waitlist');
$wpdb->count = 0;
$promoted = calsys_promote_waitlist_locked($event);
expect(count($promoted) === 1 && $promoted[0]->email === 'first@example.test', 'The earliest waitlist entry must be promoted first');
expect(count($wpdb->waiting) === 1 && $wpdb->waiting[0]->email === 'second@example.test', 'Unpromoted entries must remain on the waitlist');
$event->status = 'draft';
expect(calsys_promote_waitlist_locked($event) === array(), 'Draft events must not promote waitlist entries');
echo "Waitlist tests passed.\n";
