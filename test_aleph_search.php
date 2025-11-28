<?php
// Script de test pentru funcția cautaCarteInAleph
require_once 'aleph_api.php';

echo "<h1>Test căutare Aleph</h1>";

// Test 1: Barcode 59082-10
echo "<h2>Test 1: Barcode 59082-10</h2>";
$result1 = cautaCarteInAleph('59082-10', 'AUTO');
echo "<pre>";
echo "Success: " . ($result1['success'] ? 'true' : 'false') . "\n";
if ($result1['success']) {
    echo "Titlu: " . ($result1['data']['titlu'] ?? 'N/A') . "\n";
    echo "Autor: " . ($result1['data']['autor'] ?? 'N/A') . "\n";
    echo "Barcode: " . ($result1['data']['barcode'] ?? 'N/A') . "\n";
    echo "Cota: " . ($result1['data']['cota'] ?? 'N/A') . "\n";
} else {
    echo "Mesaj: " . ($result1['mesaj'] ?? 'N/A') . "\n";
}
echo "</pre>";

// Test 2: Barcode C184507
echo "<h2>Test 2: Barcode C184507</h2>";
$result2 = cautaCarteInAleph('C184507', 'AUTO');
echo "<pre>";
echo "Success: " . ($result2['success'] ? 'true' : 'false') . "\n";
if ($result2['success']) {
    echo "Titlu: " . ($result2['data']['titlu'] ?? 'N/A') . "\n";
    echo "Autor: " . ($result2['data']['autor'] ?? 'N/A') . "\n";
    echo "Barcode: " . ($result2['data']['barcode'] ?? 'N/A') . "\n";
    echo "Cota: " . ($result2['data']['cota'] ?? 'N/A') . "\n";
} else {
    echo "Mesaj: " . ($result2['mesaj'] ?? 'N/A') . "\n";
}
echo "</pre>";

// Test 3: Cota SL Irimia/1146
echo "<h2>Test 3: Cota SL Irimia/1146</h2>";
$result3 = cautaCarteInAleph('SL Irimia/1146', 'AUTO');
echo "<pre>";
echo "Success: " . ($result3['success'] ? 'true' : 'false') . "\n";
if ($result3['success']) {
    echo "Titlu: " . ($result3['data']['titlu'] ?? 'N/A') . "\n";
    echo "Autor: " . ($result3['data']['autor'] ?? 'N/A') . "\n";
    echo "Barcode: " . ($result3['data']['barcode'] ?? 'N/A') . "\n";
    echo "Cota: " . ($result3['data']['cota'] ?? 'N/A') . "\n";
} else {
    echo "Mesaj: " . ($result3['mesaj'] ?? 'N/A') . "\n";
}
echo "</pre>";
?>




