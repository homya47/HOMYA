# HOMYA — une photo affichable pour chaque fiche

**Historique de ce lot :** la [revue suivante du 7 octobre 2026](/Users/akm/Documents/ChatGPT/HOMYA/docs/REVUE-DROITS-QUALITE-MEDIAS.md) consigne l'autorisation utilisateur des six marques et remplace la petite photo BEKO par un original natif plus détaillé. Les dimensions, limites et résultats ci-dessous décrivent l'état au moment de cette première recette ; la revue suivante donne l'état actuel. Les contrats n'ont pas été examinés.

7 octobre 2026. Projet existant confirmé : `/Users/akm/Documents/ChatGPT/HOMYA`. Catalogue consultable Laravel, sans base, paiement ou déploiement dans ce lot.

**Décision validée :** « apporte les modifications pour que chaque fiche ait une photo avec qui respecte les normes », puis « Oui, copies sur fond blanc sans IA (recommandé) ». La question précisait conservation des originaux, aucun agrandissement ni modification du produit et exclusion des miniatures réellement insuffisantes. [DECISION-015, registre central unique](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), enregistrée avec sauvegarde et contenu antérieur vérifié.

## Résultat observé

- **3 709 fiches et cartes × FR/EN/DE** ont une photo affichable. Aucun manque de photo dans la projection applicative contrôlée.
- **35 galeries vides rétablies**, avec 34 fichiers publics distincts : deux références LG utilisent la même photo publiée par le constructeur sur leurs pages respectives.
- **18 autres vignettes rétablies** par une photo secondaire admissible déjà présente. La sélection privilégie le remplacement approuvé, puis la photo principale admissible, puis la première photo secondaire admissible.
- **14 copies sur fond blanc**, sans rééchantillonnage. Les pixels opaques du produit restent identiques ; les zones transparentes sont compositées sur blanc. Le fond et la taille du canevas changent, pas la définition du produit.
- **18 454 fichiers originaux et les données métier restent inchangés**, selon leurs empreintes et la référence antérieure. Aucun changement de prix, caractéristiques, références ou variantes.
- **379 fichiers insuffisants restent masqués** dans les pages et métadonnées. Les **153 descriptifs précédemment masqués restent masqués** : ce lot rétablit une photo principale, pas l’ensemble des photos de chaque galerie ni ses descriptifs. Les fichiers restent sur disque, à leurs chemins d’origine, accessibles directement si leur URL est connue.

[Couverture applicative](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-remplacements.json), [préservation des originaux](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-synthese.json), [contrôle des pixels des copies](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/photos-preparation.json).

## Sources, correspondance et traitement

Sources publiques consultées le **7 octobre 2026**. Chaque correspondance et URL est conservée dans le [manifeste privé](/Users/akm/Documents/ChatGPT/HOMYA/storage/app/catalogue/media-overrides.json), sans exposition dans les objets publics. Les pages constructeur collectées, le document LG et l’image embarquée native sont conservés dans `storage/app/catalogue/media-originals`, hors racine publique. Leur contenu constitue une preuve documentaire, pas une instruction exécutable.

