document.addEventListener('DOMContentLoaded', function () {
    // Keep track of current page globally
    let currentPageGlobal = 1;

    // Constants
    const NUM_PER_PAGE = 24;
    const NUM_PER_ROW = 3;

    // Store latest total results count for pagination
    const state = {
        total: 0,
        filterSelections: {} // Track selected filters
    };

    // Flag to determine if filters have been applied
    let filtersApplied = false;
    let favFilterActive = false;

    // Check if there's a search query passed from another page
    const navbarSearchQuery = localStorage.getItem('navbarSearchQuery');
    if (navbarSearchQuery) {
        // Set the search boxes to the query from localStorage
        const mobileSearchBox = document.querySelector('#search-box');
        const navbarSearchBox = document.querySelector('#navbar-search-box');

        if (mobileSearchBox) mobileSearchBox.value = navbarSearchQuery;
        if (navbarSearchBox) navbarSearchBox.value = navbarSearchQuery;

        // Clear after use
        localStorage.removeItem('navbarSearchQuery');
    }

    fetch('../controllers/endpoint.php?action=getFilter')
        .then(res => res.json())
        .then(data => {
            renderFilters(data);
            addFavoritesFilter(); // Add this line
        });

    renderContent(currentPageGlobal);

    // Handle both search forms - mobile and desktop
    const mobileSearchButton = document.querySelector('#search-button');
    const navbarSearchButton = document.querySelector('#navbar-search-button');

    if (mobileSearchButton) {
        mobileSearchButton.addEventListener('click', (e) => {
            e.preventDefault();
            currentPageGlobal = 1;
            renderContent(1);
        });
    }

    if (navbarSearchButton) {
        navbarSearchButton.addEventListener('click', (e) => {
            e.preventDefault();
            currentPageGlobal = 1;
            renderContent(1);
        });
    }

    // Sync search boxes so they display the same text
    const mobileSearchBox = document.querySelector('#search-box');
    const navbarSearchBox = document.querySelector('#navbar-search-box');

    if (mobileSearchBox && navbarSearchBox) {
        // Sync navbar search to mobile search
        navbarSearchBox.addEventListener('input', () => {
            mobileSearchBox.value = navbarSearchBox.value;
        });

        // Sync mobile search to navbar search
        mobileSearchBox.addEventListener('input', () => {
            navbarSearchBox.value = mobileSearchBox.value;
        });
    }

    // Smooth scroll to search section when mobile search icon is clicked
    const searchIcon = document.querySelector('a[href="#search-section"]');
    if (searchIcon) {
        searchIcon.addEventListener('click', (e) => {
            e.preventDefault();
            const searchSection = document.querySelector('#search-section');
            if (searchSection) {
                searchSection.scrollIntoView({behavior: 'smooth'});
                // Focus on the search input after scrolling
                setTimeout(() => {
                    const searchInput = searchSection.querySelector('input[type="search"]');
                    if (searchInput) searchInput.focus();
                }, 500);
            }
        });
    }


    // Functions
    function addFavoritesFilter() {
        const isLoggedIn = !!sessionStorage.getItem('userInfo');
        if (!isLoggedIn) return; // Only show to logged in users

        const filterContainer = document.getElementById('filter-container');
        if (!filterContainer) return;

        const favFilterTemplate = `
            <div id="filter-favorites" class="favorite-filter">
                <button id="favorites-toggle" class="btn btn-outline-danger mb-2 fw-bold">
                    <i class="bi bi-heart me-1"></i> Favorites
                </button>
            </div>
        `;

        // Add to beginning of filters
        filterContainer.insertAdjacentHTML('afterbegin', favFilterTemplate);

        // Add toggle behavior
        const favToggle = document.getElementById('favorites-toggle');
        favToggle.addEventListener('click', function () {
            favFilterActive = !favFilterActive;

            if (favFilterActive) {
                this.classList.remove('btn-outline-danger');
                this.classList.add('btn-danger');
                this.querySelector('i').classList.remove('bi-heart');
                this.querySelector('i').classList.add('bi-heart-fill');
            } else {
                this.classList.add('btn-outline-danger');
                this.classList.remove('btn-danger');
                this.querySelector('i').classList.add('bi-heart');
                this.querySelector('i').classList.remove('bi-heart-fill');
            }
        });
    }

    // Attach favorite toggle functionality to heart icons
    function attachFavoriteListeners() {
        // Only attach if user is logged in
        const userInfo = sessionStorage.getItem('userInfo');
        if (!userInfo) return;

        const user = JSON.parse(userInfo);
        const heartIcons = document.querySelectorAll('.toolbar li');

        heartIcons.forEach(icon => {
            // Prevent multiple event listeners
            if (icon.dataset.hasListener === 'true') return;

            icon.dataset.hasListener = 'true';
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent triggering the card link

                const card = this.closest('.card');
                const menuId = card.dataset.id;
                const filledHeart = this.querySelector('.bi-heart-fill');
                const emptyHeart = this.querySelector('.bi-heart');

                // Send request to toggle favorite
                fetch('../controllers/endpoint.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'toggleFavorite',
                        userId: user.id,
                        menuId: menuId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Toggle the heart icons
                        if (data.isFavorite) {
                            filledHeart.classList.remove('d-none');
                            emptyHeart.classList.add('d-none');
                        } else {
                            filledHeart.classList.add('d-none');
                            emptyHeart.classList.remove('d-none');

                            // If we're in favorites filter mode, we might need to remove this card
                            if (favFilterActive) {
                                // Wait a moment before refreshing to give user visual feedback
                                setTimeout(() => {
                                    renderContent(currentPageGlobal);
                                }, 300);
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Error toggling favorite:', err);
                });
            });
        });
    }

    function renderFilters(filters) {
        const filterMap = {
            "genre": "Genre",
            "tag": "Tag",
            "prepare_time_group": "Voorbereid",
            "author": "Auteur"
        }
        const filtersContainer = document.querySelector('#filter-container');
        if (!filtersContainer) {
            console.error(`No '#filter-container' found`);
            return;
        }

        for (const filterName in filters) {
            const filterItems = filters[filterName];
            const filterDisplayName = filterMap[filterName];
            const filterTemplate = `
                <div id='filter-${filterName}' class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle mb-2 fw-bold" data-bs-toggle="dropdown"
                            id="title-${filterName}" aria-expanded="false">${filterDisplayName}
                    </button>
                    <div class="dropdown-menu position-absolute border-0 pt-0 mx-0 rounded-3 shadow overflow-x-hidden overflow-y-auto w-100"
                         style="min-width: 200px; max-height: 400px"
                         id="dropdown-menu-${filterName}" aria-labelledby="title-${filterName}">
                        <div class="selected d-flex flex-wrap pt-3"></div>
                        <form class="p-2 bg-dark border-bottom border-dark">
                            <input type="search" class="form-control bg-dark filter-search" autocomplete="off"
                                   placeholder="Typ te zoeken">
                        </form>
                        <ul class="list-unstyled mb-0">
                        </ul>
                    </div>
                </div>
            `;
            filtersContainer.insertAdjacentHTML('afterbegin', filterTemplate);

            const itemContainer = filtersContainer.querySelector(`#filter-${filterName} ul`);

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
                    item.classList.toggle('selected');

                    // Update filter button appearance when selection changes
                    updateFilterButtonAppearance(filterName);
                });
            }
        }

        // Set up filter action buttons
        setupFilterActionButtons();
    }

    // Updates the appearance of a filter button based on selections
    function updateFilterButtonAppearance(filterName) {
        const filterButton = document.querySelector(`#title-${filterName}`);
        if (!filterButton) return;

        const checkboxes = document.querySelectorAll(`#filter-${filterName} input[type="checkbox"]:checked`);

        if (checkboxes.length > 0) {
            // Change button to primary color when items are selected
            filterButton.classList.remove('btn-secondary');
            filterButton.classList.add('btn-primary');

            // Add badge with count if more than one item selected
            if (checkboxes.length > 1) {
                // Check if badge already exists
                let badge = filterButton.querySelector('.badge');
                if (!badge) {
                    // Create badge showing number of selected items
                    filterButton.innerHTML = `${filterButton.textContent.split('<')[0]} <span class="badge bg-light text-primary rounded-pill ms-1">${checkboxes.length}</span>`;
                } else {
                    // Update existing badge
                    badge.textContent = checkboxes.length;
                }
            } else {
                // Remove badge if only one item is selected
                filterButton.innerHTML = filterButton.textContent.split('<')[0];
            }
        } else {
            // Reset to default when no items are selected
            filterButton.classList.remove('btn-primary');
            filterButton.classList.add('btn-secondary');
            filterButton.innerHTML = filterButton.textContent.split('<')[0]; // Remove any badge
        }
    }

    // Set up the apply filter and clear filter buttons
    function setupFilterActionButtons() {
        const applyFilterButton = document.getElementById('apply-filter-button');
        const clearFilterButton = document.getElementById('clear-filter-button');

        if (applyFilterButton) {
            applyFilterButton.addEventListener('click', () => {
                filtersApplied = true;
                currentPageGlobal = 1;
                renderContent(1);
            });
        }

        if (clearFilterButton) {
            clearFilterButton.addEventListener('click', () => {
                // Clear all checkboxes
                document.querySelectorAll('#filter-container input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = false;
                });

                // Clear all selected items
                document.querySelectorAll('#filter-container li.selected').forEach(item => {
                    item.classList.remove('selected');
                });

                // Reset all filter button appearances
                document.querySelectorAll('#filter-container button.dropdown-toggle').forEach(button => {
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-secondary');
                    const badge = button.querySelector('.badge')
                    if (badge) badge.remove();
                });

                filtersApplied = false;
                currentPageGlobal = 1;
                renderContent(1);
            });
        }
    }

    function renderContent(currentPage = 1) {
        // Get all selected filter items
        let selectedFilters = {};

        // Add favorites filter if active
        if (favFilterActive) {
            let userInfo = sessionStorage.getItem('userInfo');
            if (userInfo) {
                userInfo = JSON.parse(userInfo);
                selectedFilters['fav'] = userInfo.id;
            }
        }

        const filters = document.querySelectorAll('#filter-container > div:not(.favorite-filter)');
        filters.forEach(function (filter) {
            const filterName = filter.id.replace('filter-', '');
            if (!selectedFilters[filterName]) {
                selectedFilters[filterName] = [];
            }
            const itemList = filter.querySelectorAll('ul li');
            itemList.forEach(function (item) {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox.checked) {
                    const itemId = item.id.replace(`${filterName}-`, '');
                    selectedFilters[filterName].push(itemId);
                }
            });
        });
        const searchText = document.getElementById('search-box').value;

        // Render cards
        const contentContainer = document.querySelector('#content-container');
        if (!contentContainer) {
            console.error(`No '#content-container' found`);
            return;
        }
        const params = {
            action: 'getContent',
            start: (currentPage - 1) * NUM_PER_PAGE,
            count: NUM_PER_PAGE,
            filters: selectedFilters,
            search: searchText
        };
        fetch(`../controllers/endpoint.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(params)
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                const total = data['total'] || 0;
                const menuData = data['data'] || [];

                // Update searchState with current values
                state.total = total;

                const cardContainer = contentContainer.querySelector('#card-container');
                cardContainer.innerHTML = '';

                // Render pagination with ellipsis
                const paginationContainers = contentContainer.querySelectorAll('.pagination');

                function renderPaginations() {
                    paginationContainers.forEach(pagi => {
                        pagi.innerHTML = '';
                        const totalPages = Math.ceil(state.total / NUM_PER_PAGE);
                        // Create a temporary page-item to measure width
                        const tempLi = document.createElement('li');
                        tempLi.className = 'page-item';
                        const tempA = document.createElement('a');
                        tempA.className = 'page-link';
                        tempA.textContent = '88'; // 2-digit for max width
                        tempLi.appendChild(tempA);
                        pagi.appendChild(tempLi);
                        // Measure available width and item width
                        const containerWidth = pagi.offsetWidth;
                        const itemWidth = tempLi.offsetWidth;
                        pagi.removeChild(tempLi);
                        // Calculate reserved slots (prev, next, first, last, ellipsis)
                        let reserved = 2; // prev, next
                        if (totalPages > 1) reserved += 2; // first, last
                        // We'll add ellipsis only if needed, so don't reserve for them yet
                        // Calculate how many page numbers can fit
                        let maxVisible = Math.max(1, Math.floor((containerWidth - reserved * itemWidth) / itemWidth));
                        maxVisible = Math.min(maxVisible, totalPages);
                        // Always show first and last if possible
                        let showFirst = true, showLast = true;
                        if (totalPages <= maxVisible + 2) {
                            showFirst = false;
                            showLast = false;
                        }
                        // Calculate start and end page
                        let startPage = 1;
                        let endPage = totalPages;
                        if (showFirst && showLast) {
                            let side = Math.floor((maxVisible - 2) / 2);
                            startPage = Math.max(2, currentPage - side);
                            endPage = Math.min(totalPages - 1, currentPage + side);
                            if (endPage - startPage + 1 < maxVisible - 2) {
                                if (startPage === 2) {
                                    endPage = Math.min(totalPages - 1, endPage + (maxVisible - 2 - (endPage - startPage + 1)));
                                } else if (endPage === totalPages - 1) {
                                    startPage = Math.max(2, startPage - (maxVisible - 2 - (endPage - startPage + 1)));
                                }
                            }
                        } else if (showFirst) {
                            startPage = 2;
                            endPage = Math.min(totalPages, maxVisible + 1);
                        } else if (showLast) {
                            startPage = Math.max(1, totalPages - maxVisible);
                            endPage = totalPages - 1;
                        } else {
                            startPage = 1;
                            endPage = totalPages;
                        }
                        // Prev button
                        const prevEl = document.createElement('li');
                        prevEl.className = 'page-item' + (currentPage === 1 ? ' disabled' : '');
                        const prevLink = document.createElement('a');
                        prevLink.className = 'page-link';
                        prevLink.classList.add('icon');
                        prevLink.href = '#';
                        prevLink.innerHTML = '&#xF27F;'; // Bootstrap icon chevron-left
                        prevLink.onclick = (e) => {
                            e.preventDefault();
                            if (currentPage > 1) goToPage(currentPage - 1);
                        };
                        prevEl.appendChild(prevLink);
                        pagi.appendChild(prevEl);
                        // First page + ellipsis
                        if (startPage > 1) {
                            const firstEl = document.createElement('li');
                            firstEl.className = 'page-item' + (currentPage === 1 ? ' active' : '');
                            const firstLink = document.createElement('a');
                            firstLink.className = 'page-link';
                            firstLink.href = '#';
                            firstLink.textContent = '1';
                            firstLink.onclick = (e) => {
                                e.preventDefault();
                                goToPage(1);
                            };
                            firstEl.appendChild(firstLink);
                            pagi.appendChild(firstEl);
                            if (startPage > 2) {
                                const ellipsisEl = document.createElement('li');
                                ellipsisEl.className = 'page-item disabled';
                                const ellipsisSpan = document.createElement('span');
                                ellipsisSpan.className = 'page-link';
                                ellipsisSpan.textContent = '...';
                                ellipsisEl.appendChild(ellipsisSpan);
                                pagi.appendChild(ellipsisEl);
                            }
                        }
                        // Page numbers
                        for (let i = startPage; i <= endPage; i++) {
                            const pageEl = document.createElement('li');
                            pageEl.className = 'page-item' + (i === currentPage ? ' active' : '');
                            const pageLink = document.createElement('a');
                            pageLink.className = 'page-link';
                            pageLink.href = '#';
                            pageLink.textContent = i;
                            pageLink.onclick = (e) => {
                                e.preventDefault();
                                goToPage(i);
                            };
                            pageEl.appendChild(pageLink);
                            pagi.appendChild(pageEl);
                        }
                        // Last page + ellipsis
                        if (endPage < totalPages) {
                            if (endPage < totalPages - 1) {
                                const ellipsisEl = document.createElement('li');
                                ellipsisEl.className = 'page-item disabled';
                                const ellipsisSpan = document.createElement('span');
                                ellipsisSpan.className = 'page-link';
                                ellipsisSpan.textContent = '...';
                                ellipsisEl.appendChild(ellipsisSpan);
                                pagi.appendChild(ellipsisEl);
                            }
                            const lastEl = document.createElement('li');
                            lastEl.className = 'page-item' + (currentPage === totalPages ? ' active' : '');
                            const lastLink = document.createElement('a');
                            lastLink.className = 'page-link';
                            lastLink.href = '#';
                            lastLink.textContent = totalPages;
                            lastLink.onclick = (e) => {
                                e.preventDefault();
                                goToPage(totalPages);
                            };
                            lastEl.appendChild(lastLink);
                            pagi.appendChild(lastEl);
                        }
                        // Next button
                        const nextEl = document.createElement('li');
                        nextEl.className = 'page-item' + (currentPage === totalPages ? ' disabled' : '');
                        const nextLink = document.createElement('a');
                        nextLink.className = 'page-link';
                        nextLink.classList.add('icon')
                        nextLink.href = '#';
                        nextLink.innerHTML = '&#xF280;'; // Bootstrap icon chevron-right
                        nextLink.onclick = (e) => {
                            e.preventDefault();
                            if (currentPage < totalPages) goToPage(currentPage + 1);
                        };
                        nextEl.appendChild(nextLink);
                        pagi.appendChild(nextEl);
                    });
                }

                renderPaginations();
                // Responsive: re-render pagination on resize
                if (!window._paginationResizeHandler) {
                    window._paginationResizeHandler = () => {
                        // Use the persistent searchState instead of local variables
                        if (state.total >= 0 && NUM_PER_PAGE > 0) {
                            renderPaginations();
                        }
                    };
                    window.addEventListener('resize', window._paginationResizeHandler);
                }

                // Helper to change page and re-render
                function goToPage(page) {
                    // Update the global page variable before rendering
                    currentPageGlobal = page;
                    renderContent(page);
                }

                let count = 0;
                for (let rowIndex = 0; rowIndex < (NUM_PER_PAGE / NUM_PER_ROW); rowIndex++) {
                    if (count >= total || count >= menuData.length) break;
                    const rowTemplate = `
                        <div class="row row-cols-1 row-cols-lg-3 align-items-stretch g-3 py-3 py-lg-4">
                        </div>
                    `
                    cardContainer.insertAdjacentHTML('afterbegin', rowTemplate);
                    const row = cardContainer.firstElementChild;
                    for (let itemIndex = 0; itemIndex < NUM_PER_ROW; itemIndex++) {
                        if (count >= total || count >= menuData.length) break;
                        const menuItem = menuData[count] || {};
                        const imgSrc = menuItem['img'] ? `data:image/webp;base64,${menuItem['img']}` : '../assets/img/meal.jpeg';

                        const heartIcon = menuItem.is_favorite ?
                            `<i class="bi bi-heart-fill display-7" data-bs-toggle="popover" data-bs-content="Undo Fav"></i>
                             <i class="bi bi-heart display-7 d-none" data-bs-toggle="popover" data-bs-content="Fav"></i>` :
                            `<i class="bi bi-heart display-7" data-bs-toggle="popover" data-bs-content="Fav"></i>
                             <i class="bi bi-heart-fill display-7 d-none" data-bs-toggle="popover" data-bs-content="Undo Fav"></i>`;


                        const cardTemplate = `
                            <div class="col">
                                <div data-id="${menuItem['id'] || ''}" class="card card-cover h-100 overflow-hidden text-bg-dark rounded-4 shadow-lg position-relative">
                                    <img src="${imgSrc}" class="card-img-top" alt="menu-id-${menuItem['id'] || 'unknown'}" style="object-fit:cover;max-height:180px;">
                                    <div class="d-flex flex-column justify-content-between h-100 p-4 p-lg-5 pb-3 text-white text-shadow-1">
                                        <ul class="d-flex justify-content-between align-items-center list-unstyled mb-0">
                                            <li class="d-flex align-items-center gap-1">
                                                <i class="bi bi-person-circle fs-6"></i>
                                                <small class="fs-6">${menuItem['author'] ? menuItem['author'] : 'n.v.t'}</small>
                                            </li>
                                            <li>
                                                <a href="detail.php?id=${menuItem['id'] || ''}" class="text-decoration-none">
                                                    <i class="bi bi-arrow-right-circle display-5 text-secondary-emphasis"></i>
                                                </a>
                                            </li>
                                        </ul>
                                        <div class="name-container py-3">
                                            <h3 class="fs-1 text-truncate-2 fw-bold">${menuItem['name'] || 'Geen naam'}</h3>
                                        </div>
                                        <div class="card-bottom d-flex flex-column gap-2 mb-3 mb-lg-4">
                                            <ul class="d-flex list-unstyled mb-1">
                                                <li class="d-flex align-items-center me-3 gap-1">
                                                    <i class="bi bi-egg-fried"></i>
                                                    <div class="genre-container d-flex flex-wrap gap-1"></div>
                                                </li>
                                                <li class="d-flex align-items-center gap-1">
                                                    <i class="bi bi-clock-history"></i>
                                                    <small>${menuItem['prepare_time'] ? menuItem['prepare_time'] + ' min' : 'n.t.v'}</small></li>
                                            </ul>
                                        </div>
                                        <ul class="toolbar d-flex flex-row justify-content-end align-items-center list-unstyled mb-0">
                                            <li style="cursor:pointer;" class="position-relative z-2">
                                                ${heartIcon}
                                            </li>
                                        </ul>
                                        <a href="detail.php?id=${menuItem['id'] || ''}" class="stretched-link" aria-label="View details for ${menuItem['name'] || 'this recipe'}"></a>
                                    </div>
                                </div>
                            </div>
                        `
                        row.insertAdjacentHTML('beforeend', cardTemplate);
                        const card = row.lastElementChild;
                        const genreContainer = card.querySelector('.genre-container');
                        let genreContainerTemplate = '';
                        // Handle genres
                        if (menuItem['genre'] && menuItem['genre'].length > 0) {
                            for (const key in menuItem['genre']) {
                                genreContainerTemplate += `<small class="badge bg-light-subtle border border-light-subtle text-light-emphasis rounded-pill">${menuItem['genre'][key]}</small>`;
                            }
                        } else {
                            genreContainerTemplate += `<small>n.v.t</small>`;
                        }
                        genreContainer.insertAdjacentHTML('afterbegin', genreContainerTemplate);

                        // Handle tags
                        if (menuItem['tag'] && menuItem['tag'].length > 0) {
                            const cardBottom = card.querySelector('.card-bottom');
                            const tagContainerTemplate = `
                                <div class="tag-container d-flex flex-wrap w-100">
                                </div>
                            `;
                            cardBottom.insertAdjacentHTML('beforeend', tagContainerTemplate);
                            const tagContainer = cardBottom.querySelector('.tag-container');
                            let tagListTemplate = ``;
                            for (const key in menuItem['tag']) {
                                tagListTemplate += `<small class="badge border border-dark-subtle text-dark-emphasis rounded-pill"># ${menuItem['tag'][key]}</small>`;
                            }
                            tagContainer.insertAdjacentHTML('afterbegin', tagListTemplate);
                        }

                        count++;
                    }
                }

                // Attach favorite listeners after rendering cards
                attachFavoriteListeners();
            });
    }
});
