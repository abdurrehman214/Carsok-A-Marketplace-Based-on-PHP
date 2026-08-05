<?php
// ajax/delete-image.php — Remove a single car image (edit-listing mode)
require_once '../connection.php';

header('Content-Type: application/json');

// Auth check
if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

// CSRF check
if (!CSRF::verify()) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$imageId = (int)($_POST['image_id'] ?? 0);
if ($imageId < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid image ID.']);
    exit;
}

// Fetch the image — verify ownership via car → user_id
$img = DB::selectOne(
    "SELECT ci.id, ci.image_path, ci.thumb_path, ci.car_id
     FROM car_images ci
     JOIN cars c ON c.id = ci.car_id
     WHERE ci.id = ? AND c.user_id = ?
     LIMIT 1",
    [$imageId, Auth::id()]
);

if (!$img) {
    echo json_encode(['success' => false, 'message' => 'Image not found or access denied.']);
    exit;
}

// Must keep at least 1 photo on the listing
$remaining = (int) DB::value(
    "SELECT COUNT(*) FROM car_images WHERE car_id = ?",
    [$img['car_id']]
);
if ($remaining <= 1) {
    echo json_encode(['success' => false, 'message' => 'You must keep at least one photo on a listing.']);
    exit;
}

// Delete DB record
$deleted = DB::execute("DELETE FROM car_images WHERE id = ?", [$imageId]);

if ($deleted) {
    // Delete files from disk (best-effort — don't fail if missing)
    $mainFile  = UPLOAD_PATH . basename($img['image_path']);
    $thumbFile = UPLOAD_PATH . ltrim($img['thumb_path'], '/');
    if (file_exists($mainFile))  @unlink($mainFile);
    if (file_exists($thumbFile)) @unlink($thumbFile);

    // If the deleted image was the featured one, promote the next image
    // (only if it was is_featured=1)
    $wasFeatured = DB::value(
        "SELECT 1 FROM car_images WHERE id = ? AND is_featured = 1",
        [$imageId]
    );
    // Already deleted — check another way: just ensure there's a featured image
    $hasFeatured = DB::value(
        "SELECT 1 FROM car_images WHERE car_id = ? AND is_featured = 1",
        [$img['car_id']]
    );
    if (!$hasFeatured) {
        // Promote the first remaining image
        $firstId = DB::value(
            "SELECT id FROM car_images WHERE car_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1",
            [$img['car_id']]
        );
        if ($firstId) {
            DB::execute("UPDATE car_images SET is_featured = 1 WHERE id = ?", [(int)$firstId]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Photo removed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete image. Please try again.']);
}
