const EMPTY_DOCUMENT = {
    type: 'doc',
    content: [{ type: 'paragraph' }],
};

const IMAGE_LABELS = {
    loading: 'Loading image',
    missing: 'Image not found',
    unknown: 'Image could not be checked',
    error: 'Image search failed',
    offSite: 'Not on the site',
    result: '1 result',
    results: '%count% results',
    none: 'No images',
    embedded: 'Embedded image:',
};

const PAGE_LABELS = {
    title: 'Link the selected text',
    loading: 'Loading page',
    missing: 'Page not found',
    unknown: 'Page could not be checked',
    target: 'Linked page: %title%. %status%.',
    offSite: 'Not on the site',
    error: 'Page search failed',
    result: '1 result',
    results: '%count% results',
    none: 'No pages',
    removed: 'Link removed',
};

const WIDGET_LABELS = {
    title: 'Insert related content',
    label: 'Related content:',
    loading: 'Loading page',
    missing: 'Page not found',
    unknown: 'Page could not be checked',
    offSite: 'Not on the site',
    result: '1 result',
    results: '%count% results',
};

export const richTextEditor = ({
    document = EMPTY_DOCUMENT,
    features = { blocks: ['heading'], headingLevels: [2], marks: [] },
    blockOptions = [
        { value: 'paragraph', label: 'Paragraph' },
        { value: 'heading:2', label: 'Heading 2' },
    ],
    images = false,
    links = false,
    widgets = false,
    maxLength = null,
} = {}) => {
    const calloutLabels = Object.fromEntries((features.calloutVariants ?? []).map((variant) => [
        variant,
        variant.charAt(0).toUpperCase() + variant.slice(1),
    ]));
    const imageValues = images ? `
        data-rich-text-editor-image-search-url-value="/api/images/search"
        data-rich-text-editor-image-lookup-url-value="/api/images/lookup"
        data-rich-text-editor-image-labels-value='${JSON.stringify(IMAGE_LABELS)}'
    ` : '';
    const pageValues = links || widgets ? `
        data-rich-text-editor-page-search-url-value="/api/pages/search"
        data-rich-text-editor-page-lookup-url-value="/api/pages/lookup"
        data-rich-text-editor-page-labels-value='${JSON.stringify(PAGE_LABELS)}'
    ` : '';
    const widgetValues = widgets ? `
        data-rich-text-editor-widget-labels-value='${JSON.stringify(WIDGET_LABELS)}'
    ` : '';
    const countValues = maxLength === null ? '' : `
        data-rich-text-editor-max-length-value="${maxLength}"
        data-rich-text-editor-count-value="Count %count%/%max%"
        data-rich-text-editor-count-over-value="Over by %over%"
    `;
    const imageButton = images ? `
        <button type="button" data-rich-text-editor-target="button" data-action="rich-text-editor#run"
            data-command="insertImage">Insert image</button>
    ` : '';
    const linkButton = links ? `
        <button type="button" data-rich-text-editor-target="button" data-action="rich-text-editor#run"
            data-command="insertLink">Link</button>
    ` : '';
    const widgetButton = widgets ? `
        <button type="button" data-rich-text-editor-target="button" data-action="rich-text-editor#run"
            data-command="insertWidget">Related page</button>
    ` : '';
    const linkTools = links ? `
        <div data-rich-text-editor-target="linkTools" hidden>
            <p data-rich-text-editor-target="linkTarget"></p>
            <button type="button" data-rich-text-editor-target="button" data-action="rich-text-editor#run"
                data-command="unlink">Remove link</button>
        </div>
    ` : '';
    const imageDialog = images ? `
        <dialog data-rich-text-editor-target="imageDialog">
            <label for="content-image-search">Search images</label>
            <input id="content-image-search" type="search" data-rich-text-editor-target="imageSearch">
            <button type="button" data-action="rich-text-editor#searchImages">Search images</button>
            <p role="status" data-rich-text-editor-target="imageStatus"></p>
            <ul data-rich-text-editor-target="imageResults"></ul>
        </dialog>
    ` : '';
    const pageDialog = links || widgets ? `
        <dialog data-rich-text-editor-target="pageDialog">
            <h2 data-rich-text-editor-target="pageDialogTitle"></h2>
            <label for="content-page-search">Search pages</label>
            <input id="content-page-search" type="search" data-rich-text-editor-target="pageSearch">
            <button type="button" data-action="rich-text-editor#searchPages">Search pages</button>
            <p role="status" data-rich-text-editor-target="pageStatus"></p>
            <ul data-rich-text-editor-target="pageResults"></ul>
            <button type="button" data-rich-text-editor-target="pageMore" data-action="rich-text-editor#morePages" hidden>More pages</button>
        </dialog>
    ` : '';
    const count = maxLength === null ? '' : '<p id="content-count" data-rich-text-editor-target="count"></p>';

    return `
        <div data-controller="rich-text-editor"
            data-rich-text-editor-id-value="content"
            data-rich-text-editor-placeholder-value="Write something"
            data-rich-text-editor-slash-hint-value="Choose a block"
            data-rich-text-editor-quote-source-hint-value="Source"
            data-rich-text-editor-block-options-value='${JSON.stringify(blockOptions)}'
            data-rich-text-editor-moved-up-value="Block moved up"
            data-rich-text-editor-moved-down-value="Block moved down"
            data-rich-text-editor-section-moved-up-value="Section moved up"
            data-rich-text-editor-section-moved-down-value="Section moved down"
            data-rich-text-editor-features-value='${JSON.stringify(features)}'
            data-rich-text-editor-callout-labels-value='${JSON.stringify(calloutLabels)}'
            ${imageValues}
            ${pageValues}
            ${widgetValues}
            ${countValues}>
            <span id="content-label">Content</span>
            <p id="content-hint" data-rich-text-editor-target="hint" hidden>Edit the content</p>
            <div data-rich-text-editor-target="frame" hidden>
                <div data-rich-text-editor-target="canvas" data-action="focusout->rich-text-editor#leave">
                    <div data-rich-text-editor-target="editor"></div>
                    <div data-rich-text-editor-target="dock" hidden>
                        ${imageButton}${linkButton}${widgetButton}
                        ${linkTools}
                    </div>
                    <ul id="content-menu" role="listbox" data-rich-text-editor-target="menu" hidden></ul>
                </div>
            </div>
            ${count}
            ${imageDialog}
            ${pageDialog}
            <p aria-live="polite" data-rich-text-editor-target="status"></p>
            <input type="hidden" name="content" value='${JSON.stringify(document)}' data-rich-text-editor-target="input">
        </div>
    `;
};
