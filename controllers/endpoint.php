<?php
require_once("../lib/db.php");

$creds_path = __DIR__ . '/../db_creds.json';
$all_filters = [
    "author",
    "prepare_time_group",
    "tags",
    "genre"
];

function get_filters($creds, array|string $filters = "*"): false|string
{
    $data = [];

    foreach ($filters as $filter)
    {
        $db = new DB($creds, $filter);

        $data[$filter] = $db->fetch($filter, ["id", "name"]);
    }

    return json_encode($data);
}

if (isset($_GET)) {
    header('Content-Type: application/json');
    if ($_GET["action"] == "getFilter") {
        echo(get_filters($creds_path, $all_filters));
    }
}

