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
    let fromMeFilterActive = false; // track 'from me' filter

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

    // Function to fetch filters with retry logic
    function fetchFiltersWithRetry(retryCount = 0, maxRetries = 2) {
        fetch('../controllers/menu.php?action=getFilter')
            .then(res => {
                if (!res.ok) {
                    throw new Error(`Server error ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('Error fetching filters:', data.error);
                    return; // Skip rendering if there's an error
                }
                renderFilters(data);
                addFavoritesFilter();
                addFromMeFilter();

                // If this was a successful retry, trigger content rendering again
                if (retryCount > 0) {
                    console.log(`Filter fetch succeeded after ${retryCount} retries. Re-rendering content...`);
                    renderContent(currentPageGlobal);
                }
            })
            .catch(err => {
                console.error(`Failed to load filters (attempt ${retryCount + 1}/${maxRetries + 1}):`, err);

                // Retry logic
                if (retryCount < maxRetries) {
                    console.log(`Retrying filter fetch in 500ms... (${retryCount + 1}/${maxRetries})`);
                    setTimeout(() => {
                        fetchFiltersWithRetry(retryCount + 1, maxRetries);
                    }, 500); // Wait 500ms before retrying
                } else {
                    console.error('Maximum retry attempts reached. Continuing without filters.');
                    // Continue with the app even if filters fail to load after all retries
                }
            });
    }

    // Start the fetch process with retry capability
    fetchFiltersWithRetry();

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
    /**
     * Adds a 'Favorites' filter button for logged-in users to filter content by favorites.
     * @returns {void}
     */
    function addFavoritesFilter() {
        const isLoggedIn = !!sessionStorage.getItem('userInfo');
        if (!isLoggedIn) return; // Only show to logged in users

        // const filterContainer = document.getElementById('filter-container');
        const filterToolbarLeft = document.querySelector('.filter-toolbar > .left');
        if (!filterToolbarLeft) return;

        const favFilterTemplate = `
            <div id="filter-favorites" class="favorite-filter">
                <button id="favorites-toggle" class="btn btn-outline-danger d-flex gap-1 fw-bold">
                    <i class="bi bi-heart me-1"></i> Fav
                </button>
            </div>
        `;

        // Add to beginning of filters
        filterToolbarLeft.insertAdjacentHTML('afterbegin', favFilterTemplate);

        // Add toggle behavior
        const favToggle = document.getElementById('favorites-toggle');
        favToggle.addEventListener('click', function () {
            favFilterActive = !favFilterActive;

            if (favFilterActive) {
                this.classList.remove('btn-outline-danger');
                this.classList.add('btn-danger');
                this.querySelector('i').classList.remove('bi-heart');
                this.querySelector('i').classList.add('bi-heart-fill');
                // deactivate 'from me' when favorites activated
                fromMeFilterActive = false;
                const fromBtn = document.getElementById('from-me-toggle');
                if (fromBtn) {
                    fromBtn.classList.remove('btn-primary');
                    fromBtn.classList.add('btn-outline-primary');
                    const icon = fromBtn.querySelector('i');
                    icon.classList.replace('bi-person-fill', 'bi-person');
                }
            } else {
                this.classList.add('btn-outline-danger');
                this.classList.remove('btn-danger');
                this.querySelector('i').classList.add('bi-heart');
                this.querySelector('i').classList.remove('bi-heart-fill');
            }
            renderContent();
        });
    }

    /**
     * Attaches click event listeners to favorite icons within cards to toggle favorite status via API.
     * @returns {void}
     */
    function attachFavoriteListeners() {
        // Only attach if user is logged in
        const userInfo = sessionStorage.getItem('userInfo');
        if (!userInfo) return;

        const user = JSON.parse(userInfo);
        const heartIcons = document.querySelectorAll('.toolbar .fav-btn');

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
                fetch('../controllers/menu.php', {
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

    /**
     * Renders filter dropdowns based on server-provided filter data and sets up selection behavior.
     * @param {Object.<string, Array>} filters - Mapping of filter categories to arrays of filter items.
     * @returns {void}
     */
    function renderFilters(filters) {
        const filterMap = {
            "genre": "Genre",
            "tag": "Tag",
            "prepareTimeGroup": "Voorbereid",
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
                    <button class="btn btn-secondary dropdown-toggle fw-bold" data-bs-toggle="dropdown"
                            id="title-${filterName}" aria-expanded="false">${filterDisplayName}
                    </button>
                    <div class="dropdown-menu position-absolute border-0 pt-0 mx-0 rounded-3 shadow overflow-x-auto overflow-y-auto w-100"
                         style="min-width: 250px; max-height: 400px"
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

    /**
     * Updates the appearance of a filter button based on the number of selected items.
     * @param {string} filterName - The name of the filter category.
     * @returns {void}
     */
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

    /**
     * Initializes the apply and clear filter action buttons to control filter state and content refresh.
     * @returns {void}
     */
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

    /**
     * Fetches and displays content cards based on current page, selected filters, and search text.
     * @param {number} [currentPage=1] - The page number to render.
     * @returns {void}
     */
    function renderContent(currentPage = 1, retryCount = 0, maxRetries = 2) {
        let selectedFilters = {};

        if (favFilterActive) {
            let userInfo = sessionStorage.getItem('userInfo');
            if (userInfo) {
                userInfo = JSON.parse(userInfo);
                selectedFilters['fav'] = userInfo.id;
            }
        }
        if (fromMeFilterActive) {
            let userInfo = sessionStorage.getItem('userInfo');
            if (userInfo) {
                userInfo = JSON.parse(userInfo);
                // filter by author (menus created by current user)
                selectedFilters['author'] = [userInfo.author_id];
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
        fetch(`../controllers/menu.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(params)
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`Server error ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                // console.log(data);
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
                        const totalPages = Math.ceil(state.total / NUM_PER_PAGE);
                        if (totalPages <= 1) {
                            pagi.classList.remove('d-flex');
                            pagi.classList.add('d-none');
                        } else {
                            pagi.classList.remove('d-none');
                            pagi.classList.add('d-flex');
                        }

                        // Create a temporary page-item to measure width
                        pagi.innerHTML = '';
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
                    cardContainer.insertAdjacentHTML('beforeend', rowTemplate);
                    const row = cardContainer.lastElementChild;
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
                                        <div class="flex-grow-1">
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
                                                        <small>${menuItem['prepareTime'] ? menuItem['prepareTime'] + ' min' : 'n.t.v'}</small></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <ul class="toolbar d-flex flex-row gap-3 justify-content-end align-items-center list-unstyled mb-0">
                                            <li style="cursor:pointer;" class="fav-btn position-relative z-2">
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
                        // Add delete button for admin or author
                        const currentUserInfo = sessionStorage.getItem('userInfo');
                        if (currentUserInfo) {
                            const currentUser = JSON.parse(currentUserInfo);
                            if (currentUser.role === 'admin' || currentUser.author_id === menuItem['author_id']) {
                                const deleteLi = document.createElement('li');
                                deleteLi.style.cursor = 'pointer';
                                deleteLi.classList.add('delete-btn', 'position-relative', 'z-2');
                                deleteLi.innerHTML = '<i class="bi bi-trash display-7"></i>';
                                const toolbar = card.querySelector('.toolbar');
                                toolbar.prepend(deleteLi);
                                // Add edit button for author only
                                if (currentUser.author_id === menuItem['author_id']) {
                                    const editLi = document.createElement('li');
                                    editLi.style.cursor = 'pointer';
                                    editLi.classList.add('edit-btn', 'position-relative', 'z-2');
                                    editLi.innerHTML = '<i class="bi bi-pencil display-7"></i>';
                                    editLi.addEventListener('click', (e) => {
                                        e.preventDefault(); e.stopPropagation();
                                        window.location.href = `upsert.php?id=${menuItem['id']}`;
                                    });
                                    toolbar.prepend(editLi);
                                }
                            }
                        }
                         count++;
                    }
                }

                // Attach favorite and delete listeners after rendering cards
                attachFavoriteListeners();
                attachDeleteListeners();

            }); // end data.then
        }

    /**
     * Attaches click event listeners to delete buttons in cards to remove recipes via API.
     */
    function attachDeleteListeners() {
        const userInfo = sessionStorage.getItem('userInfo');
        if (!userInfo) return;

        // Initialize the delete confirmation modal
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        // Create a variable to store the current card being deleted
        let currentCardToDelete = null;
        let currentMenuName = '';

        document.querySelectorAll('.toolbar .delete-btn').forEach(btn => {
            if (btn.dataset.hasListener === 'true') return;
            btn.dataset.hasListener = 'true';

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Store references to the card elements
                const card = this.closest('.card');
                currentCardToDelete = card.closest('.col');
                const menuId = card.dataset.id;

                // Get the recipe name and set it in the modal
                currentMenuName = card.querySelector('.name-container h3').textContent;
                document.getElementById('recipe-name-to-delete').textContent = currentMenuName;

                // Show the confirmation modal
                deleteModal.show();
            });
        });

        // Handle the confirm delete button click
        if (confirmDeleteBtn && !confirmDeleteBtn.dataset.hasListener) {
            confirmDeleteBtn.dataset.hasListener = 'true';

            confirmDeleteBtn.addEventListener('click', function() {
                if (!currentCardToDelete) {
                    deleteModal.hide();
                    return;
                }

                // Show loading state
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verwijderen...';
                this.disabled = true;

                const card = currentCardToDelete.querySelector('.card');
                const menuId = card.dataset.id;
                const user = JSON.parse(userInfo);

                const fd = new FormData();
                fd.append('id', menuId);
                fd.append('user_id', user.id);
                fd.append('role', user.role || '');

                fetch('../controllers/menu.php?action=deleteRecipe', {
                    method: 'POST',
                    body: fd
                })
                .then(res => res.json())
                .then(data => {
                    // Reset button state
                    this.innerHTML = 'Verwijderen';
                    this.disabled = false;

                    if (data.success) {
                        // Remove the card from the UI
                        if (currentCardToDelete) {
                            currentCardToDelete.remove();
                        }
                        // Hide the modal
                        deleteModal.hide();
                    } else {
                        // Hide the modal
                        deleteModal.hide();
                        // Show error alert
                        alert('Verwijderen mislukt: ' + (data.message || 'Onbekende fout.'));
                    }
                })
                .catch(err => {
                    console.error('Error deleting recipe:', err);
                    // Reset button state
                    this.innerHTML = 'Verwijderen';
                    this.disabled = false;
                    // Hide the modal
                    deleteModal.hide();
                    // Show error alert
                    alert('Verwijderen mislukt door een netwerkfout.');
                });
            });
        }
    }

    /**
     * Adds a 'From Me' filter to show only current user's recipes.
     */
    function addFromMeFilter() {
        const userInfo = sessionStorage.getItem('userInfo');
        if (!userInfo) return;
        const container = document.querySelector('.filter-toolbar .left');
        if (!container) return;
        const html = `
            <div id="filter-from-me" class="from-me-filter">
                <button id="from-me-toggle" class="btn btn-outline-primary d-flex gap-1 fw-bold">
                    <i class="bi bi-person me-1"></i> Van mij
                </button>
            </div>
        `;
        container.insertAdjacentHTML('afterbegin', html);
        const btn = document.getElementById('from-me-toggle');
        btn.addEventListener('click', function() {
            fromMeFilterActive = !fromMeFilterActive;
            if (fromMeFilterActive) {
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
                this.querySelector('i').classList.replace('bi-person', 'bi-person-fill');
                // deactivate favorites when 'from me' activated
                favFilterActive = false;
                const favBtn = document.getElementById('favorites-toggle');
                if (favBtn) {
                    favBtn.classList.remove('btn-danger');
                    favBtn.classList.add('btn-outline-danger');
                    const icon = favBtn.querySelector('i');
                    icon.classList.replace('bi-heart-fill', 'bi-heart');
                }
            } else {
                this.classList.add('btn-outline-primary');
                this.classList.remove('btn-primary');
                this.querySelector('i').classList.replace('bi-person-fill', 'bi-person');
            }
            renderContent();
        });
    }
});
