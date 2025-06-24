<?php
require_once __DIR__ . '/../lib/wejv.php';

$creds_path = __DIR__ . '/../../db_creds.json';
$wejv = new Wejv($creds_path);

$all_filters = [
    "author",
    "prepareTimeGroup",
    "tag",
    "genre"
];

function get_filters(): string
{
    global $wejv;
    try {
        $filters = $wejv->fetchFilters();
        return json_encode($filters);
    } catch (Exception $e) {
        error_log("Error in get_filters(): " . $e->getMessage());
        return json_encode(['error' => 'An error occurred while fetching filters', 'success' => false]);
    }
}

function get_content($payload): false|string {
    global $wejv;
    $search = $payload['search'] ?? '';
    $filters = $payload['filters'] ?? [];
    $start = $payload['start'] ?? '';
    $count = $payload['count'] ?? '';
    return json_encode($wejv->fetchCards($filters, $search, $start, $count));
}

function toggle_favorite($payload): false|string {
    global $wejv;
    $userId = (int)$payload['userId'];
    $menuId = (int)$payload['menuId'];
    if ($userId <= 0 || $menuId <= 0) {
        return json_encode(['success' => false, 'message' => 'Invalid user or menu ID']);
    }
    $result = $wejv->toggleFavorite($userId, $menuId);
    return json_encode(['success' => true, 'isFavorite' => $result]);
}

function check_favorite($payload): false|string {
    global $wejv;
    $userId = (int)$payload['userId'];
    $menuId = (int)$payload['menuId'];
    if ($userId <= 0 || $menuId <= 0) {
        return json_encode(['success' => false, 'message' => 'Invalid user or menu ID']);
    }
    $result = $wejv->isFavorite($userId, $menuId);
    return json_encode(['success' => true, 'isFavorite' => $result]);
}

function add_recipe(): void
{
    global $wejv;
    $name = trim($_POST['name'] ?? '');
    $prepareTime = (int)($_POST['prepare_time'] ?? 0);
    $personNum = (int)($_POST['person_num'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $preparation = trim($_POST['preparation'] ?? '');
    $ingredients = $_POST['ingredients'] ?? '[]';
    $genres = json_decode($_POST['genres'] ?? '[]', true) ?: [];
    $tags = json_decode($_POST['tags'] ?? '[]', true) ?: [];
    $author_id = $_POST['author_id'];
    if ($author_id < 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid author']);
        exit;
    }
    $img = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $type = $_FILES['image']['type'];
        $img = $wejv->convertToWebP($_FILES['image']['tmp_name'], $type);
        if ($img === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format']);
            exit;
        }
    }
    $genre_ids = [];
    foreach ($genres as $g) {
        $genre_ids[] = $wejv->getOrCreateId('genre', $g);
    }
    $tag_ids = [];
    foreach ($tags as $t) {
        $tag_ids[] = $wejv->getOrCreateId('tag', $t);
    }
    try {
        $menuId = $wejv->insertMenu([
            'name' => $name,
            'prepareTime' => $prepareTime,
            'personNum' => $personNum,
            'author_id' => $author_id,
            'description' => $description,
            'preparation' => $preparation,
            'ingredients' => $ingredients,
            'img' => $img,
            'genres' => $genre_ids,
            'tags' => $tag_ids
        ]);
        echo json_encode(['success' => true, 'id' => $menuId]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function delete_recipe(): void
{
    global $wejv;
    $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $menuId = isset($payload['id']) ? (int)$payload['id'] : 0;
    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    $role = $payload['role'] ?? '';

    $result = $wejv->deleteRecipe($menuId, $userId, $role);
    echo json_encode($result);
    exit;
}

function update_recipe(): void
{
    global $wejv;
    $menuId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $prepareTime = (int)($_POST['prepare_time'] ?? 0);
    $personNum = (int)($_POST['person_num'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $preparation = trim($_POST['preparation'] ?? '');
    $ingredients = $_POST['ingredients'] ?? '';
    $genres = json_decode($_POST['genres'] ?? '[]', true) ?: [];
    $tags = json_decode($_POST['tags'] ?? '[]', true) ?: [];

    $recipeData = [
        'id' => $menuId,
        'name' => $name,
        'prepareTime' => $prepareTime,
        'personNum' => $personNum,
        'description' => $description,
        'preparation' => $preparation,
        'ingredients' => $ingredients,
        'genres' => $genres,
        'tags' => $tags
    ];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $type = $_FILES['image']['type'];
        $img = $wejv->convertToWebP($_FILES['image']['tmp_name'], $type);
        if ($img === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format']);
            exit;
        }
        $recipeData['img'] = $img;
    }

    $result = $wejv->updateRecipe($recipeData);
    echo json_encode($result);
    exit;
}

header('Content-Type: application/json');
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $actionVar = $_GET['action'] ?? ($data['action'] ?? '');
    if (in_array($actionVar, ['addRecipe', 'updateRecipe', 'deleteRecipe'], true)) {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }
    if (isset($data['action']) && $data['action'] === 'getContent') {
        echo (get_content($data));
    } elseif (isset($data['action']) && $data['action'] === 'toggleFavorite') {
        echo (toggle_favorite($data));
    } elseif (isset($data['action']) && $data['action'] === 'checkFavorite') {
        echo (check_favorite($data));
    } elseif (isset($_GET['action']) && $_GET['action'] === 'addRecipe') {
        add_recipe();
    } elseif (isset($_GET['action']) && $_GET['action'] === 'updateRecipe') {
        update_recipe();
    } elseif ((isset($data['action']) && $data['action'] === 'deleteRecipe') || (isset($_GET['action']) && $_GET['action'] === 'deleteRecipe')) {
        delete_recipe();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'getFilter') {
        echo(get_filters());
    } elseif (isset($_GET['action']) && $_GET['action'] === 'getRecipe' && isset($_GET['id'])) {
        $info = $wejv->fetchInfo((int)$_GET['id']);
        echo json_encode(['success' => true, 'data' => $info]);
    }
}
