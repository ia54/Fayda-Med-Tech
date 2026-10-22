#!/usr/bin/env python3
"""Deterministic archive of a prepared synthetic cPanel bundle. No uploads."""
import argparse, gzip, hashlib, json, pathlib, tarfile

def archive(root, output):
    root=pathlib.Path(root).resolve();output=pathlib.Path(output).resolve()
    if output.exists() or output == root or root in output.parents: raise ValueError('New output outside bundle required')
    manifest=json.loads((root/'PILOT-MANIFEST.json').read_text())
    if manifest.get('kind') != 'godaddy-cpanel-php-static-pilot' or manifest.get('synthetic_only') is not True or manifest.get('clinical_release') is not False:
        raise ValueError('Synthetic cPanel manifest required')
    paths=[root/'PILOT-MANIFEST.json']+sorted(p for p in root.rglob('*') if p != root/'PILOT-MANIFEST.json')
    for p in paths:
        if p.is_symlink() or not(p.is_dir() or p.is_file()): raise ValueError('Only regular files/directories allowed')
    output.parent.mkdir(parents=True,exist_ok=True)
    with output.open('xb') as raw, gzip.GzipFile(filename='',mode='wb',fileobj=raw,mtime=0) as compressed, tarfile.open(fileobj=compressed,mode='w|',format=tarfile.PAX_FORMAT) as tar:
        for p in paths:
            info=tarfile.TarInfo(p.relative_to(root).as_posix());info.uid=info.gid=info.mtime=0;info.uname=info.gname=''
            info.mode=p.stat().st_mode & 0o777
            if p.is_dir(): info.type=tarfile.DIRTYPE;tar.addfile(info)
            else:
                info.size=p.stat().st_size
                with p.open('rb') as data: tar.addfile(info,data)
    return {'commit':manifest['commit'],'archive':str(output),'bytes':output.stat().st_size,'sha256':hashlib.sha256(output.read_bytes()).hexdigest(),'deployed':False}
if __name__ == '__main__':
    p=argparse.ArgumentParser();p.add_argument('--root',required=True);p.add_argument('--output',required=True);a=p.parse_args();print(json.dumps(archive(a.root,a.output),indent=2))
