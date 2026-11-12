/*
 * The markup of the small form controllers, with the attributes their Twig
 * templates write: character_count.html.twig, CopyField, SearchPreview,
 * FormTextarea and FormButton.
 */

export const characterCount = (value = '') => `
    <div data-controller="character-count"
        data-character-count-min-value="5" data-character-count-max-value="10"
        data-character-count-short-value="%count% of %min% to %max%: short"
        data-character-count-fits-value="%count% of %min% to %max%: fits"
        data-character-count-long-value="%count% of %min% to %max%: long">
        <label for="title">Title</label>
        <textarea id="title" data-character-count-target="field" data-action="input->character-count#update">${value}</textarea>
        <p id="title-count" data-character-count-target="output">Written by the server</p>
    </div>
`;

export const copyField = (value) => `
    <div data-controller="copy-field" data-copy-field-copied-value="Copied" data-copy-field-failed-value="Copy it by hand">
        <label for="address">Address</label>
        <input type="url" id="address" value="${value}" readonly data-copy-field-target="value" data-action="focus->copy-field#select">
        <button type="button" data-action="copy-field#copy">Copy</button>
        <p role="status" data-copy-field-target="status"></p>
    </div>
`;

export const searchPreview = ({ title = '', description = '', slug = 'first-page' } = {}) => `
    <form>
        <label for="page-title">Title</label>
        <input id="page-title" value="${title}">
        <label for="page-description">Description</label>
        <textarea id="page-description">${description}</textarea>
        <label for="page-slug">Slug</label>
        <input id="page-slug" value="${slug}">
    </form>
    <figure data-controller="search-preview"
        data-search-preview-sources-value='{"title":"page-title","description":"page-description","slug":"page-slug"}'
        data-search-preview-base-value="https://blogautista.test"
        data-search-preview-title-limit-value="20"
        data-search-preview-description-limit-value="30"
        data-search-preview-empty-value="No description">
        <figcaption>Search result</figcaption>
        <p data-search-preview-target="url"></p>
        <p data-search-preview-target="title"></p>
        <p data-search-preview-target="description"></p>
    </figure>
`;

export const singleLine = (value = '') => `
    <label for="title">Title</label>
    <textarea id="title" rows="1" data-controller="single-line"
        data-action="keydown->single-line#preventBreak input->single-line#flatten">${value}</textarea>
`;

/* The publish button waits for the edit form, one of whose fields lives outside it. */
export const unsavedChanges = () => `
    <form id="edit" action="/api/save" method="post">
        <label for="title">Title</label>
        <input id="title" name="title" value="First page">
    </form>
    <label for="summary">Summary</label>
    <input id="summary" name="summary" form="edit" value="A summary">
    <form id="publish" action="/api/publish" method="post">
        <span data-controller="unsaved-changes" data-unsaved-changes-form-value="edit"
            data-action="input@document->unsaved-changes#check change@document->unsaved-changes#check">
            <span id="edit-unsaved" role="status" data-unsaved-changes-target="hint" hidden>Save first</span>
            <button type="submit" aria-describedby="edit-unsaved" data-unsaved-changes-target="button" data-action="unsaved-changes#hold">Publish</button>
        </span>
    </form>
`;
