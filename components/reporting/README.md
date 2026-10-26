# Common reporting component

This opt-in component is maintained in the existing `leolnid/amocrm-common` repository.
It does not replace an application's existing Common or Rodionov package and does not
register providers automatically. Applications provide fixed operation/context catalogs,
existing SDK bindings, protected topic configuration, and explicit capture providers.

Requires PHP 8.5 and an existing Laravel 12 or 13 installation. Shared code lives only
under `src/Reporting`. No original Common provider, configuration, credentials, or Git
history is included in the distribution.

Error reasons are fixed codes, links use already-loaded validated identifiers, and
unbound/raw legacy records remain local. No financial retries or business operations
are performed by observers. Delivery uses the application's existing SDK through an
explicit transport port. Daily reports keep HTTP requests, technical job executions,
module runs, and optional console activity as distinct units.

Each release uses a full-commit tag and a deterministic ZIP_STORED archive. Composer
metadata is DIST-only: `dist.reference` identifies the canonical source commit,
`dist.shasum` checks SHA1, and the independent release manifest seals SHA256 and the
expanded member hashes. Installers must verify both archive and member manifests.
Existing vendor packages, lock entries, SDK discovery state, and application bindings
must remain unchanged except for this component and reviewed generated metadata.


## Error notification contract (1.0.3)

The shared reporter renders a linked project heading when an authenticated admin landing exists; projects without an admin UI use a plain bold heading. It also renders a concise sanitized failure and reason, and any available typed context items. Context is an open list of registered items such as account, module, request, deal, contact, document, rule, order, or payment; unavailable values are omitted. Labels and values are escaped for Telegram HTML, and links must be validated HTTP(S) URLs. Request timestamps are shown only when supplied from the actual record. Unknown operations retain safe context and a correlation code while benign validation, health, and unverified closure noise remain local.

Applications may extend `Leolnid\Common\Reporting\Capture\AbstractLoadedContextObserver` with a fixed `LABELS` catalog. It provides `attachEntity`, `attachEntities`, `attachId`, and `merge` for already-loaded typed objects; it does not fetch entities or call external APIs. The failure refinement hook receives existing metadata and scope while the original two-argument hook remains compatible.

Run the repository fixtures with `php tests/reporting/run.php`. They use a fake transport and synthetic configuration; they do not send notifications or perform business writes.
