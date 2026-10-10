# HOMYA — réparation système XAMPP B

Version 0.2 — 07/10/2026 — **EXÉCUTÉE ET VÉRIFIÉE sous DECISION-024. Upgrade système réussi ; aucun redémarrage requis.**

Cette fiche précise B du [plan de correction](PLAN-CORRECTION-XAMPP-SYSTEME.md). Elle n'est pas un registre général : l'accord sera consigné et relu dans la section HOMYA du [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), sous DECISION-024 si le registre reste dans son état actuel. Aucun accord B n'est enregistré à cette préparation.

**Actualisation de clôture :** le paragraphe précédent et la procédure ci-dessous décrivent la préparation soumise. Le plan exact accepté est conservé dans [cor-b-plan-autorise.md](../output/qa-import/cor-b-plan-autorise.md), SHA-256 `922cc8453304423123d03a697a520597dd815ae8ddce86e0ce365e545e1d8a71`. Confirmation humaine Terminal, accord central et relecture vérifiés dans [cor-b-autorisation.json](../output/qa-import/cor-b-autorisation.json). L'acceptation explicite porte aussi sur la restauration non testée de la copie sur même volume.

Le helper utilisateur a terminé à 21:44:03 UTC : sauvegarde A recontrôlée, lanceur root macOS sauvegardé/corrigé/vérifié, `mysql_upgrade` code 0 et contrôles SQL réussis. **Aucun arrêt/redémarrage B exécuté**, le contrôle EVENTS ayant réussi directement. Voir [résultat root expurgé](../output/qa-import/cor-b-resultat-script.json), conservé sans réécriture.

Contrôle indépendant phpMyAdmin, connexion 53 : même XAMPP 10.4.28/3307/socket/datadir/écoute locale, `mysql.proc` **21 colonnes**, ROUTINES/EVENTS réussis, 16 Mio global/session, FK/CHECK actifs, événements OFF ; homya_test utf8mb4/utf8mb4_bin sans table/vue/routine/événement ; homya_local absente. Empreinte/syntaxe/uid0/gid80/mode0755 du lanceur installé conformes ; trois configurations et 3 868 témoins inchangés. Processus XAMPP65069/UID74 et Homebrew1009 encore présents. [Synthèse et preuves de clôture](../output/qa-import/cor-b-synthese-verification.json).

DECISION-024 a été complétée puis relue ; sauvegarde documentaire du registre : `/private/tmp/homya-cor-b-cloture-registre-o4yzdz79/registre-avant-cloture.md`. Une première copie documentaire avait un groupe différent ; la procédure a refusé avant toute écriture du registre, puis une nouvelle copie avec métadonnées conformes a permis la clôture. Aucun nouveau SQL d'écriture, service, signal, upgrade, compte, schéma, migration ou import par Codex à ce contrôle.

**Limites maintenues :** manifeste privé de sauvegarde recontrôlé par le helper root, pas relu indépendamment par Codex ; restauration non testée. Le comportement d'arrêt réel du lanceur corrigé n'a pas été exercé, aucun redémarrage nécessaire. Connexion Laravel et autres applications non certifiées. La reprise OP-02 se fera sous DECISION-021, pas sous l'accord B.

## Faits vérifiés et interprétation

- La [preuve CLI](../output/qa-import/cor-b-test-cli.json), SELECT uniquement, confirme XAMPP 10.4.28/3307, socket/datadir exacts, session administrative attendue, neuf privilèges globaux utiles, paquet 16 Mio et événements OFF. L'accès local sans mot de passe a été **testé**, pas présumé ; il ne devient pas un accès Laravel.
- `mysql.proc` comporte toujours 20 colonnes ; le contrôle ROUTINES précédent en attendait 21. L'historique de l'ancien schéma système reste inconnu.
- La [copie physique A](COR-XAMPP-A-REPRISE.md) a été vérifiée et le retour SQL contrôlé. Elle date du 07/10/2026 à 21:12 UTC, contient toute l'instance, se trouve sur le même volume et **sa restauration n'a pas été testée**. Un retour arrière pourrait perdre les écritures postérieures ; aucune restauration automatique proposée.
- Le lanceur installé utilise `su - mysql -s /bin/sh` lorsque `$USER` n'est pas `mysql`. Les options locales macOS et le shell du compte ne permettent pas cette méthode ; le premier arrêt avait annoncé un succès sans arrêter le serveur. Le correctif proposé fait utiliser la branche native `kill` au processus administrateur réel, vérifié par `/usr/bin/id -u`.

