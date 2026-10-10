import hashlib
from io import BytesIO
import unittest

from PIL import Image
from prepare_responsive_media import encode_variants


class ResponsiveMediaTests(unittest.TestCase):
    def image(self, size=(650, 900), mode='RGB'):
        output = BytesIO()
        Image.new(mode, size, (40, 100, 140, 128) if mode == 'RGBA' else (40, 100, 140)).save(output, 'PNG')
        return output.getvalue()

    def test_variants_preserve_source_ratio_never_upscale_and_are_content_addressed(self):
        source = self.image()
        before = hashlib.sha256(source).hexdigest()
        variants = encode_variants(source)
        self.assertEqual([400, 650], [entry['width'] for entry in variants])
        for entry in variants:
            self.assertEqual(hashlib.sha256(entry['data']).hexdigest(), entry['sha256'])
            with Image.open(BytesIO(entry['data'])) as image:
                self.assertEqual('WEBP', image.format)
                self.assertEqual((entry['width'], entry['height']), image.size)
                self.assertLessEqual(abs(image.height/image.width - 900/650), 1/image.width)
        self.assertEqual(before, hashlib.sha256(source).hexdigest())

    def test_transparency_is_preserved(self):
        for entry in encode_variants(self.image(mode='RGBA')):
            with Image.open(BytesIO(entry['data'])) as image:
                self.assertIn('A', image.getbands())
                self.assertEqual(128, image.getpixel((0, 0))[3])

    def test_small_and_animated_sources_are_excluded(self):
        self.assertEqual([], encode_variants(self.image((400, 400))))
        output = BytesIO()
        images = [Image.new('RGB', (600, 600), color) for color in ['red', 'blue']]
        images[0].save(output, 'GIF', save_all=True, append_images=images[1:])
        self.assertEqual([], encode_variants(output.getvalue()))


if __name__ == '__main__': unittest.main()
