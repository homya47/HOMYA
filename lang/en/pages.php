<?php

$legalPages = require __DIR__.'/legal.php';

return [
    'help' => 'Help & advice',
    'information' => 'Information',
    'pending' => 'Service in preparation',
    'pending_detail' => 'You can browse the catalogue. Sales are not open yet.',
    'questions' => 'Frequently asked questions',
    'entries' => [
        'cookies' => $legalPages['entries']['cookies'],
        'a-propos' => [
            'title' => 'About HOMYA',
            'intro' => 'Home, made simple.',
            'paragraphs' => ['HOMYA is an independent retailer of stoves and wood heating equipment, refrigerators, washing machines, food processors and furniture.', 'New appliances in sealed packaging, delivered in 3 to 7 days by our own carrier, with a 2-year legal guarantee of conformity and an invoice in your name.'],
            'pending' => false,
        ],
        'provenance' => [
            'title' => 'Catalogue & frequently asked questions',
            'intro' => 'Clear guidance for exploring HOMYA.',
            'paragraphs' => ['Product pages bring together the available photos and technical information for each model. Some flagged data still needs verification; a displayed price does not confirm current availability.', 'HOMYA is an independent retailer. Our appliances are new and supplied in sealed packaging, with a 2-year legal guarantee of conformity and an invoice in your name.'],
            'pending' => false,
        ],
        'conseiller' => [
            'title' => 'Choosing your appliance',
            'intro' => 'Find a model for your project.',
            'paragraphs' => ['Start with a department, then select a category and your budget. Compare the dimensions, capacities, power and features actually listed on the product pages.', 'For heating appliances, also check installation requirements with a qualified professional.'],
            'pending' => false,
        ],
        'contact' => [
            'title' => 'Contact',
            'intro' => 'A question about a product, delivery, your order or a return? Find HOMYA contact details here.',
            'paragraphs' => ['State the subject of your request and, where needed, the product or order reference. Do not attach passwords or complete card numbers.', 'You can write to HOMYA at the correspondence address below. Email, business telephone and opening hours will be added when confirmed. No contact form or automatic message sending is currently active.', 'The registered office also receives returned goods, including bulky items. See Returns & withdrawal for notification and free transport. No shop collection or public warehouse is advertised.'],
            'pending' => true,
        ],
        'livraison' => [
            'title' => 'Delivery',
            'intro' => 'Free delivery in France, Belgium and Germany.',
            'paragraphs' => ['Your appliances are delivered in 3–7 days from payment by our own carrier, according to HOMYA’s declared commitment.', 'Delivery is free in France, Belgium and Germany: stated delivery charge €0.', 'Before sales open, the use of working or calendar days, handling, transit and covered areas will be specified. The dispatch location remains to be provided.', 'Access, unloading and handover arrangements for pallets, furniture and bulky appliances must be confirmed. Installation, commissioning and collection of an old appliance are not assumed to be included.', 'Returns are free for withdrawal notified within 14 days after receipt. The receiving address is confirmed in Returns & withdrawal; suitable transport must be arranged with HOMYA.'],
            'pending' => true,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Payment',
            'intro' => 'Payment methods must be connected before sales open.',
            'paragraphs' => ['The card payment form is retained pending provider configuration. The transfer page after ordering is prepared; business bank details remain to be provided. The transfer deadline and stock reservation will be 72 hours.', 'Visa, Mastercard and CB are contemplated methods. Logos do not confirm acceptance or an active payment service.', 'Before confirming an order, the euro price, applicable VAT, mandatory charges and total due must be visible. Stated delivery charges are €0 in France, Belgium and Germany.', 'Charging arrangements, confirmation and refund crediting times must be specified with the provider. No payment, transfer or charge is possible on this local version.'],
            'pending' => true,
        ],
        'suivi' => [
            'title' => 'Order tracking',
            'intro' => 'Tracking will be available after orders open.',
            'paragraphs' => ['No orders or carrier connection are active. This page does not request an order number or simulate a delivery status.'],
            'pending' => true,
        ],
        'panier' => [
            'title' => 'Basket',
            'intro' => 'Purchases are not open yet.',
            'paragraphs' => ['You can explore products and their specifications. Stock, quantity, tax and delivery rules must be validated before the basket and orders are enabled.'],
            'pending' => true,
        ],
        'avis' => [
            'title' => 'Customer reviews',
            'intro' => 'HOMYA reviews will appear when available.',
            'paragraphs' => ['No reviews or sales history from another store are presented as HOMYA reviews. This version does not collect testimonials.'],
            'pending' => true,
        ],
        'mentions-legales' => $legalPages['entries']['mentions-legales'],
        'cgv' => $legalPages['entries']['cgv'],
        'confidentialite' => $legalPages['entries']['confidentialite'],
    ],
    'faq' => [[
        'question' => 'How can I find a product?',
        'answer' => 'Use the search at the top of the page or catalogue filters: category, brand, condition and price range, according to the available data.',
    ], [
        'question' => 'Can I change the language?',
        'answer' => 'The FR, EN and DE selector preserves the product page or catalogue context. The site indicates when an essential translation is missing.',
    ], [
        'question' => 'Where can I find specifications?',
        'answer' => 'Open a product page, then use the Specifications link. Additional photos are accessible through thumbnails when available.',
    ], [
        'question' => 'Can I order now?',
        'answer' => 'You can prepare your basket. Payments are temporarily unavailable.',
    ]],
];
