import {
    trimString,
    addPlaceholderIfEmpty,
    walk
} from './lib.js';
import { ingredientRowTemplate } from './components.js';
// Get recipe ID from URL if editing
const urlParams = new URLSearchParams(window.location.search);
const recipeId = urlParams.get('id');


// Load recipe data into form if editing
async function loadRecipe() {
    if (!recipeId) return;
    try {
        const res = await fetch(`../controllers/endpoint.php?action=getRecipe&id=${recipeId}`);
        const result = await res.json();
        if (result.success && result.data) {
            const r = result.data;
            document.getElementById('name').value = r.name || '';
            document.getElementById('prepare_time').value = r.prepareTime || '';
            document.getElementById('person_num').value = r.personNum || '';
            document.getElementById('description').value = r.description || '';
            document.getElementById('preparation').value = r.preparation || '';
            // Populate existing selected genres
            const genresArr = Array.isArray(r.genre) ? r.genre : [];
            genresArr.forEach(name => {
                const badge = document.querySelector(`.genre-suggestion .suggestion-badge[data-name="${name.trim().toLowerCase()}"]`);
                if (badge) toggleSelected(badge);
            });
            // Populate existing selected tags
            const tagsArr = Array.isArray(r.tag) ? r.tag : [];
            tagsArr.forEach(name => {
                const badge = document.querySelector(`.tag-suggestion .suggestion-badge[data-name="${name.trim().toLowerCase()}"]`);
                if (badge) toggleSelected(badge);
            });
            // Update page title and button text
            const titleEl = document.querySelector('h1');
            if (titleEl) titleEl.textContent = 'Bewerk Recept';
            const submitBtn = document.querySelector('.submit-receipt-form');
            if (submitBtn) submitBtn.textContent = 'Recept Bijwerken';
        }
    } catch (e) {
        console.error('Failed to load recipe:', e);
    }
}

/**
 * Toggles visibility of suggestion elements.
 *
 * @param {'hide'|'show'} [toggle] - 'hide' to conceal suggestions, 'show' to display them; if omitted, toggles state.
 * @param {'genre'|'tag'} [category] - Category prefix for target elements; if omitted, applies to all.
 * @returns {void}
 */
function toggleSuggestion(toggle, category) {
    const containers = category ? document.querySelectorAll(`.${category}-suggestion`)
        : document.querySelectorAll('.suggestion');
    if (!containers) {
        console.error('No suggestion containers found');
        return;
    }

    containers.forEach(container => {
        if (toggle) {
            if (toggle === 'hide') {
                container.classList.remove('d-flex')
                container.classList.add('d-none');
            } else if (toggle === 'show') {
                container.classList.remove('d-none');
                container.classList.add('d-flex')
            } else {
                console.error(`Unsupported action: ${toggle}`);
            }
        } else {
            container.classList.toggle('d-none');
            container.classList.toggle('d-flex');
        }
    })
}

/**
 * Moves a badge element between suggestion and selected containers, updating its styling and interactivity.
 *
 * @param {HTMLElement} badge - Badge element with a 'data-type' attribute.
 * @returns {void}
 */
function toggleSelected(badge) {
    const clone = badge.cloneNode(true);
    const category = badge.getAttribute('data-type');
    const parent = badge.parentNode;
    let isTemp = false;
    if (badge.classList.contains('temp')) {
        isTemp = true;
    }

    let isSelected = false;

    if (parent.classList.contains('selected')) {
        isSelected = true;
    }

    const targetClass = isSelected ? `.${category}-suggestion` : `.${category}-selected`;
    const patternSearch = isSelected ? /primary/gi : /secondary/gi;
    const stringReplace = isSelected ? 'secondary' : 'primary';
    const linkStyle = [
        'link-body-emphasis',
        'link-primary'
    ];

    const target = document.querySelector(targetClass);
    if (!target) {
        console.error(`No target found: "${targetClass}"`)
        return;
    }
    let classList = clone.getAttribute('class');
    classList = classList.replaceAll(patternSearch, stringReplace);
    clone.setAttribute('class', classList);
    clone.classList.remove(isSelected ? linkStyle[1] : linkStyle[0]);
    clone.classList.add(isSelected ? linkStyle[0] : linkStyle[1]);

    if (!isSelected) {
        clone.insertAdjacentHTML('beforeend', '<i class="bi bi-x-circle-fill"></i>');
    } else {
        const closeIcon = clone.querySelector('i.bi-x-circle-fill');
        if (!closeIcon) {
            console.warn(`No close icon`);
        } else {
            closeIcon.remove();
        }
    }
    makeTagInteractive(clone);

    if (!isTemp) {
        target.insertAdjacentElement('afterbegin', clone);
    }

    badge.remove();
    addPlaceholderIfEmpty([parent, target]);
}

