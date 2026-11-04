# Security tests

Legend as in `authentication-tests.md` (Auto = automated suite on the PHP-subset interpreter; *Repeat* = please confirm on real Apache/PHP).

## Access control
| # | Case | Expected | Auto | Your run |
|---|------|----------|------|----------|
| S1 | Guest opens any protected page | 302 to login | ✔ | ☐ |
| S2 | Student opens every `staff/*` and `admin/*` page | 403 for each | ✔ | ☐ |
| S3 | Staff opens every `admin/*` page | 403 for each | ✔ | ☐ |
| S4 | Admin opens staff tools | 200 (admins can do staff work) | ✔ | ☐ |
| S5 | Student POSTs to staff/admin actions (approve, handover, settings, users, categories, archive) | 403, nothing changes | ✔ | ☐ |
| S6 | Staff tries to make themselves admin / edit settings | 403, role unchanged | ✔ | ☐ |
| S7 | Student edits/deletes another student's report by changing `report_id` | Refused, record unchanged | ✔ | ☐ |
| S8 | Student opens another student's claim / the finder opens the claim | 404 | ✔ | ☐ |
| S9 | Student marks another user's notification read | Row unchanged | ✔ | ☐ |
| S10 | Forged `status` / `reviewed_by` fields in a claim form | Ignored; claim stays `pending` | ✔ | ☐ |
| S11 | Staff form tries `new_status=returned` | Refused (only allowed transitions) | ✔ | ☐ |

## Request integrity
| # | Case | Expected | Auto | Your run |
|---|------|----------|------|----------|
| S12 | POST without / with forged CSRF token | 419 page, no change (all 15 mutating actions + login/register/logout) | ✔ | ☐ |
| S13 | GET to a mutating action | 405 | ✔ | ☐ |
| S14 | `return_to` = `http://evil`, `//evil`, `/evil.php`, `../x.php`, `javascript:` … | Falls back to a safe in-app page | ✔ | ☐ |
| S15 | Non-numeric / SQL-looking ids (`1 OR 1=1`, `1;DROP…`) | 404/redirect, no SQL error text | ✔ | ☐ |
| S16 | Search for `' OR 1=1 --`, `'; DROP TABLE…`, `%`, `_` | No error, no damage, wildcards matched literally | ✔ | ☐ |

## Output encoding (XSS)
| # | Case | Expected | Auto | Your run |
|---|------|----------|------|----------|
| S17 | `<script>`/`<img onerror>` in item name, description, location, claim text, staff messages, notes, category name, user name, app-name setting | Shown as inert text on every page that displays them; no raw tag in any response | ✔ | ☐ |
| S18 | CSP / headers | `Content-Security-Policy` (no inline script), `X-Frame-Options`, `nosniff`, `Referrer-Policy` | ✔ | ☐ Repeat |

## Uploads and private files
| # | Case | Expected | Auto | Your run |
|---|------|----------|------|----------|
| S19 | PHP code named `.jpg`, PNG named `.php`, JPEG named `.png`, GIF, SVG, > 5 MB, 0-byte, 13000 px wide, upload error codes | Rejected; no DB row; no file left in `uploads/` | ✔ | ☐ |
| S20 | Stored names | 32 random hex chars + extension from the detected image type | ✔ | ☐ |
| S21 | Private evidence by direct URL `uploads/claim-evidence/<file>` | 403 | ✔ | ☐ Repeat |
| S22 | Evidence through `actions/serve-evidence.php` | 200 for claimant/staff/admin; 404 for others and for unknown ids; guests redirected | ✔ | ☐ |
| S23 | `database/*.sqlite`, `database/schema.sql`, `config/`, `includes/`, `services/`, `tools/`, `tests/`, `logs/`, `.htaccess` | 403 (Apache `.htaccess` / `router.php`) | ✔ | ☐ Repeat |

## Data integrity
| # | Case | Expected | Auto | Your run |
|---|------|----------|------|----------|
| S24 | Edit/delete `status_history` / `claim_status_history` rows | Blocked by database triggers | ✔ | ☐ |
| S25 | Second approved/returned claim for one report | Blocked by a partial unique index | ✔ | ☐ |
| S26 | Delete a user who owns reports | Blocked by foreign keys (deactivate instead) | ✔ | ☐ |
| S27 | Failure half-way through claim submission / handover | Whole transaction rolled back, files cleaned up | ✔ | ☐ |
| S28 | CSV export with `=cmd`, `+1`, `-1`, `@x` cells | Prefixed with `'`; commas/quotes/newlines escaped | ✔ | ☐ |
| S29 | Error pages | Generic text; no SQL, file paths or stack traces unless `CAMPUSFIND_DEBUG=1` | ✔ | ☐ |
| S30 | Admin audit log | Role/active/password/settings/category actions appear in `logs/audit.log`, without secrets | ✔ | ☐ |
