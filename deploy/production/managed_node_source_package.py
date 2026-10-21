#!/usr/bin/env python3
"""Secret-free managed Node source ZIP. Reads committed Git blobs; never uploads."""
import argparse, hashlib, json, pathlib, subprocess, zipfile

def build(repo, output):
    repo=pathlib.Path(repo).resolve();output=pathlib.Path(output).resolve()
    if repo==output or repo in output.parents or output.exists(): raise ValueError('New output outside checkout required')
    def git(*args): return subprocess.check_output(['git',*args],cwd=repo)
    if git('status','--porcelain','--untracked-files=no').strip(): raise ValueError('Commit source changes before packaging')
    commit=git('rev-parse','HEAD').decode().strip(); files={}
    allowed_dirs={'app','components','hooks','lib','store','types','public','utils','redux','schemas','context','contexts','providers','styles'}
    allowed_root={'.eslintrc.json','next.config.mjs','postcss.config.mjs','tsconfig.json','components.json','tailwind.config.ts','tailwind.config.js'}
    for raw in git('ls-tree','-r','--name-only','HEAD','admin-panel').decode().splitlines():
        p=pathlib.PurePosixPath(raw);rel=str(p.relative_to('admin-panel'));first=rel.split('/')[0]
        if first in allowed_dirs or rel in allowed_root or rel in ['tests/braces-depth.test.mjs','patches/braces@3.0.3.patch']:
            files[rel]=git('show',f'{commit}:{raw}')
    prefix='deploy/production/godaddy-node/'
    for raw in git('ls-tree','-r','--name-only','HEAD',prefix).decode().splitlines():
        rel=raw[len(prefix):]
        if rel.startswith('node_modules/') or rel.startswith('.next/'): raise ValueError('Generated files in managed source')
        files[rel]=git('show',f'{commit}:{raw}')
    for name in files:
        if '..' in pathlib.PurePosixPath(name).parts or name.startswith('/') or name.startswith('.env') or any(p in name.split('/') for p in ['node_modules','.git','.next']): raise ValueError('Forbidden ZIP path')
        if b'-----BEGIN PRIVATE KEY-----\n' in files[name] or b'-----BEGIN RSA PRIVATE KEY-----\n' in files[name]: raise ValueError('Private key material')
    package=json.loads(files['package.json']);lock=json.loads(files['package-lock.json'])
    assert package['name'] and package['version'] and package['scripts']['build'] and package['scripts']['start']
    assert lock['packages']['']['dependencies']==package['dependencies']
    for required in ['app/layout.tsx','package.json','package-lock.json','.npmrc','scripts/start-managed.cjs']: assert required in files,required
    manifest={'kind':'godaddy-managed-node-source','commit':commit,'synthetic_only':True,'deployed':False,'source_pnpm_lock_sha256':hashlib.sha256(git('show',f'{commit}:admin-panel/pnpm-lock.yaml')).hexdigest(),'managed_npm_lock_sha256':hashlib.sha256(files['package-lock.json']).hexdigest(),'node_modules_included':False,'secrets_included':False,'files':{k:{'bytes':len(v),'sha256':hashlib.sha256(v).hexdigest()} for k,v in sorted(files.items())}}
    files['SOURCE-MANIFEST.json']=(json.dumps(manifest,indent=2)+'\n').encode()
    output.parent.mkdir(parents=True,exist_ok=True)
    with zipfile.ZipFile(output,'x',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for name,data in sorted(files.items()):
            info=zipfile.ZipInfo(name,date_time=(2026,10,21,0,0,0));info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,data,compresslevel=9)
    return {'archive':str(output),'commit':commit,'files':len(files),'uncompressed_bytes':sum(map(len,files.values())),'zip_bytes':output.stat().st_size,'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'node_modules_included':False,'secrets_included':False,'deployed':False}
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--repo',default='.');p.add_argument('--output',required=True);a=p.parse_args();print(json.dumps(build(a.repo,a.output),indent=2))
