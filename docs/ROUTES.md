# Authenticated routes

All mutations use session authentication, active-account checks and CSRF. Roles and resource policies are derived from the server session. Validation failures return form errors or JSON 422. Backup restore uses a separate database and requires an administrator.

| Method | URL | Handler | Middleware |
|---|---|---|---|
| GET|HEAD | `/` | Closure | web, auth, active |
| GET|HEAD | `download/admit/{card}` | WorkflowController@downloadAdmit | web, auth, active |
| GET|HEAD | `download/backup/{backup}` | WorkflowController@downloadBackup | web, auth, active, role:admin |
| GET|HEAD | `forgot-password` | Closure | web, guest |
| POST | `forgot-password` | AuthController@forgot | web, guest, throttle:reset |
| GET|HEAD | `login` | Closure | web |
| POST | `logout` | AuthController@logout | web, auth, active |
| POST | `manage/{resource}/{id?}` | ResourceController@save | web, auth, active, role:admin |
| GET|HEAD | `reports/{type}` | WorkflowController@report | web, auth, active |
| POST | `reset-password` | AuthController@reset | web, guest, throttle:reset |
| GET|HEAD | `reset-password/{token}` | AuthController@resetForm | web, guest |
| GET|HEAD | `sign-in` | AuthController@form | web |
| POST | `sign-in` | AuthController@login | web, guest, throttle:login |
| GET|HEAD | `up` | Closure |  |
| POST | `workflow/admit/{exam}` | WorkflowController@admit | web, auth, active, role:admin |
| POST | `workflow/analysis/{exam}` | WorkflowController@analysis | web, auth, active, role:admin |
| POST | `workflow/assistant` | WorkflowController@assistant | web, auth, active, throttle:assistant |
| POST | `workflow/availability` | WorkflowController@availability | web, auth, active, role:admin,teacher |
| POST | `workflow/backup` | WorkflowController@backup | web, auth, active, role:admin |
| POST | `workflow/backup/restore` | WorkflowController@restoreBackup | web, auth, active, role:admin |
| POST | `workflow/batch/{batch}/state` | WorkflowController@resultState | web, auth, active, role:admin |
| POST | `workflow/correction/{correction}/approve` | WorkflowController@approveCorrection | web, auth, active, role:admin |
| POST | `workflow/correction/{mark}` | WorkflowController@correction | web, auth, active, role:admin |
| POST | `workflow/duty-request/{request}/reject` | WorkflowController@rejectDuty | web, auth, active, role:admin |
| POST | `workflow/duty/{duty}/request` | WorkflowController@dutyRequest | web, auth, active, role:teacher |
| POST | `workflow/duty/{entry}` | WorkflowController@duty | web, auth, active, role:admin |
| POST | `workflow/examination/{exam}/generate` | WorkflowController@generateSchedule | web, auth, active, role:admin |
| POST | `workflow/examination/{exam}/revision` | WorkflowController@revision | web, auth, active, role:admin |
| POST | `workflow/examination/{exam}/state` | WorkflowController@examinationState | web, auth, active, role:admin |
| POST | `workflow/flag/{flag}/review` | WorkflowController@reviewFlag | web, auth, active, role:admin |
| POST | `workflow/forecast` | WorkflowController@forecast | web, auth, active |
| POST | `workflow/import` | WorkflowController@import | web, auth, active, role:admin,teacher |
| POST | `workflow/marks/{course}` | WorkflowController@marks | web, auth, active, role:admin,teacher |
| POST | `workflow/marks/{course}/return` | WorkflowController@returnMarks | web, auth, active, role:admin |
| POST | `workflow/notifications/{id}/read` | WorkflowController@readNotification | web, auth, active |
| POST | `workflow/recheck/{recheck}/decide` | WorkflowController@decideRecheck | web, auth, active, role:admin |
| POST | `workflow/recheck/{result}` | WorkflowController@recheck | web, auth, active, role:student |
| POST | `workflow/replacement/{replacement}/approve` | WorkflowController@replacement | web, auth, active, role:admin |
| POST | `workflow/replacement/{replacement}/nominate` | WorkflowController@nominate | web, auth, active, role:admin |
| POST | `workflow/results/{exam}/calculate` | WorkflowController@calculate | web, auth, active, role:admin |
| POST | `workflow/schedule/{course}` | WorkflowController@schedule | web, auth, active, role:admin |
| POST | `workflow/seating/{entry}` | WorkflowController@seating | web, auth, active, role:admin |
| GET|HEAD | `{role}/{screen?}` | ScreenController@show | web, auth, active |
