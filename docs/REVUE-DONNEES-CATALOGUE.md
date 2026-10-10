# HOMYA — revue détaillée des données avant import

7 octobre 2026. Revue locale de l'archive, sans modification des produits, traductions ou médias, sans création de base, import ou migration. Les modules E-commerce, Database Engineering, Security et Quality ont été consultés. Les consignes du dépôt et le registre central HOMYA restent applicables.

## Résultat et preuves

**Observations :** 3 709 produits, 65 catégories, cinq univers, 41 977 lignes de caractéristiques, 92 variantes pour 26 produits, 18 454 associations de galerie. Références produit présentes et distinctes dans cet instantané ; cette observation ne définit pas une règle métier d'unicité future. Toutes les sommes produit sont des entiers positifs en EUR et concordent entre index et fiches. Les prix datent de la collecte, du 07/10/2026 à 13:04:05–13:49:28 UTC ; aucune actualisation commerciale effectuée.

**Liste détaillée : 627 lignes concernant 479 produits distincts.** Les identifiants `AN-…` dépendent du produit, de la catégorie de signalement et du champ ; ils restent identiques pour le même objet de revue. Chaque ligne contient référence, champ, valeurs/positions observées, statut, recommandation et fichier source. Il s'agit de signalements et d'inconnues, pas de 627 erreurs métier certifiées.

- [Liste CSV à filtrer](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/anomalies-detaillees.csv).
- [Liste JSON conservant exactement les valeurs](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/anomalies-detaillees.json).
- [Synthèse et comptages](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/synthese.json).
- [Empreintes des 3 714 fichiers source contrôlés](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/empreintes-sources.json).

Les cellules CSV pouvant être interprétées comme formules sont préfixées pour lecture dans un tableur. Le JSON et l'archive conservent les valeurs originales. Les rapports sont des fichiers de revue internes ; ils ne doivent pas être placés dans la racine web.

## Classification et traitement recommandé

Priorités proposées selon les dépendances, sans calendrier validé : **P1** avant vente/publication d'une information technique ; **P2** relecture ou enrichissement. Aucune anomalie de structure bloquant la préparation de l'archive n'a été détectée. Les responsabilités métier doivent être attribuées par l'exploitant HOMYA.

| Groupe / code du rapport | Lignes / produits concernés | Qualification | Traitement proposé / preuve de clôture |
|---|---:|---|---|
| `distinct_specification_values` | 298 / 279 | À vérifier, P1 si donnée essentielle ; même libellé avec valeurs différentes | Préserver chaque ligne ; confronter la référence exacte au document constructeur et consigner source/date/valeur retenue. Une différence textuelle ne démontre pas toujours une contradiction physique |
| `possible_multivalued_specification` | 48 / 48 | Hypothèse de champ multivalué, P2 ; zones d'installation | Confirmer les zones autorisées et la façon de les présenter comme liste. Aucune première/dernière valeur retenue automatiquement |
| `missing_specifications` | 98 / 98 | Information manquante, P1 selon famille | Obtenir une fiche technique et identifier les champs indispensables à la vente ; aucune puissance, dimension ou certification inventée |
| `empty_filtered_description` | 23 / 23 | Information manquante, P2 | Description vide après filtrage des engagements vendeur ; rédiger un contenu factuel puis le valider en FR/EN/DE |
| `unresolved_description` | 22 / 22 | Marqueur technique observé, P2 | Obtenir le texte réellement référencé. Les marqueurs tels que `$7e` restent archivés, sans être publiés comme prose par le service actuel |
| `variant_business_data_missing` | 92 / 26 | Information manquante, P1 avant achat | Titres/SKU présents ; prix, stock et options opérationnelles non fournis dans ces entrées. Confirmer chaque unité vendable ; aucun prix parent hérité ni stock nul inventé |
| `semantic_translation_review` | 46 / 37 | À vérifier en contexte, P1 pour information technique | Usages de `Fonte → Schriftzug` et `Trémie hermétique → Hermetischer Hopfen` relevés dans le dictionnaire allemand. Relecture technique avant correction ; `Gusseisen` est une proposition pour le matériau Fonte, pas un remplacement global appliqué |

