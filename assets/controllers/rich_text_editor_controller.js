import { Controller } from '@hotwired/stimulus';
import { Editor, Extension, Mark, Node, mergeAttributes } from '@tiptap/core';
import { Plugin, PluginKey, TextSelection } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import { Document } from '@tiptap/extension-document';
import { Paragraph } from '@tiptap/extension-paragraph';
import { Text } from '@tiptap/extension-text';
import { Heading } from '@tiptap/extension-heading';
import { Bold } from '@tiptap/extension-bold';
import { Blockquote } from '@tiptap/extension-blockquote';
import { BulletList, OrderedList, ListItem } from '@tiptap/extension-list';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';
import { Placeholder, UndoRedo } from '@tiptap/extensions';
import { Suggestion } from '@tiptap/suggestion';
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

/** Paragraphs set apart with a visible label, never just a colour. */
const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'paragraph+',
    defining: true,

    addOptions() {
        return { labels: {} };
    },

    addAttributes() {
        return {
            variant: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-variant'),
                renderHTML: (attributes) => ({ 'data-variant': attributes.variant }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-callout]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, {
            'data-callout': '',
            'data-label': this.options.labels[node.attrs.variant] ?? '',
            role: 'note',
        }), 0];
    },
});

/**
 * Where a quote comes from, written as plain text at its end. Enter leaves
 * the quote for a new paragraph; Backspace on an empty source removes it.
 */
const QuoteSource = Node.create({
    name: 'quoteSource',
    content: 'text*',
    marks: '',
    defining: true,

    addOptions() {
        return { hint: '' };
    },

    parseHTML() {
        return [{ tag: 'div[data-quote-source]' }];
    },

    // Tiptap's Placeholder only marks top-level blocks, so the source carries its own hint.
    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-quote-source': '', 'data-placeholder': this.options.hint }), 0];
    },

    addCommands() {
        return {
            /** Takes the cursor to the source of the current quote, adding it if it has none. */
            focusQuoteSource: () => ({ tr, state, dispatch }) => {
                const quote = currentQuote(state);

                if (quote === null) {
                    return false;
                }

                if (dispatch) {
                    const { node, pos } = quote;
                    let end = pos + node.nodeSize - 1;

                    if (node.lastChild.type.name !== this.name) {
                        tr.insert(end, this.type.create());
                        end += 2;
                    }

                    tr.setSelection(TextSelection.create(tr.doc, end - 1));
                    tr.scrollIntoView();
                }

                return true;
            },
        };
    },

    addKeyboardShortcuts() {
        const inSource = () => this.editor.state.selection.$from.parent.type.name === this.name;

        return {
            Enter: () => {
                if (!inSource()) {
                    return false;
                }

                const { pos, node } = currentQuote(this.editor.state);
                const after = pos + node.nodeSize;

                return this.editor.chain()
                    .insertContentAt(after, { type: 'paragraph' })
                    .setTextSelection(after + 1)
                    .scrollIntoView()
                    .run();
            },
            Backspace: () => {
                const { $from, empty } = this.editor.state.selection;

                if (!inSource() || !empty || $from.parentOffset > 0) {
                    return false;
                }

                // At the start of a source with text, it stays a source rather than joining the quote.
                if ($from.parent.content.size > 0) {
                    return true;
                }

                const from = $from.before();

                return this.editor.chain()
                    .deleteRange({ from, to: from + $from.parent.nodeSize })
                    .setTextSelection(from - 1)
                    .run();
            },
        };
    },
});

/** The quote around the cursor, with where it starts, or null outside of one. */
const currentQuote = (state) => {
    const { $from } = state.selection;

    for (let depth = $from.depth; depth > 0; depth -= 1) {
        if ($from.node(depth).type.name === 'blockquote') {
            return { node: $from.node(depth), pos: $from.before(depth) };
        }
    }

    return null;
};

