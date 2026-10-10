# HOMYA — environnement local

7 octobre 2026 — socle technique local ; actualisé après rétablissement de phpMyAdmin. Les tableaux initiaux ci-dessous décrivent les contrôles du socle ; l'état actuel figure dans la section finale.

## Décision utilisateur

Laravel avec la base locale sur XAMPP, dans `/Users/akm/Documents/ChatGPT/HOMYA`. Une application neuve est construite ; les rayons DAMDAM sont inclus, avec des parcours similaires et une identité HOMYA originale.

## Faits vérifiés

| Élément | Observation |
|---|---|
| PHP disponible via `php` | 8.5.11, Homebrew |
| Composer | 2.10.3 |
| PHP XAMPP | 8.2.4 |
| Client MariaDB XAMPP | 10.4.28 ; version du serveur actif XAMPP non vérifiée |
| Extensions | PDO MySQL, mbstring, OpenSSL, XML et autres extensions nécessaires présentes dans le PHP CLI |
| Node / npm | Node 26.8.1, npm 11.19.0 |
| Port 3306 | Occupé par `/opt/homebrew/opt/mysql/bin/mysqld`, identifié par lecture du processus |
| Socket XAMPP configuré | `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, absent lors du contrôle |
| Port 3307 | Aucun processus en écoute observé lors du contrôle ; à revérifier avant activation |

## Choix d'implémentation

Laravel 13 avec le PHP 8.5 déjà disponible. Le PHP de XAMPP reste inchangé. Laravel 13 exige PHP 8.3 minimum selon les [notes officielles](https://laravel.com/framework/docs/13.x/releases). MariaDB 10.3+ fait partie des moteurs pris en charge selon la [documentation officielle](https://laravel.com/framework/docs/13.x/database) ; cela ne prouve pas une connexion à l'installation locale.

Vues Blade, composants partagés, Vite et Tailwind du squelette officiel. FR/EN/DE configurés et page d'attente traduite ; textes commerciaux complets à produire dans une phase ultérieure.

Le dépôt utilise `DB_CONNECTION=mariadb`, le socket XAMPP et le nom local proposé `homya_local`. Les accès sont laissés vides dans l'exemple ; aucun compte ni mot de passe présumé. Les sessions et le cache sont sur fichiers, la file d'attente est synchrone et les emails restent en mode journal local.

L'application est servie par `php artisan serve` sur l'interface de boucle locale. Apache XAMPP et ses virtual hosts ne sont pas modifiés. L'exécution par Apache XAMPP avec PHP 8.2 nécessiterait un autre choix de version PHP/framework avant usage.

## Périmètre de cette phase

- Squelette officiel Laravel téléchargé dans un dossier temporaire, inspecté puis copié sans collision de fichiers. Documentation existante conservée.
- Scripts automatiques SQLite et migrations retirés de Composer.
- Commande `homya:db-check` en lecture seule : vérifie la cible MariaDB, refuse une URL substituée ou un autre socket, puis contrôle l'accès et l'identité du serveur si le socket existe.
- Aucun arrêt de MySQL Homebrew, aucune modification de XAMPP, aucune base créée ni migration exécutée.
- Aucune donnée produit, prix, stock, avis ou donnée client inventés/importés.

## Blocage initial de connexion — résolu pour phpMyAdmin

Lors du contrôle initial, le service sur 3306 appartenait à Homebrew et le socket XAMPP attendu n'était pas disponible. Le service MariaDB XAMPP est désormais accessible ; la base HOMYA n'est toujours pas considérée comme connectée, car aucun schéma ni accès applicatif n'a été préparé.

La proposition initiale [XAMPP-PORT-3307.patch](XAMPP-PORT-3307.patch) est conservée comme historique et ne doit pas être réappliquée à la configuration actuelle. La correction appliquée avec sauvegardes et authentification administrateur par l'utilisateur inclut aussi l'écoute locale et l'alignement du gestionnaire et du lanceur. Voir [le diagnostic détaillé](DIAGNOSTIC-XAMPP.md). MySQL Homebrew est conservé.

Avant de créer `homya_local` ou d'appliquer les migrations, confirmer la cible et les accès, vérifier l'absence de collision avec une base existante et valider le plan de schéma utile au projet. Ne pas publier de secrets dans le chat.

## Contrôles

| Contrôle exécuté | Résultat et limite |
|---|---|
| `php artisan --version` | Laravel Framework 13.35.0 |
| `composer validate --strict` | Valide |
| `composer check-platform-reqs` | Exigences satisfaites sur PHP 8.5.11 ; aucune autre version PHP testée |
| `npm run build` après retrait de la police inutilisée | Réussi dans le sandbox, sans récupération réseau de police |
| `php artisan test --testsuite=Feature` | 7 tests, 21 assertions, tous réussis ; aucune requête/migration base |
| `vendor/bin/pint --test` | Réussi |
| `php artisan view:cache`, cache et purge de configuration | Réussis |
| `git check-ignore .env` | Configuration sensible ignorée par Git |
| `php artisan homya:db-check` | Refus attendu : socket XAMPP absent ; connexion non vérifiée |
| Démarrage serveur | Serveur local accessible sur `http://127.0.0.1:8000` |
| Navigateur, FR → EN → DE | Navigation réelle réussie et contenus correspondants observés |
| Mobile allemand, viewport demandé 390 × 844 | Rendu observé, colonnes adaptées ; largeur du contenu 375, viewport 390, aucun débordement horizontal mesuré |

Les tests sans requêtes ne valent pas vérification du moteur cible. Le contrôle mobile n'est pas une revue complète d'accessibilité, de performances ou de toutes les langues et dimensions.

## Correction des dépendances frontend

L'audit npm initial signalait deux alertes critiques dans `concurrently` → `shell-quote` 1.9.0, liées à [GHSA-pqg4-j6r4-53mv](https://github.com/advisories/GHSA-pqg4-j6r4-53mv). Une substitution ciblée vers `shell-quote` 1.11.0 est inscrite dans `package.json` et le verrou npm. Après installation : 0 vulnérabilité signalée par npm. L'exécution de deux commandes locales par `concurrently` est vérifiée avec sorties normales et codes 0 ; compilation également réussie.

Ces résultats couvrent les dépendances npm installées à cette date, pas un audit de sécurité complet de l'application ou de XAMPP. Aucun test offensif sur DAMDAM ou un service externe réalisé.

## État actuel après correction XAMPP

- L'utilisateur a exécuté le script préparé depuis son Terminal administrateur. Les sauvegardes vérifiées sont dans `/Applications/XAMPP/xamppfiles/etc/homya-backup-20261007-sn5vul8w`.
- `my.cnf`, `properties.ini` et le test de port du lanceur `xampp` sont alignés sur 3307 ; MariaDB écoute uniquement sur `127.0.0.1`.
- Une requête SELECT de contrôle confirme `10.4.28-MariaDB`, port 3307, socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, répertoire de données XAMPP et adresse d'écoute locale.
- phpMyAdmin 5.2.1 ouvre son interface d'administration dans le navigateur, sans erreur de connexion. L'accès reste local (`Require local`). Apache répond HTTP 200.
- Le processus MySQL Homebrew reste actif. Aucun arrêt de ce service ni changement de mot de passe, création de base ou migration exécutés pendant la correction.
- Le serveur de prévisualisation HOMYA, qui était arrêté lors du contrôle, a été relancé sur `127.0.0.1:8000` ; la page française est vérifiée dans le navigateur.
- `.env.example` indique désormais le port 3307. Le fichier `.env` privé n'a pas été modifié dans cette phase. Le socket reste le mode de connexion prévu pour Laravel.

La connexion et les droits applicatifs de HOMYA, son schéma et ses migrations restent à préparer séparément. L'ouverture de phpMyAdmin ne prouve pas que la boutique ou sa base sont implémentées.

## Contrôle préalable à l'archive catalogue — 07/10/2026

Dix SELECT de métadonnées exécutés dans la session locale phpMyAdmin confirment MariaDB XAMPP 10.4.28, socket exact, port 3307, datadir XAMPP et écoute 127.0.0.1. Les schémas homya_local/homya_test sont absents, ainsi que les six comptes dédiés proposés. InnoDB est le moteur par défaut transactionnel ; ascii_bin/utf8mb4_bin sont disponibles, modes global/session stricts et contrôles FK/CHECK actifs. Ces observations ne prouvent ni contraintes futures ni authentification applicative.

