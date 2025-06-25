/**
 * User authentication and UI management
 */
document.addEventListener('DOMContentLoaded', function () {
    // Initialize elements
    const accountButton = document.getElementById('account-button');
    const mobileAccountButton = document.getElementById('mobile-account-button');
    const loginSubmitButton = document.getElementById('login-submit');
    const registerSubmitButton = document.getElementById('register-submit');

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

                // Reload page to reset all UI elements
                window.location.reload();
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
