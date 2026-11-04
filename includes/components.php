<?php
/** Small HTML builders shared by many pages. Every dynamic value goes through e(). */
require_once __DIR__ . '/functions.php';

function status_label($status)
{
    static $labels = array(
        'open' => 'Open', 'claim_pending' => 'Claim pending', 'returned' => 'Returned', 'closed' => 'Closed', 'archived' => 'Archived',
        'pending' => 'Pending', 'under_review' => 'Under review', 'needs_info' => 'Needs info', 'approved' => 'Approved',
        'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
    );
    $status = (string) $status;
    return isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));
}

function status_choices($statuses)
{
    $out = array();
    foreach ($statuses as $s) {
        $out[$s] = status_label($s);
    }
    return $out;
}

function badge($status)
{
    return '<span class="badge badge-' . e($status) . '">' . e(status_label($status)) . '</span>';
}

function type_pill($type)
{
    return '<span class="pill ' . e($type) . '">' . e(ucfirst((string) $type)) . '</span>';
}

/** Public URL of an item photo. Only server-generated names inside uploads/items/ are accepted. */
function upload_url($relative)
{
    if (!is_string($relative) || preg_match('/^items\/[a-f0-9]{32}\.(jpg|png|webp)$/', $relative) !== 1) {
        return '';
    }
    return url('uploads/' . $relative);
}

/** Private evidence is only reachable through this permission-checked endpoint. */
function evidence_url($evidenceId)
{
    return url('actions/serve-evidence.php?id=' . (int) $evidenceId);
}

function hidden_field($name, $value)
{
    return '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
}

