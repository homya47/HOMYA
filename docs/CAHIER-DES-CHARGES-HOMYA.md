# Cahier des charges HOMYA

**Version 0.1 — 7 octobre 2026 — PROPOSÉ pour validation documentaire.**

HOMYA est un projet de boutique en ligne destiné à vendre des produits pour la maison, dont bois et combustibles, chauffage, électroménager, mobilier et téléviseurs. Ce cahier des charges décrit la cible fonctionnelle et les critères de sa future recette. Il ne valide pas les propositions ouvertes, ne prouve pas leur implémentation et n'autorise aucun développement, migration, déploiement ou service externe.

Projet et emplacement confirmés : `/Users/akm/Documents/ChatGPT/HOMYA`. Destinataire et approbateur documentaire : utilisateur du projet HOMYA. Responsables commerciaux, logistiques, techniques et juridiques nommés : **Inconnu**.

## 1. Sources et qualification

| Code | Source et portée |
|---|---|
| S-B | Brief et réponses utilisateur du fil HOMYA, repris dans le [cadrage](/Users/akm/Documents/ChatGPT/HOMYA/docs/CADRAGE-HOMYA.md). Les demandes sont distinguées des modalités non décidées. |
| S-D | [Registre canonique](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), section HOMYA, décisions DECISION-001 à DECISION-008, relues pour cette version. Aucune décision d'un autre projet n'est applicable. |
| S-O | Observations publiques DAMDAM du 7 octobre 2026 consignées dans le cadrage : accueil, catalogue, produit, panier et suivi. Interface observée seulement ; fonctionnement interne et checkout complet non vérifiés. |
| S-L | Inspection locale et [environnement local](/Users/akm/Documents/ChatGPT/HOMYA/docs/ENVIRONNEMENT-LOCAL.md), complétés par le [diagnostic XAMPP](/Users/akm/Documents/ChatGPT/HOMYA/docs/DIAGNOSTIC-XAMPP.md). Les contrôles antérieurs ne sont pas relancés pour rédiger ce document. |
| S-S | Cadrage stratégique présenté dans le fil : objectifs issus du brief ; positionnement, segments et indicateurs proposés, sans approbation globale. |
| S-M | Recommandations des modules Specification, Strategy, E-commerce, Database Engineering, Design, SEO, Analytics, Google Services, Security, Quality, Performance et Legal & Compliance. Leurs prescriptions de méthode ne fournissent pas de règles commerciales propres à HOMYA. |

**Statuts des éléments :** Fait sourcé ; Décision validée dans sa portée ; **Inconnu** si l'information manque ; **À confirmer** si le détail n'est pas accepté ; **Hypothèse** pour une proposition provisoire ; **Décision nécessaire** pour un arbitrage utilisateur ; **Bloquante** uniquement pour la phase explicitement indiquée. Un critère proposé n'est pas un test réussi.

Le registre reste l'unique registre général des décisions. Les listes de questions ci-dessous sont les éléments ouverts de cette version, pas un second registre.

### Décisions déjà validées

| Décision HOMYA | Portée à conserver |
|---|---|
| DECISION-001 | Nom HOMYA et utilisation du dépôt confirmé ; aucun déplacement d'un autre projet. |
| DECISION-002 | Droits de réutilisation DAMDAM déclarés par l'utilisateur ; aucune certification indépendante, import de données personnelles ou autorisation d'accès privé. |
| DECISION-003 | Travail local avant déploiement ; aucun achat ni déploiement autorisé par ce choix. |
| DECISION-004 | Tous les rayons DAMDAM, dont poêles, mobilier et TV ; inventaire, prix et stocks HOMYA à vérifier. |
| DECISION-005 | Parcours publics similaires et identité HOMYA originale ; détail des écrans et charte à revoir. |
| DECISION-006 | Nouvelle application locale ; pas de reprise du code DAMDAM dans le périmètre actuel. |
| DECISION-007 | Laravel et base locale XAMPP ; migrations métier et autres services non autorisés implicitement. |
| DECISION-008 | MariaDB XAMPP sur 3307, écoute locale, gestionnaire et lanceur alignés ; MySQL Homebrew conservé. Correction exécutée avec sauvegardes. |

Les pays France/Belgique/Allemagne, langues FR/EN/DE, euro, carte et virement sont des **demandes sourcées S-B**. Ils ne déterminent pas automatiquement les zones livrables, le prestataire, les règles fiscales ou la séquence de lancement.

## 2. Objectifs et utilisateurs

**Objectif confirmé — S-B :** vendre les produits HOMYA. Objectifs chiffrés, budget, date ferme et période de mesure : **Inconnu**. « Le plus tôt possible » exprime une préférence de calendrier, pas une échéance contractuelle.

**Hypothèse de besoin — S-S :** permettre à l'acheteur de trouver un produit adapté, d'en comprendre les caractéristiques et de connaître coût total, disponibilité et conditions avant de commander.

**Proposition de valeur recommandée — S-S, À confirmer :** choisir les produits pour chauffer et équiper sa maison avec des informations claires et une commande suivie. Aucun avantage de prix, livraison, disponibilité ou conseil n'est démontré.

| Utilisateur ou partie prenante | Qualification | Besoin envisagé |
|---|---|---|
| Visiteur | S-B, public large confirmé | Explorer l'offre et comprendre les informations commerciales. |
| Acheteur particulier | Hypothèse | Acheter pour son logement ; segmentation et motivations à vérifier. |
| Acheteur professionnel | Décision nécessaire | Déterminer si une offre B2B, des devis ou conditions distinctes sont nécessaires. |
| Exploitant catalogue et commandes | Hypothèse opérationnelle | Publier des données fiables et traiter les achats ; personnes à désigner. |
| Support, logistique, rapprochement des paiements | Information manquante : Inconnu | Identifier les responsables et leurs actions avant de figer les droits. |

