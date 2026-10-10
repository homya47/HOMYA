# HOMYA — source des données catalogue

7 octobre 2026 — qualification initiale et actualisation pendant le lot catalogue consultable. Collecte locale distincte d’un import MariaDB.

## Choix confirmé

DAMDAM.fr est la source retenue pour **l’ensemble du catalogue, les images, les prix affichés et les caractéristiques**. Confirmation utilisateur : « je voudrais tout de damdam.fr. les images les prix les caracteristiques ». Choix persisté et relu dans le [registre central, section HOMYA, DECISION-010](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). Les droits de réutilisation étaient déjà déclarés en DECISION-002. Aucun second registre créé.

La demande inclut maintenant la reprise des prix, contrairement à l’option antérieure « descriptifs sans prix ni stock ». Elle ne définit pas le stock réellement disponible pour HOMYA, les règles de calcul fiscal/livraison, les réservations, le prestataire de paiement ou les remboursements. La distinction entre données produits sources et règles opérationnelles reste nécessaire avant les fonctions de vente.

## Faits observés et vérifiés

Sources primaires consultées le **2026-10-07**. Deux méthodes distinguées : extraction web pouvant être mise en cache, puis lecture HTTP directe du HTML public avec Python standard, sans authentification, formulaire, panier ni commande. Le contrôle direct initial était bloqué par le réseau du sandbox ; il a réussi après autorisation technique. Aucun secret collecté ou affiché, aucun HTML complet enregistré.

