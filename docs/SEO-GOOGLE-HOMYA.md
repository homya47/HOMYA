# HOMYA — SEO et préparation Google

10 octobre 2026. Rapport du travail local et procédure de raccordement ; aucune approbation Google ni décision commerciale supplémentaire.

**Mise à jour après correction du catalogue et installation XAMPP :** voir [CATALOGUE-MERCHANT-XAMPP.md](CATALOGUE-MERCHANT-XAMPP.md). Les résultats de l'audit initial ci-dessous sont historiques : titres/descriptions désormais valides dans les trois langues, URL de sélection pour les 92 options des 26 produits à variantes, export conditionnel des variantes et installation locale sauvegardée. Les données fournisseur non confirmées restent bloquantes.

## Périmètre et faits

HOMYA utilise Laravel 13, Blade, FR/EN/DE, EUR et un catalogue de 3 709 produits. France, Belgique et Allemagne sont les marchés déclarés. Hostinger est l'hébergeur prévu ; **le nom de domaine exact reste à fournir**. L'utilisateur prévoit de créer les services Google. Aucun compte externe n'a été consulté ou modifié pendant ce travail.

L'inspection a relevé un `noindex, nofollow` universel, aucun canonical ni sitemap et un robots statique autorisant l'exploration du site local. Le parcours actuel peut préparer une commande avec paiement différé ; cela ne constitue pas un règlement réel prêt pour Shopping. Les pages juridiques affichent encore leur statut préparatoire. Les travaux existants sont conservés.

## Corrections réalisées dans le dépôt

- Métadonnées communes, descriptions bornées, canonical, Open Graph et JSON-LD échappé correctement. Nom, marque, référence interne, images et description sont dérivés du contenu affiché ; aucun avis, GTIN, MPN ni prix supplémentaire n'est créé.
- Canonical propre pour chaque produit, sans paramètres de recherche/navigation/publicité. Liens `hreflang` FR/EN/DE et `x-default` vers les versions canoniques ; navigation linguistique existante conservée.
- Les catégories prennent leur propre titre, H1 et description. Les pages paginées conservent leur propre canonical. Recherche, tri, facettes, résultats vides et filtres invalides restent non indexables. Catégorie et univers constituent les deux niveaux de navigation pouvant être indexés ; une catégorie conserve une seule URL canonique même avec son contexte d'univers.
- Les pages privées, les erreurs, les services préparatoires et les pages de suivi/avis gardent `noindex`. Les pages locales ou de préproduction restent protégées, même si un opérateur active le drapeau SEO par erreur.
- `/sitemap.xml` liste les fichiers de pages et de produits par langue, par blocs de 1 000. Seuls les produits publiés et les catégories actuelles entrent dans les listes ; pas de dernière modification inventée. Les URL produit proviennent de la projection publique : la cohérence entre index et fiches doit rester maintenue par la source catalogue.
- `/robots.txt` devient dépendant de l'environnement : fermeture locale, découverte du sitemap en production validée. Une règle Apache précède le test des fichiers existants ; le fichier statique conserve un repli fermé. Les routes privées utilisent des contrôles d'accès et des en-têtes `noindex`, sans bloquer la lecture de ces en-têtes par robots.
- Product et BreadcrumbList décrivent les fiches. Offer ne paraît que sur le domaine public, avec paiement réel configuré, prix valide, photo, disponibilité visible, produit simple et sans signalement catalogue. Le mode carte test, les commandes différées et les variantes non sélectionnées ne produisent pas d'offre. Cela ne signifie pas qu'un produit sans Offer remplisse les critères d'un résultat enrichi Google.
- Flux RSS XML préparé à `/google/merchant/fr.xml`, `/google/merchant/en.xml`, `/google/merchant/de.xml`. Activation séparée, prix issus du catalogue public et contrôle de concordance avec l'inventaire. Une seule lecture d'inventaire, aucune écriture, aucune quantité de stock exposée. Disponibilité = quantité moins réservation ; le planificateur d'expiration doit rester fonctionnel, sinon les réservations expirées peuvent sous-estimer temporairement la disponibilité.
- Le flux exclut les produits sans revue spécifique, avec données modifiées depuis cette revue, signalements, variantes non prises en charge, description/photo/prix/état manquants ou identifiants non justifiés. Aucun flux vide ne se présente comme réussi : réponse 503 si rien n'est exportable ou si la lecture échoue ; réponse 404 tant que la publication est fermée.

