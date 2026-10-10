# HOMYA — complément OP-04 : migrations, importeur et lecteur SQL

Version de référence 0.1 — 08/10/2026 — **DÉVELOPPEMENT AUTORISÉ DANS DECISION-028 ; RÉSULTATS AU §11**. Les sections 1–10 conservent la préparation historique. [Plan exact approuvé](../output/qa-import/op04-complement-plan-autorise.md).

Demande : préparer le complément OP-04. Cette phase produit uniquement ce plan et ses preuves d'inspection. Aucun code applicatif, fichier de migration, SQL, accès dédié ou service XAMPP utilisé/modifié. Le premier incrément est clos dans DECISION-027 ; son accord exclut expressément le code décrit ici. Le [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md) est inchangé ; aucune décision supplémentaire validée ni registre parallèle.

Références : [MLD v0.2](MODELE-LOGIQUE-ARCHIVE-HOMYA.md), [plan physique et opérations §10](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md), [premier incrément OP-04 §9](OP-04-CONNEXIONS-GARDES-CAPSULE.md#9-premier-incrément-réalisé--état-après-accord), [preuve de clôture](../output/qa-import/op04-synthese-verification.json). Traçabilité : TECH-02/03/06/07/09, IMP-05/06/08/09/10, CON-001 à CON-008. Les documents initiaux conservent leurs observations historiques ; l'état courant est décrit ci-dessous.

## 1. État vérifié et autorité

| Qualification | Élément et portée |
|---|---|
| Décisions validées | DECISION-017/018 : plan et quatre relations ; DECISION-019 : types physiques, journal Laravel, six rôles et capacité proposée ; DECISION-025/026 : comptes et CREATE/ALTER ciblés ; DECISION-027 : gardes/connexions/capsule, sans migrations/importeur/lecteur SQL |
| Fait local actuel | Laravel installé 13.35.0, PHP CLI 8.5.11 et services existants relus ; catalogue public encore sur fichiers, projection HOMYA, langues, masquages, filtres et tri dans Catalogue.php |
| Fait local actuel | database/migrations contient seulement le squelette users/cache/jobs ; absence de migrations catalogue. phpunit.xml conserve homya_test_not_created et les suites sans base |
| Preuves recontrôlées le 08/10 | Empreintes de tous les fichiers de la clôture OP-04 concordantes ; manifeste privé conforme à sa clé ; 3 717 JSON sources/rapports et leurs copies concordants en taille/SHA-256 ; registre concordant avec la clôture |
| Fait historique SQL | Dernier contrôle OP-03b : XAMPP MariaDB 10.4.28, socket/datadir exacts, port3307, 16 Mio, FK/CHECK actifs, deux bases vides ; deux rôles schema avec CREATE/ALTER sur cinq tables chacun. Lecteurs/importeurs sans DML. Ce n'est pas un contrôle SQL du 08/10 |
| Fait historique médias | Capsule copiée et entièrement rapprochée le 07/10 : 22 239 fichiers, 3 477 829 369 octets, 18 522 médias uniques. Les binaires médias ne sont pas re-hachés dans cette préparation |
| À vérifier | Contraintes et DDL réel, droits effectifs au moment d'exécuter, transport PDO, concurrence, consommation mémoire/durée et restauration isolée |
| Recommandation soumise | Réaliser maintenant le code et les tests sans base décrits ci-dessous ; conserver chaque exécution XAMPP dans son opération distincte |

Capsule candidate unique : `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809`, sous `storage/app/private/catalogue-archives/<clé>/`. Manifeste de 3 550 149 octets. Cette préparation n'autorise ni nouvelle copie ni remplacement de capsule. La future simulation d'import relira la capsule entière ; elle ne déduira pas une vérification fraîche des médias de ce document.

## 2. Périmètre de développement proposé

**Inclus après accord :** quatre migrations ciblées, préparation du DDL sans connexion, assemblage des lignes depuis la capsule, importeur protégé, lecteur SQL isolé, simulations et tests synthétiques sans base ; préparation séparée de la recette MariaDB, sans la lancer. Aucune dépendance Composer/npm supplémentaire.

**Préservé :** Catalogue.php, routes, vues, styles, recherche/tri/pagination, contenus/prix/photos/traductions, sources et capsule, .env applicatif, six fichiers d'accès, connexions par défaut, migrations du squelette et phpunit.xml actuel. Les services existants ne seront étendus que pour les contrôles explicitement identifiés. Aucune connexion SQL automatique lors d'une simulation ou du démarrage web.

**Exclus :** SQL réel, même SELECT/diagnostic ; création base/compte/table, GRANT, lancement de migrations ou d'import, restauration, activation du lecteur sur le site, édition de secrets, réglage/redémarrage XAMPP, export réel, paiement/stock/commande, réseau ou déploiement. Écrire une migration et l'exécuter sont deux autorisations différentes.

## 3. Migrations ciblées et DDL à produire

Répertoire futur : `database/migrations/catalogue/`. Quatre fichiers ordonnés : lots, catégories, produits, traductions. Les types sont ceux de TECH-02 validé, sans colonne auto-ID ni timestamps Eloquent ajoutés.

| Table | Colonnes et contraintes à reprendre exactement |
|---|---|
| archive_lots | lot_key PK CHAR64 ASCII/ascii_bin ; format_version INT signé positif (=1) ; manifest_document/archive_document JSON obligatoires ; load_state VARCHAR16 ASCII, prepared/loading/verified/failed ; verification_document JSON nullable ; registered_at DATETIME(6) UTC obligatoire ; verified_at DATETIME(6) UTC nullable |
| archive_categories | PK(lot_key,slug) ; slug VARCHAR221 ASCII/ascii_bin ; source_name TEXT utf8mb4_bin non null ; FK lot RESTRICT |
| archive_products | PK(lot_key,slug) ; category_slug même domaine que slug ; index_position INT signé positif, UNIQUE(lot_key,index_position) ; reference/source_name/source_brand/source_condition TEXT non null ; universe VARCHAR32 ASCII ; price_cents INT signé positif ; previous_price_cents INT signé nullable ; currency CHAR3 ASCII EUR ; index_document/detail_document JSON obligatoires ; FK lot et FK(lot_key,category_slug) → catégorie du même lot, RESTRICT |
| archive_translations | PK(lot_key,locale,source_key) ; locale CHAR2 ASCII en/de ; source_key CHAR64 ASCII/ascii_bin ; source_text/translated_text TEXT utf8mb4_bin non null ; source_position INT signé positif ; UNIQUE(lot_key,locale,source_position) ; FK lot RESTRICT |

Toutes les tables : InnoDB, utf8mb4/utf8mb4_bin par défaut ; clés textuelles ASCII explicitement définies. Index produit(lot_key,category_slug) pour sa FK ; aucun index spéculatif de recherche/prix. Noms de contraintes stables et distincts dans le schéma, ASCII ≤64 caractères. CHECK pour hexadécimal minuscule64, slugs du contrat, format1, positions positives, prix courant positif, EUR, en/de, cinq univers de cette capsule (`appliances`, `firewood`, `furniture`, `heating`, `televisions`) et états. `verified` exige preuve JSON non SQL-NULL et verified_at ; avant verified ces deux colonnes sont SQL-NULL. Refuser un JSON racine/type incompatible au contrôle applicatif. Aucune relation ancien/nouveau prix, marque non vide ou disponibilité commerciale inventée.

Les CHECK JSON effectifs seront explicitement identifiés dans le DDL et contrôlés dans SHOW CREATE TABLE lors d'OP-05. Le type JSON de MariaDB est un alias LONGTEXT ; JSON_VALID ne détecte pas la fidélité, les doublons de clés ou un lot complet. Les migrations ne désactivent jamais FK/CHECK/modes stricts.

**Préparation hors SQL :** compiler les définitions avec la grammaire Laravel installée et une connexion factice dont tout accès PDO échoue ; produire la liste CREATE/ALTER exacte et ses empreintes. Les tests ne passent pas par `artisan migrate --pretend`. Toute compilation nécessitant une requête doit être remplacée par une définition explicite, pas une connexion réelle.

**Exécution future seulement OP-05 :** recontrôler identité/état/objets/droits, créer le journal standard migrations, faire accorder ses SELECT/INSERT au seul homya_test_schema puis lancer seulement les quatre fichiers approuvés. Enveloppe de commande ciblée, rôle schema imposé, chemins/empreintes et absence de dump revérifiés ; pas de migration générale ni découverte implicite du squelette. Le code `down()` refuse une suppression ; aucun DROP/fresh/refresh/rollback ou seeder. En cas de DDL partiel, arrêter et inventorier pour une reprise autorisée ; aucune compensation destructive. Le DDL ne bénéficie pas du rollback de l'import.

## 4. Contrat de données depuis la capsule

L'importeur travaille uniquement sur `capsule/files/…`, jamais sur les fichiers source courants ou une URL. CatalogueCapsule valide ensemble fermé, hashes et chemins avant/après chargement. Réutiliser PrivateLocalFiles ; aucun nouveau résolveur permissif. Exposer seulement une lecture bornée de documents vérifiés, sans rendre les méthodes privées accessibles arbitrairement.

**Recommandation de représentation interne v1 :** archive_document contient `index`, `inventory`, `media_overrides`, `localization_metadata` (en/de, tous les champs sauf strings) et `review_reports` (synthese.json, anomalies-detaillees.json, empreintes-sources.json). Cette enveloppe sera fixée dans le code et les tests après accord ; les documents inclus restent complets. format_version=1 reste la version du manifeste, sans version business supplémentaire.

| Source | Assemblage et rapprochement |
|---|---|
| manifest.json | Conserver ses octets exacts dans manifest_document ; lot_key SHA-256 exact et comparaison des octets, pas seulement du hash |
| index.products et fiches | index_position = position1..N ; stocker entrée compacte complète et fiche complète séparément ; colonnes redondantes égales aux champs partagés, slug et référence compris |
| Catégorie de chaque produit | Une ligne par slug exact ; un nom différent pour le même slug bloque la vérification ; aucune catégorie orpheline dans un lot vérifié |
| Dictionnaires en/de | Toutes les entrées dans leur ordre, hash du texte UTF-8 source exact ; comparer aussi le texte pour toute collision ; métadonnées intactes au lot ; FR reste la source |
| Inventaire/overlay/rapports | Documents complets au lot ; signalements privés conservés, sans marquer une anomalie résolue ni certifier traduction/image/Google |

**Fidélité JSON :** étendre StrictJson sans changer son API actuelle : un mode de décodage/comparaison conserve objet/tableau (`{}` ≠ `[]`), types scalaires (`1` ≠ `"1"`), null/absence, chaînes vides, clés exactes et ordre des tableaux. L'ordre des clés d'objet n'est pas une identité sémantique ; l'ordre des dictionnaires reste enregistré par source_position. Refuser les clés dupliquées, dont formes échappées/numeriques équivalentes. Réencoder les documents avec préservation des types ; pas de conversion numérique automatique, trim ou normalisation Unicode. Le manifeste exige toujours l'égalité binaire ; les originaux conservent leurs octets sur disque.

**Capacité :** vérifier longueur en octets de chaque TEXT (65 535), limites INT signé, UTF-8 et dimensions du domaine avant SQL ; ne pas tronquer. Valider documents et budget des messages comprenant SQL, paramètres et protocole. Les 4 730 180 octets mesurés historiquement pour un candidat archive_document ne garantissent pas la taille de cette nouvelle enveloppe ni son transport avec le manifeste. Mesurer à nouveau en simulation ; transport réel à éprouver OP-06 sous les limites global/session16Mio. Aucun accroissement automatique, découpage logique silencieux ou BLOB image.

Comptages de référence, non plafonds métier : 3 709 produits, 65 catégories, 41 977 caractéristiques, 92 variantes, 18 454 associations de galerie originales, 23 878 traductions (11 939/langue, entrées inutilisées comprises), 35 overlays, 627 signalements. Conserver les six marques vides et les anciens prix null ; une variante sans prix reste un document signalé. Total attendu pour ces quatre tables : un lot + 65 catégories + 3 709 produits + 23 878 traductions = 27 653 lignes, sans inclure le journal.

## 5. Importeur, concurrence et erreurs

Commande prévue `homya:catalogue-import` : simulation par défaut, capsule et lot explicites. Elle vérifie et assemble sans charger d'accès, créer de connexion ou écrire en base ; sorties de compteurs/erreurs expurgées, hors capsule. L'option d'exécution future exige rôle import, cible autorisée, clé attendue et accès privé explicite ; aucune exécution SQL permise sous ce complément. Pas de target/default, mot de passe ou approbation persistante implicites. OP-06 vise test, OP-09 local ; l'opérateur doit disposer de l'accord propre au lot/cible.

**Recommandation proportionnée pour le premier import :** enregistrement initial `prepared` avec documents complets ; puis une transaction InnoDB unique de chargement des enfants et de vérification. Chaque écriture acquiert le même verrou de ligne parent via FOR UPDATE dans cette transaction. Passage loading, INSERT catégories/produits/traductions, rapprochement intégral SQL/capsule et dernier contrôle fichiers, puis preuve/date/passsage verified dans le même commit. Le lot ne devient jamais lisible partiellement. Les INSERT paramétrés sont dimensionnés ; pas d'upsert/INSERT IGNORE/REPLACE, UPDATE enfant, DELETE, DDL ou désactivation de contraintes. Pas de retry automatique de la transaction.

**Idempotence/conflit :** unicité lot_key pour l'enregistrement initial, comparaison du manifeste complet en cas de collision. Un lot verified identique doit être intégralement rapproché et déclaré déjà vérifié sans réécriture ni nouvelle date. Un état prepared/loading/failed préexistant provoque un arrêt diagnostiqué ; aucune reprise automatique ou commande de reprise permissive dans ce premier code. Après inspection, la procédure exacte de reprise sera soumise séparément, conformément au modèle qui l'autorise uniquement explicitement. Ne pas remplacer une capsule divergente.

**Échec :** rollback des INSERT de la transaction, aucune activation. Après rollback, marquer failed seulement dans une transaction distincte sous le verrou commun, si manifeste/état et absence d'enfants permettent de prouver qu'aucun autre importeur n'a progressé ; sinon conserver l'état constaté et rapporter une situation à inspecter. Perte de connexion/timeout/commit indéterminé : arrêter et demander un rapprochement, jamais conclure à une absence d'écritures ni rejouer aveuglément. Un lot vérifié par un autre processus ne doit jamais être rétrogradé. Attente/deadlock bornés et erreurs contrôlées à éprouver sur MariaDB ; pas de réglage global ou verrou de toute la base.

Le gel verified est une obligation de l'importeur et de tous les futurs écrivains coopérants, pas une garantie d'immuabilité imposée par les seules FK/permissions. La durée du verrou, mémoire et paquet doivent être mesurés en recette ; aucune estimation de performance ou besoin de traitement en tâche de fond présumés.

## 6. Lecteur SQL sans bascule

CatalogueArchiveStore utilise Query Builder sur une connexion reader explicite et un lot explicite. Pas d'Eloquent supposant une PK simple, compte schema/import, choix automatique du dernier lot ou valeur par défaut. Vérifier lot verified, manifeste/preuve/date cohérents et absence de données incomplètes ; inconnue/incomplétude = erreur contrôlée, pas un catalogue vide présenté comme réussi. Toutes les requêtes sont bornées par lot, clés/identifiants/colonnes fixes et paramètres liés ; aucun fragment SQL fourni par l'utilisateur.

Reconstituer index/meta/inventaire/overlay/rapports depuis archive_document ; produits dans l'ordre index_position, fiche via(lot,slug), dictionnaires avec métadonnées et source_position. Le rapprochement exhaustif assure concordance des documents et projections SQL. Les données brutes privées restent hors des réponses publiques ; les futurs adaptateurs utiliseront la projection Catalogue actuelle, sans nettoyer les originaux ni changer filtres, tri, langues ou règles images.

Le service reste isolé et exercé avec doubles dans OP-04. Aucune liaison dans un provider, route, contrôleur ou Catalogue.php ; aucun réglage .env/source active/lot choisi. Une recette CLI pourra lire avec homya_test_reader après OP-06. La configuration et l'adaptation runtime web restent OP-10, après test du retour fichiers ; le garde CLI existant ne sera pas assoupli pour charger des accès schema/import en HTTP. Pas de repli automatique masquant une panne SQL.

## 7. Fichiers et séquence de développement soumis

| Fichiers prévus | Responsabilité et borne |
|---|---|
| database/migrations/catalogue/ — quatre nouveaux fichiers | Schéma ordonné conforme §3, up protégé/rôle schema, down refusant DROP ; aucune exécution |
| app/Services/CatalogueArchiveSchema.php ; app/Console/Commands/PrepareCatalogueSchema.php | Définitions communes et liste DDL compilée sans PDO ; préparation de l'enveloppe ciblée pour OP-05, sans création du journal ni lancement du migrateur ici |
| app/Services/CatalogueArchivePayload.php | Lecture de capsule/assemblage §4 et rapprochement type/ordre/colonnes/comptages ; conserver les sources, jamais télécharger |
| app/Services/CatalogueArchiveImporter.php ; app/Console/Commands/ImportCatalogueArchive.php | Simulation sans accès ; orchestration future des transactions/verrous et erreurs §5, jamais exécutée ici contre SQL |
| app/Services/CatalogueArchiveStore.php | SELECT et reconstruction §6, injection explicite, sans activation web |
| app/Services/ArchiveDatabaseGuard.php | Ajouter contrainte de rôle/purpose spécifique et refus des opérations en contexte non conforme ; conserver diagnostic/auth-only actuel et protections des secrets |
| app/Services/StrictJson.php ; CatalogueCapsule.php | Extension minimale pour types JSON et lecture bornée de documents de capsule vérifiés ; conserver signatures/comportements existants |
| tests/Unit/, tests/Feature/, tests/Support/ — tests dédiés | DDL compilé/connexions factices/payload/import/lecteur simulés ; aucun RefreshDatabase, accès réel, migrateur ou SQL |
| tests/MariaDB/, phpunit.mariadb.xml — nouveaux, isolés | Scénarios de recette explicitement désactivés par défaut et double garde d'exécution/cible test ; pas de découverte par phpunit.xml existant, purge ni fixture destructive automatique |
| docs/ et output/qa-import/ | DDL exact, compte rendu code/tests, plan d'OP-05 puis recette détaillée ; jamais dans le manifeste de capsule |

Les noms de fichiers de migration seront fixés avant édition et consignés dans le rapport ; la conception ne prescrit pas un nouvel ensemble d'abstractions génériques. Si la compilation DDL exige une organisation différente, justifier un changement ciblé sans élargir les objets/rôles autorisés.

Séquence après accord : (1) consigner et relire la portée dans le seul registre central ; (2) définitions/DDL et tests de contraintes sans PDO ; (3) payload/comparaison JSON et simulation sur fixtures puis capsule réelle sans SQL ; (4) importeur/lecteur avec doubles ; (5) recette MariaDB isolée préparée mais non exécutée ; (6) régression sans base, syntaxe/Pint, conservation des témoins ; (7) présenter OP-05 avec code/DDL/empreintes/commande exacts. Arrêter et expliquer toute incompatibilité, sans changer les décisions physiques ou privilèges pour contourner un refus.

## 8. Critères observables et recette différée

Tous les critères ci-dessous sont **proposés / non exécutés**. Priorité indispensable : intégrité, isolation et conservation conditionnent chaque étape suivante. Aucun résultat des doubles ne sera présenté comme une preuve MariaDB.

| ID | Dépendances / exigences | Acceptation à démontrer |
|---|---|---|
| OP04-CAC01 | TECH-02/06, CON-001 à005 | DDL compilé exact : quatre tables seulement, types/clés/contraintes/nullabilité §3 ; aucun PDO/journal/squelette/dump/DROP. Recette physique réelle OP-05 |
| OP04-CAC02 | TECH-03, gardes OP-04 | Refus avant connexion sur rôle/cible/accès/socket inapproprié ; simulation n'appelle ni accès privés ni DatabaseManager ; aucune donnée sensible en erreurs |
| OP04-CAC03 | IMP-05, CON-006/008 | Payload fidèle, compteurs et ordre exhaustifs ; fixtures objets/tableaux/absences/nombres/clés Unicode/doublons ; capsule réelle simulée sans mutation, médias hachés avant/après simulation |
| OP04-CAC04 | CON-005/006 | Dépassement TEXT/INT/paquet, JSON/média invalide, incohérence index/fiche/catégorie ou collision arrêtent avant INSERT ; ancien prix null/marque vide/signalements autorisés conservés selon leur domaine |
| OP04-CAC05 | IMP-08, CON-007 | Doubles prouvent verrou parent avant chaque groupe d'écritures et vérification avant commit ; aucun UPDATE enfant/DDL/delete ; vraie transaction/rollback/concurrence à prouver OP-06 |
| OP04-CAC06 | IMP-08/09, CON-001/007 | Même lot verified relu sans réécriture ; divergence/lot incomplet/reprise non autorisée refusés ; échec/commit incertain ne rétrograde jamais un autre succès. Épreuve à deux processus OP-06 |
| OP04-CAC07 | TECH-07/09, IMP-06/10 | Lecteur isolé reconstitue valeurs/types/ordre/dictionnaires ; refuse lot incomplet/inconnu/rôle incorrect ; ne s'active pas sur le site. Comparaison SQL réelle puis projection/parcours FR/EN/DE différées OP-06/10 |
| OP04-CAC08 | TECH-06/07, Quality | Suite sans base conservée et réussie, syntaxe/Pint, configuration MariaDB indépendante sans nettoyage ni exécution implicite ; témoins code/source/capsule/.env et registre contrôlés |
| OP04-CAC09 | TECH-06, OP-05 | Rapport livre liste/empreintes des fichiers et DDL, commandes et effets exacts à autoriser pour test ; aucun SQL réel/migration/import/GRANT/bascule exécuté sous OP-04 |

OP-06 devra éprouver sur XAMPP réel : domaines et PK/FK/CHECK/UNIQUE, colonnes/tables/rôles/grants exacts, refus croisés/écriture reader, marque vide/ancien prix null/JSON malformé/catégorie autre lot/position doublée, transaction interrompue et état incertain, deux importeurs, lot vérifié non modifiable par importeur, gros messages PDO, et rapprochement complet de la capsule. Les jeux invalides doivent rester dans des transactions synthétiques prévues ; aucun nettoyage d'une base trouvée. OP-07 reste nécessaire pour la sauvegarde/restauration isolée avant local ; la copie système existante sur le même disque n'a pas été restaurée en test.

## 9. Sources officielles : questions ciblées, faits et limites

Questions recherchées avant conception : (a) JSON MariaDB protège-t-il les doublons/fidélité ? (b) dans quelles conditions FOR UPDATE protège-t-il le parent InnoDB ? (c) CREATE/ALTER sont-ils annulables par la transaction d'import ? Consultation **08/10/2026**, documentation générale officielle ; ce n'est pas une épreuve du serveur XAMPP10.4.28.

| Titre et URL | Fait publié / portée | Interprétation et recommandation HOMYA |
|---|---|---|
| [JSON Data Type](https://mariadb.com/docs/server/reference/data-types/string-data-types/json) | Alias LONGTEXT utf8mb4_bin avec JSON_VALID ; texte conservé, clés dupliquées néanmoins admises dans les exemples | Garder StrictJson et comparaison des types/valeurs ; examiner CHECK réel en OP-05, sans assimiler validité JSON et fidélité |
| [FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update) | Verrou de ligne InnoDB applicable dans une transaction ou autocommit désactivé | Verrou commun dans la transaction de chargement ; les doubles ne démontrent pas blocage/délai/concurrence sur XAMPP |
| [SQL statements Causing an Implicit Commit](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit) | CREATE TABLE/ALTER TABLE figurent parmi les commits implicites ; la transaction ne les annule pas | Séparer schéma et import ; arrêter sur DDL partiel et ne promettre aucun rollback destructif |

Source primaire d'environnement : [MigrateCommand.php installé](/Users/akm/Documents/ChatGPT/HOMYA/vendor/laravel/framework/src/Illuminate/Database/Console/Migrations/MigrateCommand.php:146) crée le journal avant le test pretend ; le chargement de dump est conditionné séparément. [MySqlGrammar.php installé](/Users/akm/Documents/ChatGPT/HOMYA/vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/MySqlGrammar.php:955), hérité par MariaDbGrammar, compile JSON en `json` et précision datetime explicitement. Constats de lecture locale, pas commandes exécutées.

## 10. Décision groupée à présenter

**Décision nécessaire : autoriser le développement du complément OP-04 v0.1 (§2–8)**, incluant l'enveloppe archive_document, chargement des enfants en transaction unique et arrêt sans reprise automatique proposés. Autorité limitée au code, aux tests/simulations sans base et à leur documentation ; aucune correction métier ou activation.

Texte soumis : « J'autorise le développement du complément OP-04 v0.1 : migrations ciblées, importeur, lecteur SQL isolé et tests/simulations sans base, avec conservation du site sur fichiers. Aucun SQL réel, création, GRANT, migration exécutée, import, réglage XAMPP, secret modifié ou bascule autorisé. »

Cette autorisation reste absente à la préparation ; ne pas attribuer DECISION-028 avant une réponse explicite et sa consignation. L'exigence de séparation est déjà fixée par l'utilisateur/DECISION-027 ; le [Skill Database Engineering](/Users/akm/.codex/skills/database-engineering/SKILL.md) confirme : « Chacune de ces actions requiert son propre plan et autorisation explicite. »

**Blocage de code :** accord du complément absent. **Dépendances d'exécution, sans blocage documentaire :** validation DDL réel OP-05 ; DML/recette OP-06 ; restauration OP-07 ; local OP-08/09 ; bascule OP-10. Aucun choix commercial/visuel/hébergeur supplémentaire nécessaire pour préparer ce complément.

Preuve de cette phase : [inspection et conservation](../output/qa-import/op04-complement-inspection.json). Les 101 tests/717 assertions cités dans la clôture précédente sont historiques, pas relancés dans cette phase documentaire ; aucun build requis par les seuls documents.

## 11. Complément développé et vérifié après accord

Accord « oui j autorise » reçu après la demande explicite ; DECISION-028 enregistré, sauvegardé et relu avant code. [Reçu](../output/qa-import/op04-complement-autorisation.json). Développement natif Codex ; aucun appel du modèle Claude ni authentification supposée.

**Fichiers :** quatre migrations du répertoire catalogue ; services CatalogueArchiveSchema/Payload/Importer/Store ; commandes PrepareCatalogueSchema et ImportCatalogueArchive ; extensions ciblées de ArchiveDatabaseGuard (rôle), StrictJson (types/comparaison/canonicalisation sémantique) et CatalogueCapsule (documents vérifiés bornés). Deux fixtures/doubles, quatre classes de tests et une recette de lecture MariaDB isolée, phpunit.mariadb.xml et sa notice. Liste/empreintes exactes dans [contrôles finaux](../output/qa-import/op04-complement-controles-finaux.json).

**Source fichiers maintenue :** Catalogue.php, contrôleurs/routes/providers, vues/styles/langues, configurations existantes, phpunit.xml et migrations du squelette inchangés. Aucun secret/accès dédié réel lu par les simulations ; .env applicatif conservé. Aucun SQL, GRANT, réglage/service XAMPP, import, nouvelle capsule ou activation. Le service de lecture SQL ne possède aucune liaison web.

**Migrations :** [DDL compilé](../output/qa-import/op04-complement-schema-final.sql), 6 492 octets, SHA-256 `1d85f2b72f241349254a9911cfa9da3bee76792d3fe9c3d9b77cb5d64600b568`. [Empreinte de fichiers et liste des quatre migrations](../output/qa-import/op04-complement-schema-final.json). Compilation sans PDO ; CHECK explicites, InnoDB, types approuvés, FK/positions. Les migrations seules refusent l'exécution sans contexte explicite ; down refuse DROP. La commande de schéma fournit une enveloppe future protégée par rôle/operation/empreintes/confirmation : elle exige un journal déjà créé, vide et accessible, refuse tout dump/schéma partiel, utilise directement Migrator sur le seul dossier catalogue et restaure le contexte CLI. Aucun migrate/pretend/install exécuté. La vue des objets par un compte aux droits limités ne remplace pas le précontrôle administratif indépendant.

**Import/lecture :** simulation sans accès ; INSERT paramétrés d'une ligne, parent commun verrouillé dans la transaction de chargement, rapprochement complet avant commit, aucune reprise automatique/UPDATE enfant/delete. Même lot verified comparé sans réécriture ; commit incertain rapporté à inspecter, pas rejoué ; failed seulement sous contrôles de parent/absence d'enfants, jamais en rétrogradant un autre succès. Lecteur explicite borné par lot, sans connexion implicite/repli/activation ; reconstruction et proof/counts/digest vérifiés, types et ordre préservés. Les droits SQL ne garantissent pas à eux seuls l'immuabilité face à un écrivain externe non coopérant.

**Simulation réelle sans SQL** : [résultat](../output/qa-import/op04-complement-simulation.json), capsule complète relue avant/après, DatabaseManager interdit par doubles pendant l'exécution de la vraie commande. Tous les comptes attendus concordent : 3 709 produits, 65 catégories, 23 878 traductions, 41 977 caractéristiques, 92 variantes, 18 454 associations galerie, 35 overlays, 627 signalements et six marques vides. Archive_document = 4 730 205 octets, manifeste = 3 550 149 ; budget conservateur INSERT lot = 16 626 698 octets sous 16 777 216. Ce budget n'est pas une preuve de transport PDO. Durée locale observée104,181s, pic PHP alloué267 993 088 octets (~256Mio) sous memory_limit512M ; pas une cible ni une promesse de performance. La simulation précède le dernier contrôle des colonnes du lot, qui ne concerne que le rapprochement SQL ; sa garde est incluse dans la régression finale.

**Tests :** [régression finale](../output/qa-import/op04-complement-tests-regression-finale.xml) **142 tests / 950 assertions, aucun échec/erreur/skipped**. Vingt fichiers PHP contrôlés en syntaxe et Pint. Les premiers essais ont détecté l'injection Laravel d'une connexion lors de la préparation de schéma, supprimée par une fabrique d'exécution explicite ; deux signatures du double ont été corrigées. Sockets synthétiques bloqués dans le sandbox : reprises hors sandbox, toujours sans PDO réel. Rapports initiaux conservés ; 21 puis34 puis40 tests ciblés réussis et régression finale142. Aucun build frontend puisque aucun asset modifié.

| Critère | État de recette après développement |
|---|---|
| OP04-CAC01/02 | Compilation déterministe/objets/types et refus sans contexte/empreinte/rôle/access avant connexion prouvés ; DDL réel/droits effectifs restent OP-05/06 |
| OP04-CAC03/04 | Capsule réelle simulée et compteurs rapprochés ; objets/tableaux/nombres/absences/Unicode/doublons, TEXT/INT/paquet, provenance de copie et incohérences testés ; message PDO réel à éprouver OP-06 |
| OP04-CAC05/06 | Vrai Query Builder avec double sans PDO : ordre/verrou avant enfant, commit/rollback, panne, absence de retry, lot incomplet, idempotence et non-rétrogradation d'un autre succès. Ce n'est pas une épreuve InnoDB/concurrence réelle |
| OP04-CAC07 | Reconstruction fidèle et refus d'incomplétude/corruption/rôle incorrect prouvés sur fixtures ; pas de liaison web. Comparaison SQL réelle et projection/parcours après bascule restent OP-06/10 |
| OP04-CAC08/09 | Régression/syntaxe/Pint, témoins, DDL et commande ciblée disponibles sans SQL. Recette MariaDB indépendante désactivée : [un skipped avant boot/accès](../output/qa-import/op04-complement-recette-desactivee.xml), pas un test réussi de compatibilité |

La recette live de ce complément couvre uniquement le rapprochement SELECT d'un lot test déjà vérifié ; les jeux SQL d'écriture/contraintes/deux processus seront finalisés après le schéma et avant l'accord OP-06. Voir [notice](../tests/MariaDB/README.md). Ils ne sont ni exécutés ni annoncés comme entièrement implémentés. Restauration isolée toujours non testée.

### Commandes utilisables sans base

```sh
/opt/homebrew/bin/php artisan homya:catalogue-schema
/opt/homebrew/bin/php artisan homya:catalogue-schema --ddl
/opt/homebrew/bin/php -d memory_limit=512M artisan homya:catalogue-import 48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809 --progress
```

Sans --execute, les options d'accès/exécution sont refusées. Aucune commande --execute n'est autorisée par cette clôture. OP-04 prépare désormais le code et la simulation ; contraintes, transport, concurrence, sauvegarde/restauration et connexion applicative ne sont pas clôturés.

**Prochaine étape :** préparer le précontrôle et l'autorisation OP-05 à partir du [périmètre et artefacts précis](OP-05-SCHEMA-TEST-PREPARATION.md). Aucun accord OP-05 enregistré ; aucun objet créé sous ce complément.