**Blocage import :** max_allowed_packet global et session à 1 048 576 octets (my.cnf `[mysqld]` : 1M), insuffisant pour le document de lot candidat de 4 730 180 octets. Réglage 16 Mio proposé, non appliqué. Les privilèges administratifs utiles sont déclarés pour la session de contrôle ; aucun droit n'est accordé à Laravel. `php artisan homya:db-check` retourne encore 1, accès non renseigné, avant SQL.

Clients mysql/mysqldump XAMPP 10.4.28 présents ; PHP CLI 8.5.11 avec pdo_mysql/mysqlnd ; mémoire CLI 128M. Le volume partagé projet/datadir dispose de 64 800 000 Kio au contrôle. Sauvegarde/restauration, capacité du lot figé et transport PDO restent à vérifier après leurs autorisations. Aucune création, migration, import, modification persistante de configuration ou changement de service réalisés.

Voir [plan technique v0.2 et opérations séparées](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md) et [preuve SQL expurgée](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/xampp-controle-lecture-seule-20261007.json). L'état actuel complète les tableaux historiques ; les preuves d'anciennes phases sont conservées.

## Réglage des paquets OP-01 — état après autorisation distincte

Le 07/10/2026, l'utilisateur a validé OP-01, consigné dans DECISION-020. `my.cnf` a été sauvegardé et sa seule limite sous `[mysqld]` passée de 1M à 16M, propriétaire et permissions conservés. Le serveur actif a reçu `SET GLOBAL max_allowed_packet = 16777216`. Une nouvelle connexion de contrôle (393, distincte de la connexion 389 utilisée pour le réglage global) confirme global/session à 16 777 216 octets, même cible XAMPP sur 3307, FK/CHECK actifs. L'uptime a augmenté ; aucun service arrêté ou redémarré.

Les bases homya_local/homya_test sont toujours absentes. Aucun compte, table, migration ou import créés/exécutés. La connexion Laravel et le transport PDO restent à préparer et vérifier séparément. Le réglage persistant n'a pas été testé par un redémarrage. Voir [compte rendu OP-01 et sauvegarde](OP-01-REGLAGE-XAMPP.md) ; les valeurs 1 Mio de la section précédente sont historiques.

## OP-02 — état partiel après autorisation du 07/10/2026

La réponse « oui j autorise » a autorisé les deux créations vides, consignée et relue dans DECISION-021 avant SQL d'écriture. Le recontrôle confirmait les deux noms absents sur la cible XAMPP exacte. `homya_test` a été créée une fois ; un SELECT ultérieur confirme utf8mb4 / utf8mb4_bin et zéro table/vue. `homya_local` reste absente.

Le contrôle intermédiaire des routines a échoué : erreur 1558, table système mysql.proc attendue avec 21 colonnes mais trouvée avec 20 (créée sous MariaDB 100108, serveur courant 100428). Le contrôle des événements a également échoué avec 1577 ; event_scheduler=OFF a été observé. Absence de routines/événements non vérifiée par ces catalogues. La création de homya_local a été suspendue selon la règle d'arrêt sur erreur.

Aucune réparation/mise à niveau système, activation du planificateur, suppression, création de compte/table, migration, import ou redémarrage exécutés. L'accord OP-02 ne couvre pas cette réparation de l'instance. Le catalogue sur fichiers et la configuration applicative restent inchangés ; connexion Laravel toujours non vérifiée. Voir [OP-02 : résultat, erreurs, preuves et suite](OP-02-CREATION-BASES-VIDES.md).

## Sauvegarde de l'instance COR-XAMPP-A — 07/10/2026

Après une première tentative de lanceur incomplète (processus actif/PID supprimé), l'ajustement SIGTERM normal a été autorisé dans DECISION-023 et exécuté par l'utilisateur. Le helper root confirme arrêt propre, copie complète comparée avec métadonnées et retour du même service ; résumé terminé à 21:12:39 UTC. La destination privée est `/Applications/XAMPP/xamppfiles/homya-backups/20261007T211225Z-2fd4b2bb-aa11-4ac9-8e6c-fd94c7c81b0e`.

Une nouvelle connexion SQL ID13 confirme XAMPP 10.4.28/3307/socket/datadir exacts, écoute locale, global/session 16 777 216 octets, FK/CHECK actifs et événements OFF. **La persistance du réglage 16 Mio d'OP-01 est maintenant observée après redémarrage.** homya_test reste utf8mb4 / utf8mb4_bin, zéro table/vue ; homya_local absente. Nouveau processus XAMPP 65069/UID74, PID revenu ; MySQL Homebrew PID1009 reste actif. Trois configurations et 3 868 fichiers témoins inchangés.

Voir [clôture et limites de A](COR-XAMPP-A-REPRISE.md) et [synthèse vérifiable](../output/qa-import/cor-a-reprise-synthese-verification.json). La restauration est non testée et la copie reste sur le même volume. Aucun mysql_upgrade/réparation, compte, schéma supplémentaire, migration ou import exécuté ; connexion Laravel toujours non vérifiée. COR-XAMPP-B conserve ses prérequis et une autorisation distincte. Les affirmations sans redémarrage des sections précédentes décrivent leurs phases historiques.

## Préparation de la réparation B — 07/10/2026

Le [SELECT CLI administratif](../output/qa-import/cor-b-test-cli.json), connexion 26, confirme sans fichier d'options ni variables d'authentification héritées la cible exacte XAMPP et neuf privilèges utiles. L'accès local sans mot de passe a réussi ; il ne doit pas être repris pour Laravel. Le premier essai CLI était bloqué par le sandbox ; la reprise hors sandbox a réussi. Aucun upgrade ou DDL dans ce contrôle.

`mysql.proc` reste à 20 colonnes, événements OFF, 16 Mio ; la réparation réelle reste à faire. Le [helper B et son plan](COR-XAMPP-B-REPARATION.md) sont préparés et testés sur fixtures : arrêt root macOS ciblé, upgrade système seulement et éventuel redémarrage conditionnel. L'exécution nécessite la confirmation humaine du périmètre et de la restauration non testée dans Terminal. Aucun changement installé, compte/base supplémentaire, migration/import ou modification `.env` à cette préparation. La recette après exécution, phpMyAdmin et la connexion Laravel restent des contrôles distincts.

## Réparation B — état réel après exécution et contrôle du 07/10/2026

L'utilisateur a confirmé `CORRIGER XAMPP` dans Terminal ; DECISION-024 enregistrée et relue avant réparation. Le helper root a recontrôlé la copie A, sauvegardé/corrigé la seule branche root macOS de `mysql.server`, puis exécuté l'upgrade des tables système avec code 0. Terminé à 21:44:03 UTC ; aucun redémarrage nécessaire et événements toujours OFF. Le défaut à 20 colonnes décrit ci-dessus est désormais historique.

Une nouvelle connexion phpMyAdmin ID53 confirme `mysql.proc` à 21 colonnes et les contrôles ROUTINES/EVENTS réussis, même XAMPP10.4.28/3307/socket/datadir/écoute locale, 16 Mio global/session, FK/CHECK actifs, homya_test utf8mb4/utf8mb4_bin sans objet, homya_local absente. Empreinte/syntaxe/métadonnées du lanceur conformes, trois configurations et 3 868 témoins inchangés ; XAMPP PID65069 et Homebrew PID1009 toujours présents.

Voir [clôture B](COR-XAMPP-B-REPARATION.md) et [preuve indépendante SQL](../output/qa-import/cor-b-controle-final-sql.json). Restauration non testée ; comportement d'arrêt réel du lanceur corrigé non testé car aucune interruption requise. Connexion Laravel non vérifiée. B n'a créé aucune base ni compte et n'a exécuté aucune migration/import ; la reprise OP-02 conserve son autorisation distincte DECISION-021.

## OP-02 — reprise clôturée, deux bases vides vérifiées

Après clôture B et relecture de DECISION-021, précontrôle SELECT ID59 réussi : homya_local toujours absente, homya_test conservée vide et cible XAMPP exacte. Un seul CREATE homya_local soumis sous l'accord OP-02 existant. Avis annexe phpMyAdmin1046 après DDL, cause non déterminée ; aucun second CREATE. Les SELECT suivants confirment la création et l'état final.

Connexion de contrôle ID69 et vue compacte : **homya_local et homya_test existent**, toutes deux `utf8mb4 / utf8mb4_bin`, sans table/vue/routine/événement. XAMPP10.4.28/3307/socket/datadir exacts, 16 Mio global/session, FK/CHECK actifs, mysql.proc21, événements OFF. Trois configurations et 3 868 témoins inchangés. Aucune action de service, compte/droit/table/.env/migration/import dans la reprise.

