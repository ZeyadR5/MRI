<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
echo 'Status: ' . $response->getStatusCode() . PHP_EOL;
echo 'Content length: ' . strlen($response->getContent()) . PHP_EOL;
if ($response->getStatusCode() !== 200) {
    echo substr($response->getContent(), 0, 3000) . PHP_EOL;
} else {
    // Check key elements
    $content = $response->getContent();
    echo (str_contains($content, 'نظام حجز الرنين') ? '✅' : '❌') . ' Arabic title found' . PHP_EOL;
    echo (str_contains($content, 'schedule-container') ? '✅' : '❌') . ' Schedule container found' . PHP_EOL;
    echo (str_contains($content, 'slot-card') ? '✅' : '❌') . ' Slot cards found' . PHP_EOL;
    echo (str_contains($content, 'booking-modal') ? '✅' : '❌') . ' Booking modal found' . PHP_EOL;
    echo (str_contains($content, 'فترة الصبح') ? '✅' : '❌') . ' Morning section found' . PHP_EOL;
    echo (str_contains($content, 'فترة الضهر') ? '✅' : '❌') . ' Afternoon section found' . PHP_EOL;
    echo (str_contains($content, 'فترة الليل') ? '✅' : '❌') . ' Night section found' . PHP_EOL;
    
    // Count slots
    preg_match_all('/slot-card/', $content, $matches);
    echo 'Total slot cards: ' . count($matches[0]) . PHP_EOL;
    
    preg_match_all('/slot-empty/', $content, $matches);
    echo 'Empty slots: ' . count($matches[0]) . PHP_EOL;
}
