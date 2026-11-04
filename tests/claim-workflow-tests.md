# Claim workflow tests

## State machine (enforced in `ClaimService`, plus DB constraints)
```
pending ──► under_review ◄──► needs_info
   │             │  │              │
   │             │  └► approved ──► returned   (only via the handover; terminal)
   └► rejected ◄─┴──────┘  (staff)         
   └► cancelled (claimant: from pending / under_review / needs_info / approved)
```
Item report: `open` → `claim_pending` on the first claim → `returned` after the handover. If the last active claim is rejected or
cancelled the item goes back to `open`; if another valid claim remains it stays `claim_pending`.

## End-to-end scenario (all steps automated and passing in the harness)
| Step | Action | Expected |
|------|--------|----------|
| 1–2 | Student A registers/logs in | student dashboard |
| 3–4 | A reports a *found* umbrella with a photo | row `open`, photo registered, history `NULL→open`, visible in Browse |
| 5–7 | Student B registers, logs in, finds it by keyword search | listed |
| 8–9 | B submits a claim with ownership details + 2 evidence photos | claim `pending`, evidence rows `pending`, item `claim_pending`, history written |
| 10 | Staff opens the queue | claim listed with its private evidence |
| 11 | Staff *start review*, then *request info* | `under_review` → `needs_info`; B notified; B sees the message |
| 11b | B answers with text + photo | evidence added, back to `under_review`; reviewer notified |
| 12 | Staff accepts/rejects evidence items, adds internal notes (never shown to B), approves | `approved`; item still `claim_pending` (**not** returned); B gets "approved" + "ready for pickup" |
| 13 | Public history checked | approved-but-not-returned item is **absent** |
| 14–16 | Handover with ID check ticked | `handover_records` row; claim **and** item `returned`; histories written; rival claims auto-rejected |
| 17 | Public history | item listed with category + return date only |
| 18 | Reports / CSV export | counts equal the database |
| 19 | Second handover, reject/cancel/edit/delete/archive after return | all refused |

## Negative and race cases (automated)
Own-item claim · duplicate claim · claim on `returned/closed/archived` item · approve a `pending` claim · approve while another claim is approved ·
request info while `pending` · reject a `returned` claim · cancel a `returned` claim · student calls review/approve/handover endpoints (403) ·
handover without ID tick / future time / wrong recipient / unknown claim / non-approved claim · evidence review on a finished claim ·
failure inside the transaction (forced by a database trigger) rolls everything back and removes stored files · a clean retry afterwards succeeds.
