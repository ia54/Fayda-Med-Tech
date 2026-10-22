#!/usr/bin/env python3
"""Read-only streaming verification of a cPanel pilot archive; NEVER extracts."""
import argparse, hashlib, json, pathlib, re, tarfile


def verify(archive, expected_sha256, expected_commit):
    if not re.fullmatch(r'[0-9a-f]{64}', expected_sha256) or not re.fullmatch(r'[0-9a-f]{40}', expected_commit):
        raise ValueError('Full trusted SHA-256 and source commit required')
    digest = hashlib.sha256()
    with open(archive, 'rb') as source:
        while chunk := source.read(512 * 1024): digest.update(chunk)
    if digest.hexdigest() != expected_sha256: raise ValueError('Archive digest mismatch')
    seen = set(); seen_dirs = set(); manifest = None; expected = {}; files = 0; total = 0
    empty_dirs = {'api/storage/app/private/documents', 'api/storage/framework/cache/data', 'api/storage/framework/sessions', 'api/storage/framework/views', 'api/storage/logs'}
    allowed_dirs = set()
    for name in empty_dirs:
        allowed_dirs.update(str(p) for p in pathlib.PurePosixPath(name).parents if str(p) != '.')
        allowed_dirs.add(name)
    with tarfile.open(archive, 'r|gz') as stream:
        for member in stream:
            name = member.name.rstrip('/'); p = pathlib.PurePosixPath(name)
            if name in seen or name != p.as_posix() or p.is_absolute() or '..' in p.parts or '\\' in name or member.mode & 0o7000:
                raise ValueError('Duplicate or noncanonical archive path')
            seen.add(name)
            if len(seen) > 25000 or not (member.isdir() or member.isfile()): raise ValueError('Unsupported or excessive archive entries')
            if member.isdir():
                if manifest is not None and name not in allowed_dirs and ('directories' in manifest or p.parts[0] not in manifest.get('components', {})): raise ValueError('Unexpected directory')
                seen_dirs.add(name)
                continue
            if manifest is None:
                if name != 'PILOT-MANIFEST.json' or member.size > 8 * 1024 * 1024: raise ValueError('Manifest must be first regular member and bounded')
                manifest = json.load(stream.extractfile(member))
                if manifest.get('kind') != 'godaddy-cpanel-php-static-pilot' or manifest.get('commit') != expected_commit or manifest.get('synthetic_only') is not True or manifest.get('clinical_release') is not False or manifest.get('production_ready') is not False:
                    raise ValueError('Source or synthetic-scope manifest mismatch')
                for row in manifest['files']:
                    path = row['path']; q = pathlib.PurePosixPath(path)
                    if not q.parts or q.parts[0] not in {'api','public','ops'} or '\\' in path or row['type'] != 'file' or path in expected or path != q.as_posix() or q.is_absolute() or '..' in q.parts or path == name:
                        raise ValueError('Invalid manifest file inventory')
                    if q.name == '.env' or q.suffix in {'.key','.pem','.sqlite','.sqlite3'}: raise ValueError('Runtime material refused')
                    expected[path] = row
                    allowed_dirs.update(str(a) for a in q.parents if str(a) != '.')
                if 'directories' in manifest: allowed_dirs = set(manifest['directories'])
                if any(n not in allowed_dirs and ('directories' in manifest or pathlib.PurePosixPath(n).parts[0] not in manifest.get('components', {})) for n in seen_dirs): raise ValueError('Unexpected prefix directory')
                total += member.size
                continue
            row = expected.get(name)
            if not row or member.size != row['size'] or (member.mode & 0o777) != row['mode']: raise ValueError('Unexpected file, size or mode')
            total += member.size
            if total > 512 * 1024 * 1024: raise ValueError('Archive logical size exceeds rehearsal bound')
            h = hashlib.sha256()
            source = stream.extractfile(member)
            while chunk := source.read(512 * 1024): h.update(chunk)
            if h.hexdigest() != row['sha256']: raise ValueError('File digest mismatch')
            files += 1
    if manifest is None or set(expected) != seen.intersection(expected): raise ValueError('Missing manifest or required files')
    if 'directories' in manifest and seen_dirs != set(manifest['directories']): raise ValueError('Directory inventory mismatch')
    for component, counts in manifest.get('components', {}).items():
        if sum(n == component or n.startswith(component+'/') for n in seen_dirs) != counts['directories']: raise ValueError('Component directory count mismatch')
    return {'verified': True, 'source_commit': expected_commit, 'files_verified': files, 'archive_entries': len(seen),
            'fresh_extraction_entries_including_root': len(seen)+1, 'logical_bytes': total,
            'directory_inventory':'exact_paths' if 'directories' in manifest else 'legacy_component_counts_only',
            'filesystem_entries_created': 0, 'extracted': False, 'operational_backup_verified': False}


if __name__ == '__main__':
    p = argparse.ArgumentParser(description=__doc__); p.add_argument('--archive', required=True); p.add_argument('--sha256', required=True); p.add_argument('--commit', required=True)
    a = p.parse_args()
    try: print(json.dumps(verify(a.archive, a.sha256, a.commit), indent=2))
    except (ValueError, KeyError, tarfile.TarError) as e: p.exit(1, str(e)+'\n')
