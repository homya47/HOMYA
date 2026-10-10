# HOMYA — reprise de COR-XAMPP-A

7 octobre 2026 — **EXÉCUTÉ ET VÉRIFIÉ : copie physique et retour SQL ; COR-XAMPP-A clôturée sous DECISION-023. Restauration non testée.**

Le document exact soumis est conservé dans `/private/tmp/homya-cor-a-reprise-accord-e84_9ol5/COR-XAMPP-A-REPRISE.md`, SHA-256 `cb8a1634335d60631f2eabe8453a6dfc8521bba4e674b6efd582e2488256d1e0`. L'actualisation présente consigne l'accord et la commande locale ; elle ne change pas le mécanisme approuvé.

## Faits, diagnostic et source

Le [diagnostic administrateur](../output/qa-import/cor-a-diagnostic-service.json) de 20:54:25 UTC confirme : lanceur d'arrêt annonçant « ok », aucun redémarrage annoncé, processus initial XAMPP 18340/UID 74 toujours actif, socket présent et PID configuré absent. Aucun datadir ni manifeste de sauvegarde créé. L'inventaire avant la tentative mesurait 122 100 888 octets ; il sera remesuré avant la reprise.

Le fichier installé `share/mysql/mysql.server` appelle `su - mysql -s /bin/sh -c ...` pour tester/signaler le processus. Son échec de test conduit à une branche supprimant le PID sans envoyer le signal d'arrêt. Le compte du processus a `/usr/bin/false` comme shell. Le binaire macOS `/usr/bin/su -s`, invoqué seul sans compte ou commande cible, retourne 1 : « illegal option -- s » ; son aide locale expose `su [-] [-flm] [login [args]]`.

**Interprétation étayée :** cette méthode de `su` est incompatible avec l'environnement observé ; elle explique le test négatif, la disparition du PID et le faux succès extérieur du lanceur. Le stderr interne du premier arrêt a été masqué par le lanceur ; aucun test `su` sur le compte MariaDB ni reproduction d'arrêt n'a été exécuté pour confirmer chaque branche. Le helper initial a conservé un message générique de récupération, masquant sa première cause. Les nouvelles erreurs seront enregistrées séparément.

| Source officielle | Consultation / portée / fait |
|---|---|
| [MariaDB — SHUTDOWN](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/shutdown) | 07/10/2026 ; arrêt Unix du processus serveur : SIGTERM (15) est documenté comme équivalent à un arrêt par mariadb-admin. Cela ne prouve pas le résultat local ; les contrôles ci-dessous restent nécessaires. |

Le [précontrôle SQL actualisé](../output/qa-import/cor-a-reprise-precontrole.json) conserve XAMPP 10.4.28/3307/datadir/socket exacts, écoute 127.0.0.1, paquet 16 Mio, PID configuré exact, innodb_fast_shutdown=1, événements OFF, journal binaire OFF, aucun tablespace extérieur déclaré ni DATA DIRECTORY, homya_test présente sans table/vue, homya_local absente. SELECT uniquement.

## Ajustement autorisé

L'autorisation DECISION-022 de sauvegarde complète A demeure enregistrée. Son [plan exact approuvé](/private/tmp/homya-cor-a-accord-q5rbctin/PLAN-CORRECTION-XAMPP-SYSTEME.md) imposait « Utiliser uniquement la commande de service ci-dessous ». Le lanceur d'arrêt ne fonctionne pas ici ; l'utilisateur a autorisé l'ajustement **COR-XAMPP-A-REPRISE-SIGTERM** par « j autorise », enregistré puis relu dans DECISION-023 du [registre central](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). Aucune autorisation de réparation B n'en découle.

**Seul changement de mécanisme :** envoyer un SIGTERM normal directement au processus MariaDB XAMPP après vérification stricte. Le PID absent ne sera pas recréé manuellement ; aucun fichier installé ni compte système ne sera modifié. SIGKILL, killall, pkill et tout signal forcé restent exclus.

La fenêtre concerne toutes les bases locales de l'instance XAMPP. Suspendre leurs écritures externes pendant l'opération. Apache et MySQL Homebrew/3306 restent hors intervention.

## Opérations et garde-fous exacts

