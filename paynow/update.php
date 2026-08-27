<?php
/**
 * Paynow result URL — server-to-server status updates.
 * Configure this URL in your Paynow integration if required;
 * the SDK also sends it with each payment.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';

$reference = (string) ($_POST['reference'] ?? $_GET['reference'] ?? '');
$pollUrl   = (string) ($_POST['pollurl'] ?? $_POST['pollUrl'] ?? '');

if ($reference === '' && isset($_POST['Reference'])) {
    $reference = (string) $_POST['Reference'];
}
if ($pollUrl === '' && isset($_POST['PollUrl'])) {
    $pollUrl = (string) $_POST['PollUrl'];
}

$order = $reference !== '' ? paynow_load_order($config['orders_dir'], $reference) : null;

if ($order === null && $pollUrl === '') {
    http_response_code(400);
    echo 'Missing reference';
    exit;
}

if ($order !== null && !empty($order['poll_url'])) {
    $pollUrl = (string) $order['poll_url'];
}

try {
    $paynow = paynow_create_client($config, $reference !== '' ? $reference : null);
    $status = $paynow->pollTransaction($pollUrl);

    if ($order !== null) {
        $order['paynow_status'] = method_exists($status, 'status') ? (string) $status->status() : null;
        $order['updated_at'] = date('c');

        if ($status->paid()) {
            $order['status'] = 'paid';
            $order['paid_at'] = date('c');
        } else {
            $order['status'] = strtolower((string) $status->status());
        }

        paynow_save_order($config['orders_dir'], (string) $order['reference'], $order);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Poll failed';
    exit;
}

http_response_code(200);
echo 'OK';
