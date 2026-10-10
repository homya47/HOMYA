# HOMYA — en-tête et pied de page

DECISION-053 — 08/10/2026. Demande explicite : tenir compte des captures21:23:01 et21:23:20 et appliquer les modifications adaptées. Les captures fournies montrent le pied de page4colonnes, des sélections de catalogue et les barres supérieures de services de la source autorisée. Ce lot porte sur ces composants partagés, pas sur une validation des CGV ni un clone exhaustif.

## Adaptation réalisée

| Élément observé | Traitement HOMYA | Acceptation contrôlée |
|---|---|---|
| Barre de réassurance | Catalogue en euros, langues proposées, lien Retours & rétractation | Libellés FR/EN/DE ; pas de délai/avantage commercial inventé |
| Barre grise contact/services | Contact HOMYA, conseil, catalogue/FAQ, suivi | Destinations locales ; coordonnées source absentes |
| En-tête recherche et actions | Bouton Rechercher explicite, icônes Conseil/Suivi/Panier, drapeaux conservés | Recherche et changement de langue conservent les comportements existants ; aucune fausse quantité panier |
| Raccourcis catalogue | Tables extensibles/céramique/budget800€/160cm/blanches, ETNA, La Nordica, poêles bois1000€/granulés1500€ |9sélections non vides ; plafonds inclusifs « jusqu’à » appliqués au prix final réel |
| Pied de page4colonnes | Catégories, Aide & contact, Informations légales, Paiement & livraison |4colonnes bureau,2tablette,1mobile ;8catégories +Promotions ; groupes de liens nommés accessibles |
| Paiement/livraison | Carte et virement prévus, liens vers leurs pages | Libellé « prévus », aucune affirmation de paiement sécurisé déjà connecté/gratuité/délais |
| Gestion des cookies source | Page informative Cookies FR/EN/DE | Aucun faux bouton de préférences, bandeau artificiel, tag GA/Ads ou consentement publicitaire ajouté |

Composants Blade et styles partagés. Configurations de sélections dans `config/storefront.php`, libellés `lang/{fr,en,de}/chrome.php`, footer dans `storefront-footer.blade.php`. Nouveau chemin `/{locale}/cookies` pris en charge par le contrôleur d’information existant ; textes dans `pages.php`. Aucun nouveau paquet, service externe, JavaScript ou schéma SQL.

## Corrections déduites de la revue

- Le filtre marque strict La Nordica excluait3produits `LA NORDICA`. Le raccourci est maintenant une recherche insensible à la casse dans l’univers chauffage ; les221slugs de cette marque sont inclus en FR/EN/DE.
- En paysage667×375, le menu dépassait de7,99px le bas de la fenêtre. La hauteur maximale a été réduite ; après rechargement, bas du menu346,99px, inférieur aux375px disponibles, et menu déroulant utilisable. L’observation avant correction est conservée et marquée comme telle.

## Observations et limites

Les promesses de livraison offerte, transporteur propre, délais3à7/7à10jours, virement gratuit et retours14/30jours de la source ne sont pas des décisions HOMYA. Les captures présentent d’ailleurs14jours en haut et30jours en bas : aucune de ces valeurs n’est adoptée. Aucun email/téléphone DAMDAM, avis, ville ou pays supplémentaire n’est copié. L’utilisateur fournira ultérieurement les informations commerciales et contacts HOMYA ; les pages concernées restent explicitement en préparation. Les liens de suivi/panier aboutissent à leur état réel, sans simuler une commande ni ouvrir un achat.

Cookies : code applicatif inspecté sans références de balises Google Analytics/Google Ads/Facebook ; configuration réelle installée consultée sur liste blanche uniquement : session fichier, cookie de session HTTPOnly, SameSite=lax. Réponses HTTP émettant `XSRF-TOKEN` et `homya-session`, sans enregistrer leurs valeurs. La page décrit cette version locale ; elle ne constitue ni une politique publique finalisée ni une certification juridique. Toute future mesure/marketing devra suivre sa validation propre avant activation.

Remises DECISION-052, catalogue et archives conservés ; fichiers de prix, index, dictionnaires de traduction, .env et configurations DB/catalogue protégés par empreintes. Aucun SQL persistant, migration/import, droit, configuration XAMPP, arrêt/rechargement de service, paiement ou production.

## Contrôles réellement exécutés

- Agent Catalogue :9tests/1504assertions initiaux réussis, puis7tests/1190assertions finaux (nouvelle régression La Nordica incluse) et Pint réussis. Tests sur archives fichiers, sans SQL.
- Suite complète initiale hors base :288tests/5085assertions réussis, puis recette ciblée finale ci-dessus après l’ajustement La Nordica. La suite complète n’a pas été répétée après le changement CSS seul, validé par build, navigateur et HTTP des assets.
- Build Vite final réussi : CSS95,21Ko/19,09Ko gzip ; JS inchangé4,14Ko/1,48Ko gzip. Pas d’allégation Core Web Vitals ni d’audit d’accessibilité complet.
-165occurrences de liens des composants inventoriées ;105destinations HTTP natives200 sous configuration SQL XAMPP ;51sélections de catalogue non vides dans les3langues, dont221La Nordica ;4accès privés403/404. Première assertion d’audit rejetait à tort les liens vers les autres langues : test corrigé avant recette réussie, sans défaut de routage du site.
-2assets finaux Source==htdocs==HTTP ;15fichiers actifs Source==htdocs après installation initiale de15fichiers et correctif styles/manifest/asset de3fichiers. Sauvegardes privées distinctes, anciens assets conservés ; restauration non testée.
-11observations navigateur à1440/768/375/320px et667×375 :10confirmations et1défaut initial ensuite corrigé. Colonnes, traductions, liens Cookies, menu/Escape/focus, accès Panier, petits écrans et paysage. Aucun débordement horizontal ni erreur/avertissement console observé ; ne certifie pas toutes les tailles/navigateurs.
- Revue statique indépendante agent Qualité : défaut La Nordica identifié et corrigé ; aucune recette SQL/navigateur exécutée par cet agent.

Preuves : `output/qa-storefront-chrome-20261008/{before.json,protected.json,tests-all.xml,tests-final-targeted.xml,installation.json,installation-landscape.json,installation-final.json,http-evidence.json,assets-final.json,runtime-observation.json,browser-evidence.json,en-tete-desktop.png,pied-de-page-desktop.png,en-tete-mobile.png,menu-paysage.png,verification.json}`. Rapport et preuves à copier privés dans htdocs à la clôture. Statut : composants locaux vérifiés ; services commerciaux/conditions finales toujours dépendants des informations HOMYA.
