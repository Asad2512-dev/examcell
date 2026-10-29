# AI features

**Principle: AI proposes, code validates, an administrator approves.** Grades, weighted scores, GPA and CGPA are always computed by the existing deterministic code; no AI path writes or alters marks.

## Setup
1. Put your key in `.env` (never in code or chat): `OPENAI_API_KEY=...` and optionally `OPENAI_MODEL=gpt-4.1`.
2. `php artisan config:clear`
3. Admin → **AI Settings** → **Test connection**.
4. Make sure a queue worker is running for long tasks: `php artisan queue:work` (QUEUE_CONNECTION=database).

## Architecture (`app/Services/AI`)
- `AiClient` – the only OpenAI gateway (`openai-php/laravel`). Strict JSON-schema structured outputs, up to 3 transport retries, 90 s timeout, usage/cost row in `ai_usages` and a summary (feature, model, tokens, cost – no prompt or response text, no key) in `audit_logs`. `proposeAndValidate()` runs propose → validate → feed errors back, max 3 attempts.
- `AiSettings` – key status (configured/missing only), model choice (allow-list in `config/ai.php`), monthly usage.
- `RunAiTask` job + `ai_runs` table – queued work with a polling progress bar; results wait for review.

## Features (Admin → AI Automation)
| Feature | AI does | Code validates |
|---|---|---|
| Date sheet | Interprets plain-language rules, proposes times/rooms, explains; chat "move Physics to Tuesday" revises | room/student/teacher clashes, capacity, session bounds, room & teacher unavailability, blocked slots, max papers per day per class, gap before major subjects; re-checked by `SchedulingService` on apply |
| Seating | Mixes classes across seats | every student once, capacity, no same-class neighbours, special-needs → accessible seats |
| Invigilation | Fair duty assignment | `DutyService::blocked`, overlaps, daily limits, workload cap; replacement suggestion limited to eligible candidates |
| Smart import | Reads xlsx/csv/pdf/photo, detects target, maps columns | per-row Laravel validation, uniqueness, section/department lookup; uncertain rows flagged; one confirmation; uploaded file deleted after analysis |
| Reports | Writes a query *plan* (metric, grouping, filters) | only fixed, read-only, permission-scoped queries run; the AI never writes SQL |
| Triage | Summarises rechecks and anomaly flags | recommendation only; admin decides, marks untouched |
| Announcements | Drafts text from database facts | admin edits and sends |

## Seat map
Room grids (rows/columns from `rooms.seat_columns`, default 6), board and door marked, colour by class with legend, hover/focus details, class filter, room-wise / class-wise toggle, roll-number search, drag-and-drop swap with immediate server validation, student's own seat highlighted (classmates anonymised for students), printable A4 per room. Mark accessible seats on a room via `rooms.accessible_seats` (JSON list) and students via `student_profiles.special_needs`.

## Not covered / limits
- Teacher-side AI import (marks) is admin-only for now; the old CSV import screen still exists alongside it and was not removed.
- Photo/PDF extraction accuracy depends on the model; always review the preview.
- Cost figures are estimates from `config/ai.php` prices.
- Tests mock OpenAI (`OpenAI::fake`); no live call is made by the suite.
