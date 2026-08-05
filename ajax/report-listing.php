<?php
// ajax/report-listing.php — Submit a fraud report
require_once '../connection.php';
header('Content-Type: application/json');

if (!Auth::check()) {
    jsonResponse(false, 'Please sign in to report listings.', null, 401);
}

$carId  = (int)($_POST['car_id'] ?? 0);
$reason = cleanInput($_POST['reason'] ?? '');
if (!$carId || !$reason) jsonResponse(false, 'Invalid request.');

// Check if already reported by this user
if (DB::exists("SELECT 1 FROM reports WHERE reporter_id=? AND car_id=?", [Auth::id(), $carId])) {
    jsonResponse(false, 'You have already reported this listing.');
}

DB::execute(
    "INSERT INTO reports (reporter_id, car_id, reason, description) VALUES (?,?,?,?)",
    [Auth::id(), $carId, 'other', $reason]
);
jsonResponse(true, 'Report submitted. Our team will review it within 24 hours. Thank you.');
