<?php
// ...existing code...
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Toevoeg - Zoek en Filter</title>
    <link href="../assets/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="../css/style.css" rel="stylesheet">
    <script src="../assets/js/color-modes.js"></script>
    <script src="../js/script.js"></script>
</head>
<body>
<?php include "../components/navbar.php"; ?>
<main class="container py-4">
    <!-- Search Box -->
    <form class="mb-4 d-flex" role="search">
        <input class="form-control me-2" type="search" placeholder="Zoek..." aria-label="Zoek">
        <button class="btn btn-primary" type="submit">Zoeken</button>
    </form>
    <!-- Filters -->
    <div class="mb-4 d-flex flex-wrap gap-2" id="filter-container"></div>
    <div class="container px-4 py-4" id="content-container">
    </div>
</main>
<script src="../assets/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Fetch data and render cards/filters
    document.addEventListener('DOMContentLoaded', function () {
        fetch('../controllers/endpoint.php?action=getFilter')
            .then(res => res.json())
            .then(data => {
                // console.log(data);
                // renderCards(data.cards);
                renderFilters(data);
            });

        function renderCards(cards) {
            const cardsContainer = document.querySelector('#custom-cards .row');
            if (!cardsContainer) return;
            cardsContainer.innerHTML = '';
            cards.forEach(card => {
                let imgSrc = card.img ? `data:image/jpeg;base64,${card.img}` : '';
                const col = document.createElement('div');
                col.className = 'col';
                col.innerHTML = `
                    <div class="row row-cols-1 row-cols-lg-3 align-items-stretch g-4 py-4 py-lg-5">
                        <div class="card card-cover h-100 overflow-hidden text-bg-dark rounded-4 shadow-lg">
                            <img src="${imgSrc}" class="card-img-top" alt="${card.name}" style="object-fit:cover;max-height:180px;">
                            <div class="d-flex flex-column h-100 p-5 pb-3 text-white text-shadow-1">
                                <h3 class="py-3 display-6 lh-1 fw-bold">${card.name}</h3>
                                <ul class="d-flex list-unstyled mt-auto">
                                    <li class="d-flex align-items-center me-3 gap-1">
                                        <i class="bi bi-egg-fried"></i>
                                        <small>Hoofdgerecht</small></li>
                                    <li class="d-flex align-items-center gap-1">
                                        <i class="bi bi-clock-history"></i>
                                        <small>${card.prepare_time || ''}</small></li>
                                </ul>
                                <ul class="toolbar d-flex justify-content-end list-unstyled">
                                    <li style="cursor:pointer;">
                                        <i class="bi bi-heart display-7" data-bs-toggle="popover" data-bs-content="Fav"></i>
                                        <i class="bi bi-heart-fill display-7 d-none" data-bs-toggle="popover" data-bs-content="Undo Fav"></i>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
                cardsContainer.appendChild(col);
            });
            // Re-init popovers for new cards
            const popoverTriggerList = [].slice.call(document.querySelectorAll('.toolbar .bi-heart, .toolbar .bi-heart-fill'));
            popoverTriggerList.forEach(function (el) {
                new bootstrap.Popover(el, {
                    trigger: 'hover focus',
                    placement: 'bottom',
                    delay: {show: 500, hide: 100},
                    template: '<div class="popover" role="tooltip"><div class="popover-arrow"></div><div class="popover-body"></div></div>'
                });
            });
        }

        function renderFilters(filters) {
            /**
             *
             * @param el {HTMLElement}
             */
            function toggleCheckbox(el) {
                const checkbox = el.querySelector('input[type="checkbox]')
                checkbox.checked = !checkbox.checked;
            }

            const filtersContainer = document.querySelector('#filter-container');
            if (!filtersContainer) {
                console.error(`No '#filter-container' found`);
                return;
            }

            for (const filterName in filters) {
                const filterItems = filters[filterName];
                const filterTemplate = `
                    <div id='filter-${filterName}' class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle mb-2 fw-bold" data-bs-toggle="dropdown"
                                id="title-${filterName}" aria-expanded="false">${filterName}
                        </button>
                        <div class="dropdown-menu position-absolute border-0 pt-0 mx-0 rounded-3 shadow overflow-x-hidden overflow-y-auto w-100"
                             style="min-width: 200px; max-height: 400px"
                             id="dropdown-menu-${filterName}" aria-labelledby="title-${filterName}">
                            <div class="selected d-flex flex-wrap pt-3"></div>
                            <form class="p-2 bg-dark border-bottom border-dark">
                                <input type="search" class="form-control bg-dark filter-search" autocomplete="off"
                                       placeholder="Type to filter...">
                            </form>
                            <ul class="list-unstyled mb-0">
                            </ul>
                        </div>
                    </div>
                `;
                filtersContainer.insertAdjacentHTML('afterbegin', filterTemplate);

                const itemContainer = filtersContainer.querySelector(`#filter-${filterName} ul`)

                for (const itemIndex in filterItems) {
                    const itemInfo = filterItems[itemIndex];
                    const itemId = itemInfo['id'];
                    const itemName = itemInfo['name'];

                    const filterItemTemplate = `
                        <li id="${filterName}-${itemId}" class="dropdown-item d-flex align-items-start gap-2 py-2 clickable-checkbox"
                            style="cursor:pointer;"
                            >
                            <input class="form-check-input flex-shrink-0" type="checkbox"
                                                           value="${itemName}">${itemName}
                        </li>
                    `;
                    itemContainer.insertAdjacentHTML('afterbegin', filterItemTemplate);

                    const item = itemContainer.querySelector(`#${filterName}-${itemId}`);
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    checkbox.addEventListener('click', (e) => {
                        e.stopPropagation();
                    });
                    item.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        checkbox.checked = !checkbox.checked;
                    })
                }
            }
        }
        function renderContent() {

        }
    });
</script>
</body>
</html>
