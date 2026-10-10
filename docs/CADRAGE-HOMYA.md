# HOMYA — cadrage de démarrage

Version 0.2 — 7 octobre 2026 — cadrage actualisé avec les choix explicites reçus.

Ce document décrit les demandes reçues, les observations publiques et les propositions. Il ne constitue ni une validation du modèle de reproduction, ni une preuve d'implémentation.

## 1. Projet et informations fournies

| Élément | Information et qualification |
|---|---|
| Nom | HOMYA — fourni par l'utilisateur |
| Dossier actif | `/Users/akm/Documents/ChatGPT/HOMYA` — inspecté, puis explicitement confirmé par l'utilisateur |
| État local | Dépôt Git sans commit, aucun code applicatif ni dépendance présents à l'inspection |
| Site de référence | https://www.damdam.fr/ — site existant fourni comme référence |
| Activité | Vente de bois de chauffage et assimilés, ainsi que d'appareils électroménagers — demande utilisateur |
| Objectif | Vendre les produits du catalogue HOMYA ; aucun objectif chiffré fourni |
| Public | Tout type de public — fourni ; segments prioritaires et vente professionnelle à confirmer |
| Pays ciblés | France, Belgique, Allemagne — fournis |
| Langues | Français, anglais, allemand — fournies |
| Devise | Euro — fournie |
| Paiements | Cartes de crédit et débit, virement — demandés ; prestataire non décidé |
| Autres connexions | Email, CRM, Google — demandés ; produits et comptes inconnus |
| Marque | Identité HOMYA à créer — demandée |
| Adaptation | Parcours similaires, identité HOMYA originale — choix explicite |
| Catalogue cible | Tout DAMDAM, y compris poêles, mobilier et TV — choix explicite |
| Base de développement | Construire une nouvelle base locale — choix explicite |
| Technologie | Laravel et base de données locale sur XAMPP — instruction explicite |
| Contenus et visuels | DAMDAM comme source — demandé |
| Réutilisation | L'utilisateur a déclaré : « Je possède le site ou les droits de réutilisation » ; déclaration reçue, sans audit indépendant des licences |
| Environnement initial | L'utilisateur a précisé : « non nous allons travailler d abord en local avant le deploiement » |
| Hébergeur et domaine | Non disponibles d'après la réponse utilisateur ; choix différable |
| Calendrier | « Le plus tôt possible » ; aucune date ni estimation ferme |
| Budget | Information manquante |

La mention « EXISTANT » du brief concerne bien une référence existante. Elle ne prouve pas l'existence d'une application HOMYA locale. Aucun fichier existant n'a été supprimé ou déplacé.

## 2. Observation de DAMDAM

Sources consultées le 7 octobre 2026 : extraction web des pages publiques et observation de l'accueil dans le navigateur intégré. Les extractions web peuvent provenir de captures à des dates différentes ; leurs décomptes ne forment pas un inventaire fiable du catalogue actuel.

