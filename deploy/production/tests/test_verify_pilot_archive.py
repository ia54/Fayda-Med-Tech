import hashlib, importlib.util, io, json, pathlib, tarfile, tempfile, unittest
spec=importlib.util.spec_from_file_location('pilot_verifier',pathlib.Path(__file__).parents[1]/'verify_pilot_archive.py')
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
COMMIT='a'*40
class PilotArchiveTest(unittest.TestCase):
    def make(self, folder, extra=None, data=b'synthetic', declared=b'synthetic'):
        path=pathlib.Path(folder)/'pilot.tar.gz'
        row={'path':'api/artisan','type':'file','size':len(declared),'mode':0o644,'sha256':hashlib.sha256(declared).hexdigest()}
        manifest={'kind':'godaddy-cpanel-php-static-pilot','commit':COMMIT,'synthetic_only':True,'clinical_release':False,'production_ready':False,'files':[row]}
        with tarfile.open(path,'w:gz') as t:
            for name,body in [('PILOT-MANIFEST.json',json.dumps(manifest).encode()),('api/artisan',data)]+(extra or []):
                item=tarfile.TarInfo(name);item.size=len(body);item.mode=0o644;t.addfile(item,io.BytesIO(body))
        return path,hashlib.sha256(path.read_bytes()).hexdigest()
    def test_valid_verification_creates_no_extraction_entries(self):
        with tempfile.TemporaryDirectory() as d:
            p,h=self.make(d);before=list(pathlib.Path(d).iterdir());r=module.verify(p,h,COMMIT)
            self.assertEqual(r['filesystem_entries_created'],0);self.assertFalse(r['extracted']);self.assertEqual(before,list(pathlib.Path(d).iterdir()))
    def test_wrong_trusted_digest_or_source_refuses(self):
        with tempfile.TemporaryDirectory() as d:
            p,h=self.make(d)
            for digest,commit in [('0'*64,COMMIT),(h,'b'*40)]:
                with self.assertRaises(ValueError):module.verify(p,digest,commit)
    def test_mutated_file_duplicate_and_traversal_refuse(self):
        with tempfile.TemporaryDirectory() as d:
            for extra,data in [(None,b'MUTATION!'),([('api/artisan',b'synthetic')],b'synthetic'),([('../outside',b'x')],b'synthetic'),([('extra',b'x')],b'synthetic')]:
                p,h=self.make(d,extra,data)
                with self.assertRaises(ValueError):module.verify(p,h,COMMIT)
if __name__=='__main__':unittest.main()