/**
 * Attaches event handlers to badge(s) to handle selection clicks and prevent focus loss.
 *
 * @param {HTMLElement|NodeList<HTMLElement>|HTMLElement[]} tag - Single badge or collection to init.
 * @returns {void}
 */
function makeTagInteractive(tag) {
    // Add mousedown event to prevent blur from firing when clicking on a badge
    tag.addEventListener('mousedown', (e) => {
        e.preventDefault();
    });

    tag.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleSelected(tag);
    });
}

/**
 * Configures an input field for live tag suggestions: shows/hides list on focus/blur and filters items on input.
 *
 * @param {HTMLInputElement} input - Input element with 'data-type' attribute for category.
 * @returns {void}
 */
function initInput(input) {
    const category = input.dataset.type;
    // Toggle suggestion list when focus or losing focus
    input.addEventListener('focus', () => {
        toggleSuggestion('show', category);
    })
    input.addEventListener('blur', (e) => {
        e.stopPropagation();
        e.preventDefault();
        // Add a small delay to allow click events to process first
        setTimeout(() => {
            toggleSuggestion('hide', category);
        }, 150);
    })

    let typingTimeout;
    input.addEventListener('input', () => {
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            const userInput = trimString(input.value);
            const container = document.querySelector(`.suggestion`);

            addPlaceholderIfEmpty(container, (child) => {
                const re = new RegExp('^' + userInput, 'i');
                if (!re.test(child.dataset.name)) {
                    child.classList.add('d-none');
                } else {
                    child.classList.remove('d-none');
                }
            });
        }, 500);
    })
}

/**
 * Attaches click handler to a button to add user-entered tags, ensuring no duplicates.
 *
 * @param {HTMLElement} btn - Button element that triggers tag addition.
 * @returns {void}
 */
function initTagSearchButton(btn) {
    btn.addEventListener('click', e => {
        e.preventDefault();
        // Input box
        const input = btn.parentNode.querySelector('input');
        if (!input) {
            console.warn('No search box found');
            return;
        }
        const userInput = trimString(input.value);
        if (userInput === '') {
            return;
        }

        // Handle input
        const category = input.dataset.type;
        if (!category) {
            console.warn('No category found');
            return;
        }
        let existed = false;
        let ok = true;
        const badges = document.querySelectorAll(
            `.tags-input[data-type="${category}"] .suggestion-badge`);
        for (const badge of badges) {
            if (badge.dataset.name === userInput.toLowerCase()) {
                if (!badge.parentElement.classList.contains('selected')) {
                    toggleSelected(badge);
                } else {
                    // TODO notification
                    ok = false;
                    console.warn('tag already in selected');
                }
                existed = true;
                break;
            }
        }

        // Post
        if (!existed) {
            const selected = document.querySelector(`.tags-input .${category}-selected`)
            if (!selected) {
                console.warn('No container of selected found');
                return;
            }

            const tag = document.createElement('span');
            tag.setAttribute('class', 'suggestion-badge badge temp d-flex gap-1 link-primary fs-7 bg-primary-subtle border border-primary-subtle text-primary-emphasis rounded-pill');
            tag.setAttribute('style', 'cursor: pointer;');
            tag.setAttribute('data-name', userInput.toLowerCase());
            tag.setAttribute('data-type', category);
            tag.textContent = userInput;
            tag.insertAdjacentHTML('beforeend', '<i class="bi bi-x-circle-fill"></i>');
            tag.addEventListener("click", () => {
                toggleSelected(tag);
            })

            selected.insertAdjacentElement('beforeend', tag);
            addPlaceholderIfEmpty(selected);
        }
        if (ok) {
            input.value = '';
        }
    })
}

/**
 * Discovers and initializes all tag input widgets on the page.
 *
 * @returns {void}
 */
