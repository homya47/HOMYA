# HOMYA — masquage des photos sous le seuil et descriptifs associés

**État historique de ce lot.** Le [lot photos suivant](/Users/akm/Documents/ChatGPT/HOMYA/docs/INCREMENT-PHOTOS-PRODUITS.md), autorisé en DECISION-015, rétablit les 35 galeries et les 53 vignettes. Les 379 fichiers originaux insuffisants et les 153 descriptifs restent masqués et conservés. Les preuves ci-dessous décrivent le résultat du masquage avant ces remplacements.

7 octobre 2026 — incrément local demandé : « Peux tu modifier les 379 images afin que les photos respectent les règles ? Sinon les masquer avec leurs descriptions respectives. » DECISION-014, HOMYA, dans le [registre central](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), ajoutée avec sauvegarde et relue. Aucun registre parallèle.

## Choix exécuté et portée

**Fait observé :** les 379 photos signalées ont moins de 500 pixels sur au moins un axe. **Information manquante :** originaux authentiques de meilleure résolution. **Repli autorisé exécuté :** masquage des photos et descriptifs associés. Un agrandissement seul ne constitue pas une garantie de qualité ; aucun traitement génératif, réencodage, modification de pixels ou suppression de fichier effectué.

Le masquage est effectué côté serveur, dans la projection publique du catalogue, et concerne :

- Les 379 photos : cartes, image principale affichée, miniatures et commandes de galerie ne référencent plus les fichiers exclus.
- Le descriptif des 153 fiches ayant au moins une photo exclue, ainsi que sa reprise dans la meta description. La meta description utilise alors le nom du produit.
- Les légendes et attributs alternatifs liés aux photos exclues, absents du HTML. Les galeries restantes sont renumérotées de 1 au nombre de photos affichées.

Les noms, prix courants, références, caractéristiques, variantes et photos restantes restent consultables. Les produits ne sont pas retirés. Les fichiers originaux restent sur disque, à leurs chemins existants ; ce masquage d’affichage n’est ni une suppression ni une restriction d’accès direct aux fichiers. Les descriptions françaises et leurs traductions restent conservées dans les archives/dictionnaires privés.

**53 cartes** n’affichent plus leur photo principale ; **35 fiches** n’ont aucune photo restante et présentent l’état traduit « Image indisponible ». Aucune légende « Image 0 sur 0 » ni commande de galerie inutile n’est affichée. Les 18 autres fiches dont la photo principale était exclue disposent de photos secondaires conservées dans leur galerie.

Le filtrage des images locales vérifie les dimensions réelles du fichier : au moins 500 pixels sur chaque axe, au plus 64 mégapixels et 16 000 000 octets, format reconnu et cohérent avec l’extension. Le résultat est mémorisé pendant la requête. Les fichiers absents ou aux en-têtes invalides sont également écartés ; la liste CSV n’est pas une dépendance du fonctionnement des pages. Elle sert de référence indépendante au contrôle de cet incrément.

Une nouvelle photo qui passe les contrôles techniques peut rétablir l’affichage ; le descriptif revient lorsque toutes les photos de la fiche passent ces contrôles. Une revue visuelle de la nouvelle photo et de sa correspondance au produit reste nécessaire. Aucun uploader, administration, flux Google ou synchronisation ajouté par ce lot.

## Sources officielles et limites

Sources consultées le **7 octobre 2026** :

