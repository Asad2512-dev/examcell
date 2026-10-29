# Repository map

| Contract | Implementation | Current consumer |
|---|---|---|
| `App\Repositories\Contracts\UserRepositoryInterface` | `App\Repositories\EloquentUserRepository` | `AuthController@login` |

The repository layer is intentionally small. Existing domain services already provide the stronger boundary for scheduling, seating, duties, marks, results, analysis, reports, backups, and assistant workflows. Additional repositories should be introduced only when they remove duplicated persistence logic or materially improve testing.