function initTagInputs() {
    const tagsInput = document.querySelectorAll('.tags-input');
    // Init inputs
    walk(tagsInput, (el) => {
        walk(el.querySelectorAll('.search'), initInput);
    })
    // Add placeholder to tag container
    walk(tagsInput, (el) => {
        addPlaceholderIfEmpty(el.querySelectorAll('.suggestion, .selected'));
    })

    // Can add new tag
    walk(tagsInput, (el) => {
        walk(el.querySelectorAll('.search-button'), initTagSearchButton);
    })

    // Init badges
    walk(tagsInput, (el) => {
        walk(el.querySelectorAll('.badge'), makeTagInteractive);
    })
}

/**
 * Initializes the ingredient add/remove button functionality.
 *
 * - Adds a new ingredient row to the ingredients container when the add button is clicked.
 * - Allows removal of an ingredient row when the remove button is clicked, but keeps at least one row.
 *
 * @returns {void}
 */
function initIngredientBtn() {
    const ingredientsContainer = document.getElementById('ingredients-container');
    const addIngredientBtn = document.getElementById('add-ingredient');
    if (!ingredientsContainer || !addIngredientBtn) return;

    // Add new ingredient row
    addIngredientBtn.addEventListener('click', () => {
        ingredientsContainer.insertAdjacentHTML('beforeend', ingredientRowTemplate());
    });

    // Remove ingredient row
    ingredientsContainer.addEventListener('click', (e) => {
        if (e.target.closest('.remove-ingredient')) {
            const row = e.target.closest('.ingredient-row');
            if (row && ingredientsContainer.children.length > 1) {
                row.remove();
            }
        }
    });
}

/**
 * Initializes the submit button for the recipe form.
 * Handles form validation, collects all form data (including dynamic ingredients and tags),
 * serializes ingredients to JSON, and submits via AJAX. Shows success/error alerts.
 *
 * @returns {void}
 */
function initSubmitBtn() {
    const form = document.getElementById('add-recipe-form');
    if (!form) return;
    const successAlert = document.getElementById('success-alert');
    const errorAlert = document.getElementById('error-alert');
    const ingredientsJson = document.getElementById('ingredients-json');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        if (!form.checkValidity()) return;

        // Collect ingredients
        const ingredientRows = form.querySelectorAll('.ingredient-row');
        const ingredients = [];
        ingredientRows.forEach(row => {
            const amount = row.querySelector('input[type="number"]').value.trim();
            const unit = row.querySelectorAll('input[type="text"]')[0].value.trim();
            const name = row.querySelectorAll('input[type="text"]')[1].value.trim();
            if (amount && unit && name) {
                ingredients.push(`${amount} ${unit} ${name}`);
            } else if (amount && name) {
                ingredients.push(`${amount} ${name}`);
            } else if (name) {
                ingredients.push(name);
            }
        });
        ingredientsJson.value = ingredients.join('; ');

        // Collect tags/genres
        const tags = [];
        document.querySelectorAll('.tag-tags-input .selected .suggestion-badge').forEach(badge => {
            tags.push(badge.textContent.trim());
        });
        const genres = [];
        document.querySelectorAll('.genre-tags-input .selected .suggestion-badge').forEach(badge => {
            genres.push(badge.textContent.trim());
        });

        // Prepare form data
        const formData = new FormData(form);
        formData.set('ingredients', ingredientsJson.value);
        formData.set('tags', JSON.stringify(tags));
        formData.set('genres', JSON.stringify(genres));

        // AJAX submit
        const action = recipeId ? 'updateRecipe' : 'addRecipe';
        if (recipeId) formData.append('id', recipeId);
        try {
            const response = await fetch(`../controllers/endpoint.php?action=${action}`, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                successAlert.classList.remove('d-none');
                errorAlert.classList.add('d-none');
                setTimeout(() => {
                    window.location.href = `detail.php?id=${recipeId || result.id}`;
                }, 1500);
            } else {
                errorAlert.classList.remove('d-none');
                successAlert.classList.add('d-none');
            }
        } catch (err) {
            errorAlert.classList.remove('d-none');
            successAlert.classList.add('d-none');
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadRecipe();
    initIngredientBtn();
    initTagInputs();
    initSubmitBtn();
})
