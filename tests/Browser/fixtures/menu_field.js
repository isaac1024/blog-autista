const LABELS = {
    loading: 'Loading page',
    missing: 'Page not found',
    unknown: 'Page could not be checked',
    offSite: 'Not on the site',
    added: 'Added %title%',
    removed: 'Removed %title%',
    movedUp: 'Moved up %title%',
    movedDown: 'Moved down %title%',
    repeated: 'The page is already in the menu',
    none: 'No results',
    result: '1 result',
    results: '%count% results',
    error: 'Search failed',
};

const row = (pageId = '', label = '') => `
    <li data-menu-field-target="row" data-page-id="${pageId}">
        <input type="hidden" value="${pageId}" data-field="page_id">
        <p data-field="page">
            <span data-menu-field-target="pageTitle">Loading page</span>
            <span data-menu-field-target="pageStatus"></span>
        </p>
        <label data-field="label-for">Custom label</label>
        <input type="text" value="${label}" data-field="label">
        <button type="button" data-action="menu-field#moveUp" data-field="up">Move up</button>
        <button type="button" data-action="menu-field#moveDown" data-field="down">Move down</button>
        <button type="button" data-action="menu-field#remove" data-field="remove">Remove</button>
    </li>
`;

export const menuField = ({ items = [], max = 2 } = {}) => `
    <form>
        <fieldset id="menu" data-controller="menu-field"
            data-menu-field-name-value="menu"
            data-menu-field-search-url-value="/api/pages/search"
            data-menu-field-lookup-url-value="/api/pages/lookup"
            data-menu-field-max-value="${max}"
            data-menu-field-labels-value='${JSON.stringify(LABELS)}'>
            <legend>Main menu</legend>
            <p id="menu-label-hint" hidden>Optional label</p>
            <p data-menu-field-target="empty">No entries</p>
            <ol data-menu-field-target="list">${items.map((item) => row(item.id, item.label ?? '')).join('')}</ol>
            <template data-menu-field-target="template">${row()}</template>
            <button type="button" data-menu-field-target="add" data-action="menu-field#openDialog" hidden>Add page</button>
            <span data-menu-field-target="full" hidden>The menu is full</span>
            <dialog data-menu-field-target="dialog">
                <label for="menu-search">Search pages</label>
                <input id="menu-search" type="search" data-menu-field-target="search">
                <button type="button" data-action="menu-field#search">Search</button>
                <p role="status" data-menu-field-target="searchStatus"></p>
                <ul data-menu-field-target="results"></ul>
                <button type="button" data-menu-field-target="more" data-action="menu-field#more" hidden>More</button>
            </dialog>
            <p aria-live="polite" data-menu-field-target="status"></p>
        </fieldset>
    </form>
`;
