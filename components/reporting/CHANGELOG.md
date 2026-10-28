# Changelog

## 1.0.10

- Read the technical-reporting Telegram relay from the isolated `app_errors.telegram` config namespace before the legacy service keys, so native Laravel Telegram SDK configuration is not redirected.
- Keep legacy service-key fallback for existing deployments and retain the HTTPS, origin, and request-scoped credential safeguards.

## 1.0.9

- Route Telegram delivery through an explicitly configured HTTPS relay with a request-scoped service credential; preserve the default API path when relay settings are absent.
- Reject non-HTTPS endpoints and prevent relay credentials from reaching the normalized official Telegram API origin.

## 1.0.8

- Clarify that HTTP counters represent received requests and job counters represent background execution; distinguish logged action errors from job failures.

## 1.0.7

- Persist safe outbox delivery diagnostics through the configured Laravel single log channel; retain a fail-open standalone fallback.

## 1.0.6

- Record safe per-envelope delivery failure diagnostics (exception class, HTTP status, allowlisted host, reason bucket, attempt and opaque ID) without request details or payloads.

## 1.0.5

- Correlate confirmed notifications from registered scheduled console child commands with their parent run, without extending correlation to queue jobs or the schedule runner.
- Retain validated upstream host and HTTP status in structured error metadata without request URLs or response bodies.

## 1.0.4

- Classify the SDK's fixed amoCRM token-parse failure without exposing token details.
- Render safe HTTP status and upstream hostname when available; make unknown failures actionable without raw exception text.
- Correlate foreground scheduler wrappers with a child notification only after confirmed delivery, while keeping cache failures fail-open.
- Clarify partial daily coverage, observed error-log categories, and the distinction between log records and business operations.

## 1.0.3

- Allow projects without an admin UI to render a plain project heading while retaining validated entity links.
- Report truthful partial daily coverage and observed counts.


## 1.0.2

- Render typed, extensible error context with escaped labels and verified links.
- Preserve known context and safe reasons for unmapped failures; keep benign validation and health noise local.
- Add a backward-compatible failure-context hook and shared loaded-entity observer.
- Improve sanitized exception detail, correlation, and object-scoped deduplication.
- Add reusable fake-transport semantic and full reporter pipeline fixtures.