function select_options($options, $selected, $placeholder = null)
{
    $html = '';
    if ($placeholder !== null) {
        $html .= '<option value="">' . e($placeholder) . '</option>';
    }
    foreach ($options as $value => $label) {
        $html .= '<option value="' . e($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

function empty_state($title, $text, $actionHtml = '')
{
    return '<div class="empty"><strong>' . e($title) . '</strong><p>' . e($text) . '</p>' . $actionHtml . '</div>';
}

function breadcrumbs_html($crumbs)
{
    $parts = array();
    foreach ($crumbs as $c) {
        $parts[] = ($c[1] === null) ? '<span aria-current="page">' . e($c[0]) . '</span>' : '<a href="' . e(url($c[1])) . '">' . e($c[0]) . '</a>';
    }
    return '<nav class="breadcrumbs" aria-label="Breadcrumb">' . implode('<span class="sep">/</span>', $parts) . '</nav>';
}

function item_photo_html($report, $class = 'card-photo')
{
    $src = upload_url(isset($report['image_path']) ? $report['image_path'] : '');
    if ($src !== '') {
        return '<img class="' . e($class) . '" src="' . e($src) . '" alt="Photo of ' . e($report['item_name']) . '" loading="lazy">';
    }
    $letter = function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr((string) $report['item_name'], 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr((string) $report['item_name'], 0, 1));
    return '<div class="' . e($class) . ' placeholder" aria-hidden="true">' . e($letter) . '</div>';
}

function item_card_html($r)
{
    return '<article class="item-card">' . item_photo_html($r) . '<div class="card-body">'
        . '<div class="card-tags">' . type_pill($r['report_type']) . ' ' . badge($r['status']) . '</div>'
        . '<h3>' . e($r['item_name']) . '</h3>'
        . '<p class="card-meta">' . e($r['category_name']) . ' · ' . e($r['location']) . '</p>'
        . '<p class="card-desc">' . e(text_cut($r['description'], 110)) . '</p>'
        . '<small>' . ($r['report_type'] === 'lost' ? 'Lost' : 'Found') . ' on ' . e(fmt_date($r['date_occurred'])) . '</small>'
        . '<a class="text-link" href="' . e(url('student/item-details.php?id=' . (int) $r['id'])) . '">View details</a>'
        . '</div></article>';
}

function pager_html($pg, $path, $params)
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination"><span class="muted">Page ' . (int) $pg['page'] . ' of ' . (int) $pg['pages']
        . ' · ' . e(plural($pg['total'], 'result')) . '</span><span class="pages">';
    if ($pg['page'] > 1) {
        $p = $params;
        $p['page'] = $pg['page'] - 1;
        $html .= '<a href="' . e(page_url($path, $p)) . '" rel="prev">Previous</a>';
    }
    $prev = 0;
    for ($i = 1; $i <= $pg['pages']; $i++) {
        if ($i !== 1 && $i !== $pg['pages'] && abs($i - $pg['page']) > 2) {
            continue;
        }
        if ($prev > 0 && $i - $prev > 1) {
            $html .= '<span class="gap">…</span>';
        }
        $p = $params;
        $p['page'] = $i;
        $html .= ($i === $pg['page']) ? '<span class="current" aria-current="page">' . $i . '</span>' : '<a href="' . e(page_url($path, $p)) . '">' . $i . '</a>';
        $prev = $i;
    }
    if ($pg['page'] < $pg['pages']) {
        $p = $params;
        $p['page'] = $pg['page'] + 1;
        $html .= '<a href="' . e(page_url($path, $p)) . '" rel="next">Next</a>';
    }
    return $html . '</span></nav>';
}

/** rows need: changed_at, old_status, new_status, notes, actor_name (nullable). */
function timeline_html($rows, $emptyText = 'No history yet.')
{
    if (count($rows) === 0) {
        return '<p class="muted">' . e($emptyText) . '</p>';
    }
    $html = '<ol class="timeline">';
    foreach ($rows as $h) {
        $same = ($h['old_status'] !== null && $h['old_status'] === $h['new_status']);
        if ($same) {
            $title = 'Details updated';
        } elseif ($h['old_status'] === null || $h['old_status'] === '') {
            $title = 'Created as ' . status_label($h['new_status']);
        } else {
            $title = status_label($h['old_status']) . ' → ' . status_label($h['new_status']);
        }
        $html .= '<li><span class="dot" aria-hidden="true"></span><div><strong>' . e($title) . '</strong> <time>' . e(fmt_dt($h['changed_at'])) . '</time>';
        $html .= '<p class="muted">By ' . e(!empty($h['actor_name']) ? $h['actor_name'] : 'System') . '</p>';
        if (!empty($h['notes'])) {
            $html .= '<p>' . nl2br_e($h['notes']) . '</p>';
        }
        $html .= '</div></li>';
    }
    return $html . '</ol>';
}

/** Sidebar groups per role. Item = array(label, path[, array of extra scripts that keep it highlighted]). */
function nav_items($role)
{
    if ($role === 'admin') {
        return array(
            array('label' => '', 'items' => array(array('Dashboard', 'admin/dashboard.php'))),
            array('label' => 'Administration', 'items' => array(
                array('Users', 'admin/manage-users.php'),
                array('Categories', 'admin/manage-categories.php'),
                array('System settings', 'admin/system-settings.php'),
                array('Status history', 'admin/status-history.php'),
            )),
            array('label' => 'Operations', 'items' => array(
                array('Item reports', 'admin/manage-reports.php'),
                array('Claims', 'admin/manage-claims.php', array('staff/verify-claims.php')),
                array('Handover', 'staff/handover.php'),
                array('Unclaimed items', 'staff/unclaimed-items.php'),
                array('Archived reports', 'admin/archived-reports.php'),
                array('Reports & export', 'admin/reports.php'),
            )),
            array('label' => 'Browse', 'items' => array(
                array('Browse items', 'student/browse-items.php', array('student/item-details.php')),
                array('Report an item', 'student/report-item.php'),
                array('Notifications', 'staff/notifications.php'),
                array('Returned history', 'public/claimed-items-history.php'),
            )),
        );
    }
    if ($role === 'staff') {
        return array(
            array('label' => '', 'items' => array(array('Dashboard', 'staff/dashboard.php'))),
            array('label' => 'Items', 'items' => array(
                array('Manage items', 'staff/manage-items.php'),
                array('Unclaimed & archive', 'staff/unclaimed-items.php'),
                array('Report an item', 'student/report-item.php'),
            )),
            array('label' => 'Claims', 'items' => array(
                array('Claim queue', 'staff/manage-claims.php', array('staff/verify-claims.php')),
                array('Handover', 'staff/handover.php'),
            )),
            array('label' => 'Insights', 'items' => array(
                array('Reports & export', 'staff/reports.php'),
                array('Notifications', 'staff/notifications.php'),
                array('Browse items', 'student/browse-items.php', array('student/item-details.php')),
                array('Returned history', 'public/claimed-items-history.php'),
            )),
        );
    }
    return array(
        array('label' => '', 'items' => array(
            array('Dashboard', 'student/dashboard.php'),
            array('Browse items', 'student/browse-items.php', array('student/item-details.php')),
            array('Report an item', 'student/report-item.php'),
        )),
        array('label' => 'My activity', 'items' => array(
            array('My reports', 'student/my-reports.php', array('student/edit-report.php')),
            array('My claims', 'student/my-claims.php', array('student/submit-claim.php')),
            array('Notifications', 'student/notifications.php'),
        )),
        array('label' => 'Public', 'items' => array(array('Returned history', 'public/claimed-items-history.php'))),
    );
}

/** Sticky form value: $old[$key] if present, otherwise $default. */
function ov($old, $key, $default = '')
{
    return (is_array($old) && isset($old[$key]) && is_string($old[$key])) ? $old[$key] : $default;
}
