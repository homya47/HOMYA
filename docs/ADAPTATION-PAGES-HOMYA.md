# HOMYA — adaptation des pages et thème jaune

08/10/2026 — instruction explicite consignée dans DECISION-048 du registre Specification existant. Ce document décrit le résultat et ses limites ; il ne crée pas un registre parallèle.

## Demande et données conservées

Adapter les pages publiques de la référence autorisée aux fichiers et à la base HOMYA existants, corriger les défauts observés et utiliser du jaune plutôt que du vert. Projet source : `/Users/akm/Documents/ChatGPT/HOMYA` ; copie servie : `/Applications/XAMPP/xamppfiles/htdocs/HOMYA` ; accès local `http://127.0.0.1/HOMYA/` redirigé vers `http://127.0.0.1:8080/fr`.

L’archive et ses traductions, le profil validé et les accès SQL ne sont pas réimportés. Le catalogue conserve3709références ; les prix restent les données archivées, pas une nouvelle validation commerciale. MariaDB XAMPP est utilisée en lecture seule par les gardes existantes. Aucune migration, écriture SQL, activation de ventes ni modification des services.

## Observation sourcée et qualification

Consultation08/10/2026 par extraction web des pages publiques, puis navigateur pour catalogue et provenance. Les extractions peuvent être mises en cache : un décompte de capture ancienne ne vaut pas inventaire actuel. Le catalogue vu aujourd’hui au navigateur présente3709références. Aucun compte, message, commande ou paiement n’a été créé sur la référence.

