"""Prepare reviewed local product photos without AI or resampling.

Default: validate/plan only. --apply writes sibling media, private originals and
the presentation overlay. Catalogue source JSON and existing media stay intact.
No network, database, migrations or service calls. Pillow required.
"""
import argparse
from datetime import datetime, timezone
from io import BytesIO
import hashlib
import json
import math
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'storage/app/catalogue'
FORMATS = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp', 'GIF': 'gif'}


def prepare(entry, products):
    slug = entry['slug']
    product = products.get(slug)
    if not product or entry['reference'] != product['reference'] or entry.get('visual_review') != 'verified':
        raise ValueError(f'Unverified product correspondence: {slug}')
    source_path = Path(entry['input_path'])
    if not source_path.is_absolute():
        source_path = ROOT / source_path
    data = source_path.read_bytes()
    source_hash = hashlib.sha256(data).hexdigest()
    if source_hash != entry['input_sha256']:
        raise ValueError(f'Input changed since review: {slug}')
    with Image.open(BytesIO(data)) as opened:
        if opened.format not in FORMATS or getattr(opened, 'n_frames', 1) != 1:
            raise ValueError(f'Unsupported or animated photo: {slug}')
        extension = FORMATS[opened.format]
        original_extension = extension
        opened.load()
        image = opened.copy()
    original_size = image.size
    if max(original_size) < 500 or image.width * image.height > 64_000_000 or len(data) > 16_000_000:
        raise ValueError(f'Insufficient or oversized original: {slug}')
    transform = {'resampled': False, 'source_dimensions': original_size}
    if entry.get('crop'):
        box = entry['crop']
        if len(box) != 4 or not (0 <= box[0] < box[2] <= image.width and 0 <= box[1] < box[3] <= image.height):
            raise ValueError(f'Invalid crop: {slug}')
        image = image.crop(box)
        transform['crop'] = box
    if entry.get('white_background'):
        # Keep every source pixel at 1:1. Only the background/canvas changes.
        side = max(500, math.ceil(max(image.size) / 0.9))
        offset = ((side - image.width) // 2, (side - image.height) // 2)
        canvas = Image.new('RGBA', (side, side), 'white')
        rgba = image.convert('RGBA')
        canvas.alpha_composite(rgba, dest=offset)
        image = canvas.convert('RGB')
        expected = Image.new('RGBA', rgba.size, 'white')
        expected.alpha_composite(rgba)
        region = image.crop((offset[0], offset[1], offset[0] + rgba.width, offset[1] + rgba.height))
        if region.tobytes() != expected.convert('RGB').tobytes():
            raise ValueError(f'Product pixel preservation failed: {slug}')
        transform.update(background='white', offset=offset, product_pixels_preserved=True)
    if entry.get('crop') or entry.get('white_background'):
        encoded = BytesIO()
        image.save(encoded, format='PNG', optimize=True)
        output = encoded.getvalue()
        extension = 'png'
    else:
        output = data
    with Image.open(BytesIO(output)) as check:
        dimensions = check.size
        check.verify()
    if min(dimensions) < 500 or dimensions[0] * dimensions[1] > 64_000_000 or len(output) > 16_000_000:
        raise ValueError(f'Prepared photo fails technical thresholds: {slug}')
    digest = hashlib.sha256(output).hexdigest()
    original_name = source_hash[:24] + '.' + original_extension
    image_name = digest[:24] + '.' + extension
    record = {key: value for key, value in entry.items() if key not in {'input_path', 'input_sha256', 'white_background', 'crop'}}
    record.update(original_file='media-originals/' + original_name, original_sha256=source_hash,
                  transform=transform, image={'local_url': '/media/catalogue/' + image_name,
                  'width': dimensions[0], 'height': dimensions[1], 'bytes': len(output), 'sha256': digest})
    return record, [(ARCHIVE / 'media-originals' / original_name, data), (ROOT / 'public/media/catalogue' / image_name, output)]


def preserve_write(path, data):
    if path.exists():
        if path.read_bytes() != data:
            raise ValueError(f'Refusing to overwrite a different existing file: {path.name}')
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open('xb') as handle:
        handle.write(data)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('manifest', type=Path)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    entries = json.loads(args.manifest.read_text())
    products = {p['slug']: p for p in json.loads((ARCHIVE / 'index.json').read_text())['products']}
    prepared, writes = {}, []
    for entry in entries:
        if entry['slug'] in prepared:
            raise ValueError('Duplicate product in manifest')
        record, files = prepare(entry, products)
        prepared[entry['slug']] = record
        writes.extend(files)
    target = ARCHIVE / 'media-overrides.json'
    previous = json.loads(target.read_text()) if target.exists() else {'version': 1, 'products': {}}
    if previous.get('version') != 1 or not isinstance(previous.get('products'), dict):
        raise ValueError('Existing overlay must be reviewed before changing it')
    combined = previous['products'] | prepared
    if args.apply:
        # Validate every entry before any writes. Preserve any prior overlay.
        for path, data in writes:
            if path.exists() and path.read_bytes() != data:
                raise ValueError(f'Refusing to overwrite a different existing file: {path.name}')
        if target.exists():
            old_data = target.read_bytes()
            preserve_write(ARCHIVE / 'media-originals' / ('overlay-' + hashlib.sha256(old_data).hexdigest()[:24] + '.json'), old_data)
        for path, data in writes:
            preserve_write(path, data)
        document = {'version': 1, 'prepared_at': datetime.now(timezone.utc).isoformat(), 'products': combined}
        temporary = target.with_suffix('.pending.json')
        temporary.write_text(json.dumps(document, ensure_ascii=False, indent=2) + '\n')
        temporary.replace(target)
    print(json.dumps({'applied': args.apply, 'products_prepared': len(prepared),
                      'new_unique_photos': len({r['image']['local_url'] for r in prepared.values()}),
                      'white_backgrounds': sum(r['transform'].get('background') == 'white' for r in prepared.values()),
                      'resampled': 0}, ensure_ascii=False))


if __name__ == '__main__':
    main()