## 3. Périmètre inclus et exclu

**Inclus dans la cible confirmée :** tous les rayons DAMDAM, nouvelle marque HOMYA, adaptation des parcours, FR/EN/DE, EUR, vente par carte et virement, développement local Laravel/XAMPP. Les connexions email, CRM et Google sont demandées, avec produits et finalités à préciser.

**Proposé pour rendre cette cible exploitable, À confirmer :** catégories, recherche, fiche produit, panier, checkout, suivi, assistance et administration. Les modalités de gestion commerciale restent ouvertes.

**Exclus de cette phase :** code applicatif, modèle physique ou migrations, création de base ou compte, import de catalogue, configuration de prestataires, envoi réel, dépenses et déploiement. Seul ce document est créé.

**Non inclus sans décision complémentaire :** import de clients/commandes DAMDAM, application mobile native, marketplace, réservation de prestations ou créneaux, programme de fidélité, abonnement, promotions, avis, devis/bon de commande professionnels et automatisations marketing. Les comptes clients sont conditionnels. La reprise intégrale du catalogue au lancement est **À confirmer** ; le périmètre global validé n'est pas réduit.

## 4. État de départ et contraintes techniques

**Faits sourcés S-L, au 7 octobre 2026 :** Laravel 13.35.0, Blade, PHP CLI 8.5.11, Vite 8.3.3 et Tailwind 4.3.3 ; fichiers de verrouillage et dépendances présents. Page d'attente FR/EN/DE, racine redirigée vers FR, endpoint de santé et `noindex`. Les fonctions de vente et le schéma métier sont absents.

MariaDB XAMPP 10.4.28, son socket et le port 3307 ont été vérifiés après correction ; phpMyAdmin fonctionne. Le PHP XAMPP 8.2.4 ne satisfait pas le minimum PHP 8.3 déclaré pour Laravel 13 : Laravel utilise actuellement le PHP CLI distinct. **Inconnu :** accès applicatifs et base HOMYA. phpMyAdmin fonctionnel ne prouve pas leur disponibilité.

Git : branche `main`, aucun commit et fichiers applicatifs non suivis à l'inspection. Préserver tous les fichiers, secrets locaux et services existants ; ne pas recréer le projet. Hébergement, domaine, exploitation et procédure de déploiement : **Inconnu**.

## 5. Domaines applicables et conditionnels

| Domaine | Applicabilité | État et dépendances |
|---|---|---|
| E-commerce | Applicable | Cible explicite ; règles de vente Décision nécessaire, fonctions non implémentées. REQ-001, REQ-005 à REQ-019. |
| Données personnelles | Applicable au parcours de vente projeté | Données, finalités, destinataires et conservation À confirmer ; aucune base client importée. REQ-024. |
| Paiements | Applicable | Carte et virement demandés ; prestataire et procédures Décision nécessaire. REQ-013 à REQ-015. |
| Réservation de prestations/créneaux | Non applicable au périmètre actuel | Aucune offre réservable ni calendrier demandés. |
| Réservation temporaire de stock | Conditionnel / à vérifier | Dépend du modèle d'approvisionnement et du virement ; durée et libération non décidées. REQ-010, REQ-014. |
| Comptes utilisateurs clients | Conditionnel / à vérifier | Compte facultatif, obligatoire ou achat invité Décision nécessaire. REQ-023. |
| Comptes d'administration | Applicable à l'administration proposée | Gestion de l'exploitation À confirmer ; rôles et personnes non définis. REQ-019, REQ-020. |
| SEO | Applicable à la future boutique publique | Prototype local non indexable ; architecture publique et validations à préparer. REQ-026. |
| Analytics | Conditionnel / à vérifier | Indicateurs proposés, finalités et outil non décidés ; aucun tracking vérifié. REQ-027. |
| Services Google | Conditionnel / à vérifier | Services précis et comptes Information manquante ; préparation séparée de toute approbation. REQ-028. |
| Accessibilité | Applicable comme objectif de qualité recommandé | Niveau de référence et périmètre juridique À confirmer ; aucune certification annoncée. REQ-004. |
| Sécurité | Applicable | Vente, paiements, administration et données ; contrôles à vérifier lors de l'implémentation. REQ-020, REQ-024, REQ-025. |
| Hébergement | Applicable au lancement ; Information manquante | Prestataire, offre et capacités non choisis ; Hostinger non présumé. REQ-031. |

## 6. Pages et parcours proposés

Les noms ci-dessous désignent les besoins de pages, pas des URLs publiques adoptées.

| Parcours | Pages ou étapes | Exigences et critères liés |
|---|---|---|
| PAR-01 Découverte | Accueil → univers/catégorie → recherche, filtres et résultats | REQ-001 à REQ-006 ; AC-001 à AC-006. |
| PAR-02 Choix du produit | Fiche → caractéristiques, unités, disponibilité → quantité → panier | REQ-006 à REQ-010 ; AC-006 à AC-010. |
| PAR-03 Achat carte | Panier → coordonnées/destination → livraison et total → carte → résultat → confirmation | REQ-008 à REQ-013, REQ-015, REQ-016, REQ-021 ; critères correspondants. |
| PAR-04 Achat virement | Même parcours → instructions de virement → attente → rapprochement → résultat communiqué | REQ-008 à REQ-012, REQ-014 à REQ-016, REQ-021 ; critères correspondants. |
| PAR-05 Après achat | Confirmation → suivi → contact → demande de retour ou assistance, selon règles acceptées | REQ-016 à REQ-018, REQ-021, REQ-024. |
| PAR-06 Exploitation | Connexion administration → gestion catalogue et commandes → actions autorisées | REQ-019, REQ-020, REQ-025, REQ-029. |
| PAR-07 Compte client conditionnel | Inscription/connexion → profil/adresses/historique/récupération, si retenus | REQ-023 à REQ-025 ; absent du socle actuel. |

