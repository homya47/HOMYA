# HOMYA — OP-06, préparation de la recette XAMPP

Version 0.4 — 08/10/2026 — **OP-06 VÉRIFIÉE DANS HOMYA_TEST ; AUCUNE ACTIVATION PUBLIQUE**.

Clôture actuelle au §13. Les préparations, demandes d’accord et incidents antérieurs restent historiques ; [plan de reprise v0.3 effectivement autorisé](../output/qa-import/op06-reprise-plan-autorise.md) conservé sans modification.

Les sections1–10 conservent la préparation historique v0.2. Le [plan v0.2 réellement autorisé](../output/qa-import/op06-plan-autorise.md) est figé ; l'accord d'exécution DECISION-032 et ses résultats sont décrits ci-dessous. L'état courant et la reprise proposée figurent aux sections11–12 ; aucune reprise n'est autorisée par cette révision documentaire.

Historique v0.1 conservé dans [la copie documentaire](../output/qa-import/op06-plan-preparation-v01.md). Autorisation du développement : DECISION-031 ; cette décision exclut explicitement SQL, droits et import.

## 1. Objectif et limites

Démontrer que les données du catalogue peuvent être conservées fidèlement dans MariaDB XAMPP, que les contraintes refusent les données invalides et que deux importeurs ne produisent pas deux chargements concurrents du même lot. Cette recette ne développe pas le parcours d'achat et n'active pas la lecture SQL sur le site.

La demande actuelle autorise le complément de développement et sa vérification sans base. **Aucun SQL, accès dédié, GRANT, écriture de données, import, migration ou service exécuté pendant cette préparation.** Les scénarios et droits ci-dessous restent proposés pour un futur lot d'exécution. Aucun nouveau registre général : DECISION-031 consigne uniquement l'accord de code dans le [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). Aucun reçu d'exécution n'est émis avant l'accord humain groupé.

Traçabilité : TECH-03/07, IMP-06/08/09, CON-001 à CON-008 des [opérations et modèle physique](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md) et du [modèle logique](MODELE-LOGIQUE-ARCHIVE-HOMYA.md). Les règles commerciales de prix, stock, paiement et livraison ne sont pas créées par ces tests d'archive.

## 2. Faits, décisions et inconnues

| Qualification | Élément et preuve |
|---|---|
| Fait vérifié antérieurement | DECISION-030 et [clôture OP-05](../output/qa-import/op05-reprise-synthese-verification.json) : cinq tables test, quatre archives vides, journal id1–4/batch1 ; 24 privilèges de table, aucun droit lecteur/importeur ; homya_local vide. Pas de nouvelle inspection SQL dans OP-06 à ce stade. |
| Décision validée en conception | [Plan technique §4/10](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md) : SELECT lecteur ; SELECT/INSERT importeur et UPDATE de trois colonnes de lot seulement. Cette conception n'autorise pas les GRANT. |
| Fait observé dans le code | Importeur et lecteur isolés, sans activation web ; transaction des enfants/verrou parent, relecture d'un lot vérifié et refus des incomplets. [Code importeur](../app/Services/CatalogueArchiveImporter.php). |
| Fait observé et corrigé sans base | Laravel installé peut reconnecter et rejouer une requête hors transaction : `Connection::handleQueryException/tryAgainIfCausedByLostConnection`. L'INSERT initial du lot est hors transaction. `preventReplay` bloque désormais le reconnector sur la seule connexion d'archive avant sa première requête. Le vrai chemin Laravel d'erreur est testé avec un double PDO : une seule préparation/exécution, aucun rejeu. Une incertitude d'enregistrement produit un état à inspecter, pas une reprise. |
| Fait observé / livré, non exécuté | Le [test MariaDB de lecture](../tests/MariaDB/CatalogueArchiveReadRecipeTest.php) est une recette de lecture d'un lot explicitement autorisé. Le script de recette couvre désormais les scénarios DML et deux processus ; la classe WriteRecipe audite le résultat, sans déclencher de chargement. Les deux classes restent skipped avant boot/accès par défaut. |
| Fait vérifié sans SQL | Neuf fixtures synthétiques distinctes construites et validées en mémoire depuis `CatalogueArchiveFixture`, sans chargement d'accès ; [clés et compteurs](../output/qa-import/op06-fixtures-proposees.json). Aucun de ces lots n'est en base. |
| À vérifier à l'exécution | État actuel du serveur et des droits, authenticité des deux accès dédiés, règles effectives FOR UPDATE avec ces droits, transport PDO réel, rollback et concurrence. |
| Recommandation | Une préparation des outils sans SQL, puis une autorisation d'exécution groupée couvrant droits ciblés et recette complète ; aucun accord demandé à chaque assertion. |
| Limite connue | Gel des lots vérifiés imposé par les écrivains coopérants ; droits SQL/contraintes seuls ne garantissent pas leur immuabilité. Restauration non testée ; OP-07 distincte. |

