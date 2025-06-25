<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 container">
    <div class="container-fluid py-1 py-lg-2">
        <!-- Mobile view structure -->
        <div class="d-flex align-items-center justify-content-between w-100 d-lg-none">
            <!-- Left: Mobile menu toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarOffcanvas"
                    aria-controls="navbarOffcanvas" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Center: Brand logo -->
            <a class="navbar-brand m-0" href="../pages/home.php">WEJV</a>

            <!-- Right: Mobile search and account -->
            <div class="d-flex gap-2 align-items-center">
                <a href="#search-section"
                   class="d-flex justify-content-center rounded-circle btn btn-outline-light ratio-1x1 h-100 w-auto border-0"
                   style="aspect-ratio: 1/1;">
                    <i class="bi bi-search"></i>
                </a>
                <button id="mobile-account-button" class="btn btn-outline-light rounded-circle ratio-1x1 h-100 w-auto" type="button"
                        style="aspect-ratio: 1/1;"
                        data-bs-toggle="modal" data-bs-target="#loginModal">
                    <i class="bi bi-person"></i>
                </button>
            </div>
        </div>

        <!-- Desktop view structure - hidden on mobile -->
        <div class="d-none d-lg-flex justify-content-between align-items-center w-100">
            <div class="right d-flex justify-content-start align-items-center flex-grow-1">
                <!-- Left: Brand logo -->
                <a class="navbar-brand" href="../pages/home.php">WEJV</a>

                <!-- Center: Search form -->
                <div class="flex-grow-1 mx-3" style="max-width: 500px;">
                    <form class="d-flex w-100" role="search" id="navbar-search-form">
                        <input id="navbar-search-box" class="form-control me-2" type="search" placeholder="Zoek..."
                               aria-label="Zoek">
                        <button id="navbar-search-button" class="btn btn-primary" type="submit">Zoeken</button>
                    </form>
                </div>

                <!-- Right: Navigation links -->
                <ul class="navbar-nav flex-row me-2">
                    <li class="nav-item px-2"><a class="nav-link" href="../pages/home.php">Home</a></li>
                    <li class="nav-item px-2"><a class="nav-link" href="../pages/upsert.php">Toevoeg</a></li>
                </ul>

            </div>
            <div class="right d-flex align-items-center">
                <!-- Account button -->
                <div class="account-container ms-2">
                    <button id="account-button" class="btn btn-outline-light rounded-circle w-auto h-100" type="button"
                            style="aspect-ratio: 1/1;"
                            data-bs-toggle="modal" data-bs-target="#loginModal">
                        <i class="bi bi-person"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Offcanvas for mobile navigation -->
<div class="offcanvas offcanvas-start" data-bs-backdrop="true" data-bs-scroll="false" tabindex="-1" id="navbarOffcanvas"
     aria-labelledby="navbarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="navbarOffcanvasLabel">WEJV</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <!-- Navigation links -->
        <ul class="navbar-nav list-unstyled">
            <li class="nav-item"><a class="nav-link" href="../pages/home.php">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="../pages/upsert.php">Toevoeg</a></li>
        </ul>
    </div>
</div>

<!-- Add search functionality that works across all pages -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Get the navbar search form
        const navbarSearchForm = document.getElementById('navbar-search-form');
        const navbarSearchBox = document.getElementById('navbar-search-box');

        // Handle the navbar search form submission
        if (navbarSearchForm) {
            navbarSearchForm.addEventListener('submit', function (e) {
                e.preventDefault();

                // Get the search query
                const searchQuery = navbarSearchBox.value.trim();

                // If we're not on the discover page, redirect to it with the search query
                const currentPage = window.location.pathname;
                if (!currentPage.includes('home.php')) {
                    // Store the search query in localStorage so discover.js can use it
                    localStorage.setItem('navbarSearchQuery', searchQuery);

                    // Redirect to the discover page
                    window.location.href = 'home.php';
                } else {
                    // If we're already on home.php, the event handler in discover.js will handle it
                    // But we need to make sure the mobile search box is updated too
                    const mobileSearchBox = document.getElementById('search-box');
                    if (mobileSearchBox) {
                        mobileSearchBox.value = searchQuery;
                    }
                }
            });
        }

        // Handle mobile search redirect for non-discover pages
        const mobileSearchButton = document.querySelector('a[href="#search-section"]');
        if (mobileSearchButton) {
            mobileSearchButton.addEventListener('click', function (e) {
                const currentPage = window.location.pathname;
                if (!currentPage.includes('home.php')) {
                    e.preventDefault();
                    window.location.href = 'home.php';
                }
                // If we're on home.php, let the default handler in discover.js handle it
            });
        }
    });
</script>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="loginModalLabel">Login</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="login-form" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="username"
                               required>
                        <div class="invalid-feedback">Please enter your username or email</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control bg-dark text-white border-secondary" id="password"
                               required>
                        <div class="invalid-feedback">Please enter your password</div>
                    </div>
                    <div id="login-error" class="alert alert-danger d-none" role="alert">
                        Invalid username or password
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-toggle="modal"
                        data-bs-target="#registerModal" data-bs-dismiss="modal">Register
                </button>
                <button type="button" class="btn btn-primary" id="login-submit">Login</button>
            </div>
        </div>
    </div>
</div>

<!-- Registration Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="registerModalLabel">Register</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="register-form" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="first_name"
                                   required>
                            <div class="invalid-feedback">Please enter your first name</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="last_name"
                                   required>
                            <div class="invalid-feedback">Please enter your last name</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reg_username" class="form-label">Username</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="reg_username"
                               required>
                        <div class="invalid-feedback">Please choose a username</div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control bg-dark text-white border-secondary" id="email"
                               required>
                        <div class="invalid-feedback">Please enter a valid email</div>
                    </div>
                    <div class="mb-3">
                        <label for="reg_password" class="form-label">Password</label>
                        <input type="password" class="form-control bg-dark text-white border-secondary"
                               id="reg_password" required>
                        <div class="invalid-feedback">Please enter a password</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control bg-dark text-white border-secondary"
                               id="confirm_password" required>
                        <div class="invalid-feedback">Passwords do not match</div>
                    </div>
                    <div id="register-error" class="alert alert-danger d-none" role="alert">
                        Registration failed
                    </div>
                    <div id="register-success" class="alert alert-success d-none" role="alert">
                        Registration successful! You can now log in.
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#loginModal"
                        data-bs-dismiss="modal">Back to Login
                </button>
                <button type="button" class="btn btn-primary" id="register-submit">Register</button>
            </div>
        </div>
    </div>
</div>

<!-- User Account JS -->
<script src="../js/user.js"></script>
