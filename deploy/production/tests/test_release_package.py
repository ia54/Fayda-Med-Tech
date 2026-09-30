import gzip
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('release_package', Path(__file__).parents[1] / 'release_package.py')
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


class ReleasePackageTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.repo = self.root / 'repo'
        self.repo.mkdir()
        self.git('init', '-q')
        # Disposable fixtures must not launch background maintenance during cleanup.
        self.git('config', 'gc.auto', '0')
        self.git('config', 'maintenance.auto', 'false')
        self.git('config', 'user.email', 'synthetic@example.invalid')
        self.git('config', 'user.name', 'Synthetic release test')
        for path in package.REQUIRED:
            self.write(path, 'SYNTHETIC SOURCE: ' + path)
        self.write('api/database/migrations/2026_09_27_000021_example.php', '<?php // synthetic migration')
        self.write('api/storage/framework/views/.gitignore', '*\n!.gitignore\n')
        self.write('api/.env', 'SECRET_RUNTIME_VALUE')
        self.write('admin-panel/.env.production', 'SECRET_FRONTEND_VALUE')
        self.write('api/storage/app/private/patient.txt', 'PRIVATE_RUNTIME_RECORD')
        self.write('api/public/uploads/patient.pdf', 'PRIVATE_UPLOAD')
        self.write('admin-panel/.next/server.js', 'LOCAL_BUILD_OUTPUT')
        self.write('admin-panel/store/unused.ts.bak', 'STALE_BACKUP_SOURCE')
        self.write('unrelated/other-site.txt', 'UNAUTHORIZED_SITE')
        self.commit = self.commit_all()
        self.output = self.root / 'source.tar.gz'

    def git(self, *args):
        return subprocess.check_output(['git', '-C', str(self.repo), *args], stderr=subprocess.DEVNULL).decode().strip()

    def write(self, path, content):
        target = self.repo / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content)

    def commit_all(self):
        self.git('add', '--all')
        self.git('commit', '-qm', 'Synthetic source')
        return self.git('rev-parse', 'HEAD')

    def build(self):
        return package.build(self.repo, self.commit, self.output)

    def mutate_archive(self, transform):
        # Test a malicious archive even when its own digest is supplied, independently of checksum rejection.
        with tarfile.open(self.output, 'r:gz') as archive:
            members = [(m.name, archive.extractfile(m).read(), m.mode, m.type) for m in archive]
        members = transform(members)
        target = self.root / 'malicious.tar.gz'
        with tarfile.open(target, 'w:gz') as archive:
            for name, content, mode, kind in members:
                info = tarfile.TarInfo(name)
                info.mode, info.type, info.size = mode, kind, len(content)
                info.linkname = '../../outside' if kind in (tarfile.SYMTYPE, tarfile.LNKTYPE) else ''
                archive.addfile(info, io.BytesIO(content))
        return target, package.file_digest(target)

    def test_reproducible_pinned_source_excludes_runtime_and_uncommitted_changes(self):
        self.write('api/artisan', 'UNCOMMITTED_CHANGE')
        result = self.build()
        other = package.build(self.repo, self.commit, self.root / 'other-name.tar.gz')
        self.assertEqual(result['sha256'], other['sha256'])
        self.assertFalse(result['production_ready'])
        self.assertTrue(package.verify(self.output, result['sha256'], self.commit)['verified'])
        with tarfile.open(self.output, 'r:gz') as archive:
            names = archive.getnames()
            content = b'\n'.join(archive.extractfile(m).read() for m in archive)
            self.assertIn('source/api/storage/framework/views/.gitignore', names)
            for secret in [b'SECRET_RUNTIME_VALUE', b'SECRET_FRONTEND_VALUE', b'PRIVATE_RUNTIME_RECORD', b'PRIVATE_UPLOAD', b'LOCAL_BUILD_OUTPUT', b'UNAUTHORIZED_SITE', b'UNCOMMITTED_CHANGE', b'STALE_BACKUP_SOURCE']:
                self.assertNotIn(secret, content)
            manifest = json.loads(archive.extractfile('RELEASE-MANIFEST.json').read())
            self.assertEqual([ 'api/database/migrations/2026_09_27_000021_example.php' ], manifest['migrations'])
        self.assertEqual(0o600, self.output.stat().st_mode & 0o777)

    def test_refuses_mutable_ref_missing_required_source_overwrite_and_checkout_output(self):
        with self.assertRaises(package.PackageError): package.build(self.repo, 'HEAD', self.output)
        with self.assertRaises(package.PackageError): package.build(self.repo, self.commit, self.repo / 'artifact.tar.gz')
        self.build()
        before = self.output.read_bytes()
        with self.assertRaises(package.PackageError): self.build()
        self.assertEqual(before, self.output.read_bytes())
        (self.repo / 'api/composer.lock').unlink()
        self.commit = self.commit_all()
        with self.assertRaises(package.PackageError): package.build(self.repo, self.commit, self.root / 'incomplete.tar.gz')
        self.assertFalse((self.root / 'incomplete.tar.gz').exists())

    def test_symlinks_submodules_and_credential_material_are_refused(self):
        (self.repo / 'api/app').mkdir()
        (self.repo / 'api/app/linked.php').symlink_to('/etc/passwd')
        self.commit = self.commit_all()
        with self.assertRaises(package.PackageError): self.build()
        (self.repo / 'api/app/linked.php').unlink()
        self.write('api/app/credential.txt', '-----BEGIN PRIVATE KEY-----\n' + 'A' * 80 + '\n-----END PRIVATE KEY-----')
        self.commit = self.commit_all()
        with self.assertRaises(package.PackageError): self.build()
        (self.repo / 'api/app/credential.txt').unlink()
        self.write('api/app/credentials.key', 'SECRET')
        self.commit = self.commit_all()
        with self.assertRaises(package.PackageError): self.build()
        (self.repo / 'api/app/credentials.key').unlink()
        self.commit_all()
        self.git('update-index', '--add', '--cacheinfo', '160000,' + self.commit + ',api/app/submodule')
        self.git('commit', '-qm', 'Synthetic gitlink')
        self.commit = self.git('rev-parse', 'HEAD')
        with self.assertRaises(package.PackageError): self.build()
        self.assertFalse(self.output.exists())

    def test_wrong_digest_commit_and_changed_file_content_are_rejected(self):
        result = self.build()
        with self.assertRaises(package.PackageError): package.verify(self.output, '0' * 64, self.commit)
        with self.assertRaises(package.PackageError): package.verify(self.output, result['sha256'], '0' * 40)
        target, checksum = self.mutate_archive(lambda rows: [rows[0]] + [(name, b'X' * len(data), mode, kind) for name, data, mode, kind in rows[1:]])
        with self.assertRaises(package.PackageError): package.verify(target, checksum, self.commit)

    def test_unsafe_duplicate_missing_and_link_members_are_rejected_without_extraction(self):
        self.build()
        for transform in [
            lambda rows: rows + [('source/../../outside', b'bad', 0o644, tarfile.REGTYPE)],
            lambda rows: rows + [rows[1]],
            lambda rows: rows[:-1],
            lambda rows: rows + [('source/api/link', b'', 0o644, tarfile.SYMTYPE)],
            lambda rows: rows + [('source/api/link', b'', 0o644, tarfile.LNKTYPE)],
            lambda rows: rows + [('RELEASE-MANIFEST.json', rows[0][1], 0o644, tarfile.REGTYPE)],
        ]:
            target, checksum = self.mutate_archive(transform)
            with self.assertRaises(package.PackageError): package.verify(target, checksum, self.commit)
        self.assertFalse((self.root / 'outside').exists())

    def test_appended_archive_after_end_markers_is_not_ignored(self):
        self.build()
        extra = io.BytesIO()
        with tarfile.open(fileobj=extra, mode='w') as archive:
            info = tarfile.TarInfo('source/../../outside')
            info.size = 3
            archive.addfile(info, io.BytesIO(b'bad'))
        target = self.root / 'appended.tar.gz'
        target.write_bytes(self.output.read_bytes() + gzip.compress(extra.getvalue()))
        with self.assertRaises(package.PackageError): package.verify(target, package.file_digest(target), self.commit)
        self.assertFalse((self.root / 'outside').exists())

    def test_invalid_manifest_migration_inventory_and_lfs_are_rejected(self):
        self.build()
        def change_manifest(rows):
            data = json.loads(rows[0][1]); data['migrations'] = []
            return [(rows[0][0], json.dumps(data).encode(), rows[0][2], rows[0][3])] + rows[1:]
        target, checksum = self.mutate_archive(change_manifest)
        with self.assertRaises(package.PackageError): package.verify(target, checksum, self.commit)
        self.output.unlink()
        self.write('frontend/public/asset.bin', 'version https://git-lfs.github.com/spec/v1\noid sha256:synthetic\n')
        self.commit = self.commit_all()
        with self.assertRaises(package.PackageError): self.build()
        self.assertFalse(self.output.exists())


if __name__ == '__main__':
    unittest.main()
