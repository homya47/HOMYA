# HOMYA — corrections du catalogue et installation XAMPP

10 octobre 2026. Demande : corriger catalogue, titres/descriptions, 26 produits à variantes, puis installer localement. Les corrections éditoriales concernent la projection publique ; l'archive fournisseur reste intacte. Aucun prix, stock, avis, GTIN, MPN ou caractéristique absente n'est inventé.

## Travail réalisé

- Les photos écartées pour dimensions/format restent exclues, mais ne masquent plus une description valide. Le seuil d'image existant n'est pas contourné par agrandissement.
- Les 45 descriptions vides ou contenant un marqueur technique reçoivent un texte limité au nom et à la référence catalogue déjà disponibles. C'est un socle factuel, à enrichir avec les données fournisseur ; aucune promesse de rendement, de garantie ou de certification n'est ajoutée.
- 15 titres sont révisés en FR/EN/DE : 11 dépassaient la limite en français, trois autres dépassaient celle-ci après traduction, et Uovo contenait une dimension contradictoire. Les règles sont liées au slug et à l'empreinte exacte du titre source. Une source différente n'est pas silencieusement écrasée. Infinity 6200 et Uovo nécessitent encore une confirmation technique ; leurs variantes de finition/dimensions contradictoires ne sont pas départagées.
- Les répétitions strictement identiques d'une caractéristique sont regroupées. Des valeurs différentes sous un même intitulé apparaissent comme « En cours de vérification » ; les valeurs originales restent dans l'archive et l'audit privé. Le signalement reste bloquant pour le flux. Une puissance minimale supérieure à la puissance maximale est également détectée sans supposer une virgule manquante.
- Les 26 produits à variantes possèdent 92 liens `?variant=vN`. Le serveur valide et sélectionne l'option, même sans JavaScript. Le sélecteur met à jour l'URL ; les liens de langue conservent l'option. Une option invalide renvoie 404. Le canonical reste celui de la fiche parent.
- Le flux peut exporter une ligne par variante, avec identifiant stable distinct, `item_group_id` commun, URL sélectionnée, attributs différenciants et identifiants fabricant propres. Le prix et l'inventaire restent communs au parent, conformément au fonctionnement déjà validé (DECISION-061). Une référence catalogue ne devient pas automatiquement un MPN.
- Une photo doit être explicitement vérifiée pour la finition et appartenir à la galerie validée du modèle. Le flux n'hérite pas silencieusement de la photo ni du GTIN parent. Les attributs doivent être cohérents entre variantes et distinguer chaque option. ProductGroup et les offres de variantes ne sont publiés qu'après ces revues, avec les conditions de paiement/stock déjà requises.
- Lorsque la photo de la finition sélectionnée n'est pas encore vérifiée, la page indique que les photos présentent le modèle et que la finition peut différer. Une image générale n'est ainsi pas présentée comme la preuve de cette finition.
- Les nouveaux titres et descriptions rédigés par IA portent une provenance `trained_algorithmic_media`. Le flux exige que la revue corresponde à cette provenance et emploie les attributs structurés correspondants. Les traductions et textes antérieurs gardent une provenance à confirmer.

## Audit actuel et limites

Audits réellement exécutés sur les fichiers, administration désactivée, sans lecture d'inventaire ni contact Google : `output/qa-seo-catalogue/google-audit-fr.json`, `google-audit-en.json`, `google-audit-de.json`.

| Contrôle | Résultat |
|---|---:|
| Produits audités par langue | 3 709 |
| Titres/descriptions absents ou dépassant la limite du flux | 0 dans les trois langues |
| Produits à variantes / options | 26 / 92 |
| Fiches nécessitant encore une confirmation fournisseur | 418 |
| Fiches sans caractéristiques techniques complètes | 98 |
| Fiches avec au moins une caractéristique conflictuelle | 319 |
| Intervalle de puissance incohérent | 1 |
| Titres dont les contradictions techniques restent à confirmer | 2 |

