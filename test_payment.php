<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;
use Illuminate\Contracts\Console\Kernel;

$payment = Payment::factory()->paid()->create(['amount_inr' => 1000]);
echo 'Status: '.$payment->status->value.PHP_EOL;
echo 'Amount Paid: '.$payment->amount_paid_inr.PHP_EOL;
echo 'Is Paid: '.($payment->isPaid() ? 'true' : 'false').PHP_EOL;
