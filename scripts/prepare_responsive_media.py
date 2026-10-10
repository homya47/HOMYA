"""Create content-addressed WebP display variants; never alter source images.

Pillow is required. The default only inventories candidate product images.
--apply creates a new output directory; a collision stops without overwriting.
Only static, valid source images are eligible; no crop, upscale or AI changes.
"""
import argparse
import hashlib
import json
import re
from io import BytesIO
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
URL = re.compile(r"^/media/catalogue/[a-f0-9]{24}\.(?:jpg|png|webp|gif)$")


def encode_variants(data):
    with Image.open(BytesIO(data)) as source:
        if source.format not in ('JPEG', 'PNG', 'WEBP', 'GIF') or getattr(source, 'n_frames', 1) != 1:
            return []
        if min(source.size) < 500 or source.width * source.height > 64_000_000 or len(data) > 16_000_000:
            return []
        source.load()
        image = source.convert('RGBA' if 'A' in source.getbands() or 'transparency' in source.info else 'RGB')
    records = []
    for width in sorted({min(value, image.width) for value in (400, 800, 1200)}):
        resized = image.resize((width, max(1, round(image.height * width / image.width))), Image.Resampling.LANCZOS)
        stream = BytesIO()
        resized.save(stream, 'WEBP', quality=84, method=4, icc_profile=source.info.get('icc_profile', b''))
        encoded = stream.getvalue()
        with Image.open(BytesIO(encoded)) as check:
            check.verify()
        digest = hashlib.sha256(encoded).hexdigest()
        records.append({'name':digest + '.webp', 'width':width, 'height':resized.height,
                        'bytes':len(encoded), 'sha256':digest, 'data':encoded})
    return records


def candidate_urls(root):
    archive = root / 'storage/app/catalogue'
    index = json.loads((archive/'index.json').read_text())
    urls = set()
    for product in index['products']:
        if product.get('image'): urls.add(product['image'].get('local_url', ''))
    overrides = json.loads((archive/'media-overrides.json').read_text())
    for entry in overrides['products'].values():
        if entry.get('image'): urls.add(entry['image'].get('local_url', ''))
    return sorted(url for url in urls if URL.fullmatch(url))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--root', type=Path, default=ROOT)
    args = parser.parse_args()
    root = args.root.resolve()
    directory = root / 'public/media/catalogue-display'
    urls = candidate_urls(root)
    if not args.apply:
        print(json.dumps({'apply':False, 'candidates':len(urls)}))
        return
    directory.mkdir(exist_ok=False)
    entries = {}
    total_source = total_variants = 0
    def prepare(url):
        path = root/'public'/url.lstrip('/')
        if not path.is_file() or path.is_symlink(): return url, None, []
        data = path.read_bytes()
        try:
            return url, data, encode_variants(data)
        except (OSError, ValueError, Image.DecompressionBombError):
            return url, None, []
    pool = ThreadPoolExecutor(max_workers=4)
    for i, (url, data, variants) in enumerate(pool.map(prepare, urls)):
        path = root/'public'/url.lstrip('/')
        if not variants: continue
        with Image.open(BytesIO(data)) as image:
            width, height = image.size
        output = []
        for variant in variants:
            encoded = variant.pop('data')
            target = directory/variant.pop('name')
            if target.exists():
                if target.read_bytes() != encoded: raise ValueError('Variant collision')
            else:
                with target.open('xb') as handle: handle.write(encoded)
            output.append(variant | {'url':'/media/catalogue-display/'+target.name})
        if path.read_bytes() != data: raise ValueError('Source changed during encoding')
        entries[url] = {'sha256':hashlib.sha256(data).hexdigest(), 'width':width, 'height':height, 'bytes':len(data), 'variants':output}
        total_source += len(data)
        total_variants += sum(v['bytes'] for v in output)
        if (i+1) % 250 == 0: print(json.dumps({'processed':i+1}), flush=True)
    pool.shutdown()
    (directory/'manifest.json').write_text(json.dumps({'version':1,'images':entries},separators=(',',':'))+'\n')
    print(json.dumps({'images':len(entries),'source_bytes':total_source,'variant_bytes':total_variants,'source_modified':False}))


if __name__ == '__main__': main()