## 3. Cible et précontrôles obligatoires

Avant tout GRANT puis avant tout DML : vérifier administrativement XAMPP 10.4.28, port 3307, socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, datadir XAMPP, identité des comptes/hôte localhost, cinq tables et DDL conformes à OP-05, journal inchangé, archives initialement vides et homya_local sans objet. Un lot ou droit inattendu arrête la recette ; aucune réutilisation/nettoyage d'un état trouvé.

Vérifier max_allowed_packet global et nouvelle session = 16 777 216, FK/CHECK actifs, InnoDB/utf8mb4_bin/ascii_bin et sessions Laravel strictes ; relever isolation, autocommit, pilote/client PDO, préparation native effective et délais de session. Refuser une configuration mise en cache ou une URL/default substituée. Lire uniquement les deux accès privés test reader/import via les gardes existantes ; secrets uniquement en mémoire, jamais en arguments, reçus, journaux ou rapports.

Le contrôle administratif root est borné aux métadonnées HOMYA et au rapprochement des données de recette. Les écrivains utilisent exclusivement homya_test_import ; le lecteur homya_test_reader. Aucun DML root ou schema. Pas de SQL sur Homebrew/3306, de configuration globale, arrêt de service ou accès au contenu d'autres bases.

## 4. Droits exacts à autoriser — aucune instruction exécutée

Les comptes existent déjà. Conserver intégralement les 24 droits OP-05 et n'ajouter que les neuf instructions suivantes, avec audit avant/après chaque instruction et arrêt sur écart. Aucun compte implicite, motif `*`, ALL ou GRANT OPTION.

```sql
GRANT SELECT ON homya_test.archive_lots TO 'homya_test_reader'@'localhost';
GRANT SELECT ON homya_test.archive_categories TO 'homya_test_reader'@'localhost';
GRANT SELECT ON homya_test.archive_products TO 'homya_test_reader'@'localhost';
GRANT SELECT ON homya_test.archive_translations TO 'homya_test_reader'@'localhost';
GRANT SELECT, INSERT ON homya_test.archive_lots TO 'homya_test_import'@'localhost';
GRANT SELECT, INSERT ON homya_test.archive_categories TO 'homya_test_import'@'localhost';
GRANT SELECT, INSERT ON homya_test.archive_products TO 'homya_test_import'@'localhost';
GRANT SELECT, INSERT ON homya_test.archive_translations TO 'homya_test_import'@'localhost';
GRANT UPDATE (load_state, verification_document, verified_at)
  ON homya_test.archive_lots TO 'homya_test_import'@'localhost';
```

Attendu : **36 privilèges de table et trois privilèges UPDATE de colonne**, non délégables ; contrôle distinct information_schema.TABLE_PRIVILEGES/COLUMN_PRIVILEGES et mysql.tables_priv/columns_priv, sans affichage d'authentification. Les colonnes ne deviennent pas un UPDATE global de la table. Aucun droit sur journal pour reader/import, sur homya_local ou au niveau global/schéma/routine/rôle/proxy. Les deux REFERENCES de test schema restent les seuls de ce type.

Le lecteur doit refuser INSERT/UPDATE même sur une ligne valide ; l'importeur doit refuser UPDATE des enfants, documents et clés. Tests négatifs en transactions avec rollback et vérification de l'absence de mutation si un droit inattendu permet une instruction. Aucun test DROP/ALTER/DELETE/TRUNCATE/REPLACE/GRANT sous prétexte de vérifier leur refus : métadonnées seules pour ces droits, pas de risque de commit implicite.

## 5. Jeux, persistance et conservation proposés

Les neuf clés synthétiques figées figurent dans [op06-fixtures-proposees.json](../output/qa-import/op06-fixtures-proposees.json), dérivées du manifeste après ajout du seul marqueur `OP06-v01-<scenario>` dans les métadonnées de l'index de fixture. Chaque payload contient une catégorie, un produit fictif et six traductions ; prix de fixture, jamais proposé au client. Les mutations invalides sont appliquées aux copies de lignes pour éprouver SQL, sans modifier les sources ni le payload de référence.

| Scénario / clé dans le manifeste | Effet préparé dans les outils, non exécuté |
|---|---|
| integrity, parent-a, parent-b | INSERT/UPDATE contrôlés et mutants dans transactions/sauvegardes de transaction ; aucun commit, rollback final vérifié. |
| transport | Probe de gros documents JSON synthétiques valides avec hash/longueur avant/après PDO, rollback ; puis import complet de la petite fixture par le vrai importeur, un lot verified conservé. |
| failure | Erreur de vérification injectée après chargement des enfants mais avant commit : un parent failed conservé, aucun enfant après rollback ; aucune panne du service. |
| timeout | Une connexion A insère un parent non commité et conserve le verrou ; B tente son enregistrement, délai de session borné ; A rollback, rapprochement administratif et aucun lot conservé. |
| concurrency | Deux processus importeurs indépendants sur la même clé, rendez-vous déterministes ; un seul lot verified conservé avec exactement 1/1/6 enfants, aucune duplication. |
| partial | Parent prepared enregistré et conservé sans enfant ; importeur et lecteur doivent refuser sa reprise/lecture, sans le modifier. |
| hash-collision | Ligne synthétique avec même clé mais documents divergents dans transaction, contrôle `assertLotMatches/assertMatches` doit refuser ; rollback. Collision provoquée de fixture, aucune affirmation de collision SHA-256 réelle. |