Les fichiers de configuration privés, la base MariaDB, XAMPP, les données commerciales et les campagnes n'ont pas été modifiés pour ces corrections. Ce rapport concerne **le dépôt de travail** ; la copie installée dans htdocs et le futur hébergement exigent une synchronisation vérifiée distincte.

## Audit initial du catalogue hors base

Commande effectuée sur la source fichiers, administration et inventaire désactivés :

```sh
HOMYA_CATALOGUE_SOURCE=files HOMYA_ADMIN_MANAGEMENT_ENABLED=false HOMYA_INVENTORY_ENABLED=false HOMYA_CHECKOUT_ENABLED=false php -d memory_limit=512M artisan homya:google-audit --locale=fr --json
```

Rapport : `output/qa-seo/google-audit-fr.json`. Aucun appel Google ni vérification du stock courant. L'état français `neuf` se mappe correctement vers `new` ; les codes `occasion` et `reconditionne` disposent aussi d'un mapping explicite.

| Observation FR | Produits concernés | Impact |
|---|---:|---|
| Catalogue audité | 3 709 | Source fichiers, pas un diagnostic Merchant Center |
| Revue Merchant non fournie | 3 709 | À réaliser avant inclusion dans le flux |
| Identifiants fabricant non validés pour le flux | 3 709 | Ne signifie pas que les fabricants n'en possèdent pas |
| Provenance des titres/descriptions non confirmée | 3 709 | Distinguer texte ordinaire et création/modification par IA |
| Signalements catalogue | 417 | Corriger les données concernées avant inclusion |
| Titre/description absent ou dépassant les limites de flux | 206 | Revoir le contenu réel, sans le fabriquer |
| Variantes à prendre en charge | 26 | URL sélectionnant la variante et attributs réels nécessaires |

Les lignes se recoupent. Le contrôle ne qualifie pas les photos visuellement, les droits d'utilisation des contenus, les exigences par catégorie, les traductions humaines, les taxes, les conditions de transport ni les diagnostics du compte. Les audits EN/DE sont disponibles par `--locale=en` ou `--locale=de`, mais ne sont pas annoncés comme exécutés dans cette phase.

## Revue et données Merchant

`storage/app/private/google/merchant-products.json` est un fichier privé, ignoré par Git. Il n'est pas créé ni rempli automatiquement. Le marchand doit valider les faits depuis ses produits, fournisseurs et données commerciales. Structure attendue pour chaque slug :

```json
{
  "slug-du-produit": {
    "approved": true,
    "fingerprints": {
      "fr": "empreinte réelle du rapport FR",
      "en": "empreinte réelle du rapport EN",
      "de": "empreinte réelle du rapport DE"
    },
    "gtin": "code fabricant réel, conservé comme chaîne",
    "mpn": "référence fabricant réelle si applicable",
    "title_source": "default",
    "description_source": "default"
  }
}
```

Ce schéma est illustratif et ses valeurs textuelles ne sont pas valides pour publier. Omettre les identifiants non applicables. `identifier_exists: false` est possible uniquement après confirmation que le produit n'a réellement pas les identifiants applicables ; l'absence de données dans HOMYA n'est pas une exemption. Un GTIN doit satisfaire longueur et checksum, sans que cela prouve son attribution au bon produit. Une référence interne HOMYA ne devient pas automatiquement un MPN.

