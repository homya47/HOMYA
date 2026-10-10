# HOMYA — contenus multilingues et contrôle des images

Recette locale du 7 octobre 2026. Décision validée : DECISION-013, HOMYA, dans le [registre central](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). Choix explicite pour les photos : « Conserver les fichiers et signaler les images à remplacer ». DECISION-012 reste applicable à l’identité publique et aux copies locales des originaux.

## Résultat et exigences

| Exigence / parcours | Comportement livré et critère observé | Statut et limite |
|---|---|---|
| REQ-003, AC-003, parcours catalogue → fiche → changement de langue | FR/EN/DE pour noms, catégories, descriptions, libellés/valeurs des caractéristiques et titres des variantes ; même fiche et contexte de navigation conservés | Implémenté pour les pages existantes du catalogue consultable. Relecture éditoriale complète **À confirmer** ; parcours d’achat non implémenté |
| REQ-005, recherche → langue → résultats | Recherche dans les textes de la langue choisie et dans les noms/catégories français pour conserver une recherche existante ; filtres, tri, page conservés | Tests HTTP et recherche navigateur « poele » en FR/DE/EN réussis |
| REQ-006/007, fiche → galerie / variantes | Photos locales, alt principal localisé, dimensions HTML explicites, galerie conservant les dimensions lors de la sélection ; titres de variantes traduits et SKU conservés | Consultation seulement ; les caractéristiques métier restent celles de l’archive |
| REQ-002, identité publique | HOMYA dans le contenu public, aucun lien source/CDN ni historique de prix présenté comme HOMYA | Contrôle des projections des 3 709 fiches × 3 langues ; provenance privée conservée |
| REQ-004, écran mobile/tablette/desktop | Composants Blade/CSS existants conservés ; absence de débordement observée sur les vues contrôlées | Recette ciblée à 390/768/1280 px, pas de certification WCAG exhaustive |
| REQ-026, variantes linguistiques | `html lang`, titres/descriptions localisés, alternates `hreflang` réciproques correspondant au contexte | Préparation locale ; `noindex, nofollow` maintenu, domaine public/indexation/sitemap différés |
| REQ-030/034 | Laravel et environnement conservés ; tests sans base | Aucun changement XAMPP, SQL, migration, tag, compte Google ou déploiement |

Une traduction manquante, invalide ou devenue insuffisante pour un nouveau texte déclenche un état indisponible **503 traduit**, sans afficher silencieusement le contenu français. Une fiche inconnue conserve son contexte linguistique et répond **404**. Les pointeurs de description source non résolus (`$…`, 22 fiches) sont masqués et signalés ; aucun descriptif inventé.

## Méthode et conservation des données

Les dictionnaires EN/DE sont préparés hors ligne à partir de l’archive privée française : Argos/OPUS-MT, modèle français→anglais 1.9 puis anglais→allemand 1.3. Les traductions sont complétées par un glossaire relu, des termes métier et des corrections de contresens observés, notamment chauffage et lavage. Aucun texte n’a été envoyé à une API de traduction ; aucune dépendance de traduction n’a été ajoutée au runtime Laravel, à Composer ou à npm.

Outils de préparation isolés dans `/private/tmp/homya-translation-runtime` : CTranslate2 4.8.2 et SentencePiece 0.2.2. Modèles téléchargés depuis l’index officiel Argos, conservés hors du dépôt dans `/private/tmp/homya-models`. Le README du modèle français→anglais indique CC-BY 4.0, auteurs Jörg Tiedemann et Santhosh Thottingal, travaux OPUS-MT. Le README du modèle anglais→allemand utilisé ne précise pas sa licence : **À vérifier avant redistribution des poids**. Les poids ne sont pas livrés avec le site. Index consulté : <https://github.com/argosopentech/argospm-index>, le 7 octobre 2026 ; portée : disponibilité des modèles, pas certification de traduction.

Les contrôles comparent les nombres et références alphanumériques des textes, les montants en centimes, références, identités, états, univers, SKU et nombres de champs/photos à l’archive. Ils ne prouvent pas à eux seuls la fidélité sémantique des unités et caractéristiques. Les marques constructeur et les technologies nommées restent conservées. Les corrections du glossaire portent sur la langue, sans modifier l’archive métier.

**11 927 textes distincts couverts dans chacune des deux langues, aucun texte manquant ni écart de nombre/référence détecté.** Le [rapport de couverture](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/langues-couverture.json) et la [liste de relecture](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/traductions-a-relire.csv) sont reproductibles. Les candidats comprennent des noms propres légitimes et des traductions segmentées pour protéger les chiffres ; la liste n’est ni une liste certaine d’erreurs ni une revue éditoriale exhaustive. Relecture professionnelle des textes techniques **À confirmer avant publication**.

## Photos : observations et décisions

| Observation mesurée | Preuve / implication |
|---|---|
| 18 454 originaux locaux, 3 450 463 473 octets au total | Fichiers conservés, aucune modification de pixels, format, résolution ou compression ; ce total n’est pas le poids d’une page |
| 379 fichiers sous 500 px sur au moins un axe, concernant 153 produits | [Liste à remplacer](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-a-remplacer.csv), avec référence, position, dimensions, chemin et motif |
| Dont 53 photos principales | Priorité recommandée pour obtenir les originaux plus grands |
| 18 075 fichiers passent les seuils techniques contrôlés | Dimensions, format décodable, 64 MP maximum et 16 Mo maximum ; pas une validation visuelle ou Google |
| 11 069 fichiers atteignent 1500 × 1500 sur les deux axes | Recommandation de qualité, distincte du minimum annoncé |
| Empreintes des 3 709 fichiers présents au départ inchangées ; données métier de l’archive inchangées | [Synthèse images](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-synthese.json), comparaison au [point de départ](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/avant-langues-images.json) |