À la sortie nominale : **quatre lots synthétiques conservés** (transport/concurrency verified, failure failed, partial prepared). Les autres ne sont pas committés. Aucun DELETE/purge automatique à la fin ; conservation explicite des lignes et preuves, sans durée inventée. Si un scénario ne peut être implémenté dans ces effets, réviser le plan et le reçu avant autorisation, sans agrandir la portée au moment d'exécuter.

Après réussite de ces contrôles seulement, importer en test la capsule réelle déjà figée, clé **48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809**, via l'importeur existant et compte test import, puis comparer avec le lecteur test. Ce chargement réel de catalogue reste **proposé et non autorisé**, même s'il ne bascule pas le site. Re-hacher contenu et médias contre manifeste avant/après ; aucun nouveau téléchargement/copier-coller de contenu.

Attendu capsule d'après simulation OP-04 : 3 709 produits, 65 catégories, 23 878 traductions, 41 977 caractéristiques, 92 variantes, 18 454 associations galerie, 35 overlays, 627 signalements et six marques vides. Le rapprochement est intégral : octets de manifeste, valeurs/types, tableaux/objets, ordre, null/vide/absence et digest du payload, pas seulement compteurs. Vérifier ces compteurs contre la simulation figée lors de préparation finale ; aucun signalement « corrigé » par cet import.

Avec quatre fixtures et cette capsule : cinq lots ; totaux nominaux **67 catégories, 3 711 produits et 23 890 traductions**, journal toujours quatre lignes. Toutes les clés/états exacts, absence d'enfants pour failed/prepared et absence des clés rollback doivent être audités indépendamment. Seul le lot réel complet pourrait être candidat à une future bascule explicitement autorisée ; aucun lot de test sélectionné automatiquement.

## 6. Scénarios et critères d'acceptation

Priorité : préalable pour les gardes/droits ; ensuite intégrité, transaction et concurrence ; transport/rapprochement complet avant toute importation locale. Chaque refus attendu fait partie du scénario ; une erreur non attendue interrompt le lot entier.

| ID | Exigence / cas | Acceptation observable |
|---|---|---|
| OP06-01 | TECH-03 ; cible, accès et habilitations | Identité/session et matrice exactes ; reader/import ne lisent pas migrations ; refus de cible locale avant chargement d'accès. Aucune permission élargie silencieusement. |
| OP06-02 | CON-001/004 ; PK et UNIQUE | Doublons lot, (lot,slug), (lot,index_position), (lot,locale,source_key/source_position) rejetés ; variante identique dans un autre lot admise lorsque prévue. État identique après rollback. |
| OP06-03 | CON-004 ; références et RESTRICT | Parent absent refusé pour catégorie/produit/traduction ; produit du lot B ne peut utiliser une catégorie présente uniquement en A. Quatre FK/actions RESTRICT restent conformes à OP-05 ; pas de DELETE de parent pour éprouver RESTRICT, exclusion de permission préservée. |
| OP06-04 | CON-005 ; domaines/NULL/bornes | Clés 63/65 caractères, uppercase/non-hex, slugs vides/222 caractères/underscore, locale hors en/de, devise hors EUR, univers hors liste, positions/prix 0/négatifs et dépassements INT rejetés au niveau pertinent. Bornes 64/221, marque vide et ancien prix NULL admis ; aucun prix/ancien prix lié par une règle inventée. |
| OP06-05 | CON-005/006 ; JSON et états | JSON malformé refusé dans chacun des cinq champs ; JSON valides conservés. format_version hors 1, état inconnu, verified sans preuve/date ou nonverified avec preuve/date rejetés. Preuve JSON valide mais mensongère refusée par application, pas attribuée au CHECK. |
| OP06-06 | CON-006/008 ; fidélité | UTF-8 français/allemand/€, objets/tableaux vides, NULL, clé numérique de traduction, float/entier et ordre reconstitués ; TEXT mesuré en octets ≤65 535, dépassement refusé sans troncature par garde/session. Documents divergents refusés et rollback prouvé. |
| OP06-07 | IMP-08 ; interruption | Erreur injectée avant commit : enfants non persistés, failed sûr ou état à inspecter, pas de lecteur sur lot incomplet. Jamais conclure « aucune écriture » après enregistrement parent ou commit indéterminé. |
| OP06-08 | IMP-08/09 ; concurrence | Deux PID et CONNECTION_ID différents, événements de rendez-vous et verrous horodatés ; aucun test fondé seulement sur sleep. Une importation complète, second déjà verified ou refus contrôlé si course prepared ; pas de double enfant, dégradation ou reprise automatique. Nouveau passage sur verified : valeurs/preuves/dates inchangées. |
| OP06-09 | TECH-07 ; attente bornée | À autoriser : `SET SESSION innodb_lock_wait_timeout=5` uniquement sur connexions de recette, puis lecture de la valeur ; erreur 1205 attendue pour scénario verrou, rollback explicite et audit. Aucun SET GLOBAL ni KILL/arrêt MariaDB. |
| OP06-10 | CON-007 ; gel applicatif | Second load du même verified ne change aucun octet/date ; les méthodes d'état refusent sa mutation. Aucun test ne prétend qu'un utilisateur SQL autorisé aux colonnes techniques est bloqué par un trigger inexistant. |
| OP06-11 | TECH-07/IMP-06 ; transport | PDO natif observé, mêmes longueurs/hashes JSON en émission/réception, pas de troncature/transformation, aucune hausse du paquet. Probe restant sous budget16Mio ; dépassement du budget refusé hors SQL, pas d'envoi destiné à casser la connexion. |
| OP06-12 | IMP-06/09 ; catalogue réel inactif | Capsule/manifeste/médias inchangés ; mêmes digest, valeurs et compteurs via lecteur SQL ; journal/schéma/droits restent exacts. Catalogue web sur fichiers, aucune activation. |
| OP06-13 | Arrêt/erreurs | Refus par défaut avant boot/accès/SQL sans reçu ; journaux sans paramètres, messages SQL bruts ou secrets. Hors scénario attendu : stop, diagnostic lecture seule, aucune purge/rejeu/REVOKE automatique. |