/** Top-level blocks of a document, with where each starts and ends. */
const topLevelBlocks = (doc) => {
    const blocks = [];

    doc.forEach((node, offset) => blocks.push({ node, from: offset, to: offset + node.nodeSize }));

    return blocks;
};

const isHeading = (node) => node.type.name === 'heading';

/** Whether the cursor is on a heading, so moving it moves its section. */
const onHeading = (state) => {
    const { $from } = state.selection;

    return $from.depth > 0 && isHeading($from.node(1));
};

/**
 * What moves together from the block at the index: a heading takes its whole
 * section, down to the next heading of its level or above; any other block,
 * only itself. Returns the range of block indexes, end excluded.
 */
const unitAt = (blocks, index) => {
    const { node } = blocks[index];

    if (!isHeading(node)) {
        return [index, index + 1];
    }

    let end = index + 1;

    while (end < blocks.length && !(isHeading(blocks[end].node) && blocks[end].node.attrs.level <= node.attrs.level)) {
        end += 1;
    }

    return [index, end];
};

/**
 * The two neighbouring units to swap for a move, or null when there is
 * nowhere to go. A section only swaps with a sibling section, so it never
 * leaves its parent nor lands among the blocks of another one.
 */
const movePlan = (doc, index, direction) => {
    const blocks = topLevelBlocks(doc);
    const [start, end] = unitAt(blocks, index);
    const { node } = blocks[start];

    if (direction < 0) {
        if (start === 0) {
            return null;
        }

        let previous = start - 1;

        if (isHeading(node)) {
            while (previous >= 0 && !(isHeading(blocks[previous].node) && blocks[previous].node.attrs.level <= node.attrs.level)) {
                previous -= 1;
            }

            if (previous < 0 || blocks[previous].node.attrs.level !== node.attrs.level) {
                return null;
            }
        }

        return { blocks, first: [previous, start], second: [start, end], moving: 'second' };
    }

    if (end >= blocks.length) {
        return null;
    }

    if (isHeading(node) && !(isHeading(blocks[end].node) && blocks[end].node.attrs.level === node.attrs.level)) {
        return null;
    }

    const next = isHeading(node) ? unitAt(blocks, end)[1] : end + 1;

    return { blocks, first: [start, end], second: [end, next], moving: 'first' };
};

/** Moves the current block, or its section, past its neighbour. */
const BlockMover = Extension.create({
    name: 'blockMover',

    addOptions() {
        return { onMove: () => {}, onControlsRequest: () => {} };
    },

    addCommands() {
        return {
            moveBlock: (direction) => ({ tr, dispatch }) => {
                const { $from, from } = tr.selection;
                const plan = $from.depth > 0 ? movePlan(tr.doc, $from.index(0), direction) : null;

                if (plan === null) {
                    return false;
                }

                if (dispatch) {
                    const { blocks, first, second, moving } = plan;
                    const firstFrom = blocks[first[0]].from;
                    const middle = blocks[second[0]].from;
                    const secondTo = blocks[second[1] - 1].to;
                    const swapped = tr.doc.slice(middle, secondTo).content.append(tr.doc.slice(firstFrom, middle).content);
                    // The cursor travels with what moves.
                    const cursor = moving === 'first' ? from + (secondTo - middle) : from - (middle - firstFrom);

                    tr.replaceWith(firstFrom, secondTo, swapped);
                    tr.setSelection(TextSelection.near(tr.doc.resolve(cursor)));
                    tr.scrollIntoView();
                }

                return true;
            },
        };
    },

    addKeyboardShortcuts() {
        const move = (direction) => {
            const section = onHeading(this.editor.state);

            return this.editor.commands.moveBlock(direction) && (this.options.onMove(direction, section), true);
        };

        return {
            'Mod-Shift-ArrowUp': () => move(-1),
            'Mod-Shift-ArrowDown': () => move(1),
            // The usual shortcut of accessible editors to reach their controls.
            'Alt-F10': () => (this.options.onControlsRequest(), true),
        };
    },
});

