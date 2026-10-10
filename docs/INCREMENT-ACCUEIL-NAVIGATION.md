# HOMYA — lot accueil et navigation

7 octobre 2026 — implémentation locale de WF-01/WF-02, autorisée explicitement par l’utilisateur. Registre unique : [Specification, section HOMYA, DECISION-009](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). La validation concerne ce seul lot, pas l’ensemble du cahier des charges ou de l’architecture.

## Résultat et périmètre

Accueil Blade FR/EN/DE avec identité existante maison/flamme, palette forêt/crème/terre cuite et typographie système/Georgia. En-tête partagé, sélecteur de langue nommé, navigation des cinq univers, CTA vers les univers et footer. Menu mobile en disclosure, sans dialogue modal : Entrée/Espace, Échap, état `aria-expanded`, fermeture après navigation et focus sur la destination. Au passage mobile/desktop, aucune commande cachée ne conserve le focus. Le changement de langue conserve l’ancre d’univers avec JavaScript.

Les liens d’univers ciblent les blocs de l’accueil, pas des catégories contenant des références inexistantes. Le champ de recherche est désactivé, nommé et accompagné de l’explication « catalogue non publié ». Le statut de préparation précise l’indisponibilité des achats. Sans JavaScript, le HTML fournit une navigation visible et masque le bouton de repli ; cette structure est constatée dans la source, le navigateur sans JavaScript n’a pas été testé.

Aucun produit, prix, stock, avis, délai, prestataire ou photo ajouté. Aucune base, migration, configuration XAMPP ou `.env` modifiée ; aucune nouvelle dépendance. Pas de formulaire, paiement, compte, email, CRM, tracking ou déploiement. L’interface est rendue côté serveur : aucune opération asynchrone ne nécessite un état de chargement propre à ce lot. Le refus d’une langue inconnue et la redirection initiale restent ceux du socle.

## Traçabilité et critères observables

| Exigence / critère | Résultat vérifié et limite |
|---|---|
| REQ-001 / AC-001 | Les cinq univers confirmés sont présents et accessibles depuis le menu. Partiel : aucun inventaire détaillé ni recherche de références. |
| REQ-002 / AC-002 | HOMYA et la direction WF-01 sont appliqués au lot. Aucun élément d’identité DAMDAM ajouté ; aucune prétention de marque déposée. |
| REQ-003 / AC-003 ; UX-C04 | L’accueil, le menu et les libellés sont disponibles en FR/EN/DE, avec langue active nommée. Partiel : aucun autre parcours commercial ou montant. Relecture linguistique métier avant publication à confirmer. |
| REQ-004 / AC-004 ; UX-C01/C02/C12 | Rendu à 320/390/768/1280 px dans les trois langues, menu clavier, destinations, focus et lien d’évitement vérifiés. Partiel : aucune certification WCAG ou recette de la boutique entière. |
| REQ-026 / AC-026 | Langue, titre, description, un H1 et `noindex, nofollow` conservés/vérifiés. Partiel : pas de sitemap, ouverture publique ou données produit. |
| REQ-034 / AC-034 ; UX-C21 | Tests du socle conservés, contrôles du lot et état catalogue indisponible vérifiés, preuves ci-dessous. |
| REQ-005 / AC-005 ; UX-C03 | Non satisfait : recherche réelle, filtres et tri exclus de ce lot, dépendants du catalogue approuvé. |

## Fichiers concernés

- Modifiés : `resources/views/components/layout.blade.php`, `resources/views/home.blade.php`, `resources/css/app.css`, `resources/js/app.js`, `lang/fr/home.php`, `lang/en/home.php`, `lang/de/home.php`.
- Ajouté : `tests/Feature/HomeNavigationTest.php` ; documentation : ce rapport, actualisation limitée de `docs/UX-UI-HOMYA.md` et `README.md`.
- Build régénéré dans `public/build`, ignoré par Git. Captures et mesures dans `output/playwright/accueil-navigation/`.
- DECISION-009 ajoutée dans le registre canonique, avec sauvegarde préalable et relecture du projet, de l’ID, du statut et de la portée. Aucun registre parallèle.

