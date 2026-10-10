# HOMYA — OP-07 : sauvegarde SQL et restauration isolée

Version 0.4 — 8 octobre 2026 — **REPRISE AUTORISÉE ET RESTAURATION ISOLÉE VÉRIFIÉE ; SITE NON BASCULÉ**.

Demande : préparer et éprouver OP-07 avant toute bascule vers la lecture SQL. DECISION-034 valide les choix/outils hors base ; DECISION-035 trace la première tentative interrompue avant création, puis sa correction. L'accord « J’autorise la reprise OP-07 » est enregistré/relu DECISION-036 avant la seconde tentative : restauration isolée réussie, comparaisons intégrales et droits réduits vérifiés (§11). Les §1–10 gardent leurs états historiques ; la reprise n'y était pas encore exécutée. [v0.1 validée](../output/qa-import/op07-plan-valide-v01.md), [v0.2 autorisée figée](../output/qa-import/op07-execution-plan-autorise-v02.md), [v0.3 autorisée figée](../output/qa-import/op07-reprise-plan-autorise-v03.md).

## 1. Sources, état de départ et limites

Références : [plan technique §10](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md#10-opérations-précises-à-autoriser-séparément--aucune-exécutée), OP-07 / TECH-05 / IMP-04 ; [clôture OP-06 §13](OP-06-RECETTE-XAMPP-PREPARATION.md#13-reprise-autorisée-et-clôture--08102026). DECISION-033 clôt la recette test et exclut explicitement OP-07. Le [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md) demeure le seul registre : DECISION-034 valide les choix et outils hors base, aucune exécution SQL. [Enregistrement et relecture](../output/qa-import/op07-outils-enregistrement-accord.json).

| Élément | Qualification et preuve |
|---|---|
| Serveur et schéma source | **Fait historique**, audit OP-06 : MariaDB XAMPP 10.4.28, port 3307, socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, datadir XAMPP ; `homya_test`, cinq tables InnoDB/utf8mb4_bin. Identité et état SQL à renouveler avant exécution. |
| Tables à exporter | **Périmètre déjà prévu dans OP-07** : `archive_lots`, `archive_categories`, `archive_products`, `archive_translations`, `migrations`, intégralement. Ni `mysql`, ni utilisateurs/privilèges, ni autre schéma. |
| Données attendues | **Fait historique**, [synthèse du 08/10 à 12:33 UTC](../output/qa-import/op06-reprise-synthese-verification.json) : 5 lots, 67 catégories, 3 711 produits, 23 890 traductions ; journal 4 lignes. Le lot réel contient 3 709 produits/65 catégories/23 878 traductions/627 signalements. Les quatre fixtures restent incluses, avec leurs états et preuves. |
| Structure attendue | **Fait historique**, [DDL audité](../output/qa-import/op06-run-20261008T121403Z-a43af607-46fa-4eb1-8356-91138466d6c3/final-admin.json) : cinq tables, 27 CHECK et quatre FK, actions RESTRICT ; journal AUTO_INCREMENT=5. PK, UNIQUE, colonnes, index, collations et options exactes à comparer, pas seulement ces nombres. |
| Capsule et médias | **Fait vérifié lors d'OP-06** : clé `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809`, 22 239 fichiers, 3 477 829 369 octets par jeu original/copie ; tailles/SHA-256 identiques. Manifeste re-haché aujourd'hui, fichiers complets non re-hachés dans cette préparation. |
| Outils et espace aujourd'hui | **Observation sans connexion** : clients XAMPP `mysql` et `mysqldump` 10.4.28 présents ; versions/aides inspectées. `df` : 56 800 000 Kio libres, environ 54,2 Gio, valeur variable. Projet, datadir et `/private/tmp` sur le même volume. [Preuve](../output/qa-import/op07-preparation-inspection.json). |
| Site et droits | **État historique OP-06** : site sur fichiers, `homya_local` vide, six comptes et 36 droits de table + trois droits de colonne. Aucun compte existant n'a, à lui seul, le rôle proposé pour restaurer une nouvelle base ; le lecteur test ne peut pas exporter le journal. |
| Ancienne copie physique XAMPP | **Sauvegarde historique**, vérifiée le 07/10 à 21:12 UTC, restauration non testée. Antérieure à la recette actuelle et sur le même disque. Elle ne constitue pas la sauvegarde SQL OP-07 ni une preuve de récupération du catalogue actuel. |

Onze fichiers de preuve du dernier run OP-06 et l'empreinte du registre correspondent à la clôture consignée. Cela prouve la conservation des documents, **pas l'absence de changements SQL depuis OP-06**. Un écart au prochain précontrôle arrêtera l'opération pour inspection.

## 2. Choix structurants validés en conception — DECISION-034

**Recommandation :** export logique natif des cinq tables, puis restauration dans une seule nouvelle base sur le même serveur XAMPP, avec un compte dédié restreint. Cette méthode couvre le catalogue sans arrêter MariaDB ni restaurer ses tables système. Une instance indépendante ajouterait installation et configuration ; elle restera utile pour une future épreuve de sinistre ou de changement de version, hors de cette recette locale.

| Choix | Proposition / statut |
|---|---|
| Cible isolée | `homya_restore_op07`, charset utf8mb4/collation utf8mb4_bin — **Décision validée en conception**. Liberté du nom non vérifiée par SQL aujourd'hui. Refus si la base existe, même vide ; aucun renommage ou remplacement automatique. |
| Compte neuf | `homya_op07_restore` à l'hôte `localhost` — **Décision validée en conception**. Refus si compte/rôle rencontré. Secret distinct à générer et conserver localement lors de l'exécution autorisée, jamais dans le chat, le dump ou les arguments. |
| Export et contrôle administratifs | Accès administratif local déjà employé dans OP-06, uniquement pour lecture des cinq tables/métadonnées bornées lors de l'export ; création ciblée et habilitation séparées lors de la restauration — **Méthode validée, accès réel À vérifier**. Le dump ne sera jamais exécuté avec ce compte administratif. |
| Conservation | Conserver dump, preuves et cible de recette après contrôle ; réduire le compte à SELECT sur les cinq tables — **Décision validée pour la recette**. Aucune suppression automatique. Rétention définitive et nettoyage ultérieur : **Inconnu**, accord ciblé nécessaire avant suppression. |
| Copie indépendante | Destination, accès, chiffrement et politique de conservation : **Inconnu**. Ne bloque pas la recette locale ; bloque toute affirmation de sauvegarde résistante à la perte de ce disque. Aucune copie réseau/externe implicitement autorisée. |

### Droits du compte de restauration proposé

Uniquement sur les cinq noms de table de `homya_restore_op07` : **CREATE, INSERT, SELECT**, soit 15 privilèges ; **REFERENCES** seulement sur `archive_lots` et `archive_categories`, soit deux privilèges supplémentaires, par prudence au regard de l'incident FK d'OP-05 sur la version installée. Cinq GRANT de table pourront regrouper cette matrice ; aucun droit sur `schema.*` ou `*.*`, aucun ALL/GRANT OPTION/role/proxy/global, ni accès à `homya_test`/`homya_local`.

Aucun ALTER, DROP, DELETE, UPDATE, LOCK TABLES, FILE, TRIGGER, EVENT ou EXECUTE. La proposition suppose des CREATE TABLE complets avec contraintes et l'absence d'ALTER dans le dump ; si l'outil exige davantage, arrêt et revue, jamais élargissement silencieux. Après réussite, retirer CREATE/INSERT des cinq tables et REFERENCES des deux parents : **12 privilèges retirés, cinq SELECT conservés**. Cette réduction devra être incluse dans l'autorisation groupée d'exécution. En cas d'échec, tenter cette même réduction bornée et rendre son résultat ; une indisponibilité SQL ne permet pas de la déclarer réussie.

Les six comptes existants sont conservés. Le garde-fou applicatif [ArchiveDatabaseGuard](../app/Services/ArchiveDatabaseGuard.php) n'accepte que les rôles test/local ; il **ne sera pas élargi** pour cette cible. Un vérificateur CLI spécifique OP-07 pourra lire la cible autorisée et réutiliser les contrôles purs de payload ; aucune connexion de restauration ne sera ajoutée au site.

## 3. Sauvegarde proposée et options réelles

Nouveau dossier privé, sans réutilisation : `storage/app/private/database-backups/op07/<UTC-UUID>/`, répertoires 0700/fichiers 0600, propriétaire local, sans lien symbolique. Le nom final et les empreintes des outils seront figés dans le dossier d'autorisation après développement. Aucun dossier de sauvegarde SQL créé dans cette phase.

Le paquet comprendra : dump SQL non compressé, manifeste (tailles/SHA-256/versions/cible/options), inventaire des cinq tables et empreintes de toutes leurs lignes, DDL, journal exact, preuves OP-06 nécessaires et références des migrations/code de vérification figés. Aucun `.env`, secret, donnée d'authentification ou copie des tables système. Les accès seront gérés séparément, sans inventer une sauvegarde de secrets.

**Images :** elles ne sont pas contenues dans le SQL. Le manifeste référencera la capsule privée complète, son manifeste et toutes les empreintes ; les chemins devront être relatifs et résolus dans un répertoire autorisé. Le test utilisera cette copie déjà figée, sans re-téléchargement ni retouche. Le paquet SQL seul devra porter explicitement `media_included=false` et le chemin/empreinte de la capsule requise. Une sauvegarde autonome à déplacer devra réunir SQL, manifeste, capsule complète et code nécessaire ; seule une copie des médias avec relecture indépendante prouvera cette transportabilité.

Options proposées, acceptées ensemble par le binaire installé **avec `--help` uniquement, code 0, aucune connexion** :

```text
--single-transaction --quick --skip-lock-tables
--skip-add-locks --skip-add-drop-table --skip-disable-keys
--skip-extended-insert --complete-insert --hex-blob
--skip-triggers --skip-events --skip-routines --no-tablespaces
--default-character-set=utf8mb4 --max-allowed-packet=16777216
--skip-comments --skip-dump-date --tz-utc
```

Le processus recevra une liste d'arguments fixe : client XAMPP exact, options isolées des configurations héritées, socket exact, `homya_test`, puis les cinq noms explicites. Pas de `--databases`, `--all-databases`, `--system`, `--force`, `--flush-*`, `--master-data`, `--tab`, `--where`, REPLACE ou INSERT IGNORE. Les variables d'accès MySQL/MariaDB héritées seront neutralisées. Si une authentification privée devient nécessaire, le fichier d'options 0600 sera explicitement contrôlé ; aucun mot de passe dans argv ou stderr conservé.

Les défauts observés `add-drop-table`, `add-locks`, `disable-keys`, `lock-tables` et `triggers` sont actifs dans l'aide sans options : ils ne conviennent pas ici. Les drapeaux ci-dessus les désactivent explicitement. Un INSERT par ligne facilite la mesure de chaque instruction, sans fusion de gros documents. Les tailles après échappement SQL, y compris les documents du lot, seront mesurées : **transport SQL natif de ce dump encore À vérifier**, même si PDO a passé OP-06. Aucun relèvement global des 16 Mio n'est inclus.

La cohérence InnoDB sera obtenue par un seul export transactionnel des cinq tables. Pendant le contrôle/export, aucun outil HOMYA ne devra écrire ou faire du DDL sur la source ; vérification des empreintes source avant/après. Un verrou coopératif des outils n'empêche pas une intervention manuelle externe : cette limite reste explicite. Aucun verrou global serveur, arrêt de service ou réglage XAMPP proposé.

## 4. Déroulement contrôlé à développer, puis autoriser

1. **Précontrôle sans écriture :** vérifier accord figé, outils, fichiers privés/capacité, identité serveur/socket/datadir/port/version, sessions/paquets/modes, source exacte et droits, état des lots/journal, absence de vues/triggers/routines/events et collisions cible/compte. Comparer les données à la clôture OP-06 ; tout écart bloque la suite.
2. **Exporter :** créer un fichier neuf `.partial` et un dump borné aux cinq tables ; attendre la fin du processus, vérifier code 0/stderr expurgé, contenu complet et tailles. Ne pas publier un dump partiel comme sauvegarde valide. Mesurer taille, SHA-256, durée et instruction maximale, confirmer les empreintes source inchangées. Une erreur interdit toute restauration.
3. **Inspecter et figer le dump :** lire les instructions avec prise en compte des chaînes, échappements et commentaires exécutables MariaDB. Une recherche textuelle naïve ne suffit pas : un texte produit peut contenir des mots SQL. Autoriser uniquement les cinq CREATE TABLE attendus, INSERT à colonnes explicites et réglages de session natifs recensés. Refuser changement de base, noms qualifiés vers un autre schéma, DDL/SQL supplémentaires, DEFINER, global SET, fonctions/chargements de fichiers et commandes du client. Vérifier aussi les références FK dans les CREATE. Toute construction inconnue arrête la recette. Après contrôle, nom final neuf et manifeste validé, protégés contre substitution.
4. **Créer la cible seulement après autorisation :** dernier contrôle de collision immédiatement avant CREATE DATABASE, CREATE USER et les cinq GRANT bornés. Pas de `IF NOT EXISTS`, OR REPLACE, modification d'un objet rencontré ni suppression de rattrapage. Contrôle administratif expurgé des seules identités/permissions prévues.
5. **Restaurer :** client XAMPP exact, compte dédié, sélection de `homya_restore_op07`, socket fixé, entrée depuis le dump vérifié ; mode batch/binary, `local-infile=0`, pas de force ni reprise automatique. Les commandes client, y compris DELIMITER et `\C` encore possibles en mode binary, restent refusées par le contrôle du dump. `--one-database` ne sera pas utilisé comme frontière de sécurité. Vérification de la sélection effective avant et après ; journal de progression sans valeurs de lignes ni secrets.
6. **Contrôler :** nouvelle connexion de lecture ciblée, contrôles §5, comparaison complète source/copie et capsule ; pas d'importeur Laravel ni migration pour recréer la copie. Les cinq tables sont reconstruites depuis le dump, y compris le journal. Les réglages de session importés ne doivent pas changer les variables globales ni les événements OFF.
7. **Clore :** retirer les 12 droits de création/écriture/références, vérifier les cinq SELECT restants et l'absence de droits supplémentaires ; contrôler la source, les six comptes historiques, `homya_local`, la configuration et le site inchangés. Conserver dump, cible et rapports ; aucune activation du lecteur SQL.

**Arrêt/échec :** source jamais corrigée ou restaurée ; DDL de cible potentiellement partiel, aucun rollback global promis. Conserver cible partielle et preuves avec statut INCOMPLET, signaler les droits résiduels ; pas de purge, replay ou nouveau nom automatique. Une tentative ultérieure exige inspection et accord adapté. Le secret du compte cible ne figurera dans aucun reçu d'autorisation ou manifeste de sauvegarde.

La capacité finale demeure **À vérifier** : taille réelle SQL, taille des tables/index source et allocation de cible seront mesurées avant sa création ; réserve disque et plafond de supervision seront fixés dans le plan technique exécutable après ces mesures. Les 54,2 Gio observés ne garantissent ni capacité restante au jour du test, ni durée de restauration. Pas de RPO/RTO, cadence ou durée de conservation inventés.

## 5. Critères d'acceptation liés à TECH-05 / IMP-04

Tous **P1 proposés** : ils conditionnent une preuve de récupération avant OP-08/09/10. Résultats actuellement **À vérifier**, aucune ligne n'est déclarée passée.

| ID | Critère observable | Dépendance |
|---|---|---|
| OP07-01 | Cible XAMPP exacte, nouveau schéma/compte absents avant création, droits de source et état OP-06 sans écart ; aucune connexion Homebrew/3306. | Accord d'exécution, précontrôle réel |
| OP07-02 | Dump fini code 0, cinq tables seulement, SHA/tailles/instructions vérifiés, absence de contenu d'authentification, fichiers privés non substitués. | Export réel, vérificateur testé sans base |
| OP07-03 | Dump accepté par une liste fermée d'instructions/objets ; mutants hors périmètre et commandes client refusés avant connexion d'écriture. | Tests hors base, syntaxe native installée |
| OP07-04 | Cinq tables restaurées : DDL, types, colonnes, PK/UNIQUE/index, moteur/collations, 27 CHECK/quatre FK et RESTRICT identiques ; journal exact, AUTO_INCREMENT conservé. | Restauration sur cible neuve |
| OP07-05 | Toutes les lignes de chaque table identiques, triées par clé primaire, encodage canonique typé et empreintes égales ; préservation NULL, binaires, décimaux, dates à six décimales, textes/JSON exacts et ordre. Les seuls COUNT ne suffisent pas. | Lectures source/cible cohérentes et empreintes |
| OP07-06 | 5 lots/67 catégories/3 711 produits/23 890 traductions +4 lignes journal ; trois lots verified dont réel et deux synthétiques ; failed/prepared sans enfants. Champs de vérification/dates conservés. Payload réel et payloads synthétiques relus à l'identique ; refus de servir failed/prepared. | OP07-05, contrôles purs d'archive |
| OP07-07 | FK/CHECK actifs dans la nouvelle session, aucune référence orpheline ou croisée, domaines/JSON/états vérifiés sur les lignes chargées ; aucune dépendance FK vers la source. | Contrôles explicites après chargement |
| OP07-08 | Capsule requise accessible dans la racine privée déclarée : 22 239 tailles/SHA conformes ; références galerie/overlays/langues/rapports inchangées. Rapport distingue restauration SQL et présence des médias. | Capsule complète et manifeste |
| OP07-09 | Source/DDL/journal/droits historiques intacts ; compte cible réduit aux cinq SELECT, aucun droit global/schéma/délégation/croisé ; secrets exclus des preuves. | Audit final, réduction de droits autorisée |
| OP07-10 | Configuration/.env/code métier/migrations/site inchangés, catalogue toujours sur fichiers ; mesures taille/durée/erreurs et limites consignées sans prétendre à une reprise de sinistre ou activation. | Témoins avant/après et rapport final |

La comparaison des lignes devra préciser son format : colonnes dans l'ordre du schéma, lignes dans l'ordre des PK, marqueur NULL distinct de chaîne vide, longueur et octets des textes/binaires, valeurs numériques/dates normalisées sans perte. Réutiliser les contrôles existants sur les lots verified ; comparer directement les parents failed/prepared sans contourner leur refus applicatif.

Le dump natif peut comporter une désactivation **de session** des contrôles FK. La remise à 1 ne constituera pas une preuve de validité des lignes déjà chargées : anti-jointures, contraintes inter-lots et contrôles de domaine seront exécutés explicitement après restauration. Toute désactivation CHECK ou modification globale est exclue ; toute autre instruction de session non recensée est refusée.

## 6. Risques et contrôles spécialisés

| Risque / statut | Contrôle proposé et limite |
|---|---|
| Mauvaise destination — risque majeur | Cible neuve nommée, socket/identité figés, compte sans droit sur source, dump contrôlé ; jamais de restauration sous compte administratif. |
| Fichier altéré ou SQL hors périmètre — risque majeur | SHA/taille/stat/propriété/droits avant lecture ; analyse consciente des chaînes/commentaires exécutables ; refus par défaut et tests de mutants. |
| Dump incohérent ou partiel — À vérifier | InnoDB/transaction unique, absence de DDL/écritures HOMYA, contrôle intégral avant/après, retourcode strict, aucun replay. |
| Paquet échappé trop grand — À vérifier | Une ligne par INSERT, mesure réelle de chaque instruction et des nouvelles sessions ; arrêt sans modifier XAMPP. |
| Copie incomplète sans photos — risque identifié | Capsule explicitement requise, inventaire et hashes, aucune affirmation d'autonomie du SQL seul. |
| Perte du disque — limite identifiée | Copie indépendante à choisir plus tard ; cette recette sur même serveur/volume ne l'éprouve pas. |
| Compte de recette conservé — À confirmer | Secret privé et retrait ciblé des droits d'écriture/création à la fin ; échec du retrait visible, nettoyage ultérieur autorisé séparément. |

Database Engineering possède fidélité et contraintes ; Security possède destinations/droits/secrets ; Quality possède tests de refus, rapprochement et preuves. Modules consultés selon le routeur, sans revendication d'audit complet ni de préparation à la production. Codex natif utilisé ; outils Claude visibles, aucune authentification supposée ni appel réalisé.

## 7. Questions externes vérifiées et sources

Questions précises : la transaction d'export couvre-t-elle le DDL concurrent ? Les défauts du client ajoutent-ils DROP/verrous/triggers ? Peut-on reconstruire dans une cible bornée sans restaurer les comptes ? Les droits et contrôles FK documentaires suffisent-ils à prouver le comportement de XAMPP 10.4.28 ?

Consultation **08/10/2026** pour toutes les sources ci-dessous ; documentation actuelle, date de publication non établie. Les aides installées déterminent la disponibilité des drapeaux de 10.4.28 ; les pages actuelles couvrent aussi des versions ultérieures et ne prouvent pas une exécution locale.

| Source primaire / titre et URL | Portée et fait publié/observé étayé | Interprétation/recommandation HOMYA |
|---|---|---|
| [mariadb-dump — MariaDB](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump) | Export par base et liste de tables ; transaction cohérente InnoDB sans garantie face au DDL concurrent ; options de génération DROP/verrous. | Export borné aux cinq tables, options explicites, source stable ; syntaxe du dump réel encore à vérifier. |
| [Foreign Keys — MariaDB](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/foreign-keys) | Contraintes référentielles InnoDB et possibilité de désactiver les contrôles par variable de session. | Ne pas confondre contrôle actif à la fin et preuve d'intégrité ; vérifier toutes les références après chargement. |
| [GRANT — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant) | Permissions ciblables par table ; documentation actuelle décrit REFERENCES comme inutilisé. | L'incident OP-05 sur le moteur installé reste une preuve locale distincte : proposer deux REFERENCES bornés, pas généraliser la documentation à XAMPP. |
| [mysqldump — aide du binaire XAMPP](../output/qa-import/op07-mysqldump-aide-locale.txt) | Version 10.4.28 ; défauts et drapeaux disponibles, y compris SELECT transactionnel, hex-blob et suppression des DROP/verrous. Options proposées acceptées avec `--help`, sans serveur. | Ni sauvegarde exécutée ni transport/restauration démontrés. |
| [mysql — aide du binaire XAMPP](../output/qa-import/op07-mysql-aide-locale.txt) | Version 10.4.28 ; mode batch/binary, local-infile et effet de force ; binary laisse DELIMITER et `\C` interprétables. | Refus explicite des commandes client, chargement local désactivé, arrêt à l'erreur. |

L'ancien lien officiel « Restoring Data » ressorti par la recherche retourne **Page Not Found** à l'ouverture ; il n'est pas utilisé comme preuve. Les informations sans preuve de restauration réelle restent **À vérifier**. Aucune compatibilité avec une autre version/hébergeur n'est certifiée.

## 8. Validation et prochaine étape

**Arbitrage groupé validé — DECISION-034 :** export intégral des cinq tables ; cible neuve `homya_restore_op07` ; un compte `homya_op07_restore@localhost` avec 17 privilèges de table puis retrait des 12 droits de création/écriture/références ; conservation sans purge ; capsule existante vérifiée séparément, limitation au même serveur/disque acceptée pour cette recette.

Complément développé et testé **sans base** (§9). Présenter **une autorisation groupée d'exécution OP-07** comprenant export, création de la seule cible/compte, droits bornés, restauration, vérifications et réduction de droits. Elle ne comprendra aucune migration/import dans homya_local, purge, changement XAMPP ou bascule. La capacité SQL effective et les collisions seront mesurées au précontrôle après cet accord ; aucune capacité finale déduite des 54,2 Gio historiques.

**État de cette phase :** choix enregistrés, outils développés et tests hors base. Aucune connexion SQL, sauvegarde SQL réelle, restauration réelle, création de base/compte/table, migration, GRANT/REVOKE ou activation réalisée. DECISION-034 n'autorise que la conception et les outils/tests sans base.

## 9. Outils préparés, commandes et preuve hors base

| Fichier | Rôle et limite |
|---|---|
| [op07_xampp_backup.py](../scripts/op07_xampp_backup.py) | Préparation hors base par défaut ; orchestration conditionnée à un futur accord d'exécution enregistré/relu, reçu privé lié au plan/artefacts/binaires ; audits, export, création ciblée, restauration, comparaison et retrait des droits. N'émet jamais lui-même un reçu exécutable. |
| [op07_dump.py](../scripts/op07_dump.py) | Dialecte SQL fermé : split borné aux octets, chaînes/échappements, commentaires exécutables SET recensés, cinq DDL exacts comparés au témoin OP-06, INSERT à colonnes explicites et littéraux seulement, compteurs complets. Syntaxe native réelle encore À vérifier avant toute création. |
| [op07_snapshot.php](../scripts/op07_snapshot.php) | CLI source/copie : reçu validé avant bootstrap/PDO, aucune connexion à l'inclusion ou sans arguments ; snapshot cohérent, PK/colonnes, hash de toutes les lignes, journal et cinq états/payloads vérifiés, orphelins/inter-lots. Pas de nouvelle connexion applicative ni modification du garde existant. |
| [Tests Python du dialecte](../scripts/test_op07_dump.py), [tests de l'enveloppe](../scripts/test_op07_xampp_backup.py), [tests PHP](../tests/Unit/Op07SnapshotTest.php) | Doubles à toutes les frontières SQL/CLI de la simulation ; refus, paramètres, permissions, secrets en stdin, interruptions et comparaison, format des lignes et conversions sans perte. Aucun résultat de restauration réelle. |
| [.gitignore](../.gitignore) | Ajout ciblé `/storage/app/private/database-backups/` : les futurs dump/accès privés restent exclus de Git ; exclusion des deux futurs fichiers d'accès contrôlée. |

**Choix techniques proportionnés :** aucune dépendance installée ; bibliothèques standard Python 3.9, PHP 8.5.11/PDO et code pur du projet. PHP CLI borné à 512 Mio par snapshot, délai 300 s au plus par snapshot, supervision de l'opération 1 800 s avec arrêt sans replay. Ces limites sont des garde-fous proposés pour l'exécution, pas des engagements de durée. Vérification de PHP/PDO/posix sans connexion effectuée.

Budget disque futur : plafond d'export calculé à partir des octets de valeurs source lus, `2 × octets + 8 192 × nombre de lignes + 2 × octets DDL + 16 Mio`. Il couvre échappements/hex et syntaxe, sans annoncer une taille réelle. Exiger au moins `3 × plafond + 256 Mio` disponibles avant dump ; mesurer et surveiller le fichier partial. Cible InnoDB et SQL sur ce même volume : allocation réelle à vérifier, le facteur de réserve ne garantit pas l'absence de saturation ; une erreur stoppe sans nettoyage.

Le compte administratif exporte avec `--no-defaults`. La restauration utilise **`--defaults-file` comme premier argument** et un seul fichier 0600 généré dans le nouveau dossier privé ; `--defaults-extra-file` aurait conservé d'autres configurations et est exclu. Secret hex64 uniquement dans ce fichier et dans le JSON privé du compte, jamais dans les arguments ; CREATE USER envoyé par stdin, logs général/lent/binaires vérifiés OFF juste avant. Aucun accès privé des six comptes existants chargé pendant les tests.

Le verrou fichier OP-07 protège uniquement les exécutions OP-07. Il ne verrouille pas les importeurs ni les interventions manuelles ; leur arrêt d'écriture reste une condition opérationnelle de la recette. Les hashes complets source avant/après export et à la clôture, ainsi que les témoins .env/code métier/config/routes/vues/langues/migrations/my.cnf, détectent les dérives ; aucune prétention à un verrou serveur global.

Commandes de préparation/vérification **sans base** (dossier courant HOMYA) :

```text
python3 scripts/op07_xampp_backup.py
python3 -m unittest discover -s scripts -p 'test_op0*.py' -v
/opt/homebrew/bin/php -d memory_limit=512M vendor/bin/phpunit --testsuite Unit
/opt/homebrew/bin/php vendor/bin/pint --test scripts/op07_snapshot.php tests/Unit/Op07SnapshotTest.php
```

Commande d'exécution **PRÉPARÉE, INTERDITE AVANT ACCORD DISTINCT ET REÇU FUTUR** :

```text
/usr/bin/python3 /Users/akm/Documents/ChatGPT/HOMYA/scripts/op07_xampp_backup.py --execute --receipt=/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/op07-execution-autorisation.json
```

Le futur reçu devra correspondre à un nouvel accord d'exécution OP-07 inscrit/relu dans le registre, distinct de DECISION-034, consigné dans `op07-execution-enregistrement-accord.json`. Ces deux fichiers exécutables ne sont pas émis dans cette phase. Modifier un artefact/outil/plan après leur émission invalidera le reçu. Le [programme et les empreintes préparés](../output/qa-import/op07-outils-programme.json) ont `execution_authorized=false` et ne constituent pas un reçu.

**Contrôles réellement exécutés hors base :** 60 tests Python (dont 27 OP-07), 72 tests unitaires PHP/250 assertions (dont quatre OP-07/huit assertions), syntaxe PHP/Python et Pint. Après normalisation de style par Pint, les quatre tests OP-07 sont repassés et Pint est réussi. Les premiers essais ont détecté une incohérence des chemins de fichiers temporaires macOS `/var`/`private/var` dans les doubles de tests, corrigée en résolvant les chemins des fixtures ; les garde-fous de production ont été conservés.

DDL réel figé des cinq tables accepté par le parseur hors base ; mutants de FK qualifiés vers la source refusés. INSERT synthétique de 4 730 255 octets accepté, temps observé 1,747 s : test de parseur uniquement, sans transport SQL. Cli sans reçu/arguments refusés avant SQL ; commande par défaut prépare seulement les hashes. Simulation de succès et erreurs avant création/export/GRANT/restauration/comparaison, sans base réelle, sans suppression ni reprise. Les compteurs/valeurs de source réels restent ceux d'OP-06, non reconsultés.

Les artefacts, preuves OP-06 et manifeste de capsule listés dans le reçu futur seront aussi copiés à l'identique dans `reference-files/` du nouveau paquet, fichiers 0400/dossiers 0700, sans inclure `.env`, accès privés existants, dépendances vendor ou médias. Le manifeste associera SHA, versions constatées des clients et PHP et références de code. Cette copie permet de relire le contexte de la recette ; elle ne constitue pas une installation autonome ni une sauvegarde du serveur complet.

Une limite Python 3.9 du nombre de contextes de mock imbriqués a également été détectée pendant la clôture ; les doubles ont été réunis dans ExitStack. Le rapport intermédiaire en échec est conservé ; le dernier rapport v2 passe les 60 tests. Les validations de contenu des parents failed/prepared réutilisent aussi les fixtures pures, sans permettre leur lecture commerciale.

Preuves : [CLI/dialecte](../output/qa-import/op07-controles-cli-dialecte.json), [options client sans serveur](../output/qa-import/op07-options-client-controlees.json), [runtime PHP sans connexion](../output/qa-import/op07-php-runtime-controle.json), [tests Python de clôture](../output/qa-import/op07-tests-python-cloture-v2.json), [tests unitaires PHP](../output/qa-import/op07-tests-unit-cloture.xml), [tests PHP après style](../output/qa-import/op07-tests-php-finaux.xml), [clôture des outils](../output/qa-import/op07-outils-controles-finaux.json). Les critères OP07-01/04/05/06/07/08/09/10 demeurent à éprouver sur XAMPP ; OP07-02/03 sont testés en simulation mais attendent aussi le dump réel. Aucune restauration déclarée réussie par ces tests.

## 10. Exécution autorisée, arrêt contrôlé et correction — 08/10/2026

**Décision validée :** accord groupé « J’autorise OP-07 dans ce périmètre » consigné/relu DECISION-035 avant connexion, avec sauvegarde et préservation des métadonnées du registre. Reçu privé lié au plan v0.2, au programme et aux empreintes des artefacts/binaires, contrôlé avant chaque phase. [Enregistrement d'accord](../output/qa-import/op07-execution-enregistrement-accord.json). Aucun périmètre supplémentaire accordé.

**Faits vérifiés :** précontrôle source XAMPP conforme, cible et compte absents, cinq tables et droits historiques conformes. Snapshot cohérent complet des lignes et payloads source. Export natif terminé sans erreur de client, conservé sous `catalogue.sql.partial` dans le dossier privé `storage/app/private/database-backups/op07/20261008T134130Z-ff114f30-96d4-4361-81bf-b0e173132f40/` ; fichier 35 850 176 octets, SHA-256 `cb67ffd9c66cfd0b12bbbe0c7fe0acb0a211cf197557c2030a46aed841b1febd`. Arrêt immédiat sur `DUMP_SESSION_STATEMENT` avant renommage/manifeste, vérification complète des médias, création de cible/compte, GRANT ou restauration.

**Cause observée :** le binaire XAMPP 10.4.28 émet `/*!40101 SET character_set_client = utf8 */` avant chacun des cinq CREATE TABLE, entre sauvegarde et restauration du charset client. Les données suivent le rétablissement de la valeur précédente `utf8mb4`. La liste initiale du vérificateur anticipait `utf8mb4` pour cette instruction de DDL. Aucune donnée SQL ni aucun secret affiché lors du diagnostic.

**Contrôle indépendant après arrêt :** audit SQL en lecture seule et nouveau snapshot source identiques au précédent ; source, DDL, journal, six comptes et 36 droits de table + trois droits de colonne inchangés, `homya_local` sans objet. Cible `homya_restore_op07` et compte `homya_op07_restore` toujours absents. Témoins `.env`, `my.cnf`, configuration, code métier, routes/vues/langues/styles et migrations identiques. [Preuve de l'arrêt](../output/qa-import/op07-execution-arret-controle.json). Le champ technique `rights_reduction_verified=false` du rapport d'échec indique qu'aucun compte n'était créé et qu'aucun retrait n'était requis ; ce n'est pas un résidu de GRANT.

**Correction réalisée sans SQL :** [op07_dump.py](../scripts/op07_dump.py) accepte cette seule instruction native, limitée à la séquence sauvegarde du client / CREATE TABLE exact / rétablissement immédiat du client. INSERT avant rétablissement, version de commentaire détournée, SET intercalé et séquence inachevée refusés. [Test supplémentaire](../scripts/test_op07_dump.py) et 61 tests Python réussis, dont 28 OP-07. Les tests PHP historiques restent 72/250 assertions ; aucun PHP modifié ni nouveau test PHP nécessaire pour ce correctif Python.

Le dump réel conservé passe désormais le parseur **hors base** : cinq DDL exacts, 27 715 instructions, 5/67/3 711/23 890/4 lignes par table. Instruction maximale 8 852 675 octets, sous 16 Mio ; durée locale du contrôle 13,503 s, aucune durée de restauration déduite. [Contrôle du dump corrigé](../output/qa-import/op07-correctif-dump-natif-controle.json). Aucun renommage de l'ancien fichier, nouveau dump, replay ou restauration exécuté après l'arrêt.

| Critères | Résultat de cette tentative |
|---|---|
| OP07-01 | Précontrôle réel réussi ; collision absente, identité/état source vérifiés. |
| OP07-02 | Export et SHA/taille contrôlés ; fichier partial conservé, paquet/manifeste final non produit. Critère complet non acquis. |
| OP07-03 | Dump réel accepté hors base après correctif ; tests de refus réussis. |
| OP07-04/05/06/07 | Source relue et identique ; comparaison avec cible et restauration non exécutées. |
| OP07-08 | Médias complets non re-hachés pendant cette tentative ; preuve complète OP-06 conservée. |
| OP07-09 | Source/droits historiques inchangés ; compte cible absent, aucune réduction à effectuer. |
| OP07-10 | Site sur fichiers et témoins inchangés ; tentative incomplète, aucune activation. |

**Reprise proposée — décision nécessaire :** nouvelle tentative du même OP-07 avec précontrôle renouvelé et nouveau dossier privé, nouvel export des cinq seules tables, validation corrigée et contrôle complet des médias, puis création/restauration de la seule cible/compte prévus, 17 droits ciblés et réduction à cinq SELECT. Conserver intégralement la tentative interrompue et ses reçus/empreintes figés ; aucune suppression, écriture dans les bases existantes, migration, réglage XAMPP ou activation du site. Collisions toujours refusées, aucun objet réutilisé, arrêt au premier écart.

Le [programme de reprise et ses empreintes](../output/qa-import/op07-reprise-programme-v03.json) est une proposition hors base, sans autorisation d'exécution. L'ancien reçu DECISION-035 est conservé et **invalide pour le correctif** ; nouveau reçu lié au code/plan et nouvel accord requis avant une tentative. Cette limite découle du §4 du plan validé : « Une tentative ultérieure exige inspection et accord adapté. » La reprise garde la limitation au même disque et n'apportera aucune preuve de récupération après sa perte.

## 11. Reprise autorisée et clôture vérifiée — 08/10/2026

**Décision validée :** « J’autorise la reprise OP-07 », accord groupé consigné/relu DECISION-036 avant connexion, avec sauvegarde du registre et préservation de ses métadonnées. [Enregistrement de reprise](../output/qa-import/op07-reprise-enregistrement-accord.json), [reçu v0.3](../output/qa-import/op07-reprise-autorisation-v03.json). Ancien accord figé et reçu DECISION-035 conservés ; l'enregistrement standard a été réémis pour cette seule nouvelle tentative. Le code testé et le plan v0.3 sont restés figés pendant toute l'exécution.

**Actions réellement exécutées :** précontrôle XAMPP renouvelé, collisions absentes ; nouveau dossier privé et export logique natif des cinq seules tables ; vérification fermée du SQL, données source avant/après export identiques ; capsule complète vérifiée ; création de `homya_restore_op07` et de `homya_op07_restore@localhost`, 17 seuls droits ciblés ; restauration sous ce compte, comparaison intégrale ; retrait des 12 CREATE/INSERT/REFERENCES, cinq SELECT restants. Aucun second correctif ni nouvelle dépendance nécessaire pendant cette tentative.

Paquet conservé dans `storage/app/private/database-backups/op07/20261008T135251Z-936e0ffa-9ca0-4b24-88a2-9614b4fb7bad/` : `catalogue.sql` 0400, dossier 0700, accès privés 0600 exclus de Git et des rapports. SQL **35 850 176 octets**, SHA-256 `cb67ffd9c66cfd0b12bbbe0c7fe0acb0a211cf197557c2030a46aed841b1febd`, identique au premier export conservé. Manifeste, snapshots, droits finaux, témoins et statut conservés ; 30 fichiers de contexte recopiés à leurs empreintes en 0400, sans `.env`, secrets historiques, vendor ou médias.

**Preuves de données :** source avant export, après export, cible restaurée et source finale ont exactement les mêmes schémas de colonnes/PK, hashes des octets de toutes les lignes et payloads. Cinq tables InnoDB/utf8mb4_bin : 5 lots, 67 catégories, 3 711 produits, 23 890 traductions, quatre lignes de journal. DDL complet/PK/UNIQUE/index/collations/types et AUTO_INCREMENT identiques au témoin OP-06 ; 27 CHECK et quatre FK RESTRICT/RESTRICT, références vers la seule cible, aucune dépendance FK vers source. Trois lots verified, failed et prepared sans enfants/proof/date de vérification ; leurs refus applicatifs et contenus conservés.

Le lot réel reste distinct des fixtures : **3 709 produits / 65 catégories / 23 878 traductions**, 41 977 caractéristiques, 92 variantes, 18 454 associations galerie, 35 overlays, 627 signalements, six marques vides ; digest `49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1` inchangé. Aucune correction de prix/stock/anomalies métier ou réécriture du contenu dans cette opération.

**Médias :** les **22 239 fichiers / 3 477 829 369 octets** de la capsule privée requise ont été entièrement re-hachés avant création puis à la clôture, tailles/propriété/permissions/liens/empreintes conformes. Ils ne sont pas contenus dans le SQL et n'ont pas été recopiés pendant OP-07 ; le manifeste indique explicitement leur racine requise. La restauration SQL et la disponibilité de cette capsule sur le même disque sont les preuves acquises.

**Contrôle final distinct en lecture seule :** compteurs et DDL source/cible relus, contraintes et cible des FK vérifiées, compte dédié unique et réduit aux cinq SELECT non délégables ; aucun droit global/schéma/colonne/routine/rôle/proxy supplémentaire. Audit source : six comptes historiques et leurs 36 droits de table + trois droits de colonne inchangés ; `homya_local` sans objet ; paquets 16 Mio, FK/CHECK actifs, événements et logs général/lent/binaires OFF. Les 62 témoins `.env`/`my.cnf`/code métier/config/routes/vues/langues/styles/migrations et tous les fichiers de la première tentative sont identiques. [Contrôle indépendant](../output/qa-import/op07-reprise-controle-independant.json).

**Mesures observées, sans engagement :** exécution supervisée **102,525 s** ; instruction SQL maximale **8 852 675 octets**, sous 16 Mio ; pic PHP alloué **291 061 760 octets** sous 512 Mio pour source et copie. Plafond conservateur d'export 302 102 054 octets et minimum de libre exigé 1 174 741 618 octets ; libre observé après opération 58 381 737 984 octets. Les tailles de tables/index du contrôle indépendant sont des métadonnées InnoDB observées, pas une mesure garantie d'allocation physique. Pas de RPO/RTO, performances commerciales ou capacité future déduits.

| Critères OP-07 | Résultat dans le périmètre autorisé | Preuve |
|---|---|---|
| OP07-01 | Réussi : identité XAMPP, collisions préalables absentes, état source conforme. | Précontrôle de l'enveloppe ; audit final indépendant |
| OP07-02/03 | Réussis : export fini, liste fermée acceptée, cinq tables, SHA/taille/paquets conformes ; mutants refusés hors base. | Manifeste du paquet ; tests corrigés 61 Python |
| OP07-04 | Réussi : DDL complet identique, cinq tables et journal exact. | DDL et contraintes relus indépendamment |
| OP07-05/06 | Réussis : tous les hashes de lignes et payloads identiques ; états/compteurs conservés. | Quatre snapshots rapprochés |
| OP07-07 | Réussi : sessions FK/CHECK actives, vérifications explicites de domaines/orphelins/inter-lots ; FK limitées à la cible. | Snapshot PHP et contrôle référentiel final |
| OP07-08 | Réussi : capsule requise complète et empreintes conformes ; SQL déclaré sans médias. | Deux contrôles complets de capsule et manifeste |
| OP07-09 | Réussi : source/droits historiques intacts, compte cible limité à cinq SELECT. | Audit indépendant et droits finaux |
| OP07-10 | Réussi : site toujours sur fichiers, témoins intacts, taille/durée/limites consignées. | Témoins et statut final |

**État :** `RESTORE_VERIFIED_TEST_ONLY_NOT_ACTIVATED`, restauration isolée testée ; [synthèse de clôture](../output/qa-import/op07-reprise-synthese-verification.json). Les 61 tests Python et 72 tests unitaires PHP/250 assertions sont les contrôles hors base précédents ; cette phase ajoute l'exécution réelle et les audits, sans prétendre à un nouveau lancement des suites ni à un audit commercial complet.

**Limites et suite :** même serveur et même disque ; aucune récupération après perte du disque, sauvegarde distante/indépendante, installation autonome ou reprise sur autre version démontrée. Aucun service/réglage XAMPP, migration/import dans homya_local, nettoyage, bascule/publication ou action commerciale exécuté. La cible/compte et les deux paquets restent conservés, sans purge. Actualiser ce document et clore le registre invalide le reçu achevé pour un nouvel usage ; aucune nouvelle exécution n'est autorisée par cette clôture. Prochaine phase proposée : préparer OP-08, schéma local et droits ciblés, avec autorisation distincte avant ses créations/migrations ; OP-09 import et OP-10 bascule restent séparés.
