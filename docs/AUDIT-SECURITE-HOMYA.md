# HOMYA — audit et durcissement avant déploiement

Date : 10 octobre 2026. Domaine confirmé par l’utilisateur : `homya.eu`. Hébergement non renseigné. Périmètre autorisé : revue du code, corrections réversibles et vérifications locales ; aucune publication ni modification de base de données.

## Rapport fourni et qualification

Le rapport Herozion `021a4ed07c83` annonce 62 alertes (47 critiques, 8 élevées, 7 moyennes), mais fournit seulement cinq descriptions. Les 57 alertes masquées ne peuvent pas être qualifiées individuellement. Le nombre annoncé ne constitue pas une preuve de vulnérabilités exploitables.

| Alerte détaillée | Vérification | Conclusion dans le périmètre examiné |
| --- | --- | --- |
| SQL injection, `app/Services/Admin/Messages.php:97` | Insertions et filtres du Query Builder, paramètres liés, corps chiffré ; test de charge SQL littérale et de tentative de contournement du code d’accès sur SQLite isolé | Faux positif pour la ligne et le flux examinés ; aucune concaténation d’entrée utilisateur dans une requête SQL |
| Sortie LLM exécutée, `scripts/install_admin_management.py:38` | `subprocess.run` reçoit une liste d’arguments, sans shell ; SQL via entrée standard, plan et empreintes contrôlés | Faux positif : aucun flux LLM constaté |
| Même catégorie, `scripts/install_commerce_preview.py:29` | Arguments Git structurés ; pas de shell ni d’appel LLM | Faux positif pour le chemin cité |
| Même catégorie, `scripts/install_commerce_preview.py:53` | Lecture Git d’une référence locale via liste d’arguments ; empreintes et collisions vérifiées | Faux positif pour le chemin cité |
| Même catégorie, `scripts/install_local_optimization.py:59` | Ancien script présent dans la copie XAMPP : PID numérique, commande `ps` structurée, commandes Apache fixes et plan contrôlé | Faux positif pour le chemin cité ; script historique non exécuté durant cet audit |

Les installateurs locaux ne sont pas des points d’entrée HTTP et ne doivent pas être inclus dans le paquet de production. Les commandes promotionnelles proposées par le scan n’ont pas été exécutées et le projet n’a pas été transmis à un service d’analyse externe.