Pages complémentaires **À confirmer** : présentation HOMYA, contact, livraison/retours, FAQ et documents commerciaux/confidentialité/cookies selon l'analyse juridique. REQ-018, REQ-021, REQ-024, REQ-033. Les pages produit et catégorie devront être disponibles dans les langues demandées ; aucune traduction commerciale complète n'est encore validée.

## 7. Exigences et critères de recette

Les identifiants REQ et AC sont stables. Une évolution conservera l'identifiant d'une exigence inchangée et tracera explicitement les remplacements. Tous les critères ci-dessous sont **proposés pour une future recette**, sans résultat d'exécution.

Priorités recommandées par dépendance, sans score ni délai inventé : **F — Fondation**, nécessaire avant de figer la solution ; **V — Vente**, nécessaire pour un parcours de vente fiable ; **C — Conditionnelle**, dépend d'un choix explicite. Le pilote indiqué est le domaine propriétaire ; les personnes habilitées restent à désigner.

### Catalogue et parcours

| Exigence et résultat attendu | Source et statut | Priorité motivée / pilote | Dépendances | Critère observable |
|---|---|---|---|---|
| REQ-001 — Représenter tous les rayons retenus de DAMDAM, dont bois/combustibles, chauffage, électroménager, mobilier et TV. | S-D/004, Décision validée ; inventaire Inconnu. | F : détermine la structure ; E-commerce. | Liste des rayons et produits HOMYA approuvée. | AC-001 : l'arborescence couvre chaque rayon de la liste approuvée et permet de retrouver ses références sans données inventées. |
| REQ-002 — Utiliser une identité HOMYA originale dans l'interface cible. | S-D/005, Décision validée ; charte À confirmer. | F : distingue la cible de sa référence ; Design. | Actifs HOMYA et contenus validés. | AC-002 : logo, nom et composants publics utilisent l'identité retenue ; aucun élément DAMDAM restant n'est présenté comme identité HOMYA. |
| REQ-003 — Prévoir les parcours et contenus FR/EN/DE et les montants en EUR. | S-B, demande confirmée ; couverture et traductions À confirmer. | F : influe sur contenu, données et parcours ; E-commerce/Design. | Traductions approuvées et REQ-009. | AC-003 : PAR-01 à PAR-05 sont parcourables dans les trois langues avec libellés cohérents et montants en EUR ; les contenus manquants sont identifiés avant publication. |
| REQ-004 — Permettre l'usage sur mobile/ordinateur et au clavier. | S-M, recommandation À confirmer ; référentiel Décision nécessaire. | V : rend le parcours utilisable ; Design/Quality. | Liste des écrans, tailles et niveau d'accessibilité acceptés. | AC-004 : sur les configurations de recette retenues, les étapes critiques restent lisibles sans débordement bloquant ; actions accessibles au clavier, focus visible, champs nommés et erreurs compréhensibles. |
| REQ-005 — Explorer le catalogue avec recherche, filtres et tri pertinents. | S-O, adaptation S-D/005 ; À confirmer. | V : aide au choix dans une offre étendue ; E-commerce. | REQ-001, données réelles, attributs/filtres retenus. | AC-005 : un jeu de références validées produit les résultats attendus ; filtres et tri correspondent aux données ; absence de résultat et remise à zéro sont compréhensibles. |
| REQ-006 — Présenter une fiche produit avec médias sourcés, référence, informations commerciales et caractéristiques validées. | S-O, S-D/002 et S-B ; contenu exact À confirmer. | V : permet une décision d'achat informée ; E-commerce. | REQ-001, REQ-009 à REQ-011, provenance et REQ-033. | AC-006 : les informations visibles concordent avec la fiche métier approuvée ; les champs inconnus ne sont pas remplacés par des valeurs ou promesses fictives. |
| REQ-007 — Représenter unités, conditionnements, variantes et attributs selon chaque famille réellement vendue. | S-B, recommandation S-M ; Décision nécessaire. | F : évite quantités et comparaisons incohérentes ; E-commerce/Database Engineering. | Données et règles de vente par famille. | AC-007 : une commande d'un produit échantillon de chaque famille utilise l'unité et le conditionnement approuvés ; aucune conversion de poids/volume non définie n'est appliquée. |
| REQ-008 — Ajouter, modifier et retirer des produits du panier. | S-O, adaptation S-D/005 ; À confirmer. | V : préalable à l'achat ; E-commerce. | REQ-006, REQ-007, REQ-010 ; persistance du panier à décider. | AC-008 : PAR-02 permet les trois actions et affiche le récapitulatif exact ; quantité invalide, produit indisponible ou panier vide produisent un état explicite. |
| REQ-009 — Calculer et contrôler les montants critiques côté serveur. | S-M, recommandation technique À confirmer ; règles de calcul Inconnu. | V : protège l'intégrité de l'achat ; E-commerce/Security. | Prix, taxes, frais et arrondis approuvés ; REQ-007, REQ-011. | AC-009 : altérer un prix ou total envoyé par le navigateur ne modifie pas le montant calculé ; panier, récapitulatif, paiement et commande concordent selon les règles retenues. |
| REQ-010 — Contrôler disponibilité et quantités selon le modèle d'approvisionnement. | S-B, S-M ; Décision nécessaire. | F puis V : conditionne la promesse et les ventes ; E-commerce. | Source des stocks ; moment de contrôle/déduction ; réservation et annulation. | AC-010 : les scénarios acceptés de rupture, achats concurrents et annulation aboutissent aux résultats documentés sans perte de stock ni vente dépassant les règles approuvées. |
| REQ-011 — Déterminer les destinations desservies et les modalités de livraison. | S-B, trois marchés demandés ; Décision nécessaire. | F puis V : nécessaire au coût total ; E-commerce. | Origines, transporteurs, conditionnements, frais et zones approuvés. | AC-011 : chaque destination de recette obtient les méthodes, frais et informations validés ; une destination non desservie est signalée avant paiement. |
| REQ-012 — Recueillir les informations nécessaires à la commande et présenter le récapitulatif avant engagement. | S-B, S-O, recommandation S-M ; À confirmer. | V : termine PAR-03/PAR-04 ; E-commerce. | REQ-008 à REQ-011, REQ-023, REQ-024, REQ-033. | AC-012 : l'acheteur peut corriger ses informations ; les champs invalides sont signalés ; le récapitulatif affiche les articles, quantités, destination, mode de paiement et montants cohérents. |