Les dates impossibles et erreurs d'accès sont validées contre le code et la session observée ; capturer SQLSTATE/code numérique sans publier requête/bindings. Contrôles de fuite et de commit indéterminé d'abord par injection sans base. Une vraie rupture réseau/commit ou un test deadlock destructif n'est pas requis pour cette recette ; si absent, signaler explicitement cette limite.

## 7. Outils livrés et commandes

| Fichier | Travail livré ; preuve sans base et limite |
|---|---|
| `app/Services/CatalogueArchiveImporter.php` | Garde anti-rejeu avant toute requête, commune aux appels de l'importeur ; échec d'enregistrement incertain arrêté. Tests sur le vrai chemin Connection Laravel avec PDO simulé. Aucun patch vendor/DB manager global. Commande import existante conservée. |
| `scripts/op06_xampp_admin.py` | Reçu fermé lié au plan/code, contrôle des neuf stades de droits et colonnes, DDL/journal/compteurs, verrou et supervision. Dix tests Python isolés, aucun SQL réel. |
| `scripts/op06_recipe.php`, `scripts/op06_common.php`, `tests/Support/Op06Recipe.php` | Jeux et mutations exactes, transactions/savepoints/rollback, droits négatifs, transport, erreur injectée, parent partiel, timeout et deux travailleurs. Deux tests de protocole avec processus PHP fictifs ; ils ne prouvent pas les verrous MariaDB. |
| `tests/MariaDB/CatalogueArchiveReadRecipeTest.php`, `CatalogueArchiveWriteRecipeTest.php` | Lecture/rapprochement et audit administratif d'une recette terminée. Reçu commun obligatoire ; skipped avant boot/accès par défaut. L'écriture n'est déclenchée que par l'enveloppe CLI autorisée. |
| Tests Unit/Feature et `scripts/test_op06_xampp_admin.py` | Refus par défaut, empreintes/cible/droits excessifs, anti-rejeu, budgets, événements/PID et lignes incomplètes. Aucun PDO réel ou accès dédié. |

Enveloppe unique : reçu privé0600/propriétaire/chemins sans symlink, décision enregistrée, empreintes du plan et liste fermée des fichiers, état initial et clés attendues. Le verrou local `.op06.lock` est détenu par l'orchestrateur ; ses deux travailleurs utilisent chacun une connexion et le verrou de ligne parent. Toute erreur inattendue arrête sans retry, purge ou REVOKE automatique. Ne laisser aucune autre application écrire dans la cible pendant la recette.

[Programme figé sans SQL](../output/qa-import/op06-programme.json) : neuf clés, mutants exacts par colonne, probe4Mio/2Mio, quatre lots persistants. [Empreintes préparées](../output/qa-import/op06-empreintes-preparation.json) : plan et liste fermée des outils/sources ; **ce fichier n'est pas un reçu d'autorisation**. Le reçu sera émis seulement après accord humain et enregistrement central, puis revalidé avant chaque étape.

