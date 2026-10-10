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
            'title' => 'Our products',
            'intro' => 'Clear guidance for exploring HOMYA.',
            'paragraphs' => ['Browse the photos, specifications and options for each product to prepare your purchase.', 'HOMYA is an independent retailer. Our appliances are new and sealed, with a 2-year statutory conformity guarantee and an invoice in your name.'],
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
            'paragraphs' => ['State your enquiry and, where relevant, the product or order reference.', 'Contact HOMYA by email, telephone, WhatsApp or SMS using the details below.', 'The registered office also receives returned goods, including bulky items. See Returns & withdrawal for the arrangements.'],
            'pending' => false,
        ],
        'livraison' => [
            'title' => 'Delivery',
            'intro' => 'Free delivery in France, Belgium and Germany.',
            'paragraphs' => ['Your appliances are delivered in 3 to 7 days from payment by our own carrier.', 'Delivery is free in France, Belgium and Germany.', 'For furniture and bulky appliances, share access details to arrange delivery with HOMYA.', 'Returns are free for withdrawal notified within 14 days of receipt. See Returns & withdrawal for the address and return arrangements.'],
            'pending' => false,
        ],
        'retractation' => $legalPages['entries']['retractation'],
        'paiement' => [
            'title' => 'Payment',
            'intro' => 'Find the information you need to pay for your order.',
            'paragraphs' => ['Confirm your basket and contact details to register your order. Payment instructions are associated with your order reference.', 'The summary lists your items, quantities and total in euros including taxes. Delivery is free in France, Belgium and Germany.', 'Your items are reserved for 72 hours after confirmation. Order processing continues once payment is confirmed.', 'For payment enquiries, contact HOMYA with your order number.'],
            'pending' => false,
        ],
        'suivi' => [
            'title' => 'Order tracking',
            'intro' => 'Enter your order number to see its processing progress.',
            'paragraphs' => ['Check order registration, payment, preparation, dispatch and delivery.'],
            'pending' => false,
        ],
        'panier' => [
            'title' => 'Basket',
            'intro' => 'Review your items and confirm your order.',
            'paragraphs' => ['Check your products, options and quantities before entering your contact details.'],
            'pending' => false,
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
        'answer' => 'Yes. Add your items to the basket, check quantities and confirm your order. Keep your order number to see its processing progress on the Order tracking page.',
    ]],
];
