<?php

function verify_name(string $str): bool
{
    if (!preg_match('/^\w+$/', $str)) {
        return false;
    }
    return true;
}

function array_to_sql_value(array $arr): string
{
    return $arr === ['*']
        ? '*'
        : implode(', ', array_map(fn($x) => "`$x`", $arr));
}