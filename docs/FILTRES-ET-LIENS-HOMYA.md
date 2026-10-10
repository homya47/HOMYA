# HOMYA — filtres dépendants et vérification des liens

08/10/2026. Demande utilisateur et complément MIME explicitement autorisé consignés dans DECISION-049 du registre Specification existant. Source : `/Users/akm/Documents/ChatGPT/HOMYA` ; copie servie : `/Applications/XAMPP/xamppfiles/htdocs/HOMYA` ; catalogue local : http://127.0.0.1:8080/fr/catalogue.

## Résultat et exigences

PAR-01 / REQ-005 / AC-005 : le choix d’univers limite les catégories aux appartenances réellement observées dans les produits. Sans univers, toutes les catégories restent accessibles. Une catégorie valide est conservée, y compris si elle appartient à plusieurs univers ; ce cas est testé par fixture. Une catégorie incompatible connue revient à « Tous », avec une notice FR/EN/DE. Le navigateur met immédiatement les options à jour ; sans JavaScript, une soumission normalise l’URL et retire uniquement catégorie et page, conservant recherche, marque, état, prix et tri. Une valeur inconnue ou une autre erreur reste refusée en 422. Le marqueur de notice n’est pas propagé dans les liens de navigation.

Les 3709 produits de l’archive présentent 65 catégories :34 électroménager,15 chauffage,13 mobilier,2 combustibles et 1 TV. Aucune catégorie partagée actuellement ; aucune correspondance n’est déduite d’un préfixe de slug. Les catégories et messages sont localisés. Prix, archives, traductions métier, profils SQL et données MariaDB conservés.

REQ-003 et REQ-034, périmètre consultatif : filtres, langues, retour fiche, pagination, états invalides et responsive contrôlés. Les compteurs affichant accidentellement `[0,Inf]` sont corrigés. La notice de réinitialisation reste visible sur mobile ; l’aide accessible n’annonce pas une réinitialisation inexistante. Le lien « Toutes les catégories » garde le libellé visible sous l’en-tête fixe.

## Fichiers concernés

- `app/Http/Controllers/CatalogueController.php` : appartenance, scoping et normalisation serveur.
- `resources/views/catalogue/index.blade.php` et `resources/js/app.js` : options dépendantes, restauration navigateur et notice accessible.
- `resources/css/app.css` : aide et position d’ancre.
- `lang/{fr,en,de}/catalogue.php` : messages et pluriels.
- `tests/Feature/CataloguePresentationTest.php` : scénarios de régression.
- Build Vite : manifeste et bundles locaux. Aucun paquet ajouté.

Onze fichiers applicatifs/build ont été synchronisés avec contrôle des empreintes existantes, sauvegarde privée et vérification source/htdocs. Les anciennes ressources construites de la copie servie sont conservées. Les reçus `installation*.json` identifient les sauvegardes.

## Contrôles exécutés et portée

Preuves privées dans `output/qa-filters-links-20261008/` :

| Contrôle | Résultat réel et limite |
|---|---|
| Suite PHP hors base | 263 tests /2896 assertions réussis. MariaDB réelle exclue de cette suite. |
| Pint, syntaxe JS et build | Réussis ; CSS 90,34 Ko/18,23 Ko gzip, JS 4,14 Ko/1,48 Ko gzip. |
| Inventaire complet rendu | 11847 pages produites par les vrais contrôleurs et vues, source fichiers et connexions DB désactivées :11127 fiches FR/EN/DE,547887 occurrences de liens,25518 URL distinctes,43719 cibles d’ancres. Destinations, routes, existence des produits/catégories, ancres et fichiers vérifiés ; zéro erreur. Il ne s’agit pas de 11127 GET SQL. |
| HTTP natif SQL | 948 GET, tous 200 :42 pages fixes,465 pages de pagination,195 catégories,18 univers,195 associations catégorie/univers,33 fiches/contextes échantillonnés. Aucun lien externe, mention source ni ancre locale manquante dans ces réponses. |
| Filtres natifs complémentaires | 12 contrôles FR/EN/DE : redirections incompatibles, préservation des autres paramètres, marqueur isolé, catégories inconnues/univers inconnus/plage incohérente refusés en 422. Derniers 2 bundles téléchargés et empreintes comparées à source/htdocs ;11 fichiers installés identiques. |
| Ressources | 18120 HEAD :200 et tailles concordantes. Ne vérifie pas le décodage de chaque photo ni une conformité Google. |
| Navigateur | 1440 × 1000,375 × 900,768 × 1000 ; catégories des cinq univers et liste globale, préservation/retrait de catégorie, tri/prix/langues, pagination 2, fiche et retour, notice serveur mobile et ancre visible. Pas de débordement ou erreur console observés. Captures `filtres-desktop.png` et `filtres-mobile.png`. Pas d’audit exhaustif de lecteur d’écran ou certification WCAG. |

## Complément serveur autorisé et vérifié

L’audit a identifié 594 WebP accessibles sans `Content-Type`. Après accord distinct « Oui, autoriser ce correctif local », seule la configuration privée du serveur HOMYA isolé sur 127.0.0.1:8080 reçoit `AddType image/webp .webp`. Sauvegarde et empreinte initiale vérifiées, syntaxe avant/après réussie et rechargement progressif exécuté. Parent Apache 52273 et PHP-FPM 52271 inchangés ; fichiers de configuration Apache principal, MariaDB, PHP-FPM et fichiers `.env` conservés par empreinte.

Les 594 WebP sont retestés enHEAD :200, taille concordante et `Content-Type: image/webp`, aucune erreur. Trois accueils FR/EN/DE contrôlés après rechargement. Preuves : `webp-execution.json`, `links-webp-mime-recheck-report.json` et recette finale de l’agent qualité. Les photos elles-mêmes ne sont pas modifiées.

## Décisions et inconnues

La remise à zéro porte sur la catégorie devenue incompatible et la page. Les autres filtres restent choisis par l’utilisateur ; ils peuvent légitimement produire un résultat vide. Une catégorie inexistante n’est pas silencieusement remplacée. La source SQL reste active, sans migration/import/droit/écriture SQL, sans modification du serveur XAMPP principal 80 ni redémarrage MariaDB/PHP-FPM.

Aucun blocage pour ce lot. Les fonctions de vente, paiement, contact et livraison restent dépendantes des informations commerciales promises ; cette recette n’ouvre pas les ventes, ne certifie pas toutes les URL SQL par GET, la qualité de chaque traduction/photo, la conformité juridique, WCAG/CWV ou une acceptation Google. Prochaine étape : recette utilisateur du catalogue local corrigé.
