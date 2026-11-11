<?php
// Run with: php tests/event-series.php
define('ABSPATH', __DIR__ . '/');
function wp_timezone() { return new DateTimeZone('America/New_York'); }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/event-series.php';

expect(calsys_shift_event_datetime('2030-03-03 10:00:00', 7 * 86400) === '2030-03-10 10:00:00', 'A date shift must preserve wall time across DST');
expect(calsys_shift_event_datetime('2030-03-10 01:30:00', 3600) === false, 'A shift into a nonexistent DST time must be rejected');
expect(calsys_shift_event_datetime('2030-11-03 10:00:00', -3600) === '2030-11-03 09:00:00', 'Negative time shifts must be supported');
echo "Event series tests passed.\n";
