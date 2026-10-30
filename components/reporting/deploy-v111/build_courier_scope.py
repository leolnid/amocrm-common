"""Build C3's exact three-file candidate from a freshly captured source state."""
import base64,copy,hashlib,io,json,os,pathlib,subprocess,tarfile,zipfile
HERE=pathlib.Path(__file__).resolve().parent
ROOT=HERE.parents[2]
BASE=ROOT.parent.parent
RUN=HERE/'runs/courier'
REPO=pathlib.Path(r'C:\Development\amorenat.ru\bazacvetov\couriers')
OBJECTS=BASE/'work/v109-application-sources-20261029/worktrees/courier'
OLD=BASE/'work/v110-extra-mc-courier-special-scopes-artifacts-20261029-r1/scopes/courier-common-v110-scope.json'
BUILDER=BASE/'work/v108-application-sources-20261028/build_v108_composer_metadata.py'
DIST=ROOT/'dist-v111-final'
def sha(raw):return hashlib.sha256(raw).hexdigest()
def canonical(value):return sha(json.dumps(value,sort_keys=True,separators=(',',':'),ensure_ascii=True).encode())
def run(args,**kwargs):
 p=subprocess.run(args,capture_output=True,**kwargs)
 if p.returncode:raise RuntimeError('command_failed_'+str(p.returncode)+':'+p.stderr.decode(errors='replace')[-400:])
 return p.stdout
