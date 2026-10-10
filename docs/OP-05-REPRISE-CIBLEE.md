# HOMYA — reprise ciblée OP-05, proposition v0.1

**État actuel : REPRISE EXÉCUTÉE ET SCHÉMA PHYSIQUE VÉRIFIÉ le 08/10/2026, DECISION-030.** Le texte de proposition ci-dessous décrit l'état avant accord ; sa copie autorisée immuable est `output/qa-import/op05-reprise-plan-autorise.md`. Les opérations ne doivent pas être rejouées. Clôture ci-dessous ; aucun import ni activation.

08/10/2026 — **PRÉPARÉE, NON AUTORISÉE, AUCUNE REPRISE SQL EXÉCUTÉE**.

DECISION-029 autorise le plan OP-05 initial. Son arrêt au premier écart a été appliqué ; cette proposition exige un accord distinct pour deux droits supplémentaires et la reprise du schéma partiel. Le seul registre reste Specification ; aucun accord complémentaire n'est présumé.

## État réel conservé

[État figé](../output/qa-import/op05-etat-partiel-fige.json) et [précontrôle de reprise en lecture seule](../output/qa-import/op05-reprise-precontrole.json) :

- homya_test contient seulement migrations, archive_lots et archive_categories, InnoDB/utf8mb4_bin ; homya_local reste sans objet.
- archive_lots est complète selon le DDL observé ; archive_categories possède ses colonnes/PK mais aucune FK ni CHECK. Les tables produits/traductions sont absentes.
- Journal : une seule ligne id 1, migration 000001_create_archive_lots, batch 1 ; archive_lots/categories sans aucune ligne.
- Les 22 droits OP-05 sont présents, les six comptes/absence de privilèges hors périmètre sont confirmés. Aucun REFERENCES supplémentaire.
- Le migrateur a renvoyé un échec, puis les écritures ont cessé. Aucune suppression, rollback, reprise ou import. Le code OP-04 reste inchangé.

## Cause : observations, source et interprétation

**Observation :** l'interruption se situe entre la création de la catégorie et sa première FK. Le message original du migrateur est expurgé ; le code d'erreur SQL original n'a pas été conservé. Performance Schema est désactivé et aucun diagnostic FK HOMYA n'a été retrouvé dans InnoDB. Ne pas présenter une erreur SQL précise comme capturée.

