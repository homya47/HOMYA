# HOMYA — promotions, prix et langues

**État actuel : DECISION-052 — remises définitives, affichage commun aux pages, 08/10/2026.** La simulation décrite ci-dessous est la recette historique DECISION-051, remplacée par la mise à jour en fin de document. Les preuves de cette ancienne phase sont conservées telles quelles.

## Historique DECISION-051

DECISION-051 — 08/10/2026. Demande utilisateur : drapeaux et nouvelle page Promotions. Réponses confirmées : remise 5 % de 1 000 € à moins de 1 500 € et **simulation locale**.

## Périmètre et règles

- FR, EN, DE conservés avec noms de langue accessibles et drapeaux SVG décoratifs : France, Royaume-Uni, Allemagne. Le drapeau anglais désigne la langue et ne remplace pas la Belgique dans les marchés/livraisons.
- Page `/fr/promotions`, `/en/promotions`, `/de/promotions`, reliée à la navigation et au pied de page partagés.
- Prix catalogue en EUR inférieur à 1 000 € ou absent : exclu. Autre devise : exclue.
- 1 000 € ≤ prix < 1 500 € : 5 % ; 1 500 € ≤ prix ≤ 2 000 € : 15 % ; prix > 2 000 € : 25 %.
- Base de classement : prix catalogue avant simulation. Paliers exclusifs, aucun cumul ni code de remise.
- Prix final calculé en centimes entiers, arrondi une seule fois au centime le plus proche ; demi-centime vers le haut. Économie = prix catalogue − prix final. Exemple : 1 000 € → 950 € ; 1 500 € → 1 275 € ; 2 000 € → 1 700 € ; 2 000,01 € → 1 500,01 €.
- Prix catalogue, prix simulé et économie distinctement étiquetés sur chaque carte. Aucun prix barré/ancien prix historique inventé. Les fiches et autres pages conservent leur prix catalogue ; le lien carte désigne explicitement la fiche catalogue.
- Recherche, filtre par taux, tri déterministe par prix simulé décroissant puis slug et pagination de 24 produits. Nouvelle recherche/palier retire le numéro de page ; langue et navigation paginée conservent les filtres. Une page excessive est ramenée à la dernière ; entrées invalides rejetées 422.
- État vide explicite ; indisponibilité de source/traduction : 503 sans détails privés. Aucune commande, paiement, mutation SQL, migration/import ou activation commerciale.

## Données observées

Archive locale existante : 3 709 produits ; 2 077 éligibles, dont 684 à 5 %, 511 à 15 %, 882 à 25 %. Ces décomptes décrivent l'archive, pas des stocks disponibles ni des offres commercialement validées. SQL XAMPP reste la source réelle du site installé ; le calcul de remise est une projection de lecture.

## Sources et limites juridiques

Question vérifiée : un prix catalogue importé suffit-il comme prix antérieur d'une annonce de réduction réelle en France/Belgique/Allemagne ? Aucun historique HOMYA vérifiable n'a été fourni ; l'utilisateur a choisi la simulation.

| Source officielle | URL | Consultation | Juridiction / portée / fait étayé |
|---|---|---|---|
| DGCCRF, « Annonces de réduction de prix : ce que vous devez savoir ! », écrit le 25/08/2026 | https://www.economie.gouv.fr/dgccrf/les-fiches-pratiques/annonces-de-reduction-de-prix-ce-que-vous-devez-savoir | 08/10/2026 | France : annonce de réduction et prix le plus bas pratiqué par le professionnel pendant les 30 jours précédents. |
| SPF Économie, « Réductions de prix », mise à jour 25/09/2025 | https://economie.fgov.be/fr/themes/ventes/politique-des-prix/reductions-de-prix | 08/10/2026 | Belgique : prix antérieur le plus bas sur 30 jours, déterminé par canal ; page mentionne des exceptions dont nouveaux biens (7 jours). Aucune exception appliquée automatiquement à HOMYA. |
| Gesetze im Internet, PAngV § 11 | https://www.gesetze-im-internet.de/pangv_2022/__11.html | 08/10/2026 | Allemagne : extrait officiel indexé consulté, obligation du plus bas prix total appliqué pendant 30 jours pour annonces de réduction aux consommateurs. Ouverture directe de la page en échec (timeout) : texte complet et exceptions à revérifier avant publication. |

