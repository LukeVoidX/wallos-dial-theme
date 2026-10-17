<?php
// Deterministic fictional data. Render the release's actual mail function for the after panel.
require __DIR__ . '/../../../../theme/includes/dial_mail_theme.php';

$items = [
    ['name' => 'Aster AI', 'formatted_price' => 'US$19.00', 'date' => '2026-10-20', 'days' => 3],
    ['name' => 'Northstar VPS', 'formatted_price' => 'US$8.00', 'date' => '2026-10-24', 'days' => 7],
];
$original = "The following subscriptions are up for renewal:\n"
    . "Aster AI for US$19.00 (In 3 days)\n"
    . "Northstar VPS for US$8.00 (In 7 days)\n";
$after = dial_mail_notifications('renewal', $items, '', false, 'https://demo.example.org', 'en');
file_put_contents(__DIR__ . '/mail-dial-render.html', $after);
file_put_contents(__DIR__ . '/mail-original-body.txt', $original);
echo "Fictional email fixtures rendered.\n";
