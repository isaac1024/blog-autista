export const RESOURCE_FIELD_LABELS = {
    empty: 'No page selected',
    loading: 'Loading page',
    missing: 'Page not found',
    unknown: 'Page could not be checked',
    offSite: 'Not on the site',
    chosen: 'Chosen %title%',
    none: 'No results',
    result: '1 result',
    results: '%count% results',
    error: 'Search failed',
};

export const resourceField = (value = '') => `
    <form>
        <fieldset id="page" data-controller="resource-field"
            data-resource-field-search-url-value="/api/pages/search"
            data-resource-field-lookup-url-value="/api/pages/lookup"
            data-resource-field-key-value="pages"
            data-resource-field-param-value="title"
            data-resource-field-labels-value='${JSON.stringify(RESOURCE_FIELD_LABELS)}'>
            <legend>Related page</legend>
            <input type="hidden" name="page" value="${value}" data-resource-field-target="input">
            <p><span data-resource-field-target="title"></span><span data-resource-field-target="state"></span></p>
            <button type="button" data-resource-field-target="choose" data-action="resource-field#openDialog" hidden>Choose page</button>
            <dialog data-resource-field-target="dialog">
                <label for="page-search">Search by title</label>
                <input id="page-search" type="search" data-resource-field-target="search">
                <button type="button" data-action="resource-field#search">Search</button>
                <p role="status" data-resource-field-target="searchStatus"></p>
                <ul data-resource-field-target="results"></ul>
                <button type="button" data-resource-field-target="more" data-action="resource-field#more" hidden>More</button>
            </dialog>
            <p aria-live="polite" data-resource-field-target="status"></p>
        </fieldset>
    </form>
`;