### Vente et exploitation

| Exigence et résultat attendu | Source et statut | Priorité motivée / pilote | Dépendances | Critère observable |
|---|---|---|---|---|
| REQ-013 — Proposer le paiement par carte de crédit/débit via un prestataire choisi. | S-B, demande confirmée ; prestataire Décision nécessaire. | V : moyen de paiement demandé ; E-commerce/Security. | Prestataire, contrat, accès de test et REQ-009, REQ-012, REQ-015. | AC-013 : en environnement de test, succès, refus et abandon donnent le résultat attendu ; une page de retour seule ne suffit pas à déclarer un paiement confirmé. |
| REQ-014 — Proposer le virement avec rapprochement et information cohérente de l'acheteur. | S-B, demande confirmée ; procédure Décision nécessaire. | V : moyen de paiement demandé ; E-commerce. | Référence, bénéficiaire validé hors secrets, responsable, délais et REQ-010. | AC-014 : instructions approuvées disponibles ; commande reçue distincte du paiement confirmé ; réception, absence, écart ou doublon de virement suivent la procédure validée. |
| REQ-015 — Éviter les doubles effets de commande et paiement ; contrôler les confirmations. | S-M, recommandation À confirmer. | V : protège commandes et paiements ; E-commerce/Security. | REQ-013, REQ-014 et contrat technique du prestataire. | AC-015 : répétition d'une requête ou notification de test ne crée pas de double commande, paiement ou effet de stock ; confirmation invalide refusée ; montant et devise contrôlés. |
| REQ-016 — Conserver l'historique de commande et appliquer les transitions autorisées. | S-O, S-M ; états et transitions Décision nécessaire. | V : permet l'exploitation et le suivi ; E-commerce. | REQ-009 à REQ-015, responsable et matrice des états. | AC-016 : une commande conserve les valeurs de son achat après modification du catalogue ; une transition interdite est refusée et les actions acceptées sont traçables. |
| REQ-017 — Donner accès au suivi prévu sans divulguer d'autre commande. | S-O, adaptation S-D/005 ; À confirmer. | V : complète le parcours après achat ; E-commerce/Security. | REQ-016, source de suivi, méthode d'accès acceptée. | AC-017 : les statuts correspondent à la source retenue ; identifiant inconnu ou accès non autorisé ne révèle aucune information d'une autre commande. |
| REQ-018 — Présenter les conditions validées de livraison, retours et assistance. | S-O, S-M ; règles Inconnu. | V : conditionne l'information et le traitement après achat ; E-commerce/Legal & Compliance. | Entreprise, règles commerciales/juridiques vérifiées, REQ-011 et REQ-033. | AC-018 : les conditions approuvées sont cohérentes entre produit, panier et pages d'information ; les demandes sont traitées selon la procédure acceptée. |
| REQ-019 — Prévoir l'administration utile au catalogue et aux commandes. | S-M, proposition ; À confirmer, administration DAMDAM non observée. | V : besoin d'exploitation recommandé ; E-commerce. | REQ-001, REQ-006 à REQ-018, REQ-020 ; responsabilités à désigner. | AC-019 : les opérations retenues peuvent être réalisées par l'exploitant habilité ; modification publiée visible correctement ; état non publiable empêché selon les critères validés. |
| REQ-020 — Contrôler les permissions et tracer les opérations sensibles. | S-M, recommandation À confirmer. | V : protège l'administration et les commandes ; Security. | Matrice de rôles, REQ-019, REQ-024 ; accès authentifié. | AC-020 : un utilisateur sans droit ne peut pas modifier prix, stock, statut ou accès ; les actions autorisées retenues sont journalisées sans secrets. |
| REQ-021 — Prévoir contact et emails transactionnels adaptés au cycle de commande. | S-B email, S-O contact ; À confirmer, fournisseur Inconnu. | V : informe l'acheteur ; E-commerce. | Coordonnées réelles, fournisseur, messages/traductions, REQ-016. | AC-021 : dans un canal de test, le message attendu est émis au bon état et avec la bonne référence ; aucun paiement non reçu n'est annoncé payé ; échec d'envoi visible à l'exploitant. |
| REQ-022 — Définir l'usage et les échanges CRM avant connexion. | S-B ; Décision nécessaire. | C : dépend de la finalité commerciale ; Specification/E-commerce. | Prestataire, catégories de données, REQ-024 et REQ-025. | AC-022 : si retenu, une fiche de flux approuvée et un test montrent les données nécessaires, destinataires et traitement des erreurs ; aucun secret dans les échanges de preuve. |
| REQ-023 — Décider du compte client et de l'achat invité. | S-S/S-M ; Décision nécessaire. | C mais arbitrage avant checkout : influe sur accès et parcours ; E-commerce. | Choix utilisateur, REQ-012, REQ-020, REQ-024. | AC-023 : le checkout suit le mode approuvé ; si comptes retenus, connexion, récupération et accès aux seules données autorisées passent les scénarios de recette. |

