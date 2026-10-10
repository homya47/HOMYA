# HOMYA — OP-05 : préparation du schéma de recette

Version 0.3 — 08/10/2026 — **EXÉCUTION INITIALE AUTORISÉE, INTERROMPUE ; SCHÉMA TEST PARTIEL, REPRISE NON AUTORISÉE**.

Source : plan documentaire DECISION-019, OP-03a/b exécutées DECISION-025/026 et code OP-04 complément DECISION-028. Le seul registre reste [Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). La version 0.2 et ses mentions de préparation sont conservées comme historique ci-dessous ; copie exacte approuvée dans [op05-plan-autorise.md](../output/qa-import/op05-plan-autorise.md). L'accord utilisateur a été enregistré/relu sous DECISION-029 avant exécution. **Voir l'état d'interruption en fin de document ; ne pas relancer les commandes initiales sur le schéma partiel.**

## Périmètre à autoriser distinctement après précontrôle

Dans **homya_test seulement**, via **homya_test_schema@localhost** : créer le journal Laravel migrations, accorder ses deux seuls droits SELECT/INSERT à ce compte après création, puis lancer les quatre migrations catalogue revues. Résultat attendu : exactement cinq tables InnoDB (`migrations`, `archive_lots`, `archive_categories`, `archive_products`, `archive_translations`) ; quatre lignes techniques de journal, zéro ligne dans les tables archive. Aucun catalogue chargé, lecteur/importeur habilité, modification homya_local, changement de compte/configuration/service ou bascule.

## Artefacts et instructions précis

1. Journal proposé : [DDL généré hors connexion](../output/qa-import/op05-journal-propose.sql). Colonnes id INT unsigned auto-incrément PK, migration VARCHAR255, batch INT, selon createRepository du Laravel installé ; InnoDB/utf8mb4_bin explicites. L'ID technique du journal n'est pas ajouté aux quatre relations d'archive.
2. Après création vérifiée, seul GRANT supplémentaire proposé :

```sql
GRANT SELECT, INSERT ON `homya_test`.`migrations` TO 'homya_test_schema'@'localhost';
```

   Les vingt droits CREATE/ALTER audités seraient conservés, plus ces deux droits techniques : total **22** privilèges de table, sans délégation/DROP/DELETE/droit de schéma/global/lecteur/importeur. Les deux droits supplémentaires restent proposés ; aucun GRANT exécuté pendant ce précontrôle.
3. [DDL des quatre tables](../output/qa-import/op04-complement-schema-final.sql). Le contenu compilé utilisé par la garde compte **6 492 octets**, SHA256 `1d85f2b72f241349254a9911cfa9da3bee76792d3fe9c3d9b77cb5d64600b568`. Le fichier console a un saut de ligne supplémentaire : **6 493 octets**, SHA256 `462cd029a43dbc2a2c35d40a0d395f59bcad88d9dd41bb3d3eb407315163515b`. Ces empreintes ne sont pas interchangeables ; aucune normalisation des fichiers. [Liste et empreinte code](../output/qa-import/op04-complement-schema-final.json) : `0f87b009dfc2658d74ee421d5dbfb0cfe77c17ebc6ed6732252a10de9193528f`, recontrôlée et inchangée.
4. Enveloppe future disponible : `homya:catalogue-schema --execute --operation=OP-05 --connection=homya_test_schema --expected-ddl=<empreinte ci-dessus> --expected-files=<empreinte code ci-dessus> --access=<chemin privé sélectionné localement>`. **Ne pas lancer maintenant.** La confirmation interactive porte sur l'accord déjà reçu ; elle n'en crée pas un. L'accès ne contient pas de secret en argument, uniquement un chemin.

