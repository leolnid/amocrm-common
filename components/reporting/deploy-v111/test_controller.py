import hashlib, importlib.util, json, pathlib, sys, types, unittest
HERE=pathlib.Path(__file__).resolve().parent
sys.modules.setdefault('fcntl',types.SimpleNamespace(LOCK_EX=1,LOCK_NB=2,flock=lambda *_:None))
sys.modules.setdefault('pwd',types.SimpleNamespace(getpwuid=lambda *_:None))
sys.modules.setdefault('grp',types.SimpleNamespace(getgrgid=lambda *_:None))

def load(name,path):
    spec=importlib.util.spec_from_file_location(name,path);module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module

class ControllerScopeTest(unittest.TestCase):
    def setUp(self):self.base=load('v111_base',HERE/'reporting_component_overlay_v111.py')
    def test_component_version_and_all_targets(self):
        self.assertEqual(self.base.RELEASE_VERSION,'1.0.11');self.assertEqual(len(self.base.ALLOWED_PROJECTS),25)
        for project in self.base.ALLOWED_PROJECTS:
            paths={'composer.json','composer.lock'}|({'app/Console/Commands/DeliverApplicationErrors.php'} if project=='courier' else set())
            self.base.validate_config_change_policy(project,paths,{})
            for bad in [paths|{'config/app_errors.php'},paths|{'.env'},paths|{'app/Jobs/Replay.php'}]:
                with self.assertRaises(self.base.Refuse):self.base.validate_config_change_policy(project,bad,{})
            with self.assertRaises(self.base.Refuse):self.base.validate_config_change_policy(project,paths,{'app_errors.telegram.base_uri':{}})
    def test_flowwow_is_current_live_release(self):
        special=load('v111_special',HERE/'reporting_component_overlay_v111_special.py')
        base=special.install(self.base,HERE)
        expected=('/var/www/flowwow-ms-releases/20261029-control-dd29317',)*2
        self.assertEqual(base.APP_ROOTS['flowwow-ms'],expected)
        self.assertEqual(special.ROOTS['flowwow-ms'],expected)
        with self.assertRaises(base.Refuse):base.load_controller({'profile':{'project':'flowwow-ms','application_root':expected[0]},'component':{'canonical_reference':'a'*40,'package_metadata':{'version':'1.0.10'}}})
    def test_dependencies_and_member_manifest(self):
        manifest=json.loads((HERE/'controller-manifest.json').read_text())
        for name,digest in manifest.items():self.assertEqual(hashlib.sha256((HERE/name).read_bytes()).hexdigest(),digest)
        source=(HERE/'reporting_component_overlay_v111.py').read_text()
        for guard in ['old_vendor_or_sdk_drift','cached_configuration_anchor_drift','reviewed_controller_hash_mismatch','rollback_external_source_drift','full_source_delta_not_exact']:
            self.assertIn(guard,source)

if __name__=='__main__':unittest.main()