## Intervention précise à accepter

1. Précontrôler une nouvelle connexion CLI, le PID/exécutable/UID/socket/écoute XAMPP, les configurations et les empreintes. Refuser une opération déjà enregistrée ou concurrente. Recalculer sous droits administrateur l'inventaire complet de la copie A et le comparer à son manifeste privé, sans afficher données ou authentifications.
2. Préparer un nouveau dossier privé root/0700 `xamppfiles/homya-maintenance/<UTC>-<UUID>/`. Il reçoit les sauvegardes du lanceur et du registre, les options et journaux privés. Aucun fichier de la sauvegarde A n'est changé.
3. Présenter le périmètre et demander la saisie exacte **CORRIGER XAMPP** dans un véritable Terminal. Cette saisie accepte explicitement la limite de restauration et autorise les deux changements ci-dessous, ainsi que le seul redémarrage conditionnel décrit. Toute autre réponse arrête avant modification installée/SQL. Les applications locales écrivant dans XAMPP doivent rester fermées pendant l'intervention.
4. Sauvegarder et comparer `share/mysql/mysql.server` et le registre ; enregistrer puis relire l'accord central. Conserver propriétaires, groupe, modes, ACL et attributs lors des écritures ciblées. La modification de `mysql.server` remplace **une seule ligne** :

```sh
# Avant
  if test "$USER" = "$user"; then
# Après
  if test "`/usr/bin/id -u`" = "0" || test "$USER" = "$user"; then
```

La branche non administrateur reste inchangée. Le signal d'arrêt natif est SIGTERM par défaut ; aucun SIGKILL, `killall`, modification de shell ou suppression manuelle de PID. Vérifier `sh -n`, empreinte et métadonnées avant toute suite.

5. Lancer une fois le binaire installé, sans téléchargement, avec cette liste d'arguments :

```text
/Applications/XAMPP/xamppfiles/bin/mysql_upgrade
--defaults-file=<nouveau dossier privé>/options.cnf
--protocol=socket
--socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
--upgrade-system-tables
--force
--version-check
--skip-write-binlog
--skip-verbose
```

Le fichier d'options est le premier argument, mode 0600 dans un dossier privé ; accès limité à la session administrative locale déjà testée. Les variables d'authentification/ciblage héritées sont retirées. Aucun contenu de ce fichier ou journal brut n'est publié. Le PHP XAMPP n'est pas utilisé pour Laravel.

**Impact :** changement des structures système `mysql`, dont celles des privilèges de **toutes les bases locales XAMPP**, et marqueur éventuel `mysql_upgrade_info`. Une opération DDL peut rester partielle. Pas de rollback garanti ou relance forcée. L'aide locale indique que les tables des autres bases ne sont pas vérifiées ni modifiées par l'option retenue ; leur fonctionnement applicatif complet n'est pas certifié.

