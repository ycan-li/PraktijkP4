<?php
require_once "../lib/wejv.php";

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Initialize Wejv class
try {
    $wejv = new Wejv('../db_creds.json');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// Check if user is authenticated
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Check if user exists and get author ID
$userInfo = $wejv->fetchUserInfo($user_id);
if (!$userInfo) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Get author ID for the user
$author_id = getAuthorIdForUser($wejv, $user_id);
if (!$author_id) {
    echo json_encode(['success' => false, 'message' => 'Author not found for user']);
    exit;
}

// Debug: Log the incoming data
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Validate required fields
$required_fields = ['name', 'prepare_time', 'person_num', 'description', 'preparation', 'ingredients'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Process custom genres and tags
$genres = isset($_POST['genres']) ? $_POST['genres'] : [];
$tags = isset($_POST['tags']) ? $_POST['tags'] : [];
$custom_genre_names = isset($_POST['custom_genre_names']) ? $_POST['custom_genre_names'] : [];
$custom_tag_names = isset($_POST['custom_tag_names']) ? $_POST['custom_tag_names'] : [];

// Process genres (including custom ones)
$processedGenres = [];
foreach ($genres as $i => $genreId) {
    if ($genreId === 'custom') {
        // Find the corresponding custom name
        if (isset($custom_genre_names) && !empty($custom_genre_names)) {
            $customName = array_shift($custom_genre_names);
            if ($customName) {
                try {
                    // Add new genre to database
                    $newGenreId = addGenre($wejv, $customName);
                    if ($newGenreId) {
                        $processedGenres[] = $newGenreId;
                    }
                } catch (Exception $e) {
                    error_log("Error adding custom genre: " . $e->getMessage());
                }
            }
        }
    } else {
        $processedGenres[] = $genreId;
    }
}

// Process tags (including custom ones)
$processedTags = [];
foreach ($tags as $i => $tagId) {
    if ($tagId === 'custom') {
        // Find the corresponding custom name
        if (isset($custom_tag_names) && !empty($custom_tag_names)) {
            $customName = array_shift($custom_tag_names);
            if ($customName) {
                try {
                    // Add new tag to database
                    $newTagId = addTag($wejv, $customName);
                    if ($newTagId) {
                        $processedTags[] = $newTagId;
                    }
                } catch (Exception $e) {
                    error_log("Error adding custom tag: " . $e->getMessage());
                }
            }
        }
    } else {
        $processedTags[] = $tagId;
    }
}

// Process image upload if provided
$img_data = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
    $img_tmp_name = $_FILES['image']['tmp_name'];
    $img_type = $_FILES['image']['type'];

    // Check if it's an image
    if (!in_array($img_type, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPG, PNG, and WebP are allowed.']);
        exit;
    }

    // Convert to WebP for consistency
    $img_data = convertToWebP($img_tmp_name, $img_type);
    if (!$img_data) {
        echo json_encode(['success' => false, 'message' => 'Failed to process image']);
        exit;
    }
}

// Prepare menu data
$menuData = [
    'name' => $_POST['name'],
    'prepare_time' => (int)$_POST['prepare_time'],
    'person_num' => (int)$_POST['person_num'],
    'author_id' => $author_id,
    'description' => $_POST['description'],
    'preparation' => $_POST['preparation'],
    'ingredients' => $_POST['ingredients'],
    'img' => $img_data,
    'genres' => $processedGenres,
    'tags' => $processedTags
];

// Insert menu into database
try {
    $menu_id = $wejv->insertMenu($menuData);
    echo json_encode(['success' => true, 'menu_id' => $menu_id, 'message' => 'Recipe added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to add recipe: ' . $e->getMessage()]);
}

// Helper function to get author ID for a user
function getAuthorIdForUser($wejv, $user_id) {
    try {
        // Use PDO connection directly from existing Wejv instance
        $stmt = $wejv->conn->prepare("SELECT author_id FROM user_author WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting author ID: " . $e->getMessage());
        return false;
    }
}

// Helper function to convert image to WebP format
function convertToWebP($img_path, $img_type) {
    try {
        // Create image resource based on type
        switch ($img_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($img_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($img_path);
                break;
            case 'image/webp':
                // If already WebP, just read the file
                return file_get_contents($img_path);
            default:
                return null;
        }

        if (!$image) {
            return null;
        }

        // Start output buffering to capture WebP image data
        ob_start();
        imagewebp($image, null, 80); // 80% quality
        $webp_data = ob_get_contents();
        ob_end_clean();

        // Free up memory
        imagedestroy($image);

        return $webp_data;
    } catch (Exception $e) {
        error_log("Image conversion error: " . $e->getMessage());
        return null;
    }
}

// Helper function to add a new genre
function addGenre($wejv, $name) {
    try {
        // Check if a genre with this name already exists
        $stmt = $wejv->conn->prepare("SELECT id FROM genre WHERE name = ?");
        $stmt->execute([$name]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            return $existingId;
        }

        // Insert the new genre into the database
        $stmt = $wejv->conn->prepare("INSERT INTO genre (name) VALUES (?)");
        $stmt->execute([$name]);
        return $wejv->conn->lastInsertId();
    } catch (Exception $e) {
        error_log("Error adding genre: " . $e->getMessage());
        throw $e;
    }
}

// Helper function to add a new tag
function addTag($wejv, $name) {
    try {
        // Check if a tag with this name already exists
        $stmt = $wejv->conn->prepare("SELECT id FROM tag WHERE name = ?");
        $stmt->execute([$name]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            return $existingId;
        }

        // Insert the new tag into the database
        $stmt = $wejv->conn->prepare("INSERT INTO tag (name) VALUES (?)");
        $stmt->execute([$name]);
        return $wejv->conn->lastInsertId();
    } catch (Exception $e) {
        error_log("Error adding tag: " . $e->getMessage());
        throw $e;
    }
}

