# Suivi des commandes et présentation — 10/10/2026

Demande utilisateur : saisir un numéro de commande pour connaître son traitement, afficher l’e-mail dans le bandeau supérieur, retirer les liens supérieurs « Bien choisir » et « Catalogue & FAQ », actualiser la réponse « Puis-je commander maintenant ? » et retirer les annonces de préparation des pages clients. L’utilisateur a ensuite retiré la demande d’ouverture du paiement par carte : aucun prestataire, encaissement ou activation carte n’est ajouté.

## Fonctionnement du suivi

Les pages `/fr/suivi`, `/en/suivi` et `/de/suivi` proposent un formulaire POST protégé par CSRF. La référence est l’UUID v4 complet déjà indiqué dans les confirmations et documents de commande ; aucune nouvelle numérotation ni migration n’est nécessaire.

Le service lit le statut enregistré dans `commerce_orders` et, après paiement confirmé, les étapes réelles de `commerce_admin_shipments` gérées par l’administration. Étapes : commande enregistrée, paiement confirmé, préparation, expédition, livraison ; annulation et expiration sont présentées séparément. Aucune étape de livraison n’est déduite de l’écoulement du temps ou simulée.

Seuls la référence et le stade de traitement sont retournés. Aucun nom, e-mail client, adresse, montant, article, document, lien transporteur ou identifiant d’accès privé n’est divulgué. La consultation ne donne aucun accès au dossier privé et ne change pas le propriétaire de session. Le numéro reste dans le corps POST ; réponses privées sans cache, sans referrer et exclues de l’indexation. Les requêtes sont limitées à 10 par minute ; les références invalides ou inconnues produisent une réponse générique.

## Présentation

Le bandeau supérieur affiche l’e-mail public validé avec lien `mailto:` et le lien de suivi. Les deux liens de conseil/catalogue demandés sont retirés du bandeau ; « Bien choisir » est aussi retiré du menu supérieur mobile. Les pages et liens de conseil restent accessibles ailleurs dans le site.

La FAQ décrit la commande et le suivi. Les bandeaux de préouverture, avis techniques sur la qualité des données et annonces de configuration sont retirés ou remplacés par des instructions client en FR/EN/DE. Les moyens de contact, instructions de règlement, réservation de 72 heures, réception du paiement, livraison et organisation des retours restent cohérents avec les traitements existants. Les contrôles de stock, prix, paiement et autorisation sont conservés. Les erreurs d’une opération réelle restent traitées ; aucun succès de paiement ou de livraison n’est fabriqué.

Les informations techniques restant à traiter appartiennent aux rapports internes. Cette modification de présentation ne certifie pas une conformité juridique, un paiement carte opérationnel ni une publication du site sur Internet.

## Vérifications

- 60 tests du commerce, du suivi, des contacts, des informations légales et de la navigation : 5 068 assertions.
- 106 tests de régression catalogue, accueil, pages, retours, documents commerciaux, SEO, Merchant Center, navigation et en-têtes : 4 180 assertions.
- Après les derniers ajustements de texte : 24 tests ciblés de suivi, contact et informations légales, 3 246 assertions.
- Syntaxe PHP et formatage ciblé Pint valides. Tests de données métier sur SQLite en mémoire ou doubles ; aucune migration ni écriture dans MariaDB réelle.

Installation limitée aux fichiers de cette demande, sauvegarde avant copie, protection contre les modifications concurrentes et préservation de `.env`. Les routes XAMPP reçoivent uniquement les deux routes et l’import du contrôleur de suivi, en conservant les autres différences de la copie installée. Les contrôles HTTP et navigateur après installation sont consignés dans `output/qa-storefront-20261010/`.