Les 319 fiches signalées par la collecte pour valeurs multiples correspondent à **346 groupes de libellés** dans cette revue : 298 autres groupes et 48 groupes de zones. Certains produits cumulent les deux. Les trois flags initiaux (319/98/23) concernent **417 fiches distinctes** ; les nouvelles pistes de revue élargissent le total à 479. Additionner les colonnes produits compterait certains produits plusieurs fois.

### Exemples vérifiables

- [Référence 111700002](/Users/akm/Documents/ChatGPT/HOMYA/storage/app/catalogue/products/chauffage-111700002-poeles-a-granules-etanches.json) : autonomie minimale publiée à 8 puis 12 heures ; zones 1, 2 et 3 sur trois lignes. Le premier champ demande confrontation, le second peut être une liste admissible. Aucune correction appliquée.
- [Palette charbon, référence 26329](/Users/akm/Documents/ChatGPT/HOMYA/storage/app/catalogue/products/1-2-palette-500-kg-charbon-20-30-anthracite-superieur.json) : description source `$7e`, marqueur conservé dans l'archive et exclu de la prose publique.
- [Bronpi NICOL](/Users/akm/Documents/ChatGPT/HOMYA/storage/app/catalogue/products/bronpi-nicol.json) : traduction allemande à relire de la fonte de la chambre de combustion et de la trémie. Références et caractéristiques françaises restent conservées.

## Médias et relecture éditoriale : preuves distinctes

Les 379 originaux insuffisants et les 153 descriptifs masqués ne sont pas rétablis par cette revue. La [liste des médias à remplacer](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-a-remplacer.csv), la [couverture des photos affichables](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/images-remplacements.json) et la [revue des droits/médias](/Users/akm/Documents/ChatGPT/HOMYA/docs/REVUE-DROITS-QUALITE-MEDIAS.md) restent les preuves de ces phases. Les produits concernés peuvent aussi figurer dans les 479 fiches de cette revue.

La [liste éditoriale des traductions à relire](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/traductions-a-relire.csv) signale aussi les textes inchangés ou segmentés. Ces candidats ne sont pas automatiquement des traductions erronées. Les 11 927 textes actuellement utilisés sont couverts en EN/DE ; les dictionnaires contiennent chacun 11 939 entrées, dont des entrées supplémentaires à conserver dans l'import. Couverture et protection des nombres ne certifient pas le sens de toutes les traductions.

## Inconnues transversales et portée de l'import

**Décisions validées :** Laravel/MariaDB XAMPP, catalogue consultable, conservation de la provenance et des données absentes/divergentes, droits déclarés. [Registre central unique, section HOMYA](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), notamment DECISION-007/010/011/016. La nouvelle demande autorise cette revue et le plan ; elle ne valide aucun modèle relationnel ni opération en base.

**Informations manquantes :** source de stock et d'actualisation, prix HOMYA vendables et fiscalité, unités/conditionnements, règles de variantes, justificatifs de licences, responsable métier. Ces inconnues n'empêchent pas de proposer l'archivage local des observations ; elles empêchent de déclarer les produits prêts à vendre. Propositions à faire valider dans le [plan d'import](/Users/akm/Documents/ChatGPT/HOMYA/docs/PLAN-IMPORT-XAMPP.md).

## Contrôles exécutés

- Quatre tests sur fixtures synthétiques : valeurs divergentes/multivaluées préservées, refus des clés JSON dupliquées, prix de variantes non inventés, marqueur de prose et cellules CSV protégés.
- Revue des 3 709 fiches : index/fiches/prix/devise rapprochés, comptages par groupe/produit, absence de conflit de nom de catégorie, empreintes identiques avant/après les rapports.
- Vérificateurs existants de structure du catalogue et de couverture linguistique exécutés séparément ; résultats dans `output/qa-catalogue`.
- Le diagnostic Laravel refuse l'accès avant SQL : accès applicatifs non renseignés. phpMyAdmin affiche MariaDB 10.4.28 via socket local ; cela ne prouve pas une connexion Laravel.

**Limites :** revue automatisée exhaustive des formes de données listées, pas vérification constructeur exhaustive des 479 produits ni validation de règles de vente. Aucun fichier applicatif ou donnée source modifié. Build, tests de base et restauration non exécutés ; aucune migration écrite ou lancée. Aucune anomalie déclarée corrigée par la simple génération du rapport.
