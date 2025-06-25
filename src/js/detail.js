document.addEventListener('DOMContentLoaded', function() {
    // Get the favorite button
    const favoriteButton = document.querySelector('.fav.btn');
    if (!favoriteButton) return;

    // Get menu ID from the URL
    const urlParams = new URLSearchParams(window.location.search);
    const menuId = urlParams.get('id');
    if (!menuId) return;

    // Check if user is logged in
    const userInfo = sessionStorage.getItem('userInfo');
    if (!userInfo) {
        // User not logged in, make button redirect to login modal when clicked
        favoriteButton.addEventListener('click', function(e) {
            e.preventDefault();
            // Try to get the login modal and show it
            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            if (loginModal) {
                loginModal.show();
            }
        });
        return;
    }

    // User is logged in
    const user = JSON.parse(userInfo);

    /**
     * Initializes favorite button functionality on the detail page after DOM is loaded.
     * Sets up login redirect for unauthenticated users and toggle behavior for favorites.
     * @returns {void}
     */

    // Check if this menu is already a favorite
    checkFavoriteStatus();

    // Add click event to toggle favorite
    favoriteButton.addEventListener('click', function(e) {
        e.preventDefault();

        // Toggle icon and button state
        const heartIcon = this.querySelector('i.bi');
        const isFilled = heartIcon.classList.contains('bi-heart-fill');

        // Toggle the heart icon appearance
        if (isFilled) {
            heartIcon.classList.remove('bi-heart-fill');
            heartIcon.classList.add('bi-heart');
            this.classList.remove('btn-primary');
            this.classList.add('btn-secondary');
        } else {
            heartIcon.classList.remove('bi-heart');
            heartIcon.classList.add('bi-heart-fill');
            this.classList.remove('btn-secondary');
            this.classList.add('btn-primary');
        }

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
            if (!data.success) {
                // If the toggle failed, revert the UI changes
                if (isFilled) {
                    heartIcon.classList.remove('bi-heart');
                    heartIcon.classList.add('bi-heart-fill');
                    favoriteButton.classList.remove('btn-primary');
                    favoriteButton.classList.add('btn-danger');
                } else {
                    heartIcon.classList.remove('bi-heart-fill');
                    heartIcon.classList.add('bi-heart');
                    favoriteButton.classList.remove('btn-danger');
                    favoriteButton.classList.add('btn-primary');
                }
                console.error('Failed to toggle favorite');
            }
        })
        .catch(err => {
            console.error('Error toggling favorite:', err);
            // Revert UI changes on error
            if (isFilled) {
                heartIcon.classList.remove('bi-heart');
                heartIcon.classList.add('bi-heart-fill');
                favoriteButton.classList.remove('btn-secondary');
                favoriteButton.classList.add('btn-danger');
            } else {
                heartIcon.classList.remove('bi-heart-fill');
                heartIcon.classList.add('bi-heart');
                favoriteButton.classList.remove('btn-secondary');
                favoriteButton.classList.add('btn-primary');
            }
        });
    });

    /**
     * Checks and updates the favorite status of the specified menu for the current user.
     * Sends a request to retrieve the initial favorite state and updates the button UI accordingly.
     * @returns {void}
     */
    function checkFavoriteStatus() {
        fetch('../controllers/menu.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'checkFavorite',
                userId: user.id,
                menuId: menuId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.isFavorite) {
                // If it's already a favorite, update the UI
                const heartIcon = favoriteButton.querySelector('i.bi');
                heartIcon.classList.remove('bi-heart');
                heartIcon.classList.add('bi-heart-fill');
                favoriteButton.classList.remove('btn-secondary');
                favoriteButton.classList.add('btn-primary');
            }
        })
        .catch(err => {
            console.error('Error checking favorite status:', err);
        });
    }

    // --- Edit/Delete Button Logic ---
    const editBtn = document.getElementById('edit-recipe-btn');
    const deleteBtn = document.getElementById('delete-recipe-btn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            // Get menu ID from the URL
            const urlParams = new URLSearchParams(window.location.search);
            const menuId = urlParams.get('id');
            if (!menuId) return;

            // Show loading state
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verwijderen...';
            this.disabled = true;

            fetch('../controllers/menu.php?action=deleteRecipe', {
                method: 'POST',
                headers: { },
                body: (() => {
                    const fd = new FormData();
                    fd.append('id', menuId);
                    // Optionally add user info for backend auth
                    const userInfo = sessionStorage.getItem('userInfo');
                    if (userInfo) {
                        const user = JSON.parse(userInfo);
                        fd.append('user_id', user.id);
                        fd.append('role', user.role || '');
                    }
                    return fd;
                })()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Redirect to home page after successful delete
                    window.location.href = 'home.php';
                } else {
                    // Hide the modal
                    const modalElement = document.getElementById('deleteConfirmModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    modal.hide();

                    // Reset button state
                    this.innerHTML = 'Verwijderen';
                    this.disabled = false;

                    // Show error alert
                    alert('Verwijderen mislukt: ' + (data.message || 'Onbekende fout.'));
                }
            })
            .catch(err => {
                console.error('Error deleting recipe:', err);
                // Reset button state
                this.innerHTML = 'Verwijderen';
                this.disabled = false;
                alert('Verwijderen mislukt door een netwerkfout.');
            });
        });
    }
});
