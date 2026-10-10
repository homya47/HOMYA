# Déploiement Hostinger — catalogue et inventaire initiaux

Ce guide prépare l’instance Hostinger `homya.eu` à partir des sources HOMYA vérifiées. Il ne remplace pas le contrôle des secrets, de la configuration d’exploitation ni les tests réels du parcours de commande.

## État vérifié

- Hébergement : formule Unlimited, région France, 50 Go disque, PHP CLI 8.5.11, Composer 2.9.8, MariaDB 11.8.9, `pdo_mysql` présent.
- Domaine et racine configurée : `homya.eu`, `~/domains/homya.eu/public_html`.
- La base commerce `u429557141_homya` contient déjà les cinq tables d’archive et le lot validé. Une seconde base `u429557141_catalogue` a été créée pour isoler la lecture catalogue, car l’offre Hostinger partagée limite les comptes par base.
- Archive catalogue approuvée : 3 709 produits, 65 catégories, 23 878 traductions ; SQL SHA-256 `cb67ffd9c66cfd0b12bbbe0c7fe0acb0a211cf197557c2030a46aed841b1febd`.
- Profil privé SHA-256 `adb862b6f9b96e602417d2a6d57160dfcd0040ba3115e956b7d638bfc8deca96`.
- Quantité de départ confirmée par l’utilisateur : 1 000 unités par produit, soit 3 709 000 unités déclarées.
- Archive média validée localement : 18 488 fichiers dans `public/media/catalogue`, environ 3,46 Go. Le compte Hostinger dispose d’assez d’espace selon les limites affichées, sous réserve de l’espace déjà consommé.
- Archive média de livraison `catalogue-media.tar` SHA-256 `528e0ce10ebe2274758df5fcf1fbd13a52e952c2b144f7862902d059d4a9af5e`.
- Le lot de transfert inclut `SHA256SUMS` pour contrôler le dump, le profil, le stock, les images et Vite avec `shasum -a 256 -c SHA256SUMS`.

## Publication du code

Publier le code HOMYA depuis une branche basée sur `origin/main`, après tests et revue du diff. Ne pas forcer le push : l’historique local n’a pas de base commune avec `origin/main`. Les ressources Vite doivent être incluses (`public/build/manifest.json` et `public/build/assets/*`) puisque Node n’est pas installé dans le shell SSH Hostinger. Déployer le code avant l’import, puis utiliser exclusivement les commandes protégées ci-dessous.

## Transfert privé des données

Depuis le poste local, transférer le dump, le profil, le manifeste de stock et l’archive média par SCP vers le répertoire personnel SSH. Ne pas transférer `.env`, les identifiants MySQL ni les secrets. Le dump et le profil doivent rester dans `storage/app/private`, jamais dans `public_html` accessible par HTTP.

Créer les dossiers privés avec permissions `0700` et les fichiers sensibles avec `0600` ou `0400`. Le dump doit être placé à `storage/app/private/hostinger-import/catalogue.sql`. Le profil doit être placé dans `storage/app/private/catalogue-profiles/48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809.json`. Le manifeste de stock doit être placé à `storage/app/private/commerce/initial-inventory-v2.json`.

Extraire l’archive média dans la racine du projet : elle contient le chemin `public/media/catalogue/...`. Comparer son SHA-256 au manifeste de livraison avant extraction ; contrôler que les images représentatives répondent en HTTP après publication.

## Base miroir du catalogue

Dans hPanel/phpMyAdmin, importer `catalogue.sql` dans la base dédiée `u429557141_catalogue`. Ne pas modifier ni supprimer `u429557141_homya` : elle contient déjà l’archive importée et reste la base d’écriture commerce. Le dump crée les tables d’archive ainsi qu’une table `migrations`; ne pas lancer les migrations de l’application sur cette base miroir.

Dans hPanel, limiter ensuite l’utilisateur associé à la base miroir aux privilèges `SELECT`. Le `.env` privé doit contenir les identifiants `HOMYA_CATALOGUE_DB_*` de cette base, avec `HOMYA_CATALOGUE_SOURCE=sql`. Garder `DB_*` pour les commandes : la connexion `commerce` reprend ces valeurs si `HOMYA_COMMERCE_DB_*` sont absentes ou vides. Ne jamais coller le contenu de `.env` dans une conversation.

Cette séparation garde les tables de commandes dans la base commerce et isole le catalogue consultatif dans son propre schéma. Laravel vérifie l’identité, la base et la session MariaDB ; les privilèges `SELECT` doivent aussi être contrôlés dans hPanel.

Après déploiement du correctif, depuis SSH et `public_html`, vérifier les deux connexions et l’audit :

```sh
cd ~/domains/homya.eu/public_html
php artisan optimize:clear
php artisan db:show --database=commerce
php artisan db:show --database=homya_hosted_catalogue_reader
php artisan homya:security-audit --json
```

Ne pas relancer `homya:hostinger-catalogue-import` : il cible la connexion principale, déjà alimentée. Vérifier les quatre tables miroir, les comptes du lot `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809`, puis tester `https://homya.eu/fr/catalogue` après le déploiement.

## Activation catalogue et initialisation du stock

Après l’import miroir confirmé, vérifier que le profil reste privé et que l’application lit le catalogue via le lecteur dédié. Appliquer ensuite les migrations commerce sur la connexion `commerce` uniquement, après vérification de l’absence des tables commerce et d’une sauvegarde :

```sh
php artisan migrate --force --path=database/migrations/commerce
```

Le manifeste transmis a déjà été contrôlé localement (3 709 références, 3 709 000 unités, SHA-256 ci-dessus). Vérifier son empreinte sur Hostinger puis installer une seule fois :

```sh
php artisan homya:commerce-inventory --apply --sha256=de245295043163a5bd532f20c676d4a53641790012fc53cda8ec354442461264
```

La commande refuse de remplacer un inventaire ou des commandes existants. Vérifier son résumé avant de mettre la boutique en vente.

## Contrôles avant ouverture

- Configurer les paramètres de vente déjà confirmés dans l’environnement privé, y compris les coordonnées de virement, l’adresse de contact et la livraison. Ne jamais publier de secrets.
- Vérifier `php artisan homya:security-audit --json`, les routes, `php artisan route:cache`, `php artisan view:cache`, puis `php artisan config:cache`.
- Vérifier catalogue, fiches et images, sélection de variantes, prix affichés, panier, livraison, validation de commande par virement, confirmation, email, suivi, retour et expiration des réservations. N’effectuer aucun paiement réel de test.
- Vérifier les journaux sans afficher de données client et tester les URLs publiques en HTTPS sur `homya.eu` (racine redirigée vers `/fr`).
- Configurer une tâche cron Hostinger chaque minute pour `php artisan schedule:run` afin que les réservations expirent comme prévu ; confirmer son résultat dans hPanel.
- Merchant Center/Search Console demandent une vérification et une validation par Google ; le bon fonctionnement local ne signifie pas que Google a accepté ou indexé le flux.

Une ouverture réelle n’est confirmée qu’après exécution de ces étapes sur Hostinger et vérification du parcours complet en production.
