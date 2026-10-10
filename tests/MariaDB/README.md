# Recette XAMPP — isolée, désactivée par défaut

Ce dossier n'est pas inclus dans `phpunit.xml`. Aucun RefreshDatabase, migrate, seeder, nettoyage, suppression, création de cible ni import automatique. Le seul test exécutable préparé ici est un rapprochement **SELECT** avec `homya_test_reader` ; il attend un lot déjà chargé et vérifié sous OP-06. Il ne valide pas les scénarios d'écriture/concurrence ci-dessous.

Sans `HOMYA_RUN_ARCHIVE_RECIPE=OP-06`, le test est skipped avant démarrage Laravel/chargement d'accès. Même avec ce flag, il exige `HOMYA_RECIPE_EXPECTED_LOT` et `HOMYA_RECIPE_AUTHORIZATION` : reçu privé0600 sans symlink dans output/qa-import, précisant operation=OP-06, schema=homya_test, lot_key exact et sql_read_recipe_authorized=true. **Ce reçu n'existe pas sous OP-04 ; ne pas le fabriquer pour contourner l'accord.** Le fichier d'accès est choisi localement par HOMYA_TEST_READER_ACCESS, jamais un secret en argument ou sortie.

Commande de sélection : `/opt/homebrew/bin/php vendor/bin/phpunit --configuration phpunit.mariadb.xml`. Sans les gardes satisfaites, ce n'est pas une preuve de compatibilité MariaDB. N'exécuter avec flags/accès qu'après accord distinct couvrant la lecture de recette.

## Scénarios à finaliser après OP-05, avant l'accord OP-06

- Contrôle DDL réel des cinq seuls objets, InnoDB/types/PK/FK/CHECK/UNIQUE/actions RESTRICT, dates6, JSON_VALID et paramètres de session actifs.
- Matrice administrative des droits DML test puis tests de refus croisés/écriture reader ; aucune élévation automatique.
- Jeux synthétiques précisément identifiés : violations PK/FK/catégorie autre lot/positions/domaines/nullabilité/JSON, marque vide/ancien prix null, document hors capacité, hash/texte différent, précision des types et absence de trous.
- Transactions de validation qui annulent les seules insertions synthétiques prévues ; aucune suppression ou mutation d'un lot vérifié ni nettoyage d'une base trouvée.
- Deux processus importeurs du même lot, interruption avant commit, commit incertain, lot partiel, idempotence ; comptes dédiés et synchronisation contrôlée. Un double PHP n'en prouve pas le comportement InnoDB.
- Transport PDO des gros documents et rapprochement intégral de la capsule identifiée, mémoire/durée/verrou mesurés, aucune activation locale.

Les scripts/jeux SQL exacts de ces scénarios restent à produire et examiner dans la préparation OP-06, avec tables et droits réellement observés. Ce dossier ne donne aucune autorisation d'exécution anticipée. La sauvegarde/restauration isolée garde sa propre étape OP-07.
