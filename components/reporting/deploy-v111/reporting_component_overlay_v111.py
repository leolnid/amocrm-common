#!/usr/bin/env python3
"""State-safe common-reporting 1.0.11 overlay with isolated reporting Telegram cache leaves."""
import pathlib,os,stat,json,hashlib,tarfile,zipfile,io,subprocess,secrets,time,datetime,fcntl,sys,importlib.util,types,base64,re,signal,copy,errno
COMPONENT='leolnid/common-reporting'
PREFIX='vendor/'+COMPONENT+'/'
META={'vendor/composer/autoload_classmap.php','vendor/composer/autoload_files.php','vendor/composer/autoload_psr4.php','vendor/composer/autoload_static.php','vendor/composer/installed.json','vendor/composer/installed.php'}
META2={'vendor/composer/installed.json','vendor/composer/installed.php'}
STATE_MEMBER='__state__/current.json'
STATE_RECONCILE_MEMBER='__state__/reconciliation.json'
STATE_ABSENCE_MEMBER='__state__/absence.json'
RELEASE_VERSION='1.0.11'
ALLOWED_PROJECTS={
 'amobriz','neftecamsk','almetevsk','bazacvetov-nignekamsk','courier','bp1','bp','contact-channel','cvray',
 'leads','documents','elena-repeat','partners','florist','flowwow-ms',
 'flower-secretary','frontpad','mc','paykeeper','poznanie','recipients','recurent-leads',
 'salesbots','segments','tilda'
}
APP_ROOTS={
 'amobriz':('/var/www/amobriz.amorenat.ru','/var/www/amobriz.amorenat.ru'),
 'neftecamsk':('/var/www/bazasvetov.amorenat.ru','/var/www/bazasvetov.amorenat.ru'),
 'almetevsk':('/var/www/bazacvetovalmetevsk.amorenat.ru','/var/www/bazacvetovalmetevsk.amorenat.ru'),
 'cvray':('/home/user/www/cvray.amorenat.ru','/home/user/www/cvray.amorenat.ru'),
 'leads':('/home/user/www/leads-automove.amorenat.ru','/home/user/www/leads-automove.amorenat.ru'),
 'documents':('/home/user/www/documents.amorenat.ru','/home/user/www/documents.amorenat.ru'),
 'elena-repeat':('/home/user/www/elena911prazdnikru.amorenat.ru','/home/user/www/elena911prazdnikru.amorenat.ru'),
 'poznanie':('/home/user/www/leads-from-poznanie-deti.amorenat.ru','/home/user/www/leads-from-poznanie-deti.amorenat.ru'),
 'bazacvetov-nignekamsk':('/var/www/bazacvetov-nignekamsk.amorenat.ru','/var/www/bazacvetov-nignekamsk.amorenat.ru-releases/6e34a74b78082e3b3e7e70f2f446d2c4f3496c16'),
 'bp1':('/var/www/bp1.amorenat.ru','/var/www/bp1-releases/compact-a0a578e0-20261025'),
 'bp':('/var/www/bp.amorenat.ru','/var/www/bp-releases/compact-50dea816-20261024'),
 'contact-channel':('/var/www/contact-channel.amorenat.ru','/var/www/contact-channel-releases/compact-1728a256-20261025'),
 'partners':('/var/www/partners.amorenat.ru','/var/www/partners-releases/compact-e13aeb96-20261024'),
 'florist':('/var/www/florist-releases/52de5789','/var/www/florist-releases/52de5789'),
 'flowwow-ms':('/var/www/flowwow-ms-releases/20261029-control-dd29317','/var/www/flowwow-ms-releases/20261029-control-dd29317'),
 'flower-secretary':('/var/www/flower-secretary-releases/4d73842bb06cf0a526dd28798c7c12bd5662a218','/var/www/flower-secretary-releases/4d73842bb06cf0a526dd28798c7c12bd5662a218'),
 'frontpad':('/var/www/frontpad.amorenat.ru','/var/www/frontpad.amorenat.ru'),
 'paykeeper':('/var/www/paykeeper.amorenat.ru','/var/www/paykeeper-releases/compact-da929a42-20261024'),
 'recipients':('/var/www/recipients.amorenat.ru','/var/www/recipients-releases/compact-902489bd-20261024'),
 'recurent-leads':('/var/www/recurent-leads.amorenat.ru','/var/www/recurent-leads-releases/compact-bb753448-20261024'),
 'salesbots':('/var/www/salesbots-launcher.amorenat.ru','/var/www/salesbots-releases/compact-7173202b-20261024'),
 'segments':('/var/www/segments.amorenat.ru','/var/www/segments-releases/compact-66560184-20261024'),
 'tilda':('/var/www/tilda-releases/20261024-php85','/var/www/tilda-releases/20261024-php85'),
 'courier':('/var/www/bazasvetov-courier.amorenat.ru','/var/www/bazasvetov-courier.amorenat.ru'),
 'mc':('/var/www/mc.amorenat.ru','/var/www/mc.amorenat.ru'),
}
PRIVATE_PROTOTYPE='0cb0796378dfaf1c70474ce620451b2ade9d0d2e'
DIST_ZIP='_dist/common-reporting.zip'
DIST_MANIFEST='_dist/release-manifest.json'
class Refuse(RuntimeError):pass
def cache_identity(path):
 info=path.stat();return (info.st_dev,info.st_ino,info.st_mode,info.st_uid,info.st_gid,info.st_size,info.st_mtime_ns)
def safe_refusal_code(exc):
 if (isinstance(exc,Refuse)or(type(exc).__name__=='Refuse'and type(exc).__module__=='reviewed_component_controller'))and re.fullmatch('[a-z][a-z0-9_]{0,99}',str(exc)):return str(exc)
 return 'component_overlay_failed'
def sha(raw):return hashlib.sha256(raw).hexdigest()
def need(ok,code):
 if not ok:raise Refuse(code)
def rel(name):
 need(isinstance(name,str)and name and not name.startswith('/')and'\\'not in name and'..'not in pathlib.PurePosixPath(name).parts and'\0'not in name and not({'.env','auth.json','.git'}&set(pathlib.PurePosixPath(name).parts)),'unsafe_relative_path');return name
def expected_config_changes(project):
 return {}

def validate_config_change_policy(project,source_paths,config_changes):
 expected_files={'composer.json','composer.lock'}|({'app/Console/Commands/DeliverApplicationErrors.php'} if project=='courier' else set())
 need(project in ALLOWED_PROJECTS and set(source_paths)==expected_files,'source_path_not_exact_v111_scope')
 need(config_changes=={},'configuration_changes_forbidden_v111_scope')

def validate_source_paths(project,source_paths,live_files=None,candidate_files=None):
 validate_config_change_policy(project,source_paths,expected_config_changes(project))
 if live_files is not None and candidate_files is not None:
  changed={k for k in set(live_files)|set(candidate_files) if live_files.get(k)!=candidate_files.get(k)}
  need(changed==set(source_paths),'full_source_delta_not_exact')
def wrap_core_validate_profile(core_validator):
 def validate(profile):
  project=profile.get('project') if isinstance(profile,dict) else None
  live=profile.get('live_manifest',{});candidate=profile.get('candidate_manifest',{})
  changed={k for k in set(live.get('files',{}))|set(candidate.get('files',{})) if live.get('files',{}).get(k)!=candidate.get('files',{}).get(k)}
  changes=profile.get('config_change_hashes',{})
  validate_config_change_policy(project,changed,changes)
  clean=copy.deepcopy(profile)
  clean.pop('config_change_hashes',None)
  core_validator(clean)
  return profile
 return validate

def fingerprint(root):
 result={};root=pathlib.Path(root)
 if not root.exists():return result
 for here,dirs,files in os.walk(root,followlinks=False):
  for name in dirs+files:
   p=pathlib.Path(here)/name;key=p.relative_to(root).as_posix()
   if p.is_symlink():result[key]=['link',os.readlink(p)]
   elif p.is_file():result[key]=['file',sha(p.read_bytes())]
 return result
def canonical(value):return sha(json.dumps(value,sort_keys=True,separators=(',',':'),ensure_ascii=True).encode())

def state_manifest_digest(manifest):
 need(isinstance(manifest,dict) and isinstance(manifest.get('candidate_commit'),str) and re.fullmatch(r'[0-9a-f]{40,64}',manifest['candidate_commit']) and isinstance(manifest.get('files'),dict),'recorded_state_manifest_invalid')
 return canonical(manifest)

def state_snapshot(path):
 path=pathlib.Path(path)
 need(not path.is_symlink() and path.is_file(),'recorded_state_file_untrusted');fd=os.open(path,os.O_RDONLY|getattr(os,'O_NOFOLLOW',0)|getattr(os,'O_CLOEXEC',0))
 try:
  info=os.fstat(fd);chunks=[]
  while True:
   part=os.read(fd,65536)
   if not part:break
   chunks.append(part)
  raw=b''.join(chunks);after=os.fstat(fd);entry=path.lstat()
 finally:os.close(fd)
 need(stat.S_ISREG(info.st_mode) and info.st_nlink==1 and not stat.S_ISLNK(entry.st_mode) and (entry.st_dev,entry.st_ino)==(info.st_dev,info.st_ino),'recorded_state_file_untrusted')
 need(info.st_uid==0 and stat.S_IMODE(info.st_mode)==0o600 and info.st_gid==0,'recorded_state_file_metadata_untrusted')
 need((info.st_dev,info.st_ino,info.st_size,info.st_mtime_ns,info.st_uid,info.st_gid,stat.S_IMODE(info.st_mode))==(after.st_dev,after.st_ino,after.st_size,after.st_mtime_ns,after.st_uid,after.st_gid,stat.S_IMODE(after.st_mode)) and len(raw)==info.st_size,'recorded_state_changed_during_read')
 return raw,info

