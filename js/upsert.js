import {
    trimString,
    addPlaceholderIfEmpty,
    walk
} from './lib.js';

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

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initTagInputs();
})