Les paramètres liés sont documentés par le [Query Builder Laravel](https://laravel.com/framework/docs/13.x/queries). Les identifiants SQL (tables, colonnes, tri) restent fixes ou choisis dans des listes côté serveur dans les chemins examinés.

## Menaces et corrections appliquées

Actifs : paramètres secrets, accès aux conversations et commandes, justificatifs et documents privés, catalogue, administration, sessions et données personnelles. Frontières : navigateur vers application, application vers stockage/SQL, outils locaux vers copie XAMPP, future configuration d’hébergement. Un visiteur distant ne doit pouvoir ni exécuter un fichier téléversé, ni lire des fichiers privés, ni obtenir des données de débogage ou des journaux contenant des secrets.

| Constat | Risque et confiance | Correction |
| --- | --- | --- |
| Absence de CSP générale et de politique des capacités sur la boutique | Moyen, défense supplémentaire contre une injection ; observation directe, pas de XSS exploitable démontrée | Scripts limités à la même origine et à un nonce aléatoire par requête ; JSON-LD autorisé avec ce nonce ; objets intégrés bloqués, formulaires et connexions limités à l’origine ; caméra, micro, géolocalisation et Payment Request désactivés |
| Configuration locale facilement réutilisable lors d’une publication | Élevé **si cette configuration est exposée** ; risque conditionnel, production actuelle non examinée | `ProductionGuard` refuse une production avec debug actif, URL incorrecte, clé invalide, sessions non protégées, serveur Vite de développement ou stockage privé dangereux ; contrôle de l’hôte `homya.eu` et de HTTPS avant les sessions et contrôleurs |
| Journaux Laravel standards pouvant inclure messages d’exception et paramètres SQL | Moyen, fuite possible en cas d’erreur ; absence de fuite réelle démontrée | En production, rapport d’exception limité à classe/fichier/ligne ; sans message brut, arguments de trace, requête ou données du client ; debug forcé désactivé aussi au rendu des exceptions |
| Route générique de fichiers privés inutile, et protections Apache dépendant de la configuration globale | Moyen, réduction de surface | Désactivation de la route générique de stockage ; livraison des documents par les contrôleurs autorisés existants ; refus des fichiers cachés, sources/configurations, sauvegardes et exécutables hors `index.php` ; listings désactivés indépendamment de la négociation de contenu |
| Répertoire parent du stockage privé en `0755` | Moyen pour un autre utilisateur local pouvant atteindre ce dossier ; permissions observées | Passage à `0700`, propriétaire conservé ; vérification des permissions et des liens du stockage dans le contrôle de production |
| Champs financiers absents de la liste globale d’exclusion des anciennes saisies | Faible, prévention ; formulaire carte actuel déjà désactivé | Exclusion du numéro de carte, titulaire, échéance, CVC/CVV et IBAN/BIC du flash de validation |

Le nonce CSP est ajouté au script JSON-LD existant sans modifier ses données. Les politiques plus strictes des documents privés et de l’administration sont conservées. Les styles inline restent autorisés pour la compatibilité de l’interface ; aucun script inline libre n’est autorisé. Une future intégration de paiement devra adapter et tester ces politiques. Voir la [documentation CSP de MDN](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy).

Les sessions deviennent chiffrées et les cookies Secure par défaut en production. Les cookies restent HttpOnly, SameSite Lax/Strict, limités à l’hôte et la sérialisation JSON est imposée par le contrôle. HSTS est envoyé sur HTTPS en production, sans préchargement ni extension implicite aux sous-domaines. Le fonctionnement HTTP local est conservé.

## Contrôles existants préservés

- `.env` source et copie servie en `0600`, sans affichage ni changement des valeurs ; seul `public/index.php` apparaît dans les fichiers publics sensibles inspectés.
- Refus HTTP des fichiers privés via le document root public et la protection du dossier Laravel complet accessible depuis XAMPP.
- Administration réservée au contexte local/testing et au pair loopback réel ; aucune ouverture de l’administration sur Internet.
- Autorisations de commandes, documents et conversations ; secrets et contenus privés chiffrés ; validation des formulaires, limitation de fréquence et CSRF existants.
- Images administrateur décodées et réencodées en WebP, chemins générés ; fichiers exécutables refusés ; justificatifs conservés hors du dossier public.
- Intégrations avec URL HTTPS fixes et sans suivi de redirection ; PDF sans PHP ni ressources distantes ; SQL métier paramétré et colonnes fixes dans les chemins examinés.
- Aucun prestataire carte actif, aucun rétablissement de Stripe, aucune saisie de carte activée. Les changements d’affichage précédents restent conservés.

## Vérifications et preuves

Suite complète réussie : **427 tests / 10 504 assertions**, avec fixtures et sockets Unix fictifs. Les 59 tests ciblés / 839 assertions ont également réussi ; Pint et les contrôles de syntaxe ont réussi. La première exécution complète était limitée par la mémoire du processus enfant Artisan, puis par les sockets Unix interdits dans le bac à sable ; exécution finale directe avec 512 Mo et permission pour ces sockets, réussie.

Les résultats définitifs des tests, de l’installation locale et de la recette HTTP/navigateur sont consignés dans `output/qa-security/`. Les tests utilisent des fixtures, SQLite en mémoire ou des mocks ; aucune migration ni commande réelle n’a été exécutée sur MariaDB.

Audits en ligne observés le 10/10/2026 : `composer audit --locked --format=json` ne retourne aucun avis ni paquet abandonné ; `npm audit --json` retourne zéro vulnérabilité connue. Laravel verrouillé : 13.35.0 ; Dompdf : 3.1.6. Ce résultat porte sur les bases d’avis de ces outils à cette date, pas sur toute vulnérabilité possible.

La commande `php artisan homya:security-audit --json` ne contacte ni SQL ni un service externe, ne modifie rien et n’affiche que des noms de contrôles et des booléens. Un échec dans l’environnement local est attendu : la configuration locale n’est pas une configuration de production. Même une réussite de cette commande ne valide pas l’hébergement, le TLS, les sauvegardes ou l’ensemble des fonctionnalités.

## Installation et recette locale terminées

Les 12 fichiers applicatifs/de configuration/documentation ont été synchronisés avec contrôle des empreintes et sauvegarde dans `/Applications/XAMPP/xamppfiles/homya-local/security-backup-20261010T142223Z`. Les permissions du stockage privé sont `0700` dans les deux copies. Les valeurs des `.env` et les fichiers métier protégés sont inchangés.

Le contrôle HTTP a montré que l’Apache dédié 8080 utilisait `AllowOverride None` : les nouvelles règles du `.htaccess` n’étaient donc pas appliquées sur ce point d’entrée. Correction vérifiée : chargement de `mod_rewrite` et inclusion explicite du `.htaccess` public dans la configuration du répertoire, en conservant `AllowOverride None`, `Require local` et l’écoute sur `127.0.0.1:8080`. Syntaxe Apache validée avant/après, sauvegarde `httpd-before.conf`, rechargement gracieux du seul Apache HOMYA avec les PID Apache/PHP-FPM conservés. Ni XAMPP 80 ni MariaDB n’ont été reconfigurés ou arrêtés.

Recette après correction : **22 chemins interdits HTTP 403** (11 sur chacun des accès 8080 et XAMPP 80), **7 pages de contrôle HTTP 200** avec CSP et protections attendues. Vérification navigateur : **32 affichages** (accueil, catalogue, panier, carte, assistance × FR/EN/DE × 1440/375 px, plus connexion administrateur aux deux largeurs), sans erreur JavaScript/CSP ni débordement. Le formulaire carte conserve ses neuf champs désactivés et sans nom de soumission. Captures de l’accueil et de la carte inspectées.

Preuves : `output/qa-security/junit.xml`, `targeted-junit.xml`, `dependencies.json`, `install-{plan,result}.json`, `apache-{plan,result}.json`, `http.json`, `browser-output-final.txt`, `protected-final.json`, `private-directory-permissions.json`, `readiness-local.json`. Captures sous `output/playwright/security-*.png`. Aucun compte rendu de lancement public, certificat réel ou paiement n’est déduit de ces tests locaux.

## Relecture du second scan `d11b41bc2a3c`

Le second rapport porte sur la copie XAMPP et annonce encore 62 alertes (47 critiques, 8 élevées, 7 moyennes), avec les mêmes cinq descriptions et emplacements que le premier rapport. Les protections précédentes sont présentes dans la copie servie. Les cinq conclusions du tableau restent applicables ; aucune nouvelle injection SQL ou exécution de sortie LLM n’est démontrée par ces descriptions. Les 57 alertes non détaillées restent à examiner ; le scanner n’a pas été relancé et aucun changement de son score n’est revendiqué.

La revue complémentaire a confirmé deux défauts conditionnels distincts du scan : une requête publique avec un environnement accidentellement `local` traversait le garde, et `https://homya.eu:8443` était accepté avec une configuration de production correcte. Deux tests isolés ont reproduit ces réponses HTTP 200 avant correction, sans contacter MariaDB ni un serveur public.

Corrections complémentaires :

- Hors production, accès réservé aux environnements `local`/`testing`, au pair loopback réel **et** à un nom d’hôte local. Un nom public derrière un proxy local ne suffit plus. Les requêtes publiques échouent en HTTP 503 avant les sessions et contrôleurs ; l’administration conserve HTTP 404. Les en-têtes de pair falsifiés ne donnent pas accès.
- En production, le port HTTPS doit également être 443 ; un port inattendu échoue en HTTP 400. L’environnement de production reste soumis à tous les contrôles précédents.
- Désactivation des pages de debug et expurgation des journaux pour les requêtes non locales même si le fichier d’environnement indique `local`. Le rendu couvre aussi les exceptions du framework déclenchées avant le garde. Le diagnostic local et CLI est conservé.
- Les hôtes synthétiques des fixtures ne sont autorisés que dans un véritable processus PHPUnit, en environnement `testing`, avec un pair loopback ; cette exception ne s’applique pas aux requêtes HTTP d’un serveur simplement configuré en `testing`.

Suite complète finale réussie : **432 tests / 10 524 assertions**. Les 66 tests ciblés de sécurité / 940 assertions et les 16 tests de navigation/contacts / 2 483 assertions ont réussi ; syntaxe PHP, Python et Pint vérifiés. La première suite complète a relevé trois attentes historiques dépendant de contacts absents, alors que les coordonnées publiques ont été renseignées entre-temps. Les trois classes de test concernées utilisent désormais explicitement une fixture sans contacts ; les tests dédiés des liens de contact continuent de couvrir leur configuration. Aucun contact ou lien du site n’a été retiré.

Installation locale complémentaire terminée : trois fichiers applicatifs et ce rapport, contrôlés par empreintes et sauvegardés dans `/Applications/XAMPP/xamppfiles/homya-local/security-rescan-backup-20261010T144958Z`. Aucun service ni paramètre d’environnement modifié. Recette réelle : 22 chemins privés refusés (HTTP 403), sept pages accessibles (HTTP 200) et trois rejets d’hôtes publics sur le serveur local (503/404). Dix affichages à 1440/375 px ont été vérifiés, dont le formulaire carte FR/EN/DE : aucun débordement, erreur JavaScript/CSP ou appel externe ; neuf champs toujours désactivés et sans nom de soumission. Les fichiers protégés sont inchangés. Les simulations de production ne constituent pas une vérification du certificat ou de l’hébergement public.

Les protections du stockage, les secrets, les requêtes métier, les dépendances verrouillées et le formulaire carte désactivé sont conservés. Les preuves de cette relecture sont sous `output/qa-security-rescan/` ; les résultats de la première recette restent archivés séparément.

## Troisième scan et revue indépendante — `4b085a9c4c50`

L’utilisateur a confirmé que le détail des 57 alertes est payant et demandé explicitement une revue avec agents, skills et workflows. Trois revues indépendantes en lecture seule ont couvert : accès/authentification et transitions des commandes ; entrées SQL/HTML, uploads, fichiers/PDF et destinations HTTP ; configuration, permissions, proxies et déploiement. Méthode Security/Quality, workflows Security/Scripts/Deployment ; tests négatifs locaux, SQLite mémoire, mocks, dossiers temporaires et fixtures en mémoire. Aucun test de production ou SQL XAMPP réel.

Herozion 2.0.14 a été retrouvé dans le cache npx existant, sans installation. Son export standard requiert le plan payant. Un nouveau scan documenté `--offline --output json` a été exécuté localement : identifiant `d2c4f8d8dda04a8f8259d6bb20b4d7c5`, 4 148 fichiers, 62 alertes et seulement cinq détails, 57 verrouillés ; score toujours 0/F. Les huit fichiers de sécurité contrôlés concordaient entre source et copie servie. Aucun abonnement, correctif payant, waiver, suppression d’alerte ni transmission du projet n’a été effectué. Le score de cet outil ne démontre pas l’exploitabilité de chacune de ses détections.

| Constat indépendant | Preuve et portée | Correction |
| --- | --- | --- |
| Journal de la copie XAMPP lisible par groupe/autres, dossier `storage/logs` en `0755` et parents traversables | Métadonnées uniquement ; contenu non consulté, présence de données sensibles non qualifiée. Risque moyen conditionnel à l’accès d’un autre compte OS. La source bénéficie aussi d’un parent Documents privé. | Dossier `0700`, journaux ordinaires existants `0600`, propriétaire conservé ; permissions `0600` des canaux single/daily/monthly. Nouveau contrôle de production du dossier privé non lié et des permissions configurées. Aucun journal supprimé. |
| Dossier de retours lié symboliquement suivi par le service | Probe isolé : déclaration chiffrée écrite puis relue dans la cible du lien. Risque faible, modification locale préalable nécessaire sous racine privée ; aucune création de lien par HTTP ou exploitation distante trouvée. | Contrôle des composants par `PrivateLocalFiles::path` avant sauvegarde/lecture ; dossier lié refusé avant écriture, lecture liée ou manquante masquée par 404. Même contrôle préventif sur la quarantaine des justificatifs avant exists/files/put. Archives, noms, quota et chiffrement conservés. |
| CSRF souvent contournée dans les tests PHPUnit par le framework | Lacune de recette, pas une CSRF exploitable démontrée ; middleware sans exclusion déjà présent. | Deux tests de régression exécutent le middleware réel hors environnement testing : 22 opérations sans jeton/avec jeton incorrect refusées en 419 avant contrôleur/SQL, sur commande/assistance/retours/admin/RIB/preuves/panier ; jeton correct atteint la validation métier. |

Les revues n’ont confirmé aucune injection SQL, XSS, SSRF ou contournement d’autorisation dans les flux examinés. Paramètres SQL, HTML échappé, PDF sans réseau/PHP/JS, images réencodées, justificatifs chiffrés ne confirmant jamais un paiement, propriété des commandes/documents et codes de conversation sont préservés. Ce résultat limité ne prouve pas l’absence de toute vulnérabilité.

Preuves indépendantes sous `output/qa-security-independent/` : accès 22 tests/559 assertions ; entrées 17 tests négatifs/184 assertions plus probe de défaut avant correction (1 test/3 assertions) ; relecture stockage après correction 11 tests/102 assertions ; cinq fixtures proxy avant et après, sans réseau. Les 28 tests ciblés du correctif /305 assertions passent, dont création effective des journaux fixes et datés en `0600` — aucun franchissement réel d’un jour/mois testé. Audits Composer verrouillé et npm réussis le 10/10/2026, aucun avis/paquet abandonné Composer ni vulnérabilité npm connue ; accès aux bases d’avis après échec DNS du bac à sable, aucune transmission de code ou de secrets.

Les preuves du scan, des tests et de l’installation de cette phase sont sous `output/qa-security-third-scan/`. Le JSON brut du scanner est privé (`0600`) et aucun contenu sensible de journal n’a été copié dans ces preuves. L’hébergeur, le certificat et la configuration du serveur frontal restent inconnus ; concurrence réelle MariaDB, limites distribuées et résistance à la charge ne sont pas déduites des fixtures.

## Conditions à satisfaire sur l’hébergement de homya.eu

1. Identifier l’hébergeur et la formule, puis vérifier PHP 8.3 minimum, extensions, MariaDB compatible, compte applicatif dédié, cron et accès de déploiement. Utiliser une pile maintenue ; ne pas publier le serveur XAMPP local.
2. Servir uniquement `<projet>/public` et refuser toute exécution PHP hors du contrôleur frontal. Vérifier que `.htaccess` est réellement appliqué sous Apache. Sous Nginx, reprendre explicitement les protections dans la configuration serveur : les fichiers `.htaccess` ne sont pas interprétés. La [documentation de déploiement Laravel](https://laravel.com/framework/docs/deployment) impose le document root public et recommande de désactiver le debug.
3. Configurer un certificat valide, rediriger HTTP vers HTTPS sur le serveur frontal, utiliser l’origine exacte `https://homya.eu`. Si `www` est souhaité, le rediriger vers cette origine au frontal. En cas de terminaison TLS par proxy, déclarer uniquement les adresses de proxies réellement approuvées ; ne pas faire confiance aux en-têtes envoyés par n’importe quel client.
4. Dans le `.env` **de production**, renseigner `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://homya.eu`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_DOMAIN=null`. Prévoir le stockage de sessions côté serveur et les répertoires accessibles uniquement au compte PHP autorisé. Préparer `storage/logs` en `0700`, ses journaux en `0600`, et le stockage privé en `0700`, avec le propriétaire PHP correct avant le contrôle de production. Protéger aussi les caches contenant la configuration/secrets lors de leur génération sur l’hébergement. Désactiver aussi `display_errors` de PHP.
5. Préparer une clé de chiffrement et les secrets hors du paquet public. **Ne pas régénérer une clé utilisée par des commandes ou documents existants** sans procédure de migration/rotation : ces données deviendraient illisibles. Prévoir sauvegarde chiffrée et restauration vérifiée des données et de la clé.
6. Construire les assets ; installer les dépendances de production avec le fichier lock, sans dépendances de développement. Exclure `.git`, `.env` local, `node_modules`, `output`, scripts et fixtures de test, anciens installateurs, sauvegardes SQL et accès CLI privés. Ne transférer des données privées historiques que dans un plan de migration séparé.
7. Le lecteur SQL du catalogue actuel est **volontairement limité à XAMPP/loopback**. Il n’a pas été affaibli pour le déploiement. Préparer et vérifier un accès de production dédié, ou un catalogue fichier validé. L’administration distante nécessite également un projet d’authentification distinct : elle reste fermée en production.
8. Exécuter le contrôle de sécurité dans l’environnement cible, puis la recette HTTPS : fichiers privés et exécutables interdits, sessions protégées, erreurs expurgées, autorisations, uploads, commandes, documents et restaurations. Vérifier séparément les prérequis métier, coordonnées, RIB et paiements avant toute ouverture commerciale.
9. Obtenir les descriptions complètes des 57 alertes masquées et les rapprocher du code réellement déployé. Aucun abonnement ou envoi externe n’est nécessairement requis : un export détaillé de l’outil utilisé suffit s’il est disponible.

Conclusion limitée au périmètre : corrections et défenses vérifiées localement, aucune garantie d’inviolabilité. Le lancement reste conditionné à la configuration et à la recette de l’hébergement, ainsi qu’à l’examen des alertes non détaillées.
