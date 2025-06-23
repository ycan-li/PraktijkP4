<?php
require_once("../lib/wejv.php");

$creds_path = __DIR__ . '/../db_creds.json';
$all_filters = [
    "author",
    "prepareTimeGroup",
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

function check_favorite($creds, $payload): false|string {
    $wevj = new Wejv($creds);
    $userId = (int)$payload['userId'];
    $menuId = (int)$payload['menuId'];

    if ($userId <= 0 || $menuId <= 0) {
        return json_encode(['success' => false, 'message' => 'Invalid user or menu ID']);
    }

    $result = $wevj->isFavorite($userId, $menuId);
    return json_encode(['success' => true, 'isFavorite' => $result]);
}

function get_or_create_id(PDO $conn, string $table, string $name): int {
    $stmt = $conn->prepare("SELECT id FROM `$table` WHERE name = ?");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $stmt = $conn->prepare("INSERT INTO `$table` (name) VALUES (?)");
    $stmt->execute([$name]);
    return (int)$conn->lastInsertId();
}

function add_recipe($creds): void
{
    $wejv = new Wejv($creds);
    $conn = (new ReflectionClass($wejv))->getProperty('conn');
    $conn->setAccessible(true);
    $pdo = $conn->getValue($wejv);

    // Get POST fields
    $name = trim($_POST['name'] ?? '');
    $prepareTime = (int)($_POST['prepare_time'] ?? 0);
    $personNum = (int)($_POST['person_num'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $preparation = trim($_POST['preparation'] ?? '');
    $ingredients = $_POST['ingredients'] ?? '[]';
    $genres = json_decode($_POST['genres'] ?? '[]', true) ?: [];
    $tags = json_decode($_POST['tags'] ?? '[]', true) ?: [];
    $authorName = isset($_POST['author']) ? trim($_POST['author']) : 'Onbekend';

    // Handle image upload (optional)
    $img = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img = file_get_contents($_FILES['image']['tmp_name']);
    }

    // Insert or get author
    $author_id = get_or_create_id($pdo, 'author', $authorName);

    // Insert or get genres/tags
    $genre_ids = [];
    foreach ($genres as $g) {
        $genre_ids[] = get_or_create_id($pdo, 'genre', $g);
    }
    $tag_ids = [];
    foreach ($tags as $t) {
        $tag_ids[] = get_or_create_id($pdo, 'tag', $t);
    }

    // Insert menu/recipe
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

function delete_recipe($creds): void
{
    $wejv = new Wejv($creds);
    // Read JSON payload for delete parameters
    $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $menuId = isset($payload['id']) ? (int)$payload['id'] : 0;
    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    $role = $payload['role'] ?? '';
    if ($menuId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid recipe ID']);
        exit;
    }
    // Fetch menu info for author check
    $info = $wejv->fetchInfo($menuId);
    if (!$info) {
        echo json_encode(['success' => false, 'message' => 'Recipe not found']);
        exit;
    }
    // Only allow if admin or author
    $isAdmin = ($role === 'admin');
    $isAuthor = false;
    if ($userId && !$isAdmin) {
        // Compare author IDs
        $userAuthorId = $wejv->getAuthorId($userId);
        if ($userAuthorId !== false && isset($info['author_id'])) {
            $isAuthor = ((int)$info['author_id'] === $userAuthorId);
        }
    }
    if (!$isAdmin && !$isAuthor) {
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit;
    }
    // Delete menu and related links
    try {
        $pdo = (new ReflectionClass($wejv))->getProperty('conn');
        $pdo->setAccessible(true);
        $conn = $pdo->getValue($wejv);
        $conn->beginTransaction();
        $conn->prepare('DELETE FROM menu_genre WHERE menu_id = ?')->execute([$menuId]);
        $conn->prepare('DELETE FROM menu_tag WHERE menu_id = ?')->execute([$menuId]);
        $conn->prepare('DELETE FROM fav WHERE menu_id = ?')->execute([$menuId]);
        $conn->prepare('DELETE FROM menu WHERE id = ?')->execute([$menuId]);
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit;
}

function update_recipe($creds): void
{
    $wejv = new Wejv($creds);
    $connProp = new ReflectionClass($wejv);
    $prop = $connProp->getProperty('conn');
    $prop->setAccessible(true);
    $pdo = $prop->getValue($wejv);
    // Read POST fields
    $menuId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $prepareTime = (int)($_POST['prepare_time'] ?? 0);
    $personNum = (int)($_POST['person_num'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $preparation = trim($_POST['preparation'] ?? '');
    $ingredients = $_POST['ingredients'] ?? '';
    $genres = json_decode($_POST['genres'] ?? '[]', true) ?: [];
    $tags = json_decode($_POST['tags'] ?? '[]', true) ?: [];
    // Handle image upload
    $img = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img = file_get_contents($_FILES['image']['tmp_name']);
    }
    try {
        $pdo->beginTransaction();
        // Update menu table
        $sql = "UPDATE menu SET name = ?, prepareTime = ?, personNum = ?, description = ?, preparation = ?, ingredients = ?";
        $params = [$name, $prepareTime, $personNum, $description, $preparation, $ingredients];
        if ($img !== null) {
            $sql .= ", img = ?";
            $params[] = $img;
        }
        $sql .= " WHERE id = ?";
        $params[] = $menuId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        // Update genres
        $pdo->prepare('DELETE FROM menu_genre WHERE menu_id = ?')->execute([$menuId]);
        foreach ($genres as $g) {
            $genreId = is_numeric($g) ? (int)$g : get_or_create_id($pdo, 'genre', $g);
            $pdo->prepare('INSERT INTO menu_genre (menu_id, genre_id) VALUES (?, ?)')->execute([$menuId, $genreId]);
        }
        // Update tags
        $pdo->prepare('DELETE FROM menu_tag WHERE menu_id = ?')->execute([$menuId]);
        foreach ($tags as $t) {
            $tagId = is_numeric($t) ? (int)$t : get_or_create_id($pdo, 'tag', $t);
            $pdo->prepare('INSERT INTO menu_tag (menu_id, tag_id) VALUES (?, ?)')->execute([$menuId, $tagId]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $menuId]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
    }
    exit;
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['action'] === 'getFilter') {
        echo(get_filters($creds_path, $all_filters));
    } elseif ($_GET['action'] === 'getRecipe' && isset($_GET['id'])) {
        $wejv = new Wejv($creds_path);
        $info = $wejv->fetchInfo((int)$_GET['id']);
        echo json_encode(['success' => true, 'data' => $info]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['action']) && $data['action'] === 'getContent') {
        echo (get_content($creds_path, $data));
    } elseif (isset($data['action']) && $data['action'] === 'toggleFavorite') {
        echo (toggle_favorite($creds_path, $data));
    } elseif (isset($data['action']) && $data['action'] === 'checkFavorite') {
        echo (check_favorite($creds_path, $data));
    } elseif (isset($_GET['action']) && $_GET['action'] === 'addRecipe') {
        add_recipe($creds_path);
    } elseif (isset($_GET['action']) && $_GET['action'] === 'updateRecipe') {
        update_recipe($creds_path);
    } elseif ((isset($data['action']) && $data['action'] === 'deleteRecipe') || (isset($_GET['action']) && $_GET['action'] === 'deleteRecipe')) {
        delete_recipe($creds_path);
    }
}