## Recette exécutée

| Contrôle | Preuve / résultat |
|---|---|
| `npm run build` | Vite réussit ; CSS et JS locaux, aucune police distante. |
| `php artisan test --testsuite=Feature` | 9 tests, 124 assertions réussis ; garde de connexion jamais appelée pour le rendu/navigation. Aucun contrôle SQL déduit. |
| `vendor/bin/pint --test tests/Feature/HomeNavigationTest.php lang/fr/home.php lang/en/home.php lang/de/home.php` | Réussi. |
| `node --check resources/js/app.js` | Réussi. |
| Navigation HTTP | FR/EN/DE, redirection `/` → `/fr`, `/es` → 404 ; destinations d’ancres et langues vérifiées. |
| Navigateur intégré | FR : Entrée/Espace ouvrent, Échap ferme et restitue le focus ; lien Mobilier au clavier ferme le menu et focalise la section. FR → EN conserve `#universe-furniture`. DE : Tab après le bouton atteint Startseite, Échap depuis le menu restitue le focus. |
| Breakpoints / évitement | Transition 768 → 1280 conserve la navigation visible ; retour à 390 ferme et replace le focus sur le bouton. Lien d’évitement activé au clavier : focus `main`. |
| Responsive | [Mesures JSON](/Users/akm/Documents/ChatGPT/HOMYA/output/playwright/accueil-navigation/responsive.json) : douze configurations, largeur demandée et observée identiques, aucun débordement horizontal ; grilles 1/3/5 colonnes. Le scrollbar explique la différence largeur viewport/client. |
| Revue visuelle | [Accueil FR desktop](/Users/akm/Documents/ChatGPT/HOMYA/output/playwright/accueil-navigation/accueil-fr-desktop.jpg), [accueil DE mobile](/Users/akm/Documents/ChatGPT/HOMYA/output/playwright/accueil-navigation/accueil-de-mobile.jpg), [menu DE mobile](/Users/akm/Documents/ChatGPT/HOMYA/output/playwright/accueil-navigation/menu-de-mobile.jpg) inspectés. Aucun chevauchement bloquant relevé dans les vues inspectées. |
| Console navigateur | Aucune erreur ni avertissement retourné lors de la consultation du journal de recette. Ce constat couvre la session observée. |
| Préservation | [Comparaison d’empreintes](/Users/akm/Documents/ChatGPT/HOMYA/output/playwright/accueil-navigation/preservation.json) : sur 46 fichiers de référence, 38 inchangés et 8 changements prévus (sept fichiers d’interface/langues et README). Les fichiers secrets sont exclus de cette comparaison. |

Les premiers relevés de resize ont été écartés lorsqu’ils précédaient la stabilisation du viewport ; seul le tableau final avec largeur demandée = observée constitue la preuve retenue.

## Limites et suite

Non exécutés : lecteur d’écran, zoom texte 200 %, navigateurs externes Safari/Firefox/Edge, audit complet WCAG/sécurité, Core Web Vitals et mesure de performances en production. Le contraste des couleurs de référence est documenté dans la conception ; l’accent terre cuite n’est plus utilisé pour les petits textes sur crème. L’absence de piège au clavier et les contrôles de reflow observés ne certifient pas toute l’accessibilité.

Aucun blocage constaté pour ce lot local. Le catalogue réel, ses traductions/visuels autorisés et les règles prix/stock restent inconnus ou ouverts ; ils conditionnent les catégories, fiches et recherche fonctionnelles. Les comptes/permissions, paiement/livraison, hébergement et autres wireframes demandent leurs arbitrages propres. Prochaine étape proposée : définir le prochain lot catalogue et ses données validées, puis préparer le plan de schéma XAMPP avant toute création ou migration.