| Famille / source | Observations et action HOMYA |
|---|---|
| [Accueil](https://www.damdam.fr/) | ADAPTER : illustration autorisée, univers,12produits phares,8autres modèles, blocs de découverte. REMPLACER la marque et les promesses non confirmées. |
| [Catalogue](https://www.damdam.fr/catalogue), [chauffage](https://www.damdam.fr/chauffage) | AMÉLIORER : contexte d’univers, visuel existant, catégories réelles et compteurs, filtres bornés, cartes cohérentes, pagination conservant le contexte. Les URL de pagination2–5 et suivante sont distinctes au DOM natif : aucun défaut de destination n’est affirmé sur ce point. |
| [Fiche Samsung](https://www.damdam.fr/produit/froid-rb33b610esa-samsung-combines-congelateur-en-bas) | ADAPTER : galerie et données existantes, description/caractéristiques séparées, navigation vers sections présentes,4autres modèles de même catégorie maximum. Pas d’avis, remise, stock ou livraison attribués à HOMYA. Les unités de dimensions contradictoires dans la présentation source ne sont pas arbitrairement réconciliées. |
| [Provenance](https://www.damdam.fr/provenance), [conseiller](https://www.damdam.fr/conseiller), [à propos](https://www.damdam.fr/a-propos) | REMPLACER les affirmations propres à l’entreprise source par présentation HOMYA et aide concrète au catalogue. FAQ native au clavier, sans chatbot ni conseiller inventé. |
| [Contact](https://www.damdam.fr/contact), [suivi](https://www.damdam.fr/suivi), [panier](https://www.damdam.fr/panier) | ADAPTER la place dans l’arborescence ; état HOMYA explicitement en préparation. Aucune coordonnée source, faux formulaire, numéro de suivi, frais ou total de panier. Fonctions transactionnelles non activées. |
| [Mentions](https://www.damdam.fr/mentions-legales), [CGV](https://www.damdam.fr/cgv), [confidentialité](https://www.damdam.fr/confidentialite), [rétractation](https://www.damdam.fr/retractation) | NE PAS REPRODUIRE les données juridiques d’une autre entreprise. Pages HOMYA accessibles mais explicitement non finalisées. La page de rétractation source explique un délai légal et une extension commerciale ; les mentions14/30jours des autres zones doivent être distinguées, pas traitées comme une règle HOMYA. |
| [Avis](https://www.damdam.fr/avis) | NE PAS REPRODUIRE les témoignages comme ceux de HOMYA. Page explicite sans collecte. |
| Livraison et pages de ville | Le lien général `/livraison` a répondu404 à l’extraction ; les pages locales de livraison sont des liens observés, pas des zones HOMYA confirmées. Pas de promesse géographique inventée. |
| Sélections, comparatifs, promotions | Liens source observés ; adaptation actuellement par filtres existants et produits de même catégorie. Aucun comparateur dédié, moteur promotionnel ni page ville SEO ajoutés sans données fiables. Leur clonage intégral reste différé. |

Limite : il s’agit d’une adaptation des familles de pages, pas d’un crawl vérifié de chaque URL source ni d’une certification de toutes ses fonctions internes. Les pages mobilier/froid et le sitemap n’ont pas été récupérés par l’outil web de cette recette ; les départements HOMYA utilisent les fichiers et catégories déjà disponibles.

## Résultat fonctionnel et design

- En-tête, recherche réelle, langues, menu et pied de page communs à toutes les pages.
- Palette jaune `#F5C842`, encre `#28241B`, blanc et neutres chauds ; textes sombres sur jaune, titres sobres, cartes produit homogènes. Les photos de produit ne sont pas recolorées.
- Accueil illustré et collections ; catalogue/rayons contextualisés ; filtres, états vides et erreurs conservés.
- Fiches : photos, référence, prix, catégorie/état archivés, description, caractéristiques et variantes si présentes ;4similaires maximum.
-13pages d’information disponibles en FR/EN/DE : présentation, FAQ, aide au choix, contact, livraison, retours, paiement, suivi, panier, avis, mentions, conditions et confidentialité. Les services non connectés ne présentent aucun formulaire factice.
-8titres de la sélection corrigés dans la projection éditoriale EN/DE : mots résiduels français traduits, modèle/puissance/prix inchangés. Règles protégées par empreintes exactes ; archive et dictionnaires conservés. Les slugs stables restent inchangés.
- Menu compact et défilable en petite hauteur, fermeture Échap et focus ; lien vers catégories ouvrant les filtres ; marges d’ancrage mesurées selon l’en-tête fixe. Lien d’évitement au-dessus de l’en-tête. Taille des notices et titres de cartes isolée des grands titres éditoriaux. Recherche Froid traduite en FR/EN/DE ; alias exacts conservant les165slugs du filtre français dans chaque langue.

## Preuves de recette

Répertoire de preuves : `output/qa-site-20261008/`. `installation*.json` consigne sauvegardes et empreintes des copies ; `http-native.json` contrôle3langues,60prix,127liens,33ressources locales,3pannes contrôlées et3refus403sur fichiers privés ; `controls-native.json` contrôle pagination2/155,13cartes à la dernière page, page excessive ramenée à155, résultats vides, plage de prix invalide422 et conservations de fichiers.

La suite hors base initiale de cette adaptation passe254tests/2619assertions. Un premier passage restreint a échoué sur86sockets factices refusés par le sandbox ; reprise hors sandbox réussie, sans MariaDB réelle. La régression finale passe257tests/2668assertions, erreurs/échecs/sauts0 (`tests-final.xml`). Le dernier build Vite réussit :CSS90,18Ko/18,21Ko gzip ; JS3,43Ko/1,25Ko gzip. Le contrôle final natif `http-final.json` revalide3accueils/60prix, les3recherches localisées (165résultats) et34fichiers installés. Les contrôles sont consolidés dans `verification.json`.

Revue navigateur :1440×1000,375×900 et768×1000 ; absence de débordement sur les écrans examinés. Galerie image2/8 chargée,4similaires présents, changement FR→EN→DE conservant `#product-specifications` et prix361,90€/361.90€, ouverture FAQ allemande, menu Échap et lien catégories/focus vérifiés. Captures enregistrées. Contrastes calculés des paires unies principales :encre/jaune9,73:1, blanc/encre15,46:1, texte secondaire/neutre6,63:1. Menu paysage667×375 défilable (225px visibles,371px de contenu) et fermetureÉchap contrôlés. Notices18px et titres de cartes16px desktop/14px mobile contrôlés après correction. Lien d’évitement :focus main et titre visible sous l’en-tête vérifiés.

Aucun score Core Web Vitals, audit exhaustif WCAG, conformité juridique ou acceptation Google revendiqués.

## Décisions validées et inconnues

Validé : reprise des pages, actifs autorisés, marque HOMYA, jaune, données locales existantes. Informations promises mais absentes : vendeur, contact, prix/TVA commerciaux, disponibilité fournisseurs, livraison/retours, prestataire de paiement et email. Elles conditionnent les fonctions de vente, conseiller/contact, suivi, textes légaux et publication. Hostinger est envisagé ; formule et domaine inconnus. Aucun déploiement.

Les documents commerciaux et juridiques doivent être établis pour HOMYA et examinés dans les juridictions France/Belgique/Allemagne avant ouverture. Cette phase ne certifie pas leur conformité et ne transforme pas l’accès à une page en service connecté.
