# HOMYA — OP-08 : schéma local et habilitations ciblées

Version 0.3 — 8 octobre 2026 — **SCHÉMA LOCAL VÉRIFIÉ APRÈS REPRISE AUTORISÉE ; AUCUN IMPORT/BASCULE**.

Les §1–11 conservent la préparation et la première tentative arrêtée ; clôture réussie au §12, plan d'origine figé avant exécution.

Demande : préparer le schéma de `homya_local`, avec autorisation distincte avant toute création ou migration. La préparation documentaire v0.1 a inspecté les conventions et recompilé le DDL hors connexion. Le complément v0.2 développe et teste les outils sous DECISION-037, sans charger les accès privés réels ni se connecter à MariaDB. Aucun modèle métier, fichier de migration, compte ou service modifié.

## 1. Sources, faits et état de départ

Traçabilité : [plan technique §10](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md#10-opérations-précises-à-autoriser-séparément--aucune-exécutée), OP-08 / TECH-08 / IMP-07 ; modèle physique et rôles validés DECISION-019. [OP-05 et sa reprise](OP-05-REPRISE-CIBLEE.md), [recette OP-06](OP-06-RECETTE-XAMPP-PREPARATION.md) et [clôture OP-07 §11](OP-07-SAUVEGARDE-RESTAURATION-PREPARATION.md#11-reprise-autorisée-et-clôture-vérifiée--08102026). Le [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md) demeure seul registre ; DECISION-036 clôt OP-07 et n'autorise aucune exécution OP-08. Registre inchangé pendant la préparation v0.1 ; accord de développement hors base consigné/relu DECISION-037 dans le complément v0.2. Aucun accord d’exécution SQL consigné.

| Élément | Qualification / source / limite |
|---|---|
| Cible | **Décision validée** : base locale `homya_local`, MariaDB XAMPP, socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, port 3307 ; aucune connexion Homebrew/3306. |
| Base et droits actuels | **Fait historique vérifié le 08/10/2026 à 13:56:12 UTC**, [audit OP-07](../output/qa-import/op07-reprise-controle-independant.json) : base créée antérieurement, utf8mb4/utf8mb4_bin, sans table/vue/routine/trigger/événement ; trois comptes locaux existants, `homya_local_schema` a dix seuls CREATE/ALTER sur cinq noms ; lecteur/importeur sans DML. Pas de SQL frais dans cette phase : état à renouveler avant exécution. |
| Environnement | **Observation de fichiers/code** : Laravel 13.35, PHP CLI 8.5.11, six connexions dédiées et garde local/CLI/cible/session déjà implémentés. PHP XAMPP 8.2 exclu pour Laravel. |
| Schéma | **Fait éprouvé en test**, OP-05/06/07 : quatre tables d'archive + journal, InnoDB/utf8mb4_bin, 27 CHECK et quatre FK RESTRICT. Aucun nouveau choix de types ou index. |
| Prérequis de recette/restauration | **Fait vérifié**, [synthèse OP-07](../output/qa-import/op07-reprise-synthese-verification.json) : données intégralement identiques après restauration isolée ; compte de restauration limité à cinq SELECT ; capsule vérifiée. Même disque, pas de preuve de récupération après perte du disque. |
| Accès privés | **Métadonnées observées sans lecture du contenu** : trois fichiers `.env.homya_local_*` présents sous `storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/`, propriétaires courants, 0600 ; dossiers 0700. Connexions locales sélectionnant la base avec leurs futurs droits **À vérifier**. |
| Interface et catalogue | **Décision conservée** : lecture fichiers, FR/EN/DE et règles de projection existantes ; aucune activation SQL incluse. |

## 2. Périmètre proposé

Créer **cinq tables neuves dans la base HOMYA déjà autorisée/créée**, sans recréer la base : `migrations`, `archive_lots`, `archive_categories`, `archive_products`, `archive_translations`. Créer le journal sous `homya_local_schema`, accorder ses SELECT/INSERT et deux REFERENCES nécessaires, exécuter les quatre seules migrations revues, puis habiliter les lecteur/importeur locaux et vérifier en lecture seule.

**Exclusions :** nouvelle base ou nouveau compte, données catalogue/import réel ou fixtures persistantes, seeders, migrations générales users/sessions/cache/jobs, dump de schéma/restauration SQL, modification de test ou de la cible OP-07, .env/configuration/application, services XAMPP, suppression/rollback/fresh/refresh, activation du lecteur SQL, paiement/commande/externe/publication. Les quatre lignes techniques du journal sont les seules données nouvelles prévues.

Tout objet local rencontré, y compris journal vide, provoque l'arrêt et une inspection ; aucune réutilisation automatique. L'appartenance de la base existante à HOMYA est étayée par OP-02 et OP-07, à vérifier contre leurs preuves lors du précontrôle. Aucun `CREATE DATABASE`, `CREATE USER`, `IF NOT EXISTS`, `OR REPLACE`, `DROP` ou droit `homya_local.*` proposé.

## 3. DDL et migrations réutilisés, sans modification

[Définition unique](../app/Services/CatalogueArchiveSchema.php), [commande dédiée](../app/Console/Commands/PrepareCatalogueSchema.php), [garde](../app/Services/ArchiveDatabaseGuard.php) et les quatre fichiers existants de `database/migrations/catalogue/` :

1. `2026_10_08_000001_create_archive_lots.php` ;
2. `2026_10_08_000002_create_archive_categories.php` ;
3. `2026_10_08_000003_create_archive_products.php` ;
4. `2026_10_08_000004_create_archive_translations.php`.

Recompilation hors SQL identique aux témoins OP-04 : **6 492 octets**, DDL SHA-256 `1d85f2b72f241349254a9911cfa9da3bee76792d3fe9c3d9b77cb5d64600b568` ; empreinte fichiers `0f87b009dfc2658d74ee421d5dbfb0cfe77c17ebc6ed6732252a10de9193528f`. [DDL compilé](../output/qa-import/op04-complement-schema-final.sql), [empreintes](../output/qa-import/op04-complement-schema-final.json).

Journal [DDL préparé existant](../output/qa-import/op05-journal-propose.sql), SHA-256 `66d40df56dfaf0991746682aa69d4f3e510d6fb435ee7bb7f48557b99f0b5f1d` : `id` INT UNSIGNED AUTO_INCREMENT/PK ; `migration` VARCHAR(255) utf8mb4_bin ; `batch` INT ; InnoDB. Après les migrations : id1–4, batch1, noms exacts ci-dessus sans `.php`, AUTO_INCREMENT=5. Quatre archives strictement vides.

Les clés/colonnes/NULL/collations/index/UNIQUE/CHECK/FK seront comparés au DDL physique figé de test, pas seulement comptés. Les FK doivent désigner les tables de `homya_local`, sans dépendance vers test/restauration. Aucun index de prix/texte spéculatif ou règle commerciale nouvelle.

## 4. Droits précis proposés — trois comptes existants seulement

Hôte `localhost`, socket imposé. Les dix CREATE/ALTER de `homya_local_schema` sont conservés. Ajouts : **16 entrées de privilèges de table + trois UPDATE de colonne**, par **12 GRANT bornés** ; aucun droit global/schéma, DROP/DELETE/UPDATE général ou délégation.

Après création du journal, avant les migrations :

```sql
GRANT SELECT, INSERT ON `homya_local`.`migrations` TO 'homya_local_schema'@'localhost';
GRANT REFERENCES ON `homya_local`.`archive_lots` TO 'homya_local_schema'@'localhost';
GRANT REFERENCES ON `homya_local`.`archive_categories` TO 'homya_local_schema'@'localhost';
```

**Justification des REFERENCES — recommandation appuyée par preuve réelle :** OP-05 a nécessité ces deux droits pour les FK sur XAMPP 10.4.28 ; OP-07 les a également accordés sur les noms de parents avant leur création. Les accorder avant migration évite de reproduire l'arrêt partiel OP-05. Cette proposition locale demande son autorisation, aucun droit n'est appliqué ici.

Après vérification des cinq tables :

```sql
GRANT SELECT ON `homya_local`.`archive_lots` TO 'homya_local_reader'@'localhost';
GRANT SELECT ON `homya_local`.`archive_categories` TO 'homya_local_reader'@'localhost';
GRANT SELECT ON `homya_local`.`archive_products` TO 'homya_local_reader'@'localhost';
GRANT SELECT ON `homya_local`.`archive_translations` TO 'homya_local_reader'@'localhost';
GRANT SELECT, INSERT ON `homya_local`.`archive_lots` TO 'homya_local_import'@'localhost';
GRANT SELECT, INSERT ON `homya_local`.`archive_categories` TO 'homya_local_import'@'localhost';
GRANT SELECT, INSERT ON `homya_local`.`archive_products` TO 'homya_local_import'@'localhost';
GRANT SELECT, INSERT ON `homya_local`.`archive_translations` TO 'homya_local_import'@'localhost';
GRANT UPDATE (load_state, verification_document, verified_at) ON `homya_local`.`archive_lots` TO 'homya_local_import'@'localhost';
```

État final local : schema **14 entrées de table** (dix CREATE/ALTER, deux REFERENCES, deux SELECT/INSERT journal) ; reader **quatre SELECT** ; import **huit SELECT/INSERT + trois UPDATE de colonne**. Total local **26 de table + trois de colonne** ; avec test conservé, six comptes historiques **52 de table + six de colonne**. Le compte OP-07 demeure séparé, cinq SELECT inchangés. Aucun droit sur journal pour reader/import, aucune entrée croisée vers autre schéma. Habiliter l'importeur ne l'autorise pas à charger des données : OP-09 reste distinct.

## 5. Séquence d'exécution à outiller puis autoriser

1. **Enregistrer/re-lire l'accord propre à OP-08** dans le registre central, sauvegarder/préserver ses métadonnées, émettre un reçu privé lié au plan/code/DDL/binaires ; aucune émission dans cette phase.
2. **Précontrôle réel sous accord** : version/socket/datadir/3307/bind/packet16M/FK/CHECK/strict/events/logs, charset/collations/InnoDB ; base locale vide et trois comptes/droits existants exacts ; source test, copie OP-07, compte de restauration et témoins applicatifs conservés. Aucun dump de `database/schema/`, quatre fichiers de migration seuls, cache de configuration absent. Contrôle d'accès schema par PDO/Laravel sans écriture ni affichage de secret. Arrêt au premier écart.
3. **Journal seul** : helper local/CLI dédié, garde `homya_local_schema`, reçu/empreintes/audit frais, verrou d'opération ; CREATE du journal exact, puis contrôle des colonnes/PK/index/table vide. Aucun journal Laravel automatique.
4. **Trois GRANT préparatoires** du §4, chacun suivi d'un audit exact de la matrice, avant les FK. Aucune élévation automatique à l'erreur.
5. **Quatre migrations seules** sous compte schema : commande existante OP-08, journal unique vide, aucune transaction DDL ; invocation du Migrator direct sur le répertoire fixé, pas `MigrateCommand`/dump/seeder ; une seule tentative, reçu/empreintes juste avant. Contrôle physique indépendant après exécution.
6. **Neuf GRANT lecteur/importeur** après existence et conformité des cinq tables, audits par étape. Aucun INSERT métier ni UPDATE de vérification exécuté.
7. **Trois connexions dédiées** : garde/identité/session et sélection de `homya_local`, SELECT sur tables autorisées et vides pour reader/import, journal pour schema. Refus effectifs DML déjà éprouvés en test ; pas de nouvelles écritures de test dans le local. Matrices administratives complètes (tables/colonnes/global/schéma/routines/rôles/proxies/délégation/autres hôtes) confirmées.
8. **Clôture** : quatre archives à zéro, journal quatre lignes exactes, DDL/collations/27CHECK/quatreFK conformes ; droits test/restauration, sources et témoins inchangés ; preuve privée et décision centrale, site toujours sur fichiers. Aucun import/bascule inclus.

**Échec/retour :** le DDL et les GRANT ne sont pas un ensemble transactionnel annulable. Conserver schéma/journal/droits partiels et rapport expurgé ; pas de DROP/REVOKE général/rollback/purge/rejeu automatique. Examiner les seuls objets atteints, puis obtenir accord de reprise adapté. Le retour fichiers n'est pas une restauration SQL. La sauvegarde OP-07 couvre test + capsule requise, pas des objets locaux futurs ; ne jamais la charger sur `homya_local` sous cet accord.

## 6. Outils disponibles et complément livré sans base

**Disponible et lu :** commande `homya:catalogue-schema` connaît OP-08 et `homya_local_schema`, impose confirmation/empreintes/journal vide et emploie le Migrator direct ; `homya:db-check --connection=… --access=…` diagnostique explicitement un compte, sans preuve de tables/droits ; garde/configuration et migrations partagés inchangés.

**Livré/testé sans base sous DECISION-037 :** [enveloppe administrative](../scripts/op08_xampp_admin.py), [helper journal/PDO](../scripts/op08_journal.php), [tests Python](../scripts/test_op08_xampp_admin.py) et [tests du Migrator local](../tests/Feature/Op08SchemaPreparationTest.php). Les authentifications réelles, états physiques et droits effectifs restent à vérifier sous accord SQL. Détails et limites au §10.

Ne pas réutiliser directement `op05_journal.php`, `op05_xampp_admin.py` ou `op05_resume.php` : ils fixent `homya_test`, ses états vides/partiels et les anciens droits. Ne pas remplacer globalement les noms dans ces fichiers historiques. Aucun reçu OP-05/06/07 ne vaut accord OP-08 ; les preuves figées restent inchangées.

**Commandes réellement exécutées hors base** pour recompiler :

```text
/opt/homebrew/bin/php artisan homya:catalogue-schema
/opt/homebrew/bin/php artisan homya:catalogue-schema --ddl
```

**Commande de migration future, INTERDITE dans cette préparation et insuffisante sans son enveloppe/journal/droits :**

```text
/opt/homebrew/bin/php artisan homya:catalogue-schema --execute --operation=OP-08 --connection=homya_local_schema --access=<fichier-prive-verifie> --expected-ddl=1d85f2b72f241349254a9911cfa9da3bee76792d3fe9c3d9b77cb5d64600b568 --expected-files=0f87b009dfc2658d74ee421d5dbfb0cfe77c17ebc6ed6732252a10de9193528f
```

Le placeholder n'est ni un secret ni un chemin exécutable. Commande d’enveloppe et témoins vérifiés hors base : voir §10. Le reçu d’exécution reste à émettre après autorisation SQL distincte. Aucune commande `migrate`, `migrate --pretend`, `migrate:fresh` ou `db:seed` utilisée pour diagnostic.

## 7. Critères d'acceptation OP-08

Tous liés à TECH-08 / IMP-07, priorité de contrôle **P1 proposée** : ils conditionnent l'import local suivant. Résultats SQL locaux **À vérifier**, aucune migration locale déclarée réussie.

| ID | Critère observable | Dépendance |
|---|---|---|
| OP08-01 | Cible/version/socket/datadir/3307/packet/strict/FK/CHECK conformes ; homya_local créée pour HOMYA et sans objet avant opération ; aucun nom/table rencontré réutilisé. | Accord d'exécution, audit frais |
| OP08-02 | Cinq seuls objets InnoDB/utf8mb4_bin, colonnes/types/NULL/PK/UNIQUE/index/27CHECK/quatreFK RESTRICT conformes aux témoins ; aucune FK vers test/restauration. | Journal et quatre migrations ciblées |
| OP08-03 | Journal id1–4/batch1/noms exacts/AUTO_INCREMENT5 ; quatre archives vides ; aucune donnée/fixture commerciale importée. | OP08-02, SELECT explicites |
| OP08-04 | Matrice locale exacte 26 droits de table + trois UPDATE de colonne, sans global/schéma/rôle/proxy/délégation/autre hôte/accès croisé ; aucun nouveau compte/base. | Douze GRANT bornés, audit complet |
| OP08-05 | PDO/Laravel sélectionnent la bonne base/identité/session pour chacun des trois comptes ; lectures minimales sur objets permis, secrets privés jamais affichés ni placés dans .env public/runtime. | Accès existants privés et droits |
| OP08-06 | Refus hors périmètre et erreurs à chaque frontière testés sans base ; en SQL réel échec arrêté sans retry/purge/rollback non autorisé, inventaire partiel disponible. | Outils et tests à livrer |
| OP08-07 | Source test, copie/droits OP-07, capsule/manifeste et témoins application/.env/my.cnf conservés ; site fichiers/FR/EN/DE inchangé. | Témoins avant/après et audits ciblés |
| OP08-08 | Plan/reçu/empreintes/versions/commandes et résultats privés archivés, accord/clôture dans le seul registre ; aucune affirmation d'import/bascule/production. | Autorisation propre et contrôle final |

## 8. Contrôles de cette préparation et limites

Inspection des fichiers/migrations/services/tests, preuve et empreintes OP-07, registre central, métadonnées des accès privés ; **aucun contenu d'accès chargé ni SQL envoyé**. DDL/empreintes recompilés sans PDO et conformes. Programme documentaire [objets/GRANT/commandes proposés](../output/qa-import/op08-preparation-programme-propose.json), [inspection et témoins](../output/qa-import/op08-preparation-inspection.json).

Tests existants du schéma/garde/migrateur sur doubles sans PDO, rapports [premier essai sandbox](../output/qa-import/op08-preparation-tests-sans-base.xml) et [second essai hors sandbox pour sockets synthétiques](../output/qa-import/op08-preparation-tests-sans-base-v2.xml). Le premier a été empêché par la création des sockets Unix temporaires (43 erreurs de setup), sans connexion MariaDB. Résultat final et conservation des témoins consignés dans la [synthèse de préparation](../output/qa-import/op08-preparation-synthese-verification.json). Ces premiers tests v0.1 ne prouvent pas une migration locale réelle ; les nouveaux outils livrés en v0.2 sont évalués séparément au §10.

Aucune recherche externe nouvelle nécessaire : question examinée = les outils/migrations existants ciblent-ils déjà le local et quels droits manquent sur le moteur installé ? Réponse fondée sur code installé et preuves réelles versionnées OP-05/07. Les sources officielles et leurs limites restent dans les plans techniques/OP-05/OP-07 ; aucune capacité actuelle d'hébergeur ou autre version présumée. Les nouvelles sessions, état SQL et capacité réelle seront mesurés au futur précontrôle, pas déduits des contrôles historiques.

Database Engineering : structure/fidélité ; Security : cible/comptes/droits/secrets ; Quality : limites des doubles et critères de recette. Modules pertinents consultés selon le routeur, aucun audit complet revendiqué ; Codex natif, aucun appel au modèle Claude.

## 9. Arbitrages et prochaine étape

**Décisions déjà validées :** cible locale, réutilisation du modèle/migrations dédiées, comptes séparés, site sur fichiers jusqu'à OP-10. **Recommandation à inclure dans l'accord futur :** deux REFERENCES locaux bornés, reproduisant les preuves du moteur installé. **Inconnues non inventées :** état SQL frais/connexions effectives/capacité et durée ; **dépendance avant exécution :** accord SQL distinct, reçu futur et précontrôle réel ; outils locaux et tests du chemin OP-08 désormais livrés. Aucune préférence visuelle ou règle commerciale à trancher ici.

Prochaine étape : obtenir **une autorisation groupée d’exécution OP-08** couvrant précontrôles/connexions, journal, deux REFERENCES, quatre migrations, habilitations locales et lectures finales. Cette demande de préparation et DECISION-037 n’autorisent aucune création SQL, GRANT, migration, import ou bascule.

## 10. Complément d'outils vérifié sans base — 08/10/2026

**Décision validée DECISION-037 :** préparation des outils et tests uniquement, [accord consigné/relu](../output/qa-import/op08-outils-enregistrement-accord.json). La [version 0.1 figée](../output/qa-import/op08-plan-preparation-v01.md) conserve le périmètre documentaire d'origine. Aucun reçu d'exécution émis ; ni base consultée ni accès privé réel chargé.

L'enveloppe Python est hors connexion par défaut. Pour chaque appel SQL/PHP futur, elle exige un reçu privé 0600, propriétaire courant, sans lien ; accord d'exécution indépendant relu dans le registre, plan/code/migrations/témoins/binaires inchangés. DECISION-037 et les reçus d'autres opérations sont refusés. Verrou exclusif OP-08, dossier de résultat 0700, preuves 0600 ; arrêt sans nettoyage ni rejeu en cas d'erreur. Durée maximale d'enveloppe 1 800 secondes, sous-processus PHP au plus 300 secondes/512 Mio, réserve disque minimale 256 Mio : bornes techniques, aucun engagement de performance.

La matrice administrative est comparée exactement avant et après chaque GRANT ; CREATE/ALTER existants et droits test/copie conservés. DDL physique local comparé au témoin OP-07, avec journal initial vide puis quatre lignes exactes ; FK dans `homya_local` seulement. Helper PHP : arguments stricts et reçu vérifié **avant bootstrap/lecture des accès/PDO**, garde de compte/session, cible locale vide pour le journal, contrôles finaux en lecture seule. La commande partagée exécute uniquement les quatre migrations revues sous `homya_local_schema`.

Conservation future : identité/comptes/droits/objets test et restauration, témoins runtime et capsule complète avant/après. Pour les lignes test/copie : SHA-256 serveur des octets encadrés par longueur/NULL, ordre PK, puis empreinte de chaque table ; cardinalités historiques attendues, égalité source/copie et conservation pendant OP-08. **À vérifier sur MariaDB réel** : disponibilité/coût de cette requête et comportement des nouvelles connexions. Les doubles valident sa construction et le refus d'une divergence ; ils ne prouvent pas une nouvelle relecture SQL des données historiques. Les preuves réelles OP-07 restent leur source. Toute erreur de ce précontrôle arrête avant le premier CREATE.

Contrôles réellement exécutés : **79 tests Python réussis**, dont 18 nouveaux OP-08 (reçu, cible/droits, séquence, frontières d'échec, conservation, verrou, délais, absence de lecture des accès), [résultats](../output/qa-import/op08-outils-tests-python.json) et [détail](../output/qa-import/op08-outils-tests-python.txt). **50 tests PHP réussis, 326 assertions**, dont quatre tests du Migrator local sans PDO et les régressions garde/schéma : [rapport](../output/qa-import/op08-outils-tests-sans-base.xml). Les sockets de ces fixtures sont synthétiques ; aucun service MariaDB n'est contacté. Syntaxe PHP et style Pint des deux nouveaux fichiers PHP valides. Tests CLI de refus sans reçu et modes par défaut hors connexion contrôlés.

Les migrations, code applicatif, configuration, `.env`, `my.cnf`, outils historiques et preuves OP-07 sont préservés : [synthèse de conservation](../output/qa-import/op08-outils-synthese-verification.json). Programme et empreintes hors base : [programme préparé](../output/qa-import/op08-outils-programme.json). Aucun contrôle SQL réel OP-08, aucune création/habilitation/migration/import ou bascule effectués ; critères OP08-01 à OP08-05 restent à vérifier sur la cible sous accord.

**Seule commande d'exécution future à utiliser après nouvel accord enregistré et reçu émis :**

```text
/usr/bin/python3 scripts/op08_xampp_admin.py --execute --receipt=/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/op08-execution-recu.json
```

Ce reçu n'existe pas dans cette préparation. Aucun lancement direct du helper ou de l'artisan sous cet accord : l'enveloppe coordonne les audits, droits et journal. L'accord demandé couvre seulement le §5 et les douze GRANT du §4, dans la base locale existante. Il exclut OP-09 (import), OP-10 (bascule), nouvelles bases/comptes, réglage/service XAMPP et suppression/restauration. Toute collision ou état partiel déclenche un arrêt, sans reprise automatique.

## 11. Exécution autorisée puis arrêt contrôlé — 08/10/2026

Accord « J’autorise OP-08 dans ce périmètre » consigné/relu DECISION-038 avant SQL. Le plan v0.2 et le programme d'origine sont conservés [en copie figée](../output/qa-import/op08-plan-valide-v02.md). Précontrôle réel et conservation test/copie/capsule réussis ; journal créé sous schema et premier GRANT SELECT/INSERT vérifié. Le REFERENCES seul suivant sur une table absente a été refusé, erreur 1146. Arrêt sans retry/nettoyage, aucune migration démarrée.

[Contrôle indépendant après arrêt](../output/qa-import/op08-arret-controle-independant.json) : seul journal vide, douze droits de table pour schema, aucun droit lecteur/importeur local ni REFERENCES nouveau, aucune archive locale. Données/DDL/droits test/restauration, capsule complète et runtime/.env/my.cnf conservés. Aucun import/bascule ni changement XAMPP. **OP-08 INCOMPLÈTE**, critères finaux schéma/droits/lectures non satisfaits.

La préparation v0.2 avait extrapolé les GRANT combinés d'OP-07 au REFERENCES seul sans vérifier l'existence requise. Le code officiel 10.4.28 exige CREATE dans l'instruction pour une table absente. [Reprise ciblée préparée](OP-08-REPRISE-CIBLEE.md) : réaffirmer ce CREATE déjà détenu avec REFERENCES sur les deux parents, conserver le journal et son GRANT, puis poursuivre les onze instructions restantes et quatre migrations. Outils initiaux/migrations et première tentative inchangés ; nouveau reçu/accord de reprise requis. Aucun SQL de correction ou nouvelle tentative exécuté. Les §1–10 décrivent la préparation antérieure, dont les affirmations « sans SQL » se limitent à cette préparation.

## 12. Schéma local vérifié après reprise — 08/10/2026

Accord de reprise consigné/relu DECISION-039 avant SQL ; [clôture détaillée](OP-08-REPRISE-CIBLEE.md#7-reprise-autorisée-et-contrôle-final--08102026). Journal vide et premier GRANT conservés ; deux CREATE/REFERENCES sur parents puis quatre migrations catalogue et neuf GRANT reader/import restants réussis, sans élargir la matrice finale. Aucun code applicatif/migration/configuration modifié.

[Contrôle indépendant](../output/qa-import/op08-reprise-controle-independant.json) : cinq seules tables, 34 colonnes, DDL/index/PK/types/collations complets conformes, 27 CHECK et quatre FK RESTRICT locales ; archives vides et journal id1–4/batch1/AUTO_INCREMENT5 exact. Droits locaux 26 de table + trois UPDATE de colonne bornés ; trois connexions Laravel/PDO et lectures autorisées validées. Données/droits/DDL test/restauration, première tentative, capsule et runtime/.env/my.cnf conservés. Les critères OP08-01 à OP08-08 sont satisfaits dans ce périmètre.

**OP-08 TERMINÉE**, [synthèse vérifiée](../output/qa-import/op08-reprise-synthese-verification.json). Aucune nouvelle base/compte, import commercial, bascule du site, restauration/nettoyage ou service/réglage XAMPP. Le catalogue public continue de lire les fichiers. OP-09 import local à préparer/autoriser séparément ; OP-10 bascule exclue de cet accord.