La commande exige journal seul/vide/accessible et contexte/empreintes conformes avant Migrator ; elle ne crée ni journal/base, ne charge aucun dump, ne lance ni squelette ni seeder, n'exécute aucun rollback. Le DDL exact et les quatre noms de journal sont contrôlés en sortie ; résultat à confirmer indépendamment avec accès administratif. Ne pas utiliser migrate général/pretend/install/fresh/refresh.

## Précontrôle effectué en lecture seule

Le [rapport administratif final](../output/qa-import/op05-precontrole-final-xampp.json) fournit l'heure UTC, les métadonnées et la matrice exacte. Un [premier contrôle](../output/qa-import/op05-precontrole-xampp.json) et la [connexion Laravel dédiée](../output/qa-import/op05-connexion-laravel.json) sont conservés séparément.

| Observation vérifiée le 08/10/2026 | Preuve / portée |
|---|---|
| MariaDB 10.4.28, socket/datadir XAMPP exacts, port 3307, bind 127.0.0.1 | Client natif XAMPP avec options explicites, sans environnement d'authentification hérité |
| Paquets global/nouvelle session = 16 777 216 octets ; FK/CHECK actifs ; événements OFF | Variables serveur et session, aucune modification globale |
| Deux bases utf8mb4/utf8mb4_bin sans table, vue, routine, événement ou trigger | Inventaire administratif complet, distinct de la visibilité limitée du compte schema |
| Six seuls comptes HOMYA à localhost, plugin attendu ; vingt seuls droits CREATE/ALTER ciblés | mysql.tables_priv rapproché de information_schema ; aucun droit global réel/schéma/colonne/routine, délégation, rôle ou proxy |
| InnoDB disponible avec transactions/savepoints ; collations utf8mb4_bin et ascii_bin disponibles | Métadonnées moteur/collations ; ne prouve pas encore les contraintes du futur schéma |
| Laravel/PDO se connecte à homya_test avec homya_test_schema et valide la cible/session stricte | `op05_journal.php --probe`, un seul accès privé chargé en mémoire, sans DDL ni secret imprimé |

Le mode SQL administratif publié dans la preuve et le mode strict établi par le connecteur Laravel sont distincts : la connexion applicative applique ses options de session, sans changer le mode global. La preuve Laravel ne valide pas encore les capacités CREATE/ALTER réelles de ce compte.

Le premier accès sandbox a échoué (2002) ; les connexions ont été autorisées hors sandbox. La conversion JSON du contrôle a été corrigée : MariaDB 10.4 rendait certaines variables booléennes sous forme ON/OFF ; CAST explicite vers entier ajouté. Ce n'était pas une correction du serveur. Les tests de sockets synthétiques nécessitent également une exécution hors sandbox.

## Outils livrés et séquence proposée après autorisation

- [Outil administratif](../scripts/op05_xampp_admin.py) : inventaire SQL en lecture seule quand un rapport est demandé ; validation de reçu seule sans SQL ; un seul GRANT exact avec `--grant-journal` ; enveloppe de migration avec `--run-migrations`. Aucun mot de passe dans le code ou les arguments. Erreurs expurgées, arrêt sans reprise/purge automatique.
- [Outil du journal](../scripts/op05_journal.php) : compilation/empreintes hors connexion par défaut ; `--probe` lit uniquement la cible ; `--create` prépare le seul journal sous le compte test dédié, après reçu et nouvel inventaire administratif. Pas de migration ni GRANT dans cet outil.
- [Gardes testées sans SQL](../scripts/test_op05_xampp_admin.py) et [vrai Migrator Laravel sur double sans PDO](../tests/Feature/CatalogueSchemaPreparationTest.php) : droits/collisions, reçu/empreintes, ordre des opérations, arrêt sur erreur, quatre migrations exactes et batch 1 simulé, restauration du contexte applicatif. [Double](../tests/Support/ArchiveSchemaConnectionDouble.php).

