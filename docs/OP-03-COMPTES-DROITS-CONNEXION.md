# HOMYA — OP-03 : comptes locaux, droits et connexion Laravel

Version 0.1 — plan du 07/10/2026, suivi actualisé. **OP-03a ET OP-03b EXÉCUTÉES ET VÉRIFIÉES.**

La préparation seule n'autorisait pas la création. L'accord OP-03a reçu ensuite, enregistré et relu dans DECISION-025 du [registre central](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), couvre les six comptes sans privilèges et leurs tests d'authentification seulement. DECISION-019 reste la validation de conception. Le [plan exact soumis](../output/qa-import/op03a-plan-autorise.md) et la [preuve d'autorisation](../output/qa-import/op03a-autorisation.json) sont conservés ; aucun registre parallèle.

L'accord distinct « ACCORD AUTORISEE » porte sur OP-03b présentée immédiatement auparavant : dix GRANT CREATE/ALTER de table, aux deux seuls comptes schema. DECISION-026 enregistrée et relue avant exécution ; [plan exact OP-03b](../output/qa-import/op03b-plan-autorise.md) et [accord](../output/qa-import/op03b-autorisation.json) conservés. Aucun accord de code/migration/import ou de bascule ajouté.

Références : [plan technique v0.2, §4 et §10](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md), [OP-02 clôturée](OP-02-CREATION-BASES-VIDES.md), [environnement local](ENVIRONNEMENT-LOCAL.md). Traçabilité : TECH-03/04, IMP-03 ; OP-03a puis OP-03b. OP-04 et les opérations suivantes conservent leurs propres autorisations.

## 1. État vérifié et limites

