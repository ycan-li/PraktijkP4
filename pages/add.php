<?php
require_once "../lib/wejv.php";
require_once "../lib/utils.php";

// Initialize Wejv class to fetch filter data
try {
    $wejv = new Wejv('../db_creds.json');
    $filters = $wejv->fetchFilters();
} catch (Exception $e) {
    $filters = [];
    // Handle error silently, we'll just not show genre/tags if they fail to load
}

$categories = [
    "genre",
    "tag"
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voeg Recept Toe - WEJV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<?php include "../components/navbar.php"; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card bg-dark shadow-lg p-4 rounded-4 border-0">
                <h1 class="mb-4">Nieuw Recept Toevoegen</h1>

                <!-- Success and Error alerts -->
                <div id="success-alert" class="alert alert-success d-none" role="alert">
                    Recept succesvol toegevoegd! Je wordt doorgestuurd naar de detailpagina...
                </div>
                <div id="error-alert" class="alert alert-danger d-none" role="alert">
                    Er is een fout opgetreden bij het toevoegen van je recept.
                </div>

                <form id="add-recipe-form" class="needs-validation" novalidate enctype="multipart/form-data">
                    <!-- Basic Information -->
                    <section class="mb-4">
                        <h2 class="h4 mb-3">Basis Informatie</h2>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label">Naam van het gerecht *</label>
                                <input type="text" class="form-control bg-dark text-white border-secondary" id="name"
                                       name="name" required>
                                <div class="invalid-feedback">Vul een naam in voor je recept</div>
                            </div>
                            <div class="col-md-6">
                                <label for="prepare_time" class="form-label">Bereidingstijd (minuten) *</label>
                                <input type="number" class="form-control bg-dark text-white border-secondary"
                                       id="prepare_time" name="prepare_time" min="1" required>
                                <div class="invalid-feedback">Vul de bereidingstijd in</div>
                            </div>
                            <div class="col-md-6">
                                <label for="person_num" class="form-label">Aantal personen *</label>
                                <input type="number" class="form-control bg-dark text-white border-secondary"
                                       id="person_num" name="person_num" min="1" required>
                                <div class="invalid-feedback">Vul het aantal personen in</div>
                            </div>
                        </div>
                    </section>

                    <!-- Description -->
                    <section class="mb-4">
                        <h2 class="h4 mb-3">Beschrijving</h2>
                        <div class="mb-3">
                            <label for="description" class="form-label">Beschrijving van het gerecht *</label>
                            <textarea class="form-control bg-dark text-white border-secondary" id="description"
                                      name="description" rows="3" required></textarea>
                            <div class="invalid-feedback">Voeg een beschrijving toe</div>
                        </div>
                    </section>

                    <!-- Image Upload -->
                    <section class="mb-4">
                        <h2 class="h4 mb-3">Afbeelding</h2>
                        <div class="mb-3">
                            <label for="image" class="form-label">Foto van het gerecht (JPG, PNG of WebP)</label>
                            <input class="form-control bg-dark text-white border-secondary" type="file" id="image"
                                   name="image" accept="image/jpeg,image/jpg,image/png,image/webp">
                            <div class="form-text">De afbeelding wordt automatisch geconverteerd naar WebP</div>
                        </div>
                    </section>

                    <!-- Ingredients -->
                    <section class="mb-4">
                        <h2 class="h4 mb-3">Ingrediënten *</h2>
                        <div id="ingredients-container">
                            <div class="ingredient-row mb-2 d-flex gap-2">
                                <input type="number" class="form-control bg-dark text-white border-secondary"
                                       placeholder="Aantal" step="0.1" min="0">
                                <input type="text" class="form-control bg-dark text-white border-secondary"
                                       placeholder="Eenheid (g, ml, stuks)">
                                <input type="text" class="form-control bg-dark text-white border-secondary"
                                       placeholder="Ingrediënt">
                                <button type="button" class="btn btn-outline-danger remove-ingredient">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="ingredients" id="ingredients-json">
                        <button type="button" id="add-ingredient" class="btn btn-outline-primary mt-2">
                            <i class="bi bi-plus-lg"></i> Voeg ingrediënt toe
                        </button>
                    </section>

                    <!-- Preparation -->
                    <section class="mb-4">
                        <h2 class="h4 mb-3">Bereidingswijze *</h2>
                        <div class="mb-3">
                            <label for="preparation" class="form-label">Bereidingswijze stap voor stap</label>
                            <textarea class="form-control bg-dark text-white border-secondary" id="preparation"
                                      name="preparation" rows="6" required></textarea>
                            <div class="form-text">Gebruik een nieuwe regel voor elke stap</div>
                            <div class="invalid-feedback">Voeg een bereidingswijze toe</div>
                        </div>
                    </section>

                    <!-- Categories -->
                    <section class="mb-4">
                        <h2 class="h4 mb-3">Categorieën</h2>
                        <div class="row">
                            <?php foreach ($categories as $category) : ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo ucfirst($category) ?></label>
                                    <div class="tags-input <?php echo $category ?>-tags-input position-relative d-flex flex-column gap-2 border rounded p-2 bg-dark text-white"
                                         data-type="<?php echo $category ?>">
                                        <div class="<?php echo $category ?>-selected selected d-flex flex-wrap gap-1"
                                             data-type="<?php echo $category ?>">
                                        </div>
                                        <form>
                                            <input type="text"
                                                   data-type="<?php echo $category ?>"
                                                   id="<?php echo $category ?>-search"
                                                   class="search form-control form-control-sm bg-dark text-white border-secondary tag-input"
                                                   placeholder="Zoeken of aanmaken..." autocomplete="off">
                                            <button data-type="<?php echo $category ?>"
                                                    type="submit" class="d-none search-button">Zoek
                                            </button>
                                        </form>
                                        <!-- Suggestions -->
                                        <div class="position-relative">
                                            <div class="bg-body-secondary position-absolute d-block w-100 border-0 pt-0 mx-0 rounded-3 shadow overflow-x-hidden overflow-y-auto position-relative z-1"
                                                 style="max-height: calc(100px + 5dvh);">
                                                <div data-type="<?php echo $category ?>"
                                                     class="<?php echo $category ?>-suggestion suggestion badge-container d-none flex-wrap  gap-1 p-2 h-100">
                                                    <?php if (!empty($filters[$category])): ?>
                                                        <?php foreach ($filters[$category] as $cat): ?>
                                                            <span class="suggestion-badge badge d-flex gap-1 link-body-emphasis fs-7 bg-secondary-subtle border border-secondary-subtle text-secondary-emphasis rounded-pill" style="cursor: pointer;"
                                                                  data-id="<?= $cat['id'] ?>"
                                                                  data-name="<?= strtolower(htmlspecialchars($cat['name'])) ?>"
                                                                  data-type="<?php echo $category ?>">
                                                                <?= htmlspecialchars($cat['name']) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                            Annuleren
                        </button>
                        <button type="submit" class="btn btn-primary">Recept Toevoegen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO"
        crossorigin="anonymous"></script>
<script type="module" src="../js/add.js"></script>
</body>
</html>
