# Logo de paiement HOMYA — état au 10/10/2026

Demande initiale : afficher les logos Visa, Mastercard et CB. Instruction ultérieure de l’utilisateur : supprimer les logos de cartes et n’afficher que le logo du virement instantané. L’instruction la plus récente remplace le choix précédent.

Le composant partagé `resources/views/components/storefront-footer.blade.php` affiche désormais un pictogramme HOMYA original `public/media/payments/instant-transfer.svg`, accompagné d’un libellé traduit en FR/EN/DE. Les anciens logos de réseaux restent archivés dans leurs fichiers locaux mais ne sont plus affichés. Le visuel représente un virement bancaire instantané ; il ne constitue pas une marque bancaire ni une preuve que le RIB HOMYA est configuré ou que le paiement est actif.

## Sources officielles consultées le 08/10/2026

- [Visa — fichiers de marque](https://globalclient.visa.com/brand-mark-ty) : source des fichiers numériques ; archive `https://usa.visa.com/content/dam/VCOM/Brand/visa-brand-mark-digital-2025.zip`, membre `Visa Brand Mark DIGITAL 2025/Visa Brand Mark DIGITAL/VBM_digital_2025/blue/VBM_bluRGB_2025.svg`.
- [Mastercard — médiathèque](https://newsroom.mastercard.com/media-library/) : kit officiel du symbole ; archive `https://newsroom.mastercard.com/news/media/clbpi0i2/mastercard_symbol.zip`, membre `Mastercard_Symbol/Artwork/svg (for website use)/mc_symbol.svg`. Les liens SVG du Brand Center renvoyaient une erreur HTTP ; aucun fichier n’a été recréé ou recoloré.
- [Cartes Bancaires — téléchargement du logo CB](https://www.cartes-bancaires.com/mediatheque/telechargement-logo-cb/) : archive `https://www.cartes-bancaires.com/wp-content/uploads/2024/07/Logo-CB-1.zip`, membre `Logo-CB/Logo_CB_SMALL/Digital/LOGO_CB_BBR_rvb_SMALL.svg`.

## Limites et contrôle

Le pied de page affiche uniquement le symbole HOMYA de virement instantané. Les instructions bancaires ne sont disponibles qu’après configuration et validation du RIB professionnel dans l’environnement privé. Aucune coordonnée bancaire ni promesse de disponibilité du virement instantané n’est codée dans ce pictogramme.

Contrôles initiaux du 08/10/2026 : build Vite et anciens tests Visa/Mastercard/CB. Contrôle courant après remplacement : test ciblé FR/EN/DE (1 test, 404 assertions), test commerce/CSRF (29 tests, 704 assertions), routes de paiement carte absentes, SVG servi en HTTP 200 et rendu du footer local vérifié dans le navigateur. Les preuves de l’installation de ce pictogramme sont dans le répertoire de sauvegarde indiqué par la recette locale XAMPP, aucun logo officiel ancien n’a été supprimé.
