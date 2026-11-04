# CRUD tests

"Delete" is replaced by a **controlled alternative** wherever deleting would destroy history (as required by the brief).

| Resource | Create | Read | Update | Delete / alternative | Auto |
|----------|--------|------|--------|----------------------|------|
| Users | Register (student) · admin *Add a user* · `tools/create-admin.php` | Admin user list: search, role/status filters, pagination (no hashes shown) | Role, activate/deactivate, password reset (admin only; not on yourself; last admin protected) | **Deactivate** (accounts with history are never deleted; FK blocks deletion) | ✔ |
| Categories | Admin form (duplicates refused ignoring case/spaces) | Admin list with report counts; dropdowns everywhere | Inline rename/description | Delete only when unused; "In use" otherwise; last category kept | ✔ |
| Item reports | `student/report-item.php` (+photo, duplicate warning) | Browse (search/filters/sort/pagination), details, My reports, staff manager | Owner while open and unclaimed; staff may correct live reports; type never changes | Owner **Delete = controlled closure** (`closed`, row/photo/history kept); staff close/reopen with reason; **archive** per retention rules | ✔ |
| Claims | `student/submit-claim.php` (+ evidence) | Claimant: own claims + timeline · Staff: queue, review page | Status only through workflow actions; claimant can answer a request or cancel | Never deleted: `rejected` / `cancelled` / `returned` are terminal states | ✔ |
| Evidence | With the claim or when answering "needs info" (images + text) | Claimant, staff, admin via `serve-evidence.php` | Staff accept / reject / reset each item | Kept with the claim | ✔ |
| Item images | With the report | On cards/details (public photos) | Replace / remove (old file deleted, one `item_images` row kept in sync) | Removed with *remove photo* | ✔ |
| Notifications | Created by workflow events | Own list, unread filter, header badge | Mark one / all as read (own rows only) | Kept (no delete UI) | ✔ |
| System settings | Defaults from `schema.sql` (+ code fallbacks) | Admin form with "last changed" | Validated save; `updated_at` changes only for changed keys | n/a | ✔ |
| Status history | Written by the services | Admin audit screens with filters | **Not editable** (database triggers) | **Not deletable** (database triggers) | ✔ |
| Handover records | Staff handover form (one per claim, unique) | Staff "recent handovers", public history (sanitised) | Not editable | Not deletable | ✔ |

Persistence check: every automated test reads results straight from the SQLite file after each request (a new connection), so
changes are known to be committed, not just held in memory. Please also reload pages / reopen the file in DB Browser on your machine.