1. Consigner et relire l'accord d'ajustement dans le seul registre central, puis préparer `cor-a-reprise-autorisation.json` avec les empreintes du script, précontrôle, diagnostic, résultat précédent et configurations. **Réalisé :** [accord et empreintes](../output/qa-import/cor-a-reprise-autorisation.json) créés, relus et vérifiés ; aucune commande de service durant l'enregistrement.
2. Avec sudo local, vérifier la fraîcheur SQL (deux heures maximum), les empreintes, l'absence de résultat de reprise, la configuration et la destination privée. Relever tous les fichiers et l'espace avant interruption, sans liens/tablespaces extérieurs non couverts ; exiger plus de deux fois la taille mesurée plus 1 Gio libres.
3. Identifier une seule instance `sbin/mysqld` XAMPP, UID 74, liée au socket exact et à l'écoute exacte `127.0.0.1:3307`. Confirmer le processus attendu par le diagnostic, PID absent, et sa date de démarrage. Refuser toute ambiguïté, collision ou état différent.
4. Créer un nouveau dossier privé 0700 sous homya-backups/UTC-UUID. Recontrôler l'identité immédiatement avant le seul `os.kill(pid, SIGTERM)`. Aucun PID codé en dur utilisé pour envoyer le signal sans identification ; aucun signal à Homebrew.
5. Attendre au plus 120 secondes la disparition du processus, du socket, du PID et de l'écoute ; vérifier qu'aucun autre processus XAMPP n'est apparu. Si ces contrôles échouent, aucune copie ni escalade à SIGKILL. Si le processus initial reste actif, ne pas démarrer une deuxième instance.
6. À l'arrêt confirmé, copier l'intégralité du datadir puis comparer source/copie/source : noms, types, tailles, SHA-256, propriétaires, modes, horodatages, flags, ACL et attributs. Copier les trois configurations inchangées et vérifier leurs empreintes. Garder les manifestes et journaux privés, jamais dans Git/htdocs/cloud.
7. Revenir au même service via le lanceur XAMPP `startmysql` uniquement après disparition effective de l'ancien processus et absence de toute activité/objet divergent. Attendre le vrai processus, nouveau PID, socket et port. Cela recrée le PID par le démarrage normal du serveur.
8. En cas d'échec, conserver le dossier et la première erreur avec l'erreur de récupération séparée. Tenter le retour du même service seulement dans les conditions sûres de l'étape 7. Aucune suppression, restauration, réparation ou relance automatique.
9. Après le résumé du script, exécuter une nouvelle connexion SQL de contrôle et actualiser la trace centrale. Un port ouvert ou un message « ok » ne suffit pas à clôturer A.

## Script préparé et contrôles réalisés

