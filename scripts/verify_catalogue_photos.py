"""Independently compare prepared photographs to native originals; no mutations."""
from datetime import datetime, timezone
from io import BytesIO
from pathlib import Path
import hashlib
import json
from PIL import Image, ImageChops

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'storage/app/catalogue'
OUTPUT = ROOT / 'output/qa-catalogue/photos-preparation.json'


def main():
    overlay = json.loads((ARCHIVE / 'media-overrides.json').read_text())
    errors, rows = [], []
    for slug, entry in overlay['products'].items():
        source_path = ARCHIVE / entry['original_file']
        output_path = ROOT / 'public' / entry['image']['local_url'].lstrip('/')
        original = source_path.read_bytes()
        prepared = output_path.read_bytes()
        source = Image.open(BytesIO(original))
        result = Image.open(BytesIO(prepared))
        transform = entry['transform']
        assert transform['resampled'] is False
        expected = source.copy()
        if transform.get('crop'):
            expected = expected.crop(transform['crop'])
        if transform.get('background') == 'white':
            rgba = expected.convert('RGBA')
            white = Image.new('RGBA', rgba.size, 'white')
            white.alpha_composite(rgba)
            expected = white.convert('RGB')
            x, y = transform['offset']
            region = result.crop((x, y, x + expected.width, y + expected.height)).convert('RGB')
            # Independently verify the whole canvas: source region unchanged,
            # every remaining pixel is white, with no resampling.
            assembled = Image.new('RGB', result.size, 'white')
            assembled.paste(expected, (x, y))
            pixels_match = region.tobytes() == expected.tobytes() and ImageChops.difference(result.convert('RGB'), assembled).getbbox() is None
        else:
            pixels_match = result.mode == expected.mode and result.size == expected.size and result.tobytes() == expected.tobytes()
        hash_match = hashlib.sha256(original).hexdigest() == entry['original_sha256'] and hashlib.sha256(prepared).hexdigest() == entry['image']['sha256']
        valid = min(result.size) >= 500 and result.width * result.height <= 64_000_000 and len(prepared) <= 16_000_000
        product = json.loads((ARCHIVE / 'products' / (slug + '.json')).read_text())
        same_reference = product['reference'] == entry['reference'] and entry['visual_review'] == 'verified'
        passed = pixels_match and hash_match and valid and same_reference
        rows.append(dict(slug=slug, reference=entry['reference'], source_dimensions=list(source.size), dimensions=list(result.size),
                         bytes=len(prepared), same_product_reference=same_reference, original_and_output_hashes_verified=hash_match,
                         source_pixels_preserved=pixels_match, resampled=False, white_background=transform.get('background') == 'white',
                         recommended_1500=min(result.size) >= 1500, passed=passed))
        if not passed:
            errors.append(slug)
        source.close()
        result.close()
    report = dict(checked_at=datetime.now(timezone.utc).isoformat(), passed=not errors, products=len(rows),
                  unique_photos=len({entry['image']['local_url'] for entry in overlay['products'].values()}),
                  white_backgrounds=sum(row['white_background'] for row in rows), errors=errors, photos=rows,
                  limits='Pixels, native resolution, hashes, model reference and technical limits. Visual correspondence reviewed separately. Not a Google approval or licence certification.')
    OUTPUT.write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
    print(json.dumps({k:v for k,v in report.items() if k != 'photos'}, ensure_ascii=False))
    raise SystemExit(1 if errors else 0)


if __name__ == '__main__':
    main()
