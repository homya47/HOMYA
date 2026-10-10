<?php

return [
    'title' => 'Order tracking',
    'intro' => 'Enter your order number to see its processing progress.',
    'lookup' => 'Find my order', 'reference' => 'Order number',
    'help' => 'Copy the complete reference from your order confirmation or HOMYA document.',
    'submit' => 'Track my order',
    'not_found' => 'We could not find this order. Check the number or contact HOMYA.',
    'result' => 'Your order status', 'progress' => 'Order processing steps',
    'assistance' => 'Need help?',
    'assistance_help' => 'Our team can help. Include your order number with your enquiry.',
    'stages' => [
        'registered' => 'Order registered', 'paid' => 'Payment confirmed',
        'processing' => 'Preparing', 'shipped' => 'Shipped', 'delivered' => 'Delivered',
        'cancelled' => 'Order cancelled', 'expired' => 'Reservation expired',
    ],
    'details' => [
        'registered' => 'Your order is registered. Processing continues once payment is confirmed.',
        'paid' => 'Your payment is confirmed. Your order will be handled for preparation.',
        'processing' => 'Your order is being prepared.', 'shipped' => 'Your order has been shipped.',
        'delivered' => 'Delivery of your order is confirmed.',
        'cancelled' => 'This order has been cancelled. Contact HOMYA with any questions.',
        'expired' => 'The reservation period for this order has ended. Contact HOMYA to continue your purchase.',
    ],
];
