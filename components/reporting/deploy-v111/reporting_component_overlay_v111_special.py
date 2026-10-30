#!/usr/bin/env python3
"""State-safe v1.0.11 adapter for the four reviewed special S2 profiles (r2).

This module composes the frozen FEC state transaction with the separately
reviewed 268 special-profile controller. It narrows the accepted projects and
keeps all profile-specific topology checks in that controller.
"""
import hashlib
import importlib.util
import pathlib
import re
import signal
import stat
import sys
import time

BASE_SHA = 'bf116ad453c8a20f0b73c352bcf1695b28b87b60d7da687526d43bea8352b423'
SPECIAL_SHA = '3f983b6b46d0a9e50fc954a267c7eb073a7c78ee95374bf53deb0fd9aafd18c7'
MIXIN_SHA = 'd1c0ff39e2ddcc19a36e5eb00737bc1d6a4bd88a1449187a4b40d066d6597a4f'
ADAPTER_SHA = 'ac2332daf66355ba84c286f5ad59cbef23ee1d105d2611ca1f4974f66a383414'
SPECIAL_PROJECTS = {'contact-channel', 'bp', 'flowwow-ms', 'salesbots'}
ROOTS = {
    'contact-channel': ('/var/www/contact-channel.amorenat.ru', '/var/www/contact-channel-releases/compact-1728a256-20261025'),
    'bp': ('/var/www/bp.amorenat.ru', '/var/www/bp-releases/compact-50dea816-20261024'),
    'flowwow-ms': ('/var/www/flowwow-ms-releases/20261029-control-dd29317', '/var/www/flowwow-ms-releases/20261029-control-dd29317'),
    'salesbots': ('/var/www/salesbots-launcher.amorenat.ru', '/var/www/salesbots-launcher-releases/compact-7173202b-20261024'),
}


