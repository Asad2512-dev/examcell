# ExamCell architecture

The application uses a layered Laravel architecture:

```text
HTTP route
  -> controller / Form Request / middleware
  -> application service or repository boundary
  -> Eloquent model, policy, transaction, audit/notification
  -> Blade view or redirect/JSON response
```

## Boundaries

- Controllers coordinate HTTP concerns and do not own workflow rules.
- Form Requests validate request shape and limits.
- Services coordinate domain workflows and transactions.
- Repositories are used only where a persistence boundary improves testability or keeps infrastructure details out of an application service.
- Policies and scoped queries enforce ownership and role boundaries.
- Blade templates render server-provided data and reusable components.

## Adding a repository-backed feature

1. Confirm the operation is a meaningful persistence boundary.
2. Add a contract under `app/Repositories/Contracts`.
3. Add an Eloquent implementation under `app/Repositories`.
4. Bind the contract in `AppServiceProvider`.
5. Inject the contract into the service/controller that owns the use case.
6. Preserve existing validation, policy checks, transactions, URLs, and audit behavior.
7. Add a feature test against an isolated database.

The current repository example is `UserRepositoryInterface` and `EloquentUserRepository`, used for active login identity lookup.
