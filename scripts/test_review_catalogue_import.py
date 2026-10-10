"""Synthetic fixtures only; no SQL or catalogue mutations."""
import copy
import json
import unittest
from review_catalogue_import import csv_cell, review_product, unique_object


class ReviewTests(unittest.TestCase):
    def product(self):
        return dict(slug='synthetic-fixture', reference='TEST-ONLY', specifications=[],
                    description='', variants=[], has_variants=False, issues=[])

    def test_preserves_conflicting_and_multivalued_entries_without_resolving_them(self):
        product = self.product()
        product['specifications'] = [{'label': 'Autonomie min (h)', 'value': '8'}, {'label': 'Autonomie min (h)', 'value': '12'},
                                     {'label': "Zone d'installation", 'value': 'Zone 1'}, {'label': "Zone d'installation", 'value': 'Zone 2'}]
        original = copy.deepcopy(product)
        rows = review_product(product, {})
        conflict = next(r for r in rows if r['kind'] == 'distinct_specification_values')
        self.assertEqual([r['value'] for r in conflict['evidence']], ['8', '12'])
        self.assertTrue(any(r['kind'] == 'possible_multivalued_specification' for r in rows))
        self.assertEqual(product, original)

    def test_duplicate_json_keys_cannot_silently_discard_values(self):
        with self.assertRaises(ValueError):
            json.loads('{"reference":"A","reference":"B"}', object_pairs_hook=unique_object)

    def test_missing_variant_prices_are_not_inherited_or_set_to_zero(self):
        product = self.product()
        product.update(variants=[{'title': 'Synthetic option', 'sku': 'TEST-OPTION'}], has_variants=True)
        row = next(r for r in review_product(product, {}) if r['kind'] == 'variant_business_data_missing')
        self.assertIn('price', row['evidence']['missing'])
        self.assertNotIn('price', product['variants'][0])

    def test_unresolved_prose_and_dangerous_csv_cells_are_identified(self):
        product = self.product()
        product['description'] = '$a1'
        self.assertTrue(any(r['kind'] == 'unresolved_description' for r in review_product(product, {})))
        self.assertEqual(csv_cell('  =SUM(1,2)'), "'  =SUM(1,2)")
        self.assertEqual(csv_cell('\tOrdinary prose'), "'\tOrdinary prose")
        self.assertEqual(csv_cell('Ordinary prose'), 'Ordinary prose')


if __name__ == '__main__':
    unittest.main()