def git(*args,**kwargs):return run(['git','-C',str(REPO),*args],**kwargs)
def main():
 old=json.loads(OLD.read_bytes());live=json.loads((RUN/'capture-r2.json').read_bytes());assert live['identity_match'] and live['source_drift']==[]
 state=live['state_document'];manifest=state['source_manifest'];parent=manifest['candidate_commit'];meta=json.loads((DIST/'package-metadata.json').read_bytes());release=json.loads((DIST/'release-manifest.json').read_bytes());proof=json.loads((DIST/'published-verification.json').read_bytes());assert proof['status']=='PASS' and proof['reference']==meta['dist']['reference']
 inputs=RUN/'inputs';output=RUN/'metadata';inputs.mkdir(exist_ok=True)
 for rel,name in [('composer.json','composer.json'),('composer.lock','composer.lock'),('vendor/composer/installed.json','installed.json'),('vendor/composer/installed.php','installed.php')]:
  raw=base64.b64decode(live['files'][rel]['raw_b64']);assert sha(raw)==live['files'][rel]['sha256'];(inputs/name).write_bytes(raw)
 oldmeta=next(x for x in json.loads((inputs/'composer.lock').read_bytes())['packages'] if x['name']=='leolnid/common-reporting')
 args=['python',str(BUILDER),'--composer-json',str(inputs/'composer.json'),'--composer-lock',str(inputs/'composer.lock'),'--installed-json',str(inputs/'installed.json'),'--installed-php',str(inputs/'installed.php'),'--package-metadata',str(DIST/'package-metadata.json'),'--expected-metadata-sha256',sha((DIST/'package-metadata.json').read_bytes()),'--new-version','1.0.11','--expected-reference',meta['dist']['reference'],'--old-version','1.0.10','--old-reference',oldmeta['dist']['reference'],'--output-dir',str(output)]
 built=json.loads(run(args));assert built['status']=='METADATA_CANDIDATE_BUILT'
 git('fetch','--no-tags',str(OBJECTS),parent)
 for name in ['composer.json','composer.lock']:
  assert git('show',parent+':'+name).replace(b'\r\n',b'\n')==(inputs/name).read_bytes().replace(b'\r\n',b'\n')
 sources={'composer.json':(output/'composer.json').read_bytes(),'composer.lock':(output/'composer.lock').read_bytes(),'app/Console/Commands/DeliverApplicationErrors.php':(HERE/'DeliverApplicationErrors.php').read_bytes()}
 index=REPO/'.git/reporting-v111-candidate.index';assert not index.exists();env=os.environ.copy();env['GIT_INDEX_FILE']=str(index)
 try:
  git('read-tree',parent,env=env)
  for name,raw in sources.items():
   blob=git('hash-object','-w','--stdin',input=raw).decode().strip();git('update-index','--add','--cacheinfo','100644',blob,name,env=env)
  tree=git('write-tree',env=env).decode().strip();commit=git('commit-tree',tree,'-p',parent,input=b'fix(reporting): upgrade shared diagnostics and stop exhausted outbox recursion\n').decode().strip()
 finally:
  if index.exists():index.unlink()
 branch='refs/heads/incident/reporting-v111-20261031';git('update-ref',branch,commit,'0'*40)
 candidate=copy.deepcopy(manifest);candidate['candidate_commit']=commit;candidate['ancestor_commits']=list(dict.fromkeys([parent,*manifest.get('ancestor_commits',[])]))
 sourcepaths={}
 for name,raw in sources.items():
  sourcepaths[name]={'old':live['files'][name]['sha256'],'new':sha(raw)};candidate['files'][name]=sha(raw.replace(b'\r\n',b'\n'));candidate.setdefault('normalization_by_path',{})[name]='crlf_to_lf'
 assert set(git('diff','--name-only',parent,commit).decode().splitlines())==set(sources)
 zipraw=(DIST/release['zip']['filename']).read_bytes();assert sha(zipraw)==release['zip']['sha256']
 with zipfile.ZipFile(io.BytesIO(zipraw)) as z:package={name:z.read(name) for name in z.namelist()}
 assert {n:sha(raw) for n,raw in package.items()}==release['members']
 installed={'vendor/composer/installed.json':(output/'installed.json').read_bytes(),'vendor/composer/installed.php':(output/'installed.php').read_bytes()}
 members={**sources,**installed,'_dist/common-reporting.zip':zipraw,'_dist/release-manifest.json':(DIST/'release-manifest.json').read_bytes(),**{'vendor/leolnid/common-reporting/'+n:raw for n,raw in package.items()}}
 artifact=RUN/'courier-common-v111-overlay.tar.gz'
 with tarfile.open(artifact,'w:gz',format=tarfile.PAX_FORMAT) as tf:
  for name,raw in sorted(members.items()):
   ti=tarfile.TarInfo(name);ti.size=len(raw);ti.mode=0o644;ti.mtime=0;tf.addfile(ti,io.BytesIO(raw))
 profile=copy.deepcopy(old['profile']);profile.update(live_manifest=manifest,candidate_manifest=candidate,source_archive_sha256=sha(artifact.read_bytes()),config_change_hashes={})
 info=live['state'];par=live['state_parent'];recorded={k:info[k] for k in ['sha256','dev','ino','size','mtime_ns','uid','gid','mode']};recorded.update(exists=True,path=old['recorded_state']['path'],application_root=old['recorded_state']['application_root'],runtime_root=live['runtime_root'],server_identity_sha256=profile['server_identity_sha256'],source_manifest_sha256=canonical(manifest),source_commit=parent);recorded.update({'parent_'+k:par[k] for k in ['dev','ino','uid','gid','mode']})
 scope={'schema_version':1,'profile':profile,'artifact_path':'/run/courier-common-v111-overlay-'+commit[:8]+'.tar.gz','artifact_sha256':sha(artifact.read_bytes()),'source_paths':sourcepaths,'vendor_metadata_paths':{n:{'old':live['files'][n]['sha256'],'new':sha(raw)} for n,raw in installed.items()},'runtime_cache_paths':{},'provider_additions':[],'protected_cache_paths':old.get('protected_cache_paths',[]),'vendor_before':live['vendor_fingerprint'],'component':{'canonical_url':release['source_repository'],'canonical_reference':meta['dist']['reference'],'package_metadata':meta,'files':release['members'],'release_manifest_sha256':sha((DIST/'release-manifest.json').read_bytes()),'canonical_verified':True,'release_review_sha256':sha((DIST/'published-verification.json').read_bytes())},'autoload_bindings':old['autoload_bindings'],'autoload_assignability':old['autoload_assignability'],'control_directory':'/var/www/.courier-component-v111-20261031-'+commit[:8],'backup_public_key':old['backup_public_key'],'recorded_state':recorded}
 path=RUN/'courier-common-v111-scope.json';path.write_bytes((json.dumps(scope,sort_keys=True,indent=2)+'\n').encode());report={'project':'courier','parent':parent,'commit':commit,'branch':branch,'scope':str(path),'scope_sha256':sha(path.read_bytes()),'artifact':str(artifact),'artifact_sha256':sha(artifact.read_bytes()),'members':len(members),'production_changed':False};(RUN/'candidate-report.json').write_bytes((json.dumps(report,indent=2)+'\n').encode());print(json.dumps(report))
if __name__=='__main__':main()
