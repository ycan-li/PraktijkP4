<?php
function getGreeting(): string
{
    $hour = (int)date('G');
    return $hour < 6 ? "Goedenacht" :
        ($hour < 12 ? "Goedemorgen" :
            ($hour < 18 ? "Goedemiddag" : "Goedeavond"));
}

function getUsername(): string
{
    #TODO fetch username from db
    return "Li";
}

function isLogin(): bool
{
    #TODO
    return false;
}

function recentAddedMenu(): array
{
    #TODO
    return [
        [
            "id" => 1,
            "name" => "Test name",
            "img" => "../test/meal.jpeg",
            "author" => "Test Author",
            "ctime" => "2017-06-15 09:34:21"
        ],
        [
            "id" => 1,
            "name" => "Test name",
            "img" => "../test/meal.jpeg",
            "author" => "Test Author",
            "ctime" => "2017-06-15 09:34:21"
        ],
        [
            "id" => 1,
            "name" => "Test name",
            "img" => "../test/meal.jpeg",
            "author" => "Test Author",
            "ctime" => "2017-06-15 09:34:21"
        ]
    ];
}


class File
{
    private string $path {
        get {
            return $this->path;
        }
    }

    public static function joinPaths(...$paths): array|string|null
    {
        return preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }

    public static function cache(string $str, string $cache_dir = "./"): File
    {
        $full_path = self::joinPaths($cache_dir, $str);
        return new File($full_path);
    }

    public function __construct(string $str)
    {
        if (!file_exists($str)) {
            throw new RuntimeException("$str not found");
        }

        $this->path = $str;
    }

    public function __toString()
    {
        return $this->path;
    }
}

?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fixed top navbar example · Bootstrap v5.3</title>
    <link href="../assets/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="../assets/js/color-modes.js"></script>
    <script src="../js/discover.js"></script>
</head>
<body>
<!--navbar-->
<?php include "../components/navbar.php"; ?>
<main>
    <div class="container tab-navigation d-flex justify-content-start gap-2 px-4">
        <button class="tab-btn btn btn-outline-primary active" data-tab="all">Alles</button>
        <button class="tab-btn btn btn-outline-primary" data-tab="created-by-me">Ik</button>
        <button class="tab-btn btn btn-outline-primary" data-tab="created-by-others">anderen</button>
    </div>
</main>

<script src="../assets/dist/js/bootstrap.bundle.min.js"></script>
</body>