| Source, titre et URL | Portée | Preuve / limite |
|---|---|---|
| [Catalogue complet · DAMDAM Électroménager](https://www.damdam.fr/catalogue) | Catalogue public actuel | HTTP direct 200 ; liste initiale de 24 produits ; total publié actuel : **3 709**. L’extraction web antérieure de 1 002 références est obsolète pour cet inventaire. Les images produits proviennent notamment de `damdam.b-cdn.net/produits/…`. |
| [Sitemap DAMDAM](https://www.damdam.fr/sitemap.xml), déclaré par [robots.txt](https://www.damdam.fr/robots.txt) | Inventaire des URLs publiques | Lecture HTTP directe : 4 078 URLs, dont **3 709 URLs produit distinctes**. Sert à compléter l’inventaire ; une URL dans le sitemap ne prouve pas encore une fiche valide. |
| [Poêle à bois avec four Pyrite · 13 Kw – ETNA Luxe](https://www.damdam.fr/produit/chauffage-elpyrit13no-poeles-a-bois) | Fiche d’échantillon chauffage | HTTP direct 200, données structurées de type Product, FAQPage et BreadcrumbList ; 21 balises image sur l’ensemble de la page, incluant logo et produits associés. URL de média produit observée sur le CDN DAMDAM. Ne pas importer toutes les images de la page comme galerie de ce produit. |
| Même fiche Pyrite, extraction web puis lecture actuelle | Prix et caractéristiques de l’échantillon | Prix courant observé : 909,30 EUR, ancien prix 1 299,00 EUR. La capture web antérieure présentait 152 m² dans la FAQ contre 132,8 m² dans le tableau ; la lecture HTTP actuelle de la FAQ donne 132,8 m². Ne pas corriger une source actuelle à partir de cette ancienne capture. Aucune conformité fiscale ou technique certifiée. |
| [Produit introuvable · DAMDAM Électroménager](https://www.damdam.fr/produit/thermomix-tm7) | Ancien lien trouvé dans l’extraction du catalogue | HTTP direct 200, titre « Produit introuvable », aucune donnée structurée Product, aucun lien produit ; deux images de présentation du site. Une réponse 200 ne suffit pas à établir une fiche valide. Ne pas fabriquer une fiche à partir d’un ancien lien. |

Les données structurées et données publiques embarquées sont des moyens d’extraction, pas une certification métier. La collecte lit uniquement les données publiques des fiches valides, sans exécuter le JavaScript source. Les montants source entiers sont exprimés en centimes ; l’échantillon Pyrite concorde avec son offre structurée à 909,30 EUR. Les images principales sont récupérées localement après contrôle de type et de signature ; les autres URLs de galerie sont conservées et utilisées depuis le CDN autorisé. Le téléchargement local de toutes les galeries et la vérification indépendante des caractéristiques ne sont pas revendiqués.

### Pagination source et adaptation validée

Le parcours HTTP de 136 URLs de pagination n’a fourni que 1 223 références distinctes. Des références se répètent entre les pages 1, 2 et 3 ; les pages 135 et 136 proposées depuis une page précédente ne fournissent pas le contenu catalogue attendu, même après trois inspections. Preuve conservée dans [le rapport de parcours](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-catalogue/source-pagination.json). Le mécanisme interne responsable reste inconnu ; aucun accès au code ou à la base DAMDAM n’est prétendu.

L’utilisateur a demandé une meilleure pagination. HOMYA déduplique par slug, utilise un ordre déterministe avec départage par slug, affiche les pages voisines et les bornes, conserve les filtres et propose l’accès direct par numéro. Aucun changement effectué sur DAMDAM.

## Traitement proposé pour la collecte

1. Inventorier les liens produits du catalogue actuel et suivre uniquement la pagination réellement exposée ; dédupliquer par URL et référence source. Vérifier la couverture de tous les rayons, sans déduire le total actuel d’une capture web.
2. Collecter uniquement les fiches valides ; distinguer 404, « introuvable » avec HTTP 200, erreur réseau et champs manquants. Conserver la liste des exceptions et ne pas les remplacer par des données fictives.
3. Conserver pour chaque produit l’URL source, la date de collecte, la référence, la catégorie observée, le titre, la marque, les descriptions descriptives, les caractéristiques et unités originales, les variantes effectivement présentes et les montants publiés avec devise. Distinguer prix courant et prix barré ; ne pas inventer une promotion ou sa période.
4. Associer les images à la galerie de leur produit ; conserver URL d’origine, fichier local, type/taille/dimensions et empreinte après récupération. Ne pas confondre logo, média produit, image similaire et logo de paiement. Préférer les actifs locaux après contrôle plutôt qu’une dépendance permanente au CDN de la source.
5. Détecter et consigner les écarts entre titre, description, caractéristiques, données structurées et carte catalogue. L’absence d’unité ou de variante reste inconnue ; aucune conversion poids/volume automatique.
6. Ne pas transformer les avis DAMDAM en avis HOMYA, ni reprendre automatiquement les coordonnées vendeur, engagements de livraison/retour, textes fiscaux, garanties commerciales ou arguments non établis pour HOMYA. Les descriptions mélangeant produit et engagements DAMDAM devront être séparées pour revue, sans remplacement automatique trompeur du nom du vendeur.
7. Préparer l’import de manière idempotente, avec rapport des éléments créés/modifiés/ignorés et contrôle des références et médias ; aucun import SQL avant le modèle, le plan et l’autorisation propres à XAMPP.
8. Pour FR/EN/DE, préserver l’original source et identifier les traductions manquantes ; ne pas modifier les valeurs techniques au cours de la traduction. Les traductions restent à produire et à relire.

Le traitement est mis en œuvre pour la consultation locale ; l’import SQL reste différé. Les descriptions sont des extraits descriptifs nettoyés des engagements vendeur DAMDAM, au maximum 6 000 caractères. Les caractéristiques et unités sont conservées, les doublons exacts retirés et les contradictions signalées. Les règles de panier/stock/paiement ne sont pas déduites du contenu public DAMDAM.

## État et suite

- **Décisions validées :** source complète (DECISION-010), catalogue consultable sans panier et corrections HOMYA (DECISION-011), amélioration de pagination demandée dans le fil.
- **Réalisé :** collecte et intégration Laravel en lecture seule, fiches, recherche, filtres, tri, pagination, interfaces FR/EN/DE. Le décompte final et les contrôles figurent dans [la recette du lot](/Users/akm/Documents/ChatGPT/HOMYA/docs/INCREMENT-CATALOGUE-CORRECTIONS.md) et dans `storage/app/catalogue/report.json`.
- **Non réalisé :** import MariaDB, création de base/migrations, téléchargement de toutes les galeries, traductions complètes des contenus produits, certification des caractéristiques et fonctions d’achat. Les fichiers de collecte sont une archive locale de prévisualisation, pas une autre base de données cible.
- **Dépendances pour la vente :** quantités/unités, stock faisant autorité, calcul de prix/taxes/frais, livraison, règles de commande et prestataire de test ; à préciser au moment du lot concerné.
