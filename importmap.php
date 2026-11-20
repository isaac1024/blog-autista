<?php

declare(strict_types=1);

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The packages the browser runs come from npm, the "dependencies" of
 * package.json, checked against the hashes of package-lock.json: each entry
 * names the file of the package for the browser, under a directory
 * config/packages/asset_mapper.yaml maps. Never "importmap:require", which
 * downloads a build of jsDelivr's instead of the package as published.
 *
 * @return array<string, array{    // Import name as key, description of the imported file as value
 *     path: string,               // Logical, relative or absolute path to the file
 *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
 *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
 * }|array{
 *     version: string,            // Version of the remote package
 *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
 *     type?: 'js'|'css'|'json',
 *     entrypoint?: bool,
 * }>
 */
return [
    'cms' => ['path' => './assets/cms.js', 'entrypoint' => true],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@floating-ui/core' => ['path' => './node_modules/@floating-ui/core/dist/floating-ui.core.mjs'],
    '@floating-ui/dom' => ['path' => './node_modules/@floating-ui/dom/dist/floating-ui.dom.mjs'],
    '@floating-ui/utils' => ['path' => './node_modules/@floating-ui/utils/dist/floating-ui.utils.mjs'],
    '@floating-ui/utils/dom' => ['path' => './node_modules/@floating-ui/utils/dist/floating-ui.utils.dom.mjs'],
    '@hotwired/stimulus' => ['path' => './node_modules/@hotwired/stimulus/dist/stimulus.js'],
    '@tiptap/core' => ['path' => './node_modules/@tiptap/core/dist/index.js'],
    '@tiptap/core/jsx-runtime' => ['path' => './node_modules/@tiptap/core/dist/jsx-runtime/jsx-runtime.js'],
    '@tiptap/extension-blockquote' => ['path' => './node_modules/@tiptap/extension-blockquote/dist/index.js'],
    '@tiptap/extension-bold' => ['path' => './node_modules/@tiptap/extension-bold/dist/index.js'],
    '@tiptap/extension-document' => ['path' => './node_modules/@tiptap/extension-document/dist/index.js'],
    '@tiptap/extension-heading' => ['path' => './node_modules/@tiptap/extension-heading/dist/index.js'],
    '@tiptap/extension-list' => ['path' => './node_modules/@tiptap/extension-list/dist/index.js'],
    '@tiptap/extension-paragraph' => ['path' => './node_modules/@tiptap/extension-paragraph/dist/index.js'],
    '@tiptap/extension-table' => ['path' => './node_modules/@tiptap/extension-table/dist/index.js'],
    '@tiptap/extension-text' => ['path' => './node_modules/@tiptap/extension-text/dist/index.js'],
    '@tiptap/extensions' => ['path' => './node_modules/@tiptap/extensions/dist/index.js'],
    '@tiptap/pm/commands' => ['path' => './node_modules/@tiptap/pm/dist/commands/index.js'],
    '@tiptap/pm/dropcursor' => ['path' => './node_modules/@tiptap/pm/dist/dropcursor/index.js'],
    '@tiptap/pm/gapcursor' => ['path' => './node_modules/@tiptap/pm/dist/gapcursor/index.js'],
    '@tiptap/pm/history' => ['path' => './node_modules/@tiptap/pm/dist/history/index.js'],
    '@tiptap/pm/keymap' => ['path' => './node_modules/@tiptap/pm/dist/keymap/index.js'],
    '@tiptap/pm/model' => ['path' => './node_modules/@tiptap/pm/dist/model/index.js'],
    '@tiptap/pm/schema-list' => ['path' => './node_modules/@tiptap/pm/dist/schema-list/index.js'],
    '@tiptap/pm/state' => ['path' => './node_modules/@tiptap/pm/dist/state/index.js'],
    '@tiptap/pm/tables' => ['path' => './node_modules/@tiptap/pm/dist/tables/index.js'],
    '@tiptap/pm/transform' => ['path' => './node_modules/@tiptap/pm/dist/transform/index.js'],
    '@tiptap/pm/view' => ['path' => './node_modules/@tiptap/pm/dist/view/index.js'],
    '@tiptap/suggestion' => ['path' => './node_modules/@tiptap/suggestion/dist/index.js'],
    'orderedmap' => ['path' => './node_modules/orderedmap/dist/index.js'],
    'prosemirror-commands' => ['path' => './node_modules/prosemirror-commands/dist/index.js'],
    'prosemirror-dropcursor' => ['path' => './node_modules/prosemirror-dropcursor/dist/index.js'],
    'prosemirror-gapcursor' => ['path' => './node_modules/prosemirror-gapcursor/dist/index.js'],
    'prosemirror-history' => ['path' => './node_modules/prosemirror-history/dist/index.js'],
    'prosemirror-keymap' => ['path' => './node_modules/prosemirror-keymap/dist/index.js'],
    'prosemirror-model' => ['path' => './node_modules/prosemirror-model/dist/index.js'],
    'prosemirror-schema-list' => ['path' => './node_modules/prosemirror-schema-list/dist/index.js'],
    'prosemirror-state' => ['path' => './node_modules/prosemirror-state/dist/index.js'],
    'prosemirror-tables' => ['path' => './node_modules/prosemirror-tables/dist/index.js'],
    'prosemirror-transform' => ['path' => './node_modules/prosemirror-transform/dist/index.js'],
    'prosemirror-view' => ['path' => './node_modules/prosemirror-view/dist/index.js'],
    'rope-sequence' => ['path' => './node_modules/rope-sequence/dist/index.js'],
    'w3c-keyname' => ['path' => './node_modules/w3c-keyname/index.js'],
];