Voir [OP-02 clôturée](OP-02-CREATION-BASES-VIDES.md) et [preuves finales](../output/qa-import/op02-reprise-synthese-verification.json). DECISION-021 complétée et relue ; aucun nouvel accord de création utilisé. Les sections précédentes décrivent leurs étapes historiques ; l'absence de homya_local n'est plus l'état actuel. Les comptes applicatifs, la connexion Laravel, les migrations/imports et la restauration restent à préparer/vérifier sous leurs autorisations distinctes.

## OP-03 — préparation documentaire, sans création ni configuration privée

Le 07/10/2026 à 22:06 UTC, SELECT CLI administratif ID76 : XAMPP10.4.28/3307/socket/datadir exacts, 16 Mio, mysql.proc21, deux bases utf8mb4/utf8mb4_bin toujours vides, zéro collision pour les six noms de comptes prévus. Plugin mysql_native_password actif ; journaux général/lent/binlog désactivés et aucun plugin AUDIT actif observé. [Preuve expurgée](../output/qa-import/op03-inspection-xampp.json). Premier essai refusé dans le sandbox2002, reprise en lecture seule autorisée hors sandbox.

Le [profil Laravel filtré](../output/qa-import/op03-inspection-laravel.json), sans SQL, confirme PHP8.5.11/pdo_mysql, socket exact et identifiants absents. Le port déclaré n'est pas 3307 et la collation de connexion est utf8mb4_unicode_ci ; alignement proposé lors d'une future configuration explicitement autorisée. Le socket est prioritaire dans le connecteur installé ; aucune connexion applicative à XAMPP ou à Homebrew n'a été effectuée par cette inspection.

Le [plan OP-03](OP-03-COMPTES-DROITS-CONNEXION.md) sépare six comptes à USAGE (OP-03a), dix GRANT CREATE/ALTER de table (OP-03b) et les droits DML après existence des tables. L'authentification Laravel sans base sera contrôlée après création ; la connexion runtime lecteur au schéma attend tables/SELECT en OP-08. Aucun accord d'exécution OP-03, compte/droit, code applicatif, secret, changement .env, migration, import ou action de service dans cette préparation.

## OP-03a — six comptes sans privilèges, authentifications vérifiées

Accord OP-03a consigné et relu dans DECISION-025 avant exécution. Le helper opérationnel contrôlé (syntaxe + 14 tests isolés sans SQL) a créé une fois chacun des six comptes localhost, avec secrets distincts dans des fichiers .env privés0700/0600 ignorés par Git. Exécution 07/10/2026 22:26:10–22:26:13 UTC, sans erreur. Aucun GRANT, compte préexistant modifié, code applicatif ou .env applicatif édité, table, migration/import/bascule ni action XAMPP/service.

Six connexions PDO et six avec le connecteur Laravel MariaDbConnector installé ont réussi par socket exact sans sélection de base : identités attendues, DATABASE() NULL, XAMPP10.4.28/3307/datadir, 16 Mio, utf8mb4/utf8mb4_bin et modes stricts. Les 24 USE des deux bases ont été refusés1044, comme attendu sous USAGE. [Résultat expurgé](../output/qa-import/op03a-resultat-script.json).

Le [SELECT indépendant CLI ID101](../output/qa-import/op03a-verification-independante.json) confirme six comptes à localhost/mysql_native_password sans rôle, proxy, délégation ou droit réel global/schéma/table/colonne/routine ; deux bases utf8mb4_bin toujours vides. Permissions/ACL/exclusion Git recontrôlées sans lecture des secrets. .env applicatif inchangé et 3 868 témoins conservés. Aucun accès runtime au schéma validé ; fichier catalogue toujours source. OP-03b reste non autorisée, DDL/DML/migrations/imports et restauration à vérifier dans leurs lots.

## OP-03b — dix habilitations CREATE/ALTER ciblées, vérifiées

Accord « ACCORD AUTORISEE » pour la demande OP-03b, enregistré et relu dans DECISION-026 avant exécution. Syntaxe PHP et 18 tests isolés sans SQL du helper opérationnel réussis. Précontrôle ID114 : six comptes encore sans privilège/rôle/proxy/délégation, bases vides, cible XAMPP exacte. Dix GRANT exécutés une fois chacun ; audit et matrice attendue recontrôlés avant/après chaque instruction. NO_AUTO_CREATE_USER présent ; aucun changement du mode SQL global.

Résultat : vingt seuls privilèges de table CREATE/ALTER, dix noms de table ciblés dans leur propre base pour les deux comptes schema@localhost, aucun GRANT OPTION. Lecteurs/importeurs restent sans privilège ; aucun droit global/de schéma/colonne/routine, rôle ou proxy ajouté. Le [contrôle CLI indépendant](../output/qa-import/op03b-verification-independante.json) rapproche les dix lignes mysql.tables_priv de la matrice information_schema exacte et confirme les deux bases utf8mb4_bin toujours sans objet.

Voir [résultat opérationnel expurgé](../output/qa-import/op03b-resultat-script.json) et [plan suivi](OP-03-COMPTES-DROITS-CONNEXION.md). .env applicatif et 3 868 témoins inchangés ; aucun fichier d’accès dédié lu ni secret affiché, compte ou table créé, service/configuration XAMPP modifié, migration/import/bascule. Les mentions OP-03b non autorisée dans les sections précédentes sont historiques. DDL/DML/contraintes et connexion runtime lecteur restent à vérifier dans leurs opérations autorisées séparément ; OP-04 à préparer avant accord de code.

## OP-04 — préparation du premier incrément, code/capsule non autorisés

La [fiche OP-04 v0.1](OP-04-CONNEXIONS-GARDES-CAPSULE.md) propose connexions dédiées, gardes, diagnostic et capsule avec tests sans base, avant accord. Migrations/importeur/lecteur SQL prévus au plan restent différés ; la source fichiers et le .env applicatif restent conservés. Aucun nouveau SQL ni code applicatif dans cette préparation.

[Inspection des sources/médias du 07/10/2026 22:52 UTC](../output/qa-import/op04-inspection-sources-medias.json) : 3 714 JSON sources, 18 522 médias uniques (18 488 publics + 34 originaux) et trois rapports de revue, soit 22 239 fichiers / 3 477 829 369 octets. SHA-256 vérifiés, JSON sans doublon de clé, références des originaux résolues depuis storage/app/catalogue ; les 627 signalements restent conservés. Manifeste candidat calculé en mémoire : 3 550 149 octets, pas de fichier manifeste/capsule créé ; parent privé catalogue-archives absent au contrôle. Espace libre observé : 65 536 000 000 octets, à recontrôler avant copie. La préparation ne vaut ni copie figée, ni correction métier, ni preuve de transport SQL ou de migration.

## OP-04 — premier incrément autorisé et vérifié

Accord utilisateur « j autorise le p-04 » enregistré dans DECISION-027 puis relu avant code. Connexions dédiées, gardes d'accès/cible, validation JSON à clés uniques et préparation/vérification de capsule implémentées ; six accès existants non lus, .env applicatif non modifié. Aucune connexion réelle ni SQL sous OP-04. Le catalogue, ses pages FR/EN/DE et sa source fichiers restent conservés.

Copie privée complète le 07/10/2026 à 23:27:34 UTC : **22 239 fichiers, 3 477 829 369 octets**, plus manifeste ; clé identique à la simulation. Contrôles par le service, [outil indépendant](../output/qa-import/op04-verification-independante.json) et [relecture CLI](../output/qa-import/op04-verification-cli.json) réussis : liste fermée, manifeste exact, toutes les tailles/SHA-256, original/copie/original, modes privés/propriétaire/ACL des dossiers et exclusion Git. 3 709 produits et 627 signalements conservés ; aucun lot SQL vérifié ou activé.

