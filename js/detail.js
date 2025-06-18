document.addEventListener('DOMContentLoaded', function() {
    // Get the favorite button
    const favoriteButton = document.querySelector('.fav.btn-primary');
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
            this.classList.remove('btn-danger');
            this.classList.add('btn-primary');
        } else {
            heartIcon.classList.remove('bi-heart');
            heartIcon.classList.add('bi-heart-fill');
            this.classList.remove('btn-primary');
            this.classList.add('btn-danger');
        }

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
                favoriteButton.classList.remove('btn-primary');
                favoriteButton.classList.add('btn-danger');
            } else {
                heartIcon.classList.remove('bi-heart-fill');
                heartIcon.classList.add('bi-heart');
                favoriteButton.classList.remove('btn-danger');
                favoriteButton.classList.add('btn-primary');
            }
        });
    });

    // Function to check and update initial favorite status
    function checkFavoriteStatus() {
        fetch('../controllers/endpoint.php', {
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
                favoriteButton.classList.remove('btn-primary');
                favoriteButton.classList.add('btn-danger');
            }
        })
        .catch(err => {
            console.error('Error checking favorite status:', err);
        });
    }
});