Interprétation limitée : les prix copiés d'une archive ne prouvent pas les prix réellement pratiqués par HOMYA. Recommandation : conserver par produit/canal les prix datés, définir période/conditions/disponibilité et vérifier les règles applicables avec un professionnel avant publication réelle FR/BE/DE. Ni le module Legal & Compliance ni ce document ne certifient la conformité. Aucun historique ou engagement commercial n'est fabriqué.

## Contrôles et installation

Résultat vérifié :

- 16 tests ciblés / 719 assertions par l’agent Catalogue, puis suite complète **279 tests / 3 396 assertions**, réussis hors base. Première tentative complète bloquée par les sockets Unix fictifs dans le sandbox (86 erreurs d’environnement) ; XML conservé, reprise système réussie. Erreur initiale de boucle Blade corrigée avant installation.
- Syntaxe PHP, Pint et build Vite réussis. CSS 92,66 Ko / 18,64 Ko gzip ; JS inchangé 4,14 Ko / 1,48 Ko gzip. Aucun nouveau paquet, script client ou ressource externe.
- **45 scénarios HTTP natifs SQL** : pages et paliers FR/EN/DE, page suivante/dernière, zéro résultat, 422 et normalisation de page, autres familles de pages avec drapeaux. **396 prix simulés**, taux et économies vérifiés contre les prix archivés ; deux assets vérifiés Source==htdocs==HTTP.
- **14 contrôles navigateur confirmés**, à 1440/768/375 px : page, langue, palier et pagination conservés, menu mobile/Escape, état vide/récupération, fiche éligible conservant son prix catalogue et retour. Aucun débordement ni erreur console observé. Deux observations avant fin de navigation sont conservées comme transitoires, sans les compter parmi les contrôles confirmés.
- Revue statique indépendante par l’agent Qualité : aucun défaut bloquant identifié dans paliers/calcul/filtre EUR/tri/pagination/traductions et simulation ; cet agent n’a pas réexécuté les tests ni accédé à SQL.
- **17 fichiers applicatifs/tests/build** installés sous htdocs avec sauvegardes privées et empreintes. Prix/archives, .env et configurations protégées conservent leurs empreintes avant/après. Aucun SQL persistant, changement de droits ou services. Restauration non exécutée.

Preuves : `output/qa-promotions-flags-20261008/{before.json,protected.json,tests-all.xml,tests-sandbox-errors.xml,installation.json,http-evidence.json,browser-evidence.json,promotions-prix-et-drapeaux.png,verification.json}`. Sauvegarde privée identifiée dans installation.json ; anciens assets conservés. Rapport et preuves copiés privés dans htdocs à la clôture. Ces contrôles ne certifient pas les ventes, tous les navigateurs, WCAG ou la conformité juridique.

## Éléments ouverts

Historique du prix HOMYA, durée/date des offres, éligibilité commerciale, stock, TVA, conditions et validation juridique des marchés restent à confirmer avant les offres réelles. Le lot actuel est uniquement une simulation locale autorisée ; les ventes restent fermées.


## Mise à jour DECISION-052 — remises définitives et présentation selon capture

Décisions validées : l’utilisateur demande des réductions définitives, suppression des blocs de paliers et des mentions de simulation, puis fournit la capture du catalogue pour la présentation des prix. Les taux HOMYA déjà validés demeurent5% de1000€ à moins de1500€,15% de1500€ à2000€ inclus,25% au-delà. Les taux30% de la capture ne remplacent pas ces règles. Le badge est sur la photo ; le prix réduit est saillant orange, le prix avant remise barré gris sur la même ligne si l’espace permet, avec retour à la ligne mobile. La charte jaune et les drapeaux sont conservés.