| Source | Observation | Proposition pour HOMYA |
|---|---|---|
| [Accueil](https://www.damdam.fr/) | Recherche, accès catalogue et panier ; entrée par univers, sélections de produits, bloc conseil et inscription email. En-tête vert, grande image d'intérieur, titre large et actions d'achat visibles à l'écran. | ADAPTER les parcours et la hiérarchie ; REMPLACER l'identité DAMDAM par HOMYA |
| [Catalogue](https://www.damdam.fr/catalogue) | Contrôles de prix, marque, disponibilité, promotion et état ; tri et pagination visibles dans l'extraction. | ADAPTER aux catégories et attributs réellement proposés par HOMYA |
| [Chauffage](https://www.damdam.fr/chauffage) | Appareils de chauffage, conduits, accessoires et catégorie granulés/combustibles. | ADAPTER : appareils inclus par confirmation utilisateur ; détail des combustibles à confirmer |
| [Fiche produit échantillon](https://www.damdam.fr/produit/froid-rb33b610esa-samsung-combines-congelateur-en-bas) | Galerie, quantité, achat, caractéristiques, FAQ, avis et produits similaires. La page indique accepter uniquement le virement. | ADAPTER la fiche et ajouter le paiement par carte demandé ; valider les données de chaque produit |
| [Panier](https://www.damdam.fr/panier) | Récapitulatif, code promotionnel et accès à la commande ; présentation du paiement par virement. | ADAPTER ; recalculer les totaux côté serveur et rendre les frais cohérents |
| [Suivi](https://www.damdam.fr/suivi) | Saisie d'un numéro de suivi présentée à l'utilisateur. | ADAPTER ; définir la source des statuts et protéger l'accès aux informations de commande |

### Limites et points à corriger

- Aucune commande, inscription, demande de rappel ou opération de paiement n'a été effectuée sur DAMDAM.
- Les filtres, le panier rempli, le checkout, les traductions et le suivi réel ne sont pas testés. Une interface visible ne prouve pas son fonctionnement interne.
- Le code source, l'administration, la base de données, les stocks et les intégrations sont inconnus. Aucune technologie de DAMDAM n'est considérée comme établie.
- La référence affiche des mentions de retour de 14 et 30 jours à différents endroits. La règle HOMYA doit être confirmée puis présentée uniformément.
- L'extraction de la fiche échantillon emploie des unités différentes pour les mêmes dimensions : les données constructeur doivent être contrôlées avant import.
- L'extraction du panier vide présente des frais alors que le texte annonce une livraison offerte : ce résultat doit être vérifié dans le parcours réel avant toute conclusion technique.
- La catégorie de combustibles est visible ; son détail n'a pas été récupéré par l'outil web. Aucune offre de bûches précise n'est déduite de cette observation.

## 3. Modèle cible proposé

**Orientation explicitement confirmée :** adaptation des parcours publics, identité HOMYA originale, tous les rayons DAMDAM et nouvelle base locale. Le détail des règles métier et des écrans reste à valider progressivement ; les choix reçus ne valent pas approbation automatique de toutes les propositions de ce document.

### Catalogue proposé

1. **Bois et combustibles** : bûches, granulés et autres références réellement commercialisées. Les familles et conditionnements ne sont pas encore confirmés.
2. **Électroménager** : rayons tirés des produits retenus dans le catalogue source.
3. **Appareils de chauffage et autres rayons DAMDAM** : inclusion confirmée, notamment poêles, mobilier et télévision ; inventaire précis et données commerciales à contrôler.

Pour les combustibles, préciser les unités de vente, poids/volume, conditionnements et caractéristiques disponibles. Pour l'électroménager, prévoir les références, dimensions, caractéristiques et documents fabricant pertinents. Les données non fournies restent absentes, jamais fabriquées.

### Pages et parcours

Accueil → univers/catégorie → recherche et filtres → fiche produit → panier → coordonnées et livraison → choix carte/virement → confirmation → suivi et assistance.

Pages complémentaires proposées : contact, présentation HOMYA, livraison/retours, FAQ, confidentialité, cookies et documents commerciaux adaptés à l'entreprise. Le compte client, le conseil en direct et les avis sont à arbitrer selon les besoins et moyens réels.

Le fonctionnement de l'administration est à concevoir séparément : produits, traductions, prix, stocks, commandes et statuts. Il n'est pas déduit de la partie publique de DAMDAM.

### Traitement des éléments source

| Élément | Classification proposée | Condition |
|---|---|---|
| Parcours catalogue, produit, panier et suivi | ADAPTER | Validation du périmètre et vérification des comportements utiles |
| Logo, nom et charte DAMDAM | REMPLACER | Identité HOMYA proposée ci-dessous |
| Photos et descriptions | ADAPTER | Réutilisation déclarée autorisée ; associer la provenance à chaque actif importé |
| Catalogue, prix et stocks | INCONNU / À VÉRIFIER | Définir le catalogue HOMYA et obtenir une source exploitable et actuelle |
| Promotions et délais source | INCONNU / À VÉRIFIER | Confirmation commerciale pour HOMYA |
| Témoignages et notes DAMDAM | NE PAS REPRODUIRE comme avis HOMYA | Définir séparément un usage fidèle et autorisé si demandé |
| Identité légale, coordonnées et conseiller DAMDAM | REMPLACER ou À CONFIRMER | Identité et contacts HOMYA requis avant publication |
| Clients, commandes, paiements et secrets | NE PAS REPRODUIRE | Aucun import dans le périmètre actuel |

## 4. Direction de marque — proposition

**HOMYA** : une identité centrée sur la maison, la chaleur et le confort quotidien.

- Signature proposée : **« La chaleur du foyer. Le confort au quotidien. »**
- Ton : chaleureux, clair et concret ; conseils utiles et caractéristiques vérifiables.
- Logo proposé : mot-symbole HOMYA et symbole original associant une maison à une flamme.
- Palette proposée : vert profond `#173D32`, ivoire `#F6F2EA`, terre cuite `#B9522C`, charbon `#202720`.
- Photos : produits lisibles sur fond neutre et scènes de maison issues des actifs autorisés ; éviter une image qui suggère des services non disponibles.
- Interface : recherche visible, deux univers clairement distingués, prix et conditions proches du bouton d'achat, parcours mobile accessible.

Il s'agit d'une direction créative à valider, pas d'une charte adoptée ni d'une vérification de disponibilité de marque ou de domaine.

## 5. Exigences et acceptation — première proposition

Priorisation par dépendance : **Fondation** pour définir la solution ; **Vente** pour achever un achat fiable ; **Complément** pour les fonctions dont le besoin exact reste à confirmer. Aucun score d'impact ou délai n'est inventé. Les demandes utilisateur sont distinguées des détails proposés.

| ID | Source et statut | Exigence proposée et critère d'acceptation | Priorité, propriétaire et dépendances |
|---|---|---|---|
| H-01 | Brief ; détails à confirmer | Catalogue HOMYA : chaque produit publiable dispose d'une référence, d'un prix, d'une disponibilité et d'images sourcées ; recherche et filtres correspondent aux attributs réels. | Fondation ; E-commerce ; périmètre et données |
| H-02 | Brief ; demandé | FR/EN/DE et EUR : navigation, pages produit et parcours d'achat disponibles dans les trois langues ; changement de langue conserve le contexte lorsque possible. | Fondation ; Design/E-commerce/SEO ; traductions et architecture |
| H-03 | Panier source + recommandation | Ajout, modification et suppression ; total recalculé côté serveur, refus des quantités invalides et signalement d'une disponibilité devenue insuffisante. | Vente ; E-commerce ; prix, stocks et livraison |
| H-04 | Brief ; demandé | Carte de crédit/débit via prestataire choisi : paiement réussi, refusé ou abandonné donne un état cohérent ; notification authentifiée et répétée ne crée pas de double commande. | Vente ; E-commerce/Security ; prestataire et environnement de test |
| H-05 | Brief ; demandé | Virement : commande en attente distincte d'un paiement confirmé ; référence de rapprochement et procédure de réception/annulation documentées. | Vente ; E-commerce ; règles de rapprochement et de réservation |
| H-06 | Brief ; demandé, règles manquantes | France/Belgique/Allemagne : frais, zones et modalités calculés selon les règles approuvées ; pays ou destination non pris en charge explicitement signalés. | Vente ; E-commerce ; transport, coûts et traitement fiscal à confirmer |
| H-07 | Suivi source + besoin d'exploitation proposé | Statuts de commande et suivi accessibles sans divulguer les données d'un autre client ; gestion autorisée des produits et commandes dans l'administration. | Vente ; E-commerce/Security ; rôles et source logistique |
| H-08 | Brief ; demandé, prestataires inconnus | Emails transactionnels et CRM : confirmation déclenchée au bon état ; échanges testés localement ou en environnement de test, sans envoi réel pendant la maquette. | Vente/Complément selon usage ; E-commerce ; prestataires et finalités |
| H-09 | Référence + recommandation | Responsive et accessibilité : parcours utilisable au clavier, erreurs compréhensibles, affichage mobile sans débordement ; contrôles visuels et fonctionnels à réaliser. | Vente ; Design/Quality ; interface |
| H-10 | Brief Google + recommandation | Pages publiques, données produit et mesure préparées selon les services retenus ; vérification SEO/tracking distincte de l'acceptation Google. | Fondation puis Vente ; SEO/Analytics/Google Services ; comptes et plan validé |

Les seuils, règles fiscales, délais, conditions de retour, conservation des données et documents applicables restent à vérifier par leurs modules et responsables avant mise en production.

## 6. Architecture et phase locale

**Technologie choisie par l'utilisateur : Laravel avec base de données locale XAMPP.** Le projet reste dans son dépôt confirmé. Le socle prévu utilise Laravel 13, PHP 8.5 déjà installé et le moteur MariaDB de XAMPP. PHP 8.2.4 livré avec XAMPP ne permet pas d'exécuter Laravel 13 ; le serveur de développement Laravel utilisera donc le PHP disponible hors XAMPP. Ce choix ne modifie pas l'installation XAMPP.

La connexion sera isolée vers le socket XAMPP attendu et contrôlée avant utilisation : le port 3306 est déjà occupé et ne prouve pas qu'il s'agit de XAMPP. Aucune base métier ni migration ne seront créées ou exécutées pendant l'initialisation du socle.

Composants fonctionnels nécessaires : storefront multilingue, catalogue, calcul de panier et commande côté serveur, persistance des produits/commandes, administration, adaptateur de paiement, livraison et emails.

La première réalisation locale pourra présenter les pages et parcours avec un jeu de données explicitement identifié comme démonstration. Les transactions réelles et les connexions externes viendront après choix des services et vérification en mode test. Une maquette de checkout ne sera pas présentée comme un paiement opérationnel.

Hébergement et domaine sont différables pour le cadrage et la maquette ; ils devront être pris en compte avant de finaliser l'exploitation. Hostinger n'est pas considéré comme choisi.

## 7. Google — cadrage initial

Cette matrice qualifie les besoins potentiels. Aucune politique pays/programme, éligibilité ou configuration de compte n'a encore été vérifiée dans la documentation Google actuelle.

| Service | Applicabilité au besoin | Préparation | Dépendance et propriétaire |
|---|---|---|---|
| Search / Search Console | Applicable pour le futur site public | Non évalué | Domaine, pages publiées, accès ; SEO |
| Données structurées produit | Conditionnel / à vérifier | Non évalué | Données fiables et règles actuelles ; SEO/E-commerce |
| Merchant Center / flux / Free Listings | Conditionnel / à vérifier | Non évalué | Catalogue, marchés, conditions de vente et compte ; Google Services/E-commerce |
| GA4 | Conditionnel / à vérifier | Non évalué | Besoin de mesure, consentement et plan d'événements ; Analytics |
| GTM | Conditionnel / à vérifier | Non évalué | Méthode d'installation retenue ; Analytics |
| Google Ads / Shopping / Performance Max | Information manquante | Non évalué | Aucun budget ni campagne demandés ; Growth/Google Ads |
| Business Profile | Information manquante | Non évalué | Modèle d'activité et contact physique inconnus ; Google Services |
| Local Inventory | Information manquante | Non évalué | Magasin et stock local inconnus ; Google Services/E-commerce |

## 8. Prochaines étapes et validations

1. **Cadrage actuel** : inventaire public et proposition HOMYA — réalisé ; choix de périmètre, chemin et stack reçus.
2. **Socle technique local** : initialiser Laravel dans le dépôt confirmé, préparer la connexion XAMPP, vérifier le démarrage et documenter les limites. Modèle métier et migrations dans une phase ultérieure.
3. **Identité et maquette locale** : créer le logo et les écrans d'accueil/catalogue/produit/panier dans la base retenue ; revue desktop/mobile.
4. **Commerce local fonctionnel** : données réelles validées, commandes, stock, administration ; paiement et emails en test.
5. **Préparation au lancement** : règles métier, traductions, identité légale, SEO, mesure, sécurité, hébergement et tests de bout en bout.
6. **Déploiement** : seulement après autorisation explicite et validation des contrôles nécessaires.

### Questions nécessaires à la prochaine phase

- Aucun arbitrage de nom, chemin, périmètre des rayons ou framework ne reste en attente.
- Connexion effective à XAMPP : vérifier le service et le socket ; renseigner les accès uniquement dans la configuration locale ignorée par Git, jamais dans le chat.
- Avant la phase catalogue : formaliser le modèle de données et la source des références produits.

Avant le commerce réel : préciser entreprise, catalogue, prix/stock, taxes, conditionnements, logistique, retours, moyens de support et prestataires. Ces inconnues n'empêchent pas la préparation du modèle visuel.

### Traçabilité des arbitrages

Registre canonique : `/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md`, section HOMYA. Sept choix explicitement confirmés ont été ajoutés puis relus sous les identifiants HOMYA `DECISION-001` à `DECISION-007` : chemin/identité, droits déclarés, phase locale, rayons, adaptation, nouvelle base et Laravel/XAMPP. Leur portée exacte figure dans le registre. Le présent document ne crée pas de second registre général.

L'ajout au registre a été exécuté avec autorisation filesystem, sauvegarde préalable et vérification de récupération. La charte détaillée, les règles métier et les prestataires proposés dans ce document ne sont pas automatiquement validés par ces sept choix.

## 9. Vérifications réellement effectuées

- Inspection du dossier, du statut Git et des règles globales/applicables ; constat d'absence de code et de dépendances.
- Consultation du routeur, du Workflow Master et des références pertinentes de Specification, Site Cloning, Strategy, E-commerce, Design et Google Services.
- Consultation des pages publiques listées ; observation visuelle de l'accueil sur ordinateur.
- Réponses utilisateur reprises avec leur formulation et leur portée.
- MCP `claude-code` : outils annoncés dans la session et appel `Read` réussi sur les six premières lignes de ce document. Une lecture réussie ne démontre ni une authentification Anthropic ni un accès au modèle Claude. Aucun appel du modèle Claude réalisé ; travail poursuivi avec les outils natifs Codex.

Le socle Laravel local est désormais initialisé ; ses contrôles et limites sont documentés dans `ENVIRONNEMENT-LOCAL.md`. Aucun paiement, commande réelle, import massif, branche, commit, campagne ou déploiement réalisés. Les fonctions e-commerce et leurs vérifications spécialisées restent à réaliser par phase.