Limites techniques préparées : attente InnoDB5s en session, rendez-vous10s, constat administratif d'attente3s ; plafond de supervision global1800s, sondes1s, PHP CLI512M pour le chargement de capsule (simulation~256Mio). Un timeout global arrête uniquement le groupe de processus PHP lancé par cette enveloppe, jamais MariaDB ; l'état doit ensuite être inspecté. Pas de plafond global60s promis pour tous les contrôles courts, ni de performance commerciale déduite.

Commandes **sans base**, exécutables maintenant depuis le dépôt :

```sh
/usr/bin/python3 scripts/op06_xampp_admin.py
/opt/homebrew/bin/php scripts/op06_recipe.php --prepare
/usr/bin/python3 scripts/test_op06_xampp_admin.py
/opt/homebrew/bin/php vendor/bin/phpunit --no-progress
/opt/homebrew/bin/php vendor/bin/phpunit -c phpunit.mariadb.xml --no-progress
```

La dernière commande reste désactivée avant accès sans drapeaux/accord. Aucun RefreshDatabase, DatabaseMigrations, seeder ou nettoyage automatique ajouté.

Commande **future, interdite avant accord SQL groupé et reçu correspondant** :

```sh
/usr/bin/python3 scripts/op06_xampp_admin.py --execute \
  --receipt=/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/op06-autorisation.json \
  --import-access=/Users/akm/Documents/ChatGPT/HOMYA/storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_test_import \
  --reader-access=/Users/akm/Documents/ChatGPT/HOMYA/storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_test_reader
```

Ces arguments sont des chemins de fichiers privés, pas des secrets. Le lanceur appelle PHP avec `-d memory_limit=512M` et crée son nouveau dossier de preuves privé. L'entrée interactive confirme le périmètre déjà autorisé ; elle ne remplace ni l'accord humain préalable ni son reçu. Aucun reçu réel présent dans cette livraison.

## 8. Ordre du futur lot autorisé et preuves

1. Consigner l'accord exact au registre central, figer plan/manifeste/outils et reçu ; audit administratif frais et source/DDL inchangés.
2. Appliquer neuf GRANT ciblés, contrôler chaque stade et connexion des deux rôles ; arrêter sur écart, sans réexécuter des GRANT déjà passés.
3. Scénarios transactionnels/permissions, probes PDO et import de petite fixture ; auditer chaque rollback et témoin de mutation.
4. Panne injectée, parent prepared/refus de reprise, timeout et deux importeurs ; conserver quatre lots synthétiques prévus. Les erreurs prévues ne masquent aucune autre erreur.
5. Seulement si ces recettes sont réussies : capsule complète dans homya_test, import inactif, rapprochement via reader et re-hachage des médias.
6. Audit root indépendant : cinq tables/journal/droits, clés/états/comptes exacts, totaux attendus et homya_local vide. Contrôle code/config/source/capsule inchangés ; documenter résultats et limites, sans déclencher OP-07 ou une bascule.

Rapports mode0600 dans un nouveau sous-dossier QA OP-06 : source d'accord/empreintes, état initial, matrice avant/après chaque GRANT, assertions et codes expurgés par ID, PID/connexions/rendez-vous, tailles/digest/durée/mémoire observés, compteurs par clé, audit final et conservation. La sauvegarde système historique n'est pas une preuve de restauration de cette recette. Pas de contenu produit privé, secrets ou dump d'authentification dans les rapports.

## 9. Questions externes vérifiées et portée

Questions : quand FOR UPDATE verrouille-t-il réellement ; que couvre ROLLBACK ; un timeout annule-t-il toute la transaction ; que signifie la limite de paquet et comment lier les paramètres PDO ? Sources primaires consultées le **08/10/2026**, portée documentaire générale, pas preuve d'exécution du XAMPP 10.4.28 :

