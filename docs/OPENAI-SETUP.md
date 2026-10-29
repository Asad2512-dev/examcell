# Backend-only OpenAI integration

`config/services.php` reads the private environment. `AssistantService` calls `POST https://api.openai.com/v1/responses` through Laravel's HTTP client, using a Bearer token on the server. No PHP SDK is required. The supplied model setting is `gpt-4.1-mini`; choose a model allowed by your OpenAI project. Live credentials/account access were not available for this build, so provider behavior was tested with Laravel HTTP fakes.

```dotenv
OPENAI_ENABLED=true
OPENAI_API_KEY=YOUR_PRIVATE_KEY
OPENAI_MODEL=gpt-4.1-mini
```

Run `php artisan config:clear` after privately editing `.env`. Do not paste a key into a Blade view, JavaScript file, test fixture, issue or source control. When disabled, unconfigured, rate limited or unavailable, the assistant shows the authorized database answer and identifies the explanation as unavailable.

The controller validates a 1000-character question, one of four read-only intents and conversation ownership. Query scopes derive identity and role from the session. Student results must belong to that student and a published batch. Teacher data must belong to assigned courses/duties. Only minimal course codes, grades/scores or schedule/seat data are sent; user names, emails, passwords and full model dumps are excluded from retrieval context. Email addresses in questions are redacted. Treat questions as user-provided text; do not enter unrelated personal information into them.

Calls set `store=false`, a maximum of 500 output tokens, a 5-second connection timeout and 20-second total timeout. One HTTP attempt is made; there are no automatic repeat calls that could cause duplicate billing. Five assistant requests per minute and 30 provider attempts per user per day are enforced. The daily reservation locks the user row and counts failed, invalid and interrupted attempts as well as successes. Only returned token usage is stored; it is not an estimated monetary charge. Provider response bodies, headers, credentials and exception details are not written to application messages or audit logs.

The response must be completed and contain bounded plain-text output. All text is escaped when rendered in Blade. The authoritative database answer is retained above any AI explanation; generated prose remains reviewable and cannot override records. Tools, SQL execution, record mutations and grade calculations are not exposed to the provider. Deterministic grading, scheduling, completeness and anomaly rules work with OpenAI disabled.

References checked during implementation:

- [Official text generation guide](https://developers.openai.com/api/docs/guides/text)
- [GPT-4.1 Mini model and supported endpoints](https://developers.openai.com/api/docs/models/gpt-4.1-mini)

Forecasts are implemented by `ForecastService` using published database history and documented statistical uncertainty. They are separate from optional OpenAI explanations and cannot change official results.
