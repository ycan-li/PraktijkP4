// Use it only when need user login in

let userInfo = null;

function checkUser() {
    try {
        userInfo = JSON.parse(sessionStorage.getItem('userInfo'));

    } catch (e) {
    }

    if (!userInfo) {
        const mainContainer = document.querySelector('main');
        if (!mainContainer) {
            console.error("Unable to find main");
            return;
        }
        mainContainer.innerHTML = `
            <div class="alert alert-warning" role="alert">
                Moet inloggen
            </div>
        `;
    }
}

checkUser();