import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import { TextSelection } from '@tiptap/pm/state';
import { Document } from '@tiptap/extension-document';
import { Paragraph } from '@tiptap/extension-paragraph';
import { Text } from '@tiptap/extension-text';
import { Heading } from '@tiptap/extension-heading';
import { Bold } from '@tiptap/extension-bold';
import { Blockquote } from '@tiptap/extension-blockquote';
import { BulletList, OrderedList, ListItem } from '@tiptap/extension-list';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';
import { Placeholder, UndoRedo } from '@tiptap/extensions';
import { BlockMover } from '../rich_text/block_mover.js';
import { isHeading, onHeading, topLevelBlocks } from '../rich_text/block_movement.js';
import { EmbeddedImage } from '../rich_text/embedded_image.js';
import { Widget } from '../rich_text/widget.js';
import { PageLink, pageLinksKey } from '../rich_text/page_link.js';
import { Callout } from '../rich_text/callout.js';
import { QuoteSource } from '../rich_text/quote_source.js';
import { SlashMenu } from '../rich_text/slash_menu.js';
import { notifyForm } from '../shared/forms.js';
import { fetchJson } from '../shared/http.js';
import 'prosemirror-view/style/prosemirror.min.css';
import 'prosemirror-tables/style/tables.min.css';

/*
 * Mounts the Tiptap editor of the Shared:RichTextEditor component.
 *
 * The editor only registers what the module allows, so anything else can't
 * be written nor pasted: pasted links or images keep just their text. The
 * server translates the posted document and validates it again; this
 * controller holds no rules of its own.
 *
 * Nothing covers the text: every control lives in one dock at the bottom,
 * the same on every screen, shown only while editing. Everything also works from the keyboard: Alt+F10 reaches
 * the controls and Escape leaves them, "/" on an empty line lists the kinds
 * of block, and Ctrl+Shift+Arrow moves the current block, or its whole
 * section when the cursor is on a heading. The outline of the headings keeps
 * long content manageable.
 */

/** @typedef {import('@tiptap/core').ChainedCommands} Chain */
/** @typedef {import('@tiptap/extension-heading').Level} Level */
/** @typedef {import('../rich_text/slash_menu.js').SlashItem} SlashItem */
/** @typedef {import('@tiptap/suggestion').SuggestionProps<SlashItem, SlashItem>} SlashProps */
/** @typedef {import('../rich_text/page_link.js').PageState} PageState */
/** @typedef {import('./page_field_controller.js').Page} Page */

/**
 * An image as the editor's endpoints describe it.
 *
 * @typedef {import('../rich_text/embedded_image.js').ImageSummary & {id: string}} Image
 */

/**
 * @typedef {import('../rich_text/embedded_image.js').ImageLabels & {none: string, result: string, results: string, error: string}} ImageLabels
 */

/**
 * The words of the page dialog, of links and of widgets; each says only those it needs.
 *
 * @typedef {Partial<Record<string, string>>} PageLabels
 */

/**
 * @typedef {object} Features
 * @property {string[]} blocks
 * @property {Level[]} headingLevels
 * @property {string[]} marks
 */

/** @typedef {{value: string, label: string}} BlockOption */

/** @typedef {{from: number, level: number, title: string}} Section */

/**
 * The menu of kinds of block: opened from its button, which keeps the focus,
 * or by "/" in the text, which the suggestion drives.
 *
 * @typedef {{mode: 'button', button: HTMLElement, items: SlashItem[], index: number}
 *     | {mode: 'slash', button?: undefined, props: SlashProps, items: SlashItem[], index: number}} Menu
 */

/**
 * @typedef {object} Properties
 * @property {AbortController} abortController
 * @property {Editor} editor
 * @property {Menu|null} menu
 * @property {((event: Event) => void)|null} dismiss
 * @property {Map<string, Image>} images
 * @property {boolean} imagesLookedUp
 * @property {Set<string>} unknownImages
 * @property {Set<() => void>} imageViews
 * @property {number} [imageSearchRequest]
 * @property {Map<string, Page>} pages
 * @property {Set<string>} missingPages
 * @property {Set<string>} pagesLookingUp
 * @property {Set<string>} pagesUnknown
 * @property {{from: number, to: number}|null} linkRange
 * @property {'link'|'widget'} pagePurpose
 * @property {Set<() => void>} widgetViews
 * @property {{request: number, page: number, count: number}} pageSearch
 * @property {Section[]} [sections]
 * @property {HTMLElement} frameTarget
 * @property {HTMLElement} canvasTarget
 * @property {HTMLElement} editorTarget
 * @property {HTMLElement} dockTarget
 * @property {HTMLInputElement} inputTarget
 * @property {HTMLElement[]} buttonTargets
 * @property {HTMLElement} blockTypeTarget
 * @property {boolean} hasBlockTypeTarget
 * @property {HTMLElement} tableToolsTarget
 * @property {boolean} hasTableToolsTarget
 * @property {HTMLElement} quoteToolsTarget
 * @property {boolean} hasQuoteToolsTarget
 * @property {HTMLElement} menuTarget
 * @property {HTMLElement} statusTarget
 * @property {HTMLElement} hintTarget
 * @property {HTMLElement} outlineButtonTarget
 * @property {HTMLDialogElement} outlineDialogTarget
 * @property {HTMLElement} outlineDialogListTarget
 * @property {boolean} hasOutlineDialogListTarget
 * @property {HTMLElement} countTarget
 * @property {boolean} hasCountTarget
 * @property {HTMLDialogElement} imageDialogTarget
 * @property {HTMLInputElement} imageSearchTarget
 * @property {HTMLElement} imageStatusTarget
 * @property {HTMLElement} imageResultsTarget
 * @property {HTMLDialogElement} pageDialogTarget
 * @property {HTMLElement} pageDialogTitleTarget
 * @property {HTMLInputElement} pageSearchTarget
 * @property {HTMLElement} pageStatusTarget
 * @property {HTMLElement} pageResultsTarget
 * @property {HTMLElement} pageMoreTarget
 * @property {HTMLElement} linkToolsTarget
 * @property {boolean} hasLinkToolsTarget
 * @property {HTMLElement} linkTargetTarget
 * @property {string} idValue
 * @property {string} placeholderValue
 * @property {string} slashHintValue
 * @property {string} quoteSourceHintValue
 * @property {BlockOption[]} blockOptionsValue
 * @property {string} movedUpValue
 * @property {string} movedDownValue
 * @property {string} sectionMovedUpValue
 * @property {string} sectionMovedDownValue
 * @property {Features} featuresValue
 * @property {Record<string, string>} calloutLabelsValue
 * @property {boolean} singleParagraphValue
 * @property {number} maxLengthValue
 * @property {string} countValue
 * @property {string} countOverValue
 * @property {string} imageSearchUrlValue
 * @property {boolean} hasImageSearchUrlValue
 * @property {string} imageLookupUrlValue
 * @property {boolean} hasImageLookupUrlValue
 * @property {ImageLabels} imageLabelsValue
 * @property {string} pageSearchUrlValue
 * @property {string} pageLookupUrlValue
 * @property {boolean} hasPageLookupUrlValue
 * @property {PageLabels} pageLabelsValue
 * @property {PageLabels} widgetLabelsValue
 * @property {boolean} hasWidgetLabelsValue
 */

