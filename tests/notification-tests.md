# Notification tests

Notifications are rows in `notifications` (persistent). Duplicate guard: an identical message for the same user and target within 60 s is not inserted twice; state guards prevent repeats of the same transition.

| Event | Recipient(s) | `type` | Auto |
|-------|--------------|--------|------|
| Claim submitted | claimant · reporter (finder) · all active staff/admins (if *Notify staff about new claims* is on) | `claim_submitted` · `report_claim_received` · `claim_new` | ✔ |
| Review started / resumed | claimant | `claim_under_review` | ✔ |
| More information requested | claimant | `claim_needs_info` | ✔ |
| Claimant answered | reviewing staff member | `claim_info_provided` | ✔ |
| Claim approved | claimant | `claim_approved` + `ready_for_pickup` | ✔ |
| Claim rejected | claimant | `claim_rejected` | ✔ |
| Claim cancelled by claimant | reviewing staff member | `claim_cancelled` | ✔ |
| Handover confirmed | claimant · reporter | `handover_confirmed` · `report_returned` | ✔ |
| Rival claim closed by a handover | that claimant | `claim_rejected` | ✔ |
| Staff edits / closes / re-opens a report | reporter | `report_updated` · `report_status` | ✔ |
| Report archived | reporter | `report_archived` | ✔ |

## Behaviour
| # | Case | Expected | Auto |
|---|------|----------|------|
| N1 | Header badge and *Unread (n)* tab | n equals unread rows for that user | ✔ |
| N2 | Mark one as read | only the owner's row changes | ✔ |
| N3 | User B posts the id of user A's notification | A's row unchanged | ✔ |
| N4 | Mark all as read | only the caller's rows | ✔ |
| N5 | Links | claim notifications open the claim (student view or staff review page); report notifications open the item | ✔ |
| N6 | Repeating a workflow action | no duplicate notification | ✔ |
| N7 | *Notify staff about new claims* = off | no `claim_new` rows created | ✔ |
| N8 | Staff notification page | `staff/notifications.php` is staff/admin only (403 for students) | ✔ |
| N9 | Messages are escaped on output | payloads in item names appear as text | ✔ |