| Source | Portée / fait publié |
|---|---|
| [Merchant Center product data specification update 2026](https://support.google.com/merchants/answer/16989427?hl=en), publié le 14 avril 2026 | Minimum annoncé de 500 × 500 pour les images principales et additionnelles ; application au 31 janvier 2027 |
| [Lien image — image_link](https://support.google.com/merchants/answer/6324350?hl=fr) | Recommandation de 1500 × 1500 ; plafonds de 64 MP et 16 Mo ; formats/extension cohérents ; représentation fidèle et qualité du produit ; photo principale requise pour une offre |

**Interprétation HOMYA :** appliquer dès maintenant le seuil annoncé à l’affichage local et conserver les originaux. **Recommandation :** obtenir de vraies photos plus grandes, prioritaires pour les fiches sans photo.

Le masquage ne rend pas une fiche sans image admissible à Merchant Center. Les **35 fiches sans photo affichable devront être exclues d’un futur flux**, tant qu’une photo valide et pertinente n’est pas disponible. Aucun flux n’existe dans le périmètre actuel ; aucune soumission ou validation Google effectuée. Les formats/dimensions contrôlés ne certifient pas le contenu visuel, l’absence de filigrane/promotion, l’exactitude des caractéristiques ou l’éligibilité complète des produits. Le site reste local et `noindex, nofollow`.

## Exigences et critères d’acceptation

| Référence | Critère observable | Résultat |
|---|---|---|
| REQ-006, AC-006 — fiche et galerie | Aucun fichier de la liste de remplacement dans la projection publique ; photos restantes conservées et légendes cohérentes | 379 images écartées dans chaque langue ; galeries partielles et sélection contrôlées |
| REQ-003/004 — langues et états vides | Messages FR/EN/DE ; pas de débordement sur les vues mobile/desktop contrôlées | Tests des trois langues et navigateur, contrôles ciblés réussis |
| REQ-005 — catalogue et recherche | Produits, recherche, pagination, prix et identités conservés | 3 709 produits et fiches contrôlés dans chaque langue ; régression automatisée réussie |
| REQ-026 — métadonnées | Descriptif masqué absent du HTML et de la meta description | Tests HTTP ; meta description remplacée par le nom |
| REQ-034 — vérification et réversibilité | Fichiers et données source conservés, restauration possible après remplacement valide | Empreintes des 379 photos exclues concordantes ; sources de tests inchangées et restauration testée |

## Fichiers modifiés et contrôles exécutés

- `app/Services/Catalogue.php` : filtrage serveur des photos et descriptifs, cache de lecture des dimensions par requête.
- `resources/views/catalogue/product.blade.php` : état traduit de contenu temporairement indisponible et légende uniquement pour une galerie non vide.
- `lang/{fr,en,de}/catalogue.php` : nouveau message traduit.
- `tests/Feature/CatalogueTest.php` : limites à 499/500 pixels sur les deux axes, galerie partielle/vide, format incohérent, maintien des données, retrait de description/meta et restauration après remplacement valide. Images de test synthétiques générées uniquement dans le dossier temporaire des tests.
- `scripts/verify_localized_catalogue.php` : comparaison indépendante à l’audit des photos, vérification des descriptifs masqués, cartes, champs conservés et empreintes des originaux.
- README et présente recette ; [rapport de masquage](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-masquage.json) et rapports de navigateur. Aucune dépendance Composer/npm ajoutée.

Sauvegarde des fichiers avant changement : `/private/tmp/homya-avant-masquage-cm4l4mz5`. Registre sauvegardé dans `/private/tmp/homya-registre-avant-decision-014.md`. Dépôt initial sans commit et fichiers non suivis, aucun historique supprimé.

Contrôles réellement exécutés :

- Tests ciblés catalogue : **13 tests, 277 assertions**, réussis ; suite complète : **23 tests, 405 assertions**, réussis, sans base ni migration.
- Pint, build Vite, compilation puis purge des vues Blade : réussis.
- Vérificateur applicatif : **3 709 produits et fiches × 3 langues**, 379 images et 153 descriptions masquées, 35 galeries vides, 53 cartes sans image principale ; prix/références/identités/variantes/champs conservés. Les **379 empreintes des fichiers exclus** correspondent à l’audit ; aucune erreur.
- Navigateur : Bronpi NICOL, galerie vide en FR/EN/DE et mobile allemand ; Anthramix, galerie partielle d’une image ; Bronpi ARUS-N, trois photos restantes sur six et sélection de la deuxième avec légende « Image 2 sur 3 ». **7 observations**, aucun débordement horizontal sur les vues contrôlées. Viewport réinitialisé. [Rapport navigateur](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-masquage-navigateur.json), [capture desktop FR](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-masquage-desktop-fr.png), [capture mobile DE](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-masquage-mobile-de.png).

## État et prochaine étape

Masquage opérationnel et vérifié localement. Remplacement des vrais originaux **À confirmer**, revue visuelle complète **À vérifier**. Les contrôles de traduction, règles commerciales, prix instantanés et préparation Google conservent les limites du lot précédent. Aucun changement XAMPP, SQL, paiement, compte externe ou production. Le présent lot actualise l’affichage décrit dans `INCREMENT-LANGUES-IMAGES.md` ; les fichiers restent conservés.