`title_source` et `description_source` valent `default` ou `trained_algorithmic_media`, d'après la provenance confirmée. Le deuxième cas utilise les attributs structurés correspondants du flux. Aucun diagnostic ne les déduit automatiquement. Le changement des faits publics invalide l'empreinte et exige une nouvelle revue ; vérifier aussi les images, identifiants et exigences particulières (étiquetage énergétique/EPREL lorsque applicable, lots, variantes, etc.). Le contrôle applicatif n'est pas un moteur exhaustif de toutes les politiques Google.

## Matrice des services et points à terminer

| Condition / service | Applicabilité | Responsable | Contrôle attendu | État |
|---|---|---|---|---|
| Pages publiques / Search | Applicable | SEO / Qualité | Domaine HTTPS, réponse 200, exploration, contenus et performance réels | En cours : corrections locales testées |
| Diagnostic / Search Console | Applicable | SEO / Google Services | Propriété du domaine, sitemap, inspection des URL et rapports | Bloqué : domaine et compte à créer |
| Catalogue / Merchant Center | Conditionnel, objectif confirmé | E-commerce / Google Services | Pays/programme, identité et confiance, règlement, produits et politiques | Bloqué : site public et données à valider |
| Fiches gratuites | Conditionnel | Google Services | Programme et marchés à contrôler dans le compte | Non évalué côté compte |
| Flux produit | Applicable à la préparation Merchant | E-commerce / Google Services | Revue, inventaire, correspondance page/flux, diagnostics | En cours, export public fermé |
| Données structurées et landing pages | Applicable | SEO / E-commerce / Design | Rich Results Test et contrôle visuel/page/offre sur URL publique | En cours, contrôles locaux réussis |
| Shopping Ads | Objectif confirmé, éligibilité à vérifier | Growth / Google Ads | Lien comptes, approbation produits, conversions, budget validé | Bloqué ; aucune campagne lancée |
| Performance Max | Choix de campagne manquant | Growth / Google Ads | Objectifs et dépendances spécifiques | Non évalué |
| GA4 / conversions Ads | Conditionnel | Analytics / conformité / Google Ads | Plan de mesure, consentement applicable, achat payé et déduplication | Non évalué ; aucun tag installé par ce travail |
| GTM | Conditionnel | Analytics / Security | Besoin de gestion des tags à confirmer | Non évalué |
| Business Profile | Information manquante | Google Services / métier | Modèle éligible et contact physique réel | Non évalué |
| Local Inventory | Information manquante | E-commerce / Google Services | Magasin admissible et stock local distinct | Non évalué |

## Raccordement au lancement

1. Fournir le domaine exact, par exemple le nom acheté chez Hostinger, puis vérifier la formule, Laravel/PHP, les accès catalogue, HTTPS et les règles du serveur. Aucune compatibilité Hostinger n'est présumée.
2. Installer les corrections avec sauvegarde et recette. Configurer `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` et `HOMYA_PUBLIC_URL` sur la même origine HTTPS, sans chemin ni port. Définir `HOMYA_SEO_INDEXING_ENABLED=true` seulement après la recette. Toute autre origine reste non indexable ; les redirections HTTP/HTTPS et www doivent être configurées et testées sur le serveur public.
3. Vérifier le `/robots.txt` réellement servi : il doit provenir du contrôleur. Sur Nginx, une règle exacte doit transmettre cette URL à Laravel ; une règle qui sert directement le fichier statique laisserait l'exploration fermée. Contrôler en-têtes, sitemap et toutes les langues, plus les pages 404/503 et les zones privées. Purger/régénérer les caches Laravel après changement de configuration.
4. Créer et vérifier Search Console (propriété Domaine par DNS recommandée). À défaut, la propriété préfixe d'URL peut utiliser `HOMYA_GOOGLE_SITE_VERIFICATION`. Soumettre `/sitemap.xml`, inspecter accueil, catégorie et fiches dans chaque langue. Examiner exclusions, canonical choisi et données structurées.
5. Achever les pages publiques de contact, livraison, paiement, retours et les documents commerciaux pertinents avec les faits confirmés. Un statut préparatoire ne disparaît pas par activation SEO. Revoir les textes et les traductions du catalogue ainsi que les droits sur les contenus.
6. Activer et tester un moyen de règlement réel, la concordance des prix/stock et les expirations de réservations. Réaliser les revues Merchant par langue, identifiants et catégorie. Définir `HOMYA_MERCHANT_FEED_ENABLED=true` uniquement après ces contrôles ; tester les trois URL XML depuis Internet.
7. Dans Merchant Center : vérifier/revendiquer le domaine, configurer les marchés FR/BE/DE et les langues pertinentes, livraison/retours, sources de données et diagnostics. Les langues du flux, de la page et du checkout doivent être cohérentes. Choisir la combinaison réellement supportée dans le compte ; l'anglais ne remplace pas automatiquement les obligations locales.
8. Faire contrôler les URL par Rich Results Test et PageSpeed Insights, notamment sur mobile. Les optimisations d'images et d'assets existantes sont conservées ; aucun score Core Web Vitals de production n'est revendiqué ici.
9. Définir la mesure avec Analytics avant une campagne : une commande préparée ou en attente n'est pas une vente payée. Tester conversions, valeur/devise et `transaction_id`, puis relier Merchant Center et Google Ads. Lancement et budget restent des actions à autoriser séparément.

