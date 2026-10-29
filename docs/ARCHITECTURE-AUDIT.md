# Architecture audit

## Baseline

- Laravel 12.69.x / PHP 8.5 runtime in this workspace.
- Server-rendered Laravel Blade application with MySQL, Eloquent models, policies, Form Requests, services, jobs, notifications, and browser checks.
- Role entry points: `/admin/{screen}`, `/teacher/{screen}`, `/student/{screen}`.
- Public entry points: `/`, `/sign-in`, password recovery and reset routes.
- State-changing workflows are POST routes under `/workflow/*` and use authentication, active-account checks, role middleware, validation, policies, transactions, and audit services where applicable.

## Discovered responsibilities

| Area | Current implementation | Status |
|---|---|---|
| Identity | `AuthController`, `LoginRequest`, `User`, active middleware, password broker | Implemented |
| Academics | `AcademicService`, `Resources`, academic models and resource policy flow | Implemented |
| Examinations | `WorkflowController`, `SchedulingService`, examination models | Implemented |
| Seating/admit cards | `SeatingService`, `GenerateAdmitCards`, policies and workflow views | Implemented |
| Duties | `DutyService`, availability, replacement and request workflows | Implemented |
| Marks | `MarksService`, marks request flow, submission locking and return flow | Implemented |
| Results | `ResultsService`, `GradingService`, corrections, rechecks and publication state | Implemented |
| Analytics/assistant | `AnalyticsService`, `AnalysisService`, `AssistantService`, scoped queries | Implemented with external AI configuration optional |
| Audit/backup | `AuditService`, `BackupService`, audit and backup views | Implemented |
| UI | Shared Blade layout, components, role catalog, responsive CSS/JS, GSAP landing page | Implemented and under refinement |

## Architecture risks

1. `ScreenController` is a large orchestration controller and still owns screen-data composition. It should be decomposed incrementally into role/dashboard query objects and module application services.
2. Some server-backed Blade partials contain presentation-specific query helpers. These should move to controller-provided view models over time.
3. The generic screen catalog is useful for backward compatibility, but high-risk workflows should continue moving toward explicit controllers and requests rather than adding more slug conditionals.
4. Full PHPUnit/browser verification requires access to an isolated MySQL test database; the current sandbox cannot access the configured MAMP socket.

## Migration plan

1. Keep existing routes and database contracts stable.
2. Add repository contracts only at meaningful boundaries; the first is active identity lookup used by authentication.
3. Extract dashboard read models from `ScreenController`.
4. Extract examination, marks, result, and duty workflow commands behind existing services.
5. Add focused feature tests for each extracted boundary using an isolated database.
6. Remove obsolete compatibility branches only after route/browser evidence confirms parity.