def directory_identity(info):
 return {'dev':info.st_dev,'ino':info.st_ino,'uid':info.st_uid,'gid':info.st_gid,'mode':stat.S_IMODE(info.st_mode)}

def validate_absent_state(path,declared):
 path=pathlib.Path(path);need(not path.exists() and not path.is_symlink(),'recorded_state_absence_drift')
 need(isinstance(declared,dict) and declared.get('exists') is False and declared.get('path')==str(path),'recorded_state_absence_scope_invalid')
 ancestor=declared.get('nearest_existing_ancestor');missing=declared.get('missing_directories')
 need(isinstance(ancestor,dict) and set(ancestor)=={'path','dev','ino','uid','gid','mode'} and isinstance(missing,list),'recorded_state_absence_scope_invalid')
 ancestor_path=pathlib.Path(ancestor['path']);need(ancestor_path.is_absolute() and ancestor_path.exists() and not ancestor_path.is_symlink() and ancestor_path.is_dir(),'recorded_state_ancestor_untrusted')
 ancestor_info=ancestor_path.stat();need(directory_identity(ancestor_info)=={k:ancestor[k] for k in ('dev','ino','uid','gid','mode')} and ancestor_info.st_uid==0 and not(stat.S_IMODE(ancestor_info.st_mode)&0o022),'recorded_state_ancestor_drift')
 expected=[];cursor=ancestor_path
 for raw in missing:
  need(isinstance(raw,str),'recorded_state_missing_chain_invalid');item=pathlib.Path(raw);need(item.is_absolute() and item.parent==cursor and not item.exists() and not item.is_symlink(),'recorded_state_missing_chain_drift');expected.append(item);cursor=item
 need(cursor==path.parent,'recorded_state_missing_chain_invalid')
 return {'path':path,'ancestor_path':ancestor_path,'ancestor_info':ancestor_info,'missing_directories':expected}

def bind_recorded_manifest(controller,scope,validate_profile_fn):
 declared=scope.get('recorded_state');need(isinstance(declared,dict),'recorded_state_preimage_required');path=controller.state_path();controller.trusted_state_directory();need(declared.get('path')==str(path),'recorded_state_path_mismatch');need(controller.p['project'] in APP_ROOTS and (str(controller.entry),str(controller.root))==APP_ROOTS[controller.p['project']],'application_or_runtime_root_not_in_finite_scope')
 live=controller.p['live_manifest'];need(declared.get('application_root')==str(controller.entry) and declared.get('runtime_root')==str(controller.root) and declared.get('server_identity_sha256')==controller.p['server_identity_sha256'],'recorded_state_identity_drift')
 if declared.get('exists') is False:
  absent_fields={'exists','path','nearest_existing_ancestor','missing_directories','application_root','runtime_root','server_identity_sha256','source_manifest_sha256','source_commit'};need(set(declared)==absent_fields,'recorded_state_scope_shape_invalid');need(scope.get('state_reconciliation') is None and declared.get('source_manifest_sha256')==state_manifest_digest(live) and declared.get('source_commit')==live.get('candidate_commit'),'reconcile_absent_recorded_state_forbidden');absence=validate_absent_state(path,declared);controller._component_state_absent=True;controller._component_state_was_absent=True;controller._component_state_declared=declared;controller._component_state_absence=absence;controller._component_state_created_directories=[];controller._component_state_preimage=absent_state_marker(declared);controller._component_state_info=None;controller._component_state_parent_info=absence['ancestor_info'];controller._component_state_sha=sha(controller._component_state_preimage)
 else:
  present_fields={'exists','path','sha256','dev','ino','size','mtime_ns','uid','gid','mode','parent_dev','parent_ino','parent_uid','parent_gid','parent_mode','application_root','runtime_root','server_identity_sha256','source_manifest_sha256','source_commit'};need(declared.get('exists') is True and set(declared)==present_fields,'recorded_state_scope_shape_invalid');raw,info=state_snapshot(path);parent=path.parent.stat();need(declared['sha256']==sha(raw) and declared['dev']==info.st_dev and declared['ino']==info.st_ino and declared['size']==info.st_size and declared['mtime_ns']==info.st_mtime_ns and declared['uid']==info.st_uid and declared['gid']==info.st_gid and declared['mode']==stat.S_IMODE(info.st_mode) and declared['parent_dev']==parent.st_dev and declared['parent_ino']==parent.st_ino and declared['parent_uid']==parent.st_uid and declared['parent_gid']==parent.st_gid and declared['parent_mode']==stat.S_IMODE(parent.st_mode),'recorded_state_preimage_drift');state=json.loads(raw);recorded=state.get('source_manifest');need(state.get('application_root')==str(controller.entry) and state.get('runtime_root')==str(controller.root) and state.get('server_identity_sha256')==controller.p['server_identity_sha256'],'recorded_state_identity_drift');need(state_manifest_digest(recorded)==declared['source_manifest_sha256'] and recorded.get('candidate_commit')==declared['source_commit'],'recorded_state_manifest_binding_drift');validate_state_reconciliation(recorded,live,sha(raw),declared['source_manifest_sha256'],scope.get('state_reconciliation'));controller._component_state_absent=False;controller._component_state_was_absent=False;controller._component_state_declared=declared;controller._component_state_preimage=raw;controller._component_state_info=info;controller._component_state_parent_info=parent;controller._component_state_sha=sha(raw);controller._component_state_created_directories=[]
 # Keep the scoped manifest from the independently verified profile; never adopt it from the state file.
 validate_profile_fn(controller.p)

def absent_state_marker(declared):
 return json.dumps({'schema_version':1,'state_absent':True,'preimage':declared},sort_keys=True,separators=(',',':')).encode()

