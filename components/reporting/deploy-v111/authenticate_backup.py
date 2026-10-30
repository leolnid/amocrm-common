from pathlib import Path
import argparse, json, hashlib, base64, io, tarfile, subprocess, re
from cryptography.hazmat.primitives import serialization, hashes
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
BASE=Path(__file__).resolve().parents[5]
REF='96e68c142f9c37f28d62433b3aeac32f51f40278'
ZIP_SHA256='ce7718f21097d3f3d44a7cb8dcda5077ba2d86658cdc129e90790604a8223509'
ZIP_SHA1='66c46b4d2855d2122b07281082a7e2be190945da'
def sha(raw): return hashlib.sha256(raw).hexdigest()
def canon(v): return json.dumps(v,sort_keys=True,separators=(',',':'),ensure_ascii=True).encode()
def verify(scope_path,backup,report):
 s=json.loads(scope_path.read_text(encoding='utf-8')); p=s['profile']; c=s['component']; state=s['recorded_state']
 assert c['canonical_reference']==REF and c['package_metadata']['version']=='1.0.11'
 assert c['package_metadata']['dist']['shasum']==ZIP_SHA1
 assert not s.get('state_reconciliation') and not s.get('runtime_cache_paths')
 assert p.get('config_change_hashes',{})=={}
 assert set(s['source_paths'])=={'composer.json','composer.lock','app/Console/Commands/DeliverApplicationErrors.php'}
 raw=(backup/'backup.json').read_bytes(); meta=json.loads(raw)
 cipher=(backup/'rollback.aesgcm').read_bytes(); wrapped=(backup/'wrapped-key.bin').read_bytes()
 assert meta['artifact_sha256']==s['artifact_sha256'] and meta['state_preimage']==state
 assert sha(cipher)==meta['cipher_sha256'] and sha(wrapped)==meta['wrapped_key_sha256']
 keyraw=(Path.home()/'.ssh'/'id_rsa').read_bytes()
 try: private=serialization.load_ssh_private_key(keyraw,password=None)
 except ValueError: private=serialization.load_pem_private_key(keyraw,password=None)
 aes=private.decrypt(wrapped,padding.OAEP(mgf=padding.MGF1(hashes.SHA256()),algorithm=hashes.SHA256(),label=None)); assert len(aes)==32
 plain=AESGCM(aes).decrypt(base64.b64decode(meta['nonce'],validate=True),cipher,meta['aad'].encode())
 data={}
 with tarfile.open(fileobj=io.BytesIO(plain),mode='r:gz') as a:
  for item in a:
   assert item.isfile() and item.name not in data and not item.name.startswith('/') and '..' not in Path(item.name).parts
   data[item.name]=a.extractfile(item).read()
 members={k:sha(v) for k,v in data.items()}; assert members==meta['members']
 expected={'.env','bootstrap/cache/config.php'}
 for group in ('source_paths','vendor_metadata_paths'):
  for path,delta in s[group].items():
   assert delta['old'] is not None; expected.add(path); assert members[path]==delta['old']
 old={('vendor/'+k):v[1] for k,v in s['vendor_before'].items() if k.startswith('leolnid/common-reporting/') and v[0]=='file'}
 assert len(old)==36
 for path,h in old.items(): expected.add(path); assert members[path]==h
 matrix=json.loads((BASE/'project-matrix.json').read_text(encoding='utf-8-sig'))
 app=next(a for a in matrix['projects'] if a['domain']==p['server_names'][0])
 ip=re.search(r'\d+\.\d+\.\d+\.\d+',app['server']).group(0)
 root=state['runtime_root']
 program=("import hashlib,json,pathlib,os,ipaddress; conn=os.environ.get('SSH_CONNECTION','').split(); "
          "assert len(conn)==4 and ipaddress.ip_address(conn[2])==ipaddress.ip_address("+repr(ip)+"), 'ssh_target_ip_mismatch'; "
          "mid=pathlib.Path('/etc/machine-id').read_bytes(); ident=hashlib.sha256(mid).hexdigest(); assert ident=="+repr(p['server_identity_sha256'])+"=="+repr(state['server_identity_sha256'])+", 'server_identity_mismatch'; "
          "r=pathlib.Path("+repr(root)+"); v={'server_identity_sha256':ident,'env_cache':{k:hashlib.sha256((r/k).read_bytes()).hexdigest() for k in ['.env','bootstrap/cache/config.php']}}; print(json.dumps(v))")
 live=json.loads(subprocess.run(['ssh','-o','BatchMode=yes','-o','ConnectTimeout=15','root@'+ip,'python3','-'],input=program,text=True,capture_output=True,check=True,timeout=30).stdout)
 assert live['server_identity_sha256']==p['server_identity_sha256']==state['server_identity_sha256']
 for path,h in live['env_cache'].items(): assert members[path]==h
 if state['exists']:
  name='__state__/current.json'; expected.add(name); assert sha(data[name])==state['sha256']
  previous=json.loads(data[name]); assert previous['source_manifest']==p['live_manifest']
  assert previous['application_root']==state['application_root'] and previous['runtime_root']==state['runtime_root'] and previous['server_identity_sha256']==p['server_identity_sha256']
  state_hash=state['sha256']
 else:
  name='__state__/absence.json'; expected.add(name); marker={'schema_version':1,'state_absent':True,'preimage':state}
  assert data[name]==canon(marker); state_hash=sha(data[name])
  assert state['source_manifest_sha256']==sha(canon(p['live_manifest'])) and state['source_commit']==p['live_manifest']['candidate_commit']
 assert set(members)==expected
 assert meta['aad']=='COMMON-REPORTING-V103:'+p['project']+':'+s['artifact_sha256']+':'+state_hash
 assert meta['old_vendor_fingerprint_sha256']==sha(canon(s['vendor_before']))
 proof={'metadata_sha256':sha(raw),'GCM_local_verified':True,'members_sha256':sha(canon(members))}
 proof_path=report.with_suffix('.local-auth-proof.json'); proof_path.write_text(json.dumps(proof,indent=2)+'\n',encoding='utf-8')
 result={'status':'PASS','project':p['project'],'root_independent_RSA_GCM_auth':True,'members':len(members),'old_component_files_verified':36,'state_preimage':'present' if state['exists'] else 'absent','exact_scope_member_set':True,'env_cache_hashes_match_live_inventory':True,'server_identity_verified':True,'ssh_target_ip_verified':True,'plaintext_written':False,'metadata_sha256':sha(raw),'proof_sha256':sha(proof_path.read_bytes()),'proof_path':str(proof_path)}
 report.write_text(json.dumps(result,indent=2)+'\n',encoding='utf-8'); return result
if __name__=='__main__':
 a=argparse.ArgumentParser(); a.add_argument('scope',type=Path); a.add_argument('backup',type=Path); a.add_argument('report',type=Path); v=a.parse_args(); print(json.dumps(verify(v.scope,v.backup,v.report)))