[Helper exécuté par l'utilisateur dans Terminal](/private/tmp/homya-cor-a-reprise-20261007.py), dépendant des utilitaires initiaux immuables `/private/tmp/homya-cor-a-sauvegarde-20261007.py` (SHA-256 vérifié avant import). Aucun code Laravel, donnée catalogue ou fichier de configuration XAMPP modifié.

Les [tests isolés](../output/qa-import/cor-a-reprise-tests.json) vérifient syntaxe, sélection du processus et port/socket exacts simulés, refus d'un UID/PID/port divergent ou de plusieurs instances, identité recontrôlée et seul SIGTERM simulé, exclusion Homebrew, copie de fichiers d'essai avec métadonnées, détection d'altération et refus des liens. Aucune interruption, signal réel, copie des données XAMPP ou exécution root de reprise durant ces tests.

Le helper refuse `--apply` sans accord d'ajustement enregistré et relu. Son [résumé réel](../output/qa-import/cor-a-reprise-resultat-script.json) est séparé du résultat incomplet initial conservé. Les détails restent privés sous la nouvelle destination. Le résumé root conserve `sql_after_restart_verified=false` car le helper n'exécute aucun SQL ; le contrôle ultérieur et la synthèse ci-dessous apportent cette preuve distincte.

## Critères et limites

Clôture A uniquement si arrêt normal confirmé, copie complète vérifiée, configurations inchangées, retour du même service avec nouveau PID et nouvelle connexion SQL conforme. Restauration non testée, sauvegarde sur le même volume ; aucune garantie contre sa perte. Le défaut mysql.proc demeure à traiter dans B, avec son autorisation distincte. Aucun compte, schéma, migration, import, activation d'événements, changement de shell/droits ou configuration inclus.

**Décision validée :** ajustement SIGTERM normal de COR-XAMPP-A et reprise complète ainsi contrôlée. Exécution administrateur réalisée par l'utilisateur, puis vérification SQL et processus par Codex. B reste non autorisé.

## Commande exécutée — historique, ne pas relancer

Commande autorisée et exécutée une fois par l'utilisateur ; conserver comme preuve d'opération, sans relance :

```sh
sudo /usr/bin/python3 /private/tmp/homya-cor-a-reprise-20261007.py --apply
```

Saisir le mot de passe uniquement dans Terminal. Attendre le résumé JSON final et répondre « exécuté » ici, ou transmettre l'erreur/résumé expurgé sans secret. Le script recontrôle la cible et ses préconditions juste avant l'arrêt ; si le précontrôle SQL a plus de deux heures, il refuse avant interruption et devra être actualisé en lecture seule.

Un statut `COPIE_VERIFIEE_SQL_A_CONTROLER` signifie que copie et retour du service sont confirmés par le helper ; `sql_after_restart_verified` reste faux jusqu'au contrôle SQL distinct. Un statut `INCOMPLET` doit être inspecté, sans relance automatique. Les résultats et limites seront consignés après observation réelle, sans déclarer la sauvegarde terminée par anticipation.

## Résultat réel et clôture du 07/10/2026

| Contrôle / source | Résultat vérifié et limite |
|---|---|
| [Résultat du helper root](../output/qa-import/cor-a-reprise-resultat-script.json), terminé à 21:12:39 UTC | SIGTERM normal demandé, arrêt confirmé, copie complète vérifiée par source/copie/source et métadonnées, démarrage tenté, service prêt et PID présent ; aucune erreur d'opération/récupération. Aucune réparation |
| Destination privée | `/Applications/XAMPP/xamppfiles/homya-backups/20261007T211225Z-2fd4b2bb-aa11-4ac9-8e6c-fd94c7c81b0e` ; parent root/0700 revérifié, sur le même volume que les données |
| [Nouvelle connexion SQL](../output/qa-import/cor-a-reprise-controle-sql.json), ID 13, uptime 78 au contrôle | XAMPP 10.4.28/3307/socket/datadir exacts et écoute locale ; global/session 16 777 216, FK/CHECK=1, événements OFF, PID configuré exact |
| État HOMYA | homya_test présente, utf8mb4 / utf8mb4_bin, zéro table/vue ; homya_local absente. Aucun compte, migration ou import créé/exécuté dans A |
| [Processus et fichier PID](../output/qa-import/cor-a-reprise-controle-processus.json) | Nouveau processus XAMPP 65069/UID74, distinct de 18340 ; fichier PID revenu, mode 0660. Son contenu est protégé du compte courant : vérifié par le helper root à son retour, pas relu indépendamment ensuite |
| Configurations / périmètre | Trois empreintes de configuration inchangées et 3 868 fichiers témoins inchangés ; MySQL Homebrew PID1009 toujours actif. Aucun nouveau signal ou service modifié par Codex durant la vérification finale |

[Synthèse de vérification](../output/qa-import/cor-a-reprise-synthese-verification.json) : `SAUVEGARDE_ET_RETOUR_SQL_VERIFIES`. Le manifeste détaillé privé n'a pas été relu indépendamment par Codex ; la vérification physique repose sur le helper root dont l'empreinte et le résultat local ont été contrôlés. Aucun élargissement de permissions pour lire les fichiers protégés.

**Clôture A :** critères d'arrêt, copie, retour du service et SQL satisfaits dans ce périmètre. **Limites conservées :** restauration non testée, copie sur même volume, connexion Laravel non vérifiée et autres applications non certifiées. La sauvegarde conserve le défaut système initial ; aucune correction des catalogues n'a été exécutée.

**Suite distincte :** préparer COR-XAMPP-B avec contrôle des accès CLI, intégrité de la sauvegarde avant intervention et décision sur la recette de restauration ou sa limite ; présenter l'opération précise avant son autorisation. Aucun mysql_upgrade, compte, base supplémentaire, migration ou import autorisé par cette clôture.
