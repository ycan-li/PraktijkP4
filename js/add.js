/**
 * @param category 'genre' or 'tag'
 * @param toggle 'hidden' or 'show', default to null
 */
function toggleSuggestion(toggle = null, category = null) {
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

function updateSelected(category = null) {
    const containers = category ? document.querySelectorAll(`.tags-input .${category}-selected`)
        : document.querySelectorAll('.tags-input .selected');
    if (!containers || containers.length === 0) {
        console.warn('No element of selected tags found');
        return;
    }

    containers.forEach(container => {
        const childCount = container.children.length;
        category = container.dataset.type;
        if (!category) {
            console.warn('No category found via dataset');
            return;
        }

        if (childCount === 0) {
            const tagAddNew = `
            <a href="#${category}-search" class="suggestion-badge-placeholder badge  link-underline link-underline-opacity-0 link-light-emphasis fs-7 bg-light-subtle border border-light-subtle text-light-emphasis rounded-pill">
                + Nieuw
            </a>
        `;

            container.insertAdjacentHTML('beforeend', tagAddNew);
        } else {
            const tag = container.querySelector('.suggestion-badge-placeholder');
            if (tag) {
                tag.remove();
            }
        }
    })
}

/**
 *
 * @param {HTMLElement} el badge element
 */
function toggleSelected(el) {
    const clone = el.cloneNode(true);
    const category = el.getAttribute('data-type');
    const parent = el.parentNode;

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

    clone.addEventListener('click', () => {
        toggleSelected(clone);
    });
    target.insertAdjacentElement('afterbegin', clone);
    el.remove();

    // Update selected to add/remove '+new' tag
    updateSelected(category);
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize
    // Init tags input
    updateSelected();
    // (no needed due already hide by default in php) Hide suggestion list
    // toggleSuggestion('hide');
    // Init suggestion badges
    const badges = document.querySelectorAll('.suggestion-badge')
    // Listen all suggestion-container
    if (!badges || badges.length === 0) {
        console.error('No suggestion (badge-container) found');
        return;
    }
    badges.forEach(badge => {
        badge.addEventListener('click', () => {
            toggleSelected(badge);
        })
    })
    // Init suggestion input box
    const suggestionInputs = document.querySelectorAll('.tags-input .search')
    if (!suggestionInputs || suggestionInputs.length === 0) {
        console.error('No search box for tags input found');
        return;
    }
    suggestionInputs.forEach(input => {
        const category = input.dataset.type;
        if (!category) {
            console.error(`No category found for search box under tags input`);
            return;
        }
        input.addEventListener('focus', () => {
            toggleSuggestion('show', category);
        })
        input.addEventListener('blur', () => {
            toggleSuggestion('hide', category);
        })
    })
})

