<?php
/**
 * CampusFind command-line self-test. No extra software needed:
 *
 *     php tests/run-tests.php
 *
 * It builds a TEMPORARY SQLite database from database/schema.sql, runs the core workflow through the real service
 * classes (registration rules, reports, claims, review, handover, notifications, archive, export, settings, categories)
 * and prints PASS/FAIL for each check. Your real database is never opened or changed.
 * Exit code: 0 = everything passed, 1 = at least one failure.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This test can only be run from the command line.');
}
$testDb = sys_get_temp_dir() . '/campusfind-selftest-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('CAMPUSFIND_DB_PATH=' . $testDb);
putenv('CAMPUSFIND_NO_LOG=1');
require_once __DIR__ . '/../includes/auth.php';

final class Check
{
    public static $pass = 0;
    public static $fail = 0;
    public static function that($name, $ok, $detail = '')
    {
        if ($ok) {
            self::$pass++;
            echo '  PASS  ' . $name . PHP_EOL;
        } else {
            self::$fail++;
            echo '  FAIL  ' . $name . ($detail !== '' ? '  -> ' . $detail : '') . PHP_EOL;
        }
    }
}
function section($title) { echo PHP_EOL . $title . PHP_EOL; }
function person($id, $name, $role) { return array('id' => (int) $id, 'full_name' => $name, 'email' => $name . '@test', 'role' => $role); }
function first_errors($r) { return isset($r['errors'][0]) ? $r['errors'][0] : ''; }

// ---- fresh database
$setup = new PDO('sqlite:' . $testDb);
$setup->exec(file_get_contents(APP_ROOT . '/database/schema.sql'));
$setup = null;
$pdo = db();

section('Accounts');
$a = UserService::insert('Alice Student', 'alice@test.example', 'AlicePass123', 'student');
$b = UserService::insert('Bob Student', 'bob@test.example', 'BobPass12345', 'student');
$s = UserService::insert('Sam Staff', 'sam@test.example', 'StaffPass123', 'staff');
$m = UserService::insert('Ada Admin', 'ada@test.example', 'AdminPass123', 'admin');
Check::that('four accounts created', $a['ok'] && $b['ok'] && $s['ok'] && $m['ok']);
$hash = db_scalar('SELECT password_hash FROM users WHERE id = ?', array($a['id']));
Check::that('password is stored as a hash, not plaintext', strpos($hash, 'AlicePass123') === false && password_verify('AlicePass123', $hash));
Check::that('duplicate email (any case) is rejected', !UserService::insert('Dup', 'ALICE@test.example', 'AlicePass123', 'student')['ok']);
Check::that('invalid role is rejected', !UserService::insert('X Y', 'xy@test.example', 'AlicePass123', 'superuser')['ok']);
Check::that('weak password is rejected', !UserService::insert('X Y', 'xy@test.example', 'short', 'student')['ok']);
$alice = person($a['id'], 'Alice', 'student'); $bob = person($b['id'], 'Bob', 'student'); $sam = person($s['id'], 'Sam', 'staff'); $ada = person($m['id'], 'Ada', 'admin');
Check::that('a student cannot create users (service-level guard)', !UserService::create($alice, 'Evil', 'evil@test.example', 'EvilPass123', 'admin')['ok']);
Check::that('an admin cannot demote themselves', !UserService::setRole($ada, $ada['id'], 'student')['ok']);
Check::that('the last active administrator cannot be removed', first_errors(UserService::setActive(array('id' => 9999, 'role' => 'admin'), $ada['id'], false)) === 'At least one active administrator must remain.');

section('Item reports');
$cat = (int) db_scalar('SELECT MIN(id) FROM categories');
$base = array('report_type' => 'found', 'category_id' => (string) $cat, 'item_name' => 'Black Umbrella', 'description' => 'Folding umbrella, wooden handle', 'location' => 'Canteen', 'date_occurred' => date('Y-m-d'));
Check::that('missing description is rejected', !ReportService::create($alice, array_merge($base, array('description' => '')), null, true)['ok']);
Check::that('unknown category is rejected', !ReportService::create($alice, array_merge($base, array('category_id' => '9999')), null, true)['ok']);
Check::that('future date is rejected', !ReportService::create($alice, array_merge($base, array('date_occurred' => date('Y-m-d', time() + 5 * 86400))), null, true)['ok']);
$r = ReportService::create($alice, $base, null, true);
Check::that('valid report is saved as open', $r['ok'] && db_scalar('SELECT status FROM item_reports WHERE id = ?', array($r['id'])) === 'open');
$umbrella = (int) $r['id'];
Check::that('creation is written to status_history', db_count('SELECT COUNT(*) FROM status_history WHERE item_report_id = ? AND new_status = ?', array($umbrella, 'open')) === 1);
$dup = ReportService::create($alice, array_merge($base, array('item_name' => 'black umbrella')), null, false);
Check::that('a similar report triggers the duplicate warning (not saved yet)', !$dup['ok'] && isset($dup['duplicates']) && count($dup['duplicates']) === 1);
Check::that('...and can still be submitted after confirmation', ReportService::create($alice, array_merge($base, array('item_name' => 'black umbrella')), null, true)['ok']);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmpPng = sys_get_temp_dir() . '/cf-test-' . bin2hex(random_bytes(3)) . '.png';
file_put_contents($tmpPng, $png);
$withPhoto = ReportService::create($alice, array_merge($base, array('item_name' => 'Photo item', 'location' => 'Library')), array('name' => 'x.png', 'tmp_name' => $tmpPng, 'error' => 0, 'size' => strlen($png)), true);
$path = $withPhoto['ok'] ? db_scalar('SELECT image_path FROM item_reports WHERE id = ?', array($withPhoto['id'])) : '';
Check::that('a valid image is stored under a random name and registered in item_images', $withPhoto['ok'] && preg_match('/^items\/[a-f0-9]{32}\.png$/', (string) $path) === 1 && db_count('SELECT COUNT(*) FROM item_images WHERE image_path = ?', array($path)) === 1);
$fakeTmp = sys_get_temp_dir() . '/cf-fake-' . bin2hex(random_bytes(3)) . '.jpg';
file_put_contents($fakeTmp, '<?php echo "hacked"; ?>');
$fake = ReportService::create($alice, array_merge($base, array('item_name' => 'Fake photo')), array('name' => 'shell.jpg', 'tmp_name' => $fakeTmp, 'error' => 0, 'size' => 24), true);
Check::that('PHP code disguised as .jpg is rejected', !$fake['ok']);
@unlink($fakeTmp);
if ($path) { ImageUploadService::delete($path); }
Check::that('another student cannot edit the report', !ReportService::update($bob, $umbrella, $base, null, false)['ok']);
Check::that('the owner can edit it (history records the change)', ReportService::update($alice, $umbrella, array_merge($base, array('location' => 'Canteen door')), null, false)['ok']
    && db_count("SELECT COUNT(*) FROM status_history WHERE item_report_id = ? AND notes LIKE 'Details edited by the reporter%'", array($umbrella)) === 1);

section('Claim workflow');
Check::that('the finder cannot claim their own report', !ClaimService::submit($alice, $umbrella, array('claim_details' => 'This is mine really', 'evidence_description' => 'I found it myself'), array())['ok']);
$c = ClaimService::submit($bob, $umbrella, array('claim_details' => 'Black umbrella with an initial scratched under the handle', 'evidence_description' => 'Bought it last month, receipt at home'), array());
Check::that('claim is stored as pending; the item becomes claim_pending', $c['ok'] && db_scalar('SELECT status FROM claim_requests WHERE id = ?', array($c['claim_id'])) === 'pending' && db_scalar('SELECT status FROM item_reports WHERE id = ?', array($umbrella)) === 'claim_pending');
$claim = (int) $c['claim_id'];
Check::that('a duplicate claim by the same student is rejected', !ClaimService::submit($bob, $umbrella, array('claim_details' => 'Second try for the same item', 'evidence_description' => 'Second try again please'), array())['ok']);
Check::that('a claim is not approvable before review', !ClaimService::approve($sam, $claim)['ok']);
Check::that('students cannot review claims', !ClaimService::startReview($bob, $claim)['ok']);
Check::that('start review -> under_review', ClaimService::startReview($sam, $claim)['ok'] && db_scalar('SELECT status FROM claim_requests WHERE id = ?', array($claim)) === 'under_review');
Check::that('request info -> needs_info', ClaimService::requestInfo($sam, $claim, 'What colour is the strap?')['ok'] && db_scalar('SELECT status FROM claim_requests WHERE id = ?', array($claim)) === 'needs_info');
Check::that('claimant answers -> back under_review', ClaimService::respond($bob, $claim, array('response_text' => 'The strap is navy blue.'), array())['ok'] && db_scalar('SELECT status FROM claim_requests WHERE id = ?', array($claim)) === 'under_review');
Check::that('approve -> approved; the item is NOT returned', ClaimService::approve($sam, $claim, 'Collect at the desk')['ok'] && db_scalar('SELECT status FROM item_reports WHERE id = ?', array($umbrella)) === 'claim_pending');
Check::that('an approved claim is not in the public returned history', db_count("SELECT COUNT(*) FROM handover_records") === 0);
$when = date('Y-m-d\TH:i', time() - 300);
Check::that('handover requires the identity check box', !HandoverService::confirm($sam, $claim, $bob['id'], $when, '', false)['ok']);
Check::that('handover to the wrong person is refused', !HandoverService::confirm($sam, $claim, $alice['id'], $when, '', true)['ok']);
$h = HandoverService::confirm($sam, $claim, $bob['id'], $when, 'ID checked', true);
Check::that('valid handover succeeds', $h['ok']);
Check::that('claim and item are both returned', db_scalar('SELECT status FROM claim_requests WHERE id = ?', array($claim)) === 'returned' && db_scalar('SELECT status FROM item_reports WHERE id = ?', array($umbrella)) === 'returned');
Check::that('exactly one handover record; a second handover is blocked', db_count('SELECT COUNT(*) FROM handover_records') === 1 && !HandoverService::confirm($sam, $claim, $bob['id'], $when, '', true)['ok']);
Check::that('histories recorded the whole chain', db_count('SELECT COUNT(*) FROM claim_status_history WHERE claim_id = ?', array($claim)) === 6 && db_count("SELECT COUNT(*) FROM status_history WHERE item_report_id = ? AND new_status = 'returned'", array($umbrella)) === 1);
Check::that('returned items count (public page source) = 1', HandoverService::returnedCount() === 1);
Check::that('history rows cannot be edited or deleted (database triggers)', (function () { try { db()->exec("UPDATE status_history SET notes = 'x'"); return false; } catch (PDOException $e) { return true; } })()
    && (function () { try { db()->exec('DELETE FROM claim_status_history'); return false; } catch (PDOException $e) { return true; } })());

section('Notifications');
Check::that('claimant received submitted / review / info / approved / pickup / handover notifications', db_count("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type IN ('claim_submitted','claim_under_review','claim_needs_info','claim_approved','ready_for_pickup','handover_confirmed')", array($bob['id'])) === 6);
Check::that('the finder was told about the claim and the return', db_count("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type IN ('report_claim_received','report_returned')", array($alice['id'])) === 2);
Check::that('unread count works and mark-as-read only affects the owner', NotificationService::unreadCount($bob['id']) > 0
    && !NotificationService::markRead($alice['id'], (int) db_scalar('SELECT id FROM notifications WHERE user_id = ? LIMIT 1', array($bob['id'])))
    && NotificationService::markAllRead($bob['id']) > 0 && NotificationService::unreadCount($bob['id']) === 0);

section('Archive & retention');
$old = ReportService::create($alice, array_merge($base, array('item_name' => 'Old Compass', 'location' => 'Store room')), null, true);
$fresh = ReportService::create($alice, array_merge($base, array('item_name' => 'Fresh Ruler', 'location' => 'Hall')), null, true);
Check::that('retention default is 30 days', ReportArchiveService::retentionDays() === 30);
$pdo->prepare('UPDATE item_reports SET created_at = ? WHERE id = ?')->execute(array(gmdate('Y-m-d H:i:s', time() - 10 * 86400), $old['id']));
Check::that('nothing is eligible with 30-day retention', ReportArchiveService::eligibleCount() === 0 && !ReportArchiveService::archive($sam, $old['id'])['ok']);
Check::that('settings validation rejects 0 and "abc"', !SettingsService::save($ada['id'], array('unclaimed_retention_days' => '0', 'app_name' => 'CampusFind', 'items_per_page' => '12', 'notify_staff_new_claims' => '1'))['ok']
    && !SettingsService::save($ada['id'], array('unclaimed_retention_days' => 'abc', 'app_name' => 'CampusFind', 'items_per_page' => '12', 'notify_staff_new_claims' => '1'))['ok']);
Check::that('saving retention = 7 is honoured by the archive rules', SettingsService::save($ada['id'], array('unclaimed_retention_days' => '7', 'app_name' => 'CampusFind', 'items_per_page' => '12', 'notify_staff_new_claims' => '1'))['ok']
    && ReportArchiveService::retentionDays() === 7 && ReportArchiveService::eligibleCount() === 1);
Check::that('a fresh item is refused, the old one is archived by staff', !ReportArchiveService::archive($sam, $fresh['id'])['ok'] && ReportArchiveService::archive($sam, $old['id'])['ok']);
Check::that('archived_at / archived_by / history recorded', db_scalar('SELECT archived_by FROM item_reports WHERE id = ?', array($old['id'])) == $sam['id'] && db_count("SELECT COUNT(*) FROM status_history WHERE item_report_id = ? AND new_status = 'archived'", array($old['id'])) === 1);
Check::that('returned items cannot be archived', !ReportArchiveService::archive($sam, $umbrella)['ok']);
Check::that('students cannot archive', !ReportArchiveService::archive($alice, $fresh['id'])['ok']);

section('Export & categories');
$ds = ExportService::dataset('found', array());
Check::that('export lists every found report', $ds['total'] === db_count("SELECT COUNT(*) FROM item_reports WHERE report_type = 'found'"));
Check::that('CSV formulas are neutralised', ExportService::csvCell('=1+1') === "'=1+1" && ExportService::csvCell('+SUM(A1)') === "'+SUM(A1)" && ExportService::csvCell('@x') === "'@x");
Check::that('CSV quotes commas, quotes and line breaks', ExportService::csvCell("a,b") === '"a,b"' && ExportService::csvCell('say "hi"') === '"say ""hi"""' && ExportService::csvCell("l1\nl2") === "\"l1\nl2\"");
Check::that('CSV has no email / password columns', strpos(implode(',', $ds['headers']), 'mail') === false && strpos(implode(',', $ds['headers']), 'assword') === false);
$cat2 = CategoryService::create($ada['id'], 'Sports Gear', 'Balls');
Check::that('category added; duplicate in another case rejected', $cat2['ok'] && !CategoryService::create($ada['id'], 'sports  GEAR', '')['ok']);
Check::that('a category used by reports cannot be deleted', !CategoryService::delete($ada['id'], $cat)['ok']);
Check::that('an unused category can be deleted', CategoryService::delete($ada['id'], $cat2['id'])['ok']);

section('Atomic rollback');
$pdo->exec("CREATE TRIGGER selftest_boom BEFORE INSERT ON notifications WHEN NEW.type = 'claim_submitted' BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
$item = ReportService::create($alice, array_merge($base, array('item_name' => 'Rollback Item', 'location' => 'Gym')), null, true);
$claimsBefore = db_count('SELECT COUNT(*) FROM claim_requests');
$threw = false;
try { ClaimService::submit($bob, $item['id'], array('claim_details' => 'Rollback claim details', 'evidence_description' => 'Rollback proof text'), array()); } catch (Throwable $e) { $threw = true; }
Check::that('a failure halfway through a claim undoes every change', $threw && db_count('SELECT COUNT(*) FROM claim_requests') === $claimsBefore && db_scalar('SELECT status FROM item_reports WHERE id = ?', array($item['id'])) === 'open');
$pdo->exec('DROP TRIGGER selftest_boom');

// ---- clean up and report
$pdo = null;
@unlink($tmpPng);
@unlink($testDb);
echo PHP_EOL . 'Result: ' . Check::$pass . ' passed, ' . Check::$fail . ' failed.' . PHP_EOL;
exit(Check::$fail > 0 ? 1 : 0);