const BaseController = /** @type {import('./controller_types.js').ControllerWith<Properties>} */ (Controller);

/**
 * What every control runs, given a command chain.
 *
 * @type {Record<string, (chain: Chain) => Chain>}
 */
const COMMANDS = {
    bold: (chain) => chain.toggleBold(),
    moveUp: (chain) => chain.moveBlock(-1),
    moveDown: (chain) => chain.moveBlock(1),
    insertTable: (chain) => chain.insertTable({ rows: 3, cols: 2, withHeaderRow: true }),
    addRowAfter: (chain) => chain.addRowAfter(),
    addColumnAfter: (chain) => chain.addColumnAfter(),
    deleteRow: (chain) => chain.deleteRow(),
    deleteColumn: (chain) => chain.deleteColumn(),
    deleteTable: (chain) => chain.deleteTable(),
    quoteSource: (chain) => chain.focusQuoteSource(),
    // The whole link goes, wherever in it the cursor stands.
    unlink: (chain) => chain.extendMarkRange('link').unsetMark('link'),
    undo: (chain) => chain.undo(),
    redo: (chain) => chain.redo(),
};

/**
 * Whether a toggle is on for the current selection.
 *
 * @type {Record<string, (editor: Editor) => boolean>}
 */
const ACTIVE = {
    bold: (editor) => editor.isActive('bold'),
};

/**
 * Turns a plain paragraph into each kind of block.
 *
 * @type {Record<string, (chain: Chain, variant: string|undefined) => Chain>}
 */
const BLOCKS = {
    paragraph: (chain) => chain,
    heading: (chain, variant) => chain.setHeading({ level: /** @type {Level} */ (Number(variant)) }),
    quote: (chain) => chain.wrapIn('blockquote'),
    bulletList: (chain) => chain.toggleBulletList(),
    orderedList: (chain) => chain.toggleOrderedList(),
    callout: (chain, variant) => chain.wrapIn('callout', { variant }),
};

/**
 * Lower case and without accents, so "parrafo" finds "Párrafo".
 *
 * @param {string} text
 */
