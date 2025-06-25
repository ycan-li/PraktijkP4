<?php
// ...existing code...
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Toevoeg - Zoek en Filter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
<?php include "../components/navbar.php"; ?>
<main class="container py-r">
    <!-- Search Box (visible only on mobile) -->
    <div id="search-section" class="d-block d-lg-none mb-4 px-4">
        <form class="d-flex" role="search">
            <input id="search-box" class="form-control me-2" type="search" placeholder="Zoek..." aria-label="Zoek">
            <button id="search-button" class="btn btn-primary" type="submit">Zoeken</button>
        </form>
    </div>

    <div class="filter-toolbar d-flex flex-column flex-lg-row flex-wrap justify-content-between align-items-center gap-3">
        <div class="left d-flex flex-grow-1 w-100 justify-content-start align-items-center gap-3 px-4"></div>
        <div class="right d-flex w-100 flex-column flex-lg-row justify-content-start align-items-center gap-3 px-4">
            <div class="d-flex flex-wrap flex-lg-grow-1 align-items-center align-self-start gap-2" id="filter-container">
                <!-- Filter buttons will be added dynamically -->
            </div>

            <!-- Filter Action Buttons -->
            <div class="d-flex justify-content-end align-self-end gap-2">
                <button id="apply-filter-button" class="btn btn-primary">Apply Filter</button>
                <button id="clear-filter-button" class="btn btn-outline-secondary">Clear Filter</button>
            </div>
        </div>
    </div>
    <!-- Filters -->

    <div class="d-flex flex-column align-items-center px-4 py-4" id="content-container">
        <nav id="pagination-container-top" class="pagination-container d-flex justify-content-center align-self-center w-100" aria-label="Page pagination" style="max-width:100%;">
            <ul class="pagination flex-wrap mb-0 w-100" style="max-width:100%;"></ul>
        </nav>
        <div id="card-container" class="w-100">
        </div>
        <nav id="pagination-container-bottom" class="pagination-container d-flex justify-content-center align-self-center w-100" aria-label="Page pagination" style="max-width:100%;">
            <ul class="pagination flex-wrap mb-0 w-100" style="max-width:100%;"></ul>
        </nav>
    </div>
</main>

<?php include "../components/delete-confirm-modal.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
<script src="../js/home.js"></script>
</body>
</html>
