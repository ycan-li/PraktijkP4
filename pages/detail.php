<?php
require_once "../lib/wejv.php";
require_once "../lib/utils.php";

// Get menu ID from GET parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Initialize Wejv class and fetch menu info
try {
    $wejv = new Wejv('../db_creds.json');
    $menuInfo = $wejv->fetchInfo($id);
} catch (Exception $e) {
    // Handle error
    die("Error fetching menu details: " . $e->getMessage());
}

// Format image data
$imgSrc = $menuInfo['img'] ? "data:image/webp;base64,{$menuInfo['img']}" : '../assets/img/meal.jpeg';

// Format ingredients as array
$ingredients = [];
if (!empty($menuInfo['ingredients'])) {
    $rawIngredients = explode(';', $menuInfo['ingredients']);
    foreach ($rawIngredients as $rawIngredient) {
        $ingredient = trim($rawIngredient);
        if (!empty($ingredient)) {
            // Try to extract amount, unit, and name
            // Pattern to match: quantity + unit + name (e.g. "100 g passendale kaas")
            if (preg_match('/^(\d+(?:\.\d+)?)\s+([a-zA-Z]+)\s+(.+)$/', $ingredient, $matches)) {
                $ingredients[] = [
                    'amount' => $matches[1],
                    'unit' => $matches[2],
                    'name' => $matches[3]
                ];
            } else {
                // If pattern doesn't match, put everything in name
                $ingredients[] = [
                    'amount' => '',
                    'unit' => '',
                    'name' => $ingredient
                ];
            }
        }
    }
}

// Format date
$date = isset($menuInfo['ctime']) ? date('j F Y', strtotime($menuInfo['ctime'])) : 'Onbekend';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($menuInfo['name']) ?> - Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<?php include "../components/navbar.php"; ?>

<main class="container py-4">
    <div class="row g-5">
        <!-- Image and Basic Info Section -->
        <div class="col-12">
            <div class="row g-4">
                <!-- Image Section -->
                <div class="col-md-6">
                    <div class="card bg-dark shadow-lg overflow-hidden rounded-4 border-0">
                        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($menuInfo['name']) ?>" class="card-img-top img-fluid" style="max-height: 400px; object-fit: cover;">
                    </div>
                </div>

                <!-- Basic Info Section -->
                <div class="col-md-6">
                    <div class="card bg-dark shadow-lg h-100 p-4 rounded-4 border-0">
                        <h1 class="display-4 fw-bold mb-3"><?= htmlspecialchars($menuInfo['name']) ?></h1>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <?php if (!empty($menuInfo['genre'])): ?>
                                <?php foreach ($menuInfo['genre'] as $genre): ?>
                                    <span class="badge bg-light-subtle border border-light-subtle text-light-emphasis rounded-pill"><?= htmlspecialchars($genre) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-clock-history fs-4"></i>
                                    <div>
                                        <p class="mb-0 text-secondary-emphasis">Bereidingstijd</p>
                                        <p class="fs-5 fw-bold mb-0"><?= $menuInfo['prepare_time'] ? $menuInfo['prepare_time'] . ' min' : 'n.v.t.' ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-people fs-4"></i>
                                    <div>
                                        <p class="mb-0 text-secondary-emphasis">Aantal personen</p>
                                        <p class="fs-5 fw-bold mb-0"><?= $menuInfo['person_num'] ?: 'n.v.t.' ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person-circle fs-4"></i>
                                    <div>
                                        <p class="mb-0 text-secondary-emphasis">Auteur</p>
                                        <p class="fs-5 fw-bold mb-0"><?= htmlspecialchars($menuInfo['author'] ?: 'Onbekend') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-6">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-calendar-date fs-4"></i>
                                    <div>
                                        <p class="mb-0 text-secondary-emphasis">Toegevoegd op</p>
                                        <p class="fs-5 fw-bold mb-0"><?= $date ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($menuInfo['tag'])): ?>
                            <div class="mb-4">
                                <p class="text-secondary-emphasis mb-2">Tags</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($menuInfo['tag'] as $tag): ?>
                                        <span class="badge border border-dark-subtle text-dark-emphasis rounded-pill"># <?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-auto">
                            <button class="fav btn btn-primary w-25 d-flex justify-content-center align-items-center">
                                <i class="bi bi-heart"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description Section -->
        <?php if (!empty($menuInfo['description'])): ?>
            <div class="col-12 mt-4">
                <div class="card bg-dark shadow-lg p-4 rounded-4 border-0">
                    <h2 class="fw-bold mb-3">Beschrijving</h2>
                    <div class="lh-lg">
                        <?= nl2br(htmlspecialchars($menuInfo['description'])) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Ingredients Section -->
        <div class="col-md-5 mt-4">
            <div class="card bg-dark shadow-lg p-4 rounded-4 border-0 h-100">
                <h2 class="fw-bold mb-3">Ingrediënten</h2>
                <?php if (!empty($ingredients)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($ingredients as $ingredient): ?>
                            <li class="list-group-item bg-transparent border-bottom border-dark py-3 d-flex justify-content-between">
                                <span><?= htmlspecialchars($ingredient['name']) ?></span>
                                <span class="fw-bold"><?= htmlspecialchars($ingredient['amount'] . ' ' . $ingredient['unit']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-secondary">Geen ingrediënten beschikbaar.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preparation Section -->
        <div class="col-md-7 mt-4">
            <div class="card bg-dark shadow-lg p-4 rounded-4 border-0 h-100">
                <h2 class="fw-bold mb-3">Bereidingswijze</h2>
                <?php if (!empty($menuInfo['preparation'])): ?>
                    <div class="lh-lg">
                        <?= nl2br(htmlspecialchars($menuInfo['preparation'])) ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary">Geen bereidingswijze beschikbaar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
<script src="../js/detail.js"></script>
</body>
</html>

