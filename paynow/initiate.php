<?php
/**
 * Start a Paynow payment for a book pre-order.
 * Expects JSON or form POST from the book modal.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';

if (
    $config['integration_id'] === 'INTEGRATION_ID'
    || $config['integration_key'] === 'INTEGRATION_KEY'
    || $config['integration_id'] === ''
    || $config['integration_key'] === ''
) {
    paynow_json([
        'success' => false,
        'error'   => 'Paynow is not configured yet. Add your Integration ID and Key in paynow/config.php.',
    ], 503);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paynow_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

$name       = trim((string) ($input['name'] ?? ''));
$email      = trim((string) ($input['email'] ?? ''));
$phone      = trim((string) ($input['phone'] ?? ''));
$edition    = trim((string) ($input['edition'] ?? ''));
$quantity   = max(1, (int) ($input['quantity'] ?? 1));
$collection = trim((string) ($input['collection'] ?? ''));
$details    = trim((string) ($input['collection_details'] ?? ''));
$message    = trim((string) ($input['message'] ?? ''));
$method     = strtolower(trim((string) ($input['paynow_method'] ?? 'web'))); // web | ecocash | onemoney

if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    paynow_json(['success' => false, 'error' => 'Please provide a valid name and email.'], 400);
}

$prices = $config['book_prices'];
if (!isset($prices[$edition])) {
    paynow_json(['success' => false, 'error' => 'Please select a valid book edition.'], 400);
}

$unitPrice = (float) $prices[$edition];
$total = round($unitPrice * $quantity, 2);

if ($total < 1) {
    paynow_json(['success' => false, 'error' => 'Invalid payment amount.'], 400);
}

$reference = 'BOOK-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

// Web checkout:
// - LIVE: pass the buyer's email so Paynow auto-starts guest checkout (skips login for new emails).
// - TEST: leave empty (Paynow only allows the merchant email in test mode, and that forces a password login).
// Mobile express always needs an auth email.
$authEmail = '';
if ($method === 'ecocash' || $method === 'onemoney') {
    $authEmail = $email;
    if (!empty($config['test_mode']) && !empty($config['merchant_email'])) {
        $authEmail = (string) $config['merchant_email'];
    }
} elseif (empty($config['test_mode'])) {
    $authEmail = $email;
}

$paynow = paynow_create_client($config, $reference);
$payment = $paynow->createPayment($reference, $authEmail);

$itemLabel = sprintf('%s × %d — Full Circle (Tandi Mapolisa)', $edition, $quantity);
$payment->add($itemLabel, $total);

try {
    if ($method === 'ecocash' || $method === 'onemoney') {
        $msisdn = preg_replace('/\D+/', '', $phone);
        if (strlen($msisdn) < 9) {
            paynow_json(['success' => false, 'error' => 'Enter a valid EcoCash / OneMoney phone number.'], 400);
        }
        // Normalise to 07xxxxxxxx
        if (str_starts_with($msisdn, '263')) {
            $msisdn = '0' . substr($msisdn, 3);
        }
        $response = $paynow->sendMobile($payment, $msisdn, $method);
    } else {
        $response = $paynow->send($payment);
    }
} catch (\Paynow\Payments\InvalidIntegrationException $e) {
    paynow_json([
        'success' => false,
        'error'   => 'Invalid Paynow Integration ID or Key. Update paynow/config.php with your real credentials.',
    ], 502);
} catch (Throwable $e) {
    paynow_json([
        'success' => false,
        'error'   => 'Could not reach Paynow: ' . $e->getMessage(),
    ], 502);
}

if (!$response->success()) {
    $paynowError = method_exists($response, 'errors') ? trim((string) $response->errors()) : '';
    paynow_json([
        'success' => false,
        'error'   => $paynowError !== ''
            ? ('Paynow error: ' . $paynowError)
            : 'Paynow declined the payment request.',
        'paynow'  => method_exists($response, 'data') ? $response->data() : null,
    ], 502);
}

$order = [
    'reference'           => $reference,
    'name'                => $name,
    'email'               => $email,
    'phone'               => $phone,
    'edition'             => $edition,
    'quantity'            => $quantity,
    'unit_price'          => $unitPrice,
    'amount'              => $total,
    'collection'          => $collection,
    'collection_details'  => $details,
    'message'             => $message,
    'paynow_method'       => $method,
    'poll_url'            => $response->pollUrl(),
    'status'              => 'pending',
    'created_at'          => date('c'),
    'updated_at'          => date('c'),
];

paynow_save_order($config['orders_dir'], $reference, $order);

$result = [
    'success'   => true,
    'reference' => $reference,
    'amount'    => $total,
    'poll_url'  => $response->pollUrl(),
];

if ($method === 'ecocash' || $method === 'onemoney') {
    $result['mode'] = 'mobile';
    $result['instructions'] = method_exists($response, 'instructions') ? $response->instructions() : '';
} else {
    $result['mode'] = 'redirect';
    $result['redirect_url'] = $response->redirectUrl();
}

paynow_json($result);
