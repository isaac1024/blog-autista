/*
 * The markup of Shared:RichTextEditor, with the attributes, controls and
 * dialogs its Twig template writes for the features given, without the
 * classes and icons that only style it.
 */

const EMPTY_DOCUMENT = {
    type: 'doc',
    content: [{ type: 'paragraph' }],
};

export const IMAGE_LABELS = {
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

/**
 * What the editor links to, by kind, each where it is searched and looked
 * up; sections and tags are both terms, looked up together.
 */
export const LINK_TARGETS = [
    { kind: 'page', type: 'page', searchUrl: '/api/pages/search', param: 'title', key: 'pages', lookupUrl: '/api/pages/lookup' },
    { kind: 'post', type: 'post', searchUrl: '/api/posts/search', param: 'title', key: 'posts', lookupUrl: '/api/posts/lookup' },
    { kind: 'section', type: 'term', searchUrl: '/api/sections/search', param: 'name', key: 'terms', lookupUrl: '/api/terms/lookup' },
    { kind: 'tag', type: 'term', searchUrl: '/api/tags/search', param: 'name', key: 'terms', lookupUrl: '/api/terms/lookup' },
    { kind: 'author', type: 'author', searchUrl: '/api/authors/search', param: 'name', key: 'authors', lookupUrl: '/api/authors/lookup' },
];

export const PAGE_LABELS = {
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

export const WIDGET_LABELS = {
    title: 'Insert related content',
    label: 'Related content:',
    loading: 'Loading page',
    missing: 'Page not found',
    unknown: 'Page could not be checked',
    offSite: 'Not on the site off-site',
    result: '1 related page',
    results: '%count% related pages',
};

export const MOSAIC_LABELS = {
    label: 'Content mosaic',
    change: 'Change mosaic',
    limit: 'Up to %limit% posts',
    section: 'Section',
    author: 'Author',
    tags: 'Tags',
    loading: 'Loading',
    missing: 'Not found',
    unknown: 'Could not be checked',
    anySection: 'From every section',
    anyAuthor: 'By every author',
    noTags: 'With any tag',
    removeTag: 'Remove %name%',
    insert: 'Insert mosaic',
    save: 'Save mosaic',
    pick: {
        section: { title: 'Choose the section', offSite: 'Not published', result: '1 result', results: '%count% results' },
        author: { title: 'Choose the author', offSite: 'Not published', result: '1 result', results: '%count% results' },
        tag: { title: 'Add a tag', offSite: 'Not published', result: '1 result', results: '%count% results' },
    },
};

/** A control of the dock, named by its label, which its icon's tooltip repeats. */
const button = (command, label, { sectionLabel = null, toggle = false, icon = true } = {}) => `
    <button type="button" data-rich-text-editor-target="button" data-action="mousedown->rich-text-editor#keepFocus rich-text-editor#run"
        data-command="${command}" data-label="${label}"${sectionLabel === null ? '' : ` data-section-label="${sectionLabel}"`}${toggle ? ' aria-pressed="false"' : ''}>
        ${icon ? `<span data-role="name">${label}</span><span aria-hidden="true" data-role="tooltip">${label}</span>` : `<span>${label}</span>`}
    </button>
`;

export const richTextEditor = ({
    document = EMPTY_DOCUMENT,
    features = { blocks: ['heading'], headingLevels: [2], marks: [] },
    blockOptions = [
        { value: 'paragraph', label: 'Paragraph' },
        { value: 'heading:2', label: 'Heading 2' },
    ],
    singleParagraph = false,
    images = false,
    links = false,
    widgets = false,
    mosaics = false,
    maxLength = null,
} = {}) => {
    const blocks = features.blocks ?? [];
    const marks = features.marks ?? [];
    const calloutLabels = Object.fromEntries((features.calloutVariants ?? []).map((variant) => [
        variant,
        variant.charAt(0).toUpperCase() + variant.slice(1),
    ]));
    const values = [
        singleParagraph ? 'data-rich-text-editor-single-paragraph-value="true"' : '',
        images ? `data-rich-text-editor-image-search-url-value="/api/images/search"
            data-rich-text-editor-image-lookup-url-value="/api/images/lookup"
            data-rich-text-editor-image-labels-value='${JSON.stringify(IMAGE_LABELS)}'` : '',
        links || widgets || mosaics ? `data-rich-text-editor-link-targets-value='${JSON.stringify(LINK_TARGETS)}'
            data-rich-text-editor-page-labels-value='${JSON.stringify(PAGE_LABELS)}'` : '',
        widgets ? `data-rich-text-editor-widget-labels-value='${JSON.stringify(WIDGET_LABELS)}'` : '',
        mosaics ? `data-rich-text-editor-mosaic-labels-value='${JSON.stringify(MOSAIC_LABELS)}'` : '',
        maxLength === null ? '' : `data-rich-text-editor-max-length-value="${maxLength}"
            data-rich-text-editor-count-value="Count %count%/%max%"
            data-rich-text-editor-count-over-value="Over by %over%"`,
    ].join(' ');
    const inserts = blocks.includes('table') || images || widgets || mosaics || marks.includes('bold') || links;

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
            ${values}>
            <span id="content-label">Content</span>
            <p id="content-hint" data-rich-text-editor-target="hint" hidden>Edit the content</p>
            <div data-rich-text-editor-target="frame" hidden>
                <div data-rich-text-editor-target="canvas" data-action="focusout->rich-text-editor#leave keydown->rich-text-editor#controlsKey">
                    <div data-rich-text-editor-target="editor"></div>
                    <div role="toolbar" aria-label="Formatting" aria-controls="content" data-rich-text-editor-target="dock" hidden>
                        <div>
                            ${singleParagraph ? '' : `
                                <button type="button" aria-haspopup="listbox" aria-expanded="false" aria-controls="content-menu"
                                    data-rich-text-editor-target="blockType" data-action="mousedown->rich-text-editor#keepFocus rich-text-editor#openBlockMenu">
                                    <span>Kind of block:</span> <span data-role="name"></span>
                                </button>
                                ${button('moveUp', 'Move up', { sectionLabel: 'Move section up' })}
                                ${button('moveDown', 'Move down', { sectionLabel: 'Move section down' })}
                            `}
                            ${marks.includes('bold') ? button('bold', 'Bold', { toggle: true }) : ''}
                            ${links ? button('insertLink', 'Link') : ''}
                            ${blocks.includes('table') ? button('insertTable', 'Insert table') : ''}
                            ${images ? button('insertImage', 'Insert image') : ''}
                            ${widgets ? button('insertWidget', 'Related page') : ''}
                            ${mosaics ? button('insertMosaic', 'Content mosaic') : ''}
                            ${inserts ? '<span aria-hidden="true"></span>' : ''}
                            ${button('undo', 'Undo')}
                            ${button('redo', 'Redo')}
                            ${singleParagraph ? '' : `
                                <button type="button" commandfor="content-outline-dialog" command="show-modal" aria-haspopup="dialog"
                                    data-action="mousedown->rich-text-editor#keepFocus" data-rich-text-editor-target="outlineButton" hidden>Outline</button>
                            `}
                        </div>
                        ${blocks.includes('table') ? `
                            <div role="group" aria-label="Table" data-rich-text-editor-target="tableTools" hidden>
                                ${button('addRowAfter', 'Add row')}
                                ${button('addColumnAfter', 'Add column')}
                                ${button('deleteRow', 'Delete row')}
                                ${button('deleteColumn', 'Delete column')}
                                ${button('deleteTable', 'Delete table')}
                            </div>
                        ` : ''}
                        ${blocks.includes('quote') ? `
                            <div role="group" aria-label="Quote" data-rich-text-editor-target="quoteTools" hidden>
                                ${button('quoteSource', 'Quote source', { icon: false })}
                            </div>
                        ` : ''}
                        ${links ? `
                            <div role="group" aria-label="Link" data-rich-text-editor-target="linkTools" hidden>
                                <p data-rich-text-editor-target="linkTarget"></p>
                                ${button('changeLink', 'Change link', { icon: false })}
                                ${button('unlink', 'Remove link', { icon: false })}
                            </div>
                        ` : ''}
                    </div>
                    <ul id="content-menu" role="listbox" tabindex="-1" aria-label="Kinds of block" data-rich-text-editor-target="menu"
                        data-action="keydown->rich-text-editor#menuButtonKey focusout->rich-text-editor#menuFocusOut" hidden></ul>
                </div>
                ${maxLength === null ? '' : '<p id="content-count" data-rich-text-editor-target="count"></p>'}
            </div>
            ${singleParagraph ? '' : `
                <dialog id="content-outline-dialog" aria-labelledby="content-outline-title" data-rich-text-editor-target="outlineDialog">
                    <h2 id="content-outline-title">Outline</h2>
                    <ol data-rich-text-editor-target="outlineDialogList"></ol>
                </dialog>
            `}
            ${images ? `
                <dialog id="content-image-dialog" data-rich-text-editor-target="imageDialog">
                    <div role="search">
                        <label for="content-image-search">Search images</label>
                        <input id="content-image-search" type="search" data-rich-text-editor-target="imageSearch" data-action="keydown.enter->rich-text-editor#searchImages">
                        <button type="button" data-action="rich-text-editor#searchImages">Search images</button>
                    </div>
                    <p role="status" data-rich-text-editor-target="imageStatus"></p>
                    <ul data-rich-text-editor-target="imageResults"></ul>
                </dialog>
            ` : ''}
            ${links || widgets || mosaics ? `
                <dialog id="content-page-dialog" data-rich-text-editor-target="pageDialog">
                    <h2 data-rich-text-editor-target="pageDialogTitle">${links ? PAGE_LABELS.title : WIDGET_LABELS.title}</h2>
                    <fieldset data-rich-text-editor-target="pageKinds">
                        <legend>What to link</legend>
                        ${LINK_TARGETS.map(({ kind }, index) => `<label><input type="radio" name="content-page-kind" value="${kind}"${index === 0 ? ' checked' : ''} data-action="change->rich-text-editor#chooseKind"> ${kind}</label>`).join('')}
                    </fieldset>
                    <div role="search">
                        <label for="content-page-search">Search pages</label>
                        <input id="content-page-search" type="search" data-rich-text-editor-target="pageSearch" data-action="keydown.enter->rich-text-editor#searchPages">
                        <button type="button" data-action="rich-text-editor#searchPages">Search pages</button>
                    </div>
                    <p role="status" data-rich-text-editor-target="pageStatus"></p>
                    <ul data-rich-text-editor-target="pageResults"></ul>
                    <button type="button" data-rich-text-editor-target="pageMore" data-action="rich-text-editor#morePages" hidden>More pages</button>
                </dialog>
            ` : ''}
            ${mosaics ? `
                <dialog id="content-mosaic-dialog" aria-labelledby="content-mosaic-title" data-rich-text-editor-target="mosaicDialog">
                    <h2 id="content-mosaic-title">Content mosaic</h2>
                    <label for="content-mosaic-limit">Number of posts</label>
                    <input id="content-mosaic-limit" type="number" min="1" max="20" step="1" data-rich-text-editor-target="mosaicLimit">
                    <div role="group" aria-labelledby="content-mosaic-section-label">
                        <p id="content-mosaic-section-label">Section</p>
                        <p data-rich-text-editor-target="mosaicSection"></p>
                        <button type="button" data-rich-text-editor-target="mosaicSectionChoose" data-action="rich-text-editor#chooseMosaicSection">Choose section</button>
                        <button type="button" data-rich-text-editor-target="mosaicSectionRemove" data-action="rich-text-editor#removeMosaicSection" hidden>Remove section</button>
                    </div>
                    <div role="group" aria-labelledby="content-mosaic-author-label">
                        <p id="content-mosaic-author-label">Author</p>
                        <p data-rich-text-editor-target="mosaicAuthor"></p>
                        <button type="button" data-rich-text-editor-target="mosaicAuthorChoose" data-action="rich-text-editor#chooseMosaicAuthor">Choose author</button>
                        <button type="button" data-rich-text-editor-target="mosaicAuthorRemove" data-action="rich-text-editor#removeMosaicAuthor" hidden>Remove author</button>
                    </div>
                    <div role="group" aria-labelledby="content-mosaic-tags-label">
                        <p id="content-mosaic-tags-label">Tags</p>
                        <ul data-rich-text-editor-target="mosaicTags"></ul>
                        <button type="button" data-rich-text-editor-target="mosaicTagChoose" data-action="rich-text-editor#chooseMosaicTag">Add tag</button>
                    </div>
                    <button type="button" data-rich-text-editor-target="mosaicSubmit" data-action="rich-text-editor#applyMosaic"></button>
                    <button type="button" commandfor="content-mosaic-dialog" command="close">Cancel</button>
                </dialog>
            ` : ''}
            <p aria-live="polite" data-rich-text-editor-target="status"></p>
            <input type="hidden" name="content" value='${JSON.stringify(document).replaceAll("'", '&#39;')}' data-rich-text-editor-target="input">
        </div>
    `;
};