Suivi conseillé après publication : Search Console (exploration/indexation et résultats enrichis), Merchant Center (rejets, écarts prix/stock et compte), disponibilité du flux et du sitemap, paiements et conversions. Aucun suivi automatisé ni notification récurrente n'a été activé.

## Sources officielles

Consultées le **10/10/2026**, pour la préparation Search et Merchant d'un catalogue multilingue vendu en EUR sur les marchés déclarés. Relecture requise avant publication, puis lors d'un changement de pays, programme ou politique. Les conditions du compte réel restent à vérifier.

- [Canonical](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls), [pagination et facettes](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading), [versions linguistiques](https://developers.google.com/search/docs/specialty/international/localized-versions) : conventions appliquées aux URL publiques et paginées.
- [Données structurées des fiches marchandes](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing) : propriétés adaptées aux faits visibles ; balisage et affichage sont distincts.
- [Spécifications des données produit](https://support.google.com/merchants/answer/7052112?hl=fr) : noms/valeurs des attributs, identifiants, cohérence avec les pages et provenance des textes.
- [Règlement des achats](https://support.google.com/merchants/answer/9158778?hl=fr) : exigences du parcours de vente à vérifier sur le site public.

La préparation technique améliore la compréhension du site. Google décide de l'indexation, de l'approbation et de la diffusion ; les ventes dépendent aussi de l'offre, de la qualité des données, de la campagne et de la mesure.

## Vérification locale

Les preuves sont regroupées dans `output/qa-seo/`. Tests SEO et flux : fixtures fichiers et connexions simulées, sans MariaDB réelle ni réseau Google. Build Vite exécuté avec succès. La synthèse de clôture renseigne séparément le résultat de la régression générale et ses éventuelles limites ; une réussite locale ne remplace pas la recette du domaine public.

Résultats de clôture : **423 tests / 10 364 assertions** réussis dans la suite générale hors MariaDB, avec mémoire CLI temporairement portée à 512 Mo. Le premier lancement à 128 Mo a atteint la limite mémoire ; le lancement suivant dans le sandbox a rencontré 86 erreurs de création de sockets synthétiques. La relance autorisée hors sandbox a réussi sans modifier la configuration PHP, accéder à MariaDB réelle ni contacter Google. Les 20 tests SEO/Merchant réussissent également dans le sandbox ; le dernier contrôle ciblé couvre en plus le refus d'une empreinte de revue mal formée. Pint et `git diff --check` réussis. Aucun navigateur public, Rich Results Test distant ni Core Web Vitals de production vérifié.
