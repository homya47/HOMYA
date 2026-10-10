# HOMYA — OP-08 : reprise ciblée après arrêt 1146

Version 0.2 — 8 octobre 2026 — **REPRISE AUTORISÉE ET SCHÉMA LOCAL VÉRIFIÉ ; AUCUN IMPORT/BASCULE**.

Les §1–6 décrivent la préparation et ses limites au moment de la demande d'accord ; [version 0.1 validée et figée](../output/qa-import/op08-reprise-plan-valide-v01.md). Résultats natifs et clôture au §7.

## 1. État réellement vérifié et limites

Accord initial « J’autorise OP-08 dans ce périmètre » consigné/relu DECISION-038 ; [plan v0.2 figé](../output/qa-import/op08-plan-valide-v02.md). Tentative `op08-run-20261008T144507Z-1c6cacec-2c0b-43bd-a1f2-2fa352e27410`, arrêt au premier écart, [rapport](../output/qa-import/op08-run-20261008T144507Z-1c6cacec-2c0b-43bd-a1f2-2fa352e27410/failure.json).

**Faits natifs :** MariaDB XAMPP 10.4.28/socket/3307 conforme, local initialement vide ; journal seul créé sous compte schema et premier GRANT SELECT/INSERT réussi. Le GRANT REFERENCES seul sur `archive_lots`, alors absente, a échoué avec 1146. Aucune migration ni création d'archive, aucun import/bascule ; aucune nouvelle base/compte, suppression ou changement XAMPP. La préparation initiale a omis une condition du moteur pour les GRANT sur tables absentes.

[Contrôle après arrêt, en lecture seule](../output/qa-import/op08-arret-controle-independant.json) : une seule table `migrations`, zéro ligne ; trois comptes locaux existants, schema douze seuls droits de table (dix CREATE/ALTER + SELECT/INSERT journal), reader/import sans DML. REFERENCES non appliqués. Matrice historique test/copie exacte, DDL complets inchangés ; lignes test/copie re-hachées, égales entre elles et aux empreintes pré-opération. Capsule complète re-vérifiée : 22 239 fichiers/3 477 829 369 octets ; témoins runtime/.env/my.cnf identiques. Le compte de restauration conserve ses cinq SELECT seuls. Identité et session du compte schema ont été contrôlées lors du journal ; les connexions finales reader/import n'ont pas été exécutées.

Ces vérifications datent de l'arrêt et devront être renouvelées. La restauration OP-07 reste éprouvée dans sa cible isolée sur le même disque ; aucun retour arrière des futurs objets locaux n'est garanti. Aucun rejeu SQL effectué pour diagnostiquer.

## 2. Reprise proposée, sans élargissement de droits

Conserver le journal **uniquement s'il reste exactement celui de DECISION-038**, vide et conforme au DDL figé ; refuser toute autre table/ligne/permission ou dérive. Ne pas rejouer sa création ni son GRANT déjà réussi. Les migrations/contraintes/types et comptes ne changent pas.

Remplacer les deux REFERENCES seuls par ces deux instructions :

```sql
GRANT CREATE, REFERENCES ON `homya_local`.`archive_lots` TO 'homya_local_schema'@'localhost';
GRANT CREATE, REFERENCES ON `homya_local`.`archive_categories` TO 'homya_local_schema'@'localhost';
```

**CREATE existe déjà sur ces deux noms** ; sa réaffirmation ne crée pas de table et n'ajoute pas d'entrée de privilège. Chaque instruction n'ajoute que REFERENCES. Aucun SELECT/INSERT d'archive pour schema, droit global/schéma/DROP/DELETE/UPDATE général/délégation ajouté.

