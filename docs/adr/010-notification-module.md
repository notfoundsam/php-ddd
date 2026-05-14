# ADR-010: Notification Module

**Status:** Accepted
**Date:** 2026-05-13

## Context

The system needs to send transactional email and SMS from any bounded context, in both FuelPHP (today) and Laravel (after the migration), without hitting external providers in local development. Domain code (`backend/src/`) is PHP 7.4 and framework-agnostic. The application already runs an outbox + SQS worker pipeline (ADR-005), and call sites always know which channel they need at compile time — there is no requirement for a polymorphic "best-channel" dispatcher.

## Decision

### 1. Synchronous library called from an async worker; no module-owned state

`EmailNotifierInterface::notify(EmailMessage)` and `SmsNotifierInterface::notify(SmsMessage)` are blocking calls returning a `DeliveryReceipt`. The module has no outbox, no queue, no `notification_log`, no retries. End-to-end durability is composed from pieces that already exist:

1. A command handler records an `OutboxEventInterface` on its aggregate. Aggregate change and outbox row commit in the same transaction (ADR-005). HTTP returns immediately.
2. The ADR-005 SQS worker pulls the event and dispatches to a listener in the originating bounded context.
3. The listener resolves the current recipient via the owning context's public API, builds an `EmailMessage` / `SmsMessage`, and calls `$notifier->notify(...)` synchronously inside the worker.
4. Provider exceptions bubble out — SQS redrives the event, AWS DLQ catches poison messages after N attempts. No application-level retry.

"Synchronous" describes the module, not the user request. Building a module-owned outbox/worker would duplicate ADR-005 infrastructure for no functional gain.

At-least-once delivery from SQS means listeners may re-send on redrive. Listeners that cannot tolerate duplicates guard against that themselves (typically via a domain-state check before calling `notify`); the module does not enforce idempotency.

### 2. Notification lives in `SharedKernel`, not in a bounded context

There is no notification aggregate, no notification domain model — only delivery plumbing that every context calls into. `SharedKernel` is the correct location.

### 3. Channel-specific typed interfaces; no marker `NotificationMessageInterface`

Each channel has its own interface typed to its message class. No `NotificationChannelInterface` parent, no `supports()` predicate, no runtime `instanceof` in callers. The compiler enforces "right message for the channel" with no extra abstractions. If a polymorphic dispatcher becomes a product requirement, it will be added **on top of** these typed interfaces — not in place of them.

### 4. `EmailAddress` (identity) vs `EmailRecipient` (presentation)

`EmailAddress` is a lowercased, validated VO with no display name — suitable for equality and lookup. `EmailRecipient` is composition of `EmailAddress + ?displayName` for `From`/`To` rendering, with `withAddress()` so the rewriter can preserve display name across staging redirects. Treating "address with optional name" as the same thing as "address" leads to subtle bugs at the persistence boundary; the split prevents that.

### 5. Sender identity: hardcoded roles, domain via env

`EmailSenderRole` is a closed enum of `info`, `marketing`, `system`. `SenderRegistry::getFromRecipient($role)` maps each to a fixed `(localPart, displayName)` pair (`info@…/php-ddd`, `marketing@…/Marketing`, `system@…/System`). The domain comes from `EMAIL_DOMAIN` (env var, defaults to `php-ddd.test`).

Roles are class constants because they are brand/product decisions that belong in code review, not in a config file. The domain is env-var because it legitimately differs between production and staging.

Multi-service / multi-domain support is deferred (see Future Work). When a second product in the monolith starts sending email, the registry becomes a two-key lookup `(ServiceContext, EmailSenderRole)`.

### 6. `RecipientRewriter` redirects staging recipients to Mailpit

`user+tag@example.com` → `user+tag--example.com@staging.php-ddd.jp`, display name preserved, plus-tags survive, idempotent on already-rewritten addresses. Target domain and `--` delimiter are class constants — the staging domain is a DNS record we own, and the delimiter has no reason to vary.

The rewriter is **not** a decorator on `EmailNotifierInterface`. Inlining `if ($env->isStaging()) $rewriter->rewrite(...)` in the notifier keeps environment behaviour co-located with the rest of the env-specific logic (configuration sets, message-id extraction) and avoids an extra interface boundary for one conditional.

### 7. Transport: FuelPHP `Email\Email` driver with a custom SES subclass

`FuelPhpEmailNotifier` (in `fuelphp/fuel/packages/infrastructure/classes/Notification/`) maps `EmailMessage` onto the FuelPHP `Email\Email` driver layer. In cloud envs the driver is our `Email_Driver_Ses`, which calls `SesClient::sendRawEmail()` and attaches the configuration set as the `ConfigurationSetName` **API parameter** (the AWS-recommended path for `SendRawEmail`, not the legacy `X-SES-CONFIGURATION-SET` MIME header).

Configuration set names follow the pattern `php-ddd-{env}-email-{alias}`. The notifier applies the configuration set only when (a) the driver is `Email_Driver_Ses`, (b) the message has a non-empty alias, and (c) the env is not `test`.

Provider message ID is extracted via `method_exists($driver, 'getMessageId')`. SES populates it; SMTP/Mailpit does not — `DeliveryReceipt::providerMessageId` is `?string` for exactly that reason.

