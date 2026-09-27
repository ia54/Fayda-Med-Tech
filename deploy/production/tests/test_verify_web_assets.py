import gzip
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('verify_web_assets', Path(__file__).parents[1] / 'verify_web_assets.py')
verifier = importlib.util.module_from_spec(spec)
spec.loader.exec_module(verifier)


class WebVerificationTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.archive = Path(self.temp.name) / 'candidate.tar.gz'
        self.commit = 'a' * 40
        self.files = {'admin/server.js': b'synthetic server', 'public/index.html': b'synthetic page'}
        self.links = {'admin/link': 'server.js'}
        self.manifest = {
            'format': 1, 'kind': 'linux-web-build-candidate', 'production_ready': False,
            'commit': self.commit, 'tree': 'b' * 40,
            'origins': {'api': 'https://api.faydamed.tech', 'admin': 'https://admin.faydamed.tech', 'public': 'https://faydamed.tech'},
            'target': {'host': '144.126.132.98', 'admin_port': 3005, 'bind': '127.0.0.1'},
            'runtime': {'platform': 'linux', 'architecture': 'x64'},
        }

    def build(self, change_manifest=None, extra=None):
        self.manifest['files'] = [dict(path=p, type='file', size=len(b), mode=0o644, sha256=hashlib.sha256(b).hexdigest())
                                  for p, b in self.files.items()]
        self.manifest['files'] += [dict(path=p, type='symlink', target=t) for p, t in self.links.items()]
        if change_manifest:
            change_manifest(self.manifest)
        with tarfile.open(self.archive, 'w:gz') as tar:
            for p, b in {**self.files, 'WEB-ASSET-MANIFEST.json': json.dumps(self.manifest).encode()}.items():
                info = tarfile.TarInfo('web-assets/' + p)
                info.mode, info.size = 0o644, len(b)
                tar.addfile(info, io.BytesIO(b))
            for p, target in self.links.items():
                info = tarfile.TarInfo('web-assets/' + p)
                info.type, info.linkname = tarfile.SYMTYPE, target
                tar.addfile(info)
            if extra:
                extra(tar)
        return hashlib.sha256(self.archive.read_bytes()).hexdigest()

    def check(self, sha=None, commit=None):
        return verifier.verify(self.archive, sha or hashlib.sha256(self.archive.read_bytes()).hexdigest(), commit or self.commit)

    def test_valid_candidate_is_read_only_and_not_launch_acceptance(self):
        self.build()
        before = self.archive.read_bytes()
        result = self.check()
        self.assertEqual(3, result['files_verified'])
        self.assertFalse(result['production_ready'])
        self.assertFalse(result['extracted_runtime'])
        self.assertEqual(before, self.archive.read_bytes())
        self.assertEqual([self.archive], list(self.archive.parent.iterdir()))

    def test_wrong_digest_commit_or_target_refused(self):
        self.build()
        for args in ({'sha': '0' * 64}, {'commit': 'c' * 40}, {'commit': 'HEAD'}):
            with self.subTest(args=args), self.assertRaises(verifier.VerificationError):
                self.check(**args)
        self.build(lambda m: m['target'].update(admin_port=4000))
        with self.assertRaises(verifier.VerificationError): self.check()

    def test_tampered_and_duplicate_inventory_refused_even_with_matching_archive_digest(self):
        for mutation in (lambda m: m['files'][0].update(sha256='0' * 64),
                         lambda m: m['files'].append(m['files'][0]),
                         lambda m: m['files'].pop()):
            self.build(mutation)
            with self.assertRaises(verifier.VerificationError): self.check()

    def test_unsafe_links_and_link_ancestors_refused(self):
        for target in ('../../outside', '/etc/passwd', 'missing', 'link'):
            self.links['admin/link'] = target
            self.build()
            with self.subTest(target=target), self.assertRaises(verifier.VerificationError): self.check()
        self.links['admin/link'] = '../public'
        self.files['admin/link/child'] = b'nested'
        self.build()
        with self.assertRaises(verifier.VerificationError): self.check()

    def test_duplicate_paths_traversal_and_special_members_refused(self):
        for name, kind in (('web-assets/admin/server.js', tarfile.REGTYPE),
                           ('web-assets/../escape', tarfile.REGTYPE),
                           ('web-assets/admin/device', tarfile.CHRTYPE),
                           ('web-assets/admin/hard', tarfile.LNKTYPE)):
            def extra(tar):
                info = tarfile.TarInfo(name)
                info.type = kind
                tar.addfile(info)
            self.build(extra=extra)
            with self.subTest(name=name), self.assertRaises(verifier.VerificationError): self.check()

    def test_appended_gzip_tar_members_are_not_ignored(self):
        self.build()
        addition = io.BytesIO()
        with tarfile.open(fileobj=addition, mode='w:gz') as tar:
            tar.addfile(tarfile.TarInfo('web-assets/admin/unlisted'))
        with self.archive.open('ab') as stream:
            stream.write(addition.getvalue())
        with self.assertRaises(verifier.VerificationError): self.check()

    def test_duplicate_json_fields_and_wrong_origins_refused(self):
        with self.assertRaises(verifier.VerificationError):
            json.loads('{"commit":"a","commit":"b"}', object_pairs_hook=verifier.unique_object)
        self.build(lambda m: m['origins'].update(api='http://localhost:8000'))
        with self.assertRaises(verifier.VerificationError): self.check()


if __name__ == '__main__':
    unittest.main()