| Élément | Observation / statut | Preuve |
|---|---|---|
| Cible | MariaDB XAMPP 10.4.28 ; port 3307 ; socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock` ; datadir XAMPP ; écoute 127.0.0.1 | [SELECT administratif ID76](../output/qa-import/op03-inspection-xampp.json), 22:06 UTC |
| Bases | homya_local et homya_test : utf8mb4/utf8mb4_bin ; zéro table/vue/routine/événement | Même SELECT |
| Comptes proposés | Zéro collision pour les six noms, tous hôtes confondus dans mysql.user ; à recontrôler juste avant création | Même SELECT ; aucun champ d'authentification lu |
| Paramètres | 16 Mio global/session ; FK/CHECK actifs ; mysql.proc21 ; événements OFF | Même SELECT |
| Administration | Quatre privilèges nécessaires SELECT/CREATE USER/CREATE/ALTER déclarés délégables ; ce n'est pas une épreuve de CREATE USER/GRANT | Même SELECT |
| Authentification / journaux | mysql_native_password actif ; general_log, slow_query_log, log_bin désactivés ; zéro plugin AUDIT actif observé | Même SELECT ; ne certifie pas l'absence de toute trace extérieure |
| Laravel | PHP CLI 8.5.11, pdo_mysql chargé ; environnement local, connexion mariadb, socket exact ; identifiants absents et DB_URL absent | [Profil filtré](../output/qa-import/op03-inspection-laravel.json), sans SQL ni valeurs privées |
| Écarts Laravel | Port effectif différent de 3307 ; collation de connexion utf8mb4_unicode_ci ; aucune modification appliquée | Même profil ; voir §5 |
| Catalogue / tests | Source catalogue sur fichiers ; sessions/cache sur fichiers, queue synchrone ; tests existants protégés d'une base réelle | config/catalogue.php, profil filtré, phpunit.xml et tests relus |

Le premier contrôle CLI a été refusé dans le sandbox (2002). La reprise autorisée hors sandbox a exécuté uniquement un SELECT de métadonnées. Aucun compte, droit, table, migration, import, arrêt, réglage XAMPP ou changement .env dans cette phase.

Ce tableau décrit la préparation initiale. **État après OP-03a :** les six comptes existent à localhost, sans privilège réel, rôle ou délégation ; six authentifications PDO et six avec le connecteur Laravel installé réussies sans base sélectionnée. Les 24 tentatives USE des deux bases ont été refusées1044. Contrôle indépendant CLI ID101 : cible XAMPP inchangée, deux schémas toujours vides, droits de schéma/table/colonne/routine et proxy absents. [Preuve indépendante](../output/qa-import/op03a-verification-independante.json).

**État après OP-03b :** les dix GRANT ont réussi sur les noms de tables futures. La matrice exacte de 20 privilèges de table CREATE/ALTER non délégables est vérifiée dans le serveur installé ; aucun droit réel global/de schéma/DML ni rôle/proxy ajouté, lecteurs/importeurs à USAGE. Les deux bases restent sans objet. Voir §4 pour les preuves ; l'audit à zéro droit du paragraphe précédent décrit la clôture OP-03a avant ces GRANT.

**À vérifier sous les accords suivants :** exécution du DDL, contraintes et droits DML. La restauration de la copie physique A reste non testée ; elle précède ces comptes et ne constitue pas une annulation garantie d'OP-03. Aucun accès runtime lecteur au schéma déclaré fonctionnel.

## 2. Comptes et habilitations par étape

Les noms et l'hôte sont des **décisions validées de conception**. Un compte de base de données n'est pas un compte client du site.

| Compte @localhost | Schéma associé | OP-03a | OP-03b | Droits de données différés |
|---|---|---|---|---|
| homya_test_reader | homya_test | USAGE seulement | Aucun ajout | SELECT sur les quatre tables d'archive, OP-06 |
| homya_test_import | homya_test | USAGE seulement | Aucun ajout | SELECT/INSERT archive + UPDATE de trois colonnes du lot, OP-06 |
| homya_test_schema | homya_test | USAGE seulement | CREATE/ALTER sur cinq tables nommées | SELECT/INSERT du journal après création, OP-05 |
| homya_local_reader | homya_local | USAGE seulement | Aucun ajout | SELECT archive, OP-08 |
| homya_local_import | homya_local | USAGE seulement | Aucun ajout | SELECT/INSERT archive + UPDATE de trois colonnes du lot, OP-08 |
| homya_local_schema | homya_local | USAGE seulement | CREATE/ALTER sur cinq tables nommées | SELECT/INSERT du journal après création, OP-08 |

Quatre tables d'archive : archive_lots, archive_categories, archive_products, archive_translations. Cinquième table technique : migrations. L'importeur ne pourra modifier que load_state, verification_document, verified_at dans archive_lots. Le gel des lots vérifiés dépend aussi du code et du verrou approuvés, pas des seuls droits SQL.

Aucun compte distant, `%`, rôle partagé, privilège global/de schéma, GRANT OPTION, DROP, DELETE, FILE, PROCESS ou SUPER. Aucun lecteur/importeur n'accède au journal ou au schéma voisin. Aucun compte administrateur ne sera repris dans Laravel.

## 3. OP-03a autorisée et exécutée : six comptes sans droits initiaux

**Périmètre exécuté — DECISION-025 :** six CREATE USER individuels, secrets distincts générés localement, puis contrôle des identités, privilèges et authentifications. Pas de GRANT ni d'édition du .env applicatif. Pas de base/table supplémentaire, migration, import ou bascule.

Procédure appliquée par le helper opérationnel contrôlé, hors code applicatif :

1. Relire l'accord et son empreinte, recontrôler socket/version/datadir/bases vides, comptes et habilitations orphelines portant ces noms. Toute collision, privilège trouvé ou cible différente arrête l'opération ; pas de réutilisation.
2. Créer un nouveau dossier privé hors public/, sous `storage/app/private/database-access/<UTC-UUID>/`, sans lien symbolique ni réutilisation. Mode 0700, fichiers 0600, propriétaire utilisateur, absence d'ACL élargissant l'accès, exclusion Git vérifiée. Conserver les secrets dans six fichiers .env privés distincts ; aucune valeur dans les preuves.
3. Générer chaque secret avec un générateur cryptographique (32 octets aléatoires, encodés en 64 caractères hexadécimaux). Sauvegarder les accès protégés avant le premier CREATE ; ne jamais les afficher, exporter dans l'environnement shell, placer dans argv, le navigateur ou l'historique SQL.
4. **Recommandation technique locale :** authentification mysql_native_password explicite, déjà active, sans installation de plugin. Calculer localement son empreinte d'authentification ; transmettre la requête uniquement en mémoire sur la connexion administrative par socket. L'empreinte est elle aussi sensible et ne doit pas être journalisée. Ce choix ne vaut pas recommandation de sécurité pour un hébergement futur.
5. Créer un compte à la fois, dans l'ordre du tableau. Contrôler chaque résultat avant le suivant. Ni IF NOT EXISTS, ni OR REPLACE, ni ALTER USER, ni FLUSH PRIVILEGES, ni modification directe de mysql.global_priv. Arrêt au premier écart.
6. Tester chaque authentification PDO puis avec le connecteur Laravel installé dans un processus éphémère : socket explicite, aucune base sélectionnée, secrets chargés directement depuis le fichier privé. Ne pas changer la connexion par défaut ni écrire de cache de configuration.
7. Relire administrativement les seules métadonnées des six identités : plugin, hôte, absence de rôle/délégation/droits de schéma/table/colonne/routine ; ne pas extraire les chaînes d'authentification. Publier seulement résultats booléens, noms prévus, codes d'erreur filtrés et empreintes des preuves non sensibles.

Gabarit documentaire, **non exécutable tel quel**, répété pour les six noms :

```sql
CREATE USER 'homya_test_reader'@'localhost'
  IDENTIFIED VIA mysql_native_password USING '<empreinte sensible calculée localement>';
