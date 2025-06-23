/**
 * Generates an HTML snippet for an empty-state placeholder.
 *
 * @returns {string} A span element string with default styling and placeholder text.
 */
export function emptyPlaceholder() {
    return '<span class="text-secondary empty-placeholder">¯\\_(ツ)_/¯ Leeg~</span>';
}

/**
 * Generates an HTML string for a single ingredient row.
 *
 * @returns {string} HTML for an ingredient row.
 */
export function ingredientRowTemplate() {
    return `
        <div class="ingredient-row mb-2 d-flex gap-2">
            <input type="number" class="form-control bg-dark text-white border-secondary" placeholder="Aantal" step="0.1" min="0">
            <input type="text" class="form-control bg-dark text-white border-secondary" placeholder="Eenheid (g, ml, stuks)">
            <input type="text" class="form-control bg-dark text-white border-secondary" placeholder="Ingrediënt">
            <button type="button" class="btn btn-outline-danger remove-ingredient">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
}
