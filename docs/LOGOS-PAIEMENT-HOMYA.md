# Logos de paiement HOMYA — 08/10/2026

Demande utilisateur : afficher les logos sous « Carte bancaire ». Choix explicite : Visa, Mastercard et CB. Le bloc conserve « Moyens de paiement prévus » en FR/EN/DE ; aucune acceptation effective ou connexion de paiement n’est déclarée.

Le composant partagé `resources/views/components/storefront-footer.blade.php` affiche trois fichiers locaux, sans appel externe, script ou lien tiers. Les SVG officiels sont conservés sans modification ; les styles partagés assurent des proportions constantes et le retour à la ligne sur petit écran. Textes alternatifs : Visa, Mastercard, CB — Cartes Bancaires.

## Sources officielles consultées le 08/10/2026

- [Visa — fichiers de marque](https://globalclient.visa.com/brand-mark-ty) : source des fichiers numériques ; archive `https://usa.visa.com/content/dam/VCOM/Brand/visa-brand-mark-digital-2025.zip`, membre `Visa Brand Mark DIGITAL 2025/Visa Brand Mark DIGITAL/VBM_digital_2025/blue/VBM_bluRGB_2025.svg`.
- [Mastercard — médiathèque](https://newsroom.mastercard.com/media-library/) : kit officiel du symbole ; archive `https://newsroom.mastercard.com/news/media/clbpi0i2/mastercard_symbol.zip`, membre `Mastercard_Symbol/Artwork/svg (for website use)/mc_symbol.svg`. Les liens SVG du Brand Center renvoyaient une erreur HTTP ; aucun fichier n’a été recréé ou recoloré.
- [Cartes Bancaires — téléchargement du logo CB](https://www.cartes-bancaires.com/mediatheque/telechargement-logo-cb/) : archive `https://www.cartes-bancaires.com/wp-content/uploads/2024/07/Logo-CB-1.zip`, membre `Logo-CB/Logo_CB_SMALL/Digital/LOGO_CB_BBR_rvb_SMALL.svg`.

## Limites et contrôle

Présentation locale des réseaux souhaités uniquement. Le prestataire, les réseaux réellement acceptés et leurs conditions contractuelles restent à confirmer avant l’ouverture des paiements. CB conditionne le téléchargement à un contrat d’acceptation et l’utilisation au respect de sa charte ; l’existence de ce contrat HOMYA n’a pas été fournie et doit être vérifiée avant publication. Cette préparation ne certifie aucun droit ou contrat.

Contrôles réalisés : build Vite réussi ; test existant du pied de page FR/EN/DE réussi (1 test, 389 assertions). Les preuves d’installation et de recette locale sont conservées dans `output/qa-payment-logos-20261008/`.