Puis quatre seules migrations catalogue existantes sous `homya_local_schema`, journal id1–4/batch1, contrôle physique et neuf GRANT reader/import inchangés du [plan initial §4](OP-08-SCHEMA-LOCAL-PREPARATION.md#4-droits-précis-proposés--trois-comptes-existants-seulement). **Onze GRANT restants**, quatorze nouvelles entrées de table et trois de colonne ; matrice finale inchangée : local 26 droits de table + trois UPDATE de colonne, six comptes historiques 52 + six ; compte restauration cinq SELECT séparés. Quatre archives vides, aucun import.

## 3. Sources externes vérifiées le 08/10/2026

Question précise : pourquoi un REFERENCES seul est-il refusé avant création, alors que les GRANT combinés d'OP-07 étaient acceptés ?

| Source primaire / URL | Portée / fait publié |
|---|---|
| [MariaDB Server 10.4.28 — sql_acl.cc, mysql_table_grant, lignes 6536–6546](https://github.com/MariaDB/server/blob/mariadb-10.4.28/sql/sql_acl.cc#L6536-L6546) | Code officiel de la version annoncée par XAMPP : sans CREATE dans les droits de l'instruction, contrôle de l'existence de la table et erreur si absente. Le contrôle porte sur l'instruction, même si le destinataire possède déjà CREATE. |
| [MariaDB Documentation — GRANT, Table Privileges](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant#table-privileges) | Documentation générale consultée le même jour : CREATE peut être accordé sur une table absente. Elle ne suffit pas à établir seule la sémantique FK de 10.4.28 ; l'ancienne enquête versionnée [OP-05 §2](OP-05-REPRISE-CIBLEE.md) traite cette limite. |

**Interprétation étayée :** l'erreur native 1146 et cette branche expliquent l'écart entre le REFERENCES seul proposé initialement et le GRANT incluant CREATE réellement éprouvé pendant OP-07. **Recommandation :** réaffirmer le CREATE existant dans ces deux seuls GRANT. Le succès de cette reprise locale reste **À vérifier après nouvel accord** ; aucune nouvelle écriture effectuée pour la recherche.

## 4. Outils, gardes et séquence future

Trois nouveaux fichiers ; outils initiaux et migrations inchangés : [enveloppe de reprise](../scripts/op08_resume.py), [probes PHP en lecture seule](../scripts/op08_resume.php), [tests dédiés](../scripts/test_op08_resume.py).

1. Nouvel accord de reprise consigné/relu dans le seul registre central ; nouveau reçu 0600 lié à ce plan, outils/binaires/DDL et premières preuves figées. Les reçus DECISION-037/038 sont refusés. Aucun accord/reçu de reprise émis ici.
2. Même verrou OP-08 ; nouvel inventaire stage1/journal, accès privés contrôlés, données test/copie/capsule/runtime identiques à l'arrêt ; réserve disque/temps/code/cache/dumps contrôlés. Connexion schema et SELECT du journal vide, sans écriture. Arrêt à tout écart.
3. Deux GRANT CREATE/REFERENCES corrigés, chacun encadré par un audit exact des droits. Puis quatre seules migrations dédiées sur journal vide, une seule tentative, sans journal automatique/dump/seeder/transaction DDL.
4. Contrôle DDL complet/27CHECK/quatreFK RESTRICT locales/journal quatre lignes/archives vides ; neuf habilitations reader/import restantes, chacune auditée. PDO/Laravel et lectures autorisées pour les trois rôles.
5. Contrôle final droits/données protégées/capsule/runtime ; preuves privées et clôture centrale. Site toujours sur fichiers. Bornes techniques initiales conservées (1 800s d'enveloppe, 300s/512M par PHP, réserve 256 Mio) ; pas d'engagement de durée.

L'auditeur, les requêtes natives et le verrou initiaux sont réutilisés en mémoire avec le validateur du nouveau reçu, dans le seul processus de reprise ; les fichiers historiques restent identiques. Le nouveau helper ne contient aucune création de journal ni écriture SQL.

**Échec :** conservation de la nouvelle tentative et du schéma/droits partiels, sans purge, rollback, DROP/REVOKE ou rejeu automatique. Toute autre reprise nécessite un état précis et un accord adapté. Première tentative, registre/accord/reçu initiaux et capsule conservés.

## 5. Contrôles hors base et acceptation

Tests : nouveau périmètre et garde d'accord, état exact d'arrêt, onze seules instructions restantes, CREATE déjà présent, aucune recréation/rejeu journal, arrêt à chaque frontière et conservation, contexte de garde restauré, vieux reçus refusés avant bootstrap/PDO. Régressions Python initiales incluses ; aucun SQL de ces tests. Syntaxe PHP et Pint du nouveau helper contrôlés. Les 50 tests PHP/326 assertions initiaux, dont le Migrator local sur doubles, restent les preuves existantes ; aucun fichier de migration/app modifié.

[Résultats de tests](../output/qa-import/op08-reprise-tests-python.json), [programme figé hors base](../output/qa-import/op08-reprise-programme-v01.json) et [synthèse de conservation](../output/qa-import/op08-reprise-preparation-verification.json). Ils ne prouvent pas les deux nouveaux GRANT sur XAMPP ; leur exécution effective est différée à la reprise autorisée.

Critères OP08-01 à OP08-08 conservés, avec départ stage1/journal au lieu d'une base vide ; OP08-02/03/04/05 finaux **non satisfaits à l'arrêt**. OP08-06 stop sans retry observé ; OP08-07 conservation après arrêt vérifiée ; OP08-08 preuves/état partiel consignés. Ne pas déclarer le schéma local terminé.

## 6. Autorisation distincte à présenter

**Décision nécessaire :** autoriser la reprise ciblée sur le seul état d'arrêt vérifié, les deux GRANT combinés, quatre migrations d'archive, neuf GRANT reader/import et contrôles finaux. Aucune création/recréation de journal, nouvelle base/compte, import/fixture, bascule, modification XAMPP/service/.env/application, restauration/nettoyage, dépense ou transaction commerciale.

Commande future, interdite tant que cette nouvelle autorisation et son reçu n'existent pas :

```text
/usr/bin/python3 scripts/op08_resume.py --execute --receipt=/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/op08-reprise-recu.json
```

Réponse proposée : **« J’autorise la reprise OP-08 dans ce périmètre »**. L'accord initial impose l'arrêt sans reprise automatique ([plan initial §5](OP-08-SCHEMA-LOCAL-PREPARATION.md#5-séquence-dexécution-à-outiller-puis-autoriser)) ; il n'est pas réutilisé pour ce nouvel essai.

## 7. Reprise autorisée et contrôle final — 08/10/2026

Accord exact « J’autorise la reprise OP-08 dans ce périmètre » consigné/relu **DECISION-039**, sauvegarde du registre et métadonnées vérifiées avant SQL ; [enregistrement](../output/qa-import/op08-reprise-enregistrement-accord.json). Nouveau reçu privé et empreintes plan/code/DDL/migrations/binaires/ancienne tentative vérifiés. Précontrôle renouvelé : seul journal vide et droits stage1 conformes, source/copie/capsule/runtime identiques à l'arrêt.

Reprise réussie dans `op08-run-20261008T150647Z-34897840-77c4-4068-a68c-66a7b6e436c0` : deux CREATE/REFERENCES corrigés appliqués, quatre seules migrations catalogue exécutées sous schema, neuf habilitations reader/import restantes vérifiées. Journal et premier GRANT conservés, aucune recréation/rejeu ; [résultat de l'enveloppe](../output/qa-import/op08-run-20261008T150647Z-34897840-77c4-4068-a68c-66a7b6e436c0/status.json).

**Contrôle indépendant en lecture seule**, [preuve complète](../output/qa-import/op08-reprise-controle-independant.json) :

| Critère | Résultat natif / limite |
|---|---|
| OP08-01, cible | MariaDB XAMPP 10.4.28/socket/3307, packet16M/strict/FK/CHECK/collations conformes ; départ journal vide connu uniquement. Aucun service ou paramètre XAMPP changé. |
| OP08-02, structure | Cinq seules tables InnoDB/utf8mb4_bin ; DDL physiques complets égaux à la référence OP-07, 34 colonnes, cinq PK, deux UNIQUE, 27 CHECK (22 explicites + cinq JSON) et quatre FK RESTRICT/RESTRICT dans `homya_local` seulement. Aucun changement de modèle. |
| OP08-03, données | Quatre archives à zéro ; journal quatre lignes id1–4, batch1, noms exacts, AUTO_INCREMENT=5. Seules ces lignes techniques ont été insérées ; aucune donnée commerciale. |
| OP08-04, droits | Local : schema 14 droits de table, reader quatre SELECT, import huit SELECT/INSERT + trois UPDATE de colonne bornés ; total 26 + trois. Six comptes historiques 52 + six ; restauration cinq SELECT séparés, inchangés. Aucun droit global/schéma/rôle/proxy/délégation ou accès croisé. |
| OP08-05, connexions | Identité/base/session et lectures minimales réussies pour schema, reader et import via les gardes Laravel/PDO, puis confirmées indépendamment avec le helper en lecture seule. Aucun secret imprimé ni déplacé. |
| OP08-06, périmètre | Onze GRANT restants seulement, quatre migrations dédiées, aucun journal recréé ni GRANT initial rejoué ; refus/échecs/concurrence testés auparavant sans base. Cette reprise n'a pas rencontré d'erreur d'exécution SQL. |
| OP08-07, conservation | DDL/droits/données test/copie re-hachées, égales entre elles et aux empreintes avant/après/arrêt ; première tentative intacte, 122 témoins historiques et 33 preuves OP-07 inchangés. Capsule 22 239 fichiers/3 477 829 369 octets entièrement vérifiée avant les écritures et à la fin par l'enveloppe. Le contrôle indépendant n'a pas répété ce troisième hachage complet des médias. Runtime/.env/my.cnf inchangés. |
| OP08-08, preuves | Accord/reçu/plan/programme et audits par étape privés, dossiers 0700 et preuves 0600 contrôlés ; clôture dans le seul registre central. [Synthèse de reprise](../output/qa-import/op08-reprise-synthese-verification.json). |

Le premier passage du contrôleur indépendant attendait par erreur 37 colonnes ; la référence figée OP-07 en comporte 34. Le contrôleur a été corrigé pour dériver ce nombre de la référence, puis les vérifications en lecture seule ont réussi. Aucun GRANT ou migration rejoué pour ce contrôle ; aucune correction du schéma nécessaire.

Les 88 tests Python et 50 tests PHP/326 assertions sont les preuves hors base antérieures, **non rejouées dans cette exécution** ; les résultats natifs ci-dessus vérifient la cible réelle. Aucun fichier applicatif, migration, outil historique ou configuration modifié pendant la reprise. Documents/preuves de clôture seuls mis à jour. Aucun test de vente/paiement ni readiness de production revendiqué.

**OP-08 TERMINÉE dans son périmètre :** schéma local et habilitations vérifiés, catalogue SQL encore vide, site conservé sur fichiers. Aucun import/bascule, nouvelle base/compte, nettoyage/restauration, service/configuration XAMPP ou action externe. Documents et registre de clôture invalident le reçu achevé ; aucune nouvelle exécution sous ce reçu. Prochaine étape : préparer OP-09 (import local contrôlé), puis obtenir son autorisation distincte ; OP-10 bascule reste séparée.