def fsync_parent(path):
 fd=os.open(pathlib.Path(path).parent,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
 try:os.fsync(fd)
 finally:os.close(fd)

def candidate_state_bytes(controller):
 return json.dumps({'schema_version':1,'application_root':str(controller.entry),'runtime_root':str(controller.root),'server_identity_sha256':controller.p['server_identity_sha256'],'source_manifest':controller.p['candidate_manifest'],'receipt':controller.report},indent=2).encode()

def persist_absent_candidate_state(controller,payload):
 path=controller.state_path();declared=controller._component_state_declared;check=validate_absent_state(path,declared);ancestor=check['ancestor_path'];ancestor_info=check['ancestor_info'];cursor=ancestor;expected_cursor=directory_identity(ancestor_info)
 for directory in check['missing_directories']:
  now=cursor.stat();need(directory_identity(now)==expected_cursor and directory.parent==cursor,'recorded_state_parent_drift')
  os.mkdir(directory,0o700);controller.state_write_started=True;info=directory.stat();created_identity=directory_identity(info)
  controller._component_state_created_directories.append((str(directory),created_identity,directory_identity(now)))
  need(not directory.is_symlink() and directory.is_dir() and info.st_uid==0 and info.st_gid==0 and stat.S_IMODE(info.st_mode)==0o700,'created_state_directory_metadata_invalid')
  fsync_parent(directory);cursor=directory;expected_cursor=created_identity
 controller.trusted_state_directory();need(not path.exists() and not path.is_symlink(),'recorded_state_absence_drift')
 tmp=path.with_name('.current-v108-'+secrets.token_hex(8));owned_tmp=False;fd=os.open(tmp,os.O_CREAT|os.O_EXCL|os.O_WRONLY|os.O_NOFOLLOW,0o600);owned_tmp=True
 try:
  os.fchown(fd,0,0);os.fchmod(fd,0o600)
  with os.fdopen(fd,'wb')as stream:stream.write(payload);stream.flush();os.fsync(stream.fileno())
  temp_info=tmp.stat();controller._component_state_written_sha=sha(payload);controller._component_state_written_identity=(temp_info.st_dev,temp_info.st_ino,temp_info.st_size,temp_info.st_mtime_ns,temp_info.st_uid,temp_info.st_gid,stat.S_IMODE(temp_info.st_mode));controller.state_write_started=True
  need(not path.exists() and not path.is_symlink(),'recorded_state_absence_drift');os.replace(tmp,path);fsync_parent(path)
  raw,info=state_snapshot(path);need(raw==payload and (info.st_dev,info.st_ino,info.st_size,info.st_mtime_ns,info.st_uid,info.st_gid,stat.S_IMODE(info.st_mode))==controller._component_state_written_identity,'recorded_state_postwrite_mismatch')
 finally:
  if owned_tmp and tmp.exists() and not tmp.is_symlink():tmp.unlink()
 controller._component_state_absent=False;controller._component_state_info=info;controller._component_state_sha=sha(raw);controller._component_state_parent_info=path.parent.stat()

def restore_absent_state(controller,expected_written_sha):
 path=controller.state_path();created=list(controller._component_state_created_directories)
 if path.exists() or path.is_symlink():
  need(not path.is_symlink() and path.is_file() and path.stat().st_nlink==1,'rollback_state_file_untrusted');raw,info=state_snapshot(path);identity=(info.st_dev,info.st_ino,info.st_size,info.st_mtime_ns,info.st_uid,info.st_gid,stat.S_IMODE(info.st_mode));need(sha(raw)==expected_written_sha and identity==controller._component_state_written_identity,'rollback_external_state_drift');path.unlink();fsync_parent(path)
 for raw_path,created_identity,parent_identity in reversed(created):
  directory=pathlib.Path(raw_path);need(not directory.is_symlink() and directory.is_dir() and directory_identity(directory.stat())==created_identity and directory_identity(directory.parent.stat())==parent_identity and not any(directory.iterdir()),'rollback_created_state_directory_drift');directory.rmdir();fsync_parent(directory)
 validate_absent_state(path,controller._component_state_declared);controller._component_state_absent=True;controller._component_state_info=None;controller._component_state_sha=sha(absent_state_marker(controller._component_state_declared));controller.state_write_started=False;controller._component_state_created_directories=[]

def validate_state_reconciliation(recorded_manifest,live_manifest,preimage_sha256,recorded_manifest_sha256,reconcile):
 live_digest=state_manifest_digest(live_manifest)
 if recorded_manifest==live_manifest:
  need(reconcile is None,'unneeded_state_reconcile_forbidden');return False
 need(isinstance(reconcile,dict) and set(reconcile)=={'preimage_sha256','recorded_manifest_sha256','accepted_live_manifest_sha256','review_receipt_sha256','disposition'} and reconcile['preimage_sha256']==preimage_sha256 and reconcile['recorded_manifest_sha256']==recorded_manifest_sha256 and reconcile['accepted_live_manifest_sha256']==live_digest and isinstance(reconcile['review_receipt_sha256'],str) and re.fullmatch(r'[0-9a-f]{64}',reconcile['review_receipt_sha256']) and reconcile['disposition']=='reviewed_stale_recorded_state_reconcile','explicit_state_reconcile_required')
 return True

def persist_candidate_state(controller):
 path=controller.state_path();controller.trusted_state_directory();raw,info=state_snapshot(path);parent=path.parent.stat();old=controller._component_state_info;old_parent=controller._component_state_parent_info
 need(sha(raw)==controller._component_state_sha and (info.st_dev,info.st_ino,info.st_size,info.st_mtime_ns,info.st_uid,info.st_gid,stat.S_IMODE(info.st_mode))==(old.st_dev,old.st_ino,old.st_size,old.st_mtime_ns,old.st_uid,old.st_gid,stat.S_IMODE(old.st_mode)) and (parent.st_dev,parent.st_ino,parent.st_uid,parent.st_gid,stat.S_IMODE(parent.st_mode))==(old_parent.st_dev,old_parent.st_ino,old_parent.st_uid,old_parent.st_gid,stat.S_IMODE(old_parent.st_mode)),'recorded_state_cas_drift')
 payload=json.dumps({'schema_version':1,'application_root':str(controller.entry),'runtime_root':str(controller.root),'server_identity_sha256':controller.p['server_identity_sha256'],'source_manifest':controller.p['candidate_manifest'],'receipt':controller.report},indent=2).encode();tmp=path.with_name('.current-v108-'+secrets.token_hex(8));owned_tmp=False;fd=os.open(tmp,os.O_CREAT|os.O_EXCL|os.O_WRONLY|os.O_NOFOLLOW,0o600);owned_tmp=True
 try:
  os.fchown(fd,old.st_uid,old.st_gid);os.fchmod(fd,stat.S_IMODE(old.st_mode))
  with os.fdopen(fd,'wb') as stream:stream.write(payload);stream.flush();os.fsync(stream.fileno())
  now_parent=path.parent.stat();need((now_parent.st_dev,now_parent.st_ino,now_parent.st_uid,now_parent.st_gid,stat.S_IMODE(now_parent.st_mode))==(old_parent.st_dev,old_parent.st_ino,old_parent.st_uid,old_parent.st_gid,stat.S_IMODE(old_parent.st_mode)) and not path.is_symlink(),'recorded_state_parent_drift')
  controller._component_state_written_sha=sha(payload);controller.state_write_started=True;os.replace(tmp,path)
  dirfd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
  try:os.fsync(dirfd)
  finally:os.close(dirfd)
  written_raw,written=state_snapshot(path);written_parent=path.parent.stat()
  need(written_raw==payload and written.st_uid==old.st_uid and written.st_gid==old.st_gid and stat.S_IMODE(written.st_mode)==stat.S_IMODE(old.st_mode) and (written_parent.st_dev,written_parent.st_ino,written_parent.st_uid,written_parent.st_gid,stat.S_IMODE(written_parent.st_mode))==(old_parent.st_dev,old_parent.st_ino,old_parent.st_uid,old_parent.st_gid,stat.S_IMODE(old_parent.st_mode)),'recorded_state_postwrite_mismatch')
  controller._component_state_written_identity=(written.st_dev,written.st_ino,written.st_size,written.st_mtime_ns,written.st_uid,written.st_gid,stat.S_IMODE(written.st_mode))
 finally:
  if owned_tmp and tmp.exists() and not tmp.is_symlink():tmp.unlink()

def restore_state_file(path,raw,info,expected_written_sha,atomic,expected_identity=None,expected_parent=None):
 path=pathlib.Path(path);need(not path.is_symlink() and path.is_file() and path.stat().st_nlink==1,'rollback_state_file_untrusted');current=path.read_bytes();fresh=path.stat();current_identity=(fresh.st_dev,fresh.st_ino,fresh.st_size,fresh.st_mtime_ns,fresh.st_uid,fresh.st_gid,stat.S_IMODE(fresh.st_mode));parent=path.parent.stat();parent_identity=(parent.st_dev,parent.st_ino,parent.st_uid,parent.st_gid,stat.S_IMODE(parent.st_mode))
 need(expected_parent is None or parent_identity==expected_parent,'rollback_state_parent_drift')
 old_exact=sha(current)==sha(raw) and current_identity==(info.st_dev,info.st_ino,info.st_size,info.st_mtime_ns,info.st_uid,info.st_gid,stat.S_IMODE(info.st_mode))
 written_exact=sha(current)==expected_written_sha and (expected_identity is None or current_identity==expected_identity) and fresh.st_uid==info.st_uid and fresh.st_gid==info.st_gid and stat.S_IMODE(fresh.st_mode)==stat.S_IMODE(info.st_mode)
 need(old_exact or written_exact,'rollback_external_state_drift')
 atomic(path,raw,info);os.utime(path,ns=(info.st_atime_ns,info.st_mtime_ns));dirfd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
 try:os.fsync(dirfd)
 finally:os.close(dirfd)
 fresh=path.stat();restored_parent=path.parent.stat();need(path.read_bytes()==raw and fresh.st_uid==info.st_uid and fresh.st_gid==info.st_gid and stat.S_IMODE(fresh.st_mode)==stat.S_IMODE(info.st_mode) and fresh.st_mtime_ns==info.st_mtime_ns and (expected_parent is None or (restored_parent.st_dev,restored_parent.st_ino,restored_parent.st_uid,restored_parent.st_gid,stat.S_IMODE(restored_parent.st_mode))==expected_parent),'rollback_state_restore_mismatch')
MISSING={'__reporting_missing_value__':True}
def wait_safe_window():
 while not 10<=datetime.datetime.now(datetime.timezone.utc).second<=35:time.sleep(.25)
def config_equal(old,new,allowed,path=''):
 if path in allowed:return canonical(old)==allowed[path]['old']and canonical(new)==allowed[path]['new']
 if type(old)is not type(new):return False
 if isinstance(old,dict):return all(config_equal(old.get(k,MISSING),new.get(k,MISSING),allowed,path+'.'+str(k)if path else str(k))for k in old.keys()|new.keys())
 if isinstance(old,list):return len(old)==len(new)and all(config_equal(a,b,allowed,path+'[]')for a,b in zip(old,new))
 return old==new
def validate_dependency_delta(old,new,oldlock,newlock,component):
 meta=component['package_metadata'];need(meta['name']==COMPONENT and meta['version']==RELEASE_VERSION and re.fullmatch(r'(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?',meta['version'])is not None and meta['type']=='library','component_identity_mismatch')
 need(set(meta)=={'name','version','type','require','dist','autoload'}and meta['require']=={'php':'^8.5','laravel/framework':'^12.0 || ^13.0'}and meta['autoload']=={'psr-4':{'Leolnid\\Common\\Reporting\\':'src/Reporting/'}},'component_metadata_or_autoprovider_forbidden')
 ref=component['canonical_reference'];repo=component['canonical_url'].removesuffix('.git');tag='reporting-v'+meta['version']+'-'+ref;filename='common-reporting-'+meta['version']+'-'+ref+'.zip';need(set(meta['dist'])=={'type','url','reference','shasum'}and meta['dist']['type']=='zip'and meta['dist']['reference']==ref and meta['dist']['url']==repo+'/releases/download/'+tag+'/'+filename and re.fullmatch('[a-f0-9]{40}',meta['dist']['shasum'])is not None,'component_dist_pin_mismatch')
 need(component['canonical_url']in['https://github.com/leolnid/amocrm-common.git','https://github.com/leolnid/amocrm-common'],'component_repository_not_canonical')
 def without(c):
  c=json.loads(json.dumps(c));c.get('require',{}).pop(COMPONENT,None);repos=c.get('repositories',{})
  if isinstance(repos,dict):c['repositories']={k:v for k,v in repos.items()if not(isinstance(v,dict)and v.get('type')=='package'and isinstance(v.get('package'),dict)and v['package'].get('name')==COMPONENT)}
  else:c['repositories']=[v for v in repos if not(isinstance(v,dict)and v.get('type')=='package'and isinstance(v.get('package'),dict)and v['package'].get('name')==COMPONENT)]
  if not c['repositories']:c.pop('repositories')
  return c
 need(config_equal(without(old),without(new),{})and new['require'][COMPONENT]==meta['version'],'unrelated_root_dependency_delta')
 repos=new.get('repositories',{});repos=list(repos.values())if isinstance(repos,dict)else repos;found=[v['package']for v in repos if isinstance(v,dict)and v.get('type')=='package'and isinstance(v.get('package'),dict)and v['package'].get('name')==COMPONENT];need(found==[meta],'inline_component_metadata_mismatch')
 def locked(c):return {k:([x for x in v if x['name']!=COMPONENT]if k=='packages'else v)for k,v in c.items()if k!='content-hash'}
 need(config_equal(locked(oldlock),locked(newlock),{}),'old_dependency_or_sdk_lock_changed');entry=[x for x in newlock['packages']if x['name']==COMPONENT];need(entry==[meta],'component_lock_metadata_mismatch')
def validate_distribution(sources,component):
 raw=sources[DIST_ZIP];outer_raw=sources[DIST_MANIFEST];need(sha(outer_raw)==component['release_manifest_sha256'],'release_manifest_digest_mismatch');outer=json.loads(outer_raw);meta=component['package_metadata'];ref=component['canonical_reference'];repo=component['canonical_url'].removesuffix('.git');tag='reporting-v'+meta['version']+'-'+ref
 need(outer['schema_version']==1 and outer['package']==COMPONENT and outer['version']==meta['version']and outer['source_commit']==ref and outer['source_repository']==repo and outer['release_tag']==tag and outer['package_metadata']==meta,'distribution_release_identity_mismatch')
 need(len(raw)==outer['zip']['bytes']and sha(raw)==outer['zip']['sha256']and hashlib.sha1(raw).hexdigest()==outer['zip']['sha1']==meta['dist']['shasum']and outer['zip']['url']==meta['dist']['url']and outer['members']==component['files'],'distribution_archive_digest_mismatch')
 expanded={}
 with zipfile.ZipFile(io.BytesIO(raw))as archive:
  items=archive.infolist();need(len(items)==len(component['files'])and{x.filename for x in items}==set(component['files']),'distribution_members_not_exact')
  for item in items:
   rel(item.filename);need(not item.is_dir()and stat.S_IFMT(item.external_attr>>16)==stat.S_IFREG and 0<item.file_size<=4194304,'unsafe_distribution_entry');data=archive.read(item);need(sha(data)==component['files'][item.filename]and data==sources[PREFIX+item.filename],'expanded_distribution_member_mismatch');expanded[item.filename]=data
 inner=json.loads(expanded['manifest.json']);need(inner=={'schema_version':1,'package':COMPONENT,'version':meta['version'],'source_repository':repo,'source_commit':ref,'release_tag':tag,'files':{k:sha(v)for k,v in expanded.items()if k!='manifest.json'}},'inner_distribution_manifest_mismatch')
def validate_services_delta(old,new,added):
 need(added==['App\\Providers\\BusinessTelemetryServiceProvider'],'unsupported_services_provider_delta')
 need(set(old)==set(new)and old.get('deferred')==new.get('deferred')and old.get('when')==new.get('when'),'unrelated_services_cache_delta')
 for key in ['providers','eager']:need([x for x in new[key]if x not in added]==old[key]and all(new[key].count(x)==1 for x in added),'services_provider_order_or_count_changed')
def php_literal(controller,raw):
 code=r"""$s=stream_get_contents(STDIN);foreach(token_get_all($s)as$t){if(is_array($t)){if(in_array($t[0],[T_OPEN_TAG,T_RETURN,T_ARRAY,T_CONSTANT_ENCAPSED_STRING,T_LNUMBER,T_DOUBLE_ARROW,T_WHITESPACE],true))continue;if($t[0]===T_STRING&&in_array(strtolower($t[1]),['true','false','null'],true))continue;exit(54);}if(!str_contains('()[],;',$t))exit(55);}echo json_encode(eval(substr($s,5)),JSON_THROW_ON_ERROR);"""
 return json.loads(controller.run(['unshare','--net',controller.p['php_cli'],'-r',code],input=raw))
def native_config_proof(controller,old_path,new_path,allowed):
 code=r'''$old=require $argv[1];$new=require $argv[2];$allowed=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$missing=new stdClass;
function tagged($v,$missing){if($v===$missing)return ['type'=>'missing'];$type=get_debug_type($v);if(is_array($v)){$rows=[];foreach($v as$k=>$x)$rows[]=[['type'=>get_debug_type($k),'value'=>$k],tagged($x,$missing)];return ['type'=>'array','list'=>array_is_list($v),'entries'=>$rows];}if(is_object($v)||is_resource($v))throw new RuntimeException('Unsupported cached configuration type');return ['type'=>$type,'value'=>$v];}
function valueHash($v,$missing){return hash('sha256',json_encode(tagged($v,$missing),JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));}
function same($a,$b,$path,$allowed,$missing){if(isset($allowed[$path]))return valueHash($a,$missing)===$allowed[$path]['old']&&valueHash($b,$missing)===$allowed[$path]['new'];if($a===$missing||$b===$missing)return $a===$b;if(get_debug_type($a)!==get_debug_type($b))return false;if(is_array($a)){if(array_is_list($a)!==array_is_list($b))return false;if(array_is_list($a)&&array_keys($a)!==array_keys($b))return false;foreach(array_unique(array_merge(array_keys($a),array_keys($b)))as$k){$p=$path===''?(string)$k:$path.'.'.$k;if(!same(array_key_exists($k,$a)?$a[$k]:$missing,array_key_exists($k,$b)?$b[$k]:$missing,$p,$allowed,$missing))return false;}return true;}return $a===$b;}
echo json_encode(['native_types_equal'=>same($old,$new,'',$allowed,$missing)],JSON_THROW_ON_ERROR);'''
 return json.loads(controller.run(['unshare','--net',controller.p['php_cli'],'-r',code,str(old_path),str(new_path)],input=json.dumps(allowed).encode()))['native_types_equal']
def merge_native_config_nodes(controller,old_path,fresh_path,allowed):
 need(bool(allowed),'reviewed_config_nodes_required')
 code=r"""$old=require $argv[1];$fresh=require $argv[2];$allowed=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$missing=new stdClass;
function tagged($v,$missing){if($v===$missing)return ['type'=>'missing'];$type=get_debug_type($v);if(is_array($v)){$rows=[];foreach($v as$k=>$x)$rows[]=[['type'=>get_debug_type($k),'value'=>$k],tagged($x,$missing)];return ['type'=>'array','list'=>array_is_list($v),'entries'=>$rows];}if(is_object($v)||is_resource($v))throw new RuntimeException('Unsupported cached configuration type');return ['type'=>$type,'value'=>$v];}
function valueHash($v,$missing){return hash('sha256',json_encode(tagged($v,$missing),JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));}
function at($c,$path,$missing){foreach(explode('.',$path)as$k){if(!is_array($c)||!array_key_exists($k,$c))return $missing;$c=$c[$k];}return $c;}
$merged=$old;foreach($allowed as$path=>$hashes){if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)+$/D',$path))exit(71);$before=at($old,$path,$missing);$after=at($fresh,$path,$missing);if($after===$missing||valueHash($before,$missing)!==$hashes['old']||valueHash($after,$missing)!==$hashes['new'])exit(72);$keys=explode('.',$path);$leaf=array_pop($keys);$target=&$merged;foreach($keys as$k){if(!array_key_exists($k,$target))$target[$k]=[];if(!is_array($target[$k]))exit(73);$target=&$target[$k];}$target[$leaf]=$after;unset($target);}
$raw="<?php\nreturn ".var_export($merged,true).";\n";if(file_put_contents($argv[2],$raw,LOCK_EX)!==strlen($raw))exit(74);echo json_encode(['approved_native_nodes_applied'=>count($allowed),'unrelated_old_configuration_preserved'=>true],JSON_THROW_ON_ERROR);"""
 return json.loads(controller.run(['unshare','--net',controller.p['php_cli'],'-r',code,str(old_path),str(fresh_path)],input=json.dumps(allowed).encode()))


def native_missing_null_delta_proof(controller, old_path, new_path, allowed):
 project=controller.p.get('project')
 need(project in ALLOWED_PROJECTS and project!='courier' and allowed==expected_config_changes(project),'native_config_proof_scope_not_exact')
 code=r'''$old=require $argv[1];$new=require $argv[2];$allowed=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$missing=new stdClass;
function tagged($v,$missing){if($v===$missing)return ['type'=>'missing'];$type=get_debug_type($v);if(is_array($v)){$rows=[];foreach($v as$k=>$x)$rows[]=[['type'=>get_debug_type($k),'value'=>$k],tagged($x,$missing)];return ['type'=>'array','list'=>array_is_list($v),'entries'=>$rows];}if(is_object($v)||is_resource($v))throw new RuntimeException('Unsupported cached configuration type');return ['type'=>$type,'value'=>$v];}
function valueHash($v,$missing){return hash('sha256',json_encode(tagged($v,$missing),JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));}
function hasAllowedBelow($path,$allowed){foreach(array_keys($allowed)as$key)if(str_starts_with($key,$path.'.'))return true;return false;}
function sameNative($a,$b,$path,$allowed,$missing){
 if(array_key_exists($path,$allowed))return valueHash($a,$missing)===$allowed[$path]['old']&&valueHash($b,$missing)===$allowed[$path]['new'];
 if($a===$missing){if($b===$missing)return true;if(!is_array($b)||!hasAllowedBelow($path,$allowed))return false;foreach($b as$k=>$v){$child=$path===''?(string)$k:$path.'.'.$k;if(!sameNative($missing,$v,$child,$allowed,$missing))return false;}return true;}
 if($b===$missing)return false;
 if(get_debug_type($a)!==get_debug_type($b))return false;
 if(is_array($a)){if(array_is_list($a)!==array_is_list($b))return false;$oldKeys=array_keys($a);$newKeys=array_keys($b);if($oldKeys!==array_values(array_filter($newKeys,static fn($k)=>array_key_exists($k,$a))))return false;foreach(array_unique(array_merge($oldKeys,$newKeys))as$k){$child=$path===''?(string)$k:$path.'.'.$k;if(!sameNative(array_key_exists($k,$a)?$a[$k]:$missing,array_key_exists($k,$b)?$b[$k]:$missing,$child,$allowed,$missing))return false;}return true;}
 return $a===$b;
}
echo json_encode(['native_delta_exact'=>sameNative($old,$new,'',$allowed,$missing)],JSON_THROW_ON_ERROR);'''
 return json.loads(controller.run(['unshare','--net',controller.p['php_cli'],'-r',code,str(old_path),str(new_path)],input=json.dumps(allowed).encode()))['native_delta_exact']
def merge_native_cache_missing_nulls(controller, old_path, temp_path, allowed):
 project=controller.p.get('project')
 expected=expected_config_changes(project)
 need(project in ALLOWED_PROJECTS and project!='courier' and allowed==expected and set(allowed)=={'app_errors.telegram.base_uri','app_errors.telegram.relay_token'},'native_cache_scope_not_exact')
 need(not pathlib.Path(temp_path).exists() and not pathlib.Path(temp_path).is_symlink(),'private_cache_target_preexists')
 code=r'''$old=require $argv[1];$allowed=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$missing=new stdClass;
function tagged($v,$missing){if($v===$missing)return ['type'=>'missing'];$type=get_debug_type($v);if(is_array($v)){$rows=[];foreach($v as$k=>$x)$rows[]=[['type'=>get_debug_type($k),'value'=>$k],tagged($x,$missing)];return ['type'=>'array','list'=>array_is_list($v),'entries'=>$rows];}if(is_object($v)||is_resource($v))throw new RuntimeException('Unsupported cached configuration type');return ['type'=>$type,'value'=>$v];}
function valueHash($v,$missing){return hash('sha256',json_encode(tagged($v,$missing),JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));}
function at($c,$path,$missing){foreach(explode('.',$path)as$k){if(!is_array($c)||!array_key_exists($k,$c))return $missing;$c=$c[$k];}return $c;}
foreach($allowed as$path=>$hashes){if(at($old,$path,$missing)!==$missing||valueHash($missing,$missing)!==$hashes['old']||valueHash(null,$missing)!==$hashes['new'])exit(71);}
$merged=$old;foreach($allowed as$path=>$hashes){$keys=explode('.',$path);$leaf=array_pop($keys);$target=&$merged;foreach($keys as$k){if(!array_key_exists($k,$target))$target[$k]=[];if(!is_array($target[$k]))exit(72);$target=&$target[$k];}if(array_key_exists($leaf,$target))exit(73);$target[$leaf]=null;unset($target);}
$raw="<?php\nreturn ".var_export($merged,true).";\n";$f=fopen($argv[2],'x');if($f===false)exit(74);$n=0;while($n<strlen($raw)){$w=fwrite($f,substr($raw,$n));if($w===false||$w===0){fclose($f);exit(75);}$n+=$w;}if(!fflush($f)){fclose($f);exit(76);}if(function_exists('fsync')&&!fsync($f)){fclose($f);exit(77);}fclose($f);echo json_encode(['approved_null_additions'=>count($allowed),'framework_booted'=>false],JSON_THROW_ON_ERROR);'''
 command=['unshare','--net','setpriv','--reuid='+str(controller.uid),'--regid='+str(controller.gid),'--init-groups','env','APP_CONFIG_CACHE='+str(temp_path),controller.p['php_cli'],'-r',code,str(old_path),str(temp_path)]
 raw=controller.run(command,input=json.dumps(allowed).encode(),cwd=controller.root,umask=0o077)
 return json.loads(raw)

def build_native_config_cache(controller,scope):
 project=controller.p.get('project');allowed=controller.p.get('config_change_hashes',{})
 need(project!='courier' and allowed==expected_config_changes(project) and not scope.get('runtime_cache_paths') and not scope.get('provider_additions'),'native_config_delta_not_exact_v111_scope')
 need(set(scope['source_paths'])=={'composer.json','composer.lock','config/app_errors.php'},'native_config_source_scope_not_exact')
 cache=controller.root/'bootstrap/cache/config.php';before=cache.stat();parent=cache.parent.stat()
 need(cache_identity(cache)==controller._initial_cache_identity and cache.read_bytes()==controller.old_cache,'cached_configuration_anchor_drift')
 need((parent.st_dev,parent.st_ino,parent.st_uid,parent.st_gid,stat.S_IMODE(parent.st_mode))==controller._initial_cache_parent_identity,'cached_configuration_parent_drift')
 acl=controller._initial_cache_acl;need(access_acl(cache)==acl,'cached_configuration_acl_drift')
 temp=cache.with_name('.component-config-native-'+secrets.token_hex(8)+'.php');controller.temp_cache=temp
 try:
  generated=merge_native_cache_missing_nulls(controller,cache,temp,allowed);need(generated=={'approved_null_additions':2,'framework_booted':False},'native_config_generation_unexpected')
  need(temp.is_file()and not temp.is_symlink(),'native_config_temp_missing');temp_info=temp.stat();need((temp_info.st_uid,temp_info.st_gid)==(controller.uid,controller.gid),'native_config_temp_owner_mismatch')
  os.chown(temp,before.st_uid,before.st_gid);temp.chmod(stat.S_IMODE(before.st_mode));preserve_access_acl(acl,temp)
  need((temp.stat().st_uid,temp.stat().st_gid,stat.S_IMODE(temp.stat().st_mode))==(before.st_uid,before.st_gid,stat.S_IMODE(before.st_mode))and access_acl(temp)==acl,'native_config_temp_metadata_mismatch')
  need(native_missing_null_delta_proof(controller,cache,temp,allowed),'native_config_typed_delta_mismatch');candidate_cache_sha=sha(temp.read_bytes())
  need(cache_identity(cache)==controller._initial_cache_identity and cache.read_bytes()==controller.old_cache,'cached_configuration_pre_promotion_drift')
  parent_now=cache.parent.stat();need((parent_now.st_dev,parent_now.st_ino,parent_now.st_uid,parent_now.st_gid,stat.S_IMODE(parent_now.st_mode))==controller._initial_cache_parent_identity,'cached_configuration_parent_drift')
  controller.stopped();os.replace(temp,cache);controller.temp_cache=None
  after=cache.stat();parent_after=cache.parent.stat()
  need((after.st_uid,after.st_gid,stat.S_IMODE(after.st_mode))==(before.st_uid,before.st_gid,stat.S_IMODE(before.st_mode))and access_acl(cache)==acl,'native_config_promoted_metadata_mismatch')
  need((parent_after.st_dev,parent_after.st_ino,parent_after.st_uid,parent_after.st_gid,stat.S_IMODE(parent_after.st_mode))==controller._initial_cache_parent_identity,'native_config_promoted_parent_changed')
  need(sha(cache.read_bytes())==candidate_cache_sha,'native_config_promoted_digest_mismatch')
  controller.report['configuration_strategy']='native_typed_array_merge_private_network_no_framework_boot_acl_preserved'
 finally:
  if controller.temp_cache is not None and temp.exists()and not temp.is_symlink():temp.unlink()
  controller.temp_cache=None

def access_acl(path):
 try:return os.getxattr(path,'system.posix_acl_access',follow_symlinks=False)
 except OSError as exc:
  if exc.errno==getattr(errno,'ENODATA',61):return None
  raise Refuse('config_cache_acl_read_failed')
def preserve_access_acl(source_acl,target):
 if source_acl is None:
  try:os.removexattr(target,'system.posix_acl_access',follow_symlinks=False)
  except OSError as exc:
   if exc.errno!=getattr(errno,'ENODATA',61):raise Refuse('config_cache_acl_remove_failed')
 else:
  try:os.setxattr(target,'system.posix_acl_access',source_acl,follow_symlinks=False)
  except OSError:raise Refuse('config_cache_acl_copy_failed')

class ComponentOverlay:
 def __init__(self,controller,scope):self.p=controller;self.s=scope;self.changed=False;self.old_component_moved=False;self.new_component_live=False;self.new_parent=False
 def read_archive(self):
  s=self.s;p=self.p;need(sha(pathlib.Path(s['artifact_path']).read_bytes())==s['artifact_sha256'],'artifact_digest_mismatch');paths=set(s['source_paths'])|set(s['vendor_metadata_paths'])|set(s.get('runtime_cache_paths',{}))|{PREFIX+rel(x)for x in s['component']['files']}|{DIST_ZIP,DIST_MANIFEST};self.sources={}
  need(0<len(paths)<=512 and re.fullmatch(r'[0-9a-f]{40}',s['component']['canonical_reference'])is not None,'component_scope_unbounded_or_invalid_pin')
  with tarfile.open(s['artifact_path'])as tar:
   members=tar.getmembers();need(len(members)==len(paths)and{x.name for x in members}==paths,'artifact_members_not_exact')
   need(sum(x.size for x in members)<=67108864,'overlay_artifact_too_large')
   for item in members:need(item.isfile()and not item.issym()and not item.islnk()and 0<item.size<=4194304,'unsafe_artifact_member');self.sources[rel(item.name)]=tar.extractfile(item).read()
  for key,pair in {**s['source_paths'],**s['vendor_metadata_paths'],**s.get('runtime_cache_paths',{})}.items():need(sha(self.sources[key])==pair['new'],'candidate_file_digest_mismatch')
  for key,h in s['component']['files'].items():need(sha(self.sources[PREFIX+key])==h and(key in['composer.json','README.md','manifest.json']or(key.startswith('src/Reporting/')and key.endswith('.php'))),'component_source_manifest_mismatch')
  validate_distribution(self.sources,s['component'])
 def preflight(self):
  p=self.p;s=self.s;need(s.get('schema_version')==1,'scope_schema_mismatch');need(p.p['project'] in ALLOWED_PROJECTS,'project_not_in_finite_reporting_scope');self.control=pathlib.Path(s['control_directory']);need(self.control.parent==p.root.parent and re.fullmatch(r'\.'+re.escape(p.p['project'])+r'-component-[A-Za-z0-9_-]{8,80}',self.control.name)is not None,'control_directory_outside_own_parent')
  need(set(s['vendor_metadata_paths'])==META2,'only_two_installed_vendor_metadata_supported');validate_source_paths(p.p['project'],s['source_paths'],p.p['live_manifest']['files'],p.p['candidate_manifest']['files'])
  need(str(p.entry)==APP_ROOTS[p.p['project']][0] and str(p.root)==APP_ROOTS[p.p['project']][1],'application_or_runtime_root_not_in_finite_scope')
  need(not s.get('runtime_cache_paths') and not s.get('provider_additions'),'v111_configuration_delta_forbidden');validate_config_change_policy(p.p['project'],s['source_paths'],p.p.get('config_change_hashes',{}));need(not(p.root/'vendor').is_symlink(),'shared_vendor_root_forbidden');self.read_archive();need(fingerprint(p.root/'vendor')==s['vendor_before'],'old_vendor_or_sdk_drift')
  self.state_file=p.state_path();self.state_declared=s['recorded_state'];self.state_reconciliation=s.get('state_reconciliation')
  common_fields={'exists','path','application_root','runtime_root','server_identity_sha256','source_manifest_sha256','source_commit'}
  if self.state_declared.get('exists') is True:
   self.state_raw,self.state_info=state_snapshot(self.state_file);self.state_parent_info=self.state_file.parent.stat();self.state_hash=sha(self.state_raw);state_fields=common_fields|{'sha256','dev','ino','size','mtime_ns','uid','gid','mode','parent_dev','parent_ino','parent_uid','parent_gid','parent_mode'};need(set(self.state_declared)==state_fields,'recorded_state_scope_shape_invalid');need(self.state_declared['path']==str(self.state_file) and self.state_declared['sha256']==self.state_hash and self.state_declared['dev']==self.state_info.st_dev and self.state_declared['ino']==self.state_info.st_ino and self.state_declared['size']==self.state_info.st_size and self.state_declared['mtime_ns']==self.state_info.st_mtime_ns and self.state_declared['uid']==self.state_info.st_uid and self.state_declared['gid']==self.state_info.st_gid and self.state_declared['mode']==stat.S_IMODE(self.state_info.st_mode) and self.state_declared['parent_dev']==self.state_parent_info.st_dev and self.state_declared['parent_ino']==self.state_parent_info.st_ino and self.state_declared['parent_uid']==self.state_parent_info.st_uid and self.state_declared['parent_gid']==self.state_parent_info.st_gid and self.state_declared['parent_mode']==stat.S_IMODE(self.state_parent_info.st_mode),'recorded_state_preimage_drift');recorded=json.loads(self.state_raw);recorded_manifest=recorded.get('source_manifest');need(recorded.get('application_root')==self.state_declared['application_root']==str(p.entry) and recorded.get('runtime_root')==self.state_declared['runtime_root']==str(p.root) and recorded.get('server_identity_sha256')==self.state_declared['server_identity_sha256']==p.p['server_identity_sha256'],'recorded_state_identity_drift');need(state_manifest_digest(recorded_manifest)==self.state_declared['source_manifest_sha256'] and recorded_manifest.get('candidate_commit')==self.state_declared['source_commit'],'recorded_state_manifest_binding_drift');validate_state_reconciliation(recorded_manifest,p.p['live_manifest'],self.state_hash,self.state_declared['source_manifest_sha256'],self.state_reconciliation);self._state_preimage_type='present'
  else:
   absent_fields=common_fields|{'nearest_existing_ancestor','missing_directories'};need(self.state_declared.get('exists') is False and set(self.state_declared)==absent_fields,'recorded_state_scope_shape_invalid');self.state_absence=validate_absent_state(self.state_file,self.state_declared);need(self.state_declared['application_root']==str(p.entry) and self.state_declared['runtime_root']==str(p.root) and self.state_declared['server_identity_sha256']==p.p['server_identity_sha256'] and self.state_declared['source_manifest_sha256']==state_manifest_digest(p.p['live_manifest']) and self.state_declared['source_commit']==p.p['live_manifest']['candidate_commit'],'recorded_state_manifest_binding_drift');need(self.state_reconciliation is None,'reconcile_absent_recorded_state_forbidden');self.state_raw=None;self.state_info=None;self.state_parent_info=self.state_absence['ancestor_info'];self.state_hash=sha(absent_state_marker(self.state_declared));self._state_preimage_type='absent'
  self.old={};self.old_info={};self.parents={}
  for name,pair in {**s['source_paths'],**s['vendor_metadata_paths'],**s.get('runtime_cache_paths',{})}.items():
   rel(name);target=p.root/name;need(not target.is_symlink()and target.parent.resolve().is_relative_to(p.root)and target.parent.is_dir(),'source_parent_or_symlink_drift');raw=target.read_bytes()if target.exists()else None;need((sha(raw)if raw is not None else None)==pair['old'],'old_overlay_file_drift');self.old[name]=raw;self.old_info[name]=target.stat()if target.exists()else types.SimpleNamespace(st_uid=p.uid,st_gid=p.gid,st_mode=0o644);self.parents[name]=(target.parent.stat().st_dev,target.parent.stat().st_ino)
  changed={k for k in set(p.p['live_manifest']['files'])|set(p.p['candidate_manifest']['files'])if p.p['live_manifest']['files'].get(k)!=p.p['candidate_manifest']['files'].get(k)};need(changed==set(s['source_paths']),'full_source_delta_not_exact')
  validate_dependency_delta(json.loads(self.old['composer.json']),json.loads(self.sources['composer.json']),json.loads(self.old['composer.lock']),json.loads(self.sources['composer.lock']),s['component'])
  old=json.loads(self.old['vendor/composer/installed.json']);new=json.loads(self.sources['vendor/composer/installed.json']);need({k:v for k,v in old.items()if k!='packages'}=={k:v for k,v in new.items()if k!='packages'}and [x for x in old['packages']if x['name']!=COMPONENT]==[x for x in new['packages']if x['name']!=COMPONENT],'unrelated_installed_vendor_metadata_delta')
  package=[x for x in new['packages']if x['name']==COMPONENT];need(len(package)==1 and package[0].get('dist')==s['component']['package_metadata']['dist']and'source'not in package[0]and package[0].get('installation-source')=='dist'and not package[0].get('extra'),'installed_component_pin_or_provider_mismatch')
  if s.get('runtime_cache_paths'):validate_services_delta(php_literal(p,self.old['bootstrap/cache/services.php']),php_literal(p,self.sources['bootstrap/cache/services.php']),s.get('provider_additions',[]))
  self.component=p.root/PREFIX.rstrip('/');need(not self.component.is_symlink()and not self.component.parent.is_symlink(),'component_vendor_symlink_forbidden');self.component_before=fingerprint(self.component);self.guard=self.protected();self.cache_info=(p.root/'bootstrap/cache/config.php').stat();self.state=p.state_path();self.state_before=self.state.read_bytes()if self.state.exists()else None
  return {'status':'COMPONENT_PREFLIGHT_PASS','source_files':len(s['source_paths']),'vendor_metadata_files':len(META2),'component_files':len(s['component']['files']),'production_changed':False}
 def protected(self):
  p=self.p;paths=['.env',*self.s.get('protected_cache_paths',['bootstrap/cache/packages.php','bootstrap/cache/services.php','bootstrap/cache/events.php','bootstrap/cache/routes-v7.php'])];paths=[x for x in paths if x not in self.s.get('runtime_cache_paths',{})];out={x:sha((p.root/x).read_bytes())if(p.root/x).is_file()else None for x in paths};out.update({x:sha(pathlib.Path(x).read_bytes())for x in p.supervisor_hashes});need(p.nginx_path.read_bytes()==(p.nginx_gate if p.gated else p.nginx_before),'nginx_unreviewed_change');need(p.run(['crontab','-u',p.p.get('cron_user','www-data'),'-l'])==(p.cron_paused_content if p.cron_paused else p.cron_before),'cron_unreviewed_change');out['nginx']=sha(p.nginx_before);out['cron']=sha(p.cron_before);out['env_inode']=[p.env_anchor.stat().st_dev,p.env_anchor.stat().st_ino];out['storage_inode']=[p.storage_anchor.stat().st_dev,p.storage_anchor.stat().st_ino];out.update(self.state_protection());return out
 def state_protection(self):
  if self.p._component_state_absent:
   current=validate_absent_state(self.state_file,self.state_declared);state={'exists':False,'sha256':self.state_hash,'nearest_existing_ancestor':[str(current['ancestor_path']),*directory_identity(current['ancestor_info']).values()],'missing_directories':[str(x)for x in current['missing_directories']]}
  else:
   raw,st=state_snapshot(self.state_file);parent=self.state_file.parent.stat();state={'exists':True,'sha256':sha(raw),'identity':[st.st_dev,st.st_ino,st.st_size,st.st_mtime_ns,st.st_uid,st.st_gid,stat.S_IMODE(st.st_mode),st.st_nlink],'parent':[parent.st_dev,parent.st_ino,parent.st_uid,parent.st_gid,stat.S_IMODE(parent.st_mode)]}
  return {'recorded_state_protection':state}
 def restore_state(self):
  if self.p._component_state_was_absent:
   restore_absent_state(self.p,getattr(self.p,'_component_state_written_sha',None));self.guard.update(self.state_protection());return
  expected=getattr(self.p,'_component_state_written_sha',None)
  parent=self.state_parent_info;parent_identity=(parent.st_dev,parent.st_ino,parent.st_uid,parent.st_gid,stat.S_IMODE(parent.st_mode));restore_state_file(self.state_file,self.state_raw,self.state_info,expected,self.p.atomic,getattr(self.p,'_component_state_written_identity',None),parent_identity)
  self.p.state_write_started=False;self.guard.update(self.state_protection())
 def backup(self):
  from cryptography.hazmat.primitives import serialization,hashes
  from cryptography.hazmat.primitives.asymmetric import padding
  from cryptography.hazmat.primitives.ciphers.aead import AESGCM
  need(not self.control.exists(),'backup_control_already_exists');self.control.mkdir(mode=0o700);need(self.control.stat().st_dev==self.p.root.stat().st_dev,'control_must_share_component_filesystem');buf=io.BytesIO();digests={}
  with tarfile.open(fileobj=buf,mode='w:gz')as tar:
   rows={**self.old,'.env':self.p.env_anchor.read_bytes(),'bootstrap/cache/config.php':self.p.old_cache}
   if self._state_preimage_type=='present':rows[STATE_MEMBER]=self.state_raw
   else:rows[STATE_ABSENCE_MEMBER]=absent_state_marker(self.state_declared)
   if self.state_reconciliation is not None:rows[STATE_RECONCILE_MEMBER]=json.dumps(self.state_reconciliation,sort_keys=True,separators=(',',':')).encode()
   for k in self.component_before:
    target=self.component/k
    if not target.is_symlink():rows[PREFIX+k]=target.read_bytes()
   for name,raw in rows.items():
    if raw is None:continue
    info=tarfile.TarInfo(name);info.size=len(raw);original=self.state_info if name==STATE_MEMBER else (self.p.root/name).stat() if not name.startswith('__state__/') else types.SimpleNamespace(st_mode=stat.S_IFREG|0o600,st_uid=0,st_gid=0,st_mtime=0);info.mode=stat.S_IMODE(original.st_mode);info.uid=original.st_uid;info.gid=original.st_gid;info.mtime=int(original.st_mtime);tar.addfile(info,io.BytesIO(raw));digests[name]=sha(raw)
  raw=buf.getvalue();key=os.urandom(32);nonce=os.urandom(12);aad=('COMMON-REPORTING-V103:'+self.p.p['project']+':'+self.s['artifact_sha256']+':'+self.state_hash).encode();cipher=AESGCM(key).encrypt(nonce,raw,aad);need(AESGCM(key).decrypt(nonce,cipher,aad)==raw,'backup_server_gcm_auth_failed');public=serialization.load_ssh_public_key(self.s['backup_public_key'].encode());wrapped=public.encrypt(key,padding.OAEP(mgf=padding.MGF1(hashes.SHA256()),algorithm=hashes.SHA256(),label=None))
  for name,data in [('rollback.aesgcm',cipher),('wrapped-key.bin',wrapped)]:target=self.control/name;target.write_bytes(data);target.chmod(0o600)
  meta={'project':self.p.p['project'],'artifact_sha256':self.s['artifact_sha256'],'nonce':base64.b64encode(nonce).decode(),'aad':aad.decode(),'cipher_sha256':sha(cipher),'wrapped_key_sha256':sha(wrapped),'members':digests,'old_vendor_fingerprint_sha256':canonical(self.s['vendor_before']),'state_preimage':self.state_declared,'GCM_server_verified':True};target=self.control/'backup.json';target.write_text(json.dumps(meta,indent=2));target.chmod(0o600);need(self.protected()==self.guard and fingerprint(self.p.root/'vendor')==self.s['vendor_before'],'backup_snapshot_changed');return {'status':'ENCRYPTED_BACKUP_READY','control_directory':str(self.control),'metadata_sha256':sha(target.read_bytes()),'local_auth_required':True,'production_changed':False}
 def auth_backup(self):
  need(self.control.is_dir()and not self.control.is_symlink()and self.control.stat().st_uid==0 and stat.S_IMODE(self.control.stat().st_mode)==0o700,'untrusted_backup_control');meta_path=self.control/'backup.json';proof_path=self.control/'local-auth-proof.json';need(proof_path.is_file()and not proof_path.is_symlink()and proof_path.stat().st_nlink==1 and proof_path.stat().st_uid==0 and stat.S_IMODE(proof_path.stat().st_mode)==0o600,'authenticated_local_backup_proof_missing');meta=json.loads(meta_path.read_text());proof=json.loads(proof_path.read_text());need(proof=={'metadata_sha256':sha(meta_path.read_bytes()),'GCM_local_verified':True,'members_sha256':canonical(meta['members'])},'backup_auth_proof_mismatch');need(meta['artifact_sha256']==self.s['artifact_sha256']and meta['old_vendor_fingerprint_sha256']==canonical(self.s['vendor_before'])and meta['aad']==('COMMON-REPORTING-V103:'+self.p.p['project']+':'+self.s['artifact_sha256']+':'+self.state_hash),'backup_scope_drift');need(sha((self.control/'rollback.aesgcm').read_bytes())==meta['cipher_sha256']and sha((self.control/'wrapped-key.bin').read_bytes())==meta['wrapped_key_sha256'],'backup_ciphertext_drift')
  need(meta.get('state_preimage')==self.state_declared,'backup_state_preimage_drift')
  for name,h in meta['members'].items():
   if name==STATE_MEMBER:need(self._state_preimage_type=='present' and sha(state_snapshot(self.state_file)[0])==h and h==self.state_hash,'backup_state_changed_since_auth')
   elif name==STATE_ABSENCE_MEMBER:need(self._state_preimage_type=='absent' and validate_absent_state(self.state_file,self.state_declared) and sha(absent_state_marker(self.state_declared))==h,'backup_state_absence_changed_since_auth')
   elif name==STATE_RECONCILE_MEMBER:need(self.state_reconciliation is not None and sha(json.dumps(self.state_reconciliation,sort_keys=True,separators=(',',':')).encode())==h,'backup_state_reconciliation_missing')
   else:need(sha((self.p.root/name).read_bytes())==h,'backup_member_changed_since_auth')
  if self._state_preimage_type=='present':need(sha(state_snapshot(self.state_file)[0])==self.state_hash,'backup_state_preimage_drift')
  else:need(validate_absent_state(self.state_file,self.state_declared) and self.state_hash==sha(absent_state_marker(self.state_declared)),'backup_state_preimage_drift')
 def write(self,name,raw):
  p=self.p;p.stopped();target=p.root/name;need((target.parent.stat().st_dev,target.parent.stat().st_ino)==self.parents[name],'overlay_parent_inode_changed');previous=int(target.stat().st_mtime)if target.exists()else None;p.atomic(target,raw,self.old_info[name]);fresh=int(time.time());fresh+=int(fresh==previous);os.utime(target,(fresh,fresh))
 def vendor_expected(self):
  wanted={k:v for k,v in self.s['vendor_before'].items()if not k.startswith(COMPONENT+'/')}
  for name in self.s['vendor_metadata_paths']:wanted[name.removeprefix('vendor/')]=['file',sha(self.sources[name])]
  for name,h in self.s['component']['files'].items():wanted[COMPONENT+'/'+name]=['file',h]
  return wanted
 def autoload(self):
  p=self.p;code=r"""$root=$argv[1];require $root.'/vendor/autoload.php';$expected=json_decode($argv[2],true);foreach($expected as$class=>$path){if(!class_exists($class)&&!interface_exists($class))exit(51);if(realpath((new ReflectionClass($class))->getFileName())!==realpath($root.'/'.$path))exit(52);}foreach(json_decode($argv[3],true)as$pair)if(!is_a($pair['class'],$pair['type'],true))exit(53);echo json_encode(['classes'=>count($expected),'correct_bindings'=>true,'port_assignability'=>true]);""";return json.loads(p.run(['unshare','--net','setpriv','--reuid='+str(p.uid),'--regid='+str(p.gid),'--init-groups',p.p['php_cli'],'-r',code,str(p.root),json.dumps(self.s['autoload_bindings']),json.dumps(self.s.get('autoload_assignability',[]))]))
 def apply(self):
  p=self.p;s=self.s;need(s['component']['canonical_reference']!=PRIVATE_PROTOTYPE and re.fullmatch(r'[0-9a-f]{64}',s['component'].get('release_review_sha256',''))is not None and s['component'].get('canonical_verified')is True,'canonical_release_review_required');need({'Leolnid\\Common\\Reporting\\Delivery\\NotificationTransport','Leolnid\\Common\\Reporting\\Capture\\LaravelErrorReporter'}<={x['type']for x in s.get('autoload_assignability',[])},'reporter_and_transport_port_proof_required');self.auth_backup();p.control=self.control;report={'project':p.p['project'],'status':'STARTING','phase':'drain','notifications_sent':0,'business_replay':False,'production_changed':False}
  def save():(self.control/'receipt.json').write_text(json.dumps(report,indent=2));(self.control/'receipt.json').chmod(0o600)
  try:
   wait_safe_window()
   need(self.protected()==self.guard and fingerprint(p.root/'vendor')==s['vendor_before'],'fresh_prewrite_drift');p.cron(p.cron_paused_content);p.cron_paused=True;p.drain_producers();p.gate();p.drain_fpm();p.quiesce();p.stopped();save();need(fingerprint(p.root/'vendor')==s['vendor_before'],'vendor_changed_during_drain')
   candidate=self.control/'new-component';candidate.mkdir(mode=0o755)
   for name in s['component']['files']:
    target=candidate/name;target.parent.mkdir(mode=0o755,parents=True,exist_ok=True);target.write_bytes(self.sources[PREFIX+name]);target.chmod(0o644);os.chown(target,p.uid,p.gid);old=self.component/name;previous=int(old.stat().st_mtime)if old.is_file()else None;fresh=int(time.time());fresh+=int(previous==fresh);os.utime(target,(fresh,fresh))
   for here,dirs,_ in os.walk(candidate):
    for name in dirs:os.chown(pathlib.Path(here)/name,p.uid,p.gid)
   os.chown(candidate,p.uid,p.gid);need(fingerprint(candidate)=={k:['file',v]for k,v in s['component']['files'].items()},'staged_component_source_drift');self.changed=True
   if not self.component.parent.exists():self.component.parent.mkdir(mode=0o755);os.chown(self.component.parent,p.uid,p.gid);self.new_parent=True
   p.stopped()
   if self.component.exists():self.component.rename(self.control/'old-component');self.old_component_moved=True
   candidate.rename(self.component);self.new_component_live=True;report['phase']='source_and_autoload_metadata';save()
   for name in [*s['vendor_metadata_paths'],*s['source_paths'],*s.get('runtime_cache_paths',{})]:self.write(name,self.sources[name])
   need(fingerprint(p.root/'vendor')==self.vendor_expected(),'post_vendor_manifest_mismatch');p.manifest(p.root,p.p['candidate_manifest']);p.stopped();report['autoload']=self.autoload();report['phase']='typed_configuration';save();p.build_config();p.stopped();need(self.protected()==self.guard,'protected_data_or_config_changed');report['phase']='restart_and_public';save();p.start();time.sleep(p.opcache_delay);p.restore_nginx_public('component_nginx_generations');p.gated=False;p.await_public_baseline('component_public_observations')
   p.manifest(p.root,p.p['candidate_manifest']);p.report.update(component_reference=s['component']['canonical_reference'],component_files=s['component']['files']);p.persist_state();self.state_written_sha=sha(self.state_file.read_bytes());self.guard.update(self.state_protection());report['phase']='candidate_state_persisted';save()
   p.cron(p.cron_before);p.cron_paused=False
   need(self.protected()==self.guard and fingerprint(p.root/'vendor')==self.vendor_expected(),'final_protected_or_vendor_drift');p.manifest(p.root,p.p['candidate_manifest'])
   report.update(status='COMPONENT_OVERLAY_PASS',phase='verified',production_changed=True,completed_utc=datetime.datetime.now(datetime.timezone.utc).isoformat(),source_files=len(s['source_paths']),vendor_metadata_files=len(s['vendor_metadata_paths']),component_files=len(s['component']['files']),other_vendor_sdk_unchanged=True);save();return report
  except BaseException as exc:
   report.update(status='FAIL',safe_error_class=type(exc).__name__,safe_error_code=safe_refusal_code(exc))
   try:
    if self.changed:
     if not p.cron_paused:p.cron(p.cron_paused_content);p.cron_paused=True
     if not p.gated:p.gate()
     p.drain_fpm();p.drain_producers();p.quiesce();p.stopped()
     for name,raw in self.old.items():
      target=p.root/name;need((sha(target.read_bytes())if target.exists()else None)in[s['source_paths'].get(name,s['vendor_metadata_paths'].get(name,s.get('runtime_cache_paths',{}).get(name)))['new'],sha(raw)if raw is not None else None],'rollback_external_source_drift')
      if raw is None:target.unlink(missing_ok=True)
      else:self.write(name,raw)
     if self.new_component_live:need(fingerprint(self.component)=={k:['file',v]for k,v in s['component']['files'].items()},'rollback_external_component_drift');self.component.rename(self.control/'failed-component');self.new_component_live=False
     if self.old_component_moved:(self.control/'old-component').rename(self.component);self.old_component_moved=False
     if self.new_parent and not any(self.component.parent.iterdir()):self.component.parent.rmdir()
     cache=p.root/'bootstrap/cache/config.php';need(cache.read_bytes()==p.old_cache and cache_identity(cache)==p._initial_cache_identity,'rollback_cache_anchor_drift');need(fingerprint(p.root/'vendor')==s['vendor_before'],'rollback_vendor_manifest_mismatch');p.manifest(p.root,p.p['live_manifest'])
     if getattr(p,'state_write_started',False):self.restore_state()
    p.rollback();need(self.protected()==self.guard and fingerprint(p.root/'vendor')==s['vendor_before'],'rollback_protected_or_vendor_drift');report['rollback_complete']=True
   except BaseException as recovery:report.update(rollback_hold=True,rollback_error_class=type(recovery).__name__,rollback_error_code=safe_refusal_code(recovery))
   save();raise

def load_controller(scope):
 path=pathlib.Path('/run/s1-reporting-overlay-executor.py');need(sha(path.read_bytes())=='268626e13b80c4578f0d63d98ad65c81bf5833bec59bda49be0b49e7e9cc2f23','reviewed_controller_hash_mismatch');spec=importlib.util.spec_from_file_location('reviewed_component_controller',path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
 need(scope.get('component',{}).get('package_metadata',{}).get('version')==RELEASE_VERSION,'v111_component_required');need(scope.get('vendor_metadata_paths') and set(scope['vendor_metadata_paths'])==META2,'installed_metadata_only_required');need(not scope.get('runtime_cache_paths') and not scope.get('provider_additions'),'v111_runtime_cache_or_provider_delta_forbidden');validate_config_change_policy(scope.get('profile',{}).get('project'),scope.get('source_paths',{}),scope.get('profile',{}).get('config_change_hashes',{}));m.validate_profile=wrap_core_validate_profile(m.validate_profile)
 class Controller(m.ExistingRelease):
  def check_untracked_runtime(self):return # Bounded overlay never copies or replaces the application tree.
  def recorded_manifest(self):
   bind_recorded_manifest(self,scope,m.validate_profile)
  def persist_state(self):
   if self._component_state_was_absent:persist_absent_candidate_state(self,candidate_state_bytes(self))
   else:persist_candidate_state(self)
  def preflight(self):
   result=super().preflight();cache=self.root/'bootstrap/cache/config.php';self._initial_cache_identity=cache_identity(cache);parent=cache.parent.stat();self._initial_cache_parent_identity=(parent.st_dev,parent.st_ino,parent.st_uid,parent.st_gid,stat.S_IMODE(parent.st_mode));self._initial_cache_acl=access_acl(cache);return result
  def config_equal(self,old,new,path=''):return config_equal(old,new,self.p.get('config_change_hashes',{}),path.lstrip('.'))
  def build_config(self):
   cache=self.root/'bootstrap/cache/config.php';blocked={'bootstrap/providers.php','app/Providers/BusinessTelemetryServiceProvider.php','app/Providers/DailyReportingServiceProvider.php'}
   unchanged_config=not self.p.get('config_change_hashes')and not scope.get('runtime_cache_paths')and not any(name.startswith('config/')or name in blocked for name in scope['source_paths'])
   if unchanged_config:
    need(cache.read_bytes()==self.old_cache and cache_identity(cache)==self._initial_cache_identity,'cached_configuration_anchor_drift');self.report['configuration_strategy']='preserved_exact_existing_bytes_and_metadata';return
   build_native_config_cache(self,scope)
 return Controller(scope['profile'],scope['artifact_path'])

def execute(scope_path,mode='preflight'):
 scope_file=pathlib.Path(scope_path);need(not scope_file.is_symlink()and scope_file.is_file()and scope_file.stat().st_uid==0 and stat.S_IMODE(scope_file.stat().st_mode)==0o600,'untrusted_scope_file');scope=json.loads(scope_file.read_text());need(set(scope)<= {'schema_version','profile','artifact_path','artifact_sha256','source_paths','vendor_metadata_paths','runtime_cache_paths','provider_additions','protected_cache_paths','vendor_before','component','autoload_bindings','autoload_assignability','control_directory','backup_public_key','recorded_state','state_reconciliation'},'unknown_scope_field');need('recorded_state'in scope and scope['profile']['source_archive_sha256']==scope['artifact_sha256'],'profile_artifact_or_recorded_state_missing');p=load_controller(scope)
 fd=os.open('/run/lock/amorenat-existing-repeat-deploy.lock',os.O_CREAT|os.O_RDWR|os.O_NOFOLLOW,0o600);need(os.fstat(fd).st_uid==0 and stat.S_ISREG(os.fstat(fd).st_mode),'untrusted_host_lock');os.fchmod(fd,0o600);fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB);p.preflight();overlay=ComponentOverlay(p,scope);report=overlay.preflight()
 if mode=='backup':return overlay.backup()
 if mode=='apply':return overlay.apply()
 return report
if __name__=='__main__':
 def cancelled(*args):raise Refuse('component_overlay_cancelled')
 signal.signal(signal.SIGTERM,cancelled);signal.signal(signal.SIGHUP,cancelled)
 try:
  need(len(sys.argv)in[2,3]and(len(sys.argv)==2 or sys.argv[2]in['--backup','--apply']),'unsupported_cli_arguments');mode={'--backup':'backup','--apply':'apply'}.get(sys.argv[2]if len(sys.argv)>2 else'','preflight');print(json.dumps(execute(sys.argv[1],mode)))
 except BaseException as exc:print(json.dumps({'status':'FAIL','safe_error_class':type(exc).__name__,'safe_error_code':safe_refusal_code(exc)}));raise SystemExit(1)
