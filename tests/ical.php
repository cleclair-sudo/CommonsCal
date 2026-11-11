<?php
// Run with: php tests/ical.php
define('ABSPATH', __DIR__ . '/');
function add_action() {}
function wp_check_invalid_utf8($value) { return $value; }
function expect($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
}
require __DIR__ . '/../includes/ical.php';

expect(calsys_ical_text("A\\B, C; D\r\nNext") === 'A\\\\B\\, C\\; D\\nNext', 'iCalendar text must escape special characters');
$original = 'SUMMARY:' . str_repeat('é', 70);
$folded = calsys_ical_line($original);
foreach (explode("\r\n", trim($folded)) as $line) {
    expect(strlen($line) <= 75 && preg_match('//u', $line), 'Folded lines must be valid UTF-8 and at most 75 octets');
}
expect(str_replace("\r\n ", '', rtrim($folded, "\r\n")) === $original, 'Unfolded text must round-trip');
echo "iCalendar tests passed.\n";
