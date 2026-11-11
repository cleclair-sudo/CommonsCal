<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Validates one opening-hours interval or closed/unrestricted day. */
function calsys_normalize_hours($hours, $allow_unrestricted = true) {
    if (!is_array($hours) || !isset($hours['mode']) || !is_string($hours['mode']) ||
        !in_array($hours['mode'], $allow_unrestricted ? array('open', 'closed', 'unrestricted') : array('open', 'closed'), true)) {
        return false;
    }
    if ($hours['mode'] !== 'open') {
        return array('mode' => $hours['mode'], 'open' => '', 'close' => '');
    }
    $open = $hours['open'] ?? '';
    $close = $hours['close'] ?? '';
    if (!is_string($open) || !is_string($close) ||
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $open) ||
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $close) || $open >= $close) {
        return false;
    }
    return array('mode' => 'open', 'open' => $open, 'close' => $close);
}

/** Validates weekly hours and dated or annually recurring exceptions. */
function calsys_normalize_room_schedule($weekly, $holidays) {
    if (!is_array($weekly) || count($weekly) !== 7 || !is_array($holidays) || count($holidays) > 100) {
        return false;
    }
    $days = array();
    for ($day = 0; $day < 7; $day++) {
        $hours = calsys_normalize_hours($weekly[$day] ?? null);
        if ($hours === false) {
            return false;
        }
        $days[$day] = $hours;
    }
    $exceptions = array();
    $seen = array();
    foreach ($holidays as $holiday) {
        if (!is_array($holiday) || !isset($holiday['date']) || !is_string($holiday['date'])) {
            return false;
        }
        $date = $holiday['date'];
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        $hours = calsys_normalize_hours($holiday, false);
        if (!$parsed || ($errors && ($errors['warning_count'] || $errors['error_count'])) ||
            $parsed->format('Y-m-d') !== $date || $hours === false ||
            (isset($holiday['annual']) && !in_array($holiday['annual'], array(0, 1, '0', '1'), true))) {
            return false;
        }
        $annual = !empty($holiday['annual']) ? 1 : 0;
        $key = $annual ? 'annual:' . substr($date, 5) : 'date:' . $date;
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        $exceptions[] = array_merge(array('date' => $date, 'annual' => $annual), $hours);
    }
    return array('weekly' => $days, 'holidays' => $exceptions);
}

/** Resolves exact-date exceptions before annual exceptions and weekly hours. */
function calsys_room_hours_for_date($date, $rules = null) {
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    $errors = DateTimeImmutable::getLastErrors();
    if (!$day || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $day->format('Y-m-d') !== $date) {
        return false;
    }
    $rules = $rules ?? calsys_get_settings()['booking_rules'];
    $annual = null;
    foreach ($rules['holiday_hours'] as $holiday) {
        if (!$holiday['annual'] && $holiday['date'] === $date) {
            return $holiday;
        }
        if ($holiday['annual'] && substr($holiday['date'], 5) === substr($date, 5)) {
            $annual = $holiday;
        }
    }
    return $annual ?? $rules['weekly_hours'][(int) $day->format('w')];
}

/** Returns whether an interval is entirely inside its day's opening hours. */
function calsys_room_is_within_hours($date, $start, $end, $rules = null) {
    $hours = calsys_room_hours_for_date($date, $rules);
    if ($hours === false || $hours['mode'] === 'closed') {
        return false;
    }
    return $hours['mode'] === 'unrestricted' || ($start >= $hours['open'] && $end <= $hours['close']);
}
