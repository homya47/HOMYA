# HOMYA — correction des tables système XAMPP

Version 0.1 — 7 octobre 2026 — **COR-XAMPP-A clôturée sous DECISION-023 ; COR-XAMPP-B exécutée et contrôlée sous DECISION-024, sans redémarrage. Première tentative A incomplète conservée.**

État actuel : [clôture B](COR-XAMPP-B-REPARATION.md), upgrade système code 0, mysql.proc21 et ROUTINES/EVENTS réussis dans une nouvelle connexion phpMyAdmin. Restauration toujours non testée, limite explicitement acceptée dans Terminal ; les inconnues et demandes d'autorisation des sections originales ci-dessous sont historiques. Aucun schéma supplémentaire, compte, migration ou import pendant B ; reprise OP-02 distincte.

Préparation B historique : [réparation système v0.2](COR-XAMPP-B-REPARATION.md). Le SELECT CLI administratif avait réussi sur la cible exacte, sans authentification applicative. La proposition incluait un correctif unique de la branche root macOS du lanceur et un redémarrage seulement si EVENTS1577 persistait après upgrade réussi. Son consentement spécifique a depuis été reçu et B exécutée ; le redémarrage conditionnel n'a pas été nécessaire.

Actualisation de statut uniquement : le plan exact soumis est conservé dans `/private/tmp/homya-cor-a-accord-q5rbctin/PLAN-CORRECTION-XAMPP-SYSTEME.md` (SHA-256 `6cf65423a58f33613d65cc449c46256e0afd02a2e744527e8e203c2078d5b87b`). Les dispositions techniques ci-dessous constituent cette proposition initiale. Voir les [preuves de reprise A](COR-XAMPP-A-REPRISE.md) pour l'ajustement explicitement autorisé, la sauvegarde réelle et le retour SQL ; la réparation B conserve ses prérequis et son autorisation propres.

## 1. Besoin, sources et état réel

[OP-02](OP-02-CREATION-BASES-VIDES.md), autorisé dans DECISION-021 du [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), reste partiel : homya_test créée, homya_local absente. L'accord OP-02 ne couvre pas la maintenance de l'instance XAMPP. Cette fiche est un plan d'opération, pas un second registre général ; les accords futurs seront enregistrés et relus dans le registre central avant exécution.

Le présent message utilisateur demande de **préparer** la correction avec sauvegarde et autorisation distincte. Un SELECT supplémentaire de métadonnées a été soumis dans phpMyAdmin ; aucune nouvelle écriture SQL, sauvegarde, réparation ou commande de service.

| Observation sourcée | Portée / statut |
|---|---|
| MariaDB 10.4.28, port 3307, socket/datadir XAMPP exacts, écoute 127.0.0.1, paquet 16 Mio | Cible confirmée par le [diagnostic SQL](../output/qa-import/cor-xampp-diagnostic-lecture-seule.json) |
| mysql.proc comporte 20 colonnes ; erreur précédente 1558 en attend 21 | Incompatibilité système confirmée ; cause historique exacte inconnue |
| mysql.event comporte 22 colonnes ; event_scheduler=OFF ; erreur précédente EVENTS 1577 | Observé ; cause et correction de l'erreur EVENTS à vérifier, aucun passage à ON proposé |
| 71 tables InnoDB et 27 tables non InnoDB dans les schémas persistants visibles | Inventaire agrégé ; aucun contenu métier d'autres projets extrait. Une sauvegarde transactionnelle seule ne couvre pas de façon cohérente tous les moteurs |
| homya_test : zéro table/vue ; COUNT ciblé dans mysql.proc et mysql.event : zéro entrée pour homya_test ; homya_local absente | Comptages directs réussis, sans lecture des corps de routines ou d'événements. Ils ne prouvent pas le fonctionnement d'information_schema.ROUTINES/EVENTS |
| innodb_fast_shutdown=1 ; innodb_file_per_table=1 | Observé ; aucun réglage modifié |
| mysql_upgrade installé : Ver 2.0, Distrib 10.4.28 ; option upgrade-system-tables disponible | [Aide locale](../output/qa-import/cor-xampp-mysql_upgrade-aide.txt) ; aide obtenue avec code 1 et stderr vide, sans connexion SQL ; pas une exécution d'upgrade |
| mysqldump installé : Ver 10.19, Distrib 10.4.28 | [Aide locale](../output/qa-import/cor-xampp-mysqldump-aide.txt), code 0 ; aucun dump exécuté |
| Environ 66,36 Go disponibles sur le volume | Observation variable ; taille complète du datadir **inconnue**, du échoue sur des dossiers protégés ; valeur partielle non retenue pour dimensionner la copie |
| /Applications/XAMPP/xamppfiles/homya-backups absent | Emplacement proposé, hors datadir, hors dépôt et hors htdocs ; aucun dossier de sauvegarde créé |