const normalise = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export default class extends BaseController {
    static targets = [
        'frame', 'canvas', 'editor', 'dock', 'input', 'button', 'blockType', 'tableTools', 'quoteTools', 'menu', 'status',
        'hint', 'outlineButton', 'outlineDialog', 'outlineDialogList', 'count',
        'imageDialog', 'imageSearch', 'imageStatus', 'imageResults',
        'pageDialog', 'pageDialogTitle', 'pageSearch', 'pageStatus', 'pageResults', 'pageMore', 'linkTools', 'linkTarget',
    ];

    static values = {
        id: String,
        placeholder: String,
        slashHint: String,
        quoteSourceHint: String,
        blockOptions: Array,
        movedUp: String,
        movedDown: String,
        sectionMovedUp: String,
        sectionMovedDown: String,
        features: Object,
        calloutLabels: Object,
        singleParagraph: Boolean,
        maxLength: Number,
        count: String,
        countOver: String,
        imageSearchUrl: String,
        imageLookupUrl: String,
        imageLabels: Object,
        pageSearchUrl: String,
        pageLookupUrl: String,
        pageLabels: Object,
        widgetLabels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        this.menu = null;
        this.dismiss = null;
        // What the CMS knows of each image embedded or picked, by id.
        this.images = new Map();
        this.imagesLookedUp = false;
        // The ids of the images whose lookup failed.
        this.unknownImages = new Set();
        this.imageViews = new Set();
        // What the CMS knows of each page linked or picked, by id; the ids
        // looked up and found to be of no page; those being looked up; and
        // those whose lookup failed.
        this.pages = new Map();
        this.missingPages = new Set();
        this.pagesLookingUp = new Set();
        this.pagesUnknown = new Set();
        this.linkRange = null;
        // What the page dialog picks a page for: 'link' or 'widget'.
        this.pagePurpose = 'link';
        this.widgetViews = new Set();
        // Each search outdates the ones before: only the latest is shown.
        this.pageSearch = { request: 0, page: 1, count: 0 };
        this.editor = new Editor({
            element: this.editorTarget,
            extensions: this.extensions(),
            content: JSON.parse(this.inputTarget.value),
            editorProps: {
                attributes: {
                    id: this.idValue,
                    class: this.singleParagraphValue ? 'rich-text rich-text-editable rich-text-single' : 'rich-text rich-text-editable',
                    role: 'textbox',
                    'aria-multiline': String(!this.singleParagraphValue),
                    'aria-labelledby': `${this.idValue}-label`,
                    'aria-describedby': this.hasCountTarget ? `${this.idValue}-hint ${this.idValue}-count` : `${this.idValue}-hint`,
                },
                // Keep the cursor clear of the dock when scrolling it into view;
                // sideways, as in a wide table, ProseMirror's own defaults.
                scrollMargin: { top: 80, right: 5, bottom: 220, left: 5 },
                scrollThreshold: { top: 80, right: 0, bottom: 220, left: 0 },
            },
            onUpdate: ({ editor }) => {
                this.inputTarget.value = JSON.stringify(editor.getJSON());
                // Setting the value fires no event, so tell whoever watches the form.
                notifyForm(this.inputTarget);
                this.refreshOutline();
                this.refreshCount();
                // Text pasted or brought back by undo may link pages not looked up yet.
                this.lookUpPages();
            },
            onTransaction: () => this.refreshControls(),
            onFocus: () => this.refreshControls(),
        });

        this.frameTarget.hidden = false;
        this.hintTarget.hidden = false;
        this.refreshOutline();
        this.refreshControls();
        this.refreshCount();
        this.lookUpImages();
        this.lookUpPages();
    }

    /* Images: embedded by id, shown as the CMS knows them now. */

    /** Looks up every image the document embeds, then draws them. */
    async lookUpImages() {
        if (!this.hasImageLookupUrlValue) {
            return;
        }

        /** @type {string[]} */
        const ids = [];

        this.editor.state.doc.descendants((node) => {
            if (node.type.name === 'image' && node.attrs.id) {
                ids.push(node.attrs.id);
            }
        });

        if (ids.length > 0) {
            const { signal } = this.abortController;
            const url = new URL(this.imageLookupUrlValue, window.location.href);
            url.searchParams.set('ids', ids.join(','));
            /** @type {Image[]|null} */
            let images = null;

            try {
                images = (/** @type {{images: Image[]}} */ (await fetchJson(url, signal))).images;
            } catch {
                // Told as unknown below: the images may well exist.
            }

            if (signal.aborted) {
                return;
            }

            if (images === null) {
                ids.forEach((id) => this.unknownImages.add(id));
            } else {
                for (const image of images) {
                    this.images.set(image.id, image);
                }
            }
        }

        this.imagesLookedUp = true;
        this.drawImages();
    }

    /**
     * What the CMS knows of an image: undefined while it is being looked up,
     * null when there is no such image, false when it can't be checked.
     *
     * @param {string} id
     * @returns {Image|null|false|undefined}
     */
    describeImage(id) {
        if (this.images.has(id)) {
            return this.images.get(id);
        }

        if (this.unknownImages.has(id)) {
            return false;
        }

        return this.imagesLookedUp ? null : undefined;
    }

    drawImages() {
        for (const draw of this.imageViews) {
            draw();
        }
    }

    openImageDialog() {
        this.imageDialogTarget.showModal();
        this.imageSearchTarget.focus();
        this.searchImages();
    }

    /** @param {Event} [event] */
    async searchImages(event) {
        event?.preventDefault();

        const request = (this.imageSearchRequest ?? 0) + 1;
        this.imageSearchRequest = request;
        const { signal } = this.abortController;
        const url = new URL(this.imageSearchUrlValue, window.location.href);
        url.searchParams.set('alt', this.imageSearchTarget.value);
        /** @type {Image[]|null} */
        let images = null;

        try {
            images = (/** @type {{images: Image[]}} */ (await fetchJson(url, signal))).images;
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out, or the editor was disconnected meanwhile.
        if (signal.aborted || request !== this.imageSearchRequest) {
            return;
        }

        const { none, result, results, error } = this.imageLabelsValue;

        if (images === null) {
            this.imageStatusTarget.textContent = error;

            return;
        }

        this.imageResultsTarget.replaceChildren(...images.map((image) => this.imageResult(image)));
        this.imageStatusTarget.textContent = [none, result][images.length] ?? results.replace('%count%', String(images.length));
    }

    /**
     * One image to pick: the whole row is its button, named by its text and status.
     *
     * @param {Image} image
     */
    imageResult(image) {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-text-image-option';
        button.addEventListener('click', () => this.embedImage(image));

        const thumbnail = document.createElement('img');
        thumbnail.src = image.thumbnail;
        thumbnail.alt = '';
        thumbnail.width = 112;
        thumbnail.height = 63;
        thumbnail.loading = 'lazy';

        const text = document.createElement('span');
        text.className = 'rich-text-image-option-text';
        const alt = document.createElement('span');
        alt.className = 'font-bold';
        alt.textContent = image.alt;
        const status = document.createElement('span');
        status.className = 'rich-text-image-status';
        status.textContent = image.status;
        text.append(alt, status);

        button.append(thumbnail, text);
        item.append(button);

        return item;
    }

    /** @param {Image} image */
    embedImage(image) {
        this.images.set(image.id, image);
        this.imageDialogTarget.close();
        this.editor.chain().focus().insertContent({ type: 'image', attrs: { id: image.id } }).run();
        this.drawImages();
    }

    /* Page links: applied to the selected text, with their targets looked up by id. */

    /** Looks up the pages the document links to, or shows in widgets, that the editor doesn't know yet. */
    async lookUpPages() {
        if (!this.hasPageLookupUrlValue) {
            return;
        }

        /** @type {Set<string>} */
        const ids = new Set();

        /** @param {string|null|undefined} id */
        const unknown = (id) => Boolean(id) && this.pageState(id ?? '') === 'loading' && !this.pagesLookingUp.has(id ?? '');

        this.editor.state.doc.descendants((node) => {
            if (node.type.name === 'widget' && unknown(node.attrs.id)) {
                ids.add(node.attrs.id);
            }

            for (const mark of node.marks) {
                if (mark.type.name === 'link' && unknown(mark.attrs.id)) {
                    ids.add(mark.attrs.id);
                }
            }
        });

        if (ids.size === 0) {
            return;
        }

        ids.forEach((id) => this.pagesLookingUp.add(id));
        const { signal } = this.abortController;
        const url = new URL(this.pageLookupUrlValue, window.location.href);
        url.searchParams.set('ids', [...ids].join(','));
        /** @type {Page[]|null} */
        let found = null;

        try {
            found = (/** @type {{pages: Page[]}} */ (await fetchJson(url, signal))).pages;
        } catch {
            // Told as unknown below: the page may well exist.
        }

        if (signal.aborted) {
            return;
        }

        for (const id of ids) {
            this.pagesLookingUp.delete(id);
        }

        if (found === null) {
            ids.forEach((id) => this.pagesUnknown.add(id));
        } else {
            for (const page of found) {
                this.pages.set(page.id, page);
                ids.delete(page.id);
            }

            ids.forEach((id) => this.missingPages.add(id));
        }

        this.redrawPageLinks();
    }

    /**
     * How the page of a link stands, as far as the editor knows.
     *
     * @param {string} id
     * @returns {PageState}
     */
    pageState(id) {
        const page = this.pages.get(id);

        if (page) {
            return page.published ? 'published' : 'offSite';
        }

        if (this.missingPages.has(id)) {
            return 'missing';
        }

        return this.pagesUnknown.has(id) ? 'unknown' : 'loading';
    }

    /**
     * The page a link goes to, in words: never by its look alone.
     *
     * @param {string} id
     */
    describePage(id) {
        const labels = this.pageLabelsValue;
        const state = this.pageState(id);
        const page = this.pages.get(id);

        // Loading, missing or unknown: there is no page to name yet.
        if (page === undefined) {
            return labels[state] ?? '';
        }

        const target = (labels.target ?? '%title% %status%').replace('%title%', page.title).replace('%status%', page.status);

        return state === 'offSite' ? `${target} ${labels.offSite ?? ''}` : target;
    }

    /**
     * How the page of a widget stands, and the page itself once known.
     *
     * @param {string} id
     */
    describeWidget(id) {
        return { state: this.pageState(id), page: this.pages.get(id) };
    }

    /** Redraws the widgets, the decorations of the links, and the tools of the one under the cursor. */
    redrawPageLinks() {
        if (this.editor.isDestroyed) {
            return;
        }

        for (const draw of this.widgetViews) {
            draw();
        }

        // A transaction that changes nothing but redraws, which also refreshes the controls.
        this.editor.view.dispatch(this.editor.state.tr.setMeta(pageLinksKey, true).setMeta('addToHistory', false));
    }

    /**
     * Picks a page to link the selected text to, or, for 'widget', to show in a new widget.
     *
     * @param {'link'|'widget'} [purpose]
     */
    openPageDialog(purpose = 'link') {
        if (purpose === 'link') {
            const { selection } = this.editor.state;

            if (!(selection instanceof TextSelection) || selection.empty) {
                return;
            }

            // The dialog takes the focus; the range keeps the selected text intact.
            this.linkRange = { from: selection.from, to: selection.to };
        }

        this.pagePurpose = purpose;
        this.pageDialogTitleTarget.textContent = this.pagePurposeLabels().title ?? '';
        this.pageDialogTarget.showModal();
        this.pageSearchTarget.focus();
        this.searchPages();
    }

    /**
     * Lists the first pages of results for the title written.
     *
     * @param {Event} [event]
     */
    searchPages(event) {
        event?.preventDefault();
        this.pageSearch.page = 1;
        this.pageSearch.count = 0;
        this.pageResultsTarget.replaceChildren();
        this.loadPages();
    }

    /** Adds the next pages of results to those listed. */
    morePages() {
        this.pageSearch.page += 1;
        this.loadPages();
    }

    async loadPages() {
        const request = ++this.pageSearch.request;
        const { signal } = this.abortController;
        const url = new URL(this.pageSearchUrlValue, window.location.href);
        url.searchParams.set('title', this.pageSearchTarget.value);
        url.searchParams.set('page', String(this.pageSearch.page));
        this.pageMoreTarget.hidden = true;
        /** @type {{pages: Page[], more: boolean}|null} */
        let result = null;

        try {
            result = /** @type {{pages: Page[], more: boolean}} */ (await fetchJson(url, signal));
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out, or the editor was disconnected meanwhile.
        if (signal.aborted || request !== this.pageSearch.request) {
            return;
        }

        const { none, error } = this.pageLabelsValue;
        const { result: one, results } = this.pagePurposeLabels();

        if (result === null) {
            this.pageStatusTarget.textContent = error ?? '';

            return;
        }

        const first = this.pageResultsTarget.children.length;
        this.pageResultsTarget.append(...result.pages.map((page) => this.pageResult(page)));
        this.pageSearch.count += result.pages.length;
        this.pageMoreTarget.hidden = !result.more;

        const { count } = this.pageSearch;
        this.pageStatusTarget.textContent = [none, one][count] ?? (results ?? '').replace('%count%', String(count));

        // Past the first pages, the focus goes to the first page added, not back to the top.
        if (this.pageSearch.page > 1) {
            this.pageResultsTarget.children[first]?.querySelector('button')?.focus();
        }
    }

    /**
     * One page to pick: the whole row is its button, named by its title and status.
     *
     * @param {Page} page
     */
    pageResult(page) {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-text-page-option';
        button.addEventListener('click', () => (this.pagePurpose === 'widget' ? this.embedWidget(page) : this.linkPage(page)));

        const text = document.createElement('span');
        text.className = 'rich-text-page-option-text';
        const title = document.createElement('span');
        title.className = 'font-bold';
        title.textContent = page.title;
        const status = document.createElement('span');
        status.className = 'rich-text-page-status';
        status.textContent = page.status;
        text.append(title, status);

        if (!page.published) {
            const offSite = document.createElement('span');
            offSite.className = 'rich-text-page-note';
            offSite.textContent = this.pagePurposeLabels().offSite ?? '';
            text.append(offSite);
        }

        button.append(text);
        item.append(button);

        return item;
    }

    /** @param {Page} page */
    linkPage(page) {
        if (this.linkRange === null) {
            return;
        }

        this.pages.set(page.id, page);
        this.missingPages.delete(page.id);
        this.pagesUnknown.delete(page.id);
        this.pageDialogTarget.close();
        this.editor.chain().focus().setTextSelection(this.linkRange).setMark('link', { id: page.id }).run();
        this.linkRange = null;
        this.redrawPageLinks();
    }

    /** The labels of the dialog that depend on what it picks a page for. */
    pagePurposeLabels() {
        return this.pagePurpose === 'widget' ? this.widgetLabelsValue : this.pageLabelsValue;
    }

    /**
     * Shows the page picked in a new widget, where the cursor was.
     *
     * @param {Page} page
     */
    embedWidget(page) {
        this.pages.set(page.id, page);
        this.missingPages.delete(page.id);
        this.pagesUnknown.delete(page.id);
        this.pageDialogTarget.close();
        this.editor.chain().focus().insertContent({ type: 'widget', attrs: { kind: 'related_page', id: page.id } }).run();
        this.redrawPageLinks();
    }

    /** Takes the link under the cursor out, keeping its text, and says so. */
    unlink() {
        COMMANDS.unlink(this.editor.chain().focus()).run();
        this.announce(this.pageLabelsValue.removed ?? '');
    }

    /**
     * Characters written against the most it takes, said in words. Not a live
     * region: announcing every keystroke would drown the typing.
     */
    refreshCount() {
        if (!this.hasCountTarget) {
            return;
        }

        const count = [...this.editor.state.doc.textContent].length;
        const message = count > this.maxLengthValue ? this.countOverValue : this.countValue;

        this.countTarget.textContent = message
            .replace('%count%', String(count))
            .replace('%max%', String(this.maxLengthValue))
            .replace('%over%', String(count - this.maxLengthValue));
    }

    disconnect() {
        this.abortController.abort();
        this.closeMenu();
        this.editor?.destroy();
    }

    /**
     * Clicking a control keeps the focus, and the selection, in the editor,
     * so typing right after a click lands in the text.
     *
     * @param {Event} event
     */
    keepFocus(event) {
        event.preventDefault();
    }

    /** @param {Event} event */
    run(event) {
        const button = /** @type {HTMLElement} */ (event.currentTarget);
        const command = button.dataset.command ?? '';

        if (button.getAttribute('aria-disabled') === 'true') {
            return;
        }

        const section = onHeading(this.editor.state);

        // Embedding an image starts by picking it.
        if (command === 'insertImage') {
            this.openImageDialog();

            return;
        }

        // A widget starts by picking the page it shows.
        if (command === 'insertWidget') {
            this.openPageDialog('widget');

            return;
        }

        // A link starts by picking its page, and only wraps selected text.
        if (command === 'insertLink') {
            this.openPageDialog();

            return;
        }

        // Another page for the whole link under the cursor, whatever is selected.
        if (command === 'changeLink') {
            this.editor.chain().focus().extendMarkRange('link').run();
            this.openPageDialog();

            return;
        }

        if (command === 'unlink') {
            this.unlink();

            return;
        }

        COMMANDS[command](this.editor.chain().focus()).run();

        if (command === 'moveUp' || command === 'moveDown') {
            this.announceMove(command === 'moveUp' ? -1 : 1, section);
        }
    }

    /* Controls: shown while editing, out of the way otherwise. */

    refreshControls() {
        const editing = this.editor.isFocused || this.canvasTarget.contains(document.activeElement);

        // Only shown here: leave() hides them, once it knows where the focus went.
        if (editing) {
            this.dockTarget.hidden = false;
        }

        const inTable = this.editor.isActive('table');
        const section = onHeading(this.editor.state);

        for (const button of this.buttonTargets) {
            const command = button.dataset.command ?? '';
            const pressed = command in ACTIVE && ACTIVE[command](this.editor);

            if (button.hasAttribute('aria-pressed')) {
                button.setAttribute('aria-pressed', String(pressed));
            }

            // A toggle that is on stays available, so it reads as on, not as unavailable.
            button.setAttribute('aria-disabled', String(!pressed && !this.can(command)));

            if (button.dataset.sectionLabel) {
                this.rename(button, (section ? button.dataset.sectionLabel : button.dataset.label) ?? '');
            }
        }

        // A single paragraph has no kind of block to pick.
        if (this.hasBlockTypeTarget) {
            const kind = this.blockOptionsValue.find((option) => option.value === this.currentBlock())?.label ?? '';

            // Table cells only hold paragraphs.
            this.blockTypeTarget.setAttribute('aria-disabled', String(inTable));
            this.rename(this.blockTypeTarget, kind);
        }

        if (this.hasTableToolsTarget) {
            this.tableToolsTarget.hidden = !inTable;
        }

        if (this.hasQuoteToolsTarget) {
            this.quoteToolsTarget.hidden = !this.editor.isActive('blockquote');
        }

        if (this.hasLinkToolsTarget) {
            const inLink = this.editor.isActive('link');
            this.linkToolsTarget.hidden = !inLink;

            if (inLink) {
                this.linkTargetTarget.textContent = this.describePage(this.editor.getAttributes('link').id);
            }
        }

        this.markCurrentSection();
    }

    /**
     * Hides the controls once the focus leaves the text and the controls alike.
     *
     * @param {FocusEvent} event
     */
    leave(event) {
        if (event.relatedTarget instanceof Node && this.canvasTarget.contains(event.relatedTarget)) {
            return;
        }

        this.closeMenu();
        this.dockTarget.hidden = true;
    }

    /**
     * Escape, from any control, goes back to the text where the cursor was.
     *
     * @param {KeyboardEvent} event
     */
    controlsKey(event) {
        if (event.key === 'Escape' && event.target instanceof Node && this.dockTarget.contains(event.target)) {
            event.preventDefault();
            this.editor.commands.focus();
        }
    }

    /** Alt+F10: the first control of the dock, the kind of the current block when there is one. */
    focusControls() {
        this.dockTarget.hidden = false;
        (this.hasBlockTypeTarget ? this.blockTypeTarget : this.buttonTargets[0])?.focus();
    }

    /**
     * @param {HTMLElement} button
     * @param {string} name
     */
    rename(button, name) {
        const label = button.querySelector('[data-role="name"]');
        const tooltip = button.querySelector('[data-role="tooltip"]');

        if (label) {
            label.textContent = name;
        }

        if (tooltip) {
            tooltip.textContent = name;
        }
    }

    /** @param {string} command */
    can(command) {
        if (command === 'changeLink' || command === 'unlink') {
            return this.editor.isActive('link');
        }

        // Links wrap existing text: with no text selected there is nothing to link.
        if (command === 'insertLink') {
            const { selection } = this.editor.state;

            return selection instanceof TextSelection
                && !selection.empty
                && this.editor.can().setMark('link', { id: 'page' });
        }

        // Images and widgets go between blocks, never inside a table.
        if (command === 'insertImage' || command === 'insertWidget') {
            return !this.editor.isActive('table');
        }

        // The header row names the columns, so it can't be removed on its own.
        if (command === 'deleteRow' && this.editor.isActive('tableHeader')) {
            return false;
        }

        return COMMANDS[command](this.editor.can().chain()).run();
    }

    currentBlock() {
        if (this.editor.isActive('callout')) {
            return `callout:${this.editor.getAttributes('callout').variant}`;
        }

        if (this.editor.isActive('heading')) {
            return `heading:${this.editor.getAttributes('heading').level}`;
        }

        /** @type {Record<string, string>} */
        const wrapper = { blockquote: 'quote', bulletList: 'bulletList', orderedList: 'orderedList' };
        const active = Object.keys(wrapper).find((name) => this.editor.isActive(name));

        return active ? wrapper[active] : 'paragraph';
    }

    /**
     * Says where the block or the section went, since nothing changes under the cursor.
     *
     * @param {import('../rich_text/block_movement.js').Direction} direction
     * @param {boolean} section
     */
    announceMove(direction, section) {
        const message = section
            ? (direction < 0 ? this.sectionMovedUpValue : this.sectionMovedDownValue)
            : (direction < 0 ? this.movedUpValue : this.movedDownValue);

        this.announce(message);
    }

    /**
     * Says something through the live region, even the same thing twice in a row.
     *
     * @param {string} message
     */
    announce(message) {
        this.statusTarget.textContent = '';
        window.requestAnimationFrame(() => {
            this.statusTarget.textContent = message;
        });
    }

    /* Kinds of block: one menu, opened by "/" or by the block kind button. */

    /**
     * @param {Chain} chain
     * @param {string} value
     */
    applyBlock(chain, value) {
        const [block, variant] = value.split(':');

        if (block === 'table') {
            COMMANDS.insertTable(chain).run();

            return;
        }

        if (block === 'image') {
            chain.run();
            this.openImageDialog();

            return;
        }

        if (block === 'widget') {
            chain.run();
            this.openPageDialog('widget');

            return;
        }

        // Every kind starts from a plain paragraph, out of whatever wraps it.
        if (this.editor.isActive('bulletList')) {
            chain = chain.toggleBulletList();
        } else if (this.editor.isActive('orderedList')) {
            chain = chain.toggleOrderedList();
        }

        for (const wrapper of ['blockquote', 'callout']) {
            if (this.editor.isActive(wrapper)) {
                chain = chain.lift(wrapper);
            }
        }

        BLOCKS[block](chain.setParagraph(), variant).run();
    }

    /**
     * @param {string} query
     * @returns {SlashItem[]}
     */
    blockItems(query) {
        const current = this.currentBlock();
        const items = this.blockOptionsValue.map((option) => ({
            label: option.label,
            current: option.value === current,
            apply: () => this.applyBlock(this.editor.chain().focus(), option.value),
        }));

        return items.filter((item) => normalise(item.label).includes(normalise(query)));
    }

    /** @param {Event} event */
    openBlockMenu(event) {
        const button = /** @type {HTMLElement} */ (event.currentTarget);

        if (button.getAttribute('aria-disabled') === 'true') {
            return;
        }

        if (this.menu?.button === button) {
            this.closeMenu();

            return;
        }

        const items = this.blockItems('');
        /** @type {Menu} */
        const menu = { mode: 'button', button, items, index: Math.max(items.findIndex((item) => item.current), 0) };

        this.menu = menu;
        button.setAttribute('aria-expanded', 'true');
        this.renderMenu(button.getBoundingClientRect());
        this.menuTarget.focus();
        this.watchOutside();
    }

    /**
     * Like any popup, the menu closes when the pointer goes down anywhere else,
     * the text included, and when the page scrolls or resizes, which would
     * leave it floating away from its button.
     */
    watchOutside() {
        /** @param {Event} event */
        this.dismiss = (event) => {
            const target = event.target instanceof Node ? event.target : null;

            if (this.menuTarget.contains(target) || this.menu?.button?.contains(target)) {
                return;
            }

            this.closeMenu();
        };

        document.addEventListener('pointerdown', this.dismiss, true);
        window.addEventListener('scroll', this.dismiss, { capture: true, passive: true });
        window.addEventListener('resize', this.dismiss);
    }

    /**
     * The focus left the menu for something else, as Tab does.
     *
     * @param {FocusEvent} event
     */
    menuFocusOut(event) {
        const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;

        if (this.menu?.mode !== 'button' || this.menuTarget.contains(next) || next === this.menu.button) {
            return;
        }

        this.closeMenu();
    }

    /**
     * Keys of the menu opened from the button, which holds the focus meanwhile.
     *
     * @param {KeyboardEvent} event
     */
    menuButtonKey(event) {
        if (this.menu?.mode === 'button' && this.menuKey(event)) {
            event.preventDefault();
        }
    }

    /*
     * The "/" menu follows the combobox pattern: the focus stays in the text,
     * which points at the highlighted option through aria-activedescendant.
     */
    slashRender() {
        /** @type {ReturnType<import('../rich_text/slash_menu.js').SlashRender>} */
        const renderer = {
            onStart: (props) => this.openSlashMenu(props),
            onUpdate: (props) => this.openSlashMenu(props),
            onKeyDown: ({ event }) => this.menuKey(event),
            onExit: () => this.closeMenu(),
        };

        return renderer;
    }

    /**
     * The kinds of block, then the blocks inserted whole, named as their buttons are.
     *
     * @param {string} query
     */
    slashItems(query) {
        const items = this.blockItems(query);
        const { blocks } = this.featuresValue;
        const inserted = [
            { block: 'table', command: 'insertTable', allowed: blocks.includes('table') },
            { block: 'image', command: 'insertImage', allowed: this.hasImageSearchUrlValue && blocks.includes('image') },
            { block: 'widget', command: 'insertWidget', allowed: this.hasWidgetLabelsValue && blocks.includes('widget') },
        ];

        for (const { block, command, allowed } of inserted) {
            const label = this.buttonTargets.find((button) => button.dataset.command === command)?.dataset.label;

            if (allowed && label !== undefined && normalise(label).includes(normalise(query))) {
                items.push({ label, apply: () => this.applyBlock(this.editor.chain().focus(), block) });
            }
        }

        return items;
    }

    /** @param {SlashProps} props */
    openSlashMenu(props) {
        if (props.items.length === 0) {
            this.closeMenu();

            return;
        }

        const index = this.menu?.mode === 'slash' ? Math.min(this.menu.index, props.items.length - 1) : 0;

        /** @type {Menu} */
        const menu = { mode: 'slash', props, items: props.items, index };

        this.menu = menu;
        this.editor.view.dom.setAttribute('aria-controls', this.menuTarget.id);
        this.editor.view.dom.setAttribute('aria-expanded', 'true');
        this.renderMenu(props.clientRect?.());
    }

    /** @param {DOMRect|null|undefined} anchor */
    renderMenu(anchor) {
        if (this.menu === null) {
            return;
        }

        this.menuTarget.replaceChildren(...this.menu.items.map((item, index) => {
            const option = document.createElement('li');

            option.id = `${this.idValue}-menu-${index}`;
            option.setAttribute('role', 'option');
            option.className = 'rich-text-menu-option';
            option.textContent = item.label;
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => this.choose(index));

            return option;
        }));

        this.menuTarget.hidden = false;

        if (anchor) {
            const height = this.menuTarget.offsetHeight;
            const below = anchor.bottom + 8 + height <= window.innerHeight;
            const left = Math.min(anchor.left, window.innerWidth - this.menuTarget.offsetWidth - 16);

            this.menuTarget.style.top = `${below ? anchor.bottom + 8 : Math.max(anchor.top - height - 8, 8)}px`;
            this.menuTarget.style.left = `${Math.max(left, 16)}px`;
        }

        this.highlight(this.menu.index);
    }

    /**
     * Arrow keys move through the kinds; Enter picks one; Escape closes.
     *
     * @param {KeyboardEvent} event
     */
    menuKey(event) {
        if (this.menu === null) {
            return false;
        }

        const count = this.menu.items.length;
        /** @type {Record<string, number>} */
        const moves = { ArrowDown: 1, ArrowUp: -1 };

        if (event.key in moves) {
            this.highlight((this.menu.index + moves[event.key] + count) % count);

            return true;
        }

        if (event.key === 'Home' || event.key === 'End') {
            this.highlight(event.key === 'Home' ? 0 : count - 1);

            return true;
        }

        // In the "/" menu Tab picks, as editors do; leaving the button's menu
        // with Tab must not change the block, so there it just closes it.
        if (event.key === 'Enter' || (event.key === 'Tab' && this.menu.mode === 'slash')) {
            this.choose(this.menu.index);

            return true;
        }

        if (event.key === 'Escape' || event.key === 'Tab') {
            const { button } = this.menu;

            // Focus first: hiding the menu while it holds the focus would drop it to the page.
            button?.focus();
            this.closeMenu();

            return true;
        }

        return false;
    }

    /** @param {number} index */
    highlight(index) {
        if (this.menu === null) {
            return;
        }

        this.menu.index = index;

        for (const [position, option] of [...this.menuTarget.children].entries()) {
            option.setAttribute('aria-selected', String(position === index));
        }

        this.menuTarget.children[index]?.scrollIntoView({ block: 'nearest' });

        const owner = this.menu.mode === 'slash' ? this.editor.view.dom : this.menuTarget;

        owner.setAttribute('aria-activedescendant', `${this.idValue}-menu-${index}`);
    }

    /** @param {number} index */
    choose(index) {
        const { menu } = this;

        if (menu === null) {
            return;
        }

        if (menu.mode === 'slash') {
            menu.props.command(menu.items[index]);

            return;
        }

        // Back to the text before the menu hides, so the focus never falls to
        // the page, which would read as leaving the editor and hide the dock.
        this.editor.view.focus();
        this.closeMenu();
        menu.items[index].apply();
    }

    closeMenu() {
        if (this.menu === null) {
            return;
        }

        this.menu.button?.setAttribute('aria-expanded', 'false');
        this.menu = null;
        this.menuTarget.hidden = true;

        if (this.dismiss) {
            document.removeEventListener('pointerdown', this.dismiss, true);
            window.removeEventListener('scroll', this.dismiss, { capture: true });
            window.removeEventListener('resize', this.dismiss);
            this.dismiss = null;
        }

        this.menuTarget.removeAttribute('aria-activedescendant');
        this.editor.view.dom.setAttribute('aria-expanded', 'false');
        this.editor.view.dom.removeAttribute('aria-activedescendant');
    }

    /* Outline: every section one step away, the current one marked. */

    refreshOutline() {
        // A single paragraph has no sections.
        if (!this.hasOutlineDialogListTarget) {
            this.sections = [];

            return;
        }

        const sections = topLevelBlocks(this.editor.state.doc)
            .filter(({ node }) => isHeading(node) && node.textContent.trim() !== '')
            .map(({ node, from }) => ({ from, level: Number(node.attrs.level), title: node.textContent }));

        this.sections = sections;

        for (const list of [this.outlineDialogListTarget]) {
            list.replaceChildren(...sections.map((section) => {
                const item = document.createElement('li');
                const link = document.createElement('button');

                link.type = 'button';
                link.className = `rich-text-outline-item ${section.level > 2 ? 'pl-6' : 'pl-2'}`;
                link.textContent = section.title;
                link.addEventListener('click', () => this.jumpTo(section.from));
                item.append(link);

                return item;
            }));
        }

        this.outlineButtonTarget.hidden = sections.length === 0;
        this.markCurrentSection();
    }

    markCurrentSection() {
        if (!this.sections || !this.hasOutlineDialogListTarget) {
            return;
        }

        const { from } = this.editor.state.selection;
        const current = this.sections.findLastIndex((section) => section.from <= from);

        for (const list of [this.outlineDialogListTarget]) {
            for (const [index, item] of [...list.children].entries()) {
                if (index === current) {
                    item.firstElementChild?.setAttribute('aria-current', 'location');
                } else {
                    item.firstElementChild?.removeAttribute('aria-current');
                }
            }
        }
    }

    /**
     * Opens the section at the top of the screen, with the cursor at its heading.
     *
     * @param {number} from
     */
    jumpTo(from) {
        this.outlineDialogTarget.close();
        this.editor.chain().setTextSelection(from + 1).focus(null, { scrollIntoView: false }).run();
        const heading = this.editor.view.nodeDOM(from);

        if (!(heading instanceof Element)) {
            return;
        }

        heading.scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' });
    }

    /** @returns {import('@tiptap/core').AnyExtension[]} */
    extensions() {
        const { blocks, headingLevels, marks } = this.featuresValue;

        if (this.singleParagraphValue) {
            // The document is one paragraph, so Enter has nowhere to split it.
            /** @type {import('@tiptap/core').AnyExtension[]} */
            const extensions = [
                Document.extend({ content: 'paragraph' }),
                Paragraph,
                Text,
                UndoRedo,
                Placeholder.configure({ placeholder: this.placeholderValue }),
            ];

            if (marks.includes('bold')) {
                extensions.push(Bold);
            }

            if (marks.includes('link') && this.hasPageLookupUrlValue) {
                extensions.push(PageLink.configure({ stateOf: (id) => this.pageState(id) }));
            }

            return extensions;
        }

        /** @type {import('@tiptap/core').AnyExtension[]} */
        const extensions = [
            Document,
            Paragraph,
            Text,
            UndoRedo,
            BlockMover.configure({
                onMove: (direction, section) => this.announceMove(direction, section),
                onControlsRequest: () => this.focusControls(),
            }),
            SlashMenu.configure({ items: (query) => this.slashItems(query), render: () => this.slashRender() }),
            // The empty document says what goes in it; an empty line, how to pick a block.
            Placeholder.configure({
                placeholder: ({ editor, node }) => {
                    if (editor.isEmpty) {
                        return this.placeholderValue;
                    }

                    return node.type.name === 'paragraph' ? this.slashHintValue : '';
                },
            }),
        ];

        if (marks.includes('bold')) {
            extensions.push(Bold);
        }

        if (marks.includes('link') && this.hasPageLookupUrlValue) {
            extensions.push(PageLink.configure({ stateOf: (id) => this.pageState(id) }));
        }

        if (blocks.includes('heading')) {
            extensions.push(Heading.configure({ levels: headingLevels }));
        }

        if (blocks.includes('quote')) {
            extensions.push(Blockquote.extend({ content: 'paragraph+ quoteSource?' }), QuoteSource.configure({ hint: this.quoteSourceHintValue }));
        }

        if (blocks.includes('list')) {
            // Lists are not nested: every item is a single paragraph.
            extensions.push(BulletList, OrderedList, ListItem.extend({ content: 'paragraph' }));
        }

        if (blocks.includes('callout')) {
            extensions.push(Callout.configure({ labels: this.calloutLabelsValue }));
        }

        if (blocks.includes('image') && this.hasImageLookupUrlValue) {
            extensions.push(EmbeddedImage.configure({
                describe: (id) => this.describeImage(id),
                views: this.imageViews,
                labels: this.imageLabelsValue,
            }));
        }

        if (blocks.includes('widget') && this.hasWidgetLabelsValue && this.hasPageLookupUrlValue) {
            extensions.push(Widget.configure({
                describe: (id) => this.describeWidget(id),
                views: this.widgetViews,
                labels: this.widgetLabelsValue,
            }));
        }

        if (blocks.includes('table')) {
            extensions.push(
                // Columns keep a readable width; a table wider than the screen scrolls sideways.
                Table.configure({ resizable: false, cellMinWidth: 144 }),
                TableRow,
                TableHeader.extend({ content: 'paragraph' }),
                TableCell.extend({ content: 'paragraph' }),
            );
        }

        return extensions;
    }
}