Reusing the FuelPHP driver layer avoids re-implementing MIME assembly while the legacy framework is still in production. When Laravel becomes the primary runtime, a direct AWS-SDK `SesEmailNotifier` will replace this shim (Future Work).

### 8. SMS: Twilio SDK + HTTP client substitution for mock

`TwilioSmsNotifier` calls `Twilio\Rest\Client::messages->create()` and returns the message SID in `DeliveryReceipt`. The 1600-character body limit is enforced; `TwilioException` is translated to `NotificationDeliveryException`.

The SDK exposes no `setBaseUrl()` — `setEdge()`/`setRegion()` only routes within `*.twilio.com`. To target the local mock, `MockTwilioHttpClient extends CurlClient` and rewrites the outgoing URL host before delegating. This is the canonical pattern in Twilio's own examples. `SmsNotifierFactory` wires the mock client when `TWILIO_API_BASE_URL` is set, and the real SDK otherwise.

All SMS code lives under `fuelphp/`, so `twilio/sdk` is declared only in `fuelphp/composer.json`. `backend/composer.json` has no Twilio or AWS SDK dependency. When Laravel adds its own SMS notifier, it will declare `twilio/sdk` in `laravel/composer.json` and may pin a different major version independently.

### 9. Tags target Mailpit, not SES

`EmailMessage::withTag(string)` / `withTags(string[])` accept a flat string list (dedup, empty dropped) and emit a single `X-Tags: a, b, c` header. This is the format Mailpit indexes for filtering in its UI.

SES Message Tags are a different feature (`SendEmail.Tags[]` key/value pairs for CloudWatch dimensions) with no value in our current call sites and no path through the FuelPHP driver. When that need is real, it will be modeled as a separate concept on `EmailMessage` (`withMetric(name, value)`).

### Environment behaviour summary

| Env | Email transport | Recipient rewrite | SES config-set | SMS transport |
|---|---|---|---|---|
| `production` | SES (real) | no | `php-ddd-production-email-{alias}` | Twilio (real) |
| `staging` | SES (real) | yes → `staging.php-ddd.jp` | `php-ddd-staging-email-{alias}` | `sms-mock-server` via `TWILIO_API_BASE_URL` |
| `development` | Mailpit (SMTP) | no | skipped (driver is not SES) | `sms-mock-server` via `TWILIO_API_BASE_URL` |
| `test` | n/a | n/a | always skipped | `sms-mock-server` |

Email and SMS use intentionally different staging strategies. Email goes through **real SES** so we exercise the provider's signing, deliverability, and configuration-set event pipeline, then rewrites the recipient domain so messages land in our Mailpit instance — exercising the real send path is itself the value. SMS goes through the **mock server** because Twilio charges per send and we have no equivalent reason to exercise the real provider in staging — the Twilio SDK call itself is well-covered by unit tests, and a real send to a sink number adds cost without adding test coverage.

## Consequences

- HTTP requests never block on SES/Twilio; the user-visible command commits and returns, delivery happens later in the worker.
- Zero notification-specific state: no migrations, no notification-owned consumer infrastructure to operate. Durability comes from ADR-005.
- `backend/` is free of Twilio and AWS SDK dependencies; framework-agnostic unit tests stay clean.
- Notification incidents surface in the SQS/DLQ monitoring stack, not in a notification-specific dashboard. No in-app delivery tracking — provider message IDs are returned for manual lookup, but the application stores nothing.
- Tight coupling to FuelPHP's `Email\Email` driver layer. The Laravel-side implementation will reimplement against the AWS SDK directly.

## Future Work

Deliberate deferrals — implement only when a concrete trigger appears.

- **SMS sender role / multi-service.** When a second product in the monolith needs its own Twilio number, introduce `SmsSenderRole` + `SmsSenderRegistry` mirroring the email side. Trigger: a second `TWILIO_SMS_FROM_*` is wired.
- **Multi-domain email.** When a second product sends email from its own verified domain, extend `SenderRegistry` to `(ServiceContext, EmailSenderRole)`. Trigger: a second `EMAIL_DOMAIN_*` is wired.
- **In-app delivery tracking.** When opens/bounces/complaints must drive in-app behaviour, add an SES SNS webhook ingestor writing to a `notification_log` table keyed by provider message ID. The notifier already returns the ID; persisting it is additive.
- **Direct AWS-SDK `SesEmailNotifier`.** When Laravel becomes the primary runtime, drop the FuelPHP Email driver shim. `EmailNotifierInterface` does not change.

## References

- ADR-005: Event System Architecture (outbox / SQS / DLQ).
- ADR-006: CQRS Message Bus (decorator chain — relevant because notification listeners run inside command handlers' downstream pipeline).
- `backend/src/SharedKernel/Domain/Notification/` and `…/Infrastructure/Notification/Email/`.
- `fuelphp/fuel/packages/infrastructure/classes/Notification/` — `FuelPhpEmailNotifier`, `TwilioSmsNotifier`, `MockTwilioHttpClient`, `SmsNotifierFactory`.
- `fuelphp/fuel/app/classes/email/driver/ses.php`.
- `fuelphp/fuel/app/config/di.php` — DI wiring.
- `docker-compose.yml` — `mail` (Mailpit), `sms` (sms-mock-server).
