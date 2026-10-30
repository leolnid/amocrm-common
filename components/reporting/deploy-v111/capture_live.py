"""Fresh read-only snapshot; no application boot, requests, retries or writes on target."""
import argparse,base64,hashlib,json,pathlib,subprocess

def capture(scope,output):
    old=json.loads(pathlib.Path(scope).read_bytes());profile=old['profile'];recorded=old['recorded_state']
    payload={'ip':profile['server_ip'],'identity':recorded['server_identity_sha256'],'root':recorded['runtime_root'],'state':recorded['path']}
    encoded=base64.b64encode(json.dumps(payload).encode()).decode()
    remote=r'''import pathlib,os,json,hashlib,base64,stat
x=json.loads(base64.b64decode('PAYLOAD'))
assert hashlib.sha256(pathlib.Path('/etc/machine-id').read_bytes()).hexdigest()==x['identity']
assert os.environ['SSH_CONNECTION'].split()[2]==x['ip']
r=pathlib.Path(x['root']);assert r.is_dir() and not r.is_symlink() and str(r.resolve())==str(r)
def read(p,include=False):
 p=pathlib.Path(p);a=p.lstat();assert stat.S_ISREG(a.st_mode) and not p.is_symlink()
 raw=p.read_bytes();z=p.lstat();assert (a.st_dev,a.st_ino,a.st_size,a.st_mtime_ns)==(z.st_dev,z.st_ino,z.st_size,z.st_mtime_ns)
 d={'sha256':hashlib.sha256(raw).hexdigest(),'dev':a.st_dev,'ino':a.st_ino,'size':a.st_size,'mtime_ns':a.st_mtime_ns,'uid':a.st_uid,'gid':a.st_gid,'mode':stat.S_IMODE(a.st_mode),'normalized_sha256':hashlib.sha256(raw.replace(b'\r\n',b'\n')).hexdigest()}
 if include:d['raw_b64']=base64.b64encode(raw).decode()
 return d
state=read(x['state'],True);doc=json.loads(base64.b64decode(state.pop('raw_b64')));parent=pathlib.Path(x['state']).parent.stat()
files={}
for rel in ['composer.json','composer.lock','app/Console/Commands/DeliverApplicationErrors.php','vendor/composer/installed.json','vendor/composer/installed.php']:
 files[rel]=read(r/rel,True)
tree={}
for here,ds,fs in os.walk(r/'vendor',followlinks=False):
 for name in list(ds):
  p=pathlib.Path(here)/name
  if p.is_symlink():tree[p.relative_to(r/'vendor').as_posix()]=['link',os.readlink(p)];ds.remove(name)
 for name in fs:
  p=pathlib.Path(here)/name;tree[p.relative_to(r/'vendor').as_posix()]=['link',os.readlink(p)] if p.is_symlink() else ['file',read(p)['sha256']]
drift=[]
for rel,want in doc['source_manifest']['files'].items():
 assert not pathlib.PurePosixPath(rel).is_absolute() and '..' not in pathlib.PurePosixPath(rel).parts and '.env' not in pathlib.PurePosixPath(rel).parts
 p=r/rel
 if not p.is_file() or read(p)['normalized_sha256']!=want:drift.append(rel)
print(json.dumps({'identity_match':True,'ip':x['ip'],'runtime_root':str(r),'state':state,'state_document':doc,'state_parent':{'dev':parent.st_dev,'ino':parent.st_ino,'uid':parent.st_uid,'gid':parent.st_gid,'mode':stat.S_IMODE(parent.st_mode)},'files':files,'vendor_fingerprint':tree,'source_drift':drift},sort_keys=True,separators=(',',':')))
'''.replace('PAYLOAD',encoded)
    result=subprocess.run(['ssh','-oBatchMode=yes','-oConnectTimeout=15','-oStrictHostKeyChecking=yes','root@'+payload['ip'],'python3','-'],input=remote.encode(),capture_output=True,timeout=180)
    if result.returncode:raise RuntimeError('snapshot_failed_exit_'+str(result.returncode)+':'+result.stderr.decode(errors='replace')[-200:])
    doc=json.loads(result.stdout);assert doc['identity_match'];target=pathlib.Path(output);assert not target.exists();target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(result.stdout)
    print(json.dumps({'snapshot':str(target),'sha256':hashlib.sha256(result.stdout).hexdigest(),'source_drift':doc['source_drift'],'vendor_entries':len(doc['vendor_fingerprint'])}))

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--old-scope',required=True);p.add_argument('--output',required=True);a=p.parse_args();capture(a.old_scope,a.output)
