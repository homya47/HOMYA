# HOMYA — diagnostic XAMPP / phpMyAdmin

Date de consultation et de contrôle : 7 octobre 2026.
Périmètre : accès local à phpMyAdmin et démarrage de MariaDB XAMPP. Aucun import, création de base HOMYA, migration, changement de mot de passe ou déploiement inclus.

## Observations de l'environnement réel

- L'adresse fournie par l'utilisateur est `http://127.0.0.1/phpmyadmin/`.
- Le contrôle HTTP hors sandbox renvoie 200 ; la page observée dans le navigateur affiche « Connexion impossible : paramètres incorrects » et `HY000/2002: No such file or directory`.
- Apache actif est le binaire `/Applications/XAMPP/xamppfiles/bin/httpd`. Sa configuration passe le contrôle de syntaxe.
- La directive phpMyAdmin `Require local` est présente ; l'accès HTTP local fonctionne. Il n'existe donc pas de preuve d'un refus HTTP 403 dans le scénario reproduit.
- PHP XAMPP 8.2.4 configure `mysqli.default_socket` sur `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`. Ce socket est absent.
- La configuration phpMyAdmin sélectionne `localhost`, qui utilise un socket. Aucun secret n'a été reproduit.
- Le processus MySQL Homebrew `/opt/homebrew/opt/mysql/bin/mysqld` écoute sur 3306. Aucun processus MariaDB XAMPP n'est observé.
- `etc/my.cnf` et `properties.ini` sélectionnent 3306, tandis que la fonction `startMySQL` du fichier `xampp` teste 3308 : paramètres incohérents.
- Le journal d'erreurs MariaDB n'est pas lisible avec les permissions du compte courant, y compris hors sandbox. Le motif exact du dernier échec de démarrage reste à vérifier avec les permissions administrateur.

## Interprétation et recommandation

Le blocage reproduit est la connexion au socket MariaDB absent. Une erreur de mot de passe n'est pas démontrée. Le conflit entre le port configuré 3306 et MySQL Homebrew constitue un obstacle au démarrage normal de XAMPP sur ce port ; le journal de démarrage doit confirmer le résultat après correction.

Recommandation : isoler MariaDB XAMPP sur 3307 en écoute `127.0.0.1`, aligner le gestionnaire et le test de port du lanceur, conserver le socket existant et la restriction Apache `Require local`. Aucun élargissement d'accès ou changement d'authentification nécessaire au plan actuel.

## Correction préparée et appliquée

Script temporaire : `/private/tmp/homya-xampp-repair-20261007.py`.

La simulation en lecture seule a vérifié les empreintes des fichiers inspectés et produit les modifications suivantes :

1. `etc/my.cnf` : ports client et serveur de 3306 vers 3307 ; écoute serveur limitée à `127.0.0.1`.
2. `properties.ini` : `mysql_port=3307`.
3. `xampp` : test du port MariaDB aligné de 3308 vers 3307.

L'application exige le compte administrateur, refuse une configuration ayant changé depuis l'inspection ou un socket déjà actif, vérifie la disponibilité de 3307 et sauvegarde les trois fichiers avant écriture. Elle vérifie les options et la syntaxe du lanceur, puis démarre uniquement MariaDB XAMPP et attend son socket pendant au maximum 20 secondes.

La première tentative via `sudo -n` a été refusée par macOS : authentification administrateur nécessaire. Aucun fichier XAMPP ni service n'avait été modifié par cette tentative. L'utilisateur a ensuite exécuté, avec authentification exclusivement dans son Terminal :

```sh
sudo /usr/bin/python3 /private/tmp/homya-xampp-repair-20261007.py --apply
```

L'utilisateur a confirmé l'exécution et les sauvegardes vérifiées dans `/Applications/XAMPP/xamppfiles/etc/homya-backup-20261007-sn5vul8w`. Les trois configurations modifiées et le socket sont relus après l'exécution. Le script a terminé avec succès. Ne pas le relancer : ses contrôles d'empreinte refusent désormais la configuration modifiée. Toute intervention ultérieure demande une nouvelle inspection.

