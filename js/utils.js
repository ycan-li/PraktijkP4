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