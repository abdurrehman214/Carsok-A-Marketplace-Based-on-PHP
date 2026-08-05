<?php
// ajax/track-contact.php — Track call/whatsapp contact clicks
// FIXED: replaced match() with if/else for PHP 7.x compatibility
require_once '../connection.php';
header('Content-Type: application/json');

$carId = (int)($_POST['car_id'] ?? 0);
$type  = cleanInput($_POST['type'] ?? '');
if (!$carId) { jsonResponse(false, 'Invalid'); }

// FIXED: was match() — now if/else (works on PHP 7.4+)
if ($type === 'whatsapp') {
    $col = 'whatsapp_clicks';
} else {
    $col = 'contact_clicks';
}

DB::execute("UPDATE cars SET $col = $col + 1 WHERE id = ?", [$carId]);
jsonResponse(true, 'Tracked');