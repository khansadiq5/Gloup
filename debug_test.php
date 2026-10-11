<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;
use Illuminate\Contracts\Console\Kernel;

// Test 1: Create payment with paid() factory
echo "=== Test 1: Factory paid() ===\n";
$p = Payment::factory()->paid()->create(['amount_inr' => 1000]);
echo 'Status: '.$p->status->value."\n";
echo 'Amount Paid: '.$p->amount_paid_inr."\n";
echo 'Is Paid: '.($p->isPaid() ? 'true' : 'false')."\n\n";

// Test 2: Create payment with explicit status 'paid' but no factory state
echo "=== Test 2: Factory create() with status='paid' ===\n";
$p2 = Payment::factory()->create(['amount_inr' => 1000, 'status' => 'paid']);
echo 'Status: '.$p2->status->value."\n";
echo 'Amount Paid: '.$p2->amount_paid_inr."\n";
echo 'Is Paid: '.($p2->isPaid() ? 'true' : 'false')."\n\n";

// Test 3: Factory paid() with explicit amount_paid_inr
echo "=== Test 3: Factory paid() with amount_paid_inr ===\n";
$p3 = Payment::factory()->paid()->create(['amount_inr' => 1000, 'amount_paid_inr' => 1000]);
echo 'Status: '.$p3->status->value."\n";
echo 'Amount Paid: '.$p3->amount_paid_inr."\n";
echo 'Is Paid: '.($p3->isPaid() ? 'true' : 'false')."\n\n";

// Test 4: Factory create() with both
echo "=== Test 4: Factory create() with status='paid' and amount_paid_inr ===\n";
$p4 = Payment::factory()->create(['amount_inr' => 1000, 'status' => 'paid', 'amount_paid_inr' => 1000]);
echo 'Status: '.$p4->status->value."\n";
echo 'Amount Paid: '.$p4->amount_paid_inr."\n";
echo 'Is Paid: '.($p4->isPaid() ? 'true' : 'false')."\n\n";
