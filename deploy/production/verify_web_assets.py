#!/usr/bin/env python3
"""Read-only verification of a pinned web candidate; never extracts or launches it."""
import argparse
import gzip
import hashlib
import json
from pathlib import PurePosixPath
import posixpath
import re
import sys
import tarfile


class VerificationError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise VerificationError(message)


def digest(stream):
    result = hashlib.sha256()
    for chunk in iter(lambda: stream.read(1024 * 1024), b''):
        result.update(chunk)
    return result.hexdigest()


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result, 'Duplicate JSON field')
        result[key] = value
    return result


def clean_path(name):
    require(isinstance(name, str) and name and '\\' not in name and '\x00' not in name,
            'Invalid archive path')
    require(not name.startswith('/') and all(p not in ('', '.', '..') for p in name.split('/')),
            'Noncanonical archive path')
    return name


def verify(archive, expected_sha256, expected_commit):
    require(bool(re.fullmatch('[a-f0-9]{64}', expected_sha256)), 'Expected a trusted SHA-256')
    require(bool(re.fullmatch('[a-f0-9]{40}', expected_commit)), 'Expected a full pinned commit')
    with open(archive, 'rb') as raw:
        require(digest(raw) == expected_sha256, 'Archive digest mismatch')
        raw.seek(0)
        rows, directories, manifest = {}, set(), None
        # Read concatenated gzip streams and continue past tar end markers: appended
        # members must not escape the exact inventory comparison below.
        with gzip.GzipFile(fileobj=raw) as compressed, tarfile.open(
                fileobj=compressed, mode='r|', ignore_zeros=True) as tar:
            names = set()
            for member in tar:
                name = clean_path(member.name.removesuffix('/') if member.isdir() else member.name)
                require(name not in names, 'Duplicate archive member')
                names.add(name)
                require(name == 'web-assets' or name.startswith('web-assets/'), 'Unexpected archive root')
                relative = name[len('web-assets/'):]
                require(member.uid == 0 and member.gid == 0, 'Unexpected archive ownership')
                require(not member.mode & 0o7000, 'Special permission bits refused')
                if member.isdir():
                    directories.add(relative if name != 'web-assets' else '')
                    continue
                require(name != 'web-assets', 'Archive root must be a directory')
                if relative == 'WEB-ASSET-MANIFEST.json':
                    require(member.isfile() and member.size <= 16 * 1024 * 1024, 'Invalid manifest member')
                    manifest = json.load(tar.extractfile(member), object_pairs_hook=unique_object)
                elif member.isfile():
                    rows[relative] = dict(path=relative, type='file', size=member.size,
                                          mode=member.mode, sha256=digest(tar.extractfile(member)))
                elif member.issym():
                    rows[relative] = dict(path=relative, type='symlink', target=member.linkname)
                else:
                    raise VerificationError('Unsupported archive member type')

    require(isinstance(manifest, dict), 'Manifest missing')
    require(manifest.get('format') == 1 and manifest.get('kind') == 'linux-web-build-candidate', 'Unsupported manifest')
    require(manifest.get('commit') == expected_commit and manifest.get('production_ready') is False, 'Candidate identity mismatch')
    require(bool(re.fullmatch('[a-f0-9]{40}', manifest.get('tree', ''))), 'Invalid tree identity')
    require(manifest.get('origins') == {'api': 'https://api.faydamed.tech', 'admin': 'https://admin.faydamed.tech',
                                      'public': 'https://faydamed.tech'}, 'Unexpected application origins')
    require(manifest.get('target') == {'host': '144.126.132.98', 'admin_port': 3005, 'bind': '127.0.0.1'},
            'Unexpected deployment boundary')
    runtime = manifest.get('runtime', {})
    require(runtime.get('platform') == 'linux' and runtime.get('architecture') == 'x64', 'Unexpected build platform')
    expected = {}
    require(isinstance(manifest.get('files'), list), 'Missing file inventory')
    for row in manifest['files']:
        require(isinstance(row, dict), 'Invalid inventory entry')
        name = clean_path(row.get('path'))
        require(name not in expected, 'Duplicate manifest path')
        require(name.startswith(('admin/', 'public/')), 'Unexpected candidate path')
        expected[name] = row
    require(rows == expected, 'Archive does not match manifest inventory')
    for required in ('admin/server.js', 'public/index.html'):
        require(rows.get(required, {}).get('type') == 'file', 'Required web entrypoint missing')
    allowed_dirs = {''}
    for name in rows:
        allowed_dirs.update(str(p) for p in PurePosixPath(name).parents if str(p) != '.')
        require(all(str(p) not in rows for p in PurePosixPath(name).parents),
                'Archive member nested under a file or symlink')
    for name in directories:
        require(name in ('', 'admin', 'public') or name.startswith(('admin/', 'public/')), 'Unexpected directory')
        require(name not in rows and all(str(p) not in rows for p in PurePosixPath(name).parents),
                'Directory nested under a file or symlink')
    allowed_dirs.update(directories)

    def resolve(name, visited):
        parts = name.split('/')
        for index in range(1, len(parts) + 1):
            prefix = '/'.join(parts[:index])
            row = rows.get(prefix, {})
            if row.get('type') != 'symlink':
                continue
            require(prefix not in visited, 'Symlink cycle')
            target = row['target']
            require(isinstance(target, str) and target and not target.startswith('/') and '\\' not in target
                    and '\x00' not in target, 'Invalid symlink target')
            joined = posixpath.normpath(posixpath.join(posixpath.dirname(prefix), target, *parts[index:]))
            require(joined != '..' and not joined.startswith('../'), 'Escaping symlink')
            return resolve(joined, visited | {prefix})
        require(name in rows or name in allowed_dirs, 'Dangling symlink')

    for name, row in rows.items():
        if row['type'] == 'symlink':
            resolve(name, set())
    return {'verified': True, 'commit': expected_commit, 'sha256': expected_sha256,
            'files_verified': len(rows), 'runtime': runtime, 'extracted_runtime': False,
            'production_ready': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive')
    parser.add_argument('--sha256', required=True, help='Digest from the trusted release record, not the archive itself')
    parser.add_argument('--commit', required=True)
    args = parser.parse_args()
    try:
        print(json.dumps(verify(args.archive, args.sha256, args.commit), sort_keys=True))
    except (VerificationError, OSError, EOFError, tarfile.TarError, ValueError, TypeError, KeyError, RecursionError) as error:
        print('Verification refused: ' + str(error), file=sys.stderr)
        sys.exit(1)