## Sources primaires consultées

Toutes consultées le 7 octobre 2026. Les sources publiées décrivent les produits ; elles ne prouvent pas l'état de cette machine.

| Titre et URL | Portée | Fait publié étayé |
|---|---|---|
| [XAMPP FAQs for Mac OS X — Apache Friends](https://www.apachefriends.org/faq_osx.html) | Installation native XAMPP macOS, services, accès et fichiers de configuration | L'interface de gestion est `manager-osx` ; les services sont démarrés séparément ; phpMyAdmin est accessible localement ; `my.cnf` est le fichier de configuration MariaDB/MySQL indiqué. |
| [Apache Module mod_authz_host — Apache HTTP Server 2.4](https://httpd.apache.org/docs/2.4/mod/mod_authz_host.html#reqlocal) | Autorisation HTTP, directive `Require local` | Cette directive autorise notamment les connexions provenant de 127.0.0.0/8 et ::1. Elle n'impose pas de modifier les autorisations pour une connexion locale. |
| [Configuration — phpMyAdmin](https://docs.phpmyadmin.net/en/latest/config.html#cfg_Servers_socket) | Paramètres de connexion ; documentation courante 5.2.4-dev affichée, et non preuve de version locale | `localhost` sélectionne la connexion socket ; le chemin est configurable ; le numéro de port est ignoré pour cette connexion. Les pages de documentation stable/versionnée tentées n'ont pas été accessibles via l'outil web. Le chemin utilisé localement est confirmé séparément par le PHP installé. |
| [Configuration — documentation officielle embarquée phpMyAdmin 5.2.1](http://127.0.0.1/phpmyadmin/doc/html/config.html#cfg_Servers_socket) | Documentation fournie avec l'installation réelle, lue dans `/Applications/XAMPP/xamppfiles/phpmyadmin/doc/html/config.html` | Confirme les mêmes règles `localhost`, socket et port pour la version installée ; les paramètres restent vérifiés séparément sur le serveur actif. |
| [What to Do if MariaDB Doesn't Start — MariaDB](https://mariadb.com/docs/server/server-management/starting-and-stopping-mariadb/what-to-do-if-mariadb-doesnt-start) | Diagnostic du démarrage et fichiers d'options | Le journal d'erreurs et les options réellement chargées sont les preuves à examiner lors d'un échec de démarrage. |
| [Server System Variables — MariaDB](https://mariadb.com/docs/server/server-management/variables-and-modes/server-system-variables) | Paramètres `port`, `socket` et `bind_address`, différences de version à respecter | Le port et l'adresse d'écoute sont configurables ; les options peuvent être spécifiées dans `my.cnf`. Leur chargement effectif reste à contrôler sur l'installation locale après application. |

## Résultats finaux vérifiés

- Connexion SQL ciblée via le socket XAMPP : `10.4.28-MariaDB`, `@@port=3307`, `@@socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, répertoire de données `/Applications/XAMPP/xamppfiles/var/mysql/`, `@@bind_address=127.0.0.1`. Requête SELECT uniquement, aucune lecture de données métier et aucun accès affiché.
- phpMyAdmin 5.2.1 : interface d'administration chargée après rechargement réel du navigateur ; MariaDB et sa version visibles, aucune erreur de connexion. HTTP 200 confirmé.
- Apache XAMPP et MariaDB XAMPP : processus actifs avec leurs chemins de binaires XAMPP. Le processus MySQL Homebrew initial reste actif.
- Restriction Apache `Require local` inchangée ; le lanceur modifié passe `/bin/bash -n`.
- Le serveur HOMYA était arrêté ; il a été relancé sur `127.0.0.1:8000` et sa page française est vérifiée dans le navigateur.
- La cause exacte inscrite dans le journal MariaDB historique n'a pas été lue faute de permissions. La correction et le fonctionnement actuel sont confirmés indépendamment par les contrôles ci-dessus.

La connexion Laravel à une base HOMYA reste une étape séparée : aucun schéma métier ni accès applicatif ne sont validés par l'ouverture de phpMyAdmin.