```

USAGE signifie absence de privilège réel ; aucun GRANT USAGE nécessaire. Une réussite partielle laisse les comptes déjà créés et leurs secrets protégés en place. Ne pas recommencer la série ni supprimer/révoquer automatiquement ; produire l'inventaire expurgé et préparer une reprise explicitement autorisée.

**Contrôles de transport réussis :** identité CURRENT_USER attendue, DATABASE() NULL, MariaDB10.4.28/3307/socket/datadir exacts, 16 Mio, utf8mb4/utf8mb4_bin et six modes stricts attendus, dans les douze connexions. Les SET restent de session ; aucun changement global. Les refus USE ont été contrôlés sans DML. Le DSN à base vide du connecteur Laravel installé a fonctionné avec les six comptes.

Exécution du helper à empreinte approuvée : 22:26:10–22:26:13 UTC, sans écart ; [résultat détaillé expurgé](../output/qa-import/op03a-resultat-script.json). Six fichiers .env distincts dans un nouveau dossier privé `storage/app/private/database-access/`, dossier0700/fichiers0600, propriétaire utilisateur, pas d'ACL élargie ni de lien symbolique, Git ignore vérifié. Aucun secret affiché. Le .env applicatif et les 3 868 témoins ont été contrôlés inchangés. Le contrôle indépendant a relu les permissions/ACL/exclusions et les preuves d'authentification, sans lire les valeurs secrètes.

Contrôles avant exécution : syntaxe PHP et [14 tests isolés](../output/qa-import/op03a-tests-helper.json) sans SQL. Première inscription au registre interrompue avant écriture : os.listxattr indisponible dans le Python utilisé ; reprise avec xattr macOS, empreinte d'origine, sauvegarde/métadonnées et relecture vérifiées. Aucun compte créé pendant cet incident documentaire.

## 4. OP-03b autorisée et exécutée : dix GRANT ciblés

Préconditions recontrôlées : OP-03a vérifiée, six identités localhost conformes, sans rôle/proxy/délégation ni droit initial ; bases vides. Avant et après chaque GRANT, audit et matrice exacte correspondant au nombre d'instructions réussies. NO_AUTO_CREATE_USER présent et contrôlé dans la session ; aucun SET nécessaire et mode SQL global conservé.

**Dix instructions exécutées une fois chacune — DECISION-026 :**

```sql
GRANT CREATE, ALTER ON `homya_test`.`archive_lots` TO 'homya_test_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_test`.`archive_categories` TO 'homya_test_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_test`.`archive_products` TO 'homya_test_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_test`.`archive_translations` TO 'homya_test_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_test`.`migrations` TO 'homya_test_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_local`.`archive_lots` TO 'homya_local_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_local`.`archive_categories` TO 'homya_local_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_local`.`archive_products` TO 'homya_local_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_local`.`archive_translations` TO 'homya_local_schema'@'localhost';
GRANT CREATE, ALTER ON `homya_local`.`migrations` TO 'homya_local_schema'@'localhost';
```

CREATE sur une table future est documenté. **Interprétation du code MariaDB10.4.28 :** le contrôle d'existence est contourné lorsque CREATE figure dans les privilèges demandés. **Observation réelle désormais vérifiée :** le binaire XAMPP a accepté les dix instructions combinées CREATE/ALTER sur les tables encore absentes. Cela ne prouve pas une future exécution CREATE TABLE/ALTER TABLE ni la compatibilité des migrations.

Contrôle réussi : exactement 20 habilitations de table (2 privilèges × 5 tables × 2 comptes), non délégables, dix enregistrements mysql.tables_priv correspondants sans droit de colonne. Aucun droit réel global/de schéma/colonne/routine, rôle/proxy/délégation ; lecteurs/importeurs à USAGE. Deux schémas toujours sans table/vue/routine/événement. Aucune épreuve CREATE TABLE/ALTER TABLE : les tests DDL attendent OP-05/06.

