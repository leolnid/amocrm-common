# Changelog

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

