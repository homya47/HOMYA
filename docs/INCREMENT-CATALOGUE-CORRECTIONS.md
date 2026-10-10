# HOMYA — catalogue consultable, corrections et pagination

7 octobre 2026 — recette locale du lot autorisé, sans déploiement.

## Périmètre et décisions

Source et droits déclarés : HOMYA DECISION-002/010 ; catalogue complet consultable avec prix/images, recherche/filtres/fiches et corrections : DECISION-011 dans le [registre central](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), relu avant exécution. Amélioration de pagination demandée explicitement dans le fil. Aucun registre parallèle.

La base cible reste MariaDB XAMPP. Le lot consulte une archive de collecte publique en lecture seule ; aucune base créée, migration exécutée, connexion applicative revendiquée ni service XAMPP modifié. Aucun panier, paiement, compte, message externe, avis HOMYA fictif ou promesse commerciale ajouté.

## Corrections et comportement observable

| Constat | Action HOMYA | Vérification |
|---|---|---|
| Source : références répétées entre pages, contenu absent sur des pages proposées | Inventaire complété depuis le sitemap officiel, déduplication par slug ; tri déterministe avec départage par nom/slug | Rapport source séparé et couverture finale dans `storage/app/catalogue/report.json` |
| Ancienne interface sans catalogue utilisable | Accueil, univers et en-tête conduisent au catalogue ; recherche activée si des fiches vérifiées existent | Parcours navigateur réel, tests de régression accueil |
| Parcours de pagination à rendre plus pratique | 24 produits/page ; précédent/suivant, pages voisines, première/dernière, ellipses, accès direct ; filtres/langue conservés ; numéro hors plage redirigé vers la dernière page | Tests sur 11 pages et navigation réelle filtrée, accès direct au clavier |
| Images manquantes ou échouées | Emplacement stable, texte explicite ; première image locale, galerie navigable au clavier ; sélection annoncée ; vignette échouée garde son numéro | Galerie Samsung réelle, tests de données manquantes ; gestion d’échec incluse dans JS |
| Caractéristiques répétées ou divergentes | Doublons exacts retirés, valeurs distinctes pour un même libellé conservées et signalées ; unités non converties arbitrairement | Tests de collecte hors réseau et anomalies tracées par fiche |
| Contenus non traduits | Interface FR/EN/DE ; maintien de la même fiche/recherche/page ; contenu source français explicitement signalé en EN/DE | Matrices responsive et navigation réelle |
| Erreurs et états vides | Fiche inconnue 404, archive absente/corrompue 503, filtres invalides 422, aucun résultat avec remise à zéro | Tests HTTP sans base |

L’amélioration concerne HOMYA. Le défaut interne DAMDAM n’est pas diagnostiqué avec certitude et aucune correction source n’a été effectuée.

## Exigences et portée d’acceptation

| Exigences | Résultat pour cet incrément |
|---|---|
| REQ-001/005, PAR-01, AC-005, UX-C07 | Inventaire source, navigation, recherche accent-insensible par nom/marque/référence/catégorie, filtres univers/catégorie/marque/état/prix, tri et pagination ; scénarios normaux, vides et invalides testés |
| REQ-006, AC-006, UX-C08 | Fiches avec galerie, prix courant et ancien prix publiés par la source, référence, caractéristiques, date/provenance ; valeurs source conservées. Validation indépendante des caractéristiques divergentes **À confirmer** |
| REQ-003/004 | Libellés FR/EN/DE, responsive et clavier vérifiés ; traductions des contenus produits **Inconnues / à produire**. Pas de certification exhaustive d’accessibilité |
| REQ-007 | Variantes source consultables lorsqu’elles existent ; règles d’achat/quantités/unités opérationnelles **Décision nécessaire**, non implémentées |
| REQ-026 | Titres, descriptions, h1 et liens de contexte ; noindex/nofollow local conservé. SEO public, sitemap HOMYA et ouverture à l’indexation différés |
| REQ-030/034 | Laravel conservé, tests sans base et régression ; MariaDB XAMPP reste cible, import et connexion applicative **À confirmer** séparément |

## Fichiers et dépendances

- `app/Services/Catalogue.php`, `config/catalogue.php` : lecture et filtrage de l’archive, montants en centimes entiers ; aucune base alternative.
- `app/Http/Controllers/CatalogueController.php`, `HomeController.php`, `routes/web.php` : validation serveur, réponses et navigation.
- `resources/views/catalogue/*`, composants carte/image/pagination, layout et accueil : UI Blade partagée ; `resources/css/app.css` et `resources/js/app.js` : responsive, clavier, galerie et replis.
- `lang/{fr,en,de}/catalogue.php`, descriptions d’accueil : libellés traduits ; aucune API de traduction.
- `scripts/collect_damdam.py`, `scripts/test_collect_damdam.py`, `scripts/verify_catalogue.py` : bibliothèque standard Python, accès public borné, reprise des fichiers validés, provenance, tests sans réseau et vérification locale des archives/médias.
- `storage/app/catalogue/*`, `public/media/catalogue/*` : archive source filtrée et photos principales autorisées. Aucune nouvelle dépendance npm/Composer.
- `tests/Feature/CatalogueTest.php`, `HomeNavigationTest.php` : scénarios catalogue et adaptation de la régression à la nouvelle navigation.

