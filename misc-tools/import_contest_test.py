#!/usr/bin/env python3

'''
Tests for the image import logic of import-contest.

Run from this directory with: python3 -m unittest import_contest_test

Part of the DOMjudge Programming Contest Jury System and licensed
under the GNU GPL. See README and COPYING for details.
'''

import contextlib
import importlib.machinery
import importlib.util
import io
import json
import os
import sys
import tempfile
import types
import unittest

TOOLS_DIR = os.path.dirname(os.path.abspath(__file__))


uploads = []


def load_script():
    dj_utils = types.ModuleType('dj_utils')
    dj_utils.__dict__.update(
        confirm=lambda message, default: True,
        upload_file=lambda name, apifilename, file, data=None: uploads.append((name, file)),
        do_api_request=lambda name: {},
        domjudge_webapp_folder_or_api_url='',
    )
    sys.modules['dj_utils'] = dj_utils
    loader = importlib.machinery.SourceFileLoader('import_contest', os.path.join(TOOLS_DIR, 'import-contest.in'))
    spec = importlib.util.spec_from_loader('import_contest', loader)
    assert spec is not None
    module = importlib.util.module_from_spec(spec)
    loader.exec_module(module)
    return module


import_contest = load_script()

LOGO_EXT_ORDER = ('png', 'svg', 'jpg', 'jpeg')


class ParseImageFilenameTest(unittest.TestCase):
    def test_plain(self):
        self.assertEqual(import_contest.parse_image_filename('logo.png', 'logo'), ([], None))

    def test_dimensions(self):
        self.assertEqual(import_contest.parse_image_filename('logo.64x64.png', 'logo'), ([], 64))

    def test_legacy_bare_width(self):
        self.assertEqual(import_contest.parse_image_filename('logo.64.png', 'logo'), ([], 64))

    def test_tag_and_dimensions(self):
        self.assertEqual(import_contest.parse_image_filename('logo.dark.160x160.png', 'logo'), (['dark'], 160))

    def test_multiple_tags(self):
        self.assertEqual(import_contest.parse_image_filename('logo.light.sponsor.svg', 'logo'),
                         (['light', 'sponsor'], None))

    def test_wrong_property(self):
        self.assertIsNone(import_contest.parse_image_filename('banner.png', 'logo'))

    def test_wrong_extension(self):
        self.assertIsNone(import_contest.parse_image_filename('logo.txt', 'logo'))

    def test_no_extension(self):
        self.assertIsNone(import_contest.parse_image_filename('logo', 'logo'))


class ImageTagsEligibleTest(unittest.TestCase):
    def test_untagged(self):
        self.assertTrue(import_contest.image_tags_eligible([]))

    def test_light(self):
        self.assertTrue(import_contest.image_tags_eligible(['light']))

    def test_light_among_others(self):
        self.assertTrue(import_contest.image_tags_eligible(['sponsor', 'light']))

    def test_dark(self):
        self.assertFalse(import_contest.image_tags_eligible(['dark']))


class SelectImageTest(unittest.TestCase):
    def test_prefers_width(self):
        candidates = [('logo.png', None), ('logo.64x64.jpg', 64)]
        self.assertEqual(import_contest.select_image(candidates, 64, LOGO_EXT_ORDER), 'logo.64x64.jpg')

    def test_prefers_extension_order(self):
        candidates = [('logo.jpg', None), ('logo.png', None)]
        self.assertEqual(import_contest.select_image(candidates, None, LOGO_EXT_ORDER), 'logo.png')
        self.assertEqual(import_contest.select_image(candidates, None, ('jpg', 'png')), 'logo.jpg')

    def test_filename_tiebreak(self):
        candidates = [('logo.b.png', None), ('logo.a.png', None)]
        self.assertEqual(import_contest.select_image(candidates, None, LOGO_EXT_ORDER), 'logo.a.png')


class PackageTestCase(unittest.TestCase):
    def setUp(self):
        uploads.clear()
        self.package_dir = tempfile.TemporaryDirectory()
        self.old_cwd = os.getcwd()
        os.chdir(self.package_dir.name)

    def tearDown(self):
        os.chdir(self.old_cwd)
        self.package_dir.cleanup()

    def write_json(self, filename, data):
        with open(filename, 'w') as f:
            json.dump(data, f)

    def touch(self, path):
        if os.path.dirname(path):
            os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'w') as f:
            f.write('image')

    def import_logos(self):
        output = io.StringIO()
        with contextlib.redirect_stdout(output):
            import_contest.import_images('organizations', 'logo', prefer_width=64, ext_order=LOGO_EXT_ORDER)
        return output.getvalue()


