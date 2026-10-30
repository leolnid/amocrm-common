from __future__ import annotations
import base64,hashlib,json,pathlib,subprocess,sys,uuid
HERE=pathlib.Path(__file__).resolve().parent
BASE=HERE.parents[4]
SRC=HERE/'runs/courier'
OUT=SRC/'preflight-backup'
CORE=BASE/'work/v109-application-sources-20261029/frozen-core-268-readonly.py'
FILES={name:(digest,HERE/name) for name,digest in json.loads((HERE/'controller-manifest.json').read_bytes()).items() if name!='DeliverApplicationErrors.php'}
HOSTS={'courier':('81.200.158.173','b2720a9952d34a8570840539e77c725f6241da168ca02464d8cae4222998d312')}
PROJECTS=('courier',)
CORE_SHA='268626e13b80c4578f0d63d98ad65c81bf5833bec59bda49be0b49e7e9cc2f23'
def sha(b):return hashlib.sha256(b).hexdigest()
def run(project):
 assert project in PROJECTS and sha(CORE.read_bytes())==CORE_SHA
 for expected,path in [(v[0],v[1]) for v in FILES.values()]: assert sha(path.read_bytes())==expected
 scope_path=SRC/f'{project}-common-v111-scope.json'; artifact_path=SRC/f'{project}-common-v111-overlay.tar.gz'
 scope=json.loads(scope_path.read_text()); assert scope['profile']['project']==project
 artifact=artifact_path.read_bytes();assert sha(artifact)==scope['artifact_sha256']
 ip,identity=HOSTS[project]; helper_name='reporting_component_overlay_v111_special.py' if project in {'bp','contact-channel','flowwow-ms','salesbots'} else 'reporting_component_overlay_v111.py'
 helper_hash=dict((k,v[0]) for k,v in FILES.items())[helper_name]
 runid=f'v111-extra-{project}-{uuid.uuid4().hex[:12]}';rd='/run/'+runid;spremote=rd+'/scope.json';hpremote=rd+'/'+helper_name;apremote=rd+'/artifact.tar.gz';scope['artifact_path']=apremote;scope_raw=(json.dumps(scope,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
 assets={name:path.read_bytes() for name,(_,path) in FILES.items()}; assets['frozen-core-268.py']=CORE.read_bytes()
 payload={'project':project,'ip':ip,'identity_sha256':identity,'run_dir':rd,'scope_path':spremote,'helper_path':hpremote,'artifact_path':apremote,'scope_sha256':sha(scope_raw),'artifact_sha256':sha(artifact),'helper_sha256':helper_hash,'core_sha256':CORE_SHA,'scope_b64':base64.b64encode(scope_raw).decode(),'artifact_b64':base64.b64encode(artifact).decode(),'assets':{k:base64.b64encode(v).decode() for k,v in assets.items()},'asset_hashes':{k:sha(v) for k,v in assets.items()},'control_directory':scope['control_directory'],'candidate_commit':scope['profile']['candidate_manifest']['candidate_commit'],'parent_commit':scope['profile']['live_manifest']['candidate_commit']}
 remote=r'''import base64,hashlib,json,os,pathlib,stat,sys,subprocess
D=json.loads(PAYLOAD_JSON)
def guard():
 c=os.environ.get('SSH_CONNECTION','').split();assert len(c)==4 and c[2]==D['ip'];assert hashlib.sha256(pathlib.Path('/etc/machine-id').read_bytes()).hexdigest()==D['identity_sha256']
def sha(b):return hashlib.sha256(b).hexdigest()
guard();run=pathlib.Path(D['run_dir']);parent=pathlib.Path('/run');assert parent.is_dir() and not parent.is_symlink() and parent.stat().st_uid==0;assert not run.exists() and not run.is_symlink();run.mkdir(mode=0o700);os.chown(run,0,0)
def put(path,raw,mode):
 p=pathlib.Path(path);assert not p.exists() and not p.is_symlink();fd=os.open(p,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode)
 try:os.write(fd,raw);os.fsync(fd)
 finally:os.close(fd)
 os.chown(p,0,0);p.chmod(mode);s=p.lstat();assert s.st_uid==0 and s.st_nlink==1 and stat.S_IMODE(s.st_mode)==mode and sha(p.read_bytes())==sha(raw)
scope=base64.b64decode(D['scope_b64']);artifact=base64.b64decode(D['artifact_b64']);assert sha(scope)==D['scope_sha256'] and sha(artifact)==D['artifact_sha256'];put(D['scope_path'],scope,0o600);guard();put(D['artifact_path'],artifact,0o644)
for name,encoded in D['assets'].items():
 raw=base64.b64decode(encoded);assert sha(raw)==D['asset_hashes'][name];guard();put(str(run/name),raw,0o600 if name!='frozen-core-268.py' else 0o644)
core=pathlib.Path('/run/s1-reporting-overlay-executor.py');core_raw=base64.b64decode(D['assets']['frozen-core-268.py'])
if core.exists() or core.is_symlink():assert core.is_file() and not core.is_symlink() and core.stat().st_uid==0 and sha(core.read_bytes())==D['core_sha256']
else:guard();put(str(core),core_raw,0o644)
assert core.stat().st_uid==0 and core.stat().st_nlink==1 and not(stat.S_IMODE(core.stat().st_mode)&0o022) and sha(core.read_bytes())==D['core_sha256']
assert run.stat().st_uid==0 and stat.S_IMODE(run.stat().st_mode)==0o700
def invoke(backup=False):
 guard();p=subprocess.run([sys.executable,D['helper_path'],D['scope_path']]+(['--backup'] if backup else []),stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=900)
 try:r=json.loads(p.stdout.decode().strip().splitlines()[-1])
 except Exception:r={'status':'HELPER_NON_JSON','stdout_bytes':len(p.stdout),'stderr_bytes':len(p.stderr)}
 return p,r
p1,r1=invoke(False)
if p1.returncode or r1.get('status')!='COMPONENT_PREFLIGHT_PASS' or r1.get('production_changed') is not False:print(json.dumps({'status':'PREFLIGHT_REFUSED','project':D['project'],'result':r1}));raise SystemExit(2)
p2,r2=invoke(True)
if p2.returncode or r2.get('status')!='ENCRYPTED_BACKUP_READY' or r2.get('production_changed') is not False or r2.get('local_auth_required') is not True:print(json.dumps({'status':'BACKUP_REFUSED','project':D['project'],'preflight':r1,'result':r2}));raise SystemExit(3)
guard();control=pathlib.Path(D['control_directory']);files={}
for n in ('backup.json','rollback.aesgcm','wrapped-key.bin'):
 p=control/n;s=p.lstat();assert p.is_file() and not p.is_symlink() and s.st_uid==0 and stat.S_IMODE(s.st_mode)==0o600;files[n]=base64.b64encode(p.read_bytes()).decode()
print(json.dumps({'status':'ENCRYPTED_BACKUP_READY','project':D['project'],'numeric_server_ip':D['ip'],'identity_guard':'PASS','scope_sha256':D['scope_sha256'],'artifact_sha256':D['artifact_sha256'],'preflight':r1,'backup':r2,'backup_files_b64':files,'remote_run_directory':D['run_dir']}))
'''.replace('PAYLOAD_JSON',repr(json.dumps(payload)))
 proc=subprocess.run(['ssh','-oBatchMode=yes','-oConnectTimeout=15','-oStrictHostKeyChecking=yes','root@'+ip,'python3','-'],input=remote.encode(),stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=2100)
 try:r=json.loads(proc.stdout.decode().strip().splitlines()[-1])
 except Exception:r={'status':'TRANSPORT_OR_NON_JSON_FAILURE','exit':proc.returncode,'stdout_bytes':len(proc.stdout),'stderr':proc.stderr.decode('utf-8','replace')[-500:]}
 if proc.returncode or r.get('status')!='ENCRYPTED_BACKUP_READY':raise RuntimeError(project+':'+json.dumps(r,ensure_ascii=True))
 local=OUT/project;local.mkdir(parents=True,exist_ok=False);downloads={}
 for name,encoded in r.pop('backup_files_b64').items():
  raw=base64.b64decode(encoded);(local/name).write_bytes(raw);downloads[name]={'sha256':sha(raw),'size':len(raw)}
 rec={'status':'READONLY_PREFLIGHT_AND_ENCRYPTED_BACKUP_READY','project':project,'production_changed':False,'numeric_server_ip':ip,'server_identity_sha256':identity,'helper_sha256':helper_hash,'frozen_core_sha256':CORE_SHA,'scope_sha256':sha(scope_raw),'artifact_sha256':scope['artifact_sha256'],'source_commit':scope['profile']['candidate_manifest']['candidate_commit'],'source_parent':scope['profile']['live_manifest']['candidate_commit'],'recorded_state':scope['recorded_state'],'preflight_backup_result':r,'backup_directory':str(local),'downloaded_files':downloads}
 rp=OUT/f'{project}-receipt.json';rp.write_text(json.dumps(rec,sort_keys=True,indent=2)+'\n',encoding='utf-8');return {'project':project,'receipt_path':str(rp),'receipt_sha256':sha(rp.read_bytes()),'scope_sha256':rec['scope_sha256'],'artifact_sha256':rec['artifact_sha256'],'backup_files':downloads,'preflight':rec['preflight_backup_result']['preflight'],'backup':rec['preflight_backup_result']['backup']}
if __name__=='__main__':
 OUT.mkdir(exist_ok=True)
 if len(sys.argv)!=2 or sys.argv[1] not in PROJECTS:raise SystemExit('usage: runner <project>')
 print(json.dumps(run(sys.argv[1]),sort_keys=True))
