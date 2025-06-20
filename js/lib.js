/**
 * Trim white space characters upon both sides
 * @param {string} str
 * @returns {string}
 */
export function trimString(str) {
    return str.replace(/^\s+|\s+$/g, '');
}

/**
 * Empty placeholder
 * @returns {string}
 */
export function getEmptyPlaceholer() {
    return '<span class="text-secondary empty-placeholder">¯\\_(ツ)_/¯ Leeg~</span>';
}

/**
 *
 * @param {HTMLElement|NodeList|Array} parent
 * @param {function(HTMLElement):void} [callback] - Optional. Function applied to every child
 * @return {void}
 */
export function addPlaceholderIfEmpty(parent, callback = null) {
    if (NodeList.prototype.isPrototypeOf(parent) || Array.isArray(parent)) {
        parent.forEach(el => addPlaceholderIfEmpty(el, callback))
        return;
    }

    const children = parent.children;
    const childrenCount = children.length;
    let childrenHidden;
    let hiddenCount = 0;

    for (const child of children) {
        if (callback) {
            callback(child);
        }
        if (child.classList.contains('d-none')) {
            hiddenCount++;
        }
    }
    childrenHidden = hiddenCount === childrenCount;

    if (childrenCount === 0 || childrenHidden) {
        const placeholder = getEmptyPlaceholer();
        parent.insertAdjacentHTML('afterbegin', placeholder);
    } else {
        const ph = parent.querySelector('.empty-placeholder');
        if (ph) {
            ph.remove();
        }
    }
}