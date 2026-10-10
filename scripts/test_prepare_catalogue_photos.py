"""Local safeguards for reviewed media: correspondence, pixels, bounds, preservation."""
from io import BytesIO
from pathlib import Path
import hashlib
import tempfile
import unittest
from PIL import Image
from prepare_catalogue_photos import prepare, preserve_write


class PhotoPreparationTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.path = Path(self.directory.name) / 'photo.png'
        # Synthetic fixture only; actual product photos are never resampled.
        image = Image.new('RGBA', (241, 679), (12, 80, 160, 255))
        image.putpixel((0, 0), (10, 40, 70, 128))
        image.save(self.path)
        self.entry = dict(slug='test', reference='REF-1', visual_review='verified',
                          input_path=str(self.path), input_sha256=hashlib.sha256(self.path.read_bytes()).hexdigest(),
                          white_background=True)
        self.products = {'test': {'reference': 'REF-1'}}

    def tearDown(self):
        self.directory.cleanup()

    def test_white_canvas_preserves_source_and_opaque_product_pixels_at_one_to_one(self):
        before = self.path.read_bytes()
        record, files = prepare(self.entry, self.products)
        self.assertEqual(self.path.read_bytes(), before)
        self.assertFalse(record['transform']['resampled'])
        self.assertEqual(files[0][1], before)
        with Image.open(BytesIO(files[1][1])) as output:
            self.assertEqual(output.size, (755, 755))
            x, y = record['transform']['offset']
            self.assertEqual(output.getpixel((x + 1, y + 1)), (12, 80, 160))
            self.assertEqual(output.getpixel((x, y)), (132, 147, 162))
            self.assertEqual(output.getpixel((0, 0)), (255, 255, 255))

    def test_small_original_unreviewed_model_and_changed_input_are_rejected(self):
        for replacement in ({'reference': 'REF-2'}, {'visual_review': 'pending'}, {'input_sha256': 'bad'}):
            with self.assertRaises(ValueError):
                prepare(self.entry | replacement, self.products)
        Image.new('RGB', (450, 450), 'white').save(self.path)
        entry = self.entry | {'input_sha256': hashlib.sha256(self.path.read_bytes()).hexdigest()}
        with self.assertRaisesRegex(ValueError, 'Insufficient'):
            prepare(entry, self.products)

    def test_crop_retains_original_pixels_and_rejects_out_of_bounds(self):
        Image.new('RGB', (1080, 1920), (30, 40, 50)).save(self.path)
        entry = self.entry | {'input_sha256': hashlib.sha256(self.path.read_bytes()).hexdigest(),
                             'white_background': False, 'crop': [70, 600, 1070, 1600]}
        record, files = prepare(entry, self.products)
        with Image.open(BytesIO(files[1][1])) as output:
            self.assertEqual(output.size, (1000, 1000))
            self.assertEqual(output.getpixel((500, 500)), (30, 40, 50))
        self.assertFalse(record['transform']['resampled'])
        with self.assertRaisesRegex(ValueError, 'Invalid crop'):
            prepare(entry | {'crop': [-1, 0, 1080, 1920]}, self.products)

    def test_existing_media_cannot_be_overwritten_with_different_content(self):
        before = self.path.read_bytes()
        preserve_write(self.path, before)
        with self.assertRaisesRegex(ValueError, 'Refusing to overwrite'):
            preserve_write(self.path, b'different')
        self.assertEqual(self.path.read_bytes(), before)


if __name__ == '__main__':
    unittest.main()
