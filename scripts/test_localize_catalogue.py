"""Guard against altered measurements/codes and domain mistranslations. No models/network."""
import unittest

from localize_catalogue import clean, safe, technical
from refine_catalogue_localizations import title_translation


class LocalizationTest(unittest.TestCase):
    def test_guard_rejects_changed_or_extra_numeric_facts_and_model_codes(self):
        self.assertTrue(safe('Largeur 20 cm', 'Width 20 cm'))
        self.assertFalse(safe('Largeur 20 cm', 'Width 200 cm'))
        self.assertFalse(safe('Largeur 20 cm', 'Width 20 cm, 2 shelves'))
        self.assertFalse(safe('Samsung WW90T534DAW 9 kg', 'Samsung WW90T534DBW 9 kg'))

    def test_clean_identity_does_not_publish_source_links(self):
        self.assertEqual(clean('Produit DAMDAM https://www.damdam.fr/produit/test'), 'Produit HOMYA')

    def test_measures_are_identified_without_changing_their_units(self):
        self.assertTrue(technical('60 × 38 × 18 cm'))
        self.assertFalse(technical('20 cm de largeur'))

    def test_domain_titles_keep_manufacturer_technology_units_and_pipe_separators(self):
        phrases = {'Lave-linge séchant': ['Washer-dryer', 'Waschtrockner']}
        product = {'name': 'LG Lave-linge séchant | 9/6kg | AI DD™ | F964R71WRST', 'brand': 'LG'}
        self.assertEqual(title_translation(product, phrases, 0), 'LG Washer-dryer | 9/6kg | AI DD™ | F964R71WRST')
        self.assertEqual(title_translation(product, phrases, 1), 'LG Waschtrockner | 9/6kg | AI DD™ | F964R71WRST')

    def test_glossary_does_not_partially_translate_a_prose_title(self):
        product = {'name': 'Onda Red. une cheminée artisanale de 95 cm de haut.', 'brand': 'Design italien'}
        self.assertIsNone(title_translation(product, {'cheminée': ['Fireplace', 'Kamin']}, 0))


if __name__ == '__main__':
    unittest.main()
