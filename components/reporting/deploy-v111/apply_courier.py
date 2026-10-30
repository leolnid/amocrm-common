from __future__ import annotations
import base64,hashlib,json,pathlib,subprocess,sys
HERE=pathlib.Path(__file__).resolve().parent
BASE=HERE.parents[4]
SRC=HERE/'runs/courier'
BACK=SRC/'preflight-backup'
CORE=BASE/'work/v109-application-sources-20261029/frozen-core-268-readonly.py'
HELP=HERE/'reporting_component_overlay_v111.py'
CORE_SHA='268626e13b80c4578f0d63d98ad65c81bf5833bec59bda49be0b49e7e9cc2f23'
HELP_SHA='f88c32f8c79349d7602777aec449d51f0bbc86ac2b514223f9f402a16d15aa0d'
HOST={'courier':('81.200.158.173','b2720a9952d34a8570840539e77c725f6241da168ca02464d8cae4222998d312')}
def sha(b):return hashlib.sha256(b).hexdigest()
def run(project):
 assert project in HOST and sha(CORE.read_bytes())==CORE_SHA and sha(HELP.read_bytes())==HELP_SHA
 rec=json.loads((BACK/f'{project}-receipt.json').read_text());scopepath=SRC/f'{project}-common-v111-scope.json';scope=json.loads(scopepath.read_text());proofpath=BASE/'incident-20261031/courier-v111-root-backup-auth.local-auth-proof.json';proof_raw=proofpath.read_bytes();proof=json.loads(proof_raw);backup_meta=(BACK/project/'backup.json').read_bytes();artifactpath=SRC/f'{project}-common-v111-overlay.tar.gz';artifact=artifactpath.read_bytes()
 assert rec['status']=='READONLY_PREFLIGHT_AND_ENCRYPTED_BACKUP_READY' and rec['production_changed'] is False and rec['artifact_sha256']==sha(artifact)
 assert scope['profile']['project']==project and scope['artifact_sha256']==sha(artifact)
 rd=rec['preflight_backup_result']['remote_run_directory']; scope_bound=dict(scope); scope_bound['artifact_path']=rd+'/artifact.tar.gz'; scope_bound_raw=(json.dumps(scope_bound,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode(); assert rec['scope_sha256']==sha(scope_bound_raw)
 assert set(proof)=={'metadata_sha256','GCM_local_verified','members_sha256'} and proof['GCM_local_verified'] is True and proof['metadata_sha256']==sha(backup_meta)
 meta=json.loads(backup_meta);members_sha=sha(json.dumps(meta['members'],sort_keys=True,separators=(',',':'),ensure_ascii=True).encode());assert proof['members_sha256']==members_sha
 assert rec['preflight_backup_result']['backup']['metadata_sha256']==sha(backup_meta)
 assert sha((BACK/project/'rollback.aesgcm').read_bytes())==meta['cipher_sha256'] and sha((BACK/project/'wrapped-key.bin').read_bytes())==meta['wrapped_key_sha256']
 ip,identity=HOST[project];rd=rec['preflight_backup_result']['remote_run_directory'];remote_scope=rd+'/scope.json';remote_helper=rd+'/reporting_component_overlay_v111.py';remote_artifact=rd+'/artifact.tar.gz';control=rec['preflight_backup_result']['backup']['control_directory'];payload={'project':project,'ip':ip,'identity_sha256':identity,'run_dir':rd,'scope_path':remote_scope,'helper_path':remote_helper,'artifact_path':remote_artifact,'scope_sha256':rec['scope_sha256'],'helper_sha256':HELP_SHA,'core_sha256':CORE_SHA,'artifact_sha256':rec['artifact_sha256'],'control_directory':control,'backup_metadata_sha256':sha(backup_meta),'proof_sha256':sha(proof_raw),'proof_b64':base64.b64encode(proof_raw).decode()}
 remote=r'''import base64,hashlib,json,os,pathlib,stat,subprocess,sys
D=json.loads(PAYLOAD_JSON)
def guard():
 c=os.environ.get('SSH_CONNECTION','').split();assert len(c)==4 and c[2]==D['ip'];assert hashlib.sha256(pathlib.Path('/etc/machine-id').read_bytes()).hexdigest()==D['identity_sha256']
def sha(b):return hashlib.sha256(b).hexdigest()
guard();run=pathlib.Path(D['run_dir']);assert run.is_dir() and not run.is_symlink() and run.stat().st_uid==0 and stat.S_IMODE(run.stat().st_mode)==0o700
for rel,want in (('scope.json',D['scope_sha256']),('reporting_component_overlay_v111.py',D['helper_sha256']),('artifact.tar.gz',D['artifact_sha256'])):
 p=run/rel;s=p.lstat();assert p.is_file() and not p.is_symlink() and s.st_uid==0 and s.st_nlink==1 and sha(p.read_bytes())==want
core=pathlib.Path('/run/s1-reporting-overlay-executor.py');guard();assert core.is_file() and not core.is_symlink() and core.stat().st_uid==0 and sha(core.read_bytes())==D['core_sha256']
control=pathlib.Path(D['control_directory']);assert control.is_dir() and not control.is_symlink() and control.stat().st_uid==0 and stat.S_IMODE(control.stat().st_mode)==0o700
meta_path=control/'backup.json';meta_raw=meta_path.read_bytes();assert sha(meta_raw)==D['backup_metadata_sha256'];meta=json.loads(meta_raw)
proof=base64.b64decode(D['proof_b64']);assert sha(proof)==D['proof_sha256'];pd=json.loads(proof);assert set(pd)=={'metadata_sha256','GCM_local_verified','members_sha256'} and pd=={'metadata_sha256':sha(meta_raw),'GCM_local_verified':True,'members_sha256':sha(json.dumps(meta['members'],sort_keys=True,separators=(',',':'),ensure_ascii=True).encode())}
proof_path=control/'local-auth-proof.json';guard();assert not proof_path.exists() and not proof_path.is_symlink();fd=os.open(proof_path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
try:os.write(fd,proof);os.fsync(fd)
finally:os.close(fd)
os.chown(proof_path,0,0);proof_path.chmod(0o600);s=proof_path.lstat();assert s.st_uid==0 and s.st_nlink==1 and stat.S_IMODE(s.st_mode)==0o600 and sha(proof_path.read_bytes())==D['proof_sha256']
guard();p=subprocess.run([sys.executable,D['helper_path'],D['scope_path'],'--apply'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=2400)
try:r=json.loads(p.stdout.decode('utf-8').strip().splitlines()[-1])
except Exception:r={'status':'HELPER_NON_JSON','stdout_bytes':len(p.stdout),'stderr_bytes':len(p.stderr)}
if p.returncode or r.get('status')!='COMPONENT_OVERLAY_PASS' or r.get('production_changed') is not True:print(json.dumps({'status':'APPLY_FAIL','project':D['project'],'exit':p.returncode,'receipt':r}));raise SystemExit(3)
print(json.dumps({'status':'COMPONENT_OVERLAY_PASS','production_changed':True,'project':D['project'],'phase':r.get('phase'),'source_files':r.get('source_files'),'vendor_metadata_files':r.get('vendor_metadata_files'),'component_files':r.get('component_files'),'other_vendor_sdk_unchanged':r.get('other_vendor_sdk_unchanged'),'receipt':r}))
'''.replace('PAYLOAD_JSON',repr(json.dumps(payload)))
 p=subprocess.run(['ssh','-oBatchMode=yes','-oConnectTimeout=15','-oStrictHostKeyChecking=yes','root@'+ip,'python3','-'],input=remote.encode(),stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=2500)
 try:r=json.loads(p.stdout.decode().strip().splitlines()[-1])
 except Exception:r={'status':'REMOTE_NON_JSON','exit':p.returncode,'stderr_bytes':len(p.stderr)}
 if p.returncode or r.get('status')!='COMPONENT_OVERLAY_PASS':raise RuntimeError(project+':'+json.dumps(r,ensure_ascii=True))
 receipt=r.pop('receipt');rp=BACK/project/'apply-helper-receipt.json';rp.write_text(json.dumps(receipt,sort_keys=True,indent=2)+'\n',encoding='utf-8')
 return {'project':project,'status':r['status'],'production_changed':r['production_changed'],'phase':r.get('phase'),'source_files':r.get('source_files'),'vendor_metadata_files':r.get('vendor_metadata_files'),'component_files':r.get('component_files'),'other_vendor_sdk_unchanged':r.get('other_vendor_sdk_unchanged'),'receipt_path':str(rp),'receipt_sha256':sha(rp.read_bytes()),'scope_sha256':rec['scope_sha256'],'artifact_sha256':rec['artifact_sha256']}
if __name__=='__main__':
 if len(sys.argv)!=2:raise SystemExit('usage: apply_v111_extra_one.py mc|courier')
 print(json.dumps(run(sys.argv[1]),sort_keys=True))
