<?php
// ============================================================
//  ajax/poll-messages.php — returns new messages in a conversation
//  since a given message id, and marks them as seen. Used by
//  messages.php to fetch new messages without a full page reload.
// ============================================================
require_once '../connection.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!Auth::check()) {
    jsonResponse(false, 'Not logged in', null, 401);
}

$myId    = Auth::id();
$convId  = (int)($_GET['conv_id'] ?? 0);
$afterId = (int)($_GET['after_id'] ?? 0);

if ($convId <= 0) {
    jsonResponse(false, 'Invalid conversation');
}

// Make sure this user is actually part of the conversation
$conv = DB::selectOne(
    "SELECT * FROM conversations WHERE id=? AND (buyer_id=? OR seller_id=?)",
    [$convId, $myId, $myId]
);
if (!$conv) {
    jsonResponse(false, 'Conversation not found', null, 404);
}

// Any new messages since afterId?
$messages = DB::select(
    "SELECT id, sender_id, message, is_seen, created_at
     FROM messages
     WHERE conversation_id = ? AND id > ?
     ORDER BY id ASC",
    [$convId, $afterId]
);

// Mark incoming messages (not sent by me) as seen
DB::execute(
    "UPDATE messages SET is_seen=1, seen_at=NOW() WHERE conversation_id=? AND sender_id!=? AND is_seen=0",
    [$convId, $myId]
);

// Whether the other person has now seen MY messages (for double-tick updates)
$otherSeenMine = DB::exists(
    "SELECT 1 FROM messages WHERE conversation_id=? AND sender_id=? AND is_seen=1 LIMIT 1",
    [$convId, $myId]
);

$out = [];
foreach ($messages as $m) {
    $out[] = [
        'id'         => (int)$m['id'],
        'sender_id'  => (int)$m['sender_id'],
        'is_me'      => (int)$m['sender_id'] === $myId,
        'message'    => $m['message'],
        'message_html' => nl2br(e($m['message'])),
        'is_seen'    => (int)$m['is_seen'],
        'time'       => date('g:i A', strtotime($m['created_at'])),
        'day'        => date('Y-m-d', strtotime($m['created_at'])),
        'day_label'  => (function() use ($m) {
            $day   = date('Y-m-d', strtotime($m['created_at']));
            $today = date('Y-m-d');
            $yest  = date('Y-m-d', strtotime('-1 day'));
            if ($day === $today) return 'Today';
            if ($day === $yest)  return 'Yesterday';
            return date('D, j M Y', strtotime($m['created_at']));
        })(),
    ];
}

jsonResponse(true, '', [
    'messages'        => $out,
    'other_seen_mine' => (bool)$otherSeenMine,
]);
