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
            LEFT JOIN menu_info_genre mg ON m.id = mg.menu_info_id
            LEFT JOIN genre g ON mg.genre_id = g.id
            LEFT JOIN menu_info_tag mt ON m.id = mt.menu_info_id
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
            "prepare_time_group",
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
     *     prepare_time: int,
     *     img: string|null
     *   }>
     * } Returns an array with total count and data array of cards. Each card contains id, name, author, genre, tag, prepare_time, and img (base64 or null).
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
            $join_sql .= " LEFT JOIN fav f ON m.id = f.menu_info_id";
//            $join_sql .= " LEFT JOIN fav f ON m.id = f.menu_info_id AND f.user_id = :favUserId";
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
        SELECT m.id, m.name, m.prepare_time, m.img,
               a.name AS author,
               " . ($favFilter ? "IF(f.user_id IS NOT NULL, 1, 0) AS is_favorite," : "") . "
            GROUP_CONCAT(DISTINCT g.name) AS genres,
            GROUP_CONCAT(DISTINCT t.name) AS tags,
            ($match_count_sql) AS match_count
        FROM menu_info m
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
        FROM menu_info m
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
            SELECT m.name, m.prepare_time, m.img, m.person_num, m.ingredients, m.description, m.preparation, m.ctime,
                   a.name AS author,
            GROUP_CONCAT(DISTINCT g.name) AS genres,
            GROUP_CONCAT(DISTINCT t.name) AS tags
            FROM menu_info m
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
            $stmt = $this->conn->prepare("INSERT INTO user (username, email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
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
            SELECT id, username, password_hash FROM user WHERE username = ? OR email = ?
        ");

        $stmt->execute([$name, $name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $stmt = $this->conn->prepare("UPDATE user SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            return $user['id'];
        }

        return false;
    }

    public function fetchUserInfo(int $id): array|false
    {
        $stmt = $this->conn->prepare("
            SELECT id, username, email, first_name, last_name, role FROM user WHERE id = ?
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
        $stmt = $this->conn->prepare("SELECT 1 FROM fav WHERE user_id = ? AND menu_info_id = ?");
        $stmt->execute([$userId, $menuId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // Remove favorite
            $stmt = $this->conn->prepare("DELETE FROM fav WHERE user_id = ? AND menu_info_id = ?");
            $stmt->execute([$userId, $menuId]);
            return false;
        } else {
            // Add favorite
            $stmt = $this->conn->prepare("INSERT INTO fav (user_id, menu_info_id) VALUES (?, ?)");
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
        $stmt = $this->conn->prepare("SELECT 1 FROM fav WHERE user_id = ? AND menu_info_id = ?");
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
        $stmt = $this->conn->prepare("SELECT menu_info_id FROM fav WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Insert a new menu item into the database
     *
     * @param array $menuData Array containing menu data (name, prepare_time, person_num, author_id, description, preparation, ingredients, img, genres, tags)
     * @return int The ID of the newly created menu item
     * @throws Exception If the insertion fails
     */
    public function insertMenu(array $menuData): int
    {
        try {
            $this->conn->beginTransaction();

            // Insert into menu_info table
            $stmt = $this->conn->prepare("
                INSERT INTO menu_info (name, prepare_time, person_num, author_id, description, preparation, ingredients, img)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $menuData['name'],
                $menuData['prepare_time'],
                $menuData['person_num'],
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
                    INSERT INTO menu_info_genre (menu_info_id, genre_id)
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
                    INSERT INTO menu_info_tag (menu_info_id, tag_id)
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
}
