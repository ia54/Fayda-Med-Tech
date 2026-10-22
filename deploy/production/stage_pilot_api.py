#!/usr/bin/env python3
"""Stage committed API source for native PHP 8.3 / Composer 2; never installs or deploys."""
import argparse, pathlib, subprocess

def stage(repo, output):
    repo=pathlib.Path(repo).resolve(); output=pathlib.Path(output).resolve()
    if output.exists() or output == repo or repo in output.parents:
        raise ValueError('New staging directory outside checkout required')
    def git(*args): return subprocess.check_output(['git',*args],cwd=repo)
    if git('status','--porcelain','--untracked-files=no').strip():
        raise ValueError('Use a clean committed checkout')
    commit=git('rev-parse','HEAD').decode().strip()
    files=[]
    for name in git('ls-files','api').decode().splitlines():
        if name.startswith(('api/tests/','api/storage/','api/database/factories/','api/database/seeders/')) or name in ('api/.env.example','api/phpunit.xml','api/phpunit.mysql.xml'):
            continue
        relative=pathlib.PurePosixPath(name).relative_to('api')
        if any(p.startswith('.env') for p in relative.parts) or relative.suffix in ('.key','.pem','.sqlite','.sqlite3'):
            raise ValueError('Unexpected runtime/secret path in source')
        files.append((relative,git('show',f'{commit}:{name}')))
    output.mkdir(parents=True)
    for relative,data in files:
        target=output/relative; target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
    for directory in ('bootstrap/cache','storage/app/private/documents','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs'):
        (output/directory).mkdir(parents=True,exist_ok=True)
    return commit

if __name__ == '__main__':
    p=argparse.ArgumentParser();p.add_argument('--repo',default='.');p.add_argument('--output',required=True);a=p.parse_args()
    print(stage(a.repo,a.output))