**Décision validée :** conserver ces images et signaler les remplacements. **Recommandation :** obtenir les vrais originaux de meilleure résolution, en commençant par les 53 photos principales. Aucun agrandissement artificiel ni IA générative utilisé. **Inconnu :** disponibilité des nouveaux originaux. Un contrôle visuel de la correspondance produit/variante, des éventuels textes promotionnels ou filigranes et de la qualité de toutes les photos reste **À vérifier**. Droits de réutilisation déclarés par l’utilisateur, sans certification juridique indépendante.

## Sources officielles, consultées le 7 octobre 2026

| Source et URL | Portée et fait publié | Interprétation / recommandation HOMYA |
|---|---|---|
| [Merchant Center product data specification update 2026](https://support.google.com/merchants/answer/16989427?hl=en), publié le 14 avril 2026 | Images principales et additionnelles : minimum annoncé de 500 × 500 pour toutes catégories/méthodes ; application le 31 janvier 2027 | Auditer chaque axe, conserver les fichiers et lister ceux sous le seuil ; aucune optimisation automatique de compte présumée |
| [Image link — image_link](https://support.google.com/merchants/answer/6324350?hl=en) ; [version officielle accessible lors de la re-vérification](https://support.google.com/merchants/answer/6324350?hl=zh-Hans) | Recommandation de 1500 × 1500 ou plus ; plafond 64 MP et 16 Mo ; formats et qualité/correspondance produit ; extension correspondant au format | Audit technique séparé de la revue visuelle et de l’éligibilité. La version anglaise a expiré lors des deux dernières tentatives ; la version officielle chinoise confirme ces seuils |
| [Tell Google about localized versions of your page](https://developers.google.com/search/docs/specialty/international/localized-versions) | `hreflang` : chaque version se référence et référence les autres, URLs complètes et liens réciproques ; le contenu principal compte pour la langue | Contenu réellement localisé et alternates ajoutés ; la version locale reste non indexable |

Ces faits publiés n’établissent ni acceptation Merchant Center, ni indexation, ni conformité complète du catalogue HOMYA. Aucune soumission ou modification de compte Google effectuée.

## Fichiers et recette exécutée

- Application : `app/Services/Catalogue.php`, `app/Http/Controllers/CatalogueController.php`, `config/catalogue.php`, vues catalogue/composants/layout, `lang/{fr,en,de}/catalogue.php`, `resources/js/app.js`.
- Contenus : `storage/app/catalogue/localizations/{en,de}.json`, glossaire et phrases dans `scripts/`. Les galeries privées ont été enrichies de leurs chemins locaux ; les photos originales sont dans `public/media/catalogue/`.
- Préparation/contrôles : `scripts/localize_catalogue.py`, `refine_catalogue_localizations.py`, `verify_catalogue_localizations.py`, `verify_localized_catalogue.php`, `audit_catalogue_images.py`, adaptation ciblée du collecteur de médias et tests.
- Recette : `tests/Feature/CatalogueTest.php`, rapports/captures dans `output/qa-catalogue`, README actualisé. Sauvegarde préalable : `/private/tmp/homya-avant-identite-yxbin55s`. Dépôt toujours sans commit ; aucun fichier existant supprimé.

Contrôles effectivement exécutés :

- `php artisan test` : **20 tests, 345 assertions, réussis**, sans base ni migration.
- `vendor/bin/pint --test`, `npm run build`, `node --check resources/js/app.js`, compilation puis purge des vues Blade : réussis.
- Tests Python : **5 tests de localisation** et **7 tests de collecte**, réussis ; compilation syntaxique des scripts réussie.
- `python3 scripts/verify_catalogue_localizations.py` : couverture et garde des nombres/références réussies pour EN/DE.
- `php scripts/verify_localized_catalogue.php` : **3 709 index et 3 709 fiches dans chaque langue**, prix/identités/variantes/champs/galeries/publication de provenance contrôlés, aucun échec. [Rapport application](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/langues-application.json).
- Audit Pillow en lecture seule de toutes les photos et comparaison au point de départ ; contrôle source `scripts/verify_catalogue.py` réussi. Aucune certification des allégations métier source.
- Navigateur réel : fiche LG FR/EN/DE, caractéristiques corrigées, recherche « poele » FR→DE→EN conservée, accueil dans les trois langues ; galerie Onda et changement de photo contrôlés. **12 observations enregistrées**, aucun débordement horizontal ni mention/lien source sur les vues contrôlées. [Recette navigateur](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/langues-navigateur.json), [capture allemande desktop](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/langues-produit-de-desktop.png), [capture anglaise mobile](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/langues-produit-en-mobile.png). Viewport temporaire réinitialisé.

Les documents des lots antérieurs décrivent l’état de leur propre recette. Le présent document remplace leurs limites devenues historiques concernant le français public en EN/DE et les galeries distantes.

## Points ouverts et prochaine étape

Relecture éditoriale technique complète et obtention des photos de remplacement avant publication. Les prix restent un instantané, sans stock opérationnel ni synchronisation. Contradictions source et données absentes restent à vérifier avec le fournisseur/constructeur. Optimisation des poids, hébergement/domaine public, import MariaDB, vente et services Google nécessitent leurs phases propres ; aucun de ces éléments n’est déclaré opérationnel par cette recette.