### Qualité, données et préparation au lancement

| Exigence et résultat attendu | Source et statut | Priorité motivée / pilote | Dépendances | Critère observable |
|---|---|---|---|---|
| REQ-024 — Définir et limiter les traitements de données personnelles. | S-M, traitement projeté ; finalités, destinataires et conservation Inconnu. | F avant collecte : conditionne les flux ; Legal & Compliance/Security. | REQ-012, REQ-021 à REQ-023, REQ-027, REQ-033. | AC-024 : chaque champ collecté possède finalité, accès et durée/procédure approuvés ; parcours et destinations observés concordent avec cette cartographie ; événements et erreurs n'exposent pas de données inutiles. |
| REQ-025 — Protéger entrées, accès, sessions, secrets et intégrations. | S-M, recommandation technique À confirmer ; règles locales actives S-L. | V : protège les flux sensibles ; Security. | REQ-020, REQ-024, surfaces et prestataires retenus. | AC-025 : une revue et les scénarios autorisés vérifient validation serveur, accès, formulaires, erreurs et secrets ; tout écart est décrit et traité ou accepté explicitement dans sa portée. |
| REQ-026 — Concevoir les pages publiques pour leur compréhension et indexabilité. | S-M, recommandation À confirmer ; noindex local observé S-L. | F puis V : influe sur arborescence et contenu ; SEO. | REQ-001 à REQ-006, URLs/langues et contenus approuvés. | AC-026 : titres, descriptions, headings, liens, URLs et variantes linguistiques sont cohérents ; sitemap/canonicals/données structurées selon périmètre retenu ; environnement local protégé de l'indexation, ouverture publique validée séparément. |
| REQ-027 — Définir un plan de mesure des objectifs et du parcours. | S-S/S-M ; outil et collecte Décision nécessaire. | C, à concevoir avant tracking : évite incohérences ; Analytics. | KPI retenus, REQ-015, REQ-024, REQ-033 et outil. | AC-027 : si mesure retenue, événements de recette correspondent aux actions, montants et devise ; doublons de conversion contrôlés, limites de couverture décrites, achat rapproché de la source métier. |
| REQ-028 — Évaluer chaque service Google selon son usage et ses prérequis. | S-B Google, S-M ; Décision nécessaire, comptes Inconnu. | C : aucun service choisi par défaut ; Google Services. | REQ-006, REQ-010, REQ-011, REQ-026, REQ-027 et comptes. | AC-028 : chaque service reçoit une applicabilité, un état de préparation et des preuves propres ; préparation, approbation et diffusion ne sont pas confondues. |
| REQ-029 — Définir sauvegarde, restauration et gestion des incidents. | S-M, recommandation À confirmer. | V avant données réelles : préserve l'exploitation ; Security/Quality. | Hébergement, données, responsables, durées et objectifs de reprise. | AC-029 : une restauration sur environnement isolé retrouve les données attendues du jeu de recette ; procédure d'incident et responsabilités sont documentées ; aucune garantie de reprise non testée. |
| REQ-030 — Conserver le socle local Laravel et la cible MariaDB XAMPP vérifiée. | S-D/001, /006, /007, /008, Décisions validées. | F : contrainte technique confirmée ; Development/Database Engineering. | Base/droits applicatifs, modèle validé et autorisations séparées. | AC-030 : lors de la future connexion applicative, un contrôle ciblé confirme le bon serveur XAMPP et l'accès prévu ; aucun recours silencieux à MySQL Homebrew ou à SQLite ; commandes locales reproductibles documentées. |
| REQ-031 — Vérifier l'environnement de production avant de choisir le déploiement. | S-B local d'abord ; hébergement Inconnu. | C maintenant, V avant lancement : évite une architecture incompatible ; Development. | Offre réelle, PHP/extensions, base, stockage, tâches, email, domaine et responsabilités. | AC-031 : la fiche de capacités de l'offre choisie est appuyée par documentation et accès réel lorsque nécessaire ; limites ouvertes et plan de déploiement soumis avant publication. |
| REQ-032 — Mesurer la performance sur les scénarios et ressources retenus. | S-M, recommandation À confirmer ; budgets/volumétrie Inconnu. | V avant lancement : influe sur l'usage ; Performance/Quality. | Catalogue et médias réels, profils de recette, environnement. | AC-032 : mesures de chargement, interaction et réponse sont consignées avec conditions ; respect des budgets acceptés vérifié ; absence de cible ne permet pas de déclarer la performance validée. |
| REQ-033 — Vérifier les juridictions, documents et informations commerciales applicables. | S-B marchés, S-M ; applicabilité juridique À vérifier, décisions nécessaires. | V avant vente publique : conditionne les engagements ; Legal & Compliance. | Identité vendeur, B2C/B2B, produits, expéditions, données et prestataires. | AC-033 : questions, sources officielles actuelles, juridictions, interprétations et validations compétentes sont tracées ; textes publiés approuvés ; aucune conformité générale déclarée. |
| REQ-034 — Recetter les parcours retenus et préserver l'existant. | S-M, règles globales et locales ; recommandation À confirmer. | V : condition de livraison fonctionnelle ; Quality. | Toutes les exigences retenues et environnement de test. | AC-034 : chaque critère applicable possède scénario, résultat et preuve ; erreurs critiques corrigées ou exclusion explicite ; régression de la page existante et des langues contrôlée. |
| REQ-035 — Décider des promotions et avis avant de les intégrer. | S-O, recommandations S-M ; Décision nécessaire. | C : ni mécanique ni preuve HOMYA validées ; E-commerce. | Règles de promotion, provenance des avis, REQ-009 et REQ-033. | AC-035 : si retenus, calculs et conditions de promotion suivent les règles acceptées ; seuls des avis utilisables et attribués fidèlement apparaissent, sans présenter des avis DAMDAM comme achats HOMYA. |