Le helper opérationnel hors code applicatif a passé la syntaxe PHP et [18 tests isolés sans SQL](../output/qa-import/op03b-tests-helper.json), puis le [précontrôle ID114](../output/qa-import/op03b-precontrole.json). [Exécution détaillée](../output/qa-import/op03b-resultat-script.json) sans écart et [vérification CLI indépendante](../output/qa-import/op03b-verification-independante.json) : matrice information_schema rapprochée des dix lignes mysql.tables_priv, cible XAMPP10.4.28/3307/socket/datadir exacts, 16 Mio et contrôles FK/CHECK actifs. .env applicatif et 3 868 témoins inchangés ; aucune lecture de fichier secret requise, aucun service ou réglage XAMPP modifié.

**Limite de sécurité :** ALTER peut modifier ou supprimer des colonnes d'une table autorisée. L'absence de privilège DROP n'interdit pas toute modification destructive de structure. Ces accès restent réservés aux migrations ciblées relues et ne doivent pas devenir les identifiants de l'application.

## 5. Connexion Laravel : ce qui sera possible et ce qui attend

Les fichiers relus sont config/database.php, config/homya.php, config/catalogue.php, .env.example, app/Console/Commands/CheckLocalDatabase.php, phpunit.xml, tests/Feature/ExampleTest.php et les connecteurs MariaDbConnector/MySqlConnector installés. Aucune nouvelle connexion dédiée n'est implémentée.

Le connecteur installé privilégie le socket sur host/port et exécute USE lorsque database est non vide. **Déduction :** l'écart actuel DB_PORT ne prouve pas une connexion à Homebrew puisque le socket est exact. Il doit néanmoins être aligné explicitement lors de la future configuration. La collation de session actuelle diffère de la cible binaire ; cela ne change pas les bases déjà créées.

| Étape | Connexion / résultat attendu | Autorité requise |
|---|---|---|
| OP-03a | Authentification PDO/Laravel des six comptes sans sélection de base ; aucune connexion persistée | Accord OP-03a explicite |
| OP-03b | Droits DDL exacts des deux comptes schema ; lecteurs toujours sans accès aux schémas | Accord OP-03b explicite |
| OP-04 | Connexions dédiées et gardes de diagnostic/commandes, sans connexion privilégiée dans le runtime | Autorisation de code distincte |
| OP-05/06 | Tables test puis droits DML ; vérification des permissions sur fixtures autorisées | Accords propres |
| OP-08 | Tables locales puis droits lecteur/importeur ; configuration privée lecteur et test de connexion au schéma | Accord incluant ces actions, à présenter séparément |
| OP-10 | Lecture du catalogue depuis l'archive après recette et accord | Autorisation de bascule distincte |

Configuration future proposée pour le runtime, **non appliquée** : DB_CONNECTION=mariadb ; DB_DATABASE=homya_local ; DB_USERNAME=homya_local_reader ; DB_PASSWORD chargé localement ; DB_SOCKET exact ; DB_PORT=3307 ; DB_URL absent ; DB_CHARSET=utf8mb4 ; DB_COLLATION=utf8mb4_bin ; strict actif. Conserver session/cache file et queue sync. Les accès schema/import restent privés et explicitement sélectionnés pour leurs commandes.

Un lecteur à USAGE ne peut pas sélectionner homya_local, même vide. Ne pas remplir .env avec ce compte pour annoncer prématurément « connexion Laravel fonctionnelle », ni lui accorder des droits globaux pour faire passer le diagnostic. La commande homya:db-check actuelle exige une base sélectionnée ; elle ne remplace pas l'épreuve d'authentification sans base d'OP-03a.

Améliorations du diagnostic à prévoir dans OP-04 : refuser compte administrateur/inattendu, nom de base non autorisé, socket étranger et DB_URL ; comparer identité authentifiée, version, socket, datadir, port, charset/collation et paquets ; séparer authentification et disponibilité du schéma. Conserver les tests sans base et leur nom de cible protecteur. Aucune migration générale, fresh/refresh/rollback/seeder/config:cache dans cette préparation.

## 6. Critères d'acceptation et erreurs

