# ExamCell design system

## Identity
A precise indigo document/E mark with a teal check. The mark is authored as SVG geometry, so there are no raster generation artifacts. `public/brand/logo.svg` is the full dark-wordmark lockup for light surfaces; `logo-light.svg` is for dark surfaces. `logo-mark.svg`, mark variants, `favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` and `logo-mark.png` (PDF fallback) provide the other formats. Application wordmarks use real Inter text beside the SVG mark for consistent font loading and accessibility.

## Technology
Vite builds local Tailwind CSS, Alpine.js and application JavaScript. Lucide icon geometry is rendered server-side, without a client icon library download. Chart.js is imported only when a chart enters the viewport; every chart provides a text data alternative. Assets and fonts have no CDN dependency. Run `npm install`, `npm run build`; use `npm run dev` during frontend development.

References: [Tailwind with Vite](https://tailwindcss.com/docs/installation/using-vite), [Alpine installation](https://alpinejs.dev/essentials/installation), [Chart.js integration](https://www.chartjs.org/docs/latest/getting-started/integration.html).

## Tokens
Source: `resources/css/app.css`. Semantic variables change under `.dark`; preference is saved locally. Initial mode follows the operating system when no explicit preference is saved.

| Token | Light | Dark | Usage |
|---|---|---|---|
| `--primary` | #3538CD | #A5B4FC | Actions, selected navigation, focus |
| `--primary-hover` | #292BA3 | #C7D2FE | Hovered primary actions |
| `--primary-soft` | #EEF0FF | #282B51 | Selected surfaces |
| `--accent` | #0F766E | #5EEAD4 | Visual accents, chart series |
| `--bg` | #F6F7FB | #0F1422 | Workspace background |
| `--surface` | #FFFFFF | #171E30 | Cards, menus, inputs |
| `--surface-muted` | #F9FAFC | #131A2A | Table headings, nested panels |
| `--text` | #171C35 | #EDF1FF | Main text |
| `--muted` | #626C83 | #A6B1C8 | Secondary text |
| `--border` | #E5E8F0 | #2B344A | Structural boundaries |
| `--control-border` | #8792AB | #64748B | Visible input boundaries |
| `--success` | #047857 | #6EE7B7 | Approved, active, completed |
| `--warning` | #92400E | #FCD88B | Pending, review, returned |
| `--danger` | #B42335 | #FDA4AF | Errors, rejected, conflicts |
| `--info` | #2457B0 | #93C5FD | Information, submitted |

Semantic backgrounds use separate corresponding `-bg` variables. Primary buttons have white text in light mode and dark text in dark mode. Do not convey status with color alone: include text and an icon or status dot.

## Typography, spacing, shape
Inter is locally hosted with `font-display: swap`. UI body 14/1.55; table body 12; metadata 10–12; card headings 16; page titles 28 (25 mobile); public hero 44–70. Weights 400/500/600/700. Body and supporting text remain readable in both themes. Use tabular numeric data when comparing marks.

Spacing uses a 4 px base: 4, 8, 12, 16, 20, 24, 32, 40, 48. Components share 16–24 px interior padding, 20 px panel separation, 8 px controls and 12 px cards. Soft shadows supplement borders rather than replacing them. Focus uses an explicit primary outline plus a translucent ring for inputs. Honor reduced-motion preferences.

## Component contract
`app-layout`, `sidebar`, `topbar`, `breadcrumb`, `page-header`, `card`, `stat-card`/`kpi`, `data-table`, `form-field`, `input`, `select`, `date-input`, `file-upload`, `toggle`, `button`, `badge`/`status-pill`, `modal`, `drawer`, `tabs`, `stepper`, `wizard`, `empty-state`, `error-state`, `skeleton`, `toast`, `confirm-dialog`, `timeline`, `calendar`, `chart-card`, `printable-layout`, `report-toolbar`, `job-progress` and `theme-toggle` form the component library. `/styleguide` demonstrates these inside the admin shell and uses the existing admin-role middleware.

## Interaction and data integrity
- Existing POST URLs, field names, CSRF protection, role checks, lock rules and server validation remain authoritative.
- Table search/sort/column visibility and selection exports operate on the loaded page. Existing server filters and pagination remain available. Selection export is a safe local CSV export; no unsupported bulk delete or approval is invented.
- Marks use Arrow Up/Down/Enter navigation, input limits, weighted display totals and an honest saved/unsaved status. Optional autosave uses the existing draft-save action with `submit=0`, serialized requests, error feedback and coordination with explicit submission. Autosave is off until selected; locked submissions cannot enable it. Explicit Save draft remains available.
- Wizards guide configuration, review and confirmation before the original form submits. Server validation remains mandatory. Import preview parses CSV locally; Excel parsing/validation stays on the server. Existing preview/commit semantics remain unchanged.
- Calendar views show authorized schedule records with week/month navigation; local room overlaps are highlighted, while complete conflict validation remains on the server.
- Semantic dialogs use native focus trapping and Escape. Mobile navigation traps focus, restores it on close and exposes its expanded state. Command search supports Ctrl/Cmd+K. Buttons and fields have visible keyboard focus and explicit names.
- Semester result buttons filter only the authorized result rows on the loaded page. GPA/CGPA comes from the existing grading service.
- The admin academic switcher posts to `/academic-context`, persists the selected session/semester in the authenticated user session, and scopes academic lists, dashboards, reports, analytics, assistant retrieval and record selectors. Shared master directories remain institution-wide; policy authorization is unchanged.

## Responsive and print
Verify 375, 768, 1280 and 1920 px. Sidebar becomes a drawer at 768 px. Data tables become labeled cards; marks retain a horizontally scrollable grid to preserve entry relationships. Forms become a single column; KPI grids reduce to two columns. Dialogs fit the viewport. Print uses A4 rules, hides navigation/actions, repeats table headings and keeps academic identity visible. PDFs use a shared DOMPDF-safe style partial and an actual PNG QR encoding a signed public verification URL. The public view exposes only name, roll number, examination and current validity.

## Inherited functional limits
The catalog declares many conceptual screens backed by shared live modules. This redesign preserves that architecture and its permissions. A distinct catalog name does not imply a separate approval state. See docs/BACKEND_FEATURES.md for deadline, academic-context and signed-QR contracts. Submission deadlines are persisted per examination course with optional assigned-teacher overrides; live counters use server time, turn amber below 48 hours and red when overdue. Persistent schedule-conflict totals remain unavailable, so the UI does not claim zero conflicts. AI confidence is displayed only where returned by the existing forecast service. Generic imported record IDs remain necessary for the existing server contracts. Actual 500 pages use the framework error view when debug mode is disabled; debug behavior is unchanged.