Après accord humain explicite seulement : consigner et relire l'accord dans le registre central, puis délivrer un reçu local propriétaire/mode 0600 dans output/qa-import. Il contient exclusivement `operation=OP-05`, `schema=homya_test`, `account=homya_test_schema@localhost`, ID de décision, `execution_authorized=true`, SHA du présent plan et dictionnaire d'empreintes des cinq artefacts fermés du helper (les deux outils, journal SQL, schéma JSON/SQL). **Aucun reçu d'exécution n'est créé dans cette préparation.** Un reçu est une garde technique ; il ne produit pas une autorisation humaine. Recontrôler également le digest code OP-04 avant l'exécution.

Les commandes ci-dessous sont des modèles futurs, **non exécutés** ; remplacer les chemins entre chevrons par les fichiers locaux vérifiés. Ne jamais placer le contenu d'un accès dans un argument. Ouvrir une console interactive pour la migration et garder les applications qui pourraient écrire dans ces bases fermées pendant l'opération.

1. Nouveau précontrôle :

```text
/usr/bin/python3 scripts/op05_xampp_admin.py --phase=empty --report=<nouveau rapport absolu dans output/qa-import>
```

2. Journal uniquement ; avant CREATE, inventaire root frais et connexion schema gardée ; après CREATE, colonnes/PK/index et vacuité vérifiés administrativement :

```text
/opt/homebrew/bin/php scripts/op05_journal.php --create --receipt=<reçu absolu OP-05> --access=<accès privé absolu .env.homya_test_schema>
```

3. Un seul GRANT SELECT/INSERT sur le journal existant ; contrôle complet journal/vacuité/droits avant puis matrice de 22 droits après :

```text
/usr/bin/python3 scripts/op05_xampp_admin.py --grant-journal --phase=journal --receipt=<reçu absolu OP-05> --report=<nouveau rapport absolu>
```

4. Contrôle administratif journal seul/vide et droits 22, puis les quatre migrations ciblées avec les empreintes figées. L'enveloppe appelle la commande OP-04 documentée plus haut et conserve son exigence de confirmation interactive de l'accord déjà reçu :

```text
/usr/bin/python3 scripts/op05_xampp_admin.py --run-migrations --phase=granted --receipt=<reçu absolu OP-05> --access=<accès privé absolu .env.homya_test_schema> --report=<nouveau rapport absolu>
```

5. Audit administratif distinct `--phase=final` dans un nouveau rapport, puis revue complète du DDL physique décrit ci-dessous avant de déclarer OP-05 vérifiée. Les helpers contrôlent les cinq objets/moteurs/collations, les quatre lignes exactes du journal avec id 1–4/batch 1, les quatre archives vides, les 22 droits et homya_local vide ; ils collectent SHOW CREATE TABLE. Le rapprochement complet colonnes/contraintes au DDL figé demeure un contrôle indépendant obligatoire, pas une conséquence du simple statut CHECKS_PASSED.

Les outils d'écriture utilisent un verrou local commun `.op05.lock`, non bloquant ; l'enveloppe de migration garde ce verrou pendant le migrateur et le contrôle final. Ce verrou coordonne ces outils seulement, pas les autres applications ni un administrateur. Chaque étape s'arrête sur tout écart ; aucune étape suivante après échec. Les tests n'exécutent aucune des commandes d'écriture réelles.

## Contrôles à renouveler avant exécution

- Lire de nouveau en SQL **lecture seule** les identités XAMPP10.4.28/socket/datadir/3307, capacités global/session16Mio, FK/CHECK/modes stricts, moteurs/collations et inventaire administratif complet des deux bases ; aucun objet homya_test initial admis. Ne pas déduire cet inventaire de la seule visibilité du rôle schema.
- Auditer la matrice des six comptes et les vingt seuls droits actuels ; collision/écart = arrêt. Vérifier l'accès dédié seulement dans le contexte autorisé, sans afficher le contenu ; ne pas toucher aux autres comptes/bases.
- Relire les deux helpers livrés, le plan et leurs empreintes ; confirmer l'absence d'un reçu précédent applicable et de rapport de sortie en collision. Refus de collision, aucun `IF NOT EXISTS`, aucune autorisation de créer un compte implicite.
- Recontrôler les empreintes de code/DDL et le contenu du seul dossier catalogue ; prévoir sauvegarde des configurations/preuves utiles, sans déclarer restauration testée. La capsule privée ne remplace pas une sauvegarde/restauration SQL.

