# HOMYA

Accès local installé dans XAMPP : [ouvrir HOMYA](http://127.0.0.1:8080/fr). Le dossier servi est `/Applications/XAMPP/xamppfiles/htdocs/HOMYA/public`, avec PHP8.5 et catalogue SQL XAMPP. [Démarrage et contrôles réels](docs/ACCES-LOCAL-XAMPP.md). Le dossier de travail original et l'ancienne copie sont conservés. Les commandes locales sans paiement immédiat sont ouvertes ; le formulaire carte reste en attente de configuration ; le RIB reste à renseigner.

Démarrage automatique installé le 10/10/2026 : MariaDB XAMPP au démarrage du Mac, puis HOMYA à l'ouverture de la session `akm`. Aucune commande manuelle nécessaire dans le fonctionnement prévu. [Vérifications et désactivation](docs/ACCES-LOCAL-XAMPP.md#démarrage-automatique--installé-le-10102026) ; prochain redémarrage complet du Mac non encore observé.

Projet Laravel 13 de catalogue local FR/EN/DE, en euros, destiné aux marchés France, Belgique et Allemagne. La consultation est disponible : accueil, navigation, recherche, filtres, tri, pagination, fiches, caractéristiques et galeries. Le panier invité, les commandes, le formulaire de carte en attente de configuration et la page de virement avec réservation de 72 heures sont implémentés. L’inventaire local contient 1 000 unités déclarées par produit, partagées entre les options. Les paiements restent désactivés : prestataire carte non choisi, RIB professionnel et prérequis commerciaux à configurer. Le [dashboard administrateur local](http://127.0.0.1:8080/admin) est protégé et permet de consulter commandes, inventaire et retours. Voir [les accès et protections](docs/ADMINISTRATION-HOMYA.md).

## Démarrage sur cette machine

Utiliser PHP 8.3 minimum. Le PHP 8.5 Homebrew est installé ici ; le PHP 8.2 fourni avec XAMPP ne peut pas exécuter cette version de Laravel. MariaDB reste le service **XAMPP sur le port 3307**, distinct du MySQL Homebrew sur 3306.

Avec les dépendances déjà installées :

```sh
/opt/homebrew/bin/php --version
npm run build
/opt/homebrew/bin/php artisan serve --host=127.0.0.1 --port=8000
```

Ouvrir `http://127.0.0.1:8000/fr` et démarrer MariaDB dans XAMPP pour la source SQL. Si le port 8000 est occupé, choisir un autre port local ; ne pas arrêter un service existant pour le libérer. L’instance Apache dédiée sur 8080 sert également cette application avec PHP-FPM 8.5 ; le PHP 8.2 intégré à Apache XAMPP ne doit pas exécuter Laravel 13.

Sur un nouveau poste, les dépendances sont installables depuis les fichiers de verrouillage avec `composer install` et `npm ci --ignore-scripts`. Cela ne provisionne ni les données ni les accès locaux privés. Ne pas réutiliser le script `composer setup` pour une installation existante : il régénère la clé applicative. Créer `.env` depuis `.env.example` et générer une clé uniquement pour une nouvelle installation sans configuration existante. Ne jamais afficher ou versionner les secrets.

## Source du catalogue et connexion dédiée

Le serveur choisit la source avec `HOMYA_CATALOGUE_SOURCE=files` ou `sql` dans `.env`. Le défaut fourni par `.env.example` est `files`. Le navigateur ne peut pas modifier ce choix. Le résultat et la source réellement activée sont consignés dans [le dossier OP-10](docs/OP-10-BASCULE-LOCALE-PERIMETRE.md) ; ne pas confondre une recette de candidat SQL avec l’activation du site.

L’import OP-09 dans `homya_local` et la recette native OP-10 ont été vérifiés : **3 709 produits, 65 catégories, 23 878 traductions**, avec parité des cartes et fiches FR/EN/DE. Le lecteur HTTP utilise un compte technique dédié aux seules lectures des quatre tables d’archive, un accès privé protégé et un profil du lot vérifié. Les variables `DB_*` générales ne remplacent pas cet accès. Une copie du dépôt seule ne suffit donc pas à rétablir la connexion SQL sur un autre poste.

La garde actuelle accepte uniquement l’environnement `local`, les requêtes locales sans en-têtes de proxy, une configuration non mise en cache, PHP compatible, PDO MySQL, POSIX et les fichiers privés aux permissions attendues. Si ces conditions ou la connexion échouent, le catalogue répond **503** avec un message localisé, sans afficher de détails privés ni basculer automatiquement vers les fichiers. Un signal fixe `HOMYA_CATALOGUE_SOURCE_UNAVAILABLE` est envoyé au journal configuré, sans exception, SQL ou données de requête.

Ne pas exécuter `config:cache` ou `optimize` pour ce lecteur local. Ne pas lancer de migration, d’import, de restauration ou de changement de droits comme simple dépannage. Le diagnostic général `homya:db-check` ne prouve pas le fonctionnement du lecteur HTTP dédié ; utiliser la recette et ses preuves OP-10.

## Contrôles hors base

```sh
/opt/homebrew/bin/php vendor/bin/phpunit
/opt/homebrew/bin/php vendor/bin/pint --test
npm run build
```

La configuration PHPUnit impose la source `files` même si le poste utilise SQL. La suite Unit/Feature utilise des fixtures et doubles ; elle ne lance pas la suite `tests/MariaDB` ; les scénarios commerce créent leurs seules tables dans une base SQLite en mémoire, sans migration MariaDB et ne constitue pas une preuve de connexion réelle. Les recettes MariaDB et les scripts OP nécessitent leur périmètre et autorisation propres. `composer test` inclut une purge de cache de configuration ; préférer la commande PHPUnit directe ci-dessus pour ces contrôles.

Les réponses Laravel portent les en-têtes `nosniff`, `SAMEORIGIN` et `strict-origin-when-cross-origin`. Ce contrôle limité ne constitue pas une certification de sécurité ; les ressources statiques dépendent aussi de la configuration du serveur qui les sert.

## Contenus et préparation à la publication

Les originaux et la provenance restent conservés hors des vues publiques. Les photos insuffisantes sont masquées ; les copies sur fond blanc et les vues authentiques autorisées rétablissent une photo affichable pour chaque fiche selon les contrôles documentés. Ces dimensions techniques ne prouvent ni une qualité native suffisante ni une acceptation Google.

Les traductions sont préparées hors ligne. La parité technique ne garantit pas leur qualité éditoriale : une relecture métier reste nécessaire, notamment pour les mots étrangers résiduels. L’utilisateur a confirmé les prix affichés TTC et déclaré le stock initial de 1 000 unités par produit, avec même prix et stock commun pour les options. Les retours gratuits sous 14 jours sont confirmés. Le régime détaillé de TVA, l’adresse et la logistique des retours ainsi que les informations légales restent à compléter. Les signalements du catalogue sont conservés pour revue.

**La mise en ligne commerciale reste conditionnée** aux informations de l’entreprise, règles de vente, données fournisseurs et prestataire de paiement. Hostinger est envisagé ; la formule et le domaine ne sont pas fournis. La garde SQL actuelle est propre à ce poste XAMPP et ne doit pas être désactivée pour la production : l’adaptation et la vérification de l’environnement distant restent à réaliser. Aucun déploiement, compte externe ou paiement réel n’est activé par ces fichiers.

## Documents utiles

- [Cadrage](docs/CADRAGE-HOMYA.md), [cahier des charges](docs/CAHIER-DES-CHARGES-HOMYA.md), [architecture](docs/ARCHITECTURE-ET-PLAN-HOMYA.md), [UX/UI](docs/UX-UI-HOMYA.md)
- [Environnement et vérifications locales](docs/ENVIRONNEMENT-LOCAL.md)
- [Administration locale](docs/ADMINISTRATION-HOMYA.md)
- [Panier, commandes, formulaire carte, virement et installation du stock](docs/PARCOURS-ACHAT-HOMYA.md)
- [SEO, sitemap, données structurées et préparation Merchant Center](docs/SEO-GOOGLE-HOMYA.md)
- [Sélection produit, messages d’achat et retours gratuits14jours](docs/SELECTION-PRODUIT-HOMYA.md)
- [Catalogue et pagination](docs/INCREMENT-CATALOGUE-CORRECTIONS.md), [identité publique](docs/INCREMENT-IDENTITE-HOMYA.md), [langues et images](docs/INCREMENT-LANGUES-IMAGES.md)
- [Photos des produits](docs/INCREMENT-PHOTOS-PRODUITS.md), [droits et qualité des médias](docs/REVUE-DROITS-QUALITE-MEDIAS.md)
- [Anomalies des données](docs/REVUE-DONNEES-CATALOGUE.md), [plan d’import](docs/PLAN-IMPORT-XAMPP.md), [modèle logique](docs/MODELE-LOGIQUE-ARCHIVE-HOMYA.md)
- [Import local OP-09](docs/OP-09-IMPORT-LOCAL-PREPARATION.md), [adaptateurs OP-10](docs/OP-10-LECTURE-SQL-PREPARATION.md), [recette SQL native OP-10](docs/OP-10-RECETTE-NATIVE-LECTURE-SEULE.md), [bascule locale](docs/OP-10-BASCULE-LOCALE-PERIMETRE.md)

Les décisions validées sont conservées dans le registre central Specification, filtré sur HOMYA. Les documents OP conservent aussi les états historiques ; consulter leurs clôtures pour l’état final réellement vérifié.


## Optimisations locales et récupération

Le code possède désormais un historique Git local. Les dépendances, les secrets, `output/`, `public/media/` et les originaux `storage/app/catalogue/` restent hors Git. Ces fichiers sont conservés sur disque ; un clone seul ne restaure donc pas le catalogue, ses médias ou l’accès SQL. Les archives privées et sauvegardes existantes restent nécessaires.

`pint.json` exclut les preuves et vues compilées historiques des contrôles de style. Le tri du catalogue normalise chaque nom une seule fois, sans modifier les filtres, prix ou règles de lecture SQL. Le profilage hors base se lance avec `php scripts/profile_catalogue.php` ; les empreintes des 18 collections FR/EN/DE avant et après sont identiques.

Les originaux restent inchangés. Des variantes WebP de 400, 800 et 1200 pixels maximum, sans agrandissement ni recadrage, sont disponibles pour 3 690 images. Le composant choisit les variantes selon l’écran ; source et variantes sont vérifiées par empreinte. Une variante absente, divergente ou indisponible laisse l’original utilisable. La galerie retire un ancien `srcset` lors du passage à une image sans variante.

Le script `scripts/prepare_responsive_media.py` nécessite Python avec Pillow. Sur ce poste, l’interpréteur fourni par Codex est `/Users/akm/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3`. Sans `--apply`, le script prépare uniquement l’inventaire. Avec `--apply`, il crée `public/media/catalogue-display/` et refuse une collision avec un dossier existant. Une régénération doit préserver le dossier précédent dans une sauvegarde privée, sans supprimer automatiquement les originaux. Les variantes ajoutent environ 465 Mo au disque ; le téléchargement d’une seule taille est nettement inférieur à celui des originaux.

Apache HOMYA compresse les assets CSS/JS avec gzip et réserve le cache immuable aux noms basés sur leur contenu. HTML, configuration et manifests ne reçoivent pas ce cache. Les règles équivalentes de `public/.htaccess` sont conditionnelles à la disponibilité des modules du futur hébergement ; elles ne prouvent pas sa compatibilité.

Vérifications du 8 octobre 2026 : 306 tests PHP hors base, 7 327 assertions, trois tests Python des variantes, Pint et build réussis. La recette HTTP compare le contenu, les liens et les URLs d’images originales de 21 pages et scénarios FR/EN/DE. CSS transféré : 97 435 → 19 185 octets. Médiane de trois réponses du catalogue FR : 0,909 → 0,722 seconde, soit environ 20 % de réduction locale ; EN et DE montrent respectivement environ 1 % et 10 %. Ces échantillons ne constituent pas une mesure des Core Web Vitals ni une garantie en production.

Les preuves sont conservées dans `output/qa-optimization/`. La sauvegarde de travail est `storage/app/private/optimization-backups/20261008T231538Z/`. La sauvegarde du site et d’Apache est `/Applications/XAMPP/xamppfiles/homya-local/20261008T180754Z-1afc52ac-d21f-4530-a1ac-83890af9b236/optimization-backup-20261008T232605Z/` ; son `plan.json` précise les fichiers remplacés et ajoutés. Pour revenir au code précédent, restaurer les fichiers sauvegardés et l’ancien manifest Vite ; les anciens assets sont conservés. Pour revenir à la configuration Apache, restaurer son fichier sauvegardé, vérifier la syntaxe puis recharger seulement l’instance dédiée. Les variantes peuvent rester sur disque sans être utilisées. Ne pas exécuter de migration, de purge ou de restauration SQL pour ce retour. La restauration complète n’a pas été exécutée pendant cette recette.
