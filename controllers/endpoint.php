<?php
require_once("../lib/wejv.php");

$creds_path = __DIR__ . '/../db_creds.json';
$all_filters = [
    "author",
    "prepare_time_group",
    "tag",
    "genre"
];

function get_filters($creds): false|string
{
    $wevj = new Wejv($creds);
    return json_encode($wevj->fetchFilters());
}

function get_content($creds, $payload): false|string {
    $wevj = new Wejv($creds);
    $search = $payload['search'] ?? '';
    $filters = $payload['filters'] ?? [];
    $start = $payload['start'] ?? '';
    $count = $payload['count'] ?? '';
    return json_encode($wevj->fetchCards($filters, $search, $start, $count));
}

function toggle_favorite($creds, $payload): false|string {
    $wevj = new Wejv($creds);
    $userId = (int)$payload['userId'];
    $menuId = (int)$payload['menuId'];

    if ($userId <= 0 || $menuId <= 0) {
        return json_encode(['success' => false, 'message' => 'Invalid user or menu ID']);
    }

    $result = $wevj->toggleFavorite($userId, $menuId);
    return json_encode(['success' => true, 'isFavorite' => $result]);
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET["action"] == "getFilter") {
        echo(get_filters($creds_path, $all_filters));
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['action']) && $data['action'] === 'getContent') {
        echo (get_content($creds_path, $data));
    } elseif (isset($data['action']) && $data['action'] === 'toggleFavorite') {
        echo (toggle_favorite($creds_path, $data));
    }
}
