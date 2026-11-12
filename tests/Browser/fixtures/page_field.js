const LABELS = {
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

export const pageField = (value = '') => `
    <form>
        <fieldset id="page" data-controller="page-field"
            data-page-field-search-url-value="/api/pages/search"
            data-page-field-lookup-url-value="/api/pages/lookup"
            data-page-field-labels-value='${JSON.stringify(LABELS)}'>
            <legend>Related page</legend>
            <input type="hidden" name="page" value="${value}" data-page-field-target="input">
            <p><span data-page-field-target="title"></span><span data-page-field-target="state"></span></p>
            <button type="button" data-page-field-target="choose" data-action="page-field#openDialog" hidden>Choose page</button>
            <dialog data-page-field-target="dialog">
                <label for="page-search">Search by title</label>
                <input id="page-search" type="search" data-page-field-target="search">
                <button type="button" data-action="page-field#search">Search</button>
                <p role="status" data-page-field-target="searchStatus"></p>
                <ul data-page-field-target="results"></ul>
            </dialog>
            <p aria-live="polite" data-page-field-target="status"></p>
        </fieldset>
    </form>
`;
