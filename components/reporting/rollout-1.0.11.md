# Reporting 1.0.11 rollout candidate

Status: local candidate only. No production changes, notifications, jobs or CRM replay.

Use the existing `modernization/project-matrix.json` `reporting_current` entries as the
target inventory; select every project currently using common-reporting 1.0.10. Do not
infer the target count from a chat label. This plan covers all matching applications.

1. Review this source diff and project-specific typed contexts before any publication.
   Run `php tests/reporting/run.php`, build the deterministic distribution through
   `components/reporting/build_dist.py`, verify archive SHA256 and every member.
2. Refresh read-only runtime captures for every target (server IP plus SHA256 of raw
   `/etc/machine-id`, runtime/application roots, state JSON, component/member hashes,
   Composer metadata, source preimages and worker/scheduler controls). C3 courier is
   81.200.158.173; identity b2720a9952d34a8570840539e77c725f6241da168ca02464d8cae4222998d312.
   Cross-check each target against the LIVE nginx document root, Supervisor working
   directory/command and scheduler entry. Historical physical release directories
   (especially flowwow-ms) may be inactive after another chat's deployment; refuse
   stale targets and never overwrite newer application sources from an old scope.
3. Prepare separate per-target overlay candidates using the existing
   `v110-relay-native-cache/reporting_component_overlay_v110_nativecache.py` and special
   adapters as the reviewed controller baseline. A v110 controller MUST NOT be invoked
   with a v111 artifact: its RELEASE_VERSION and exact source/config change policies
   intentionally reject a different version. Review a v111 controller with version
   1.0.11 and zero configuration changes, preserving all identity/state/vendor guards.
4. Component scope: only vendor/leolnid/common-reporting, reviewed Composer generated
   metadata, composer.json/lock and reconciled state manifests. Preserve existing SDKs,
   relay credentials, config cache, providers and all other vendor/application files.
   Courier additionally changes app/Console/Commands/DeliverApplicationErrors.php to
   extend LaravelDeliverApplicationErrors. BP1/MC/mail project patches are separate.
5. Use existing encrypted preflight backups and guarded apply only after root review
   of the concrete full-source and member-hash delta. No `composer update` in production.
   Respect the special native cache handling of courier and existing special ports.
6. After apply: verify exact source and expanded component/member hashes, Composer
   assignability, state reconciliation, PHP syntax/bootstrap, worker readiness and
   single scheduler. Read fresh logs during natural scheduler/business execution.
   Never run app-errors:test, drain/replay jobs or send Telegram acceptance messages.
7. Confirm courier exhausted envelope hashes/attempts are unchanged; natural delivery
   slot returns success with one deduplicated actionable local review warning. Genuine
   drain exceptions remain nonzero and local diagnostic chains remain available.
   All other unknown failures retain safe causes, runtime context and event correlation.

Rollback: restore only the reviewed backed-up component/metadata/source/state members
with the same guarded controller; recheck natural runtime and worker readiness. Do not
restore, delete or reset outbox/business data. Target acceptance remains unconfirmed
until per-target live verification succeeds; a local fixture is not that evidence.
