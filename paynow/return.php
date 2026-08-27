<?php
/**
 * Return URL — customer lands here after Paynow checkout.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';

$reference = trim((string) ($_GET['ref'] ?? $_GET['reference'] ?? ''));
$order = $reference !== '' ? paynow_load_order($config['orders_dir'], $reference) : null;

$paid = false;
$statusLabel = 'pending';
$message = 'We could not find this order. If you completed payment, please contact us with your email and Paynow reference.';

if ($order !== null) {
    $statusLabel = (string) ($order['status'] ?? 'pending');

    try {
        if (!empty($order['poll_url'])) {
            $paynow = paynow_create_client($config, $reference);
            $status = $paynow->pollTransaction((string) $order['poll_url']);

            if ($status->paid()) {
                $paid = true;
                $statusLabel = 'paid';
                $order['status'] = 'paid';
                $order['paid_at'] = $order['paid_at'] ?? date('c');
                $order['updated_at'] = date('c');
                paynow_save_order($config['orders_dir'], $reference, $order);
            } elseif (method_exists($status, 'status')) {
                $statusLabel = (string) $status->status();
            }
        }
    } catch (Throwable $e) {
        // Fall back to stored status
        $paid = ($order['status'] ?? '') === 'paid';
    }

    if ($paid) {
        $message = 'Thank you, ' . htmlspecialchars((string) $order['name'], ENT_QUOTES, 'UTF-8')
            . '. Your payment for <em>Full Circle</em> was received. We will contact you about collection or delivery.';
    } else {
        $message = 'Your payment is still being confirmed (status: '
            . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8')
            . '). If you just paid, wait a moment and refresh this page, or contact us with reference '
            . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '.';
    }
}

$home = htmlspecialchars(paynow_base_url() . '/index.html#book', ENT_QUOTES, 'UTF-8');
$safeRef = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');
$icon = $paid ? '✓' : '!';
$title = $paid ? 'Payment successful' : 'Payment status';
$accent = $paid ? '#1F6B4A' : '#C9A84C';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?> — Full Circle Pre-Order</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root { --ink:#0F1B4D; --mist:#F7F4EF; --line:#E4DDD2; }
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; display: grid; place-items: center;
      font-family: 'DM Sans', sans-serif; background: var(--mist); color: var(--ink);
      padding: 24px;
    }
    .card {
      max-width: 480px; width: 100%; background: #fff; border: 1px solid var(--line);
      border-radius: 16px; padding: 40px 32px; text-align: center;
    }
    .icon {
      width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 20px;
      display: grid; place-items: center; font-size: 28px; font-weight: 700;
      background: <?= $accent ?>1A; color: <?= $accent ?>;
    }
    h1 { font-family: 'Fraunces', serif; font-size: 28px; margin: 0 0 12px; }
    p { line-height: 1.55; color: #4a5568; margin: 0 0 8px; }
    .ref { font-size: 13px; color: #718096; margin-top: 16px; }
    a.btn {
      display: inline-block; margin-top: 28px; padding: 12px 22px; border-radius: 999px;
      background: var(--ink); color: #fff; text-decoration: none; font-weight: 500;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon"><?= $icon ?></div>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= $message ?></p>
    <?php if ($reference !== ''): ?>
      <p class="ref">Reference: <?= $safeRef ?></p>
    <?php endif; ?>
    <a class="btn" href="<?= $home ?>">Back to website</a>
  </div>
</body>
</html>