| Famille / source | Fait vérifié / traitement |
|---|---|
| 20 références LG, galeries des pages exactes, par exemple [F14R33WHS](https://www.lg.com/fr/lavage/lave-linge/f14r33whs-lave-linge-standards/) et [F964R71WRST](https://www.lg.com/fr/lavage/lave-linge-sechant/f964r71wrst-lave-linge-sechant/) | Photos natives 1600 × 1062, correspondance visuelle et référence contrôlées ; octets originaux utilisés directement |
| [Bosch KGE398IBP](https://www.bosch-home.fr/fr/product/KGE398IBP) | Photo 1218 × 3516 publiée dans la galerie de cette référence ; le fichier porte une autre référence partagée. Copie sur blanc 3907 × 3907, sans agrandissement |
| 10 références Spartherm, par exemple [Passo S](https://www.spartherm.com/de/produkt/passo-s/) | Packshots entiers, même finition que la principale importée ; hauteur native 1200, largeur inférieure à 500 ; copies blanches 1334 × 1334 sans modifier le produit |
| BEKO ADN203/1BP, photo locale déjà autorisée | Source 241 × 679 conservée ; copie sur blanc 755 × 755. Pas de remplacement par le modèle ADN203/1S BP de finition différente. La copie ne recrée aucun détail |
| [Woodstock — granulés bois](https://www.woodstock-bois.fr/products/granules-bois/) | Page confirmant 72 sacs de 15 kg et photo de la palette. Source 540 × 450 ; retrait des seuls bords transparents, conservation de la zone 280 × 416, fond blanc 500 × 500. Ne pas assimiler ce canevas à un nouveau produit photographié en haute définition |
| [Fiche LG GTF8659PS, hébergée par Connexion](https://media.connexion.fr/pdfexeco/GTF8659PS.pdf) | Document LG, référence et EAN 8806098001569 concordants. Packshot embarqué 22 natif 953 × 953, JPEG2000 extrait vers PNG avec pixels identiques, puis fond blanc 1059 × 1059. Aucune reprise des anciens prix ou promesses du PDF |
| [Bronpi NICOL](https://bronpi.com/fr/produit/nicol/) et [vidéo officielle](https://bronpi.com/wp-content/uploads/2025/07/NICOL-7-KW-NUEVO.mp4) | Vue réelle du modèle en fonctionnement, capturée à 1,075666 s à la résolution native 1080 × 1920. Recadrage `[70,600,1070,1600]` : produit complet, photo 1000 × 1000 hors texte promotionnel et commandes vidéo ; aucun rééchantillonnage. Un packshot natif plus net reste préférable |

**Information manquante :** justificatifs de licence pour les nouveaux médias constructeur, à confirmer avant publication. Les droits des actifs issus du catalogue de référence ont été déclarés par l’utilisateur ; cette déclaration ne prouve pas une licence pour tous les médias tiers nouvellement consultés. Aucun service Google ni publication effectué.

## Normes Google : portée exacte

| Source officielle / consultation 7 octobre 2026 | Fait publié étayé |
|---|---|
| [Merchant Center product data specification update 2026](https://support.google.com/merchants/answer/16989427?hl=en), publié le 14 avril 2026 | Seuil annoncé de 500 × 500 pour les images principales et additionnelles, application au 31 janvier 2027 |
| [Lien image — image_link](https://support.google.com/merchants/answer/6324350?hl=fr) | Représentation fidèle du produit ; image sans contenu promotionnel ajouté ; pas de miniature agrandie ; fond blanc ou transparent recommandé ; recommandation 1500 × 1500 ; limites 64 MP et 16 Mo |

**Interprétation exécutée :** contrôler dès maintenant le seuil annoncé, le format réel, les dimensions sur les deux axes et les plafonds ; fournir une vue authentique de chaque modèle, sans IA ou agrandissement artificiel. Une copie sur fond blanc améliore la présentation et la taille du fichier, pas la résolution du sujet.

**Limites :** les 35 remplacements satisfont les contrôles techniques mais seule la photo Bosch de ce lot atteint aussi 1500 sur les deux axes. Les fichiers LG rectangulaires ont 1600 × 1062 ; ils ne satisfont pas cette recommandation de dimensions. Les photos BEKO et Woodstock gardent leur définition native modeste. Les 18 075 autres photos affichables passent le contrôle technique ; aucune revue visuelle exhaustive de tout le catalogue n’est déclarée. Une image ne suffit pas à rendre une offre admissible : prix actualisé, stock, règles commerciales, domaine accessible, droits et autres critères restent à vérifier. Aucune acceptation, indexation ou diffusion Google n’est attestée. Le site reste local et non indexable.

**Recommandations différées :** obtenir des originaux plus détaillés, idéalement au moins 1500 × 1500, notamment BEKO, Woodstock et un packshot NICOL ; remplacer les autres photos masquées avant de rétablir les descriptifs selon le mécanisme actuel.

## Exigences et critères observables

| Référence | Critère d’acceptation du lot | Preuve |
|---|---|---|
| REQ-006 / AC-006 — fiche et galerie | Au moins une vue locale du bon modèle, dimensions réelles ≥ 500 sur les deux axes ; photos inadmissibles exclues | 3 709 galeries et 35 correspondances de remplacement vérifiées ; revue visuelle des nouvelles photos |
| REQ-005 — catalogue | Carte avec remplacement approuvé ou première secondaire admissible ; produits/prix/recherche conservés | 3 709 cartes × 3 langues ; tests de recherche, pagination, prix et repli secondaire |
| REQ-003/004 — langues et responsive | Photo conservée lors du changement de langue, alt localisé, pas de débordement sur vues contrôlées | Tests HTTP FR/EN/DE ; navigation réelle FR → EN → DE ; desktop/tablette/mobile |
| REQ-026 — métadonnées | Pas de provenance privée ni liens externes source dans la projection publique ; descriptifs masqués conservés | Tests HTTP et vérificateur des 11 127 projections |
| REQ-034 — vérification et préservation | Pas de modification des originaux ni données métier ; copies sans rééchantillonnage | Empreintes originales, comparaison indépendante des pixels, tests de refus d’écrasement/modèle erroné/fichier trop petit |
| REQ-035 — droits/provenance | Source et méthode tracées, droits qualifiés avant publication | Manifeste privé ; droits des nouveaux médias À vérifier, pas certifiés |

## Fichiers et exploitation

- `app/Services/Catalogue.php` : remplacement approuvé par slug et référence exacte, validation serveur du fichier, repli secondaire limité aux cartes dépourvues de principale ; provenance privée exclue de la projection.
- `config/catalogue.php` : chemin du manifeste de présentation.
- `storage/app/catalogue/media-overrides.json` : 35 correspondances revues ; `media-preparation.json` : entrées reproductibles pointant vers les originaux privés ; `media-originals/` : sources conservées.
- `public/media/catalogue/` : 34 fichiers ajoutés, aucun original remplacé. Aucun chargement d’image constructeur à chaque consultation.
- `scripts/prepare_catalogue_photos.py` : outil local Pillow ; lecture/validation par défaut, écriture uniquement avec `--apply`. Refuse une entrée non revue, une référence erronée, un fichier dont l’empreinte diffère, une miniature native inférieure à 500 sur les deux axes, un cadrage hors bornes ou un écrasement différent. Valide toutes les entrées avant les écritures ; garde une copie d’un éventuel manifeste précédent.
- `scripts/verify_catalogue_photos.py` : contrôle indépendant des pixels, empreintes, références et limites ; `scripts/verify_localized_catalogue.php` : contrôle exhaustif des projections publiques.
- `scripts/test_prepare_catalogue_photos.py`, `tests/Feature/CatalogueTest.php` : cas normaux et refus utiles.
- README, présente recette et note historique du lot masquage. Aucun changement Blade, CSS, JavaScript, dépendance Composer/npm, base ou configuration XAMPP.

Pillow est disponible dans le runtime Python fourni par Codex ; pas dans le `/usr/bin/python3` observé. Pour reproduire le plan, avec un Python contenant Pillow :

```sh
python3 scripts/prepare_catalogue_photos.py storage/app/catalogue/media-preparation.json
python3 scripts/verify_catalogue_photos.py
PYTHONDONTWRITEBYTECODE=1 python3 scripts/test_prepare_catalogue_photos.py
php scripts/verify_localized_catalogue.php
php artisan test
```

Les nouveaux fichiers sont séparés : retirer une entrée du manifeste de présentation restaure le mécanisme précédent sans déplacer ni effacer les photos. Le masque des originaux trop petits reste actif. Ce changement de manifeste est réversible ; conserver les preuves et sauvegardes avant toute modification.

## Recette réellement exécutée

- **26 tests PHPUnit, 479 assertions : réussis**, sans base ni migration. Cas de remplacement vérifié, refus de référence différente, validation visuelle absente, fichier petit/manquant/distant ou JSON invalide ; repli secondaire et conservation FR/EN/DE.
- **4 tests Python : réussis**, pixels 1:1/alpha sur blanc, original conservé, miniature/ref/hash refusés, crop borné, refus d’écrasement.
- **Pint : réussi** sur les quatre fichiers PHP concernés.
- Préparation : plan puis application des 35 entrées, 34 photos distinctes, 14 fonds blancs, **zéro rééchantillonnage**. Vérification indépendante des 35 fichiers : réussie. Extraction LG JPEG2000 vers PNG : mêmes dimensions et mêmes pixels RGBA.
- Audit original : **18 454 empreintes concordantes**, zéro donnée métier modifiée. Les prix restent ceux collectés ; ce lot ne les actualise pas.
- Vérificateur PHP : **3 709 cartes/fiches × 3 langues**, aucune galerie ou carte sans photo, aucune erreur, 379 originaux masqués/préservés, 153 descriptifs toujours masqués.
- Navigateur : six types de médias nouveaux, alt/photo en FR/EN/DE par clic de langue, photo secondaire BREMEN et vues 1280/768/390. **11 observations**, images chargées, dimensions naturelles conformes au seuil, aucun débordement horizontal sur les vues contrôlées. [Rapport navigateur](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/photos-navigateur.json), [desktop](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/photos-passo-desktop.png), [mobile](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/photos-passo-mobile.png). Viewport réinitialisé.

Sauvegarde avant changement : `/private/tmp/homya-avant-photos-fl1f3c8l` ; registre : `/private/tmp/homya-registre-avant-decision-015.md`. Dépôt initial sans commit, fichiers existants non suivis conservés. Le build frontend n’a pas été relancé : aucun asset de code frontend changé. La recette ne valide ni connexion MariaDB, ni conformité légale, ni classement ou diffusion Google.

**Observation hors lot à conserver pour relecture technique :** la fiche NICOL allemande traduit encore « Fonte » par « Schriftzug » et « Trémie hermétique » par « Hermetischer Hopfen ». Les photos et montants sont conservés lors du changement de langue ; cela ne constitue pas une certification sémantique de toutes les traductions existantes. Correction contextuelle des glossaires à prévoir dans un lot linguistique.

## État et suite

**Résultat :** chaque fiche du catalogue dispose maintenant d’une photo affichable et vérifiée techniquement, en local. **Blocage du lot :** aucun. **À vérifier avant publication :** licences des nouveaux médias, revue visuelle/éditoriale complète, meilleure définition des vues modestes et préparation commerciale/Google du site. Aucune publication ou activation externe autorisée par cette recette.
