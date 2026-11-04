<?php
/** Role and object-level authorization rules. Every sensitive page/action calls these on the server. */
require_once __DIR__ . '/functions.php';

function role_in($user, $roles)
{
    return is_array($user) && isset($user['role']) && in_array($user['role'], $roles, true);
}

function is_staff_user($user)
{
    return role_in($user, array('staff', 'admin'));
}

function is_admin_user($user)
{
    return role_in($user, array('admin'));
}

function require_student()
{
    return require_role(array('student'));
}

/** Staff operations are open to staff and administrators. */
function require_staff()
{
    return require_role(array('staff', 'admin'));
}

function require_admin()
{
    return require_role(array('admin'));
}

function report_claim_count($reportId, $statuses)
{
    $marks = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge(array((int) $reportId), $statuses);
    return db_count('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND status IN (' . $marks . ')', $params);
}

/** Who may open a report's detail page. */
function report_view_allowed($user, $report)
{
    if (!$user) {
        return false;
    }
    if (is_staff_user($user) || (int) $report['user_id'] === (int) $user['id']) {
        return true;
    }
    if (in_array($report['status'], array('open', 'claim_pending'), true)) {
        return true;
    }
    return db_count('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND claimant_id = ?', array((int) $report['id'], (int) $user['id'])) > 0;
}

/** array(allowed, reason). Owners may edit only untouched open reports; staff may correct any live report. */
function report_edit_check($user, $report)
{
    if (!$user) {
        return array(false, 'Please log in first.');
    }
    $staff = is_staff_user($user);
    $owner = (int) $report['user_id'] === (int) $user['id'];
    if (!$staff && !$owner) {
        return array(false, 'You can only edit your own reports.');
    }
    if (in_array($report['status'], array('returned', 'archived'), true)) {
        return array(false, 'Returned and archived reports are locked to protect the record.');
    }
    if (!$staff) {
        if ($report['status'] !== 'open') {
            return array(false, 'This report can no longer be edited because it is ' . str_replace('_', ' ', $report['status']) . '.');
        }
        if (report_claim_count($report['id'], array('pending', 'under_review', 'needs_info', 'approved', 'returned')) > 0) {
            return array(false, 'This report has a claim in progress, so it can no longer be edited. Ask staff for help.');
        }
    }
    return array(true, '');
}

/** array(allowed, reason) for the owner "delete" (withdraw) action. */
function report_withdraw_check($user, $report)
{
    if (!$user || (int) $report['user_id'] !== (int) $user['id']) {
        return array(false, 'You can only delete your own reports.');
    }
    if ($report['status'] !== 'open') {
        return array(false, 'Only open reports can be deleted. This one is ' . str_replace('_', ' ', $report['status']) . '.');
    }
    if (report_claim_count($report['id'], array('pending', 'under_review', 'needs_info', 'approved', 'returned')) > 0) {
        return array(false, 'This report has a claim in progress, so it cannot be deleted. Contact staff to close it.');
    }
    return array(true, '');
}

function claim_view_allowed($user, $claim)
{
    if (!$user) {
        return false;
    }
    return is_staff_user($user) || (int) $claim['claimant_id'] === (int) $user['id'];
}

function evidence_view_allowed($user, $evidence, $claim)
{
    if (!$user) {
        return false;
    }
    return is_staff_user($user)
        || (int) $evidence['user_id'] === (int) $user['id']
        || (int) $claim['claimant_id'] === (int) $user['id'];
}