| ID | Étape / critère observable | État présent |
|---|---|---|
| OP03-AC01 | Cible exacte, deux bases vides, noms sans collision recontrôlés avant écriture | Réussi avant chacun des six CREATE ; contrôles finaux réussis |
| OP03-AC02 | Six comptes localhost, six secrets distincts protégés ; aucun autre compte modifié | Six CREATE sur l'allowlist uniquement ; permissions et exclusions recontrôlées |
| OP03-AC03 | Six authentifications PDO et Laravel réussies sans base ; identités/transport/session conformes | Réussi : douze authentifications ; DSN Laravel à base vide éprouvé |
| OP03-AC04 | Zéro droit réel, rôle ou délégation après OP-03a ; refus de USE sous USAGE | Réussi : audit indépendant ID101 ; 24 refus1044 |
| OP03-AC05 | Après OP-03b, 20 seuls privilèges CREATE/ALTER attendus ; zéro objet créé | Réussi : contrôle après chaque GRANT et rapprochement CLI indépendant de la matrice exacte ; bases toujours vides |
| OP03-AC06 | .env, code, catalogue et configurations XAMPP conservés ; aucun secret dans preuves/argv/logs de l'opération | .env et 3 868 témoins inchangés ; secrets générés/stockés privés, jamais affichés ; journalisation inspectée sans modification |
| OP03-AC07 | Connexion runtime lecteur au schéma seulement après tables et SELECT ; source fichiers conservée jusqu'à OP-10 | Différé, dépend d'OP-08/10 |

Traitement prévu : collision1396 → arrêt sans remplacement ; authentification1045 → diagnostic filtré, aucun changement d'un compte existant ; sélection refusée1044 sous USAGE → résultat attendu des contrôles négatifs ; socket2002/cible étrangère → aucune écriture ; GRANT1142/1146 → arrêt sans ALL ni création de table ; interruption → inventaire des opérations confirmées, pas de répétition aveugle. Aucun rollback transactionnel ni restauration automatique promis.

## 7. Sources externes et portée

Questions examinées : création à zéro droit et collisions ; authentification compatible avec le PHP local ; CREATE/ALTER sur tables futures dans MariaDB10.4.28 ; connexion PDO par socket. Consultation de toutes les sources ci-dessous : **07/10/2026**.

| Source primaire / URL | Portée et fait publié étayé |
|---|---|
| [MariaDB — CREATE USER](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/create-user) | Création sans privilège ; collisions et réussite partielle possibles ; OR REPLACE remplace un compte ; syntaxe d'authentification. Documentation multiversion, pas observation locale |
| [MariaDB — GRANT](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant) | USAGE sans droit réel ; niveaux table/colonne et CREATE sur table future. Ne prouve pas le GRANT dans XAMPP |
| [MariaDB — Authentication Plugin mysql_native_password](https://mariadb.com/docs/server/reference/plugins/authentication-plugins/authentication-plugin-mysql_native_password) | Plugin natif, syntaxe via empreinte, compatibilité client ; mécanisme ancien avec limites de sécurité. Proposition locale seulement |
| [MariaDB10.4.28 — sql_acl.cc, mysql_table_grant](https://github.com/MariaDB/server/blob/mariadb-10.4.28/sql/sql_acl.cc#L6538) | Code de la version cible : condition de vérification d'existence selon CREATE_ACL. La possibilité du GRANT combiné est une interprétation à contrôler sur le binaire réel |
| [PHP — PDO_MYSQL DSN](https://www.php.net/manual/en/ref.pdo-mysql.connection.php) | Paramètres unix_socket ; ne pas combiner ce transport avec host/port dans le DSN. Le test local sans base, distinct de cette source, a réussi en OP-03a |

Les tests locaux d'OP-03a et OP-03b étayent la création, l'authentification sans base et les habilitations décrites ; ils ne prouvent ni les DDL/DML futurs ni une compatibilité d'hébergement. Aucun accès Claude, service externe ou connexion commerciale présumé.

## 8. Clôture et prochaine étape

**OP-03a et OP-03b clôturées.** Six comptes authentifiables sans base ; dix GRANT ciblés vérifiés. Aucun .env applicatif/code modifié, table créée, migration, import ou bascule. La connexion runtime lecteur à homya_local attend les tables et droits SELECT autorisés.

**Prochaine étape :** préparer le périmètre d'OP-04 (code de capsule/connexions dédiées/gardes/tests selon le plan technique), puis présenter son autorisation propre avant implémentation. OP-03b n'autorise pas ce code ni les opérations ultérieures sur la base. La séparation reste celle d'AGENTS.md et de DECISION-019.