| Titre / URL | Fait publié étayé et décision de recette |
|---|---|
| [FOR UPDATE — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update) | Verrou transactionnel InnoDB ; transaction explicite nécessaire. Vérifier le blocage avec deux connexions réelles, sans présumer les permissions effectives. |
| [InnoDB System Variables — innodb_lock_wait_timeout](https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-system-variables#innodb_lock_wait_timeout) | Variable dynamique de session/global ; timeout1205 annule l'instruction par défaut. Proposer5s en session de recette et rollback explicite, sans changement global. |
| [ROLLBACK — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/transactions/rollback) | Annule les changements de la transaction, pas les opérations DDL/GRANT à commits implicites. Séparer habilitation, DML transactionnel et importeur avec parent persistant. |
| [Server System Variables — max_allowed_packet](https://mariadb.com/docs/server/server-management/variables-and-modes/server-system-variables#max_allowed_packet) | Limite de messages/chaînes ; session non modifiable. Observer nouvelle session16Mio et transport réel sans hausse automatique. |
| [Prepared statements and stored procedures — PHP PDO](https://www.php.net/manual/en/pdo.prepared-statements.php) | Paramètres liés séparément, préparation dépendant du pilote/mode. Contrôler mode natif et chaînes reçues, pas interpoler les valeurs. |
| [Connection.php installé](../vendor/laravel/framework/src/Illuminate/Database/Connection.php), lignes1011/1033/1468 ; [Connector.php](../vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php) | Source locale primaire : reconnexion/rejeu hors transaction et option native par défaut. Conclusion : garde contre rejeu livrée et éprouvée sans base ; option par défaut ne vaut pas preuve de la session PDO réelle. |

**Interprétations/recommandations** : scénarios et délais de recette ci-dessus, indépendants des faits publiés. **À vérifier** : droits FOR UPDATE, résultats de chaque contrôle sur la version réelle, durée/mémoire/mode client et transport de la capsule. Aucune certification de sécurité, conformité ou performance.

## 10. Vérification du complément et autorisation à présenter

Développement autorisé sous DECISION-031, achevé sans base. Résultats : **154 tests Laravel / 1 043 assertions**, dix tests Python, syntaxe et Pint vérifiés ; deux tests MariaDB skipped avant boot/accès. La première régression152tests et la régression finale après ajout du protocole restent conservées dans leurs rapports. Un lancement Python via unittest module a échoué sur le chemin d'import ; lancement direct documenté réussi, aucun SQL impliqué.

Voir [contrôles finaux et témoins](../output/qa-import/op06-complement-controles-finaux.json). Anti-rejeu testé avec PDO simulé, rendez-vous avec processus PHP fictifs, métadonnées/GRANT avec doubles. **Aucune preuve d'intégrité SQL, rollback, transport natif ou concurrence réelle produite par ces tests.** Les preuves OP-05 sont historiques : renouvellement obligatoire avant toute écriture. Capsule/médias non re-hachés intégralement pendant ce complément ; cette relecture fait partie de la future recette.

Les seules modifications applicatives existantes sont l'importeur et ses tests ; routes, vues, langues, styles, configuration, migrations et .env conservés par témoins. Source publique toujours sur fichiers, aucun accès dédié chargé, SQL, droit, service, configuration XAMPP ou import exécuté. Les outils sont en dehors du parcours public et aucun tag/service commercial ajouté. Restauration non testée ; OP-07 reste distincte.

**Blocage avant SQL :** accord groupé d'exécution absent. Les précontrôles réels restent à vérifier sous ce futur accord et peuvent arrêter l'exécution ; aucun état trouvé ne sera nettoyé ou repris automatiquement.

**Demande groupée :** autoriser les neuf GRANT ciblés de §4, les scénarios avec quatre lots synthétiques conservés, puis l'import inactif de la seule capsule identifiée dans homya_test après réussite, et les vérifications administratives/reader associées, avec les limites et arrêts décrits. Ce lot ne couvre pas homya_local, suppression/cleanup, restauration, migration, modification XAMPP/service, activation du site, paiement, publicité ou déploiement. Un seul accord couvre ce périmètre précis ; pas de nouvelle validation à chaque test.

## 11. Exécution autorisée puis interrompue — 08/10/2026

Accord utilisateur « j'autorise » enregistré et relu sous **DECISION-032** avant SQL. Reçu mode0600 lié au plan v0.2/outils/clé émis puis validé ; témoins de conservation figés. L'enveloppe a exécuté un seul passage, sans aucun retry, purge ou reprise.

Précontrôle réel renouvelé : XAMPP10.4.28/socket/datadir/3307, paquets16Mio, cinq tables/journal conformes, quatre archives vides, homya_local sans objet. Neuf GRANT appliqués une fois, chaque stade contrôlé. Résultat administratif : **36 seuls droits de table et trois UPDATE de colonne**, non délégables, six comptes et exclusions conservés.

| Contrôle | Fait vérifié / limite |
|---|---|
| Intégrité | 55 refus SQL attendus et un contrôle positif des bornes ; transactions annulées, PK/UNIQUE/FK/domaines/JSON/NULL/catégorie autre lot éprouvés selon programme. Aucun test DELETE de RESTRICT effectué. |
| Collision documentaire | Documents synthétiques divergents refusés, rollback réussi ; aucune collision SHA réelle prétendue. |
| Transport | PDO mysqlnd8.5.11 natif, session REPEATABLE-READ/autocommit1/lockwait5/paquet16Mio. JSON de 4 194 306 et 2 097 154octets reçus identiques ; probe annulée. |
| Petit import | Seule fixture transport verified, 1catégorie/1produit/6traductions ; second passage inchangé. Produit fictif, aucune publication. |
| Permissions | Étape passée avant l'échec selon ordre du code, mais son rapport combiné n'a pas été écrit ; ne pas compter cette étape comme clôturée indépendamment. Refus à revérifier lors de la reprise. |
| Échec injecté | Arrêt `FAILURE_UNEXPECTED_RESULT`. Diagnostic sans base : callback renvoyait `synthetic=true` sans `lot_key`/`sql_executed=false` ; la garde refusait avant tout INSERT du parent failure. Même défaut dans callback de concurrence, non encore exécuté. Défaut de l'outil de test, pas modification nécessaire de MariaDB. |
| État après arrêt | Diagnostic administratif SELECT : un seul lot transport verified, totaux1/1/1/6 ; aucune clé failure/partial/concurrency/capsule réelle. DDL/journal/droits/homya_local exacts. Aucune écriture de diagnostic. |
| Non exécuté | Échec/rollback du vrai scénario injecté, parent partiel, timeout et concurrence réels, capsule complète, lecture finale et restauration. Aucun import du catalogue réel, activation ou service/configuration modifié. |

[Run conservé](../output/qa-import/op06-run-20261008T120215Z-4b93cf67-ad1e-4bc4-8c4d-da9940f9b8b0/status.json), [diagnostic administratif](../output/qa-import/op06-run-20261008T120215Z-4b93cf67-ad1e-4bc4-8c4d-da9940f9b8b0/diagnostic-admin-ro.json) et [synthèse de phase](../output/qa-import/op06-execution-synthese.json). Les fichiers/outils de ce premier passage sont sauvegardés ; son reçu reste conservé, ne correspond plus au code corrigé et ne doit pas être réémis/écrasé.

Correction sans base : `Op06Recipe::instrumentedPayload` fournit le contrat complet de vérification d'une fixture et conserve les hooks d'erreur/barrière. Garde applicative, clé/manifeste/données de chaque fixture et programme de mutations inchangés. Ajout de tests des deux callbacks et de la méthode failureAndPartial avec vrais query builders/doubles, sans PDO ; branche de continuation et gardes d'état prêtes. **157 tests Laravel / 1 054assertions, douze tests Python**, syntaxe et Pint réussis. Pas de nouvelle recette SQL après l'arrêt.

## 12. Reprise ciblée proposée — nouvel accord nécessaire

Le plan approuvé impose arrêt sans reprise automatique au premier écart : une continuation du code corrigé requiert un **accord distinct sur ce périmètre concret**. Ce point est requis par l'arrêt validé, pas par une autorisation manquante des neuf GRANT déjà effectués.

Préparation figée : [état/proofs du premier run](../output/qa-import/op06-reprise-etat-fige.json), [empreintes du code corrigé et du plan](../output/qa-import/op06-reprise-empreintes-preparation.json). Aucune autorisation d'exécution dans ces fichiers. Le reçu futur `op06-reprise-autorisation.json` sera émis uniquement après enregistrement/relecture de l'accord distinct ; l'ancien reçu est exclu.

La continuation accepte uniquement le run figé et tous ses fichiers inchangés, le même schéma/journal/six comptes, 36droits de table +3colonnes exacts et le seul lot transport verified avec compteurs1/1/1/6. Relecture intégrale du payload transport via reader avant tout DML, digest/compteurs identiques. Toute ligne, état, droit, preuve ou document inattendu arrête ; aucune normalisation ou nettoyage.

Ordre limité à autoriser :

1. Audit administratif frais de cet état exact et relecture du seul transport conservé ; nouveau dossier de preuves/parent/verrou commun. Aucun GRANT.
2. Revérifier les refus de permissions dans transactions annulées ; aucune répétition de l'intégrité/collision/probe/import transport déjà achevés.
3. Exécuter le scénario failure corrigé, parent partial, timeout et deux travailleurs concurrence. Ces trois nouveaux lots persistants s'ajoutent au transport : quatre fixtures totales, mêmes clés/états/effets que le plan approuvé. Les jeux timeout restent annulés.
4. Uniquement après réussite : importer la seule capsule réelle figée, rapprocher intégralement avec reader, vérifier les médias avant/après et audit administratif final. Attendus inchangés : cinq lots, 67catégories, 3 711produits, 23 890traductions ; quatre lignes journal, homya_local vide.
5. Arrêt sur écart, aucune reprise automatique ou purge ; conserver preuves/lignes. Même limites de session, PHP512M et supervision1800s ; site sur fichiers.

Commande **préparée, non exécutée ; interdite avant accord distinct et nouveau reçu** :

```sh
/usr/bin/python3 scripts/op06_xampp_admin.py --execute \
  --resume-from=/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/op06-run-20261008T120215Z-4b93cf67-ad1e-4bc4-8c4d-da9940f9b8b0 \
  --receipt=/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/op06-reprise-autorisation.json \
  --import-access=/Users/akm/Documents/ChatGPT/HOMYA/storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_test_import \
  --reader-access=/Users/akm/Documents/ChatGPT/HOMYA/storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_test_reader
```

Aucun nouveau GRANT, base/compte/table, migration, suppression/nettoyage/restauration, réglage XAMPP/service, homya_local, activation du site, paiement ou déploiement. La sauvegarde système historique reste non testée en restauration. Si l'état exact diffère lors de l'audit frais, la continuation est refusée même avec accord.

## 13. Reprise autorisée et clôture — 08/10/2026

Accord « j'autorise » consigné/relu sous **DECISION-033** avant continuation. Reçu et run initiaux conservés, nouvel accord/empreintes/proofs validés. Précontrôle frais conforme au seul transport verified et droits36table/3colonnes ; aucune étape achevée ou habilitation rejouée.

La [synthèse vérifiée](../output/qa-import/op06-reprise-synthese-verification.json) rapproche le premier passage et sa continuation. Résultat réel :

- Permission reader/import/journal refusée comme prévu ; échec injecté annulant tous les enfants avec parent failed ; parent prepared inchangé et illisible ; timeout1205 natif sous5s avec rollback et absence finale du lot timeout.
- Concurrence : PID7303/7311, CONNECTION_ID1299/1300 ; attente InnoDB confirmée administrativement avant libération du premier travailleur. Premier verified, second déjà verified inchangé, un seul jeu d'enfants.
- Capsule réelle vérifiée et importée par le compte dédié, puis relue intégralement par reader : **3 709produits, 65catégories, 23 878traductions, 41 977caractéristiques, 92variantes, 18 454associations galerie, 35overlays, 627signalements et six marques vides**. Digest identique à la simulation ; aucun signalement corrigé ou supprimé par l'import.
- Les quatre lots synthétiques restent isolés par clés : deux verified avec1/1/6enfants chacun, un failed et un prepared sans enfant. Totaux SQL : **cinq lots, 67catégories, 3 711produits, 23 890traductions**. Aucun lot annulé présent, journal quatre lignes exactes et schéma conforme ; droits36table/3colonnes non délégables, six comptes, homya_local sans objet.
- Contrôle indépendant complet des originaux et de la copie : **22 239fichiers, 3 477 829 369octets par jeu, tailles/SHA256 identiques**, manifeste exact. [Preuve](../output/qa-import/op06-reprise-verification-fichiers-independante.json). Les vérifications applicatives de capsule avant/après passent aussi.

Mesures du run, pas promesses : compteur103,302s depuis le début du chargement jusqu'à la relecture/vérification finale (inclut vérifications de fichiers, pas durée SQL seule) ; pic PHP351 240 192octets (~335Mio) sous512M. Limites de session/paquet/globales conservées.

**Tests d'intégration finaux : deux tests MariaDB / cinq assertions, aucun échec, erreur ou risque PHPUnit.** [JUnit de clôture](../output/qa-import/op06-reprise-tests-mariadb-cloture.xml) et journal d'événements conservés. Défaut skipped avant accès recontrôlé. Treize tests Python des outils/audits sans base, syntaxe Python/PHP et Pint réussis ; régression sans base157tests/1 054assertions antérieure conservée, non relancée sans changement applicatif.

Incidents complémentaires de vérification conservés : première relecture SQL passée avec avertissement de gestionnaires globaux Laravel ; teardown du seul test de lecture corrigé. Un reçu de vérification distinct ne correspondait plus au reçu source du run, garde d'audit refusée avant SQL. Liaison de lecture seule corrigée : reçu original/accord DECISION-033 et sauvegardes des deux fichiers concernés attestés par empreintes ; toute dérive supplémentaire de l'importeur/configuration/etc. reste refusée, tests négatifs réussis. Dernière recette2tests/5assertions entièrement passée après ces corrections. Aucune nouvelle instruction DML/GRANT, migration ou import lors de ces relectures. Reçus intermédiaires conservés, jamais réécrits ; garde de provenance réservée à cette vérification de DECISION-033.

Conservation avant clôture documentaire : **125/127 témoins inchangés** ; seules exceptions : test de lecture (isolation) et outil administratif (liaison de l'audit en lecture seule). Test Python de cette liaison ajouté. Après clôture, plan et note locale évoluent aussi. Importeur, lecteur, payload, routes/vues/langues/styles/configuration/.env/migrations/my.cnf et sources/médias conservés. Le site continue à lire les fichiers ; aucun nouveau droit, service/configuration XAMPP, compte/base/table, migration, nettoyage/restauration, paiement ou déploiement.

Limites explicites : restauration non testée, OP-07 distincte ; rupture réseau/commit incertain/deadlock réels non provoqués (gardes testées avec doubles), pas de DELETE pour éprouver RESTRICT, pas de trigger d'immuabilité affirmé. Archivage fidèle vérifié ne vaut pas validation des règles commerciales prix/stock/commande/paiement, ni activation SQL publique. Les treize critères et leurs limites sont reliés aux preuves dans la synthèse.

**Prochaine étape :** préparer OP-07 (sauvegarde et recette de restauration isolée), avec son plan et ses autorisations avant toute opération ; la bascule du site ou homya_local conserve son propre périmètre. Aucun accord implicite OP-07 ou activation acquis par cette clôture.