## Arrêt et recette de sortie

Le DDL/GRANT n'est pas annulable par la transaction d'import. Arrêt au premier écart ; conserver le schéma/journal partiels et diagnostiquer, aucune purge/reprise/REVOKE automatique. Le code refuse un état initial partiel : toute reprise ciblée aura son plan propre. Une restauration n'est pas promise ; OP-07 reste l'épreuve isolée avant les opérations locales.

Après OP-05 autorisée : inventaire administratif indépendant de cinq seuls objets et moteur/collation, SHOW CREATE TABLE/colonnes/PK/FK/CHECK/UNIQUE/restrict, journal exact quatre noms/batch1, quatre tables archive vides et droits22 attendus, homya_local toujours vide. Ce constat physique ne prouve pas encore les refus de contrainte, les verrous/concurrence ou le transport : jeux et autorisation OP-06 distincts. Aucun DML de catalogue/paiement/commande dans OP-05.

## Sources et limites

Question externe vérifiée : les droits CREATE/ALTER ciblés suffisent-ils au DDL prévu, sans élargir à INDEX/REFERENCES ? Source officielle [GRANT — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/account-management-sql-statements/grant), consultée le **08/10/2026**, portée : sémantique publiée des privilèges de table. Elle indique que CREATE peut être accordé avant existence de la table, que les index peuvent être créés avec CREATE/ALTER via ces instructions et que REFERENCES est inutilisé. **Interprétation :** conserver la matrice approuvée ; aucun nouveau droit proposé pour ces index/FK. **Limite :** la documentation actuelle n'est pas une recette de notre serveur 10.4.28 ; les DDL réels restent à vérifier dans OP-05 et les refus/concurrence dans OP-06. Le code Laravel installé (DatabaseMigrationRepository/Migrator/grammaire) constitue la source primaire de la forme du journal et du parcours ciblé ; aucun changement de dépendances.

Le DDL/GRANT effectue des commits implicites : [SQL statements that cause an implicit commit — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit), source officielle consultée le 08/10/2026 pendant OP-04. Aucun rollback transactionnel sûr promis. Copie physique système XAMPP du 07/10/2026 conservée dans les preuves COR-XAMPP, même disque et antérieure aux comptes ; restauration non testée. Les témoins expurgés de droits/configuration ne sont pas une sauvegarde SQL restaurable. OP-07 conserve son épreuve isolée avant le schéma local.

## Résultat de la préparation et demande d'accord

**Faits vérifiés :** syntaxe/Pint des trois PHP ajoutés ; douze tests Python sans SQL ; quatre nouveaux tests du migrateur sans PDO ; régression Laravel **146 tests / 1 001 assertions**, sans erreur/échec ; empreintes OP-04 et témoins applicatifs inchangés. [Synthèse et empreintes](../output/qa-import/op05-preparation-synthese-verification.json). Aucun code applicatif ni migration OP-04 modifié, aucun catalogue/secret/configuration/service modifié, aucun compte/base/table créé, aucun GRANT, migration réelle, import ou bascule. Les simulations restent distinctes des deux connexions SQL de diagnostic.

**Décision nécessaire :** autoriser OP-05 dans son ensemble décrit ici : seul schéma homya_test, création du journal + quatre tables archive, seul GRANT SELECT/INSERT du journal au compte homya_test_schema, quatre lignes techniques batch 1 et contrôles indépendants, arrêt sur écart. Aucun droit/catalogue/objet homya_local ajouté. L'accord permettra de consigner la décision avant l'exécution ; il n'autorise pas OP-06/import ni activation.

