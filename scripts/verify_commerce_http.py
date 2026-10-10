"""Bounded local HTTP checks. Does not log session cookies, tokens or response bodies."""
import argparse
import json
from pathlib import Path
from urllib.request import Request, urlopen
from urllib.error import HTTPError

HOST = 'http://127.0.0.1:8080'
parser = argparse.ArgumentParser()
parser.add_argument('--deferred-open', action='store_true')
args = parser.parse_args()
checks = []

def check(path, expected, method='GET', body=None, private=False):
    request = Request(HOST + path, data=body, method=method)
    try:
        response = urlopen(request, timeout=30)
    except HTTPError as error:
        response = error
    status = response.code
    headers = response.headers
    result = {'path': path, 'method': method, 'status': status, 'expected': expected}
    assert status in (expected if isinstance(expected, tuple) else (expected,)), result
    if private:
        assert headers['Referrer-Policy'] == 'no-referrer', result
        assert 'no-store' in headers['Cache-Control'], result
        result['private_headers'] = True
    response.close()
    checks.append(result)

for locale in ('fr', 'en', 'de'):
    check('/' + locale + '/panier', 200, private=True)
    check('/' + locale + '/commande', 200 if args.deferred_open else 503, private=True)
for locale in ('fr', 'en', 'de'):
    check('/' + locale + '/commande/00000000-0000-4000-8000-000000000001/virement', 404, private=True)
    for kind in ('bank', 'summary'):
        check('/' + locale + '/commande/00000000-0000-4000-8000-000000000001/document/' + kind, 404, private=True)
    check('/' + locale + '/commande/00000000-0000-4000-8000-000000000001/preuve', 419, method='POST', body=b'')
    check('/' + locale + '/commande/adresses', 419, method='POST', body=b'')
check('/fr/commande/00000000-0000-4000-8000-000000000001/virement', 419, method='POST', body=b'')
check('/fr/panier', 419, method='POST', body=b'slug=chauffage-363102-poeles-a-bois&quantity=1')
check('/paiement/stripe/webhook', 503, method='POST', body=b'{}', private=True)
for path in ('/.env', '/vendor/autoload.php', '/storage/app/private/commerce/initial-inventory-v2.json'):
    check(path, (403, 404), method='HEAD')
result = {'result': 'passed', 'checks': checks, 'checkout_enabled': args.deferred_open, 'real_charge': False}
Path('output/qa-commerce/http-acceptance.json').write_text(json.dumps(result, indent=2) + '\n')
print(json.dumps({'result': 'passed', 'http_checks': len(checks), 'csrf': 'rejected', 'checkout': 'deferred' if args.deferred_open else 'closed', 'private_files': 'refused'}))