class ImportImagesTest(PackageTestCase):
    def test_refs_take_precedence_and_filter_dark(self):
        self.write_json('organizations.json', [{'id': 'org1', 'logo': [
            {'filename': 'a-light.png', 'mime': 'image/png', 'width': 64, 'height': 64, 'tag': ['light']},
            {'filename': 'a-dark.png', 'mime': 'image/png', 'width': 64, 'height': 64, 'tag': ['dark']},
        ]}])
        self.touch('organizations/org1/a-light.png')
        self.touch('organizations/org1/a-dark.png')
        self.import_logos()
        self.assertEqual(uploads, [('organizations/org1/logo', 'organizations/org1/a-light.png')])

    def test_dark_only_refs_import_nothing(self):
        self.write_json('organizations.json', [{'id': 'org1', 'logo': [
            {'filename': 'a-dark.png', 'mime': 'image/png', 'width': 64, 'height': 64, 'tag': ['dark']},
        ]}])
        self.touch('organizations/org1/a-dark.png')
        self.import_logos()
        self.assertEqual(uploads, [])

    def test_dark_only_refs_merged_with_disk_files(self):
        self.write_json('organizations.json', [{'id': 'org1', 'logo': [
            {'filename': 'a-dark.png', 'mime': 'image/png', 'width': 64, 'height': 64, 'tag': ['dark']},
        ]}])
        self.touch('organizations/org1/a-dark.png')
        self.touch('organizations/org1/logo.png')
        self.import_logos()
        self.assertEqual(uploads, [('organizations/org1/logo', 'organizations/org1/logo.png')])

    def test_ref_metadata_wins_over_filename_pattern(self):
        self.write_json('organizations.json', [{'id': 'org1', 'logo': [
            {'filename': 'logo.png', 'mime': 'image/png', 'width': 64, 'height': 64, 'tag': ['dark']},
        ]}])
        self.touch('organizations/org1/logo.png')
        self.import_logos()
        self.assertEqual(uploads, [])

    def test_refs_and_disk_files_merged(self):
        self.write_json('organizations.json', [{'id': 'org1', 'logo': [
            {'filename': 'a-logo.jpg', 'mime': 'image/jpeg', 'width': 32, 'height': 32},
        ]}])
        self.touch('organizations/org1/a-logo.jpg')
        self.touch('organizations/org1/logo.64x64.png')
        self.import_logos()
        self.assertEqual(uploads, [('organizations/org1/logo', 'organizations/org1/logo.64x64.png')])

    def test_no_refs_scans_directory_without_warning(self):
        self.write_json('organizations.json', [{'id': 'org1'}])
        self.touch('organizations/org1/logo.light.64x64.png')
        self.touch('organizations/org1/logo.dark.64x64.png')
        output = self.import_logos()
        self.assertEqual(uploads, [('organizations/org1/logo', 'organizations/org1/logo.light.64x64.png')])
        self.assertNotIn('Warning', output)

    def test_missing_referenced_file_warns(self):
        self.write_json('organizations.json', [{'id': 'org1', 'logo': [
            {'filename': 'missing.png', 'mime': 'image/png', 'width': 64, 'height': 64},
        ]}])
        self.touch('organizations/org1/logo.png')
        output = self.import_logos()
        self.assertIn('Warning', output)
        self.assertEqual(uploads, [('organizations/org1/logo', 'organizations/org1/logo.png')])

    def test_width_and_extension_preference(self):
        self.write_json('organizations.json', [{'id': 'org1'}])
        self.touch('organizations/org1/logo.jpg')
        self.touch('organizations/org1/logo.64x64.jpg')
        self.touch('organizations/org1/logo.svg')
        self.import_logos()
        self.assertEqual(uploads, [('organizations/org1/logo', 'organizations/org1/logo.64x64.jpg')])

    def test_numeric_json_ids(self):
        self.write_json('organizations.json', [{'id': 42, 'logo': [
            {'filename': 'the-logo.png', 'mime': 'image/png', 'width': 64, 'height': 64},
        ]}])
        self.touch('organizations/42/the-logo.png')
        self.import_logos()
        self.assertEqual(uploads, [('organizations/42/logo', 'organizations/42/the-logo.png')])

    def test_without_entity_json(self):
        self.touch('teams/team1/photo.jpg')
        with contextlib.redirect_stdout(io.StringIO()):
            import_contest.import_images('teams', 'photo', ext_order=('jpg', 'jpeg', 'png', 'svg'))
        self.assertEqual(uploads, [('teams/team1/photo', 'teams/team1/photo.jpg')])


class ImportContestBannerTest(PackageTestCase):
    def import_banner(self):
        with contextlib.redirect_stdout(io.StringIO()):
            import_contest.import_contest_banner('demo')

    def test_referenced_banner_from_contest_yaml(self):
        with open('contest.yaml', 'w') as f:
            f.write('id: demo\nbanner:\n- filename: the-banner.png\n  mime: image/png\n')
        self.touch('contest/the-banner.png')
        self.touch('banner.png')
        self.import_banner()
        self.assertEqual(uploads, [('contests/demo/banner', 'contest/the-banner.png')])

    def test_contest_directory_preferred_over_root(self):
        self.touch('contest/banner.svg')
        self.touch('banner.svg')
        self.import_banner()
        self.assertEqual(uploads, [('contests/demo/banner', 'contest/banner.svg')])

    def test_legacy_root_banner(self):
        self.touch('banner.png')
        self.import_banner()
        self.assertEqual(uploads, [('contests/demo/banner', 'banner.png')])

    def test_dark_banner_skipped(self):
        self.touch('contest/banner.dark.png')
        self.import_banner()
        self.assertEqual(uploads, [])


if __name__ == '__main__':
    unittest.main()