## 8. Règles métier à définir

Les contraintes techniques de calcul serveur et de contrôle des accès sont proposées dans les exigences ; elles ne fixent pas les règles commerciales suivantes.

| Sujet | État | Décision et impact |
|---|---|---|
| Vendeur et modèle d'approvisionnement | Décision nécessaire | Stock propre, commande fournisseur ou expédition fournisseur ; influe sur REQ-010 à REQ-018 et REQ-033. |
| Catalogue vendable au lancement | Inconnu | Références réelles, produits neufs/autres états, unités, variantes et visibilité ; REQ-001, REQ-006, REQ-007. |
| Prix, taxes, arrondis et frais | Inconnu ; Bloquante pour les totaux de vente | Définir sources, traitements et présentation par contexte de vente ; REQ-009 à REQ-014. Aucun taux ou prix repris automatiquement. |
| Stock et réservation | Décision nécessaire | Moment du contrôle, déduction, éventuelle réservation, libération et concurrence ; REQ-010, REQ-014 à REQ-016. Aucune durée par défaut. |
| Livraison | Inconnu ; Bloquante pour le checkout livrable | Destinations, origine, transport, contraintes par produits, frais et délais annoncés ; REQ-011, REQ-018. Aucune livraison gratuite présumée. |
| Commande et paiements | Décision nécessaire | États, transitions, confirmation, rapprochement du virement, erreurs, annulation/remboursement et responsabilités ; REQ-013 à REQ-017. |
| Retours, garanties, SAV | Inconnu ; Bloquante avant vente publique | Politique compatible avec l'offre et les règles vérifiées ; REQ-018, REQ-033. Les mentions DAMDAM divergentes ne définissent pas la règle HOMYA. |
| Clients et exploitation | Décision nécessaire | Achat invité ou compte, B2B éventuel, droits et traitement des demandes ; REQ-019 à REQ-024. |

## 9. Données et rôles proposés

Cette section est une description fonctionnelle **Hypothèse**, pas un MCD/MLD validé ni un schéma de tables. Les relations, types, cardinalités et contraintes seront définis après les arbitrages métier.

| Données envisagées | Source attendue et inconnues | Exigences |
|---|---|---|
| Rayons, produits, références, variantes et attributs | Export ou source métier DAMDAM/HOMYA à fournir ; unités et état Inconnu. | REQ-001, REQ-005 à REQ-007. |
| Descriptions, traductions, médias et provenance | Actifs déclarés réutilisables ; licence/provenance par actif et validation linguistique À confirmer. | REQ-002, REQ-003, REQ-006, REQ-035. |
| Prix, disponibilité, frais et règles de vente | Source de vérité, fréquence de mise à jour et responsables Inconnu. | REQ-009 à REQ-011. |
| Panier et informations de commande | Parcours projeté ; persistance et champs nécessaires À confirmer. | REQ-008, REQ-012, REQ-024. |
| Historique des articles, montants et états de commande | Valeurs à conserver au moment de l'achat ; règles de conservation Inconnu. | REQ-016, REQ-024. |
| Références et états de paiement | Prestataire/rapprochement choisis ; pas de collecte inutile de données bancaires. | REQ-013 à REQ-015, REQ-025. |
| Suivi, demandes de support et retours | Sources logistiques et procédures Inconnu. | REQ-017, REQ-018, REQ-021. |
| Rôles, opérations sensibles, données CRM/mesure | Finalités, accès, destinataires et conservation à décider. | REQ-020, REQ-022, REQ-024, REQ-027. |

Permissions proposées, **À confirmer** : visiteur limité aux contenus publics et à son parcours ; acheteur limité aux commandes auxquelles il est autorisé à accéder ; exploitant catalogue limité à ses fonctions ; exploitant commandes/support limité au traitement retenu ; administrateur technique limité à la maintenance et aux accès nécessaires. Aucun droit universel ou rôle financier attribué par défaut. Les possibilités d'annulation, remboursement, export et modification d'accès demandent des droits explicitement acceptés. REQ-020 et AC-020 permettront de vérifier la matrice finale côté serveur.

## 10. Intégrations et services Google