**Source primaire versionnée :** [MariaDB Server 10.4.28, sql_parse.cc, check_fk_parent_table_access](https://github.com/MariaDB/server/blob/mariadb-10.4.28/sql/sql_parse.cc#L7267-L7365), consultée le **08/10/2026**, portée : code officiel de la version annoncée par XAMPP. Le contrôle exige au moins un droit parmi SELECT, INSERT, UPDATE, DELETE ou REFERENCES sur la table parente, au niveau table. Aucun des cinq n'existe pour le compte schema sur archive_lots. [Appel pendant ALTER ADD FOREIGN KEY](https://github.com/MariaDB/server/blob/mariadb-10.4.28/sql/sql_table.cc#L9854-L9865), même date/portée, confirme ce passage. Les copies lues et leurs empreintes sont référencées dans la synthèse de reprise.

**Interprétation étayée :** ce contrôle explique l'arrêt avant la FK de catégorie ; il corrige l'analyse insuffisante des droits du précontrôle initial. La documentation GRANT générale qui qualifie REFERENCES d'inutilisé ne suffit pas à qualifier ce chemin de la version 10.4.28. Le succès réel de la correction reste à vérifier après autorisation.

**Recommandation :** REFERENCES sur les deux seules tables parentes, sans droit de lecture/écriture des archives pour schema. Cette matrice resterait à réexaminer pour les opérations homya_local ultérieures ; aucun droit local n'est modifié ici.

## Opérations précises proposées

1. Recontrôler administrativement les trois seuls objets, leurs SHOW CREATE TABLE identiques à l'état figé, les archives vides, la ligne de journal exacte et les 22 seuls droits. Vérifier cible/socket/session, comptes, empreintes du plan/outils et DDL OP-04.
2. Accorder successivement, avec contrôle avant/après chaque instruction :

```sql
GRANT REFERENCES ON homya_test.archive_lots TO 'homya_test_schema'@'localhost';
GRANT REFERENCES ON homya_test.archive_categories TO 'homya_test_schema'@'localhost';
```

   Matrice attendue : **24** seuls privilèges de table pour les six comptes, dont deux REFERENCES non délégables supplémentaires ; aucun SELECT/DML archive/global/schéma/colonne ajouté, aucun droit lecteur/importeur/local.
3. Sous homya_test_schema, compléter uniquement les trois ALTER encore absents de archive_categories : FK lot RESTRICT/RESTRICT, CHECK clé, CHECK slug. Conserver la table et ses colonnes/PK ; ne pas rejouer CREATE. Ajouter ensuite la seule ligne technique de migration catégorie, batch 1.
4. Créer archive_products et archive_translations avec les mêmes statements/contraintes du schéma OP-04 approuvé, puis leurs deux seules lignes techniques de journal, batch 1. Ne pas rejouer le lot ou une migration générale. La reprise réconcilie explicitement le journal après chaque DDL terminé ; elle n'est pas un nouvel appel du Migrator initial sur le schéma partiel.
5. Auditer cinq tables seulement, quatre lignes exactes id 1–4/batch 1, quatre archives vides, 24 droits exacts et homya_local vide. Comparer indépendamment toutes les colonnes/PK/FK/CHECK/UNIQUE/RESTRICT au DDL OP-04 avant de clôturer OP-05.

[DDL restant exact](../output/qa-import/op05-reprise-ddl.sql) : **24 instructions**, dont trois ALTER catégorie, deux CREATE et leurs ALTER associés. Il est dérivé sans PDO du même CatalogueArchiveSchema ; aucun SQL de catalogue/DROP/DELETE/TRUNCATE, aucune modification des colonnes. Trois INSERT techniques du journal sont prévus séparément après achèvement de chaque table.

## Outils préparés, réception de l'accord et exécution future

- [Enveloppe administrative](../scripts/op05_resume.py) : inspection en lecture seule par défaut, contrôle exact des stades de droits 22/23/24, refus de dérive du DDL partiel/journal/objets, deux seuls GRANT puis exécution ciblée et audit. Verrou local commun .op05.lock pendant les écritures et le contrôle de sortie.
- [Outil DDL](../scripts/op05_resume.php) : préparation hors SQL par défaut ; exécution future uniquement via l'enveloppe qui tient le verrou. Chargement du seul accès privé test schema, empreintes du code OP-04, garde identité/session, nouveau contrôle administratif avant DDL et confirmation interactive de l'accord reçu. Exception expurgée, seul code SQL numérique exposable si erreur.
- [Huit tests isolés sans SQL](../scripts/test_op05_resume.py) : matrice, collisions, refus sans reçu, séquence exacte et arrêt sans seconde écriture/reprise après écart. Syntaxe/Pint et contrôles hors connexion réussis ; aucune preuve de DDL réel dans ces tests.

Après accord complémentaire seulement : consigner/retrouver sa portée au registre central, puis créer un nouveau reçu local mode 0600, propriétaire courant, sans secret. Champs fermés : operation=OP-05-REPRISE, schema=homya_test, décision, execution_authorized=true, plan_sha256, artifacts. La liste fermée inclut les cinq artefacts OP-05 initiaux, les deux outils de reprise, l'état partiel figé et le DDL restant. L'accord initial/reçu OP-05 ne satisfait pas ce mode.

Commande future, **non exécutée** :

```text
/usr/bin/python3 scripts/op05_resume.py --execute --receipt=<nouveau reçu absolu> --access=<accès privé absolu .env.homya_test_schema> --report=<nouveau rapport absolu dans output/qa-import>
```

Un diagnostic indépendant final utilisera --inspect=final dans un autre rapport. Les applications susceptibles d'écrire dans les bases doivent rester fermées pendant l'intervention ; le verrou ne coordonne que ces outils. Le statut de collecte ne remplace pas la revue complète du DDL physique.

## Arrêt, exclusions et décision nécessaire

Arrêt au premier écart, aucune purge/rollback/reprise automatique ni élargissement de droits supplémentaire. Si un seul GRANT/ALTER/INSERT technique a réussi avant erreur, conserver cet état et refaire un plan ciblé ; ne jamais rejouer cette enveloppe sur cet état. DDL/GRANT non annulables par transaction ; restauration non testée.

**Décision nécessaire :** autoriser ces deux REFERENCES et cette reprise des seuls objets/lignes techniques manquants dans homya_test. Aucun import, compte/base supplémentaire, service/configuration XAMPP, homya_local, secret, lecteur/importeur, activation web ou transaction commerciale. OP-06 et OP-07 conservent leurs accords propres.

## Clôture après accord — 08/10/2026

Accord exact « j autorise les deux references », reçu en réponse à la demande regroupée de reprise, consigné/relu sous DECISION-030 avant SQL. Plan figé et reçu mode 0600 vérifiés ; [précontrôle renouvelé](../output/qa-import/op05-reprise-precontrole-autorise.json) conforme à l'état partiel. Deux seuls GRANT REFERENCES appliqués avec contrôles 22 → 23 → 24 droits ; reprise ciblée réussie sous le compte schema dédié : trois ALTER catégorie, deux tables manquantes avec leurs contraintes, trois INSERT techniques batch 1. Aucun rejeu de la table lot ni migrateur général.

[Résultat de l'enveloppe](../output/qa-import/op05-reprise-resultat.json), [audit indépendant](../output/qa-import/op05-reprise-controle-independant.json) et [comparaison physique](../output/qa-import/op05-reprise-verification-physique.json) confirment : cinq seules tables InnoDB/utf8mb4_bin, 34 colonnes, PK/index/UNIQUE conformes, quatre FK RESTRICT/RESTRICT et 27 CHECK (22 explicites et cinq JSON implicites), journal exact id 1–4/batch 1 et quatre archives vides. Matrice administrative exacte de 24 privilèges non délégables ; aucun élargissement hors ces deux REFERENCES. homya_local reste sans objet ; lecteurs/importeurs sans droits.

La comparaison tient compte des représentations physiques de MariaDB : JSON en LONGTEXT avec JSON_VALID implicite, largeur INT, BINARY rendu en CAST, NOT REGEXP rendu en négation et parenthèses redondantes supprimées. Deux premiers contrôles ont arrêté le vérificateur : absence de génération renvoyée NULL et ordre de tri des index différent de Python ; ces écarts de comparaison ont été corrigés uniquement dans l'outil de lecture, sans écriture SQL supplémentaire. [Vérificateur conservé](../output/qa-import/op05-verificateur-physique.py).

58 des 60 témoins initiaux inchangés ; document OP-05 et registre central sont les seules exceptions prévues. .env, my.cnf, code/migrations OP-04 conservés ; aucun service changé, import, recette DML, restauration ou bascule. Le site lit toujours les fichiers. Les contrôles de structure ne prouvent pas encore les refus de contraintes par données adverses, le transport ni la concurrence ; restauration non testée. [Clôture du registre](../output/qa-import/op05-reprise-cloture-registre.json) et [synthèse](../output/qa-import/op05-reprise-synthese-verification.json).

**Prochaine étape :** préparer OP-06 et ses opérations à autoriser séparément. Aucun accord d'import ou d'exécution OP-06 n'est inclus dans DECISION-030. Le reçu de reprise est historique et ne permet plus de rejouer cette opération sur l'état final.
