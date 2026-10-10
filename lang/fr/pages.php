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
            'title' => 'Nos produits',
            'intro' => 'Les repères pour explorer HOMYA en toute clarté.',
            'paragraphs' => ['Consultez les photos, les caractéristiques et les options de chaque produit pour préparer votre achat.', 'HOMYA est un revendeur indépendant. Nos appareils sont neufs sous scellé, avec une garantie légale de conformité de 2 ans et une facture à votre nom.'],
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
            'paragraphs' => ['Indiquez l’objet de votre demande et, si nécessaire, la référence du produit ou de la commande.', 'Contactez HOMYA par e-mail, téléphone, WhatsApp ou SMS aux coordonnées ci-dessous.', 'Le siège reçoit également les produits retournés, y compris les biens volumineux. Consultez Retours & rétractation pour les modalités.'],
            'pending' => false,
        ],
        'livraison' => [
            'title' => 'Livraison',
            'intro' => 'Livraison gratuite en France, en Belgique et en Allemagne.',
            'paragraphs' => ['Vos appareils sont livrés en 3 à 7 jours à compter du paiement par notre propre transporteur.', 'La livraison est gratuite en France, en Belgique et en Allemagne.', 'Pour les meubles et appareils volumineux, indiquez les conditions d’accès afin d’organiser la livraison avec HOMYA.', 'Les retours sont gratuits pour les rétractations notifiées dans les 14 jours après réception. Consultez Retours & rétractation pour l’adresse et l’organisation du retour.'],
            'pending' => false,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Paiement',
            'intro' => 'Retrouvez les informations utiles pour régler votre commande.',
            'paragraphs' => ['Validez votre panier et vos coordonnées pour enregistrer votre commande. Les instructions de règlement sont associées à votre référence de commande.', 'Le récapitulatif présente les articles, les quantités et le montant total en euros, toutes taxes comprises. La livraison est gratuite en France, en Belgique et en Allemagne.', 'Vos articles sont réservés pendant 72 heures après validation. Le traitement de la commande se poursuit après confirmation du règlement.', 'Pour toute question concernant votre règlement, contactez HOMYA en indiquant votre numéro de commande.'],
            'pending' => false,
        ],
        'suivi' => [
            'title' => 'Suivi de commande',
            'intro' => 'Saisissez votre numéro de commande pour retrouver les étapes de son traitement.',
            'paragraphs' => ['Consultez l’enregistrement, le paiement, la préparation, l’expédition et la livraison de votre commande.'],
            'pending' => false,
        ],
        'panier' => [
            'title' => 'Panier',
            'intro' => 'Retrouvez vos articles et validez votre commande.',
            'paragraphs' => ['Vérifiez les produits, les options et les quantités de votre panier avant de compléter vos coordonnées.'],
            'pending' => false,
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
        'answer' => 'Oui. Ajoutez vos articles au panier, vérifiez les quantités et validez votre commande. Conservez votre numéro pour consulter les étapes de son traitement sur la page Suivi de commande.',
    ]],
];