| Intégration | État | Données, dépendances et contrôle |
|---|---|---|
| Carte | Décision nécessaire | Prestataire et accès de test ; montant, EUR, référence et résultat selon contrat ; REQ-013, REQ-015. |
| Virement | Demande confirmée, procédure À confirmer | Référence et rapprochement ; bénéficiaire vérifié séparément ; REQ-014. |
| Email transactionnel | Fournisseur Inconnu | Messages nécessaires à la commande, envois de test avant réel ; REQ-021, REQ-024. |
| CRM | Finalités et produit Décision nécessaire | Minimisation, destinataires et reprise après erreur ; REQ-022, REQ-024. Brevo non choisi. |
| Transport/suivi | Conditionnel / à vérifier | API, flux ou saisie manuelle non décidés ; REQ-011, REQ-017. |
| Analytics | Outil et finalités Décision nécessaire | Événements et rapprochement achats ; REQ-024, REQ-027. GA4/GTM non sélectionnés automatiquement. |
| Google | Services demandés sans liste validée | Évaluation séparée ci-dessous ; REQ-028. |

| Service Google | Applicabilité | Préparation actuelle et condition |
|---|---|---|
| Search | Applicable à la future boutique publique | Non évalué pour la boutique cible ; REQ-026. Le prototype local reste non indexable. |
| Search Console | Conditionnel / à vérifier | Non évalué ; domaine public, propriété et accès Inconnu. |
| Données structurées produit | Conditionnel / à vérifier | Non évalué ; produits et offres réels, cohérence prix/disponibilité ; REQ-006, REQ-009, REQ-010. |
| Merchant Center, Free Listings, Shopping | Conditionnel / à vérifier | Non évalué ; pays, comptes, données produit, livraison/retours et règles officielles à examiner au moment du choix. |
| GA4/GTM | Conditionnel / à vérifier | Non évalué ; plan Analytics et analyse des traitements avant implémentation. |
| Google Ads / Performance Max | Conditionnel / à vérifier | Non évalué ; objectif, budget et autorisation publicitaire non fournis. Aucune dépense incluse. |
| Business Profile | Information manquante | Non évalué ; modèle d'activité et possibilité de contact physique avec les clients Inconnu. Aucun magasin présumé obligatoire. |
| Local Inventory | Information manquante | Non évalué ; présence d'un magasin, stock local, pays et programme à vérifier séparément. |

Aucun statut de préparation ci-dessus ne signifie acceptation, indexation, diffusion ou classement par Google. Les règles changeantes devront être vérifiées auprès des sources officielles du service retenu ; aucune nouvelle recherche de politique Google n'est réalisée pour cette version.

## 11. Indicateurs et contraintes non fonctionnelles

**Indicateurs recommandés, À confirmer :** aucune valeur initiale, cible chiffrée ou période n'est validée. Les sources ci-dessous sont prévues, pas connectées.

| Indicateur | Source envisagée | Dépendance |
|---|---|---|
| Visites par canal, pays et langue | Outil Analytics choisi ; Search Console pour la recherche Google | REQ-027, REQ-028 ; couverture à préciser. |
| Progression produit → panier → checkout | Événements du site | REQ-005 à REQ-012, REQ-027. |
| Commandes effectivement payées et conversion | Commandes, prestataire carte et rapprochement bancaire ; Analytics pour les sessions mesurées | REQ-013 à REQ-016, REQ-027. Virement attendu distinct d'un paiement reçu. |
| Ventes nettes et panier moyen | Commandes/remboursements rapprochés avec la comptabilité | Périmètre des montants et traitement fiscal à valider. |
| Contribution économique | Coûts d'achat, logistique, paiement et acquisition réellement disponibles | Conditionnelle : coûts et définition Inconnu. |
| Exécution, annulations et retours | Commandes, logistique et support | REQ-011, REQ-016 à REQ-018. |
| Réachat | Historique des commandes avec identification client maîtrisée | Période et identification À confirmer ; REQ-023, REQ-024. |

Autres contraintes : interface responsive/accessibilité REQ-004 ; performances sans seuil inventé REQ-032 ; protection et accès REQ-020/024/025 ; sauvegarde/reprise REQ-029 ; maintenabilité par composants partagés, fichiers de verrouillage et documentation S-L ; compatibilité locale REQ-030 et production REQ-031. Volumétrie, disponibilité de service, objectifs de reprise et budget de performance : **Inconnu**, à décider avant les engagements correspondants.

## 12. Juridictions et questions à vérifier

**Marchés sourcés S-B :** France, Belgique, Allemagne. **Inconnu :** pays d'établissement du vendeur, entrepôts, origines d'expédition, prestataires et lieux de traitement. Le cadre européen et les règles nationales pertinentes devront être examinés séparément. Cette grille n'est pas une liste d'obligations applicables ni un avis de conformité.

| Question de recherche à instruire | Données préalables / dépendances |
|---|---|
| Quelles informations et conditions de vente s'appliquent au vendeur, au public B2C/B2B et aux marchés retenus ? | Entreprise, clientèle, destinations ; REQ-012, REQ-018, REQ-033. |
| Quel traitement des prix, taxes, factures et ventes transfrontalières correspond au modèle réel ? | Établissement, expéditions, statut fiscal et acheteurs ; REQ-009. Confirmation compétente requise avant règles de calcul. |
| Quelles règles de livraison, retours, garanties et assistance s'appliquent à chaque famille réellement proposée ? | Produits, état, services et logistique ; REQ-007, REQ-011, REQ-018. |
| Quelles informations, règles de sécurité produit, environnement/énergie ou de reprise sont pertinentes pour les combustibles et appareils retenus ? | Inventaire, fabricants, rôle vendeur/importateur et pays ; REQ-006, REQ-033. Aucun dispositif réputé applicable par défaut. |
| Quelles règles concernent données personnelles, cookies, mesure, emails et transferts des prestataires choisis ? | Cartographie des finalités/flux et prestataires ; REQ-021 à REQ-028. |
| Quels droits et justificatifs couvrent marque, photos, descriptions et éventuels avis réutilisés ? | Actifs précis et déclaration S-D/002 ; REQ-002, REQ-006, REQ-035. |
| Quelles exigences d'accessibilité juridique concernent effectivement l'activité et le service ? | Entreprise, service, public et pays ; REQ-004, REQ-033. |

