<?php
/**
 * Paynow credentials.
 *
 * Integration ID = numeric ID from Paynow
 * Integration Key = the long secret (UUID) emailed from Paynow
 */

declare(strict_types=1);

return [
    'integration_id'  => getenv('PAYNOW_INTEGRATION_ID') ?: '26466',
    'integration_key' => getenv('PAYNOW_INTEGRATION_KEY') ?: '9624815e-0ca4-41ec-9ecf-dab607c25869',

    // Required while the integration is in TEST mode (must be the merchant Paynow email).
    // Set to '' after you switch the integration to LIVE in the Paynow dashboard.
    'test_mode'       => true,
    'merchant_email'  => getenv('PAYNOW_MERCHANT_EMAIL') ?: 'tandimapolisa@gmail.com',

    // Book editions → USD unit price (must match the select values in index.html)
    'book_prices' => [
        'Special signed edition $100' => 100.00,
        'Special General signed $50'  => 50.00,
        'General signed $30'          => 30.00,
        'General not signed $25'      => 25.00,
    ],

    'orders_dir' => __DIR__ . '/orders',
];
