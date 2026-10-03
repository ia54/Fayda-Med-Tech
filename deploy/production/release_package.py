#!/usr/bin/env python3
"""Prepare and inspect pinned source archives. Never connects to a host or extracts files."""
import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys
import tarfile

ROOTS = {'api', 'admin-panel', 'frontend'}
TARGETS = {'host': '144.126.132.98', 'api': 'https://api.faydamed.tech',
           'admin': 'https://admin.faydamed.tech', 'public': 'https://faydamed.tech', 'existing_admin_port': 3005}
REQUIRED = {'api/artisan', 'api/composer.json', 'api/composer.lock',
            'admin-panel/package.json', 'admin-panel/pnpm-lock.yaml', 'admin-panel/next.config.mjs',
            'frontend/package.json', 'frontend/package-lock.json', 'frontend/vite.config.js'}
MAX_FILE = 256 * 1024 * 1024
MAX_TOTAL = 1024 * 1024 * 1024
MAX_FILES = 15000


class PackageError(Exception):
    pass


def require(condition, message):
    if not condition:
        raise PackageError(message)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def file_digest(path):
    h = hashlib.sha256()
    with open(path, 'rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def safe_path(path):
    return (isinstance(path, str) and path and not path.startswith('/')
            and not any(ord(c) < 32 or ord(c) == 127 for c in path)
            and '\\' not in path and all(p not in ('', '.', '..') for p in path.split('/')))


def include(path):
    parts = PurePosixPath(path).parts
    if not parts or (parts[0] not in ROOTS and parts[:2] != ('deploy', 'production')):
        return False
    if any(p in {'.git', 'node_modules', 'vendor', '.next', 'dist', 'build', 'out', 'coverage', '__pycache__', '.well-known'} for p in parts):
        return False
    if any(p.lower().startswith('.env') for p in parts) or parts[-1].lower().endswith(('.log', '.pyc', '.tsbuildinfo', '.bak', '.backup', '.swp')):
        return False
    if path.startswith(('api/storage/', 'api/bootstrap/cache/', 'api/public/storage/', 'api/public/uploads/')):
        return parts[-1] == '.gitignore'
    return True


def check_source_path(path):
    require(safe_path(path) and include(path), 'Unsupported source path in package.')
    name = PurePosixPath(path).name.lower()
    require(name not in {'auth.json', '.npmrc', '.netrc'} and not name.endswith(('.key', '.p12', '.pfx', '.sqlite', '.sqlite3', '.db', '.sql', '.bak')),
            'Potential credential, backup or database file in source selection; inspect it locally.')
    require(not name.endswith('.pem') or path == 'api/tests/Fixtures/passport-public.pem',
            'Unapproved PEM file in source selection; inspect it locally.')


def git(repo, *args):
    result = subprocess.run(['git', '-C', str(repo), *args], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    require(result.returncode == 0, 'Git could not read the requested committed source.')
    return result.stdout


def validate_commit(commit):
    require(bool(re.fullmatch(r'[0-9a-f]{40}', commit)), 'Supply a full immutable 40-character commit SHA.')


def blobs(repo, entries):
    process = subprocess.Popen(['git', '-C', str(repo), 'cat-file', '--batch'], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
    try:
        for entry in entries:
            process.stdin.write((entry['oid'] + '\n').encode('ascii'))
            process.stdin.flush()
            header = process.stdout.readline().decode('ascii').strip().split()
            require(len(header) == 3 and header[0] == entry['oid'] and header[1] == 'blob', 'Git source object is not a blob.')
            size = int(header[2])
            require(size == entry['size'] and size <= MAX_FILE, 'Source object size is outside the package limit.')
            data = process.stdout.read(size)
            require(len(data) == size and process.stdout.read(1) == b'\n', 'Incomplete Git object read.')
            require(not data.startswith(b'version https://git-lfs.github.com/spec/v1'), 'Unresolved Git LFS pointer; retrieve and review its source before packaging.')
            require(not re.search(rb'-----BEGIN (?:RSA |EC |OPENSSH |ENCRYPTED )?PRIVATE KEY-----\s+[A-Za-z0-9+/=\r\n]{40,}', data),
                    'Private key material detected in selected source; no package was prepared.')
            yield data
    finally:
        process.stdin.close()
        process.stdout.close()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()


def tar_entry(archive, path, data, mode):
    info = tarfile.TarInfo(path)
    info.size = len(data)
    info.mode = mode
    info.mtime = 0
    info.uid = info.gid = 0
    info.uname = info.gname = ''
    archive.addfile(info, io.BytesIO(data))


def build(repo, commit, output):
    validate_commit(commit)
    repo = Path(git(repo, 'rev-parse', '--show-toplevel').decode().strip()).resolve()
    require(git(repo, 'rev-parse', '--verify', commit + '^{commit}').decode().strip() == commit, 'Source must resolve to the exact commit.')
    output = Path(output).absolute()
    require(output.parent.is_dir(), 'Create an approved local output directory first.')
    require(repo not in output.resolve().parents and output.resolve() != repo, 'Write release archives outside the source checkout.')
    require(not output.exists() and not output.is_symlink(), 'Refusing to overwrite an existing archive.')
    entries = []
    excluded = 0
    for record in git(repo, 'ls-tree', '-r', '-l', '-z', commit).split(b'\0'):
        if not record:
            continue
        meta, raw_path = record.split(b'\t', 1)
        path = raw_path.decode('utf-8')
        require(safe_path(path), 'Unsupported Git path.')
        if not include(path):
            excluded += 1
            continue
        check_source_path(path)
        mode, kind, oid, size = meta.decode('ascii').split()
        require(kind == 'blob' and mode in ('100644', '100755'), 'Symlinks and submodules are not permitted in the release source.')
        entries.append({'path': path, 'mode': int(mode[-3:], 8), 'oid': oid, 'size': int(size)})
    require(0 < len(entries) <= MAX_FILES and sum(e['size'] for e in entries) <= MAX_TOTAL, 'Source package exceeds the size limit.')
    require(REQUIRED <= {e['path'] for e in entries}, 'Required application entry points or lockfiles are missing.')
    for entry, data in zip(entries, blobs(repo, entries)):
        entry['sha256'] = digest(data)
    manifest = {'schema': 1, 'source_commit': commit, 'source_tree': git(repo, 'rev-parse', commit + '^{tree}').decode().strip(),
                'source_only': True, 'production_ready': False, 'targets': TARGETS,
                'files': [{k: e[k] for k in ('path', 'mode', 'size', 'sha256')} for e in entries],
                'migrations': [e['path'] for e in entries if e['path'].startswith('api/database/migrations/') and e['path'].endswith('.php')],
                'excluded_tracked_paths': excluded}
    metadata = (json.dumps(manifest, sort_keys=True, indent=2) + '\n').encode()
    created = False
    try:
        # O_EXCL refuses races and symlink replacement as well as ordinary overwrites.
        fd = os.open(output, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        created = True
        with os.fdopen(fd, 'wb') as raw:
            with gzip.GzipFile(filename='', mode='wb', fileobj=raw, mtime=0) as compressed:
                with tarfile.open(fileobj=compressed, mode='w|', format=tarfile.PAX_FORMAT) as archive:
                    tar_entry(archive, 'RELEASE-MANIFEST.json', metadata, 0o644)
                    for entry, data in zip(entries, blobs(repo, entries)):
                        require(digest(data) == entry['sha256'], 'Source changed while packaging.')
                        tar_entry(archive, 'source/' + entry['path'], data, entry['mode'])
        checksum = file_digest(output)
        verify(output, checksum, commit)
    except Exception:
        if created:
            output.unlink()
        raise
    return {'archive': str(output), 'sha256': checksum, 'commit': commit, 'tree': manifest['source_tree'],
            'files': len(entries), 'migrations': len(manifest['migrations']), 'source_only': True, 'production_ready': False}


def verify(archive_path, checksum, commit):
    validate_commit(commit)
    require(bool(re.fullmatch(r'[0-9a-f]{64}', checksum)), 'Supply the separately recorded trusted archive SHA-256.')
    require(Path(archive_path).stat().st_size <= MAX_TOTAL, 'Archive exceeds the size limit.')
    require(file_digest(archive_path) == checksum, 'Archive checksum does not match the separately recorded digest.')
    # GzipFile reads concatenated gzip members; tarfile's streaming gzip reader
    # stops after the first member and could otherwise hide appended entries.
    with gzip.open(archive_path, 'rb') as compressed, tarfile.open(fileobj=compressed, mode='r|', ignore_zeros=True) as archive:
        first = archive.next()
        require(first is not None and first.name == 'RELEASE-MANIFEST.json' and first.isfile() and 0 < first.size <= 16 * 1024 * 1024, 'Missing or invalid release manifest.')
        manifest = json.load(archive.extractfile(first))
        require(isinstance(manifest, dict), 'Manifest must be an object.')
        require(manifest.get('targets') == TARGETS, 'Archive target boundaries differ from the approved applications.')
        require(manifest.get('schema') == 1 and manifest.get('source_commit') == commit and manifest.get('source_only') is True and manifest.get('production_ready') is False, 'Manifest identity or release boundary is invalid.')
        require(isinstance(manifest.get('source_tree'), str) and bool(re.fullmatch(r'[0-9a-f]{40}', manifest['source_tree'])), 'Invalid source tree identity.')
        files = manifest.get('files')
        require(isinstance(files, list) and 0 < len(files) <= MAX_FILES, 'Invalid source file inventory.')
        expected = {}
        total = 0
        for row in files:
            require(isinstance(row, dict) and set(row) == {'path', 'mode', 'size', 'sha256'}, 'Invalid source entry.')
            check_source_path(row['path'])
            require(row['path'] not in expected and row['mode'] in (0o644, 0o755) and type(row['size']) is int and 0 <= row['size'] <= MAX_FILE and isinstance(row['sha256'], str) and bool(re.fullmatch(r'[0-9a-f]{64}', row['sha256'])), 'Duplicate or invalid source metadata.')
            expected[row['path']] = row
            total += row['size']
        require(total <= MAX_TOTAL and REQUIRED <= set(expected), 'Incomplete or oversized source inventory.')
        migrations = [p for p in expected if p.startswith('api/database/migrations/') and p.endswith('.php')]
        require(manifest.get('migrations') == migrations, 'Migration inventory differs from source files.')
        seen = set()
        for member in archive:
            # Streaming iteration returns the cached first member again; skip exactly that object.
            if member is first:
                continue
            require(member.isfile() and member.name.startswith('source/') and safe_path(member.name), 'Unsafe or unsupported archive member.')
            path = member.name[len('source/'):]
            require(path in expected and path not in seen, 'Unexpected or duplicate archive member.')
            row = expected[path]
            require(member.size == row['size'] and member.mode == row['mode'], 'Source mode or size differs from manifest.')
            stream = archive.extractfile(member)
            h = hashlib.sha256()
            for chunk in iter(lambda: stream.read(1024 * 1024), b''):
                h.update(chunk)
            require(h.hexdigest() == row['sha256'], 'Source content hash differs from manifest.')
            seen.add(path)
        require(seen == set(expected), 'Archive is missing committed source files.')
    return {'verified': True, 'commit': commit, 'sha256': checksum, 'files': len(seen), 'source_only': True, 'production_ready': False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    prepare = sub.add_parser('build')
    prepare.add_argument('--repo', default='.')
    prepare.add_argument('--commit', required=True)
    prepare.add_argument('--output', required=True)
    inspect = sub.add_parser('verify')
    inspect.add_argument('--archive', required=True)
    inspect.add_argument('--sha256', required=True)
    inspect.add_argument('--commit', required=True)
    args = parser.parse_args()
    try:
        result = build(args.repo, args.commit, args.output) if args.command == 'build' else verify(args.archive, args.sha256, args.commit)
        print(json.dumps(result, sort_keys=True))
    except (PackageError, OSError, ValueError, KeyError, TypeError, tarfile.TarError) as error:
        print('Release package refused: ' + str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
