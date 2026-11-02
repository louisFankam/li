<?php

use App\Models\Pharmacy;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $pharmacyId, $clientRequestId, $startAt] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $user = User::query()->findOrFail((int) $userId);
    $pharmacy = Pharmacy::query()->findOrFail((int) $pharmacyId);

    app(SaleService::class)->create($user, $pharmacy, [
        'client_request_id' => $clientRequestId,
        'items' => [['product_id' => (int) $argv[5], 'quantity' => 1]],
        'payments' => [['method' => 'especes', 'amount' => 5000]],
    ]);

    echo 'OK';
    exit(0);
} catch (Throwable $e) {
    echo get_class($e).': '.$e->getMessage();
    exit(1);
}
