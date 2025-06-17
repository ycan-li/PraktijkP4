<?php


use PHPUnit\Framework\TestCase;

class WejvTest extends TestCase
{

    private string $creds_path = '../db_creds.json';

    public function testFetchFilters()
    {
        require_once __DIR__ . '/../lib/wejv.php';
        $wejv = new Wejv($this->creds_path);

        $res = $wejv->fetchFilters();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('genre', $res);
        $this->assertArrayHasKey('tag', $res);
        $this->assertArrayHasKey('prepare_time_group', $res);
        $this->assertArrayHasKey('author', $res);
        foreach ($res as $f => $rows) {
            foreach ($rows as $r) {
                $this->assertArrayHasKey('id', $r, 'Missing key "id" in column ' . $f . ': ' . var_export($r, true));
                $this->assertArrayHasKey('name', $r, 'Missing key "name" in column ' . $f . ': ' . var_export($r, true));
                $this->assertIsInt($r['id'], 'id is not int in column ' . $f . ': ' . var_export($r['id'], true));
                $this->assertNotEmpty($r['id'], 'id is empty in column ' . $f . ': ' . var_export($r, true));
                $this->assertIsString($r['name'], 'name is not string in column ' . $f . ': ' . var_export($r['name'], true));
//                $this->assertNotEmpty($r['name'], 'name is empty in column ' . $f . ': ' . var_export($r, true));
            }
        }
    }

    public function testFetchCards() {
        require_once __DIR__ . '/../lib/wejv.php';
        $wejv = new Wejv($this->creds_path);
        $filters = [
            "author" => ["4", "16", "14"],
            "genre" => ["12", "11", "10", "9"],
//            "fav" => 2
        ];

        $res = $wejv->fetchCards(filters: $filters);
        $this->assertIsArray($res);
        $res = $wejv->fetchCards();
        $this->assertIsArray($res);
        foreach ($res as $row) {
            $this->assertNotEmpty($row, 'Empty row by fetchCards without filters');
        }
    }

    public function testFetchInfo() {
        require_once __DIR__ . '/../lib/wejv.php';
        $wejv = new Wejv($this->creds_path);
        $id = 1;
        $res = $wejv->fetchInfo($id);
        $this->assertIsArray($res);
    }

    public function testRegister() {
        require_once __DIR__ . '/../lib/wejv.php';
        $wejv = new Wejv($this->creds_path);
        $res = $wejv->register("test", "test", "test", "test", "test");
        $this->assertTrue($res);
    }

    public function testLogin() {
        require_once __DIR__ . '/../lib/wejv.php';
        $wejv = new Wejv($this->creds_path);
        $res = $wejv->login("test", "test");
        $this->assertIsInt($res);
    }

    public function testFetchUserInfo() {
        require_once __DIR__ . '/../lib/wejv.php';
        $wejv = new Wejv($this->creds_path);
        $res = $wejv->fetchUserInfo(1);
        $this->assertIsArray($res);
    }
}