6. Après code retour zéro, vérifier 21 colonnes `mysql.proc`, ROUTINES et invariants SQL. **Seulement** si EVENTS échoue encore avec 1577, les autres contrôles étant réussis, exécuter un arrêt/redémarrage propre du seul MariaDB XAMPP via le lanceur corrigé. Confirmer disparition réelle du processus/socket/port/PID avant démarrage ; refuser un autre processus ou une réapparition. Une seule tentative, événements toujours OFF. Si le contrôle échoue encore : conserver le résultat incomplet, aucune activation ou relance supplémentaire.
7. Recontrôler via de nouvelles connexions : version/port/socket/datadir/PID/écoute, admin et neuf privilèges, 16 Mio global/session, FK/CHECK actifs, événements et journal binaire OFF, homya_test utf8mb4/utf8mb4_bin sans table/vue/routine/événement, homya_local absente, métadonnées et empreintes des fichiers attendus. Publier uniquement le résumé expurgé `output/qa-import/cor-b-resultat-script.json`. Le résultat privé et les journaux sont conservés pour investigation.

En cas d'interruption ou de dépassement de 15 minutes de l'utilitaire, aucune terminaison forcée : son PID et son éventuelle activité sont signalés, aucune seconde réparation ne doit être lancée. Si un arrêt est confirmé mais le démarrage échoue, le service peut rester arrêté : inspection manuelle nécessaire, sans restauration improvisée.

## Empreintes imposées et exclusions

| Élément | SHA-256 |
|---|---|
| `mysql.server` avant | `9d0c89c86c23319faca668c153bc50f4f34771a105866668a0c6f9fe1f9bd3a0` |
| `mysql.server` après prévu | `44592ab0913ffb782e29ca75706506a43991f0f0e7e6a680c0a7645fb4a9cf13` |
| `mysql_upgrade` installé | `94bb34d3e849060411045956d4d25af57912b6d38398344e356273fa2da106e8` |

Helper : `/private/tmp/homya-cor-b-reparation-20261007.py`, avec dépendances aux deux helpers A immuables. Son empreinte et celle du présent plan seront scellées dans `output/qa-import/cor-b-preparation.json` après tests. Le helper refuse toute divergence avant l'intervention.

**Exclus :** arrêt Apache/Homebrew, nouvelle base ou compte, données/tables métier, migration/import/bascule, modification de `.env`, accès Laravel administrateur, mot de passe, activation d'événements, restauration. L'OP-02 existant reste partiel ; sa reprise vient **après** une réparation vérifiée, sous son accord distinct déjà enregistré.

## Sources et contrôles

| Source — consultation 07/10/2026 | Portée / fait étayé |
|---|---|
| [MariaDB — mariadb-upgrade](https://mariadb.com/docs/server/clients-and-utilities/deployment-tools/mariadb-upgrade) | Recommande une sauvegarde ; explique la portée système seulement et la vérification de version. Les indications de versions récentes ne remplacent pas les capacités du binaire local 10.4.28. |
| [Aide locale mysql_upgrade](../output/qa-import/cor-xampp-mysql_upgrade-aide.txt) | Options réellement disponibles, ordre du fichier d'options et risque de verbosité ; affichage de l'aide/version avec code 1, **aucun upgrade exécuté**. |
| `share/mysql/mysql.server`, lanceur `xampp`, aides macOS | Branches de service réellement lues ; comportement réel du correctif installé et arrêt via celui-ci restent à vérifier après consentement. |

Tests préparatoires : remplacement ciblé/ambiguïtés, syntaxe et appels root/nonroot simulés sans signal réel, arguments bornés/env, collision et section du registre, EVENTS1577 distinct d'autres erreurs, copie/modes/ACL/attributs et détection d'altération sur fixtures. Le succès des fixtures **ne prouve pas** la réparation de l'instance. Voir le rapport `output/qa-import/cor-b-tests.json`.

## Commande à exécuter par l'utilisateur

```sh
sudo /usr/bin/python3 /private/tmp/homya-cor-b-reparation-20261007.py --apply
```

Le mot de passe macOS, si demandé, est saisi uniquement dans Terminal. Après précontrôles, lire le périmètre et taper `CORRIGER XAMPP` pour l'autoriser, ou toute autre réponse pour arrêter. Transmettre seulement le résumé JSON expurgé, jamais les options ou journaux privés. Cette exécution ne crée pas les schémas et ne vaut pas validation des migrations ou du commerce réel.
