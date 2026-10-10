# HOMYA — correction de l’accueil

08/10/2026 — DECISION-047, demande explicite de reproduction de la composition de l’accueil de référence, avec correction des défauts et marque HOMYA.

## Observation et périmètre

La page [de référence](https://www.damdam.fr/) a été consultée le 08/10/2026 par navigateur et extraction web, sans commande, inscription ou accès privé. Elle comporte une bannière illustrée, trois repères, deux blocs illustrés, cinq univers mis en avant, douze produits phares, un bloc conseiller, huit nouveautés, inscription et pied de page. L’accueil HOMYA servi dans htdocs ne présentait que le texte introductif et cinq liens d’univers : ce manque de contenu a été vérifié.

Les droits de réutilisation sont ceux déclarés par l’utilisateur. Cette demande autorise la correction locale ; aucun nouveau service, compte, paiement, accès ou engagement commercial n’est ajouté.

## Correspondance et corrections

| Élément | Mise en œuvre HOMYA | Limite conservée |
|---|---|---|
| Bannière | Même illustration d’intérieur, titre chauffage/électroménager, accès poêles/catalogue/aide, en-tête jaune depuis DECISION-048 et recherche réelle. | Identité HOMYA ; aucun argument « prix d’entrepôt » non confirmé. |
| Trois repères | Grandes marques, prix en euros, fiches détaillées. | Paiement sécurisé, délai de livraison, retour gratuit et contradiction14/30jours non recopiés comme engagements HOMYA. |
| Deux blocs illustrés | Visuels autorisés, présentations chauffage/électroménager et liens réels. | Illustrations annoncées ; aucune possession d’entrepôt ou flotte propre affirmée. |
| Univers | Chauffage, mobilier, réfrigérateurs, lave-linge, café ; accès complémentaires combustibles et TV. | Filtres existants réutilisés ; pas de catégories fictives ni de nouveaux filtres. |
| Produits phares | Les12références observées, même ordre, projection Catalogue, photos et prix réels. | Pas d’avis/bestsellers/vendus/livraison/remises sans données HOMYA validées ; consultation des fiches, pas d’achat rapide. |
| Conseil | Bloc d’aide au choix avec catalogue/filtres/fiches réellement accessibles. | Aucun conseiller nommé, téléphone source, demande de rappel ou conversation externe recopié. |
| Autres modèles | Les8modèles de la sélection observée sur la référence. | Le titre « D’autres modèles à découvrir » évite de certifier une date d’arrivée en stock HOMYA. |
| Inscription/footer | Bloc de découverte et footer avec liens valides et statut du catalogue. | Aucun formulaire d’inscription sans destination ou consentement validés. |

Les20références existent dans le lot conservé, et leurs20prix archivés correspondent exactement aux valeurs observées aujourd’hui sur la référence. Une sélection éditoriale est distincte d’une preuve de ventes ou de disponibilité actuelle. Les données source, profil, archive, .env et SQL restent conservés ; pas de migration ni d’écriture SQL.

## Fichiers et données

HomeController/config home : une projection Catalogue par requête, collections12/8 et images réelles. Blade accueil/layout/carte et styles partagés : sections, responsive, titres h1/h2/h3 cohérents. Textes français/anglais/allemand : interface et contenu éditorial. Produits traduits par le système Catalogue existant, sans repli en français si la traduction manque.

Neuf visuels autorisés sont enregistrés localement, originaux conservés. Huit copies WebP qualité82, sans redimensionnement ni IA générative, représentent1 001 482octets contre9 798 873octets d’originaux téléchargés (le neuvième est déjà WebP). Le fond desktop natif1600×600 est utilisé ; dimensions déclarées, image principale prioritaire, images secondaires différées. Les images d’univers ne sont pas des photos de fiches destinées à un flux Merchant Center. Aucune approbation Google revendiquée.

## Vérification

Première suite globale de l’accueil :244tests/1986assertions ; ciblés accueil après état vide :8tests/459assertions. L’harmonisation suivante DECISION-048 passe257tests/2668assertions à la recette finale hors base et la matrice `output/qa-site-20261008/http-native.json` contrôle3langues,60prix,127liens,33ressources,3pannes contrôlées et3accès privés refusés sur la copie réellement servie en SQL. Ces chiffres ne prouvent pas une ouverture des ventes. Les preuves de l’accueil initial restent historiques dans `output/qa-home-20261008/` ; la revue finale est consolidée avec DECISION-048 dans `output/qa-site-20261008/verification.json`.

Préconditions de copie : empreintes des fichiers installés comparées au témoin avant édition, sauvegarde des seules versions remplacées, fichiers nouveaux refusés en cas de collision, vérification SHA256 des fichiers copiés. Les anciennes preuves de l’installation initiale restent historiques, sans réécriture.

## Inconnues et suite

Stocks, prix/TVA commerciaux, vendeur, livraison/retours, contact/conseiller, paiement et email restent à fournir selon la réponse utilisateur précédente. L’accueil riche est consultatif ; aucune ouverture des ventes ou préparation Hostinger complète n’est certifiée. Aucun tag ni cookie publicitaire ajouté.
