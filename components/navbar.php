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
            <a class="navbar-brand m-0" href="../pages/index.php">WEJV</a>

            <!-- Right: Mobile search and account -->
            <div class="d-flex">
                <a href="#search-section" class="btn btn-outline-light me-2">
                    <i class="bi bi-search"></i>
                </a>
                <button id="mobile-account-button" class="btn btn-outline-light rounded-circle" type="button"
                        data-bs-toggle="modal" data-bs-target="#loginModal">
                    <i class="bi bi-person"></i>
                </button>
            </div>
        </div>

        <!-- Desktop view structure - hidden on mobile -->
        <div class="d-none d-lg-flex align-items-center w-100">
            <!-- Left: Brand logo -->
            <a class="navbar-brand" href="#">WEJV</a>

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
                <li class="nav-item px-2"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item px-2"><a class="nav-link" href="upsert.php">Toevoeg</a></li>
            </ul>

            <!-- Account button -->
            <div class="account-container ms-2">
                <button id="account-button" class="btn btn-outline-light rounded-circle" type="button"
                        data-bs-toggle="modal" data-bs-target="#loginModal">
                    <i class="bi bi-person"></i>
                </button>
            </div>
        </div>

        <!-- Offcanvas menu (mobile only) -->
        <div class="offcanvas offcanvas-start bg-dark text-white d-lg-none" tabindex="-1" id="navbarOffcanvas"
             aria-labelledby="navbarOffcanvasLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="navbarOffcanvasLabel">WEJV Menu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="upsert.php">Toevoeg</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

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
                if (!currentPage.includes('index.php')) {
                    // Store the search query in localStorage so discover.js can use it
                    localStorage.setItem('navbarSearchQuery', searchQuery);

                    // Redirect to the discover page
                    window.location.href = 'index.php';
                } else {
                    // If we're already on index.php, the event handler in discover.js will handle it
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
                if (!currentPage.includes('index.php')) {
                    e.preventDefault();
                    window.location.href = 'index.php';
                }
                // If we're on index.php, let the default handler in discover.js handle it
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Bootstrap dropdowns
        const accountButton = document.getElementById('account-button');
        const mobileAccountButton = document.getElementById('mobile-account-button');

        // Check if user is logged in
        const checkLoginStatus = function () {
            // Get user info from session storage
            const userInfo = JSON.parse(sessionStorage.getItem('userInfo'));

            if (userInfo) {
                // User is logged in
                updateUIForLoggedInUser(userInfo);
            } else {
                // User is not logged in
                updateUIForGuest();
            }
        };

        // Update UI for logged in user
        const updateUIForLoggedInUser = function (userInfo) {
            // Update account buttons
            [accountButton, mobileAccountButton].forEach(button => {
                if (button) {
                    // Remove modal trigger
                    button.removeAttribute('data-bs-toggle');
                    button.removeAttribute('data-bs-target');

                    // Set up dropdown for account menu instead of popover
                    button.setAttribute('data-bs-toggle', 'dropdown');
                    button.setAttribute('aria-expanded', 'false');

                    // Create dropdown menu for user account
                    let dropdownMenu = document.createElement('div');
                    dropdownMenu.className = 'dropdown-menu dropdown-menu-end bg-dark border-secondary';
                    dropdownMenu.setAttribute('aria-labelledby', button.id);

                    dropdownMenu.innerHTML = `
                    <div class="px-3 py-2 d-flex align-items-center">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;">
                            <span class="text-white">${userInfo.firstName.charAt(0)}${userInfo.lastName.charAt(0)}</span>
                        </div>
                        <div>
                            <strong class="text-white">${userInfo.firstName} ${userInfo.lastName}</strong><br>
                            <small class="text-muted">${userInfo.name}</small>
                        </div>
                    </div>
                    <hr class="dropdown-divider border-secondary my-2">
                    <button class="dropdown-item text-white-50 bg-dark" id="logout-button" type="button">Logout</button>
                `;

                    // Add dropdown menu after the button
                    let buttonParent = button.parentNode;
                    buttonParent.classList.add('dropdown'); // Add dropdown class to parent
                    buttonParent.appendChild(dropdownMenu);

                    // Change button appearance
                    button.classList.remove('btn-outline-light');
                    button.classList.add('btn-primary');
                    button.innerHTML = `<span>${userInfo.firstName.charAt(0)}${userInfo.lastName.charAt(0)}</span>`;
                }
            });

            // Listen for logout button clicks
            document.querySelectorAll('#logout-button').forEach(button => {
                button.addEventListener('click', function () {
                    // Handle logout
                    sessionStorage.removeItem('userInfo');

                    // Remove dropdown menus
                    document.querySelectorAll('.account-container .dropdown-menu, .d-lg-none .dropdown-menu').forEach(menu => {
                        if (menu) menu.remove();
                    });

                    // Update UI
                    checkLoginStatus();
                });
            });
        };

        // Update UI for guest user
        const updateUIForGuest = function () {
            // Update account buttons
            [accountButton, mobileAccountButton].forEach(button => {
                if (button) {
                    // Reset to login modal trigger
                    button.setAttribute('data-bs-toggle', 'modal');
                    button.setAttribute('data-bs-target', '#loginModal');

                    // Remove dropdown attributes if they exist
                    button.removeAttribute('aria-expanded');

                    // Remove any dropdown menus
                    const dropdownMenu = button.parentNode.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        dropdownMenu.remove();
                    }

                    // Reset button appearance
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-outline-light');
                    button.innerHTML = '<i class="bi bi-person"></i>';
                }
            });
        };

        // Handle login form submission
        const loginSubmitButton = document.getElementById('login-submit');
        if (loginSubmitButton) {
            loginSubmitButton.addEventListener('click', function () {
                const loginForm = document.getElementById('login-form');
                const errorElement = document.getElementById('login-error');

                // Reset validation state
                loginForm.classList.remove('was-validated');
                errorElement.classList.add('d-none');

                // Form validation
                if (!loginForm.checkValidity()) {
                    loginForm.classList.add('was-validated');
                    return;
                }

                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;

                // AJAX call to login API
                fetch('../controllers/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'login',
                        username: username,
                        password: password
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Store user info in session storage
                            sessionStorage.setItem('userInfo', JSON.stringify(data.user));
                            document.body.dataset.loggedIn = 'true';
                            document.body.dataset.userId = data.user.id;

                            // Close the modal
                            const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                            loginModal.hide();
                            window.location.reload();

                            // Update UI
                            checkLoginStatus();
                        } else {
                            // Show error message
                            errorElement.textContent = data.message || 'Invalid username or password';
                            errorElement.classList.remove('d-none');
                        }
                    })
                    .catch(error => {
                        console.error('Login error:', error);
                        errorElement.textContent = 'An error occurred during login. Please try again.';
                        errorElement.classList.remove('d-none');
                    });
            });
        }

        // Handle registration form submission
        const registerSubmitButton = document.getElementById('register-submit');
        if (registerSubmitButton) {
            registerSubmitButton.addEventListener('click', function () {
                const registerForm = document.getElementById('register-form');
                const errorElement = document.getElementById('register-error');
                const successElement = document.getElementById('register-success');

                // Reset messages
                errorElement.classList.add('d-none');
                successElement.classList.add('d-none');
                registerForm.classList.remove('was-validated');

                // Form validation
                if (!registerForm.checkValidity()) {
                    registerForm.classList.add('was-validated');
                    return;
                }

                // Password matching validation
                const password = document.getElementById('reg_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (password !== confirmPassword) {
                    document.getElementById('confirm_password').setCustomValidity('Passwords do not match');
                    registerForm.classList.add('was-validated');
                    return;
                } else {
                    document.getElementById('confirm_password').setCustomValidity('');
                }

                // Get form values
                const firstName = document.getElementById('first_name').value;
                const lastName = document.getElementById('last_name').value;
                const username = document.getElementById('reg_username').value;
                const email = document.getElementById('email').value;

                // AJAX call to register API
                fetch('../controllers/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'register',
                        first_name: firstName,
                        last_name: lastName,
                        username: username,
                        email: email,
                        password: password
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            successElement.textContent = 'Registration successful! You can now log in.';
                            successElement.classList.remove('d-none');

                            // Reset form
                            registerForm.reset();

                            // Switch back to login modal after a delay
                            setTimeout(() => {
                                const registerModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                                registerModal.hide();
                                const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                                loginModal.show();
                            }, 2000);
                        } else {
                            // Show error message
                            errorElement.textContent = data.message || 'Registration failed. Please try again.';
                            errorElement.classList.remove('d-none');
                        }
                    })
                    .catch(error => {
                        console.error('Registration error:', error);
                        errorElement.textContent = 'An error occurred during registration. Please try again.';
                        errorElement.classList.remove('d-none');
                    });
            });
        }

        // Check login status on page load
        checkLoginStatus();
    });
</script>
