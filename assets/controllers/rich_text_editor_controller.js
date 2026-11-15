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
import { ControlsShortcut } from '../rich_text/controls_shortcut.js';
import { movedMessage, onHeading } from '../rich_text/block_movement.js';
import { currentKind, matches, menuAction, menuPosition } from '../rich_text/block_menu.js';
import { linkTarget, pageStateOf, targetsIn } from '../rich_text/pages.js';
import { maxLengthMessage } from '../form/character_count.js';
import { currentSection, sectionsOf } from '../rich_text/outline.js';
import { EmbeddedImage } from '../rich_text/embedded_image.js';
import { Widget } from '../rich_text/widget.js';
import { PageLink } from '../rich_text/page_link.js';
import { TargetLookups } from '../rich_text/targets.js';
import { Callout } from '../rich_text/callout.js';
import { QuoteSource } from '../rich_text/quote_source.js';
import { SlashMenu } from '../rich_text/slash_menu.js';
import { notifyForm } from '../shared/forms.js';
import { Lookups, MISSING } from '../shared/lookup.js';
import { imageOption, pageOption } from '../shared/options.js';
import { Search } from '../shared/search.js';

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
/** @typedef {import('./resource_field_controller.js').Resource} Page */
/** @typedef {import('../rich_text/targets.js').LinkTarget} LinkTarget */

/**
 * An image as the editor's endpoints describe it.
 *
 * @typedef {import('../rich_text/embedded_image.js').ImageSummary & {id: string}} Image
 */

/**
 * @typedef {import('../rich_text/embedded_image.js').ImageLabels & {none: string, result: string, results: string, error: string}} ImageLabels
 */

/**
 * The words of the page dialog that change with what it picks a page for.
 *
 * @typedef {object} PickLabels
 * @property {string} title
 * @property {string} offSite
 * @property {string} result
 * @property {string} results
 */

/**
 * The words of links, and of the page dialog when it picks a page to link.
 *
 * @typedef {PickLabels & {loading: string, missing: string, unknown: string, target: string, none: string, error: string, removed: string}} PageLabels
 */

/**
 * The words of widgets, and of the page dialog when it picks a page to show in one.
 *
 * @typedef {PickLabels & {label: string, loading: string, missing: string, unknown: string}} WidgetLabels
 */

/**
 * @typedef {object} Features
 * @property {string[]} blocks
 * @property {Level[]} headingLevels
 * @property {string[]} marks
 */