def _load(name, path, expected):
    path = pathlib.Path(path)
    if path.is_symlink() or not path.is_file() or hashlib.sha256(path.read_bytes()).hexdigest() != expected:
        raise RuntimeError('reviewed_special_dependency_hash_mismatch')
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def install(base, here):
    if hashlib.sha256(pathlib.Path(base.__file__).read_bytes()).hexdigest() != BASE_SHA:
        raise RuntimeError('reviewed_v111_base_hash_mismatch')
    special = _load('reviewed_special_profile_executor', here / 'reporting_component_overlay_268_special_ports.py', SPECIAL_SHA)
    redis = _load('reviewed_native_redis_mixin', here / 's2_native_redis_queue_mixin.py', MIXIN_SHA)
    adapter = here / 's2_native_redis_queue_adapter.php'
    adapter_text = adapter.read_text(encoding='utf-8')
    if hashlib.sha256(adapter_text.encode()).hexdigest() != ADAPTER_SHA:
        raise RuntimeError('reviewed_native_redis_adapter_hash_mismatch')

    original_safe_refusal = base.safe_refusal_code
    def safe_refusal(exc):
        if (type(exc).__name__ == 'Refuse' and
                type(exc).__module__ == 'reviewed_special_profile_executor' and
                re.fullmatch(r'[a-z][a-z0-9_]{0,99}', str(exc))):
            return str(exc)
        return original_safe_refusal(exc)
    base.safe_refusal_code = safe_refusal

    # This adapter owns exactly four reviewed project root pairs. Bind all four
    # explicitly so a stale general-profile entry cannot win for any special app.
    if set(ROOTS) != SPECIAL_PROJECTS:
        raise RuntimeError('special_root_table_not_finite')
    for project, pair in ROOTS.items():
        if (not isinstance(pair, tuple) or len(pair) != 2 or
                any(not isinstance(value, str) or not value.startswith('/') for value in pair)):
            raise RuntimeError('special_root_pair_invalid')
        base.APP_ROOTS[project] = pair
    if any(base.APP_ROOTS.get(project) != ROOTS[project] for project in SPECIAL_PROJECTS):
        raise RuntimeError('special_root_binding_mismatch')

    def assert_resolved_roots(controller, project):
        expected = ROOTS[project]
        # Path.as_posix() keeps the reviewed Linux paths stable when the
        # offline fixture itself runs on Windows. The real executor resolves
        # `root` from `entry` with strict=True before this check.
        if (controller.entry.as_posix(), controller.root.as_posix()) != expected:
            raise base.Refuse('special_resolved_root_pair_not_in_finite_scope')

    def load_controller(scope):
        profile = scope['profile']
        project = profile.get('project')
        if project not in SPECIAL_PROJECTS:
            raise base.Refuse('special_project_not_in_finite_scope')
        if ROOTS.get(project) is None or profile.get('application_root') != ROOTS[project][0]:
            raise base.Refuse('special_application_entry_not_in_finite_scope')
        if not re.fullmatch(r'[0-9a-f]{40}', scope.get('component', {}).get('canonical_reference', '')):
            raise base.Refuse('special_component_reference_invalid')
        if scope.get('component', {}).get('package_metadata', {}).get('version') != '1.0.11':
            raise base.Refuse('v111_component_required')
        base.validate_config_change_policy(project, scope.get('source_paths', {}), profile.get('config_change_hashes', {}))
        if scope.get('runtime_cache_paths') or scope.get('provider_additions'):
            raise base.Refuse('v111_special_runtime_cache_or_provider_delta_forbidden')
        if set(scope.get('vendor_metadata_paths', {})) != base.META2:
            raise base.Refuse('v111_special_installed_metadata_only_required')

        class Controller(special.ExistingRelease):
            def check_untracked_runtime(self):
                return

            def recorded_manifest(self):
                base.bind_recorded_manifest(self, scope, base.wrap_core_validate_profile(special.validate_profile))

            def persist_state(self):
                if self._component_state_was_absent:
                    base.persist_absent_candidate_state(self, base.candidate_state_bytes(self))
                else:
                    base.persist_candidate_state(self)

            def preflight(self):
                result = super().preflight()
                assert_resolved_roots(self, project)
                cache = self.root / 'bootstrap/cache/config.php'
                self._initial_cache_identity = base.cache_identity(cache)
                parent = cache.parent.stat()
                self._initial_cache_parent_identity = (
                    parent.st_dev, parent.st_ino, parent.st_uid, parent.st_gid,
                    stat.S_IMODE(parent.st_mode),
                )
                self._initial_cache_acl = base.access_acl(cache)
                self.temp_cache = None
                if 'cron_file' in self.p:
                    self.cron_file_current_identity = self._file_identity(self.cron_file_path)
                return result

            @staticmethod
            def _file_identity(path):
                info = pathlib.Path(path).lstat()
                return (info.st_dev, info.st_ino, info.st_uid, info.st_gid,
                        stat.S_IMODE(info.st_mode), info.st_size, info.st_mtime_ns)

            def write_cron_file(self, content, expected_sha, target_sha):
                super().write_cron_file(content, expected_sha, target_sha)
                self.cron_file_current_identity = self._file_identity(self.cron_file_path)

            def config_equal(self, old, new, path=''):
                return base.config_equal(old, new, self.p.get('config_change_hashes', {}), path.lstrip('.'))

            def build_config(self):
                cache = self.root / 'bootstrap/cache/config.php'
                if not self.p.get('config_change_hashes'):
                    if cache.read_bytes() != self.old_cache or base.cache_identity(cache) != self._initial_cache_identity:
                        raise base.Refuse('cached_configuration_anchor_drift')
                    self.report['configuration_strategy'] = 'preserved_exact_existing_bytes_and_metadata'
                    return
                base.build_native_config_cache(self, scope)

            def restore_nginx_public(self, receipt_key):
                previous = self.nginx_worker_pids()
                plural = 'nginx_configs' in self.p
                public = ({key: row['before'] for key, row in self.nginx_snapshots.items()}
                          if plural else self.nginx_before)
                try:
                    self.nginx(public)
                except BaseException:
                    if plural:
                        self.gated = all(
                            row['path'].read_bytes() == self.nginx_gates[key]
                            for key, row in self.nginx_snapshots.items()
                        )
                    else:
                        self.gated = self.nginx_path.read_bytes() == self.nginx_gate
                    raise
                # A public config may be live while its previous worker
                # generation drains. Mark it ungated before waiting so the
                # transaction rollback will restore the gate on timeout.
                self.gated = False
                deadline = time.monotonic() + 120
                while any(pathlib.Path('/proc', str(pid)).exists() for pid in previous):
                    if time.monotonic() > deadline:
                        raise base.Refuse('restored_nginx_generation_not_drained')
                    time.sleep(.25)
                self.report[receipt_key] = {
                    'previous_worker_pids': sorted(previous),
                    'current_worker_pids': sorted(self.nginx_worker_pids()),
                }

            def gate(self):
                # The reviewed special controller owns dual-file gate logic.
                return super().gate()

        if project == 'contact-channel':
            class RedisController(redis.NativeRedisQueueMixin, Controller):
                NativeQueueRefuse = base.Refuse

                def validate_queue_connections(self):
                    redis.NativeRedisQueueMixin.validate_queue_connections(self)
                    names = {name for values in self._redis_worker_specs.values() for name in values}
                    base.need(names == {'default', 'backfill'} and len(self.worker_rows) == 2,
                              'redis_queue_topology_drift')

            controller = RedisController(profile, scope['artifact_path'])
            assert_resolved_roots(controller, project)
            controller.bind_native_redis_adapter(adapter_text)
            return controller
        if project == 'salesbots':
            class RedisController(redis.NativeRedisQueueMixin, Controller):
                NativeQueueRefuse = base.Refuse

                def validate_queue_connections(self):
                    redis.NativeRedisQueueMixin.validate_queue_connections(self)
                    base.need(self.p.get('worker_groups') == {'salesbots-launcher-amorenat-ru-default': 1},
                              'salesbots_worker_topology_drift')
                    base.need(self._redis_worker_specs == {'redis': ['default']} and len(self.worker_rows) == 1,
                              'salesbots_redis_queue_scope_drift')

            controller = RedisController(profile, scope['artifact_path'])
            assert_resolved_roots(controller, project)
            controller.bind_native_redis_adapter(adapter_text)
            return controller
        controller = Controller(profile, scope['artifact_path'])
        assert_resolved_roots(controller, project)
        return controller

    def protected(self):
        p = self.p
        paths = ['.env', *self.s.get('protected_cache_paths',
                 ['bootstrap/cache/packages.php', 'bootstrap/cache/services.php',
                  'bootstrap/cache/events.php', 'bootstrap/cache/routes-v7.php'])]
        paths = [x for x in paths if x not in self.s.get('runtime_cache_paths', {})]
        out = {x: base.sha((p.root / x).read_bytes()) if (p.root / x).is_file() else None for x in paths}
        out.update({x: base.sha(pathlib.Path(x).read_bytes()) for x in p.supervisor_hashes})

        if 'nginx_snapshots' in p.__dict__:
            gate_hashes = getattr(p, 'nginx_gates', {})
            rows = {}
            for key, snapshot in p.nginx_snapshots.items():
                expected = gate_hashes[key] if p.gated else snapshot['before']
                current = snapshot['path'].read_bytes()
                base.need(current == expected, 'nginx_unreviewed_change')
                rows[key] = base.sha(snapshot['before'])
            out['nginx'] = rows
        else:
            base.need(p.nginx_path.read_bytes() == (p.nginx_gate if p.gated else p.nginx_before),
                      'nginx_unreviewed_change')
            out['nginx'] = base.sha(p.nginx_before)

        if 'cron_file' in p.p:
            expected = p.cron_paused_sha if p.cron_paused else p.cron_before_sha
            base.need(base.sha(p.cron_file_path.read_bytes()) == expected, 'cron_unreviewed_change')
            p.cron_file_metadata(p.cron_file_path)
            base.need(p._file_identity(p.cron_file_path) == p.cron_file_current_identity,
                      'dedicated_cron_file_identity_drift')
            out['cron'] = p.cron_before_sha
        else:
            expected = p.cron_paused_content if p.cron_paused else p.cron_before
            base.need(p.run(['crontab', '-u', p.p.get('cron_user', 'www-data'), '-l']) == expected,
                      'cron_unreviewed_change')
            out['cron'] = base.sha(p.cron_before)

        out['env_inode'] = [p.env_anchor.stat().st_dev, p.env_anchor.stat().st_ino]
        out['storage_inode'] = [p.storage_anchor.stat().st_dev, p.storage_anchor.stat().st_ino]
        out.update(self.state_protection())
        return out

    base.load_controller = load_controller
    base.ComponentOverlay.protected = protected
    return base


def main(argv=None):
    here = pathlib.Path(__file__).resolve().parent
    base = _load('reviewed_v111_nativecache', here / 'reporting_component_overlay_v111.py', BASE_SHA)
    base = install(base, here)
    if len(sys.argv) not in (2, 3) or (len(sys.argv) == 3 and sys.argv[2] not in ('--backup', '--apply')):
        print('{"status":"FAIL","safe_error_code":"unsupported_cli_arguments"}')
        return 2
    mode = {'--backup': 'backup', '--apply': 'apply'}.get(sys.argv[2] if len(sys.argv) > 2 else '', 'preflight')
    def cancelled(*_):
        raise base.Refuse('component_overlay_cancelled')
    signal.signal(signal.SIGTERM, cancelled)
    signal.signal(signal.SIGHUP, cancelled)
    try:
        print(base.json.dumps(base.execute(sys.argv[1], mode)))
        return 0
    except BaseException as exc:
        print(base.json.dumps({'status': 'FAIL', 'safe_error_class': type(exc).__name__,
                               'safe_error_code': base.safe_refusal_code(exc)}))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