Preuves complémentaires : [préparation environnement](../output/qa-import/cor-xampp-preparation-environnement.json), [capture du diagnostic](../output/qa-import/cor-xampp-diagnostic.png). Une session phpMyAdmin disponible ne prouve ni authentification CLI ni accès sudo. Aucun mot de passe demandé ou lu.

## 2. Recherche officielle et interprétation

Questions vérifiées : quelle opération correspond à 1558 ; comment borner l'upgrade aux tables système ; quelle sauvegarde préservera aussi les tables non transactionnelles et les définitions système actuellement défaillantes ?

| Source officielle — consultation 07/10/2026 | Portée et fait publié |
|---|---|
| [MariaDB — Error 1558](https://mariadb.com/docs/server/reference/error-codes/mariadb-error-codes-1500-to-1599/e1558) | Une structure système non alignée sur la version peut résulter d'une mise à niveau incomplète ; l'utilitaire d'upgrade est une piste de correction |
| [MariaDB — mariadb-upgrade](https://mariadb.com/docs/server/clients-and-utilities/deployment-tools/mariadb-upgrade) | Sauvegarde préalable recommandée ; upgrade-system-tables limite aux tables mysql ; version-check, socket et fichier d'options permettent de préciser la cible et les accès |
| [MariaDB — Backups Overview for SQL Server Users, Cold Backups](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/migrating-to-mariadb/migrating-to-mariadb-from-sql-server/mariadb-backups-overview-for-sql-server-users) | Arrêter le serveur puis copier tous ses fichiers constitue une sauvegarde physique à l'arrêt, au prix d'une indisponibilité |

**Interprétation / recommandation :** préférer ici une copie physique complète à l'arrêt, avec métadonnées conservées, avant intervention. Elle évite de dépendre de l'export logique des routines/événements qui échoue actuellement et couvre les moteurs observés. Une copie de fichiers pendant que MariaDB fonctionne, ou un dump partiel ignoré avec --force, ne sera pas présenté comme sauvegarde complète.

La correction ciblée ne vaut pas mise à niveau complète de toutes les bases historiques. Leur compatibilité générale reste non évaluée. La documentation courante comporte des indications pour d'autres versions ; l'aide et le binaire réellement installés restent la preuve des options locales. Aucun téléchargement, changement de version MariaDB ou remplacement de XAMPP proposé.

## 3. COR-XAMPP-A — sauvegarde complète, première autorisation à demander

**Périmètre à autoriser :** accès administrateur local pour inventaire, arrêt propre de **MariaDB XAMPP seulement**, copie privée complète, contrôles d'intégrité, puis redémarrage du même service sans réparation. L'indisponibilité concerne toutes les bases de cette instance locale, pas seulement HOMYA. Apache, MySQL Homebrew/3306 et les fichiers applicatifs restent hors intervention.

1. Enregistrer et relire l'accord dans le registre central. Vérifier de nouveau la cible SQL et le processus associé au datadir/socket XAMPP ; refuser tout PID non concordant. Vérifier les droits administrateur sans élargir les permissions des sources.
2. Avant arrêt, inventorier avec les droits nécessaires les fichiers, leur taille et les destinations de liens symboliques/tablespaces/journaux configurés. Si des données persistantes dépendent d'un chemin extérieur au datadir non couvert, arrêter et soumettre l'extension exacte avant copie. Relever l'espace libre et exiger la place pour la copie mesurée plus une marge de travail ; ne pas prendre du partiel pour une mesure complète.
3. Préparer une destination neuve `/Applications/XAMPP/xamppfiles/homya-backups/<UTC>-<UUID>/`, parents privés mode 0700, jamais réutilisée. Sauvegarder aussi `etc/my.cnf`, `properties.ini`, le lanceur `xampp` et les informations de version/empreintes utiles, sans recopier `.env` ou exporter une authentification via le chat.
4. Informer du début de l'interruption ; demander aux autres applications locales de rester sans écritures pendant la fenêtre. Utiliser uniquement la commande de service ci-dessous. Vérifier l'arrêt effectif du processus, l'absence de réapparition et la fermeture de 3307 avant de copier. Aucun kill forcé, aucune suppression de PID manuelle en cas d'échec.
5. Copier l'intégralité du datadir à l'arrêt avec propriétaires, modes, liens, ACL/attributs conservés. Le dossier contient potentiellement données d'autres projets et tables d'authentification : accès privé uniquement, aucun Git, htdocs, cloud ou transfert externe. Inclure les fichiers système et journaux persistants ; ne pas sélectionner seulement le sous-dossier homya_test.
6. Toujours à l'arrêt, comparer inventaire, tailles, empreintes SHA-256, liens et métadonnées entre source et copie ; documenter exclusions uniquement pour des objets transitoires absents après arrêt, jamais pour des fichiers de données. Conserver manifeste détaillé et journaux dans le dossier privé ; ne publier que le résultat expurgé et les comptages.
7. Redémarrer le même service, même configuration, et vérifier via une nouvelle connexion version/port/socket/datadir/paquet, présence de homya_test et absence de homya_local. Un simple retour « ok » du lanceur ne suffit pas : son démarrage est asynchrone.
8. En cas de copie incomplète : conserver le dossier signalé INCOMPLET, aucune réparation ; rétablir le service d'origine s'il a été arrêté proprement, puis signaler l'échec. Aucun effacement automatique de sauvegarde ou restauration sur l'instance.

**Commandes de service prévues — non exécutées :**

```sh
sudo /Applications/XAMPP/xamppfiles/xampp stopmysql
```

```sh
sudo /Applications/XAMPP/xamppfiles/xampp startmysql
```

Les branches et chemins de ces commandes ont été lus dans le lanceur installé et mysql.server. Leur succès réel et le temps d'interruption ne sont pas encore connus. Toute saisie sudo reste dans Terminal, jamais dans le chat.

**Critères de sortie A :** arrêt propre attesté, copie complète et métadonnées/empreintes concordantes, destination protégée, service d'origine revenu et cible vérifiée. La copie sur le même volume constitue une protection avant intervention, pas une sauvegarde indépendante contre la perte du disque. **La restauration reste non testée** ; sa recette isolée fera l'objet de son propre périmètre/cible avant d'affirmer qu'elle fonctionne. A seul n'autorise ni réparation ni restauration ni création de homya_local.

## 4. COR-XAMPP-B — réparation système, autorisation ultérieure

**Dépendances :** A réussie et sauvegarde toujours vérifiable, inventaire privé disponible, décision explicite sur la recette de restauration et ses limites, accès CLI administratif local vérifié par SELECT de cible. Les droits et secrets CLI sont **inconnus** : ne pas supposer un compte root sans mot de passe ni transférer la session phpMyAdmin à Laravel. Préparer un fichier d'options local exclusif mode 0600 si nécessaire, sans afficher son contenu. Aucun compte/mot de passe créé ou modifié pour contourner l'accès.

Commande candidate avec emplacement d'authentification encore à renseigner localement — **ne pas exécuter ; B n'est pas encore soumis à autorisation finale** :

```sh
/Applications/XAMPP/xamppfiles/bin/mysql_upgrade \
  --defaults-file='<fichier-local-prive-verifie>' \
  --protocol=socket \
  --socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock \
  --upgrade-system-tables \
  --force \
  --version-check \
  --skip-write-binlog \
  --skip-verbose
```

L'aide installée annonce déjà force=TRUE ; l'option explicite ne doit pas être assimilée à --force de mysqldump, qui ignorerait des erreurs. Le fichier d'options est le premier argument ; il sera limité aux accès attendus, avec revue privée sans sortie de secret. Pas de double --force, de --skip-version-check, de débogage ou de verbosité affichant les arguments d'authentification. L'invocation finale et ses empreintes seront présentées après A et contrôle des accès.

**Impact B à approuver :** DDL et conversions des tables système mysql, y compris structures de privilèges, et écriture éventuelle du marqueur mysql_upgrade_info. Les habilitations d'autres applications peuvent être affectées par une conversion système : ce n'est pas une opération limitée à HOMYA. L'option choisie n'est pas une permission pour l'upgrade complet des tables métier, l'activation d'événements, un compte dédié, un import ou une migration Laravel.

Code retour non nul, erreur SQL, cible/version divergente ou modification hors portée : arrêt, preuve expurgée, aucune relance forcée, correction manuelle ou restauration automatique. Une opération DDL peut être partielle ; aucun rollback transactionnel promis. Aucun redémarrage B inclus par anticipation : si nécessaire, son besoin et son effet sur toutes les bases devront être ajoutés explicitement à la proposition B.

## 5. Recette après B et reprise

- Le diagnostic mysql.proc doit constater 21 colonnes et le SELECT ciblé information_schema.ROUTINES doit réussir ; ne pas considérer le seul code retour de l'utilitaire comme résultat final.
- Le SELECT ciblé information_schema.EVENTS doit réussir. Conserver event_scheduler=OFF ; en cas d'erreur persistante, investiguer, sans passer à ON pour masquer le problème ni déclencher des événements existants.
- Vérifier phpMyAdmin, accès administratif, cible XAMPP, paquet 16 Mio, FK/CHECK et comptes/droits selon l'inventaire autorisé, sans afficher de secrets. Ne pas certifier les autres applications sans leurs contrôles propres.
- Recontrôler homya_test existante, charset/collation et zéro objet. Reprendre ensuite OP-02 sous DECISION-021, avec contrôle d'absence de homya_local juste avant sa création ; ne pas recréer homya_test ni réutiliser une collision.
- Aucun compte OP-03, migration, import, bascule ou paiement inclus. Une restauration destructive de l'instance exige son plan et sa confirmation propres ; la sauvegarde antérieure contient aussi le défaut système initial et ne constitue pas une réparation en soi.

## 6. Arbitrage immédiat

**COR-XAMPP-A réalisée :** copie privée de toutes les bases XAMPP, arrêt normal et retour du même service vérifiés après l'ajustement SIGTERM consigné dans DECISION-023. Nouvelle connexion SQL ID13 et fichier PID présent ; destination et limites dans la [fiche de reprise](COR-XAMPP-A-REPRISE.md). Ne pas relancer les commandes historiques. COR-XAMPP-B reste non autorisé.

**Décisions ultérieures :** recette de restauration isolée ou acceptation explicite de sa limite, accès CLI, commande finale et autorisation B. Aucun accord documentaire ne vaut exécution ; les confirmations sont consignées dans le seul registre central.

## 7. Diagnostic reçu et ajustement proposé après la tentative

Le diagnostic administrateur de 20:54:25 UTC confirme un arrêt annoncé « ok » sans arrêt réel, PID absent et aucune copie datadir/manifeste. La méthode `su` du lanceur est incompatible avec les options et le shell de compte macOS observés. La [reprise de A par SIGTERM normal](COR-XAMPP-A-REPRISE.md), avec garde-fous et helper testé en isolation, est autorisée dans DECISION-023 après confirmation « j autorise » et relecture du registre. Le plan initial d'arrêt par lanceur reste conservé ; seule cette méthode est remplacée explicitement. Reprise terminée à 21:12:39 UTC, copie complète vérifiée par le helper root puis retour SQL contrôlé ; restauration non testée. Aucune réparation B, création ou migration effectuée.