Toute conclusion juridique actuelle devra citer une source officielle avec date, juridiction, portée et interprétation séparée. Une question complexe, le droit étranger, un conflit de sources ou une interprétation à enjeu nécessite confirmation par un professionnel ou organisme compétent. Aucun avis professionnel ni audit de conformité n'est acquis dans cette version.

## 13. Risques et dépendances

| Risque ou inconnue | Qualification et impact | Exigences affectées |
|---|---|---|
| Données produits non disponibles ou incohérentes | Inconnu ; Bloquante pour publier un catalogue vendable, pas pour ce document. | REQ-001, REQ-006 à REQ-010. |
| Capacités de livraison et retours non définies | Inconnu ; Bloquante pour le checkout et les engagements publics. | REQ-011, REQ-018, REQ-033. |
| Absence de responsables, fournisseur carte ou rapprochement | Décision nécessaire ; Bloquante pour un paiement exploitable. | REQ-013 à REQ-021. |
| Comptes et permissions indéterminés | Décision nécessaire ; risque d'accès inapproprié si implémentés sans matrice. | REQ-019, REQ-020, REQ-023 à REQ-025. |
| Confusion entre base XAMPP et base applicative | Fait S-L : seul le serveur est vérifié ; Bloquante pour annoncer la connexion HOMYA. | REQ-030. |
| Hébergement incompatible ou non choisi | Inconnu ; Bloquante avant déploiement. | REQ-031. |
| Manque de références juridiques propres au modèle | À vérifier ; Bloquante avant vente publique pour les conditions concernées. | REQ-024, REQ-033. |
| Budget, délai et disponibilité de l'équipe inconnus | Inconnu ; aucune estimation fiable de livraison possible. | Priorisation et périmètre du lancement. |
| Travail local sans commit | Fait S-L ; historique Git absent, préservation à organiser sans commit dans cette phase. | REQ-029, REQ-034. |

Ordre recommandé par dépendance : arbitrages commerciaux et données → périmètre du lancement et rôles → modèle conceptuel/logique et architecture → UX/UI → plan de développement et autorisation → implémentation/recette → préparation au lancement → autorisation de déploiement. SEO, qualité, sécurité et traitements des données interviennent dès les étapes qu'ils conditionnent. Aucune date ni estimation n'est fixée ici.

## 14. Évolutions différées

**Propositions, non décisions de report définitives :** comptes avancés, avis, promotions, fidélité, recommandations produit, automatisations marketing, campagnes payantes et fonctions professionnelles. Chaque évolution dépend d'un besoin validé, de données et de moyens réels. Les devis et bons de commande professionnels resteront distincts des commandes e-commerce s'ils sont retenus.

Le choix de police, les variantes du logo, le détail de la charte et d'autres préférences visuelles réversibles ne bloquent pas cette version. Leur validation ne résoudra pas les inconnues de vente.

## 15. Arbitrages structurants et validation

| Groupe à arbitrer | Questions regroupées | Exigences dépendantes |
|---|---|---|
| A — Modèle de vente et lancement | Qui est le vendeur ? Particuliers seuls ou aussi professionnels ? Stock propre ou autre approvisionnement ? Quels produits et pays effectivement ouverts à la vente au lancement ? | REQ-001, REQ-007, REQ-010 à REQ-018, REQ-033. |
| B — Données et exploitation | Quelle source fournit références, prix, stocks, unités et médias ? Qui valide, traduit et met à jour ? Qui traite commandes, assistance et retours ? | REQ-003, REQ-005 à REQ-011, REQ-016 à REQ-021. |
| C — Transaction et logistique | Quels calculs et conditions de livraison/retour ? Quel prestataire carte ? Quelle procédure de virement ? Quels états, réservations et droits d'annulation/remboursement ? | REQ-009 à REQ-018, REQ-020, REQ-033. |
| D — Accès et traitements | Achat invité, compte facultatif ou obligatoire ? Quels rôles et droits ? Quelles finalités email/CRM/Analytics, destinataires et règles de conservation vérifiées ? | REQ-019 à REQ-027. |
| E — Moyens et mise en service | Quels responsables, budget et échéance souhaitée ? Quel environnement de production ? Quels objectifs de performance/reprise et services Google retenir ? | REQ-028 à REQ-034. |

**Validation attendue :** accepter le contenu et la portée de la version 0.1, identifier les propositions retenues et préciser les exclusions ou réserves. Une validation partielle indiquera les groupes et exigences couverts ; elle ne transformera pas les inconnues en valeurs acceptées. Les décisions explicitement confirmées seront ensuite enregistrées et relues dans la section HOMYA du registre canonique, sans doublon.

**État de sortie :** version proposée pour revue ; pas de validation globale reçue. Les inconnues n'empêchent pas cette première version, mais celles qualifiées Bloquantes devront être traitées ou explicitement exclues de la phase concernée. Une validation documentaire, même complète, n'autorise pas encore le développement, la création de base, les migrations, l'accès à des services, une dépense ou le déploiement.
