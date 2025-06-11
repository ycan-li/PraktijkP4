<?php
# Init database

require_once("utils.php");

class DB
{
    private PDO $conn;
    private PDO $conn_info;
    public string $dbname;

    public function __construct(array|string $creds, string $dbname = "")
    {
        if (is_string($creds)) {
            $creds = json_decode(file_get_contents($creds), true);
        }
        $this->dbname = $dbname == "" ? $creds['dbname'] : $dbname;
        $dsn = "mysql:host={$creds['host']};dbname={$creds['dbname']};charset=utf8mb4";
        $dsn_info = "mysql:host={$creds['host']};dbname=information_schema;charset=utf8mb4";
        $this->conn = new PDO($dsn, $creds['user'], $creds['password']);
        $this->conn_info = new PDO($dsn_info, $creds['user'], $creds['password']);
    }

    # Fetch rows matched cols from table
    public function fetch(string $table, array|string $cols = '*', array|string $cols_exlc = [], string $ordered_by = 'id', bool $desc = true): array|false
    {
        if (is_string($cols)) {
            if ($cols == '*') {
                $cols = $this->fetchAllCols($table);
            } else {
                $cols = [$cols];
            }
        }
        $cols = array_diff($cols, (is_array($cols_exlc) ? $cols_exlc : [$cols_exlc]));
        $cols_count = count($cols);
        $cols_in_string = array_to_sql_value($cols);

        $sort_order = $desc ? 'DESC' : 'ASC';
        $sql = "SELECT $cols_in_string FROM `$table` ORDER BY `$ordered_by` $sort_order";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $cols_count > 1 ? $stmt->fetchAll(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function fetchAllCols(string $table): array
    {
        if (!verify_name($table)) {
            throw new InvalidArgumentException("Invalid table name: $table");
        }

        $stmt = $this->conn_info->prepare("
            SELECT COLUMN_NAME
            FROM COLUMNS
            WHERE TABLE_NAME = :table
                AND TABLE_SCHEMA = :dbname
            ORDER BY COLUMN_NAME
        ");
        $stmt->execute([
            ':table' => $table,
            ':dbname' => $this->dbname
        ]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
