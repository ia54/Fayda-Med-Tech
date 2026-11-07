"""Prevent shipping newer application source with the old managed-host lock."""
import json
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[3]

class ManagedDependencyContract(unittest.TestCase):
    def test_all_managed_direct_versions_match_the_reviewed_pnpm_importer(self):
        package = json.loads((ROOT / 'deploy/production/godaddy-node/package.json').read_text())
        lock = json.loads((ROOT / 'deploy/production/godaddy-node/package-lock.json').read_text())
        importer = (ROOT / 'admin-panel/pnpm-lock.yaml').read_text().split('\npackages:', 1)[0]
        versions = {quoted or plain: version for quoted, plain, version in re.findall(
            r"^      (?:'([^']+)'|([^:\n]+)):\n        specifier: [^\n]+\n        version: ([^\n(]+)", importer, re.M)}
        self.assertGreater(len(versions), 60)
        self.assertEqual(set(package['dependencies']), set(versions))
        self.assertEqual(package['dependencies'], versions)
        self.assertEqual(lock['packages']['']['dependencies'], package['dependencies'])
        for name, version in versions.items():
            self.assertEqual(lock['packages']['node_modules/' + name]['version'], version, name)
        self.assertEqual(package['overrides']['sharp'], '0.35.5')
        self.assertEqual(lock['packages']['node_modules/sharp']['version'], '0.35.5')

    def test_managed_build_carries_real_schema_regressions_and_braces_mitigation(self):
        packager = (ROOT / 'deploy/production/managed_node_source_package.py').read_text()
        build = (ROOT / 'deploy/production/godaddy-node/scripts/build-managed.cjs').read_text()
        for name in ['tests/braces-depth.test.mjs', 'tests/schema-validation.test.mjs']:
            self.assertIn(name, packager)
            self.assertIn(name, build)
        self.assertIn('scripts/apply-braces-guard.cjs', build)
        self.assertTrue((ROOT / 'admin-panel/patches/braces@3.0.3.patch').is_file())

if __name__ == '__main__':
    unittest.main()
