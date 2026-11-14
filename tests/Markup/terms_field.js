export const TERMS_FIELD_LABELS = {
    offSite: 'Not shown on the site',
    added: 'Added %name%',
    removed: 'Removed %name%',
    repeated: '%name% is already listed',
    none: 'No results',
    result: '1 result',
    results: '%count% results',
    error: 'Search failed',
};

const row = ({ id = '', name = '', status = '' } = {}) => `
    <li data-terms-field-target="row" data-term-id="${id}">
        <input type="hidden" name="tags[]" value="${id}" data-field="id">
        <p id="tags-${id}" data-field="term">
            <span data-field="name">${name}</span>
            <span data-field="status">${status}</span>
        </p>
        <button type="button" aria-describedby="tags-${id}" data-action="terms-field#remove" data-field="remove">Remove</button>
    </li>
`;

/**
 * The tags of a post as the TermsField component writes them.
 *
 * @param {{items?: {id: string, name: string, status: string}[]}} [options]
 */
export const termsField = ({ items = [] } = {}) => `
    <form>
        <fieldset id="tags" data-controller="terms-field"
            data-terms-field-search-url-value="/api/tags/search"
            data-terms-field-labels-value='${JSON.stringify(TERMS_FIELD_LABELS)}'>
            <legend>Tags</legend>
            <p data-terms-field-target="empty"${items.length > 0 ? ' hidden' : ''}>No tags</p>
            <ul data-terms-field-target="list">${items.map((item) => row(item)).join('')}</ul>
            <template data-terms-field-target="template">${row()}</template>
            <button type="button" data-terms-field-target="add" data-action="terms-field#openDialog" hidden>Add tag</button>
            <dialog data-terms-field-target="dialog">
                <label for="tags-search">Search by name</label>
                <input id="tags-search" type="search" data-terms-field-target="search" data-action="keydown.enter->terms-field#search">
                <button type="button" data-action="terms-field#search">Search</button>
                <p role="status" data-terms-field-target="searchStatus"></p>
                <ul data-terms-field-target="results"></ul>
                <button type="button" data-terms-field-target="more" data-action="terms-field#more" hidden>More</button>
            </dialog>
            <p aria-live="polite" data-terms-field-target="status"></p>
        </fieldset>
    </form>
`;