/** @typedef {{value: string, label: string}} BlockOption */


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
 * @property {AbortController|null} menuWatch
 * @property {Lookups<Image>} images
 * @property {Set<() => void>} imageViews
 * @property {Search<Image>} imageSearch
 * @property {TargetLookups<Page>} pages
 * @property {{from: number, to: number}} [linkRange]
 * @property {'link'|'widget'} pagePurpose
 * @property {Set<() => void>} widgetViews
 * @property {Search<Page>} pageSearch
 * @property {LinkTarget} linkKind
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
 * @property {LinkTarget[]} linkTargetsValue
 * @property {boolean} hasLinkTargetsValue
 * @property {PageLabels} pageLabelsValue
 * @property {WidgetLabels} widgetLabelsValue
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
        linkTargets: Array,
        pageLabels: Object,
        widgetLabels: Object,
    };

    connect() {
        this.abortController = new AbortController();
        const owner = this.abortController.signal;
        /** @param {string} url */
        const endpoint = (url) => new URL(url, window.location.href);
        this.menu = null;
        this.menuWatch = null;
        // What the CMS knows of each image and page the text cites or the editor picked.
        this.images = /** @type {Lookups<Image>} */ (new Lookups(endpoint(this.imageLookupUrlValue), 'images', owner, () => this.drawImages()));
        this.pages = /** @type {TargetLookups<Page>} */ (new TargetLookups(this.linkTargetsValue, owner, () => this.redrawPageLinks()));
        this.imageViews = new Set();
        this.widgetViews = new Set();
        this.imageSearch = new Search({
            url: endpoint(this.imageSearchUrlValue),
            param: 'alt',
            key: 'images',
            owner,
            view: () => ({ input: this.imageSearchTarget, results: this.imageResultsTarget, status: this.imageStatusTarget, more: null }),
            labels: () => this.imageLabelsValue,
            row: (image) => imageOption(image, () => this.embedImage(image)),
        });
        // One search, of the kind of content chosen when it goes out, so a
        // search of another kind still on its way is outdated by it.
        const kind = () => this.linkKind;
        this.pageSearch = /** @type {Search<Page>} */ (new Search({
            get url() {
                return endpoint(kind().searchUrl);
            },
            get param() {
                return kind().param;
            },
            get key() {
                return kind().key;
            },
            owner,
            view: () => ({ input: this.pageSearchTarget, results: this.pageResultsTarget, status: this.pageStatusTarget, more: this.pageMoreTarget }),
            // Widgets have no words of their own for nothing found, nor for a failed search.
            labels: () => ({ ...this.pagePurposeLabels(), none: this.pageLabelsValue.none, error: this.pageLabelsValue.error }),
            row: (page) => pageOption(page, () => this.pickPage(page), this.pagePurposeLabels().offSite),
        }));
        this.linkKind = this.linkTargetsValue[0];
        this.editor = new Editor({
            element: this.editorTarget,
            // The base styles come from the stylesheets the CMS links and app.css: an injected <style> breaks the content security policy.
            injectCSS: false,
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
                // Text pasted or brought back by undo may cite images or pages not looked up yet.
                this.lookUp();
            },
            // Focusing the text is a transaction too.
            onTransaction: () => this.refreshControls(),
        });

        this.frameTarget.hidden = false;
        this.hintTarget.hidden = false;
        this.refreshOutline();
        this.refreshControls();
        this.refreshCount();
        this.lookUp();
    }

    /** Looks up the images and the pages the text cites that the editor doesn't know yet. */
    lookUp() {
        // Without the lookups, the editor takes no images, links nor widgets, so there are none to look up.
        /** @type {string[]} */
        const images = [];

        this.editor.state.doc.descendants((node) => {
            if (node.type.name === 'image' && node.attrs.id) {
                images.push(node.attrs.id);
            }
        });

        this.images.ask(images);
        this.pages.ask(targetsIn(this.editor.state.doc));
    }

    /* Images: embedded by id, shown as the CMS knows them now. */

    /**
     * What the CMS knows of an image the text embeds.
     *
     * @param {string} id
     * @returns {import('../shared/lookup.js').Lookup<Image>}
     */
    describeImage(id) {
        // An image without an id is of no image at all.
        return id ? this.images.of(id) : MISSING;
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
    searchImages(event) {
        event?.preventDefault();
        this.imageSearch.first();
    }

    /** @param {Image} image */
    embedImage(image) {
        this.images.know(image);
        this.imageDialogTarget.close();
        // Known now, the image is drawn as it is inserted.
        this.editor.chain().focus().insertContent({ type: 'image', attrs: { id: image.id } }).run();
    }

    /* Links: applied to the selected text, with their targets looked up by kind and id. */

    /**
     * How what a link goes to stands, as far as the editor knows.
     *
     * @param {string} type
     * @param {string} id
     * @returns {PageState}
     */
    pageState(type, id) {
        return pageStateOf(this.pages.of(type, id));
    }

    /**
     * What a link goes to, in words: never by its look alone.
     *
     * @param {string} type
     * @param {string} id
     */
    describePage(type, id) {
        return linkTarget(this.pages.of(type, id), this.pageLabelsValue);
    }

    /** Redraws the widgets, the decorations of the links, and the tools of the one under the cursor. */
    redrawPageLinks() {
        for (const draw of this.widgetViews) {
            draw();
        }

        // A transaction that changes nothing but redraws, which also refreshes the controls.
        // With no steps, the history leaves it out.
        this.editor.view.dispatch(this.editor.state.tr);
    }

    /**
     * Picks a page to link the selected text to, or, for 'widget', to show in a new widget.
     *
     * @param {'link'|'widget'} purpose
     */
    openPageDialog(purpose) {
        // The dialog takes the focus; the range keeps the selected text intact.
        // A link is only offered with text selected.
        const { from, to } = this.editor.state.selection;
        this.linkRange = { from, to };

        this.pagePurpose = purpose;
        this.pageDialogTitleTarget.textContent = this.pagePurposeLabels().title;
        this.pageDialogTarget.showModal();
        this.pageSearchTarget.focus();
        this.searchPages();
    }

    /**
     * Searches the kind of content chosen, from its first results.
     *
     * @param {Event} event
     */
    chooseKind(event) {
        const { value } = /** @type {HTMLInputElement} */ (event.currentTarget);
        this.linkKind = this.linkTargetsValue.find((target) => target.kind === value) ?? this.linkKind;
        this.searchPages();
    }

    /**
     * Lists the first pages of results for the name written.
     *
     * @param {Event} [event]
     */
    searchPages(event) {
        event?.preventDefault();
        this.pageSearch.first();
    }

    /** Adds the next pages of results to those listed. */
    morePages() {
        this.pageSearch.next();
    }

    /** @param {Page} page */
    linkPage(page) {
        // Known now, it stands as found wherever else it is cited.
        this.pages.know(this.linkKind.type, page);
        this.pageDialogTarget.close();
        // The dialog was opened for the selected text, which it kept.
        const range = /** @type {{from: number, to: number}} */ (this.linkRange);
        this.editor.chain().focus().setTextSelection(range).setMark('link', { type: this.linkKind.type, id: page.id }).run();
        this.redrawPageLinks();
    }

    /** The labels of the dialog that depend on what it picks a page for. */
    pagePurposeLabels() {
        return { link: this.pageLabelsValue, widget: this.widgetLabelsValue }[this.pagePurpose];
    }

    /**
     * Does with the page picked what the dialog was opened for.
     *
     * @param {Page} page
     */
    pickPage(page) {
        ({ link: () => this.linkPage(page), widget: () => this.embedWidget(page) })[this.pagePurpose]();
    }

    /**
     * Shows the page picked in a new widget, where the cursor was.
     *
     * @param {Page} page
     */
    embedWidget(page) {
        this.pages.know(this.linkKind.type, page);
        this.pageDialogTarget.close();
        this.editor.chain().focus().insertContent({ type: 'widget', attrs: { kind: 'related_page', type: this.linkKind.type, id: page.id } }).run();
        this.redrawPageLinks();
    }

    /** Takes the link under the cursor out, keeping its text, and says so. */
    unlink() {
        COMMANDS.unlink(this.editor.chain().focus()).run();
        this.announce(this.pageLabelsValue.removed);
    }

    /**
     * Characters written against the most it takes, said in words. Not a live
     * region: announcing every keystroke would drown the typing.
     */
    refreshCount() {
        if (!this.hasCountTarget) {
            return;
        }

        this.countTarget.textContent = maxLengthMessage(this.editor.state.doc.textContent, {
            max: this.maxLengthValue,
            count: this.countValue,
            over: this.countOverValue,
        });
    }

    disconnect() {
        this.abortController.abort();
        this.closeMenu();
        this.editor.destroy();
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
        // Every control names its command.
        const command = /** @type {string} */ (button.dataset.command);

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
            this.openPageDialog('link');

            return;
        }

        // Another page for the whole link under the cursor, whatever is selected.
        if (command === 'changeLink') {
            this.editor.chain().focus().extendMarkRange('link').run();
            this.openPageDialog('link');

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
            const command = /** @type {string} */ (button.dataset.command);
            const pressed = command in ACTIVE && ACTIVE[command](this.editor);

            if (button.hasAttribute('aria-pressed')) {
                button.setAttribute('aria-pressed', String(pressed));
            }

            // A toggle that is on stays available, so it reads as on, not as unavailable.
            button.setAttribute('aria-disabled', String(!pressed && !this.can(command)));

            // The controls that move a block move its section from a heading, and say so.
            const { label, sectionLabel } = button.dataset;

            if (sectionLabel !== undefined) {
                this.rename(button, section ? sectionLabel : /** @type {string} */ (label));
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
            const link = this.editor.getAttributes('link');
            this.linkTargetTarget.textContent = inLink ? this.describePage(link.type, link.id) : '';
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

    /** Alt+F10: the first control of the dock, the kind of the current block when there is one. Undo is always there. */
    focusControls() {
        this.dockTarget.hidden = false;
        (this.hasBlockTypeTarget ? this.blockTypeTarget : this.buttonTargets[0]).focus();
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
                && this.editor.can().setMark('link');
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
        return currentKind({
            isActive: (name) => this.editor.isActive(name),
            getAttributes: (name) => this.editor.getAttributes(name),
        });
    }

    /**
     * Says where the block or the section went, since nothing changes under the cursor.
     *
     * @param {import('../rich_text/block_movement.js').Direction} direction
     * @param {boolean} section
     */
    announceMove(direction, section) {
        this.announce(movedMessage(direction, section, {
            movedUp: this.movedUpValue,
            movedDown: this.movedDownValue,
            sectionMovedUp: this.sectionMovedUpValue,
            sectionMovedDown: this.sectionMovedDownValue,
        }));
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

        // These are picked first, in their dialogs.
        if (block === 'image') {
            this.openImageDialog();

            return;
        }

        if (block === 'widget') {
            this.openPageDialog('widget');

            return;
        }

        // Every kind starts from a plain paragraph: what is wrapped is one, which this lifts out of its wrappers.
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

        return items.filter((item) => matches(item.label, query));
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
        this.watchOutside(button);
    }

    /**
     * Like any popup, the menu closes when the pointer goes down anywhere else,
     * the text included, and when the page scrolls or resizes, which would
     * leave it floating away from its button.
     *
     * @param {HTMLElement} button
     */
    watchOutside(button) {
        const watch = new AbortController();
        const { signal } = watch;
        /** @param {Event} event */
        const dismiss = (event) => {
            // Pointers and scrolls start on the nodes of the page.
            const target = /** @type {Node} */ (event.target);

            if (!this.menuTarget.contains(target) && !button.contains(target)) {
                this.closeMenu();
            }
        };

        // Captured: heard before whatever keeps them to itself, and from any scrolling part of the page.
        document.addEventListener('pointerdown', dismiss, { capture: true, signal });
        window.addEventListener('scroll', dismiss, { capture: true, signal });
        window.addEventListener('resize', () => this.closeMenu(), { signal });
        this.menuWatch = watch;
    }

    /**
     * The focus left the menu for something else, as Tab does.
     *
     * @param {FocusEvent} event
     */
    menuFocusOut(event) {
        // Where the focus went, if anywhere.
        const next = /** @type {Node|null} */ (event.relatedTarget);

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
        // Only the menu opened from the button ever holds the focus.
        if (this.menuKey(event)) {
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
            // The start comes without the items, which the update that follows brings.
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

            if (allowed && label !== undefined && matches(label, query)) {
                items.push({ label, apply: () => this.applyBlock(this.editor.chain().focus(), block) });
            }
        }

        return items;
    }

    /** @param {SlashProps} props */
    openSlashMenu(props) {
        // Every change of what follows "/" comes first without its items, while they are found:
        // closing the menu then would make it flicker, and lose the option highlighted.
        if (props.loading) {
            return;
        }

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
        // The suggestion always tells where it stands, though not always where it is drawn.
        this.renderMenu(/** @type {() => DOMRect|null} */ (props.clientRect)());
    }

    /** @param {DOMRect|null|undefined} anchor */
    renderMenu(anchor) {
        // Drawn once open.
        const menu = /** @type {Menu} */ (this.menu);

        this.menuTarget.replaceChildren(...menu.items.map((item, index) => {
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
            const { top, left } = menuPosition(
                anchor,
                { width: this.menuTarget.offsetWidth, height: this.menuTarget.offsetHeight },
                { width: window.innerWidth, height: window.innerHeight },
            );

            this.menuTarget.style.top = `${top}px`;
            this.menuTarget.style.left = `${left}px`;
        }

        this.highlight(menu.index);
    }

    /**
     * Does what the key does in the menu, and says whether it took it.
     *
     * @param {KeyboardEvent} event
     */
    menuKey(event) {
        if (this.menu === null) {
            return false;
        }

        const { mode, index, items, button } = this.menu;
        const action = menuAction(event.key, { mode, index, count: items.length });

        if (action === null) {
            return false;
        }

        if ('highlight' in action) {
            this.highlight(action.highlight);
        } else if ('choose' in action) {
            this.choose(action.choose);
        } else {
            // Focus first: hiding the menu while it holds the focus would drop it to the page.
            button?.focus();
            this.closeMenu();
        }

        return true;
    }

    /** @param {number} index */
    highlight(index) {
        // Only an open menu highlights one of its options.
        const menu = /** @type {Menu} */ (this.menu);
        menu.index = index;

        for (const [position, option] of [...this.menuTarget.children].entries()) {
            option.setAttribute('aria-selected', String(position === index));
        }

        this.menuTarget.children[index].scrollIntoView({ block: 'nearest' });

        const owner = menu.mode === 'slash' ? this.editor.view.dom : this.menuTarget;

        owner.setAttribute('aria-activedescendant', `${this.idValue}-menu-${index}`);
    }

    /** @param {number} index */
    choose(index) {
        // Only an open menu has options to choose.
        const menu = /** @type {Menu} */ (this.menu);

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

        // The "/" menu is the suggestion's to watch.
        this.menuWatch?.abort();
        this.menuWatch = null;

        this.menuTarget.removeAttribute('aria-activedescendant');
        this.editor.view.dom.setAttribute('aria-expanded', 'false');
        this.editor.view.dom.removeAttribute('aria-activedescendant');
    }

    /* Outline: every section one step away, the current one marked. */

    refreshOutline() {
        // A single paragraph has no sections.
        if (!this.hasOutlineDialogListTarget) {
            return;
        }

        const sections = sectionsOf(this.editor.state.doc);

        this.outlineDialogListTarget.replaceChildren(...sections.map((section) => {
            const item = document.createElement('li');
            const link = document.createElement('button');

            link.type = 'button';
            link.className = `rich-text-outline-item ${section.level > 2 ? 'pl-6' : 'pl-2'}`;
            link.textContent = section.title;
            link.addEventListener('click', () => this.jumpTo(section.from));
            item.append(link);

            return item;
        }));

        this.outlineButtonTarget.hidden = sections.length === 0;
        this.markCurrentSection();
    }

    markCurrentSection() {
        if (!this.hasOutlineDialogListTarget) {
            return;
        }

        const { doc, selection } = this.editor.state;
        const current = currentSection(sectionsOf(doc), selection.from);

        for (const [index, item] of [...this.outlineDialogListTarget.children].entries()) {
            // Each item holds the button of its section.
            const link = /** @type {HTMLElement} */ (item.firstElementChild);

            if (index === current) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
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
                ControlsShortcut.configure({ onRequest: () => this.focusControls() }),
                Placeholder.configure({ placeholder: this.placeholderValue }),
            ];

            if (marks.includes('bold')) {
                extensions.push(Bold);
            }

            if (marks.includes('link') && this.hasLinkTargetsValue) {
                extensions.push(PageLink.configure({ stateOf: (type, id) => this.pageState(type, id) }));
            }

            return extensions;
        }

        /** @type {import('@tiptap/core').AnyExtension[]} */
        const extensions = [
            Document,
            Paragraph,
            Text,
            UndoRedo,
            ControlsShortcut.configure({ onRequest: () => this.focusControls() }),
            BlockMover.configure({ onMove: (direction, section) => this.announceMove(direction, section) }),
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

        if (marks.includes('link') && this.hasLinkTargetsValue) {
            extensions.push(PageLink.configure({ stateOf: (type, id) => this.pageState(type, id) }));
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

        if (blocks.includes('widget') && this.hasWidgetLabelsValue && this.hasLinkTargetsValue) {
            extensions.push(Widget.configure({
                describe: (type, id) => this.pages.of(type, id),
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