Les lignes se recoupent. **Aucune des 3 709 revues Merchant n'est approuvée automatiquement.** Les 92 variantes exigent notamment les photos, attributs et identifiants fabricant réels. L'absence d'identifiant dans les données disponibles ne justifie pas `identifier_exists: false`. Les 418 fiches sont traitées pour éviter la présentation de valeurs contradictoires comme certaines, mais leurs faits manquants ne sont pas résolus sans preuves fournisseur.

Le domaine public exact manque encore, les comptes Google sont à créer et le règlement réel doit être vérifié. Le site local garde `noindex` et le flux fermé. Aucun classement, indexation, approbation Google ou résultat publicitaire n'est garanti. Les résultats enrichis, conditions propres aux catégories, droits des images, EPREL le cas échéant, traductions humaines et diagnostics du compte restent à vérifier sur le site public.

## Revue des variantes

Le modèle de travail non approuvé est `output/qa-seo-catalogue/merchant-variants-review-template.json`. Il contient les slugs, options et titres dans les trois langues, sans fabriquer les données manquantes. Il ne doit pas être publié ni approuvé en bloc.

Pour chaque produit, compléter les entrées `variants.vN` : `option_titles` exacts, `attributes` par langue (`color`, `material`, `pattern`, `size` selon la variation réelle), `images` avec URL locales de la galerie et `image_verified: true` après contrôle visuel, GTIN/MPN applicables, provenance des textes et empreintes. Les attributs présents doivent être les mêmes pour toutes les options du groupe et leurs combinaisons doivent les distinguer. Le changement de texte, d'image ou d'attribut invalide la revue. Exécuter de nouveau les audits pour récupérer les empreintes des données complétées, puis valider chaque entrée et le parent avant l'inclusion. Le fichier actif demeure privé : `storage/app/private/google/merchant-products.json`.

## Installation et preuves

Site : `/Applications/XAMPP/xamppfiles/htdocs/HOMYA`, accessible sur `http://127.0.0.1:8080/fr`. Installation initiale de 30 fichiers après vérification du plan et des empreintes. Sauvegarde : `/Applications/XAMPP/xamppfiles/homya-local/seo-catalogue-backup-20261010T124926Z`.

Les routes, scripts et règles CSP de paiement déjà installés sont préservés par fusion ciblée. Le JavaScript XAMPP est recompilé avec son module de variantes ; le CSS et le bundle administration installés restent conservés. 3 727 fichiers protégés ont été contrôlés par empreinte, dont `.env` et les archives catalogue. Aucun redémarrage, migration SQL, changement de service XAMPP ou activation de paiement/campagne n'est effectué. Les requêtes de recette suivent le fonctionnement normal du site, y compris sa journalisation habituelle.

Preuves : `output/qa-seo-catalogue/install-plan.json`, `install-result.json`, `catalogue-summary.json`, `targeted-junit.xml`, `regression-junit.xml`. 58 tests ciblés / 1 310 assertions et 417 tests généraux / 10 315 assertions réussis, hors groupe MariaDB réel. Les erreurs de sockets synthétiques du sandbox ont été résolues par la relance autorisée hors sandbox. Pint, syntaxe PHP des fichiers préparés, build Vite et `git diff --check` réussis. La recette navigateur locale est consignée séparément dans `installed-browser.json` ; elle ne remplace pas les tests Google sur le futur domaine.

Recette XAMPP réussie : 276 URL d'options (92 × 3 langues), neuf pages sur 1440/375/320 pixels, sélection serveur dans trois contextes sans JavaScript, changement de l'URL par le sélecteur, erreurs 404 pour options invalides, robots local fermé et flux/sitemap indisponibles localement. Pas de débordement horizontal ni d'erreur JavaScript/asset détecté. Aucun achat réel ni appel Google effectué.

## Sources officielles

Consultées le 10/10/2026 : [variantes et ProductGroup](https://developers.google.com/search/docs/appearance/structured-data/product-variants), [groupes d'articles](https://support.google.com/merchants/answer/6324507?hl=fr), [titres et provenance des textes](https://support.google.com/merchants/answer/6324415?hl=fr). Vérifier de nouveau les exigences à la publication et selon les catégories/programmes réellement retenus.
