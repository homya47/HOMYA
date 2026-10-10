# HOMYA — plan technique de l'archive XAMPP

Version 0.2 — 7 octobre 2026 — **VALIDÉ sur le plan documentaire ; opérations d'exécution non autorisées et non réalisées**. Statut actualisé après confirmation, sans changement des choix soumis.

Origine : [plan d'import/MCD approuvés](/Users/akm/Documents/ChatGPT/HOMYA/docs/PLAN-IMPORT-XAMPP.md), DECISION-017 ; [MLD v0.2 approuvé](/Users/akm/Documents/ChatGPT/HOMYA/docs/MODELE-LOGIQUE-ARCHIVE-HOMYA.md), DECISION-018 dans le [registre central](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). La demande « vérifier la cible XAMPP en lecture seule, puis finaliser les opérations précises à autoriser séparément » a permis TECH-01 et la préparation documentaire. La réponse utilisateur « je valide le plan documentaire » valide cette v0.2 dans DECISION-019, uniquement sur le plan documentaire. Aucun réglage XAMPP, code, création, migration, import, restauration ou bascule n'est autorisé par cet accord.

La version exacte soumise et approuvée, SHA-256 `42c987d8542b2007221054a521769d32ee2d8aa0c378da07a7f37cec87d4e6de`, est conservée dans `/private/tmp/homya-plan-v02-valide-019-fzskehdk/docs/PLAN-TECHNIQUE-ARCHIVE-XAMPP.md`. Les mentions de statut sont actualisées ci-dessous ; les choix et limites approuvés sont conservés.

## 1. État réel et périmètre

**Observations actuelles :** PHP CLI 8.5.11 / Laravel 13.35.0 ; les SELECT de métadonnées dans la session phpMyAdmin locale disponible confirment MariaDB XAMPP 10.4.28, son socket exact et le port 3307. Les deux schémas et six noms de comptes proposés sont absents au contrôle. `homya:db-check` refuse les accès Laravel absents avant SQL (code 1). Cette preuve d'administration ne vaut pas connexion applicative.

Le catalogue actuel reste sur fichiers et le site demeure consultatif. Source de reprise : 3 709 fiches, 65 catégories et dictionnaires EN/DE complets ; tableaux/flags/provenance conservés. Les valeurs divergentes, variantes sans prix, marques vides et photos masquées ne sont pas corrigées par cet import.

**Conventions inspectées :** `config/catalogue.php` contient uniquement les chemins fichiers ; `config/homya.php` fixe le socket XAMPP et les langues ; les scripts Composer ne lancent pas de migrations. Les trois migrations générales présentes créent utilisateurs/sessions/cache/jobs et apparentés ; `DatabaseSeeder` crée un utilisateur de test. **Ces fichiers sont conservés et exclus du lot catalogue.** `phpunit.xml` vise actuellement `homya_test_not_created` pour les tests sans base ; ne pas le prendre pour une recette MariaDB.

## 2. Contrôles préalables en lecture seule — TECH-01

Avant de préparer une autorisation d'écriture, obtenir des preuves expurgées sur la cible exacte :

- identité serveur : version MariaDB, socket, port et répertoire de données XAMPP ; refus du serveur Homebrew/3306, SQLite ou d'une URL substituée ;
- existence et propriété des seuls noms retenus `homya_local`/`homya_test` : aucune réutilisation automatique d'un schéma déjà présent ;
- droits du compte de contrôle local et comptes dédiés proposés, sans afficher utilisateurs détaillés d'autres projets ni secrets ;
- moteur InnoDB, mode SQL strict, charset/collations, contrôles FK/CHECK actifs, limites de paquets et session/client PHP ;
- possibilité de sauvegarde et restauration isolée, versions des outils et espace réellement disponible.

**Exécuté le 07/10/2026 :** dix instructions SELECT seulement, depuis la session locale phpMyAdmin 5.2.1 déjà disponible, sur variables et métadonnées bornées aux objets HOMYA proposés. Aucune table métier d'un autre projet consultée ; aucun mot de passe, hash d'authentification ou droit d'un autre compte extrait. L'écran SQL est au niveau serveur ; `DATABASE()` y retourne `mysql`, sélection interne observée de phpMyAdmin, et non un schéma HOMYA. Le contrôle préalable n'a donc pas dépendu d'une base HOMYA préexistante.

| Contrôle / source réelle | Résultat observé | Portée / statut |
|---|---|---|
| VERSION, port, socket comparé au chemin exact, datadir, bind_address | 10.4.28-MariaDB ; 3307 ; socket attendu égal ; `/Applications/XAMPP/xamppfiles/var/mysql/` ; `127.0.0.1` | Cible XAMPP identifiée ; pas le serveur Homebrew |
| SCHEMATA, uniquement noms proposés | `homya_local` : 0 ; `homya_test` : 0 | Absence vérifiée à cet instant ; recontrôler avant création |
| mysql.user, uniquement six noms de §4, colonnes User/Host | Aucune ligne, tous hôtes confondus | Noms proposés libres ; aucune création ni inspection d'authentification |
| InnoDB / ENGINES | DEFAULT, transactions YES ; page 16 384 octets, format dynamic | Compatibilité déclarée ; contraintes effectives à éprouver sur schéma autorisé |
| Charset/collations | Serveur utf8mb4/general_ci ; ascii_bin et utf8mb4_bin disponibles ; session phpMyAdmin utf8mb4/unicode_ci | Fixer explicitement les collations physiques ; session Laravel encore inconnue |
| sql_mode global et session phpMyAdmin | STRICT_TRANS_TABLES, ERROR_FOR_DIVISION_BY_ZERO, NO_AUTO_CREATE_USER, NO_ENGINE_SUBSTITUTION | Strict observé ; paramètres effectifs Laravel à contrôler après accès |
| FK / CHECK globaux et session | Tous à 1 | Contrôles actifs ; aucune épreuve DDL/DML réalisée |
| max_allowed_packet global et session | 1 048 576 octets (1 Mio) | **Bloquante pour le document de lot candidat de 4 730 180 octets** |
| USER_PRIVILEGES, compte de contrôle courant, filtre ciblé | SELECT, INSERT, UPDATE, CREATE, ALTER, CREATE USER et SUPER ; IS_GRANTABLE=YES | Droits déclarés suffisants pour préparer les actions ; aucune écriture testée, aucun transfert à Laravel |
| lower_case_table_names | 2 | Employer noms minuscules exacts ; ne pas modifier ce paramètre |
| CLI mysql / mysqldump XAMPP, commandes --version | Tous deux MariaDB 10.4.28 | Outils présents ; authentification CLI, sauvegarde et restauration non testées |
| df -k, volume partagé projet/datadir | 64 800 000 Kio disponibles au contrôle, environ 61,8 Gio | Stockage observé, variable ; aucune preuve de capacité finale ni sauvegarde indépendante |
| PHP CLI / extensions / ini ciblée | 8.5.11 ; pdo_mysql/mysqlnd présents ; memory_limit=128M ; buffer lecture mysqlnd=32 768 octets | Ce buffer n'est pas max_allowed_packet ; transport PDO complet non éprouvé |
| `php artisan homya:db-check` | Refus « Accès local non renseigné », code 1 | Connexion applicative **À vérifier**, aucun SELECT Laravel exécuté |

Preuve des requêtes et résultats expurgés : [xampp-controle-lecture-seule-20261007.json](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/xampp-controle-lecture-seule-20261007.json). Les cellules tronquées de socket/mode ont été complétées par comparaison exacte et extraction des items. Le code phpMyAdmin installé encadre les requêtes par réglage/restauration du contrôle FK de session ; sa case cochée a été conservée, valeurs observées à 1. Aucune configuration persistante n'a été modifiée. Ne pas confondre « SELECT soumis seulement » et absence de toute instruction interne de l'outil.

Les accès dédiés doivent être saisis localement, jamais dans le chat, les arguments shell ou les preuves. `homya:db-check` est conservé ; sa connexion actuelle exige une base, il ne remplace pas ce contrôle préalable. Restent **À vérifier** : authentification des futurs comptes, paquets et paramètres de leurs sessions, contraintes/concurrence, sauvegarde-restauration. Aucune base, compte ou table créés.

## 3. Proposition physique — TECH-02

Quatre relations métier du MLD, InnoDB. Types et collations ci-dessous **validés sur le plan documentaire**, à éprouver sur 10.4.28 après autorisations d'exécution. Cette matrice est une spécification, pas un DDL exécutable.

| Relation / attributs | Type physique recommandé | Justification / contrôle |
|---|---|---|
| Toutes : `lot_key` ; traductions : `source_key` | CHAR(64), ASCII, collation `ascii_bin`, NOT NULL | Empreintes hexadécimales minuscules exactement 64 caractères ; comparer manifeste/texte en plus du hash |
| Catégories/produits : `slug` ; produits : `category_slug` | VARCHAR(221), ASCII `ascii_bin`, NOT NULL | Regex existante et domaine proposé du MLD ; mêmes type/charset/collation aux deux extrémités FK |
| Lot : `format_version` ; positions produit/traduction | INT signé, NOT NULL, contrôle positif | Version de format 1 et positions 1..N ; refuser dépassement au lieu de tronquer |
| Lot : `load_state` | VARCHAR(16), ASCII `ascii_bin`, NOT NULL | Domaine `prepared`, `loading`, `verified`, `failed` ; pas de disponibilité commerciale |
| Lot : `registered_at`, `verified_at` | DATETIME(6), valeurs UTC ; second nullable | Date d'enregistrement obligatoire ; date vérifiée seulement après succès |
| Lot : `manifest_document`, `archive_document`, `verification_document` | JSON MariaDB / LONGTEXT validé ; dernier nullable | Préserver documents et listes ; CHECK et nullabilité à éprouver |
| Catégorie : `source_name` ; produit : référence/nom/marque/état ; traduction : source/cible | TEXT utf8mb4, collation binaire pour fidélité, NOT NULL | Pas de conversion de casse/collation linguistique ; marque `''` admissible ; détecter une future valeur au-delà de la capacité avant SQL |
| Produit : `universe` | VARCHAR(32), ASCII `ascii_bin`, NOT NULL | Codes actuels jusqu'à 11 caractères ; domaine des cinq univers observés pour cette capsule |
| Produit : `price_cents`, `previous_price_cents` | INT signé ; ancien prix nullable | Prix courant positif ; 790 758 / 928 200 max observés ; pas de float, relation promotionnelle ou valeur par défaut inventés |
| Produit : `currency` | CHAR(3), ASCII `ascii_bin`, NOT NULL | EUR uniquement pour cette capsule |
| Produit : `index_document`, `detail_document` | JSON MariaDB / LONGTEXT validé, NOT NULL | Conserver index compact et fiche, y compris champs privés et image de carte |
| Traduction : `locale` | CHAR(2), ASCII `ascii_bin`, NOT NULL | Domaine en/de ; FR dans la source, pas d'entrée artificielle |

PK/FK/UNIQUE correspondent à CON-001/004 du MLD ; FK composites non nulles et actions RESTRICT. Contrôles scalaires/JSON associés à CON-005/006 ; application et rapprochement pour ordre, exhaustivité, gel et média. Aucun index texte/prix/marque spéculatif ; aucune suppression en cascade. Les noms de contraintes devront être distincts dans le schéma.

**Document de lot mesuré en mémoire : 4 730 180 octets** pour un candidat compact UTF-8 composé de l'index, inventaire, overlay, métadonnées de dictionnaires et trois rapports de revue. Ce n'est ni un fichier de capsule figée ni la taille du paquet SQL. La limite réelle de 1 Mio est insuffisante. Recommandation physique : 16 Mio serveur, contrôle préalable de chaque message/valeur/manifeste, lecture comme écriture, et épreuve PDO sur recette autorisée. Aucun découpage silencieux du document logique ni augmentation automatique. Le §10 détaille l'autorisation propre à ce réglage. Les valeurs par défaut officielles ne sont pas des observations locales.

## 4. Isolation et droits — TECH-03

**Noms validés sous réserve de collision :** `homya_test` pour recette, `homya_local` pour lecture locale. Une collision arrête le plan ; aucune purge, renommage ni réutilisation du schéma rencontré. Le périmètre d'accès à chaque schéma doit être distinct pour éviter qu'une recette puisse écrire dans le local.

| Rôle proposé | Droits minimaux à détailler avant création | Restrictions |
|---|---|---|
| Lecture applicative | SELECT sur archive_lots, archive_categories, archive_products, archive_translations du seul schéma | Aucun INSERT/UPDATE/DELETE/DDL ; pas d'accès au journal ou aux autres projets |
| Import local/test | SELECT, INSERT sur ces quatre tables ; UPDATE(load_state, verification_document, verified_at) sur archive_lots seulement | Aucun UPDATE des enfants ou autres colonnes ; aucun DDL, GRANT, DELETE, REPLACE ou purge ; gel d'un lot vérifié assuré par importeur/verrou et recette, pas le droit SQL seul |
| Migration | CREATE et ALTER au niveau de chacune des cinq tables nommées ; SELECT, INSERT sur migrations après sa création | Aucun droit au niveau schéma/global, pas de DROP/DELETE/UPDATE/GRANT ; pas de migration générale ou rollback |

**Comptes validés en conception, non créés :** `homya_test_reader`, `homya_test_import`, `homya_test_schema` pour homya_test ; `homya_local_reader`, `homya_local_import`, `homya_local_schema` pour homya_local. Hôte `localhost` seulement, connexion par socket exact ; aucun compte `%`/distant. Ils sont tous absents au contrôle. Secrets différents par compte, créés/saisis localement, jamais publiés ; aucune utilisation implicite du compte administrateur par Laravel.

CREATE/ALTER sont bornés à chaque table, jamais accordés sur `homya_local.*` ou `homya_test.*` : CREATE au niveau base autoriserait aussi sa création et les motifs `_`/`%` des droits de base exigent une attention supplémentaire. CREATE sur une table future est documenté par MariaDB ; le DML lecteur/import et celui du journal seront accordés après existence des tables. ALTER est nécessaire avec le Schema Builder Laravel installé : ses clés primaires/uniques/FK sont compilées en ALTER TABLE. Ces opérations restent limitées aux objets approuvés. Les droits effectifs exacts seront éprouvés dans test ; aucun privilège n'est élargi automatiquement après un refus.

La création des bases est séparée des migrations. Prévoir vérification par refus effectif des écritures sous le compte de lecture sur jeux synthétiques autorisés ; conserver contrôle de collision et revues du DDL avant toute création.

## 5. Journal technique et migrations ciblées — préparation de TECH-06

**Choix validé sur le plan documentaire :** usage du mécanisme Laravel standard avec répertoire dédié `database/migrations/catalogue/`, qui n'existe pas encore. Sélectionner uniquement ce chemin après revue de son contenu et autorisation de code/exécution ; ne pas lancer une migration générale, ni `fresh`, ni `refresh`, ni le seeder existant.

**Empreinte prévue : quatre tables d'archive plus `migrations`**, journal technique requis par le mécanisme Laravel. Cette cinquième table n'est pas une entité métier du MLD ; sa création doit être incluse explicitement dans l'autorisation de migration. Si un journal technique standard est refusé, examiner une autre procédure avant de coder, sans la substituer silencieusement. Aucune table commerce/users/cache/jobs incluse.

Ordre technique proposé : journal géré par Laravel, lot, catégories, produits, traductions ; relations FK créées explicitement avec domaines identiques. Le DDL étant distinct des transactions de données, une erreur de création peut laisser un schéma partiel. Prévoir inventaire des objets présents et reprise ciblée après autorisation ; aucun rollback destructif automatique. `down()` et tout DROP exigeraient leur propre justification/autorisation ; pas de promesse d'annulation complète.

**Garde supplémentaire observée dans Laravel installé :** la préparation de la commande de migration crée le journal s'il manque, même avant une simulation `pretend` ; une première migration peut aussi charger un dump de schéma. Le diagnostic de cette phase ne doit donc pas lancer cette commande comme contrôle « sans écriture ». Vérifier absence de dump/contenu SQL non approuvé, connexion/cible et existence autorisée du journal avant toute future exécution. Aucun fichier de dump de schéma observé dans `database/` lors de la préparation, à recontrôler juste avant migration. La simulation d'import proposée est un contrôle dédié sans SQL d'écriture, pas cette option du framework.

## 6. Fichiers envisagés — aucun code créé

| Fichiers proposés / existants à adapter après autorisation | Responsabilité / limites |
|---|---|
| `database/migrations/catalogue/…` | Création des quatre relations selon types approuvés, contraintes et index justifiés |
| `app/Console/Commands/PrepareCatalogueCapsule.php` | Préparer capsule figée dans un nouveau dossier privé, empreintes de tous les fichiers/binaires ; aucun réseau/SQL |
| `app/Console/Commands/ImportCatalogueArchive.php` | Simulation par défaut ; import inactif uniquement sous autorisation spécifique ; compte dédié, bornage de cible et verrou parent |
| `app/Services/CatalogueArchiveStore.php` | Accès Query Builder aux clés composites et reconstruction des documents ; pas d'hypothèse d'ID Eloquent simple |
| `config/catalogue.php`, `config/database.php`, `.env.example` | Source fichiers par défaut et connexions dédiées, noms/paramètres sans secrets ; `.env` privé préparé séparément |
| Tests et configuration MariaDB dédiés | Jeux synthétiques dans `homya_test` autorisé ; garde du nom/socket ; aucun nettoyage automatique d'une base trouvée |
| `app/Services/Catalogue.php` | Branchement futur après recette ; conserver projection HOMYA/langues/masquages, filtrage/tri PHP et chemins fichiers |

Cette structure est validée sur le plan documentaire ; aucune classe, connexion ou migration catalogue n'est implémentée. Conserver les tests actuels sans base et leurs protections ; recette MariaDB dans une configuration distincte au schéma confirmé, jamais une substitution SQLite ou un simple changement du nom dans les tests existants. Pas de dépendance externe supplémentaire proposée.

## 7. Autorisations et sorties de chaque lot

| Lot / exigence | Travail futur précis | Autorisation et preuve de sortie |
|---|---|---|
| TECH-01 / IMP-02 | Contrôles d'identité, collision, capacité et droits en lecture seule | Accès local autorisé disponible ; rapport expurgé, aucun changement |
| TECH-02/03 | Adopter types, journal et plan des schémas/comptes | Accord documentaire reçu dans DECISION-019 ; ce n'est pas autorisation de création |
| TECH-04 / IMP-03 | Créer seulement schémas/comptes isolés confirmés | Autorisation d'écriture listant objets, hôtes et droits exacts ; collisions recontrôlées juste avant |
| TECH-05 / IMP-04 | Préparer capsule, sauvegarde de cible existante, restauration isolée | Autorisation de code/copie/écriture et de restauration selon cible ; empreintes et preuve de restauration sans toucher l'existant |
| TECH-06 / IMP-05 | Implémenter migrations/importeur et tests, puis schéma dans test | Autorisation de code et autorisation distincte d'exécution ciblée ; cinq tables attendues dont journal, aucun seeder |
| TECH-07 / IMP-06/08/09 | Simulation complète puis recette sous données isolées | Chargement test autorisé séparément ; contraintes, concurrence, fidélité FR/EN/DE et non-régression démontrées |
| TECH-08 / IMP-07 | Migrer/importer le local avec lot inactif | Autorisations locales exactes après recette/sauvegarde ; aucun lot partiel visible |
| TECH-09 / IMP-10 | Sélectionner explicitement la lecture SQL locale | Autorisation de bascule après recette ; lecture fichiers conservée et retour testé sans suppression SQL |

TECH-01 est réalisé pour identité/collisions/capacités annoncées ; accès dédiés et restauration restent ouverts. Les opérations §10 précisent les autorisations de ces lots, notamment le réglage XAMPP distinct. Les étapes restent séquentielles ; aucune approbation générale ne vaut accord sur tous les lots. Bases existantes et restauration ne doivent jamais être écrasées sous prétexte de recette. Aucun changement de production, vente, paiement, hébergement ou collecte réseau dans ce plan.

## 8. Contrôles de recette et exploitation

Sur MariaDB réelle : PK/FK/UNIQUE/CHECK/nullabilité, catégorie d'un autre lot, position doublée, JSON malformé, collision de hash simulée, dépassement de capacité, marque vide et ancien prix null, état vérifié sans preuves/date, reprise sans doublon, panne partielle, deux importeurs concurrents et refus d'écriture après vérification. Vérifier que rôle lecture ne peut muter et qu'une erreur n'expose ni secret ni provenance privée dans le site.

Rapprochement intégral : 3 709 produits, 65 catégories, 41 977 caractéristiques, 92 variantes, 18 454 associations originales, 23 878 traductions, 35 overlays et 627 signalements, ainsi que valeurs exactes et ordre. Les photos masquées et descriptions signalées restent dans l'archive ; leur projection reste conforme aux règles locales déjà approuvées. FR/EN/DE, recherche/filtres/tri/pagination, erreurs 404/503 et photo de chaque fiche comparés au lecteur actuel.

Preuves privées par lot : manifeste figé, cible identifiée, versions, contraintes, compteurs et empreintes, résultats test et restauration, erreurs expurgées. Pas de purge ou durée de conservation inventée. Sauvegardes XAMPP de configuration ne valent pas sauvegarde catalogue ; retour fichiers ne vaut pas restauration SQL. Maintenance MariaDB 10.4 et hébergement traités séparément avant production.

## 9. Faits, décisions et limites de cette préparation

**Historique v0.1 :** enregistrement de DECISION-018, sauvegarde approuvée, lecture des conventions/code fournisseur et mesure du candidat, contrôle des 3 714 empreintes, sans SQL. **Cette révision v0.2 :** dix SELECT de contrôle dans phpMyAdmin, lecture ciblée de my.cnf et outils/espace/PHP, diagnostic Laravel refusé avant SQL, sauvegarde documentaire et révision du plan. Aucun code applicatif ajouté, aucune commande de migration/seeder, création de compte/base, changement persistant de configuration, restauration, import ou bascule. Aucun test applicatif/build relancé pour ces changements documentaires.

**Sources officielles :** comportements JSON, FK/CHECK, paquets, verrous et DDL documentés avec titres, URL, consultation du 7 octobre 2026 et limites dans le MLD §8 et le plan d'import §8. La gestion du journal et le ciblage des migrations Laravel sont vérifiés dans le code fournisseur installé et sa documentation officielle ; aucune capacité locale déduite d'un exemple.

| Source / consultation | Portée et fait étayé | Limite / implication |
|---|---|---|
| [Database: Migrations — Laravel 13](https://laravel.com/framework/docs/13.x/migrations), 07/10/2026 | Documentation de la structure, exécution, connexion et chargement de dumps lors des premières migrations | Planifier exécution ciblée ; cette documentation n'atteste aucun objet SQL local |
| [BaseCommand.php installé](/Users/akm/Documents/ChatGPT/HOMYA/vendor/laravel/framework/src/Illuminate/Database/Console/Migrations/BaseCommand.php), lu le 07/10/2026 | `getMigrationPaths()` retourne les chemins explicitement sélectionnés | Revoir exactement leur contenu avant autorisation de code/exécution |
| [MigrateCommand.php installé](/Users/akm/Documents/ChatGPT/HOMYA/vendor/laravel/framework/src/Illuminate/Database/Console/Migrations/MigrateCommand.php), lu le 07/10/2026 | Préparation du journal et chargement éventuel d'un dump avant migrations ; `pretend` ne supprime pas la création préalable du journal | Ne pas utiliser cette commande pour preuve de diagnostic sans écriture |
| [DatabaseMigrationRepository.php installé](/Users/akm/Documents/ChatGPT/HOMYA/vendor/laravel/framework/src/Illuminate/Database/Migrations/DatabaseMigrationRepository.php), lu le 07/10/2026 | `createRepository()` crée le journal avec id/migration/batch | Cinquième table technique à annoncer et autoriser séparément |
| [Server System Variables — MariaDB](https://mariadb.com/docs/server/server-management/variables-and-modes/server-system-variables#max_allowed_packet), 07/10/2026 | Limite des messages/chaînes ; max_allowed_packet dynamique global, session non modifiable ; vérifier aussi le client | 1 Mio est une observation SQL locale, pas la valeur par défaut documentaire ; nouvelles connexions à contrôler après réglage |
| [GRANT — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant), 07/10/2026 | Droits par table/colonne ; CREATE possible sur table future ; CREATE de base autorise aussi la base ; motifs de droits de base `_`/`%` | Définir droits table par table, sans ALL ni délégation ; comportement exact sur 10.4.28 à éprouver en recette autorisée |
| [MySqlGrammar.php installé](/Users/akm/Documents/ChatGPT/HOMYA/vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/MySqlGrammar.php), [Grammar.php installé](/Users/akm/Documents/ChatGPT/HOMYA/vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/Grammar.php), lus le 07/10/2026 | Compilation des clés en ALTER TABLE | ALTER borné aux tables prévues ; aucun DDL exécuté |

**Décisions validées en conception :** matrice physique, cinq tables dont journal, six comptes et habilitations ci-dessus ; proposition de réglage de paquets à 16 Mio et séparation des opérations. **Décision nécessaire suivante :** autorisation d'exécution OP-01, non reçue. **Informations manquantes :** secrets/connexions dédiés, transport PDO et taille capsule figée, contraintes/concurrence et restauration. **Bloquante :** paquet serveur 1 Mio. Les noms libres, collations et espace ne sont plus des inconnues à cette date ; ils doivent être recontrôlés au moment des actions.

## 10. Opérations précises à autoriser séparément — aucune exécutée

**D-01, validation documentaire reçue :** la réponse « je valide le plan documentaire » du 07/10/2026 approuve v0.2 (types §3, cinq tables dont journal, comptes/droits §4, méthode de paquets ci-dessous), consignée dans DECISION-019. Cette décision ne crée rien et n'autorise pas l'exécution d'une ligne de ce tableau. Toute opération commence par recontrôle de cible/collision et s'arrête au premier écart. Aucun `IF NOT EXISTS` destiné à réutiliser silencieusement un objet trouvé.

| Opération / dépendances | Autorisation future exacte | Résultat et garde observables |
|---|---|---|
| OP-01 — paquets XAMPP ; D-01 | Sauvegarder my.cnf avec empreinte/permissions ; changer uniquement `max_allowed_packet=1M` en `16M` sous `[mysqld]` dans `/Applications/XAMPP/xamppfiles/etc/my.cnf` ; appliquer `SET GLOBAL max_allowed_packet = 16777216` sur le serveur identifié, sans redémarrage | Fichier et nouvelles sessions à 16 Mio ; aucun port/socket/autre paramètre modifié, aucun arrêt de service. Effet global sur les futures connexions de cette instance XAMPP, pas limité à HOMYA. Accès administrateur local requis ; aucune autorisation accordée ici |
| OP-02 — schémas seulement ; D-01 | Créer homya_test et homya_local vides, chacun avec charset utf8mb4 et collation utf8mb4_bin | Deux schémas sans table ; aucun objet existant réutilisé/effacé ; charset confirmé. Aucune table, migration ou import dans cette autorisation |
| OP-03a — comptes seulement ; D-01, OP-02 | Créer les six comptes §4 à l'hôte localhost avec secrets distincts fournis/générés localement, sans privilège de données/DDL initial | Six comptes isolés authentifiables par socket ; aucun remplacement de compte rencontré. Saisie des secrets exclusivement locale, aucune modification des comptes existants |
| OP-03b — habilitation DDL bornée ; D-01, OP-03a | Pour chaque compte `_schema`, accorder CREATE et ALTER seulement sur les cinq tables de son propre schéma : archive_lots, archive_categories, archive_products, archive_translations, migrations | Aucun droit de schéma/global, DROP, délégation ou accès croisé. Les lecteurs/importeurs attendent l'existence des tables pour leurs droits DML |
| OP-04 — préparation de code/capsule sans SQL ; D-01 | Implémenter les fichiers §6 et configuration de recette dédiée ; figer capsule/manifeste privé dans un nouveau dossier `storage/app/private/catalogue-archives/<lot_key>/` et vérifier tous les octets/empreintes | Aucun SQL d'écriture/bascule ; source fichiers inchangée. Dépend de capacité disque et manifeste/transport mesurés. Les preuves de contrôle actuelles ne sont pas ajoutées aux trois rapports de revue de la capsule |
| OP-05 — schéma test ; OP-01 à OP-04 | Créer journal migrations dans homya_test uniquement, accorder SELECT/INSERT sur ce journal au seul homya_test_schema, puis exécuter les seules migrations catalogue revues (CREATE/ALTER des quatre tables) | Exactement cinq tables, aucun seeder/utilisateur/cache/job/dump. DDL exact et commande finale soumis après code ; création partielle arrête le lot, aucune suppression automatique |
| OP-06 — droits DML test et recette ; OP-05 | Accorder à homya_test_reader SELECT sur les quatre tables ; à homya_test_import SELECT/INSERT sur les quatre et UPDATE des trois colonnes techniques de archive_lots (§4). Autoriser chargements synthétiques et capsule de recette identifiée dans homya_test seulement | Refus de droits croisés, contraintes/gel/concurrence, transport PDO, comptages, fidélité et projection prouvés. Le jeu détaillé est revu après code ; aucune modification locale réelle dans cette autorisation |
| OP-07 — sauvegarde/restauration de recette ; OP-06 | Exporter uniquement les cinq tables HOMYA autorisées vers un nouveau fichier privé avec empreinte, sans contenu d'authentification ; préparer une cible de restauration neuve nommée et vérifiée avant autorisation propre de sa création/restauration | Outil installé ne vaut pas restauration validée. Nom exact, compte/droits et contenu du dump soumis lors de cette autorisation ; aucune restauration dans une base existante. Cette opération reste **À préciser avant exécution**, sans autorisation ouverte |
| OP-08 — schéma local ; recette et restauration réussies | Même procédure ciblée que OP-05 dans homya_local, journal compris ; accorder les droits §4 aux trois seuls comptes homya_local concernés, après existence des tables | Cinq tables attendues, pas de données commerciales ; connexion locale/restrictions vérifiées. Pas d'import inclus |
| OP-09 — import local inactif ; OP-08, capsule figée/recette validées | Charger le seul lot identifié par manifeste/empreinte dans homya_local via homya_local_import ; protéger transitions/verrou commun, conserver sources/signalements | Rapprochement complet et lot vérifié inactif ; en cas d'erreur aucune activation ou purge, reprise explicitement inspectée |
| OP-10 — bascule ; OP-09 | Autoriser la lecture SQL locale par homya_local_reader du seul lot vérifié sélectionné ; conserver lecture fichiers et vérifier retour | FR/EN/DE/catalogue/photos/recherche/tri/pagination identiques ; aucun paiement, commande, publication ou suppression |

OP-01 constitue un changement de configuration critique distinct de toute création de base. La sauvegarde proposée permettra de préparer le retour au fichier/paramètre précédent, mais une lecture SQL d'un document >1 Mio ne serait plus garantie après retour à 1 Mio : revenir à la lecture fichiers et inspecter l'impact avant tout abaissement. Aucun retour automatique ni promesse de rollback DDL intégral. Le réglage du client dump est déjà 16M dans my.cnf ; cela ne prouve pas le transport PDO.

OP-02/03 peuvent être demandées séparément ; les noms/matrice sont entièrement reviewables ici. OP-05 à OP-10 seront présentées à nouveau avec leurs artefacts/commandes exacts et empreintes après code/recette ; cette planification ne vaut pas un accord anticipé. Les accès applicatifs et leur saisie privée ne doivent pas être traités comme une preuve de connexion tant que les SELECT dédiés n'ont pas réussi.

**Prochaine étape :** présenter l'autorisation propre à OP-01 ; les créations restent distinctes. D-01 est validé et enregistré ; le MLD approuvé est conservé sans changement et aucune écriture de base n'est autorisée par ce document.