/** Lists the kinds of block when "/" starts an empty line. */
const SlashMenu = Extension.create({
    name: 'slashMenu',

    addOptions() {
        return { items: () => [], render: () => ({}) };
    },

    addProseMirrorPlugins() {
        return [Suggestion({
            editor: this.editor,
            char: '/',
            startOfLine: true,
            // Table cells and the source of a quote only hold text, so there is nothing to choose there.
            allow: ({ editor }) => !editor.isActive('table') && !editor.isActive('quoteSource'),
            items: ({ query }) => this.options.items(query),
            command: ({ editor, range, props }) => {
                editor.chain().focus().deleteRange(range).run();
                props.apply();
            },
            render: this.options.render,
        })];
    },
});

/**
 * Draws an embedded image as the CMS knows it now: its thumbnail, its texts
 * and where it stands for the site. Undefined while it is being looked up,
 * null when there is no such image any longer.
 */
const drawImage = (figure, image, labels) => {
    figure.replaceChildren();

    if (image === undefined || image === null) {
        const note = document.createElement('p');
        note.className = 'rich-text-image-note';
        note.textContent = image === undefined ? labels.loading : labels.missing;
        figure.append(note);

        return;
    }

    const thumbnail = document.createElement('img');
    thumbnail.src = image.thumbnail;
    // The texts below name it.
    thumbnail.alt = '';
    thumbnail.width = 240;
    thumbnail.height = 135;

    const details = document.createElement('div');
    details.className = 'rich-text-image-details';

    const status = document.createElement('span');
    status.className = 'rich-text-image-status';
    status.textContent = image.status;

    const alt = document.createElement('p');
    alt.className = 'rich-text-image-alt';
    alt.textContent = `${labels.embedded} ${image.alt}`;

    details.append(status, alt);

    if (image.caption !== '') {
        const caption = document.createElement('p');
        caption.textContent = image.caption;
        details.append(caption);
    }

    if (!image.published) {
        const offSite = document.createElement('p');
        offSite.className = 'rich-text-image-note';
        offSite.textContent = labels.offSite;
        details.append(offSite);
    }

    figure.append(thumbnail, details);
};

/**
 * An image of the library, embedded by its id alone: the editor shows what
 * the CMS knows of it now, so its texts and status are always the latest.
 */
const EmbeddedImage = Node.create({
    name: 'image',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: false,

    addOptions() {
        return { describe: () => undefined, views: new Set(), labels: {} };
    },

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-image-id'),
                renderHTML: (attributes) => ({ 'data-image-id': attributes.id }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-image-id]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['figure', mergeAttributes(HTMLAttributes)];
    },

    addNodeView() {
        return ({ node }) => {
            const figure = document.createElement('figure');
            figure.className = 'rich-text-image';
            figure.setAttribute('data-image-id', node.attrs.id);
            const draw = () => drawImage(figure, this.options.describe(node.attrs.id), this.options.labels);

            draw();
            this.options.views.add(draw);

            return { dom: figure, destroy: () => this.options.views.delete(draw) };
        };
    },
});

/**
 * Draws a widget as the CMS knows its page now: its title and where it
 * stands for the site, after what kind of widget it is, all in words.
 */
const drawWidget = (element, { state, page }, labels) => {
    element.replaceChildren();

    const label = document.createElement('p');
    label.className = 'rich-text-widget-label';
    label.textContent = labels.label ?? '';
    element.append(label);

    if (page === undefined) {
        const note = document.createElement('p');
        note.className = 'rich-text-page-note';
        note.textContent = labels[state] ?? '';
        element.append(note);

        return;
    }

    const title = document.createElement('p');
    title.className = 'rich-text-widget-title';
    title.textContent = page.title;

    const status = document.createElement('span');
    status.className = 'rich-text-page-status';
    status.textContent = page.status;

    element.append(title, status);

    if (state === 'offSite') {
        const offSite = document.createElement('p');
        offSite.className = 'rich-text-page-note';
        offSite.textContent = labels.offSite ?? '';
        element.append(offSite);
    }
};

