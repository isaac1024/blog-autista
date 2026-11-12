const LABELS = {
    empty: 'No image selected',
    loading: 'Loading image',
    missing: 'Image not found',
    unknown: 'Image could not be checked',
    offSite: 'Not on the site',
    chosen: 'Chosen %alt%',
    removed: 'Image removed',
    none: 'No results',
    result: '1 result',
    results: '%count% results',
    error: 'Search failed',
};

export const imageField = (value = '') => `
    <form>
        <fieldset id="image" data-controller="image-field"
            data-image-field-search-url-value="/api/images/search"
            data-image-field-lookup-url-value="/api/images/lookup"
            data-image-field-labels-value='${JSON.stringify(LABELS)}'>
            <legend>Featured image</legend>
            <input type="hidden" name="image" value="${value}" data-image-field-target="input">
            <span data-image-field-target="thumbnail" hidden></span>
            <p><span data-image-field-target="description"></span><span data-image-field-target="state"></span></p>
            <button type="button" data-image-field-target="choose" data-action="image-field#openDialog" hidden>Choose image</button>
            <button type="button" data-image-field-target="remove" data-action="image-field#remove" hidden>Remove image</button>
            <dialog data-image-field-target="dialog">
                <label for="image-search">Search by alternative text</label>
                <input id="image-search" type="search" data-image-field-target="search">
                <button type="button" data-action="image-field#search">Search</button>
                <p role="status" data-image-field-target="searchStatus"></p>
                <ul data-image-field-target="results"></ul>
            </dialog>
            <p aria-live="polite" data-image-field-target="status"></p>
        </fieldset>
    </form>
`;