Politique durable centralisée dans `PromotionPricing` et la projection `Catalogue::present`, depuis le montant source EUR, sans nouvelle application au montant réduit. `price_cents` est le prix final dans toutes les pages ; `promotion.base_price_cents` conserve le prix avant remise. Les filtres et tris utilisent le prix réellement affiché. Le contrôleur Promotions réutilise la remise projetée : un article réduit sous1000€ reste éligible selon sa base originale. Les archives SQL/fichiers ne sont pas réécrites. Le nom historique `PromotionPreview` est conservé comme adaptateur compatible déléguant au calcul commun ; aucun calcul de simulation indépendant.

Présentation partagée par `product-price.blade.php`, utilisée dans cartes et fiches, incluant accueil et produits associés. Labels accessibles « Prix réduit » / « Prix avant remise », traduits FR/EN/DE. Suppression des annonces/taux hors produits et filtre de taux visuel ; anciennes URLs `discount` restent compatibles avec recherche/pagination/langue et lien neutre de réinitialisation. Aucun avis, ventes, livraison gratuite ou contact copiés/inventés depuis la capture.

### Contrôles réellement exécutés

- Agent Catalogue :16tests ciblés/874assertions passés ; Pint réussi. Suite complète hors base :**282tests/3838assertions**, tous réussis, avec sockets fictifs autorisés, sans connexion MariaDB par la suite. Seuils/arrondi, EUR, non-cumul, conservation des archives, prix identique sur les pages et filtres/tri selon final.
- Pint et build Vite réussis ; CSS92,38Ko/18,60Ko gzip et JS inchangé4,14Ko/1,48Ko gzip. Pas de nouvelle dépendance.
- **72scénarios HTTP natifs sous configuration SQL XAMPP**, **537montants vérifiés** (cartes et12fiches principales),3langues, paliers/dernière page/erreurs422/redirection302, accueil/catalogue/fiches et filtres bornés au prix réduit.2assets Source==htdocs==HTTP. Le catalogue conserve3709produits, dont**2077remisés** (684/511/882 aux taux5/15/25).
- **10observations navigateur** à1440/768/375px : français/anglais/allemand, prix/badge/barrement CSS, cohérence carte→fiche, galerie de6images avec changement de photo, pagination mobile, langue conservant page, recherche vide et récupération. Aucun débordement ni erreur/avertissement console observé. Une attente d’URL a échoué car l’ancre `#results-heading` était conservée ; l’état réel a été relu et vérifié, sans présenter cela comme une panne du site.
- Revue statique indépendante agent Qualité : aucun défaut important démontré ; aucune exécution des tests/SQL/navigateur par cet agent.
- **17fichiers applicatifs/tests/build installés** avec sauvegarde privée et contrôle avant/après. Archives/index/dictionnaires de traduction/.env/configurations protégées inchangés par empreinte. Aucun changement MariaDB, compte/droit, migration/import, services ou paiement. Restauration non exécutée.

### Limites et inconnues maintenues

Le montant barré est le prix avant cette remise calculée, tiré de l’archive ; il n’est pas présenté comme une preuve du plus bas prix HOMYA pratiqué sur30jours. L’utilisateur valide la politique de prix locale définitive, sans fournir un historique de prix commercial. Les sources officielles FR/BE/DE consultées ci-dessus conservent leur portée et leurs limites. Publication de ces annonces/prix de référence à vérifier avec preuves datées/canal et revue professionnelle ; décision locale ne certifie aucune conformité et n’ouvre ni achats ni paiements. Stocks, TVA, livraison, identité et documents commerciaux restent attendus comme précédemment. Aucun clone exhaustif de toutes les pages source n’est certifié par ce lot centré sur les prix.

Preuves actuelles : `output/qa-promotions-definitives-20261008/{before.json,protected.json,tests-all.xml,installation.json,http-evidence.json,browser-evidence.json,prix-desktop.png,prix-mobile.png,verification.json}`. L’archive de sauvegarde locale est identifiée dans `installation.json`. Les anciens contrôles DECISION-051 ne constituent pas la recette de l’affichage actuel.