/**
 * A widget: for now, a page of the blog shown as a card of related content.
 * Its page is kept by its id alone, as a link's is, so the editor shows its
 * latest title and status, and the site its current address.
 */
const Widget = Node.create({
    name: 'widget',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: false,

    addOptions() {
        return { describe: () => ({ state: 'loading', page: undefined }), views: new Set(), labels: {} };
    },

    addAttributes() {
        return {
            kind: {
                default: 'related_page',
                parseHTML: (element) => element.getAttribute('data-widget-kind'),
                renderHTML: (attributes) => ({ 'data-widget-kind': attributes.kind }),
            },
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-page-id'),
                renderHTML: (attributes) => ({ 'data-page-id': attributes.id }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-widget-kind]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes)];
    },

    addNodeView() {
        return ({ node }) => {
            const element = document.createElement('div');
            element.className = 'rich-text-widget';
            element.setAttribute('data-widget-kind', node.attrs.kind);
            const draw = () => drawWidget(element, this.options.describe(node.attrs.id), this.options.labels);

            draw();
            this.options.views.add(draw);

            return { dom: element, destroy: () => this.options.views.delete(draw) };
        };
    },
});

/**
 * An internal link to a page, kept by its id alone. It has no href in the CMS:
 * the editor must not navigate away, and the site resolves the current address.
 *
 * Links whose page isn't on the site, or isn't known yet, are told apart by a
 * class on decorations, which ProseMirror draws itself: the DOM of the text is
 * never touched by hand. The controller says how each page stands through
 * stateOf(), and redraws them once it learns more.
 */
const pageLinksKey = new PluginKey('pageLinks');

const PageLink = Mark.create({
    name: 'link',
    inclusive: false,

    addOptions() {
        return {
            // 'published', 'offSite', 'missing', 'unknown' or 'loading'.
            stateOf: () => 'loading',
        };
    },

    addProseMirrorPlugins() {
        const { stateOf } = this.options;

        return [new Plugin({
            key: pageLinksKey,
            props: {
                decorations: (state) => {
                    const decorations = [];

                    state.doc.descendants((node, position) => {
                        const link = node.isText ? node.marks.find((mark) => mark.type.name === 'link') : undefined;
                        const linkState = link ? stateOf(link.attrs.id) : 'published';

                        if (linkState !== 'published') {
                            decorations.push(Decoration.inline(position, position + node.nodeSize, { class: `rich-text-link-${linkState}` }));
                        }
                    });

                    return DecorationSet.create(state.doc, decorations);
                },
            },
        })];
    },

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-page-id'),
                renderHTML: (attributes) => ({ 'data-page-id': attributes.id }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'a[data-page-id]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['a', mergeAttributes(HTMLAttributes), 0];
    },
});

/** What every control runs, given a command chain. */
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

/** Whether a toggle is on for the current selection. */
const ACTIVE = {
    bold: (editor) => editor.isActive('bold'),
};

/** Turns a plain paragraph into each kind of block. */
const BLOCKS = {
    paragraph: (chain) => chain,
    heading: (chain, variant) => chain.setHeading({ level: Number(variant) }),
    quote: (chain) => chain.wrapIn('blockquote'),
    bulletList: (chain) => chain.toggleBulletList(),
    orderedList: (chain) => chain.toggleOrderedList(),
    callout: (chain, variant) => chain.wrapIn('callout', { variant }),
};