Sauvegarde préalable des vues/langues/routes/tests : `/private/tmp/homya-avant-catalogue-gshs8_b8`. Dépôt initial sans commit et fichiers non suivis ; aucun historique supprimé.

## Contrôles exécutés

- `php artisan test` : **16 tests, 265 assertions, réussis**, sans création de base ni migration ; les tests catalogue interdisent une connexion DB.
- `PYTHONDONTWRITEBYTECODE=1 python3 scripts/test_collect_damdam.py` : **7 tests réussis**, parser, centimes, champs manquants, divergences, exclusion stock/avis/promesses, hôtes refusés, faux succès HTTP 200 et classement des cheminées dans le chauffage.
- `vendor/bin/pint --test`, syntaxe Python via `compile`, `node --check resources/js/app.js`, `npm run build` : réussis. Compilation des vues Blade et purge exécutées avec succès.
- Navigateur : recherche Samsung, passage FR → DE, pagination de recherche « poele » avec tri, page 2 puis accès direct à 4 au clavier, langue EN avec page conservée, menu Enter/Escape avec retour du focus, galerie image 2 par Space, fiche allemande.
- **36 vues** sur 320/390/768/1280 px, trois langues : accueil, catalogue et fiche Samsung. Aucun débordement horizontal, id dupliqué, h1 absent/doublé ou clé de traduction affichée détecté. Matrices et captures dans [output/qa-catalogue](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue). Vérifications ciblées, pas audit universel navigateur/WCAG/Core Web Vitals.
- Contrôle local `python3 scripts/verify_catalogue.py` : **réussi**, couverture exacte du sitemap, unicité des slugs, index/fiches/prix concordants, montants entiers, provenance, absence de champs stock/clients/commandes/avis, photos locales présentes et empreintes/taille vérifiées. [Rapport d’intégrité](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/integrite-donnees.json).
- Après fin de collecte : le navigateur affiche **3 709 produits / 155 pages**, sans bandeau de collecte partielle. Page 155 : **13 cartes**, « suivant » absent ; page 154 : **24 cartes**, aucun lien produit commun avec la page 155. Accès direct à 77 effectué au clavier. Pagination mobile 390 px sans débordement, cibles mesurées à 44 px minimum ; [capture desktop](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/pagination-desktop-fr.jpg) et [capture mobile](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/pagination-mobile-fr.jpg). Viewport temporaire réinitialisé et catalogue laissé ouvert.

## Décisions et inconnues

Les prix sont un instantané source daté, pas une synchronisation continue ni un engagement d’achat HOMYA. La collecte reprend les fiches locales existantes ; son renouvellement complet reste à organiser. Les stocks et avis source ne sont pas importés. Les univers sont une classification par catégories observées ; les catégories originales sont conservées.

Les images de galerie secondaires dépendent encore du CDN DAMDAM. Les médias locaux sont des originaux ; optimisation/miniatures, contrôle exhaustif des galeries et stockage de production à préparer avant publication. Les descriptions sont des extraits descriptifs filtrés, pas une reproduction intégrale des textes commerciaux source.

Les écarts de caractéristiques restent **À vérifier** avec une source métier/constructeur ; aucune conversion d’unités, certification, disponibilité, taxe ou règle de livraison inventée. Traductions produits et import XAMPP restent ouverts. L’architecture opérationnelle de vente, les services Google et l’hébergement ne sont pas activés par ce lot.

Un signalement de valeurs distinctes n’établit pas à lui seul une erreur physique : deux unités différentes peuvent exprimer la même mesure. Les valeurs sont conservées pour revue, plutôt que remplacées par une interprétation non validée.

## Couverture finale

Collecte terminée le **2026-10-07 à 13:49:29 UTC** : **3 709/3 709 fiches**, 3 709 slugs uniques, aucune erreur d’extraction ; inventaire recontrôlé ensuite depuis le sitemap public et rapproché exactement avec les URLs source des fiches. [Rapport de collecte](/Users/akm/Documents/ChatGPT/HOMYA/storage/app/catalogue/report.json), [inventaire](/Users/akm/Documents/ChatGPT/HOMYA/storage/app/catalogue/inventory.json).

**3 709 photos principales locales**, **18 454 URLs de galerie** conservées, **26 produits avec variantes**. Les photos locales représentent **961 124 674 octets**, mesurés ; ce poids est celui de l’ensemble des originaux collectés, pas celui d’une page. Les images de catalogue utilisent un chargement différé et un emplacement stable ; aucune mesure de Core Web Vitals ou optimisation complète des originaux revendiquée.

Répartition HOMYA dérivée des catégories observées : 52 bois/combustibles, 3 086 chauffage, 253 électroménager, 317 mobilier et 1 télévision. Les catégories originales restent intactes. **32 fiches** de cheminées initialement regroupées avec le mobilier ont été reclassées dans le chauffage ; anciennes/nouvelles valeurs tracées dans la normalisation du rapport de collecte. Aucun prix ou caractéristique changé par ce reclassement.

Points source restant **À vérifier** : 319 fiches ont plusieurs valeurs distinctes pour un même libellé, 98 n’exposent pas de caractéristiques et 23 nécessitent une revue de description après exclusion des engagements vendeur. Ces groupes peuvent se recouper. Ces signalements sont visibles sur les fiches et ne certifient pas une erreur métier.

Le lot est consultable localement. La vente, l’import XAMPP, les traductions produits et la validation métier des écarts restent hors de cette recette.
