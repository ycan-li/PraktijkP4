<?php
# Init database

require_once("utils.php");

enum Sort: int
{
    case CTIME_ASC = 1;
    case CTIME_DESC = 2;
    case NAME_ASC = 3;
    case NAME_DESC = 4;
}

class Wejv
{
    protected PDO $conn;
    public string $dbname;
    private string $join_sql;

    public function __construct(array|string $creds, string $dbname = "wejv")
    {
        if (is_string($creds)) {
            $creds = json_decode(file_get_contents($creds), true);
        }
        $this->dbname = $dbname == "" ? $creds['dbname'] : $dbname;
        $dsn = "mysql:host={$creds['host']};dbname={$creds['dbname']};charset=utf8mb4";
        $this->conn = new PDO($dsn, $creds['user'], $creds['password']);
        $this->join_sql = "
            LEFT JOIN author a ON m.author_id = a.id
            LEFT JOIN menu_genre mg ON m.id = mg.menu_id
            LEFT JOIN genre g ON mg.genre_id = g.id
            LEFT JOIN menu_tag mt ON m.id = mt.menu_id
            LEFT JOIN tag t ON mt.tag_id = t.id
        ";
    }

    /**
     * Fetches available filter options for the filter system on the discover page.
     *
     * @return array<string, array<int, array{id: int, name: string}>> Associative array where each key is a filter name (e.g., "genre")
     *     and the value is an array of options, each option being an associative array with keys 'id' and 'name'.
     *     Example: [
     *         "genre" => [ [ "id" => 1, "name" => "Hoofdgerecht" ], [ "id" => 2, "name" => "Bijgerecht" ] ],
     *         "author" => [ ... ],
     *         ...
     *     ]
     */
    public function fetchFilters(): array
    {
        $filters = [
            "genre",
            "author",
            "tag",
            "prepareTimeGroup",
        ];

        $res = [];

        foreach ($filters as $fltr) {
            $stmt = $this->conn->prepare("SELECT id, name FROM `$fltr`");
            $stmt->execute();
            $res[$fltr] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $res;
    }

    /**
     * Fetches card data for the discover and homepage.
     *
     * @param array<string, array> $filters Associative array where each key is a string and each value is an array.
     * @param int $start Offset for the first card to fetch (for pagination).
     * @param int $count Number of cards to fetch.
     * @param Sort|int $sort Sorting order, as a Sort enum or its integer value.
     * @param string $search Optional search term to filter results by name
     * @return array{
     *   total: int,
     *   data: array<array{
     *     id: int,
     *     name: string,
     *     author: string|null,
     *     genre: array<int, string>,
     *     tag: array<int, string>,
     *     prepareTime: int,
     *     img: string|null
     *   }>
     * } Returns an array with total count and data array of cards. Each card contains id, name, author, genre, tag, prepareTime, and img (base64 or null).
     */
    public function fetchCards(array $filters = [], string $search = "", int $start = 0, int $count = 24, Sort|int $sort = Sort::CTIME_DESC): array
    {
        $sort_map = [
            Sort::CTIME_ASC->value => "ctime ASC",
            Sort::CTIME_DESC->value => "ctime DESC",
            Sort::NAME_ASC->value => "name ASC",
            Sort::NAME_DESC->value => "name DESC",
        ];
        $orderBy = $sort_map[$sort instanceof Sort ? $sort->value : $sort] ?? "ctime DESC";

        // Extract and handle fav filter
        $favFilter = false;
        if (isset($filters['fav']) && $filters['fav']) {
            $favFilter = true;
            $favUserId = (int)$filters['fav'];
            unset($filters['fav']); // Remove from regular filters
        }

        $filters = array_filter($filters, fn($v) => !empty($v));
        $match_exprs = [];
        $where_exprs = [];
        if ($filters) {
            foreach ($filters as $name => $vals) {
                $prefix = match ($name) {
                    "genre" => "mg",
                    "tag" => "mt",
                    default => "m"
                };
                $match_exprs[] = "IF({$prefix}.{$name}_id IN (" . implode(',', $vals) . "), 1, 0)";
                $where_exprs[] = "{$prefix}.{$name}_id IN (" . implode(',', $vals) . ")";
            }
        }
        $match_count_sql = $match_exprs ? implode(' + ', $match_exprs) : '0';
        $where_sql = $where_exprs ? "WHERE (" . implode(' OR ', $where_exprs) . ")" : '';

        // Add search functionality
        if (!empty($search)) {
            $search = $this->conn->quote('%' . $search . '%');
            if (empty($where_sql)) {
                $where_sql = "WHERE (m.name LIKE $search OR m.description LIKE $search OR a.name LIKE $search)";
            } else {
                $where_sql .= " AND (m.name LIKE $search OR m.description LIKE $search OR a.name LIKE $search)";
            }
        }

        // Build join SQL with favorites if needed
        $join_sql = $this->join_sql;

        // For showing favorite status of each item
        if ($favFilter) {
            $join_sql .= " LEFT JOIN fav f ON m.id = f.menu_id";
            //            $join_sql .= " LEFT JOIN fav f ON m.id = f.menu_id AND f.user_id = :favUserId";
        }

        // For filtering by favorites
        if ($favFilter) {
            if (empty($where_sql)) {
                $where_sql = "WHERE (f.user_id = :favUserId)";
            } else {
                $where_sql .= " AND (f.user_id = :favUserId)";
            }
        }

        $sql = "
        SELECT m.id, m.name, m.prepareTime, m.img, m.author_id,
               a.name AS author,
               " . ($favFilter ? "IF(f.user_id IS NOT NULL, 1, 0) AS is_favorite," : "") . "
            GROUP_CONCAT(DISTINCT g.name) AS genres,
            GROUP_CONCAT(DISTINCT t.name) AS tags,
            ($match_count_sql) AS match_count
        FROM menu m
        $join_sql
        $where_sql
        GROUP BY m.id
        ORDER BY match_count DESC, $orderBy
        LIMIT :count
        OFFSET :start
    ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':count', $count, PDO::PARAM_INT);
        if ($favFilter) {
            $stmt->bindValue(':favUserId', $favUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count query with same filters
        $countSql = "
        SELECT COUNT(DISTINCT m.id)
        FROM menu m
        $join_sql
        $where_sql
    ";
        $stmt = $this->conn->prepare($countSql);
        if ($favFilter) {
            $stmt->bindValue(':favUserId', $favUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_COLUMN);

        $this->trimRow($rows);
        return [
            "total" => $total,
            "data" => $rows
        ];
    }

    public function fetchInfo(int $id): array
    {
        $sql = "
            SELECT m.author_id AS author_id, m.name, m.prepareTime, m.img, m.personNum, m.ingredients, m.description, m.preparation, m.ctime,
                   a.name AS author,
            GROUP_CONCAT(DISTINCT g.name) AS genres,
            GROUP_CONCAT(DISTINCT t.name) AS tags
            FROM menu m
            $this->join_sql
            WHERE m.id = :id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->trimRow($rows);

        return $rows[0];
    }

    //    public function register(string $username, string $email, string $password, string $first_name, string $last_name): bool
    //    {
    //        $password_hash = password_hash($password, PASSWORD_BCRYPT);
    //
    //        $stmt = $this->conn->prepare("INSERT INTO user (username, email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
    //        $stmt->execute([$username, $email, $password_hash, $first_name, $last_name]);
    //        $stmt = $this->conn->prepare("INSERT INTO author (name) (?)");
    //        $stmt->execute(["$first_name  $last_name"]);
    //        $stmt = $this->conn->prepare("INSERT INTO user_author (user_id, author_id) SELECT FROM user u")
    //    }

    public function register(string $username, string $email, string $password, string $first_name, string $last_name): bool
    {
        try {
            $this->conn->beginTransaction();

            // Insert user and get ID
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->conn->prepare("INSERT INTO user (name, email, passwordHash, firstName, lastName) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password_hash, $first_name, $last_name]);
            $userId = $this->conn->lastInsertId();

            // Insert author and get ID
            $stmt = $this->conn->prepare("INSERT INTO author (name) VALUES (?)");
            $stmt->execute(["$first_name $last_name"]);
            $authorId = $this->conn->lastInsertId();

            // Link user and author in the junction table
            $stmt = $this->conn->prepare("INSERT INTO user_author (user_id, author_id) VALUES (?, ?)");
            $stmt->execute([$userId, $authorId]);

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Registration failed: " . $e->getMessage());
            return false;
        }
    }

    public function login(string $name, string $password): int|bool
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, passwordHash FROM user WHERE name = ? OR email = ?
        ");

        $stmt->execute([$name, $name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['passwordHash'])) {
            $stmt = $this->conn->prepare("UPDATE user SET lastLogin = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            return $user['id'];
        }

        return false;
    }

    public function fetchUserInfo(int $id): array|false
    {
        $stmt = $this->conn->prepare("
            SELECT id, name, email, firstName, lastName, role FROM user WHERE id = ?
        ");
        $stmt->execute([$id]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($info) {
            return $info;
        }
        return false;
    }

    /**
     * Toggle favorite status for a menu item
     *
     * @param int $userId The user ID
     * @param int $menuId The menu info ID
     * @return bool True if added as favorite, false if removed
     */
    public function toggleFavorite(int $userId, int $menuId): bool
    {
        // Check if already favorited
        $stmt = $this->conn->prepare("SELECT 1 FROM fav WHERE user_id = ? AND menu_id = ?");
        $stmt->execute([$userId, $menuId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // Remove favorite
            $stmt = $this->conn->prepare("DELETE FROM fav WHERE user_id = ? AND menu_id = ?");
            $stmt->execute([$userId, $menuId]);
            return false;
        } else {
            // Add favorite
            $stmt = $this->conn->prepare("INSERT INTO fav (user_id, menu_id) VALUES (?, ?)");
            $stmt->execute([$userId, $menuId]);
            return true;
        }
    }

    /**
     * Check if a menu item is favorited by user
     *
     * @param int $userId The user ID
     * @param int $menuId The menu info ID
     * @return bool True if favorited
     */
    public function isFavorite(int $userId, int $menuId): bool
    {
        $stmt = $this->conn->prepare("SELECT 1 FROM fav WHERE user_id = ? AND menu_id = ?");
        $stmt->execute([$userId, $menuId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Get all favorites for a user
     *
     * @param int $userId The user ID
     * @return array Array of menu IDs
     */
    public function getUserFavorites(int $userId): array
    {
        $stmt = $this->conn->prepare("SELECT menu_id FROM fav WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Insert a new menu item into the database
     *
     * @param array $menuData Array containing menu data (name, prepareTime, person_num, author_id, description, preparation, ingredients, img, genres, tags)
     * @return int The ID of the newly created menu item
     * @throws Exception If the insertion fails
     */
    public function insertMenu(array $menuData): int
    {
        try {
            $this->conn->beginTransaction();

            // Insert into menu table
            $stmt = $this->conn->prepare("
                INSERT INTO menu (name, prepareTime, personNum, author_id, description, preparation, ingredients, img)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $menuData['name'],
                $menuData['prepareTime'],
                $menuData['personNum'],
                $menuData['author_id'],
                $menuData['description'],
                $menuData['preparation'],
                $menuData['ingredients'],
                $menuData['img']
            ]);

            $menuId = $this->conn->lastInsertId();

            // Insert genres if provided
            if (!empty($menuData['genres'])) {
                $genreValues = [];
                $genrePlaceholders = [];

                foreach ($menuData['genres'] as $i => $genreId) {
                    $genrePlaceholders[] = "(?, ?)";
                    $genreValues[] = $menuId;
                    $genreValues[] = $genreId;
                }

                $stmt = $this->conn->prepare("
                    INSERT INTO menu_genre (menu_id, genre_id)
                    VALUES " . implode(',', $genrePlaceholders)
                );

                $stmt->execute($genreValues);
            }

            // Insert tags if provided
            if (!empty($menuData['tags'])) {
                $tagValues = [];
                $tagPlaceholders = [];

                foreach ($menuData['tags'] as $i => $tagId) {
                    $tagPlaceholders[] = "(?, ?)";
                    $tagValues[] = $menuId;
                    $tagValues[] = $tagId;
                }

                $stmt = $this->conn->prepare("
                    INSERT INTO menu_tag (menu_id, tag_id)
                    VALUES " . implode(',', $tagPlaceholders)
                );

                $stmt->execute($tagValues);
            }

            $this->conn->commit();
            return $menuId;

        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new Exception("Failed to insert menu: " . $e->getMessage());
        }
    }

    /**
     * @param array $rows
     */
    private function trimRow(array &$rows): void
    {
        foreach ($rows as &$row) {
            $row['genre'] = $row['genres'] ? explode(',', $row['genres']) : [];
            $row['tag'] = $row['tags'] ? explode(',', $row['tags']) : [];
            unset($row['genres'], $row['tags'], $row['match_count']);
            if (!is_null($row['img'])) {
                $row['img'] = base64_encode($row['img']);
            }
        }
    }

    /**
     * Retrieves the author_id associated with a given user.
     * @param int $userId
     * @return int|false Author ID or false if not found.
     */
    public function getAuthorId(int $userId): int|false
    {
        $stmt = $this->conn->prepare("SELECT author_id FROM user_author WHERE user_id = ?");
        $stmt->execute([$userId]);
        $authorId = $stmt->fetchColumn();
        return $authorId !== false ? (int)$authorId : false;
    }

    public function getAuthorName(int $authorId): ?string
    {
        $stmt = $this->conn->prepare("SELECT name FROM author WHERE id = ?");
        $stmt->execute([$authorId]);
        $name = $stmt->fetchColumn();
        return $name !== false ? $name : null;
    }

    /**
     * Convert an image to WebP format
     *
     * @param string $img_path Path to the image file
     * @param string $img_type MIME type of the image
     * @return string|null WebP image data as string or null if conversion failed
     */
    public function convertToWebP(string $img_path, string $img_type): ?string
    {
        switch ($img_type) {
            case 'image/jpeg': case 'image/jpg':
                $image = imagecreatefromjpeg($img_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($img_path);
                break;
            case 'image/webp':
                return file_get_contents($img_path);
            default:
                return null;
        }
        if (!$image) return null;
        ob_start();
        imagewebp($image, null, 80);
        $webp = ob_get_clean();
        imagedestroy($image);
        return $webp;
    }

    /**
     * Get an existing ID for a name in a table, or create a new record if it doesn't exist
     *
     * @param string $table Table name
     * @param string $name Name to look up
     * @return int The ID of the existing or newly created record
     */
    public function getOrCreateId(string $table, string $name): int
    {
        $stmt = $this->conn->prepare("SELECT id FROM `$table` WHERE name = ?");
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $stmt = $this->conn->prepare("INSERT INTO `$table` (name) VALUES (?)");
        $stmt->execute([$name]);
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Delete a recipe and all its related data
     *
     * @param int $menuId The recipe ID
     * @param int $userId The user ID attempting the deletion
     * @param string $role User role ('admin' for admin users)
     * @return array Success status and message
     */
    public function deleteRecipe(int $menuId, int $userId, string $role = ''): array
    {
        if ($menuId <= 0) {
            return ['success' => false, 'message' => 'Invalid recipe ID'];
        }

        $info = $this->fetchInfo($menuId);
        if (!$info) {
            return ['success' => false, 'message' => 'Recipe not found'];
        }

        $isAdmin = ($role === 'admin');
        $isAuthor = false;

        if ($userId && !$isAdmin) {
            $userAuthorId = $this->getAuthorId($userId);
            if ($userAuthorId !== false && isset($info['author_id'])) {
                $isAuthor = ((int)$info['author_id'] === $userAuthorId);
            }
        }

        if (!$isAdmin && !$isAuthor) {
            return ['success' => false, 'message' => 'Not authorized'];
        }

        try {
            $this->conn->beginTransaction();
            $this->conn->prepare('DELETE FROM menu_genre WHERE menu_id = ?')->execute([$menuId]);
            $this->conn->prepare('DELETE FROM menu_tag WHERE menu_id = ?')->execute([$menuId]);
            $this->conn->prepare('DELETE FROM fav WHERE menu_id = ?')->execute([$menuId]);
            $this->conn->prepare('DELETE FROM menu WHERE id = ?')->execute([$menuId]);
            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing recipe with new data
     *
     * @param array $recipeData Recipe data including id, name, prepareTime, personNum, description, preparation, ingredients, genres, tags, and optional img
     * @return array Success status, message, and recipe ID
     */
    public function updateRecipe(array $recipeData): array
    {
        $menuId = $recipeData['id'] ?? 0;
        if ($menuId <= 0) {
            return ['success' => false, 'message' => 'Invalid recipe ID'];
        }

        try {
            // First, get existing recipe data
            $existingRecipe = $this->fetchInfo($menuId);
            if (!$existingRecipe) {
                return ['success' => false, 'message' => 'Recipe not found'];
            }

            // Get existing genres and tags from the recipe
            $existingGenres = $existingRecipe['genre'] ?? [];
            $existingTags = $existingRecipe['tag'] ?? [];

            $this->conn->beginTransaction();

            // Update basic menu information
            $sql = "UPDATE menu SET name = ?, prepareTime = ?, personNum = ?, description = ?, preparation = ?, ingredients = ?";
            $params = [
                $recipeData['name'],
                $recipeData['prepareTime'],
                $recipeData['personNum'],
                $recipeData['description'],
                $recipeData['preparation'],
                $recipeData['ingredients']
            ];

            if (isset($recipeData['img']) && $recipeData['img'] !== null) {
                $sql .= ", img = ?";
                $params[] = $recipeData['img'];
            }

            $sql .= " WHERE id = ?";
            $params[] = $menuId;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            // Process genres
            $genreNames = $recipeData['genres'];

            // Add any new genres that don't exist yet and establish relationship
            foreach ($genreNames as $genreName)                {
                $genreId = $this->getOrCreateId('genre', $genreName);

                // Check if this relationship already exists
                $stmt = $this->conn->prepare('SELECT 1 FROM menu_genre WHERE menu_id = ? AND genre_id = ?');
                $stmt->execute([$menuId, $genreId]);
                if (!$stmt->fetchColumn()) {
                    $this->conn->prepare('INSERT INTO menu_genre (menu_id, genre_id) VALUES (?, ?)')->execute([$menuId, $genreId]);
                }
            }

            // Remove genres that are no longer associated (directly using names)
            $genresToRemove = array_diff($existingGenres, $genreNames);
            if (!empty($genresToRemove)) {
                $placeholders = implode(',', array_fill(0, count($genresToRemove), '?'));
                $stmt = $this->conn->prepare("
                    DELETE mg FROM menu_genre mg 
                    JOIN genre g ON mg.genre_id = g.id 
                    WHERE mg.menu_id = ? AND g.name IN ($placeholders)
                ");
                $params = array_merge([$menuId], $genresToRemove);
                $stmt->execute($params);
            }

            // Process tags
            $tagNames = $recipeData['tags'];

            // Add any new tags that don't exist yet and establish relationship
            foreach ($tagNames as $tagName) {
                $tagId = $this->getOrCreateId('tag', $tagName);

                // Check if this relationship already exists
                $stmt = $this->conn->prepare('SELECT 1 FROM menu_tag WHERE menu_id = ? AND tag_id = ?');
                $stmt->execute([$menuId, $tagId]);
                if (!$stmt->fetchColumn()) {
                    $this->conn->prepare('INSERT INTO menu_tag (menu_id, tag_id) VALUES (?, ?)')->execute([$menuId, $tagId]);
                }
            }

            // Remove tags that are no longer associated (directly using names)
            $tagsToRemove = array_diff($existingTags, $tagNames);
            if (!empty($tagsToRemove)) {
                $placeholders = implode(',', array_fill(0, count($tagsToRemove), '?'));
                $stmt = $this->conn->prepare("
                    DELETE mt FROM menu_tag mt 
                    JOIN tag t ON mt.tag_id = t.id 
                    WHERE mt.menu_id = ? AND t.name IN ($placeholders)
                ");
                $params = array_merge([$menuId], $tagsToRemove);
                $stmt->execute($params);
            }

            $this->conn->commit();
            return ['success' => true, 'id' => $menuId];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
}