**Bloquante pour exécution :** autorisation explicite OP-05 absente. Aucune anomalie bloquante de cible observée au précontrôle ; celui-ci sera renouvelé avant écriture. **À vérifier après accord :** succès DDL réel et conformité du schéma physique. **Différé :** intégrité par données adverses/transport/verrous OP-06, restauration OP-07, schéma local et activation ultérieurs.

## Exécution initiale après accord — arrêt sur schéma partiel

Accord « j autorise les cinq tables » reçu en réponse à la demande regroupée OP-05, inscrit/relu sous DECISION-029. [Reçu et empreintes](../output/qa-import/op05-autorisation.json), [enregistrement](../output/qa-import/op05-enregistrement-accord.json) et [nouveau précontrôle](../output/qa-import/op05-precontrole-execution.json). Création du journal réussie sous le compte test dédié et colonnes/PK/index/vacuité contrôlés administrativement ; seul GRANT SELECT/INSERT du journal appliqué et matrice exacte 22 vérifiée.

La commande ciblée a été lancée, puis a renvoyé un échec : écritures immédiatement arrêtées, aucun rollback/purge/rejeu. [Diagnostic administratif](../output/qa-import/op05-diagnostic-interruption.json) et [état figé](../output/qa-import/op05-etat-partiel-fige.json) : journal + lot complets, catégorie créée avec colonnes/PK mais FK/CHECK absents, produits/traductions absents. Une seule ligne technique, id 1/batch 1 pour le lot ; lot/catégorie vides ; homya_local vide, 22 droits et aucun droit lecteur/importeur. Aucun import/activation/service/configuration/secret modifié.

**Correction de l'analyse initiale des privilèges :** la source générale GRANT citée plus haut ne couvrait pas le contrôle des tables parentes implémenté dans MariaDB 10.4.28. Le code versionné exige au moins un droit parent parmi SELECT/INSERT/UPDATE/DELETE/REFERENCES ; CREATE/ALTER seuls ne suffisent pas. Le code SQL de l'erreur originale n'a pas été capturé, donc la cause est une interprétation étayée par l'emplacement de l'arrêt, la matrice et le code officiel. Références, limites et correction minimale dans [la reprise proposée](OP-05-REPRISE-CIBLEE.md).

**État : INCOMPLET, ARRÊT APPLIQUÉ.** La reprise préparée propose deux seuls REFERENCES parents et la complétion sélective sans suppression, à autoriser distinctement. Aucun de ces deux GRANT ni aucun DDL de reprise exécuté. Les limites DDL/transport/concurrence/restauration et opérations OP-06+ restent séparées. Le reçu initial ne permet pas la reprise préparée.

## Résolution ultérieure — OP-05 clôturée sans import

Le 08/10/2026, l'accord complémentaire « j autorise les deux references », consigné sous DECISION-030 dans le contexte de la reprise regroupée, a permis les deux REFERENCES ciblés et la complétion sélective. [Clôture de la reprise](OP-05-REPRISE-CIBLEE.md#clôture-après-accord--08102026) : cinq tables physiques test vérifiées, journal id1–4/batch1 exact, quatre archives vides, 24 seuls droits non délégables, homya_local vide. Colonnes, PK/index/UNIQUE, quatre FK RESTRICT/RESTRICT et 27 CHECK rapprochés indépendamment du DDL OP-04 figé. [Preuve physique](../output/qa-import/op05-reprise-verification-physique.json).

Les sections de préparation et d'arrêt précédentes sont historiques. Leurs reçus/commandes ne doivent pas être rejoués. Aucun catalogue importé, service/configuration/secret modifié ou lecture SQL publique activée ; recette DML/transport/concurrence OP-06 et restauration OP-07 restent distinctes et non exécutées.
