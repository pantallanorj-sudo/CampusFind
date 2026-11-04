# Data validation tests

All rules are enforced **on the server** (browser attributes such as `required` are only a convenience).

| Field | Rule | Invalid examples tested | Auto |
|-------|------|-------------------------|------|
| Registration name | 2–100 characters | empty | ✔ |
| Email | valid format, ≤ 190, unique (case-insensitive) | `not-an-email`, duplicate in another case | ✔ |
| Password | 8–72 characters, not blank, must match confirmation | `short`, mismatch | ✔ |
| Report type | `lost` or `found` | `stolen` | ✔ |
| Category | must exist | `9999`, `abc` | ✔ |
| Item name | 2–120 | empty, 500 chars | ✔ |
| Description | 5–2000 | empty, 5000 chars | ✔ |
| Location | 2–200 | empty, 500 chars | ✔ |
| Date lost/found | real calendar date, not in the future | `2026-02-31`, +5 days | ✔ |
| Photo / evidence image | JPG/PNG/WebP, ≤ 5 MB, real image (MIME sniffed), extension must match content, ≤ 12000 px, evidence ≤ 3 files | GIF, SVG, `.php`, fake `.jpg`, 6 MB, 0 bytes, 13000 px, upload error codes | ✔ |
| Claim details | 10–2000 | empty | ✔ |
| Proof of ownership | 10–1000 | `short` | ✔ |
| Claim confirmation tick | required | unticked | ✔ |
| Staff message / rejection reason | 5–1000 | `hi`, 1001 chars | ✔ |
| Approval message | ≤ 1000 | 1001 chars | ✔ |
| Internal notes | ≤ 2000 | 2001 chars | ✔ |
| Handover time | valid, not in the future | `yesterday`, +10 min | ✔ |
| Handover notes | ≤ 1000 | 1001 chars | ✔ |
| Archive/close notes | ≤ 500; reason (≥ 3 chars) required when closing | 501 chars, empty reason | ✔ |
| Category name | 2–60, unique ignoring case and extra spaces | `A`, 61 chars, `sports gear` vs `Sports Gear` | ✔ |
| Category description | ≤ 200 | 201 chars | ✔ |
| Settings | `unclaimed_retention_days` 1–3650; `items_per_page` 5–50; `app_name` 2–40 chars, no control characters; notifications on/off | `0`, `-5`, `99999`, `abc`, empty, `3`, `500`, `x`, control char, `2` for the boolean | ✔ |
| Filters / sort / page parameters | allow-listed or cast to int/date | `sort=evil`, `status=hacked`, `page=-5`, `page=abc`, `q[]=x`, 5000-char keyword | ✔ |
| Array-typed POST fields | tolerated without PHP errors | `item_name[]=x` | ✔ |

Missing or corrupt rows in `system_settings` fall back to safe defaults (30 days, 12 per page, "CampusFind", notifications on) — Auto ✔.