La copie exige désormais la clé de simulation avec --expected-lot et une dernière relecture des sources avant promotion. **101 tests / 717 assertions réussis sans base**, syntaxe/Pint vérifiés. Les échecs initiaux de fixture macOS/sandbox sont conservés dans leurs rapports ; reprises finales réussies. Voir [résultats, commandes et limites au §9](OP-04-CONNEXIONS-GARDES-CAPSULE.md#9-premier-incrément-réalisé--état-après-accord).

Aucune création de base/compte/table, habilitation, modification XAMPP/service ou migration/import/bascule. Le runtime lecteur au schéma n'est pas vérifié/activé ; tests des gardes de serveur avec doubles seulement. Le complément de code migrations/importeur/lecteur SQL reste à préparer puis autoriser avant OP-05. Les sections précédentes gardent l'historique de préparation et d'exécution de chaque opération.

## OP-04 — complément préparé le 08/10/2026, développement en attente

Le [plan du complément v0.1](OP-04-COMPLEMENT-MIGRATIONS-IMPORT-LECTURE.md) détaille les quatre migrations ciblées, la compilation DDL sans PDO, l'assemblage/import depuis la capsule et le lecteur SQL isolé. Il propose une transaction unique de chargement des enfants, un verrou parent commun et l'arrêt sur un lot incomplet sans reprise automatique ; aucune bascule du catalogue. Neuf critères OP04-CAC relient les contrôles sans base aux recettes SQL ultérieures OP-05 à OP-10.

[Inspection/conservation](../output/qa-import/op04-complement-inspection.json) : empreintes de clôture OP-04 et registre concordants, 3 864 témoins d'origine conservés hors quatre changements déjà autorisés, .env applicatif inchangé. Les 3 717 JSON source/rapports et copies ont été re-hachés, sans divergence ; clé et taille du manifeste privé confirmées. Les médias gardent leur preuve intégrale du 07/10, sans nouvelle relecture binaire dans cette préparation. PHP CLI8.5.11 et Laravel installé13.35.0 confirmés ; code du migrateur/grammaire relu. Les sources officielles MariaDB consultées le 08/10 sont citées au §9 du plan ; elles ne prouvent pas le comportement du serveur local.

Aucun code applicatif/migration créé ou modifié, aucun SQL/accès dédié/service utilisé, aucune recette/build relancée. Seuls le plan, cette note et les preuves documentaires sont écrits. DECISION-027 exclut le complément ; son développement attend un accord distinct à consigner dans le registre central. L'exécution de migrations, les droits DML, imports et activation garderont leurs propres autorisations. Source fichiers et secrets conservés.

## OP-04 — complément autorisé et vérifié sans SQL

Accord « oui j autorise » consigné et relu sous DECISION-028 avant développement. Quatre migrations ciblées et compilation DDL hors PDO, payload/importeur/lecteur SQL isolé, deux commandes et gardes/tests implémentés ; [résultats et critères au §11](OP-04-COMPLEMENT-MIGRATIONS-IMPORT-LECTURE.md#11-complément-développé-et-vérifié-après-accord). La lecture publique reste sur fichiers ; configurations, .env, secrets dédiés, routes/vues/langues/styles, migration squelette et phpunit.xml conservés.

Simulation de la capsule réelle réussie, médias re-hachés avant/après sans SQL : 3 709 produits, 65 catégories, 23 878 traductions et 627 signalements conservés. Pic PHP alloué~256Mio sous512M ; commande de simulation documentée avec cette limite. Budget conservateur du lot16 626 698octets sous16Mio, transport PDO encore non éprouvé. Régression finale142tests/950assertions, syntaxe/Pint20fichiers réussis ; recette MariaDB distincte skipped par défaut avant boot/accès, aucun test SQL réel.

Les erreurs initiales d'injection/signatures/sandbox sont corrigées et leurs rapports conservés. Aucun GRANT, SQL, création de base/compte/table, service/configuration XAMPP, nouvelle capsule, import ou bascule. Le complément donne le code pour OP-05 ; ses opérations de schéma restent à préparer et autoriser séparément. Voir [préparation OP-05](OP-05-SCHEMA-TEST-PREPARATION.md) et [contrôles finaux](../output/qa-import/op04-complement-controles-finaux.json).

## OP-05 — précontrôle et préparation finalisés, exécution en attente

Le 08/10/2026, sur demande de précontrôle en lecture seule, [l'inventaire administratif XAMPP](../output/qa-import/op05-precontrole-final-xampp.json) confirme MariaDB 10.4.28/socket/datadir exacts/3307, paquets global/nouvelle session 16 Mio, FK/CHECK actifs, événements OFF, InnoDB et collations disponibles. Les deux bases restent sans objet ; six comptes localhost et vingt seuls droits CREATE/ALTER ciblés, aucun autre droit/rôle/proxy/délégation. [Laravel/PDO sous homya_test_schema](../output/qa-import/op05-connexion-laravel.json) valide la cible/session stricte en lecture seule, sans création ni migration. Le secret de ce seul accès est chargé en mémoire ; aucun contenu affiché ni .env modifié.

[OP-05 v0.2](OP-05-SCHEMA-TEST-PREPARATION.md) livre les outils pour journal seul, GRANT exact et enveloppe du migrateur, avec reçu d'accord séparé/empreintes/inventaire frais/verrou local et arrêt sans reprise automatique. Douze tests Python sans SQL ; vrai Migrator Laravel testé sur doubles sans PDO ; régression 146 tests/1 001 assertions ; syntaxe/Pint trois PHP réussis. Les refus sandbox initiaux et la correction du parseur de variables ON/OFF sont documentés ; aucun réglage serveur nécessaire. Empreintes du code/DDL OP-04 et témoins applicatifs conservés ; capsule non re-hachée dans cette phase, preuve complète OP-04 conservée.

Aucun GRANT, DDL réel, migration réelle, import, activation, création de base/compte, changement XAMPP/service effectué. Registre central relu et inchangé, aucun reçu d'exécution OP-05 émis. Autorisation OP-05 distincte à obtenir pour cinq tables test et les seuls droits SELECT/INSERT du journal ; succès DDL/contraintes physiques non encore vérifié. Voir [synthèse de préparation](../output/qa-import/op05-preparation-synthese-verification.json).

## OP-05 — accord enregistré, exécution interrompue sur schéma partiel

Accord « j autorise les cinq tables » reçu en réponse à la demande regroupée OP-05 v0.2, consigné/relu sous DECISION-029 avant écritures ; [plan approuvé figé](../output/qa-import/op05-plan-autorise.md). Précontrôle renouvelé réussi. Journal créé et contrôlé sous homya_test_schema, deux droits SELECT/INSERT du journal appliqués, matrice 22 vérifiée. Le migrateur ciblé a ensuite échoué : arrêt immédiat sans rollback/purge/rejeu.

[État administratif figé](../output/qa-import/op05-etat-partiel-fige.json) : trois seuls objets test, journal et lot complets, catégorie avec colonnes/PK mais FK/CHECK absents, produits/traductions absents. Journal une ligne lot id1/batch1 ; lot/catégorie vides ; homya_local vide et lecteurs/importeurs toujours sans droits. Configuration, .env, code/migrations OP-04, catalogue et médias conservés ; aucun import/activation/service modifié.

Le précontrôle de droits initial était incomplet : le code officiel MariaDB 10.4.28 contrôle aussi l'accès aux parents des FK. L'absence de SELECT/INSERT/UPDATE/DELETE/REFERENCES sur le lot explique l'emplacement de l'arrêt ; erreur SQL originale non capturée, cause interprétée à partir des preuves et du code versionné. [Reprise préparée v0.1](OP-05-REPRISE-CIBLEE.md) : deux REFERENCES parents seulement, complétion sélective sans suppression et trois lignes techniques supplémentaires ; huit tests isolés, syntaxe/Pint et DDL hors SQL contrôlés. Aucun GRANT REFERENCES ni DDL de reprise effectué ; accord complémentaire requis. [Synthèse d'exécution et préparation de reprise](../output/qa-import/op05-execution-synthese.json). DECISION-029 reste validée comme autorisation, son exécution est consignée INCOMPLÈTE.

## OP-05 — reprise autorisée, schéma test vérifié sans import

Accord complémentaire « j autorise les deux references » reçu en réponse à la reprise regroupée, inscrit/relu sous DECISION-030 avant SQL. Précontrôle renouvelé conforme ; deux REFERENCES ciblés seulement ajoutés au compte test schema, catégorie complétée, produits/traductions créés et trois lignes techniques de journal ajoutées. [Clôture](OP-05-REPRISE-CIBLEE.md#clôture-après-accord--08102026) et [preuve indépendante physique](../output/qa-import/op05-reprise-verification-physique.json).

État réel : homya_test comporte exactement migrations et quatre tables archive InnoDB/utf8mb4_bin ; 34 colonnes, PK/index/UNIQUE, quatre FK RESTRICT/RESTRICT et 27 CHECK conformes au DDL figé. Quatre archives vides et journal exact id1–4/batch1. Six comptes conservés, 24 seuls privilèges de table non délégables ; lecteurs/importeurs sans droits ; homya_local sans objet. Paquets global/nouvelle session 16 Mio, événements OFF, FK/CHECK actifs et cible XAMPP inchangés.

Aucun import, service/configuration/.env/code OP-04 changé, ni activation du lecteur SQL public : catalogue toujours sur fichiers. Les différences initiales du vérificateur (NULL de métadonnée et ordre de tri) ont été résolues sans DDL supplémentaire. Structure vérifiée ne signifie pas recette de refus/transport/concurrence ou restauration éprouvée ; celles-ci restent non exécutées. Préparation OP-06 à suivre, exécution et droits DML soumis à autorisation distincte.

## OP-06 — préparation documentaire sans SQL

Le 08/10/2026, [plan de recette v0.1](OP-06-RECETTE-XAMPP-PREPARATION.md) préparé à partir de la clôture OP-05, du plan technique, de l'importeur/lecteur et du test de lecture MariaDB existants. Neuf fixtures synthétiques identifiées et validées uniquement en mémoire, sans boot Laravel/accès/SQL : [clés et compteurs](../output/qa-import/op06-fixtures-proposees.json). Droits futurs : neuf GRANT ciblés, total proposé36 privilèges de table et trois UPDATE de colonne ; aucune instruction exécutée.

Point préalable découvert dans le code fournisseur installé : Laravel peut rejouer une requête hors transaction après reconnexion ; l'enregistrement initial du lot utilise ce chemin. Garde anti-rejeu et épreuve sans base à compléter avant SQL, sans patch vendor. Les outils de DML/concurrence et l'audit des droits de colonne restent à livrer ; recette existante de lecture seule ne les couvre pas.

Scénarios proposés : contraintes/permissions et gros messages en transactions annulées, quatre lots synthétiques conservés, puis capsule réelle identifiée dans homya_test seulement après réussite. Aucun site basculé ou commande/paiement impliqué. Les sources officielles consultées et limites sont au §9 ; elles ne valent pas recette locale. Aucun SQL, accès privé, GRANT, import, migration, service/configuration ou code applicatif modifié dans cette préparation ; registre central consulté mais inchangé. Accord d'exécution groupé à présenter après livraison/vérification des outils.

## OP-06 — complément développé et vérifié sans base

Accord de code et vérification sans base inscrit/relu sous DECISION-031. Garde anti-rejeu de la seule connexion d'archive, outils de recette/permissions/audits/transport/concurrence et liaison aux empreintes livrés ; [plan v0.2 et commandes](OP-06-RECETTE-XAMPP-PREPARATION.md#7-outils-livrés-et-commandes). Aucun SQL réel, accès dédié chargé, GRANT, migration/import, service ou configuration XAMPP exécuté.

Régression finale154tests/1 043assertions sans base, dix tests Python et deux classes MariaDB skipped avant boot/accès ; syntaxe et Pint réussis. Doubles PDO/métadonnées et processus PHP fictifs seulement : verrous/rollback/transport MariaDB réels non vérifiés. [Contrôles et conservation](../output/qa-import/op06-complement-controles-finaux.json). Routes/vues/styles/langues/configuration/migrations/.env inchangés ; catalogue public reste sur fichiers. Médias/capsule non re-hachés intégralement dans cette phase ; sauvegarde/restauration non testée.

Empreintes de préparation livrées, aucun reçu d'exécution OP-06 émis. Autorisation groupée distincte à obtenir pour neuf GRANT, recette avec quatre fixtures persistantes et capsule réelle inactives dans homya_test après réussite ; rien sur homya_local ni activation du site. Les notes précédentes gardent l'historique, leurs mentions de complément à livrer sont dépassées par cette clôture de code.

## OP-06 — exécution partielle, arrêt contrôlé et correction sans base

Accord groupé « j'autorise » inscrit/relu DECISION-032 avant SQL. Précontrôle XAMPP réussi, neuf GRANT appliqués une fois avec audits : 36droits de table +3UPDATE de colonne exacts, aucune délégation/permission élargie. Intégrité (55refus +bornes positives), collision documentaire, transport natif PDO gros JSON et petite fixture verified/idempotente réussis.

Arrêt sur erreur du callback synthétique failure : contrat de vérification incomplet, garde déclenchée avant INSERT failure. Diagnostic SELECT indépendant : seul transport verified avec1/1/1/6lignes, aucune capsule réelle ; schéma/journal/droits exacts et homya_local sans objet. [Bilan](../output/qa-import/op06-execution-synthese.json). Aucun retry, purge, service ou configuration modifié ; site sur fichiers.

Callbacks failure/concurrency corrigés et reprise ciblée préparée **sans base** : 157tests/1 054assertions Laravel, douze tests Python, syntaxe/Pint réussis. Ancien reçu conservé et invalide pour les outils corrigés. [Plan de reprise §12](OP-06-RECETTE-XAMPP-PREPARATION.md#12-reprise-ciblée-proposée--nouvel-accord-nécessaire) : garde du run/état exact, aucun nouveau droit, étapes restantes puis capsule réelle seulement après réussite. Nouvel accord requis avant toute continuation SQL, conformément à l'arrêt du plan approuvé. Aucune reprise ni capsule chargée dans cette phase ; restauration non testée.

## OP-06 — reprise autorisée, catalogue test vérifié et site conservé

Accord de continuation inscrit/relu DECISION-033 ; précontrôle renouvelé et transport relu à l'identique, aucun nouveau GRANT ni répétition des étapes achevées. Permissions/échec injecté/parent partiel/timeout1205/concurrence réelle réussis, puis capsule réelle chargée et rapprochée par reader. [Clôture §13](OP-06-RECETTE-XAMPP-PREPARATION.md#13-reprise-autorisée-et-clôture--08102026) et [synthèse](../output/qa-import/op06-reprise-synthese-verification.json).

Archive réelle : 3 709produits, 65catégories, 23 878traductions et 627signalements conservés. Avec quatre fixtures : cinq lots/67catégories/3 711produits/23 890traductions, journal quatre lignes exactes, mêmes cinq tables/six comptes/36droits de table+3colonnes, homya_local vide. Deux travailleurs/PID/connexions distincts et attente réelle confirmée, aucun double chargement. Contrôle indépendant22239fichiers/3 477 829 369octets par jeu original/copie entièrement identique.

Deux tests MariaDB finaux/cinq assertions sans erreur ni risque ; treize tests Python, syntaxe/Pint réussis. Les incidents intermédiaires d'isolation PHPUnit puis liaison du reçu d'audit sont corrigés et tracés sans nouvel import/DML. Les reçus source et sauvegardes restent intacts. Avant docs,125/127témoins conservés ; exceptions : test de lecture et outil d'audit, aucun code métier/configuration/.env/my.cnf/routes/vues/langues/styles/migration changé. Catalogue public toujours sur fichiers, services inchangés, aucune activation commerciale ou SQL.

Restauration non testée ; rupture réseau/commit incertain réels non simulés. OP-07 à préparer et autoriser séparément ; aucun homya_local, purge, restauration, bascule ou déploiement acquis par OP-06. Les sections précédentes gardent leurs états historiques.

## OP-07 — préparation documentaire, aucune exécution

[Plan proposé v0.1](OP-07-SAUVEGARDE-RESTAURATION-PREPARATION.md) : export des cinq tables test, restauration dans la cible neuve proposée `homya_restore_op07`, compte dédié aux droits de table bornés puis réduit à SELECT, contrôle intégral des lignes/contraintes et de la capsule médias. Ces nouveaux choix ne sont pas encore validés ; aucune autorisation d'exécution OP-07 enregistrée.

Inspection du 08/10/2026 sans connexion : clients mysql/mysqldump XAMPP 10.4.28, options d'export proposées acceptées avec --help ; projet/datadir/tmp sur le même volume, 56 800 000 Kio disponibles au contrôle, valeur variable. Les onze preuves du dernier run OP-06 et le registre restent conformes à leurs empreintes ; seul le manifeste capsule a été re-haché, pas les 22 239 fichiers cette fois. [Preuve de préparation](../output/qa-import/op07-preparation-inspection.json). L'état SQL de départ reste celui historiquement audité lors d'OP-06, à renouveler avant toute exécution. Aucune sauvegarde SQL, création, restauration, migration, modification de droits/services/configuration ou bascule réalisée ; une copie sur ce disque ne prouve pas la récupération après perte du disque.

## OP-07 — choix validés et outils vérifiés sans base

Accord explicite « Je valide les choix et autorise les outils/tests sans base » enregistré/relu DECISION-034, avec sauvegarde du registre et conservation de ses métadonnées. [Plan v0.2 §9](OP-07-SAUVEGARDE-RESTAURATION-PREPARATION.md#9-outils-préparés-commandes-et-preuve-hors-base) : scripts dédiés d'export/restauration/snapshot, contrôle fermé du dump, nouvelle cible nommée et compte aux 17 droits puis cinq SELECT seulement ; aucune extension du garde applicatif ou du lecteur public.

60 tests Python (27 OP-07) et 72 tests unitaires PHP/250 assertions (quatre OP-07/huit assertions) passent sans base ; syntaxe et Pint vérifiés. Refus d'exécuter sans reçu futur ; simulations bornées, INSERT synthétique 4,7 Mo et DDL figé examinés sans SQL. Ajout de l'exclusion Git du futur dossier privé de sauvegarde. [Programme/empreintes](../output/qa-import/op07-outils-programme.json) et [contrôles finaux](../output/qa-import/op07-outils-controles-finaux.json).

Aucun reçu d'exécution OP-07, accès SQL, dump réel, nouveau secret réel, base/compte/table, GRANT/REVOKE, import/migration/restauration, service/configuration XAMPP ou bascule réalisés. Catalogue reste sur fichiers. Nom libre/accès/capacité/transport/restauration restent à vérifier lors de l'exécution future ; accord distinct requis. Les notes précédentes sont historiques.

## OP-07 — exécution autorisée, arrêt avant création et correctif sans reprise

Accord groupé « J’autorise OP-07 dans ce périmètre » consigné/relu DECISION-035 avant connexion. Précontrôle source/collisions réussi, snapshot cohérent complet et export natif exécutés. Arrêt `DUMP_SESSION_STATEMENT` : XAMPP émet `SET character_set_client = utf8` pour les seuls DDL, instruction initialement absente de la liste fermée. Dump de 35 850 176 octets conservé en fichier partial privé ; aucune base/compte/table cible, aucun GRANT/restauration créé ou exécuté.

[Contrôle après arrêt](../output/qa-import/op07-execution-arret-controle.json) : source/lignes/payloads/DDL/journal/droits historiques identiques, `homya_local` vide, cible et compte absents, témoins applicatifs/.env/my.cnf inchangés. Catalogue toujours sur fichiers. Code du vérificateur corrigé sans SQL : instruction bornée à la séquence sauvegarde du client / CREATE exact / rétablissement avant données ; 61 tests Python réussis. [Dump réel accepté hors base](../output/qa-import/op07-correctif-dump-natif-controle.json), instruction maximale 8 852 675 octets sous 16 Mio ; cinq tables et compteurs exacts.

[Plan v0.3 §10](OP-07-SAUVEGARDE-RESTAURATION-PREPARATION.md#10-exécution-autorisée-arrêt-contrôlé-et-correction--08102026) et programme de nouvelle tentative préparés. Ancien reçu conservé, invalidé par les nouvelles empreintes ; aucune seconde tentative exécutée. Accord de reprise adapté requis par le plan approuvé, aucun nettoyage automatique. Médias complets non re-hachés dans cette tentative ; restauration non testée, aucun changement XAMPP/service/activation. Les sections précédentes gardent leurs états historiques.

## OP-07 — reprise autorisée, restauration isolée vérifiée et site conservé

Accord « J’autorise la reprise OP-07 » consigné/relu DECISION-036 avant connexion. Nouveau précontrôle/export et validation corrigée réussis ; seule cible neuve `homya_restore_op07`, seul nouveau compte `homya_op07_restore@localhost`, 17 droits bornés puis retrait des 12 droits d'écriture/création/références. [Clôture v0.4 §11](OP-07-SAUVEGARDE-RESTAURATION-PREPARATION.md#11-reprise-autorisée-et-clôture-vérifiée--08102026), [contrôle indépendant](../output/qa-import/op07-reprise-controle-independant.json).

Cinq tables restaurées, DDL/journal/types/collations/PK/index/27CHECK/quatreFK RESTRICT identiques, références dans la seule cible. Hashes complets et payloads égaux dans quatre snapshots source/copie ; 5 lots/67 catégories/3 711 produits/23 890 traductions et quatre lignes journal, dont réel 3 709/65/23 878 avec 627 signalements. Capsule 22 239 fichiers/3 477 829 369 octets entièrement vérifiée avant création puis à la clôture, séparée du SQL. Dump 35 850 176 octets, maximum instruction 8 852 675 octets sous 16 Mio ; durée observée 102,525s, aucun engagement de délai.

Contrôle final en lecture seule : cinq SELECT seuls pour le nouveau compte, sans délégation/permission supplémentaire ; six comptes/droits historiques conservés et `homya_local` vide. Les 62 témoins runtime/.env/my.cnf et tous les fichiers de la première tentative sont inchangés. Deux paquets, cible et compte conservés ; secrets privés 0600, dump/contextes 0400, dossiers 0700, exclusions Git vérifiées. Aucun code modifié pendant cette reprise, aucune migration/import local, service/réglage XAMPP ou activation du lecteur SQL. Catalogue public toujours sur fichiers.

Restauration isolée locale réellement testée ; récupération après perte du disque non testée, SQL seul sans médias. [Synthèse vérifiée](../output/qa-import/op07-reprise-synthese-verification.json). OP-08 à préparer puis autoriser pour le schéma local ; OP-09 import local et OP-10 bascule restent exclus de cet accord. Les sections précédentes conservent l'historique des phases antérieures.

## OP-08 — préparation documentaire sans SQL

Demande de préparation du schéma local, sans autorisation de création/migration. [Plan v0.1](OP-08-SCHEMA-LOCAL-PREPARATION.md) : cinq tables dans la base `homya_local` créée antérieurement, quatre migrations catalogue existantes et journal ; trois comptes locaux conservés, douze GRANT proposés (16 ajouts de table + trois UPDATE de colonne), dont deux REFERENCES avant les FK. Ni nouvelle base/compte, import/fixture, réglage XAMPP ni activation du site inclus.

Inspection du code/gardes/commandes/preuves OP-07 et des seules métadonnées des accès privés. DDL recompilé sans PDO identique au témoin : 6 492 octets, empreintes DDL/fichiers inchangées. 46 tests existants du schéma/garde/migrateur sur doubles passent, 275 assertions ; premier essai empêché par le sandbox lors de création de sockets Unix synthétiques, second réussi hors sandbox, rapports conservés. Aucun SQL réel ni contenu d'accès privé existant chargé, aucune migration/app/configuration/.env/my.cnf changée ; registre central inchangé.

Les preuves OP-07 donnent historiquement un local vide et dix CREATE/ALTER seuls ; aucun contrôle SQL frais pendant cette préparation. Connexions sélectionnant le local, état/droits/capacité et migration restent à vérifier sous accord futur. [Programme documentaire](../output/qa-import/op08-preparation-programme-propose.json), [synthèse de préparation](../output/qa-import/op08-preparation-synthese-verification.json).

La commande de migration partagée connaît OP-08 ; les enveloppes administratives/journal OP-05 fixent le test et ne doivent pas être utilisées sur le local. Complément de reçus/audit/journal/verrou/commandes/tests locaux à préparer sans base avant de présenter une autorisation groupée OP-08. Aucun exécuteur ou reçu OP-08 créé dans cette phase, aucune permission d'exécution demandée sur un outil incomplet. Catalogue public toujours sur fichiers ; OP-09 import et OP-10 bascule distincts.

## OP-08 — outils locaux et tests livrés sans base

Demande de préparation des outils sans base consignée/re-lue DECISION-037. [Plan v0.2 §10](OP-08-SCHEMA-LOCAL-PREPARATION.md#10-complément-doutils-vérifié-sans-base--08102026) : enveloppe Python de reçu/verrou/audits/GRANT ciblés, helper PHP journal/connexions locales et tests dédiés. Les outils historiques et quatre migrations existantes sont conservés.

79 tests Python passent (18 nouveaux OP-08), 50 tests PHP/326 assertions sur doubles sans PDO réel, syntaxe et Pint valides. Refus sans reçu, matrice de droits, frontières d'erreur et verrou testés sans MariaDB ; les sockets Unix temporaires servent uniquement aux fixtures. [Synthèse](../output/qa-import/op08-outils-synthese-verification.json). Aucun secret existant chargé, aucun SQL réel ni migration/import local, aucun changement XAMPP/.env/code du site. Programme hors connexion figé ; aucun reçu d'exécution émis.

Le précontrôle SQL/identité/données et les trois connexions locales restent à renouveler sous autorisation groupée distincte : cinq tables dans la base existante, douze GRANT bornés, quatre migrations dédiées, vérifications finales. Les quatre archives resteront vides et le site continuera de lire les fichiers. Import et bascule explicitement exclus ; stop sans nettoyage/rejeu à toute collision ou erreur.

## OP-08 — exécution partielle arrêtée, reprise préparée

Accord utilisateur consigné/relu DECISION-038, reçu/empreintes contrôlés avant SQL. Précontrôle XAMPP et données test/copie/capsule valides ; seul journal créé sous compte schema et ses SELECT/INSERT accordés. Le premier REFERENCES seul sur une table absente a été refusé (1146) : arrêt immédiat sans retry/nettoyage. **Aucune migration locale démarrée**, aucune archive locale, aucun import/bascule/nouveau compte/base ni changement XAMPP/.env/application.

[Audit après arrêt](../output/qa-import/op08-arret-controle-independant.json) : journal vide, douze seuls droits locaux de schema ; comptes/droits/données test/restauration, capsule complète et runtime conservés. Le schéma local reste incomplet. [Plan de reprise ciblée](OP-08-REPRISE-CIBLEE.md) : CREATE déjà présent réaffirmé dans les deux GRANT REFERENCES pour satisfaire le contrôle des tables absentes de MariaDB 10.4.28, sans élargir la matrice finale. Onze GRANT restants, quatre migrations, probes finales ; aucune recréation du journal ni rejeu de son GRANT.

Nouveaux outils/tests sans base, anciens outils et tentative conservés. Accord/reçu de reprise distincts nécessaires avant SQL ; aucune reprise effectuée. Le site continue de lire les fichiers ; OP-09 import et OP-10 bascule restent exclus.

## OP-08 — reprise autorisée, schéma local et connexions vérifiés

Accord « J’autorise la reprise OP-08 dans ce périmètre » consigné/relu DECISION-039 avant SQL. Précontrôle renouvelé sur journal vide connu ; onze GRANT restants et quatre migrations dédiées réussis, sans recréation du journal ni rejeu de son premier GRANT. [Clôture détaillée](OP-08-REPRISE-CIBLEE.md#7-reprise-autorisée-et-contrôle-final--08102026), [contrôle indépendant](../output/qa-import/op08-reprise-controle-independant.json).

`homya_local` : cinq seules tables InnoDB/utf8mb4_bin, 34 colonnes, cinq PK/deux UNIQUE, 27 CHECK et quatre FK RESTRICT locales ; quatre archives vides et quatre lignes techniques de journal id1–4/batch1/AUTO_INCREMENT5. Matrice locale exacte 26 droits de table + trois UPDATE de colonne bornés ; trois comptes existants, connexions Laravel/PDO et lectures minimales validées. Aucun privilège global/schéma/délégation/rôle/proxy ni accès croisé ajouté.

Source test/copie OP-07 DDL/droits/données re-hachées inchangés, premier essai conservé, 122 témoins et 33 preuves OP-07 intacts. Capsule complète 22 239 fichiers/3 477 829 369 octets vérifiée avant écritures et à clôture par l'enveloppe ; runtime/.env/my.cnf inchangés. Aucun fichier applicatif/migration/service/configuration XAMPP modifié. Les tests hors base précédents ne sont pas annoncés comme rejoués ; contrôles SQL/PDO réels réussis dans cette reprise.

**OP-08 TERMINÉE dans son périmètre**, [synthèse](../output/qa-import/op08-reprise-synthese-verification.json). Le catalogue SQL reste vide et le site conserve la lecture fichiers ; aucun import/bascule, nouvelle base/compte, restauration/purge ou transaction commerciale. OP-09 import local à préparer et autoriser séparément ; OP-10 bascule reste distincte.

## OP-09 — plan validé, outils vérifiés sans base

Validation du plan v0.1 et autorisation d'outils/tests sans base consignée/re-lue DECISION-040. [Plan v0.2 et livraison](OP-09-IMPORT-LOCAL-PREPARATION.md#8-outils-livrés-après-accord--08102026) : nouvelle enveloppe Python, helper CLI import/lecture et tests dédiés. Reçu distinct requis avant tout export/SQL/import, tentative unique et tickets bornés, sauvegarde préalable des cinq tables, rapprochement exhaustif et conservation ; aucun GRANT/DDL ni bascule inclus.

111 tests Python (23 nouveaux) et 69 tests PHP/338 assertions (sept nouveaux/58 assertions) réussis sans base ; syntaxe/Pint et refus sans reçu contrôlés. Sockets Unix temporaires synthétiques hors sandbox, PDO réel interdit. Les 183 témoins existants restent inchangés. [Synthèse](../output/qa-import/op09-outils-synthese-verification.json).

Programme préparé hors ligne, aucun accès dédié réel chargé, SQL/diagnostic/export/sauvegarde locale/import/migration ou changement XAMPP/.env/site exécuté. La simulation hors SQL de 74,243s préparée précédemment reste conservée, pas relancée. Le nouveau dump local et les connexions devront être vérifiés sous l'autorisation groupée d'exécution ; paquet sur même disque et restauration de ce futur paquet non testée. Site toujours sur fichiers, OP-10 exclue.


## OP-09 — import local autorisé et vérifié

Accord « J'autorise » consigné/relu DECISION-041 avant SQL. Précontrôles XAMPP/local vide et droits réussis, sauvegarde préalable contrôlée puis **un seul import** du lot réel :1 lot/65 catégories/3 709 produits/23 878 traductions. Relecture PDO reader distincte, digest exhaustif et audit natif indépendant concordants ;627 signalements/479 produits conservés, aucun arbitrage commercial implicite. [Clôture détaillée](OP-09-IMPORT-LOCAL-PREPARATION.md#9-exécution-autorisée-et-clôture-native--08102026), [synthèse](../output/qa-import/op09-execution-synthese-verification.json).

Export préalable des cinq tables locales8 156 octets, DDL/compteurs/journal exacts/hachage vérifiés ; même disque et **restauration de ce paquet non testée**. Capsule/originaux22 239 fichiers/3 477 829 369 octets chacun entièrement contrôlés avant et après par l'enveloppe ; contrôle indépendant SQL sans répéter les médias. Test/copie/droits/DDL/journal/runtime et183 témoins historiques inchangés ; aucun nouveau compte/base/GRANT/migration/service/.env/application ni restauration.

OP-09 terminée dans son périmètre d'archive consultative non vendable ; site toujours sur fichiers, anomalies et règles commerciales ouvertes. Aucun code modifié, tests hors base précédents non rejoués ; aucun contrôle navigateur revendiqué. OP-10 lecture SQL/parité web à préparer puis autoriser séparément, aucune bascule incluse.

## OP-10 — préparation du raccordement, sans SQL ni bascule

[Plan OP-10 v0.1 proposé](OP-10-LECTURE-SQL-PREPARATION.md) : petit contrat de source fichiers/SQL, projection publique commune, garde web distincte limitée au reader local et au lot vérifié, états503 sans fallback automatique. Les gardes/lecteur et outils CLI historiques restent dans leur contexte actuel. La reconstruction complète du lecteur privé et la limite PHP CLI128M nécessitent des mesures avant de retenir le chemin HTTP ; aucune performance web sous SQL présumée.

Inspection du code, routes, vues, configurations, tests et preuves OP-09 ; comptage fichiers actuel3 709 produits/65 catégories,11 939 entrées dans chacun des dictionnaires EN/DE et35 overlays. Métadonnées seules des accès dédiés examinées, empreintes des preuves natives OP-09 relues. Aucun SQL frais, bootstrap applicatif/chargement de secret dédié, test applicatif/build/navigateur ou changement de code/.env/XAMPP. Le site continue de lire les fichiers.

Source proposée par clé non secrète `HOMYA_CATALOGUE_SOURCE` : défaut fichiers, sélection SQL après recette et sauvegarde privée `.env`, sans changer `DB_*`. Ce choix et le développement restent à valider/autoriser ; aucun reçu ni décision d'exécution OP-10 émis. Après outils/tests hors base, présenter l'autorisation native groupée pour parité/candidat isolé en lecture seule, mesures et sélection conditionnelle, avec retour opérateur aux fichiers si explicitement retenu. [Preuves de préparation](../output/qa-import/op10-preparation-synthese-verification.json).


## OP-10 — adaptateurs et tests hors base livrés

DECISION-042 enregistrée/relu avant développement : choix regroupés validés, adaptateurs/tests hors base seuls autorisés. [Plan v0.2 et preuves](OP-10-LECTURE-SQL-PREPARATION.md#9-livraison-hors-base--decision-042). Source fichiers conservée ; `.env` et XAMPP inchangés, aucune lecture SQL réelle ni chargement de l’accès dédié réel.

Contrat fichiers/SQL, garde web locale reader distincte, lot/profil fixé, captures503 localisées sans fallback, galeries de secours en paquets64. La reconstruction complète dépasse128M ; lecture ciblée avec empreintes privées immuables retenue conformément au plan. Profils sur doubles à128M : pics55 066 624octets FR et69 206 016octets EN/DE ; aucune modification de php.ini et performance HTTP/PDO réelle non prouvée.

223 tests PHP/1 476 assertions réussis hors suite MariaDB, syntaxe/Pint contrôlés ; parité stricte de3 709 cartes et3 709 fiches par langue et filtres/pagination vérifiée hors base. Les anciennes gardes CLI, migrations, scripts OP-04..09 et assets sains sont conservés. Aucun navigateur/build ni rehash intégral frais des médias revendiqué. Prochaine phase : préparer la recette native en lecture seule et son autorisation ; bascule ultérieure sous une autre autorisation, sans création/import/restauration.

## OP-10 — recette native corrigée et vérifiée, site conservé

Les notes précédentes gardent leurs états historiques. Accord natif DECISION-043 : arrêt sur décodage JSON du premier contrôle serveur, aucun reader/candidat lancé ; conservation fichiers vérifiée. Après instruction utilisateur de corrections à chaque étape, correctif hors base et reprise bornée DECISION-044 : serveur contrôlé en colonnes tabulaires,13 tests Python et message opérateur corrigé sur test isolé, puis connexion réelle réussie. Aucun correctif XAMPP/moteur nécessaire ou appliqué.

[Recette et clôture détaillée](OP-10-RECETTE-NATIVE-LECTURE-SEULE.md#10-reprise-corrective-et-clôture-technique--decision-044) : audits63lectures avant/après identiques, payload et parité3709 cartes/fiches par langue FR/EN/DE,78 parcours par candidat et mesures ; revue navigateur375/768/1440, images visibles/menu/clavier/langue/galerie/recherche/filtres/erreurs. Native CLI pic~282Mio sous512M ; candidat SQL HTTP pic56,1Mo sous128M, listes5/6requêtes sans requête par carte. Deux candidats fermés, verrou libéré.

Capsule/originaux re-hachés intégralement avant/après (22239 fichiers/3477829369 octets chacun),244 témoins et33 artefacts identiques en fin d'exécution, avant mises à jour documentaires. Aucun SQL persistant, migration/import/droit, service/configuration XAMPP/.env ni source du site changé. [Synthèse native](../output/qa-import/op10-recette-native-reprise-synthese-verification.json).

Source active **files**, bascule non autorisée dans cette recette. [Périmètre de bascule locale proposé](OP-10-BASCULE-LOCALE-PERIMETRE.md), accord distinct avec sauvegarde privée de configuration et retour borné. La qualité linguistique reste à revoir (mots étrangers résiduels) ;627 signalements, prix/stock actuels et fonctions/règles de vente ouverts. Aucun site pleinement commercial ou conforme production revendiqué.

## État courant — SQL et installation htdocs vérifiés le08/10/2026

Les paragraphes précédents sont historiques. DECISION-045 : sélection réelle `sql`, sauvegarde privée de configuration, autres valeurs inchangées. DECISION-046 : projet intégral installé dans `/Applications/XAMPP/xamppfiles/htdocs/HOMYA` ; ancien dossier préservé hors webroot et original de travail conservé. Apache XAMPP isolé127.0.0.1:8080 avec PHP-FPM8.5.11 sur19085 ; MariaDB XAMPP10.4.28/3307/socket existant et reader dédié conservés. Config globale XAMPP et autres services inchangés.

[Accès local et preuves](ACCES-LOCAL-XAMPP.md) : URL principale `http://127.0.0.1:8080/fr`, catalogue SQL3 709 produits, FR/EN/DE ; site consultatif. Aucun compte client, administration commerciale, panier/commande/paiement opérationnel ou déploiement déclaré. Hostinger retenu ultérieurement, formule/domaine non fournis ; données commerciales différées par utilisateur. Le PHP8.2 Apache intégré ne doit pas exécuter Laravel13.


## Optimisations locales après audit des workflows

DECISION-060, accord utilisateur du 08/10/2026. Premier historique Git du code, exclusion des archives/médias/preuves et configuration Pint ; clés de tri calculées une fois, recherche inutile évitée hors requête. Parité stricte de 18 collections FR/EN/DE. Variantes WebP distinctes pour 3 690 images, aucun original écrasé ; choix responsive, contrôle des empreintes et conservation des URLs originales. Sources contrôlées sans orientation EXIF différente de 1 ni image CMYK dans ce lot.

306 tests PHP hors base/7 327 assertions, trois tests Python, Pint et build réussis. Les refus de sockets fictifs dans le sandbox sont distingués de la suite complète réussie hors sandbox. Installation ciblée vérifiée (7 941 fichiers), Apache dédié rechargé progressivement après test de syntaxe ; mêmes PID Apache/FPM, configuration FPM et fichiers protégés conservés. Aucun SQL persistant, migration/import/droit, changement `.env`, arrêt de service ou production.

Recette de 21 pages/scénarios avec contenu, liens et images sources identiques ; CSS gzip réellement servi de 97 435 à 19 185 octets, assets adressés par contenu avec cache immuable. Médianes de trois réponses locales du catalogue FR/EN/DE avant : 0,909/0,838/0,928 s ; après : 0,722/0,829/0,838 s. Différences locales observées, sans garantie de production ni mesure CWV. Galerie clic/clavier et viewports 375/768/1440 contrôlés ; source et cible identiques pour les fichiers installés, empreintes de 3 690 originaux revérifiées des deux côtés.

Preuves `output/qa-optimization/` et instructions de récupération dans le README. Les originaux, anciennes preuves et anciens assets Vite sont conservés ; les variantes ajoutent environ 465 Mo sur disque. Restauration complète non exécutée. Les galeries secondaires non présentes dans l’index conservent leurs images originales ; des optimisations ultérieures restent possibles après mesure. Commerce et préparation production restent dans leurs périmètres antérieurs.

## Panier, commandes et inventaire — 9 octobre 2026

Installation locale autorisée, DECISION-061 : quatre tables commerce dédiées dans homya_local, compte commerce distinct du lecteur catalogue, 3 709 produits avec 1 000 unités déclarées chacun et stock parent partagé pour les 26 produits à variantes. Archives et anciens comptes conservés. DDL temporaires retirés après contrôle ; réservations et commandes testées sur MariaDB avec transport Stripe simulé et annulation de toutes les écritures de recette. Copie Apache8080 synchronisée avec sauvegarde et paramètres privés commerce uniquement.

319 tests PHP/7 418 assertions, recette MariaDB et 11 contrôles HTTP réussis. L’inventaire se lit dans les fiches et le panier FR/EN/DE ; les paiements restent fermés, compte Stripe à créer et prérequis commerciaux à finaliser. [Plan, résultat et limites](PARCOURS-ACHAT-HOMYA.md). Les états précédents sans commerce sont historiques.

Extension virement préparée, DECISION-062 : page RIB privée FR/EN/DE, repli carte vérifié et délai/réservation72h confirmé. Aucune migration ; reçus vérifiés par le marchand via CLI privée. Planificateur local dédié requis, distinct des services Apache/FPM. RIB à fournir et activation des achats fermée. Voir [parcours et exploitation](PARCOURS-ACHAT-HOMYA.md).

Clôture locale virement : 36fichiers installés avec sauvegarde commerce-backup-20261009T133003Z, 327tests/7533assertions, recette MariaDB annulée et 15contrôlesHTTP réussis. Planificateur dédié PID12476 actif ; exécution minute observéeDONE. Le redémarrer avec `python3 scripts/start_commerce_scheduler.py --start` après un redémarrage du poste. Aucune activation réelle carte/virement ou nouvelle migration.
