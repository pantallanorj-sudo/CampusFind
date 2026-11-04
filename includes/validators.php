<?php
/** Server-side input validation. Every function returns clean values plus a list of readable errors. */
require_once __DIR__ . '/functions.php';

function clean_text($value)
{
    $value = str_replace(array("\r\n", "\r"), "\n", (string) $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    return trim($value);
}

/** Trimmed text field from an array such as $_POST (non-strings become ''). */
function field($src, $key)
{
    return (isset($src[$key]) && is_string($src[$key])) ? clean_text($src[$key]) : '';
}

function field_int($src, $key)
{
    return (isset($src[$key]) && is_string($src[$key]) && preg_match('/^\d{1,9}$/', $src[$key]) === 1) ? (int) $src[$key] : 0;
}

function post_int($key)
{
    return field_int($_POST, $key);
}

function valid_email($email)
{
    return is_string($email) && strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password($password, $confirm = null)
{
    $errors = array();
    $password = (string) $password;
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password must be 72 characters or fewer.';
    } elseif (trim($password) === '') {
        $errors[] = 'Password cannot be only spaces.';
    }
    if ($confirm !== null && (string) $confirm !== $password) {
        $errors[] = 'The two passwords do not match.';
    }
    return $errors;
}

function validate_person_name($name)
{
    $n = text_len($name);
    return ($n < 2 || $n > 100) ? array('Enter a name between 2 and 100 characters.') : array();
}

/** Report fields shared by create (isCreate) and edit. Returns array($clean, $errors). */
function validate_report_input($src, $isCreate)
{
    $errors = array();
    $clean = array(
        'report_type' => field($src, 'report_type'),
        'category_id' => field_int($src, 'category_id'),
        'item_name' => field($src, 'item_name'),
        'description' => field($src, 'description'),
        'location' => field($src, 'location'),
        'date_occurred' => field($src, 'date_occurred'),
    );
    if ($isCreate && !in_array($clean['report_type'], array('lost', 'found'), true)) {
        $errors[] = 'Choose whether the item was lost or found.';
    }
    if ($clean['category_id'] < 1) {
        $errors[] = 'Choose a category.';
    }
    $n = text_len($clean['item_name']);
    if ($n < 2 || $n > 120) {
        $errors[] = 'Item name is required (2–120 characters).';
    }
    $n = text_len($clean['description']);
    if ($n < 5 || $n > 2000) {
        $errors[] = 'Description is required (5–2,000 characters).';
    }
    $n = text_len($clean['location']);
    if ($n < 2 || $n > 200) {
        $errors[] = 'Location is required (2–200 characters).';
    }
    if (!valid_date($clean['date_occurred'])) {
        $errors[] = 'Choose a valid date.';
    } elseif ($clean['date_occurred'] > today_local()) {
        $errors[] = 'The date cannot be in the future.';
    }
    return array($clean, $errors);
}

/** A free-text field with length limits. Returns array($value, $errors). */
function validate_text($src, $key, $label, $min, $max, $required = true)
{
    $v = field($src, $key);
    $n = text_len($v);
    if ($v === '' && !$required) {
        return array('', array());
    }
    if ($n < $min || $n > $max) {
        return array($v, array($label . ' must be between ' . $min . ' and ' . $max . ' characters.'));
    }
    return array($v, array());
}
