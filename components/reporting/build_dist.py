#!/usr/bin/env python3
"""Build the allowlisted reporting-only distribution without external dependencies."""
import argparse,hashlib,io,json,pathlib,re,subprocess,zipfile
REPOSITORY='https://github.com/leolnid/amocrm-common'
def raw_json(value):return (json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode('utf-8')
def sha(value):return hashlib.sha256(value).hexdigest()
def fail(condition,message):
 if not condition:raise ValueError(message)
def build(root,commit,tag,output):
 root=root.resolve();output=output.resolve();fail(output.is_relative_to(root) and output!=root,'output outside component workspace')
 fail(re.fullmatch('[a-f0-9]{40}',commit) is not None,'full canonical commit required')
 metadata=root/'components/reporting';composer=json.loads((metadata/'composer.json').read_text(encoding='utf-8'));version=composer['version']
 fail(re.fullmatch(r'(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?',version) is not None,'invalid component SemVer')
 fail(composer['name']=='leolnid/common-reporting' and 'extra'not in composer,'component identity or autodiscovery changed')
 fail(tag=='reporting-v'+version+'-'+commit,'tag must contain exact version and full canonical commit')
 head=subprocess.check_output(['git','-C',str(root),'rev-parse','HEAD']).decode().strip();fail(head==commit,'checkout is not the tagged canonical commit')
 manifest_path=metadata/'source-manifest.json';approved=json.loads(manifest_path.read_text(encoding='utf-8'));files=approved['files']
 fail(approved['package']==composer['name'] and approved['version']==version and isinstance(files,dict),'source manifest identity changed')
 actual={p.relative_to(root).as_posix() for p in(root/'src/Reporting').rglob('*')if p.is_file()or p.is_symlink()}
 expected={name for name in files if name.startswith('src/Reporting/')};fail(actual==expected,'unapproved or missing reporting source file')
 fail(set(files)==expected|{'components/reporting/composer.json','components/reporting/README.md'},'distribution source allowlist changed')
 fail(all(name.endswith('.php')for name in expected),'reporting distribution contains non-PHP source')
 for relative in [*files,'components/reporting/source-manifest.json','components/reporting/build_dist.py']:
  path=root/relative;fail(path.resolve().is_relative_to(root) and path.is_file() and not path.is_symlink(),'invalid source entry')
  for parent in path.relative_to(root).parents:
   fail(not(root/parent).is_symlink(),'symlink source parent refused')
  blob=subprocess.check_output(['git','-C',str(root),'show',commit+':'+relative]);fail(path.read_bytes()==blob,'source bytes differ from approved commit')
 entries={}
 for relative,expected_hash in files.items():
  value=(root/relative).read_bytes();fail(sha(value)==expected_hash,'approved member hash mismatch')
  target=relative if relative.startswith('src/Reporting/')else pathlib.PurePosixPath(relative).name
  fail(target not in entries and '..'not in pathlib.PurePosixPath(target).parts,'duplicate or unsafe archive member');entries[target]=value
 inner={'schema_version':1,'package':composer['name'],'version':version,'source_repository':REPOSITORY,'source_commit':commit,'release_tag':tag,'files':{name:sha(value)for name,value in sorted(entries.items())}}
 entries['manifest.json']=raw_json(inner);buffer=io.BytesIO()
 with zipfile.ZipFile(buffer,'w',compression=zipfile.ZIP_STORED,strict_timestamps=True)as archive:
  for name,value in sorted(entries.items()):
   info=zipfile.ZipInfo(name,(1980,1,1,0,0,0));info.create_system=3;info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_STORED;archive.writestr(info,value)
 content=buffer.getvalue();name='common-reporting-'+version+'-'+commit+'.zip';sha1=hashlib.sha1(content).hexdigest();sha256=sha(content);url=REPOSITORY+'/releases/download/'+tag+'/'+name
 package={key:composer[key]for key in ['name','version','type','require','autoload']};package['dist']={'type':'zip','url':url,'reference':commit,'shasum':sha1}
 release={'schema_version':1,'package':composer['name'],'version':version,'source_repository':REPOSITORY,'source_commit':commit,'release_tag':tag,'zip':{'filename':name,'bytes':len(content),'sha1':sha1,'sha256':sha256,'url':url},'members':{member:sha(value)for member,value in sorted(entries.items())},'package_metadata':package}
 fail(not output.exists() or not any(output.iterdir()),'refuse to overwrite existing distribution artifacts');output.mkdir(parents=True,exist_ok=True)
 for filename,value in {name:content,name+'.sha1':(sha1+'  '+name+'\n').encode(),name+'.sha256':(sha256+'  '+name+'\n').encode(),'release-manifest.json':raw_json(release),'package-metadata.json':raw_json(package),'RELEASE_NOTES.md':('Reporting-only component '+version+'\n\nCanonical source: '+REPOSITORY+'/commit/'+commit+'\n\nZIP SHA256: '+sha256+'\n').encode()}.items():
  destination=output/filename;fail(not destination.exists() and not destination.is_symlink(),'distribution output already exists');destination.write_bytes(value)
 return release
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--source-commit',required=True);parser.add_argument('--tag',required=True);parser.add_argument('--output-dir',default='dist');args=parser.parse_args();root=pathlib.Path(__file__).resolve().parents[2];output=pathlib.Path(args.output_dir);output=output if output.is_absolute()else root/output
 print(json.dumps(build(root,args.source_commit,args.tag,output),sort_keys=True))
