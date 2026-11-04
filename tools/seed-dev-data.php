<?php
/**
 * DEVELOPMENT SEED DATA - local use only. Command line only.
 *
 *   php tools/seed-dev-data.php [--allow-existing]
 *
 *  - Creates four demo accounts (admin, staff, two students) with RANDOM passwords that are printed once.
 *    No password is stored in this file or in the README.
 *  - Creates demo reports and claims ONLY when item_reports is empty, so running the tool twice never duplicates records.
 *  - Refuses to run if real accounts exist (any email that does not end in @campusfind.test) unless --allow-existing is given.
 *  - Never deletes or overwrites anything.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This tool can only be run from the command line.');
}
require_once __DIR__ . '/../includes/auth.php';

$args = isset($argv) ? $argv : array();
if (db_count("SELECT COUNT(*) FROM users WHERE email NOT LIKE '%@campusfind.test'") > 0 && !in_array('--allow-existing', $args, true)) {
    fwrite(STDERR, 'Refusing to seed: this database already contains real accounts. Use a separate development database, or pass --allow-existing.' . PHP_EOL);
    exit(1);
}

$accounts = array(
    array('Demo Admin', 'admin@campusfind.test', 'admin'),
    array('Demo Staff', 'staff@campusfind.test', 'staff'),
    array('Demo Student One', 'student1@campusfind.test', 'student'),
    array('Demo Student Two', 'student2@campusfind.test', 'student'),
);
$users = array();
foreach ($accounts as $a) {
    $row = db_one('SELECT id, full_name, email, role FROM users WHERE email = ?', array($a[1]));
    if ($row) {
        echo 'exists   ' . $a[1] . PHP_EOL;
        $users[$a[2] . (isset($users[$a[2]]) ? '2' : '')] = $row;
        continue;
    }
    $password = bin2hex(random_bytes(6)) . 'Aa1';
    $r = UserService::insert($a[0], $a[1], $password, $a[2]);
    if (!$r['ok']) {
        fwrite(STDERR, 'Could not create ' . $a[1] . ': ' . implode(' ', $r['errors']) . PHP_EOL);
        exit(1);
    }
    echo 'created  ' . str_pad($a[1], 28) . $a[2] . str_pad('', 9 - strlen($a[2])) . 'password: ' . $password . PHP_EOL;
    $users[$a[2] . (isset($users[$a[2]]) ? '2' : '')] = array('id' => $r['id'], 'full_name' => $a[0], 'email' => $a[1], 'role' => $a[2]);
}
$admin = $users['admin'];
$staff = $users['staff'];
$s1 = $users['student'];
$s2 = $users['student2'];

if (db_count('SELECT COUNT(*) FROM item_reports') > 0) {
    echo 'Reports already exist, so demo reports were skipped (nothing duplicated).' . PHP_EOL;
    exit(0);
}

function seed_cat($name)
{
    $id = db_scalar('SELECT id FROM categories WHERE name = ?', array($name));
    return $id === null ? (int) db_scalar('SELECT MIN(id) FROM categories') : (int) $id;
}
function seed_report($user, $type, $name, $catName, $desc, $loc, $daysAgo)
{
    $r = ReportService::create($user, array(
        'report_type' => $type, 'category_id' => (string) seed_cat($catName), 'item_name' => $name,
        'description' => $desc, 'location' => $loc, 'date_occurred' => date('Y-m-d', time() - $daysAgo * 86400),
    ), null, true);
    if (!$r['ok']) {
        fwrite(STDERR, 'Seed failed: ' . implode(' ', $r['errors']) . PHP_EOL);
        exit(1);
    }
    return (int) $r['id'];
}

$umbrella = seed_report($s1, 'found', 'Black folding umbrella', 'Other', 'Compact umbrella left near the canteen entrance.', 'Canteen entrance', 2);
$bottle = seed_report($s1, 'found', 'Blue insulated water bottle', 'Other', 'Metal bottle with a dented base.', 'Gymnasium bleachers', 3);
$phone = seed_report($s1, 'found', 'Phone with cracked screen', 'Electronics', 'Black smartphone, cracked top-left corner.', 'Library, 2nd floor', 1);
$watch = seed_report($s1, 'found', 'Silver wristwatch', 'Other', 'Analog watch with a leather strap.', 'Room 204', 40);
$notebook = seed_report($s1, 'found', 'Red notebook', 'Books / School Supplies', 'Spiral notebook with a red cover.', 'Science lab', 10);
$calc = seed_report($s2, 'lost', 'Scientific calculator', 'Electronics', 'Casio calculator, white keys.', 'Room 101', 2);
// Make the wristwatch old enough to demonstrate the retention / archive screen.
db()->prepare('UPDATE item_reports SET created_at = ?, updated_at = ? WHERE id = ?')->execute(array(gmdate('Y-m-d H:i:s', time() - 40 * 86400), gmdate('Y-m-d H:i:s', time() - 40 * 86400), $watch));

// A full claim -> review -> approve -> handover journey, so the public "returned items" page has real data.
$claim = ClaimService::submit($s2, $notebook, array('claim_details' => 'It has my name inside the cover and a coffee stain on page 3.', 'evidence_description' => 'I can describe the first page doodles; my classmate can vouch for it.'), array());
ClaimService::startReview($staff, $claim['claim_id']);
ClaimService::approve($staff, $claim['claim_id'], 'Details match the notebook.');
HandoverService::confirm($staff, $claim['claim_id'], $s2['id'], date('Y-m-d\TH:i', time() - 600), 'Checked student ID.', true);
// A claim that is waiting for review and one that needs more information.
ClaimService::submit($s2, $umbrella, array('claim_details' => 'Black umbrella with a wooden handle and a torn strap.', 'evidence_description' => 'Purchased last month; the strap tore on the first day.'), array());
$c2 = ClaimService::submit($s2, $phone, array('claim_details' => 'My phone, cracked screen, blue case.', 'evidence_description' => 'Lock screen is a photo of a beach.'), array());
ClaimService::startReview($staff, $c2['claim_id']);
ClaimService::requestInfo($staff, $c2['claim_id'], 'Please tell us the phone model and the last four digits of the IMEI.');
echo 'Demo reports, claims and one completed handover were created.' . PHP_EOL;
