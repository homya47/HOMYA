<?php

$legalPages = require __DIR__.'/legal.php';

return [
    'help' => 'Aide & conseils',
    'information' => 'Informations',
    'pending' => 'Service en préparation',
    'pending_detail' => 'Le catalogue est consultable. Les ventes ne sont pas encore ouvertes.',
    'questions' => 'Questions fréquentes',
    'entries' => [
        'cookies' => $legalPages['entries']['cookies'],
        'a-propos' => [
            'title' => 'À propos de HOMYA',
            'intro' => 'La maison, tout simplement.',
            'paragraphs' => ['HOMYA, revendeur indépendant de poêles et chauffage au bois, réfrigérateurs, lave-linge, robots de cuisine et mobilier.', 'Appareils neufs sous scellé, livrés en 3 à 7 jours par notre propre transporteur, garantie légale de conformité de 2 ans et facture à votre nom.'],
            'pending' => false,
        ],
        'provenance' => [
            'title' => 'Catalogue & questions fréquentes',
            'intro' => 'Les repères pour explorer HOMYA en toute clarté.',
            'paragraphs' => ['Les fiches réunissent les photos et les informations techniques disponibles pour chaque référence. Certaines données signalées restent à vérifier ; un prix affiché ne confirme pas la disponibilité actuelle.', 'HOMYA est un revendeur indépendant. Nos appareils sont neufs sous scellé, avec une garantie légale de conformité de 2 ans et une facture à votre nom.'],
            'pending' => false,
        ],
        'conseiller' => [
            'title' => 'Bien choisir',
            'intro' => 'Trouver un appareil adapté à votre projet.',
            'paragraphs' => ['Commencez par un univers, puis choisissez une catégorie et votre budget. Comparez les dimensions, capacités, puissances et équipements réellement indiqués sur les fiches.', 'Pour les appareils de chauffage, vérifiez également les contraintes d’installation avec un professionnel qualifié.'],
            'pending' => false,
        ],
        'contact' => [
            'title' => 'Contact',
            'intro' => 'Une question sur un produit, la livraison, votre commande ou un retour ? Retrouvez les coordonnées HOMYA.',
            'paragraphs' => ['Pour faciliter le traitement de votre demande, indiquez son objet et, si nécessaire, la référence du produit ou de la commande. Ne joignez aucun mot de passe ni numéro complet de carte.', 'Vous pouvez écrire à HOMYA à l’adresse de correspondance ci-dessous. L’e-mail, le téléphone professionnel et les horaires seront ajoutés lorsqu’ils seront confirmés. Aucun formulaire de contact ni envoi automatique de message n’est actuellement actif.', 'Le siège reçoit également les produits retournés, y compris les biens volumineux. Consultez Retours & rétractation pour la notification et le transport gratuit. Aucun retrait en magasin ni entrepôt ouvert au public n’est annoncé.'],
            'pending' => true,
        ],
        'livraison' => [
            'title' => 'Livraison',
            'intro' => 'Livraison gratuite en France, en Belgique et en Allemagne.',
            'paragraphs' => ['Vos appareils sont livrés en 3 à 7 jours à compter du paiement par notre propre transporteur, selon l’engagement déclaré par HOMYA.', 'La livraison est gratuite en France, en Belgique et en Allemagne : frais de livraison annoncés de 0 €.', 'Avant les ventes, le calcul du délai en jours ouvrés ou calendaires, la préparation, le transport et les zones desservies seront précisés. Le lieu d’expédition reste à renseigner.', 'Pour les palettes, meubles et appareils volumineux, les conditions d’accès, de déchargement et de remise doivent être confirmées. Installation, mise en service et reprise de l’ancien équipement ne sont pas présumées incluses.', 'Les retours sont gratuits pour les rétractations notifiées dans les 14 jours après réception. L’adresse de retour est confirmée dans Retours & rétractation ; le transport adapté reste à organiser avec HOMYA.'],
            'pending' => true,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Paiement',
            'intro' => 'Des moyens de paiement à connecter avant l’ouverture des ventes.',
            'paragraphs' => ['Le formulaire de paiement par carte est conservé en attente de configuration d’un prestataire. La page de virement après commande est préparée ; le RIB professionnel reste à renseigner. Le délai de virement et de réservation des articles sera de 72 heures.', 'Visa, Mastercard et CB sont des moyens envisagés. Les logos ne constituent pas une confirmation d’acceptation ou de paiement actif.', 'Avant la validation d’une commande, le prix en euros, la TVA applicable, les frais obligatoires et le total à payer devront être visibles. Les frais de livraison annoncés sont de 0 € en France, en Belgique et en Allemagne.', 'Les modalités d’encaissement, la confirmation et les délais de crédit des remboursements doivent être précisés avec le prestataire. Aucun paiement, virement ou encaissement n’est possible sur cette version locale.'],
            'pending' => true,
        ],
        'suivi' => [
            'title' => 'Suivi de commande',
            'intro' => 'Le suivi sera disponible après l’ouverture des commandes.',
            'paragraphs' => ['Aucune commande ni connexion transporteur n’est active. Cette page ne demande pas de numéro de commande et ne simule aucun statut de livraison.'],
            'pending' => true,
        ],
        'panier' => [
            'title' => 'Panier',
            'intro' => 'Les achats ne sont pas encore ouverts.',
            'paragraphs' => ['Vous pouvez explorer les produits et leurs caractéristiques. Les règles de stock, quantités, taxes et livraison doivent être validées avant l’activation du panier et des commandes.'],
            'pending' => true,
        ],
        'avis' => [
            'title' => 'Avis clients',
            'intro' => 'Les avis HOMYA seront présentés lorsqu’ils seront disponibles.',
            'paragraphs' => ['Aucun avis ni historique de vente d’une autre boutique n’est présenté comme un avis HOMYA. Cette version ne recueille pas de témoignage.'],
            'pending' => true,
        ],
        'mentions-legales' => $legalPages['entries']['mentions-legales'],
        'cgv' => $legalPages['entries']['cgv'],
        'confidentialite' => $legalPages['entries']['confidentialite'],
    ],
    'faq' => [[
        'question' => 'Comment rechercher un produit ?',
        'answer' => 'Utilisez la recherche en haut de page ou les filtres du catalogue : catégorie, marque, état et intervalle de prix selon les données disponibles.',
    ], [
        'question' => 'Puis-je changer de langue ?',
        'answer' => 'Le sélecteur FR, EN et DE conserve la fiche ou le contexte du catalogue. Si une traduction indispensable manque, le site l’indique.',
    ], [
        'question' => 'Comment consulter les caractéristiques ?',
        'answer' => 'Ouvrez une fiche, puis utilisez le lien Caractéristiques. Les photos supplémentaires sont accessibles par les miniatures lorsqu’elles sont disponibles.',
    ], [
        'question' => 'Puis-je commander maintenant ?',
        'answer' => 'Vous pouvez préparer votre panier. Les paiements sont temporairement indisponibles.',
    ]],
];
