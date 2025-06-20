import {trimString, getEmptyPlaceholer, addPlaceholderIfEmpty} from './lib.js';

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

/**
 *
 * @param {HTMLElement} el badge element
 */
function toggleSelected(el) {
    const clone = el.cloneNode(true);
    const category = el.getAttribute('data-type');
    const parent = el.parentNode;
    let isTemp = false;
    if (el.classList.contains('temp')) {
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
    clone.addEventListener('mousedown', (e) => {
        e.preventDefault();
    });

    clone.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleSelected(clone);
    });

    if (!isTemp) {
        target.insertAdjacentElement('afterbegin', clone);
    }

    el.remove();
    addPlaceholderIfEmpty([parent, target]);
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize
    // Init tags input
    addPlaceholderIfEmpty(document.querySelectorAll('.tags-input .suggestion, .tags-input .selected'))
    // (no needed due already hide by default in php) Hide suggestion list
    // toggleSuggestion('hide');
    // Init suggestion badges
    const badges = document.querySelectorAll('.suggestion-badge')
    if (!badges || badges.length === 0) {
        console.error('No suggestion (badge-container) found');
        return;
    }
    badges.forEach(badge => {
        // Add mousedown event to prevent blur from firing when clicking on a badge
        badge.addEventListener('mousedown', (e) => {
            e.preventDefault();
        });

        badge.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleSelected(badge);
        });
    });

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
                const badges = document.querySelectorAll(`.tags-input .suggestion[data-type="${category}"] .suggestion-badge`);
                if (badges.length === 0) {
                    // console.warn('No badges found');
                    return;
                }
                const container = document.querySelector(`.suggestion[data-type="${category}"]`);

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
    })
    // Add new tag via hidden button
    const categorySearchButtons = document.querySelectorAll('.tags-input .search-button');
    if (!categorySearchButtons || categorySearchButtons.length === 0) {
        console.error('No button of search box for tags input found');
        return;
    }
    categorySearchButtons.forEach(btn => {
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
    })
})