/** Lower case and without accents, so "parrafo" finds "Párrafo". */
const normalise = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export default class extends Controller {
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
        this.menu = null;
        this.dismiss = null;
        // What the CMS knows of each image embedded or picked, by id.
        this.images = new Map();
        this.imagesLookedUp = false;
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
                // Keep the cursor clear of the dock when scrolling it into view.
                scrollMargin: { top: 80, bottom: 220 },
                scrollThreshold: { top: 80, bottom: 220 },
            },
            onUpdate: ({ editor }) => {
                this.inputTarget.value = JSON.stringify(editor.getJSON());
                // Setting the value fires no event, so tell whoever watches the form.
                this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
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

        const ids = [];

        this.editor.state.doc.descendants((node) => {
            if (node.type.name === 'image' && node.attrs.id) {
                ids.push(node.attrs.id);
            }
        });

        if (ids.length > 0) {
            const url = new URL(this.imageLookupUrlValue, window.location.href);
            url.searchParams.set('ids', ids.join(','));
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (response.ok) {
                for (const image of (await response.json()).images) {
                    this.images.set(image.id, image);
                }
            }
        }

        this.imagesLookedUp = true;
        this.drawImages();
    }

    describeImage(id) {
        if (this.images.has(id)) {
            return this.images.get(id);
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

    async searchImages(event) {
        event?.preventDefault();

        const url = new URL(this.imageSearchUrlValue, window.location.href);
        url.searchParams.set('alt', this.imageSearchTarget.value);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const images = response.ok ? (await response.json()).images : [];

        this.imageResultsTarget.replaceChildren(...images.map((image) => this.imageResult(image)));
        const { none, result, results } = this.imageLabelsValue;
        this.imageStatusTarget.textContent = [none, result][images.length] ?? results.replace('%count%', String(images.length));
    }

    /** One image to pick: the whole row is its button, named by its text and status. */
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

        const ids = new Set();

        const unknown = (id) => id && this.pageState(id) === 'loading' && !this.pagesLookingUp.has(id);

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
        const url = new URL(this.pageLookupUrlValue, window.location.href);
        url.searchParams.set('ids', [...ids].join(','));
        let found = null;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            found = response.ok ? (await response.json()).pages : null;
        } catch {
            // Told as unknown below: the page may well exist.
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

    /** How the page of a link stands, as far as the editor knows. */
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

    /** The page a link goes to, in words: never by its look alone. */
    describePage(id) {
        const labels = this.pageLabelsValue;
        const state = this.pageState(id);

        if (state === 'loading' || state === 'missing' || state === 'unknown') {
            return labels[state] ?? '';
        }

        const page = this.pages.get(id);
        const target = (labels.target ?? '%title% %status%').replace('%title%', page.title).replace('%status%', page.status);

        return state === 'offSite' ? `${target} ${labels.offSite ?? ''}` : target;
    }

    /** How the page of a widget stands, and the page itself once known. */
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

    /** Picks a page to link the selected text to, or, for 'widget', to show in a new widget. */
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

    /** Lists the first pages of results for the title written. */
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
        const url = new URL(this.pageSearchUrlValue, window.location.href);
        url.searchParams.set('title', this.pageSearchTarget.value);
        url.searchParams.set('page', String(this.pageSearch.page));
        this.pageMoreTarget.hidden = true;
        let result = null;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            result = response.ok ? await response.json() : null;
        } catch {
            // Told below, like an answer that isn't ok.
        }

        // A newer search went out meanwhile: this answer is stale.
        if (request !== this.pageSearch.request) {
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
        this.pageStatusTarget.textContent = [none, one][count] ?? results.replace('%count%', String(count));

        // Past the first pages, the focus goes to the first page added, not back to the top.
        if (this.pageSearch.page > 1) {
            this.pageResultsTarget.children[first]?.querySelector('button')?.focus();
        }
    }

    /** One page to pick: the whole row is its button, named by its title and status. */
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

    /** Shows the page picked in a new widget, where the cursor was. */
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
        this.closeMenu();
        this.editor?.destroy();
    }

    /**
     * Clicking a control keeps the focus, and the selection, in the editor,
     * so typing right after a click lands in the text.
     */
    keepFocus(event) {
        event.preventDefault();
    }

    run(event) {
        const button = event.currentTarget;
        const { command } = button.dataset;

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
            const { command } = button.dataset;
            const pressed = command in ACTIVE && ACTIVE[command](this.editor);

            if (button.hasAttribute('aria-pressed')) {
                button.setAttribute('aria-pressed', String(pressed));
            }

            // A toggle that is on stays available, so it reads as on, not as unavailable.
            button.setAttribute('aria-disabled', String(!pressed && !this.can(command)));

            if (button.dataset.sectionLabel) {
                this.rename(button, section ? button.dataset.sectionLabel : button.dataset.label);
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

    /** Hides the controls once the focus leaves the text and the controls alike. */
    leave(event) {
        if (this.canvasTarget.contains(event.relatedTarget)) {
            return;
        }

        this.closeMenu();
        this.dockTarget.hidden = true;
    }

    /** Escape, from any control, goes back to the text where the cursor was. */
    controlsKey(event) {
        if (event.key === 'Escape' && this.dockTarget.contains(event.target)) {
            event.preventDefault();
            this.editor.commands.focus();
        }
    }

    /** Alt+F10: the first control of the dock, the kind of the current block when there is one. */
    focusControls() {
        this.dockTarget.hidden = false;
        (this.hasBlockTypeTarget ? this.blockTypeTarget : this.buttonTargets[0])?.focus();
    }

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

        const wrapper = { blockquote: 'quote', bulletList: 'bulletList', orderedList: 'orderedList' };
        const active = Object.keys(wrapper).find((name) => this.editor.isActive(name));

        return active ? wrapper[active] : 'paragraph';
    }

    /** Says where the block or the section went, since nothing changes under the cursor. */
    announceMove(direction, section) {
        const message = section
            ? (direction < 0 ? this.sectionMovedUpValue : this.sectionMovedDownValue)
            : (direction < 0 ? this.movedUpValue : this.movedDownValue);

        this.announce(message);
    }

    /** Says something through the live region, even the same thing twice in a row. */
    announce(message) {
        this.statusTarget.textContent = '';
        window.requestAnimationFrame(() => {
            this.statusTarget.textContent = message;
        });
    }

    /* Kinds of block: one menu, opened by "/" or by the block kind button. */

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

    blockItems(query) {
        const current = this.currentBlock();
        const items = this.blockOptionsValue.map((option) => ({
            label: option.label,
            current: option.value === current,
            apply: () => this.applyBlock(this.editor.chain().focus(), option.value),
        }));

        return items.filter((item) => normalise(item.label).includes(normalise(query)));
    }

    openBlockMenu(event) {
        const button = event.currentTarget;

        if (button.getAttribute('aria-disabled') === 'true') {
            return;
        }

        if (this.menu?.button === button) {
            this.closeMenu();

            return;
        }

        const items = this.blockItems('');

        this.menu = { mode: 'button', button, items, index: Math.max(items.findIndex((item) => item.current), 0) };
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
        this.dismiss = (event) => {
            const { button } = this.menu ?? {};

            if (this.menuTarget.contains(event.target) || button?.contains(event.target)) {
                return;
            }

            this.closeMenu();
        };

        document.addEventListener('pointerdown', this.dismiss, true);
        window.addEventListener('scroll', this.dismiss, { capture: true, passive: true });
        window.addEventListener('resize', this.dismiss);
    }

    /** The focus left the menu for something else, as Tab does. */
    menuFocusOut(event) {
        if (this.menu?.mode !== 'button' || this.menuTarget.contains(event.relatedTarget) || event.relatedTarget === this.menu.button) {
            return;
        }

        this.closeMenu();
    }

    /** Keys of the menu opened from the button, which holds the focus meanwhile. */
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
        return {
            onStart: (props) => this.openSlashMenu(props),
            onUpdate: (props) => this.openSlashMenu(props),
            onKeyDown: ({ event }) => this.menuKey(event),
            onExit: () => this.closeMenu(),
        };
    }

    slashItems(query) {
        const items = this.blockItems(query);

        if (this.featuresValue.blocks.includes('table')) {
            const label = this.buttonTargets.find((button) => button.dataset.command === 'insertTable').dataset.label;

            if (normalise(label).includes(normalise(query))) {
                items.push({ label, apply: () => this.applyBlock(this.editor.chain().focus(), 'table') });
            }
        }

        if (this.hasImageSearchUrlValue && this.featuresValue.blocks.includes('image')) {
            const label = this.buttonTargets.find((button) => button.dataset.command === 'insertImage').dataset.label;

            if (normalise(label).includes(normalise(query))) {
                items.push({ label, apply: () => this.applyBlock(this.editor.chain().focus(), 'image') });
            }
        }

        if (this.hasWidgetLabelsValue && this.featuresValue.blocks.includes('widget')) {
            const label = this.buttonTargets.find((button) => button.dataset.command === 'insertWidget').dataset.label;

            if (normalise(label).includes(normalise(query))) {
                items.push({ label, apply: () => this.applyBlock(this.editor.chain().focus(), 'widget') });
            }
        }

        return items;
    }

    openSlashMenu(props) {
        if (props.items.length === 0) {
            this.closeMenu();

            return;
        }

        const index = this.menu?.mode === 'slash' ? Math.min(this.menu.index, props.items.length - 1) : 0;

        this.menu = { mode: 'slash', props, items: props.items, index };
        this.editor.view.dom.setAttribute('aria-controls', this.menuTarget.id);
        this.editor.view.dom.setAttribute('aria-expanded', 'true');
        this.renderMenu(props.clientRect?.());
    }

    renderMenu(anchor) {
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

    /** Arrow keys move through the kinds; Enter picks one; Escape closes. */
    menuKey(event) {
        if (this.menu === null) {
            return false;
        }

        const count = this.menu.items.length;
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

    highlight(index) {
        this.menu.index = index;

        for (const [position, option] of [...this.menuTarget.children].entries()) {
            option.setAttribute('aria-selected', String(position === index));
        }

        this.menuTarget.children[index]?.scrollIntoView({ block: 'nearest' });

        const owner = this.menu.mode === 'slash' ? this.editor.view.dom : this.menuTarget;

        owner.setAttribute('aria-activedescendant', `${this.idValue}-menu-${index}`);
    }

    choose(index) {
        const { mode, props, items } = this.menu;

        if (mode === 'slash') {
            props.command(items[index]);

            return;
        }

        // Back to the text before the menu hides, so the focus never falls to
        // the page, which would read as leaving the editor and hide the dock.
        this.editor.view.focus();
        this.closeMenu();
        items[index].apply();
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

        this.sections = topLevelBlocks(this.editor.state.doc)
            .filter(({ node }) => isHeading(node) && node.textContent.trim() !== '')
            .map(({ node, from }) => ({ from, level: node.attrs.level, title: node.textContent }));

        for (const list of [this.outlineDialogListTarget]) {
            list.replaceChildren(...this.sections.map((section) => {
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

        this.outlineButtonTarget.hidden = this.sections.length === 0;
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
                    item.firstElementChild.setAttribute('aria-current', 'location');
                } else {
                    item.firstElementChild.removeAttribute('aria-current');
                }
            }
        }
    }

    /** Opens the section at the top of the screen, with the cursor at its heading. */
    jumpTo(from) {
        this.outlineDialogTarget.close();
        this.editor.chain().setTextSelection(from + 1).focus(null, { scrollIntoView: false }).run();
        this.editor.view.nodeDOM(from)?.scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' });
    }

    extensions() {
        const { blocks, headingLevels, marks } = this.featuresValue;

        if (this.singleParagraphValue) {
            // The document is one paragraph, so Enter has nowhere to split it.
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
