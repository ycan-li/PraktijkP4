import {emptyPlaceholder} from "./components.js";

/**
 * Trim white space characters upon both sides
 * @param {string} str
 * @returns {string}
 */
export function trimString(str) {
    return str.replace(/^\s+|\s+$/g, '');
}

/**
 * Add placeholder of empty to container if there is no child in it
 * or all children are displayed in none
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
        const placeholder = emptyPlaceholder();
        parent.insertAdjacentHTML('afterbegin', placeholder);
    } else {
        const ph = parent.querySelector('.empty-placeholder');
        if (ph) {
            ph.remove();
        }
    }
}

/**
 * Test if obj iterable
 * @param {any} obj
 * @return {boolean}
 */
export function isIterable(obj) {
    return !obj && typeof obj[Symbol.iterator] === 'function';
}

/**
 * Iterates over an iterable object and applies a callback to each item.
 * @param {any} iter - The iterable object to walk through.
 * @param {(item: any) => void} callback - Function to execute for each item.
 * @returns {void}
 */
export function walk(iter, callback) {
    if (!iter && !isIterable(iter)) {
        return;
    }

    iter.forEach(i => {
        callback(i);
    });
}

export function map(iter, callback) {
    let res = [];
    if (!iter && !isIterable(iter)) {
        return null;
    }

    iter.forEach(i => {
        res.push(callback(i));
    })

    return res;
}