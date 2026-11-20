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
 * The "importmap:require" command can be used to add new entries to this file.
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
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@tiptap/core' => ['version' => '3.34.0'],
    '@tiptap/pm/state' => ['version' => '3.34.0'],
    '@tiptap/extension-document' => ['version' => '3.34.0'],
    '@tiptap/extension-paragraph' => ['version' => '3.34.0'],
    '@tiptap/extension-text' => ['version' => '3.34.0'],
    '@tiptap/extension-heading' => ['version' => '3.34.0'],
    '@tiptap/extension-bold' => ['version' => '3.34.0'],
    '@tiptap/extension-blockquote' => ['version' => '3.34.0'],
    '@tiptap/extension-list' => ['version' => '3.34.0'],
    '@tiptap/extension-table' => ['version' => '3.34.0'],
    '@tiptap/extensions' => ['version' => '3.34.0'],
    '@tiptap/pm/transform' => ['version' => '3.34.0'],
    '@tiptap/pm/commands' => ['version' => '3.34.0'],
    '@tiptap/pm/model' => ['version' => '3.34.0'],
    '@tiptap/pm/schema-list' => ['version' => '3.34.0'],
    '@tiptap/pm/view' => ['version' => '3.34.0'],
    '@tiptap/pm/keymap' => ['version' => '3.34.0'],
    '@tiptap/pm/history' => ['version' => '3.34.0'],
    'prosemirror-state' => ['version' => '1.4.4'],
    '@tiptap/core/jsx-runtime' => ['version' => '3.34.0'],
    '@tiptap/pm/tables' => ['version' => '3.34.0'],
    '@tiptap/pm/dropcursor' => ['version' => '3.34.0'],
    '@tiptap/pm/gapcursor' => ['version' => '3.34.0'],
    'prosemirror-transform' => ['version' => '1.12.7'],
    'prosemirror-commands' => ['version' => '1.7.2'],
    'prosemirror-model' => ['version' => '1.25.13'],
    'prosemirror-keymap' => ['version' => '1.2.3'],
    'prosemirror-tables' => ['version' => '1.8.5'],
    'prosemirror-dropcursor' => ['version' => '1.8.4'],
    'prosemirror-gapcursor' => ['version' => '1.4.1'],
    'orderedmap' => ['version' => '2.1.1'],
    'w3c-keyname' => ['version' => '2.2.8'],
    'rope-sequence' => ['version' => '1.3.4'],
    'prosemirror-tables/style/tables.min.css' => ['version' => '1.8.5', 'type' => 'css'],
    'prosemirror-gapcursor/style/gapcursor.min.css' => ['version' => '1.4.1', 'type' => 'css'],
    '@tiptap/suggestion' => ['version' => '3.34.0'],
    '@floating-ui/dom' => ['version' => '1.8.0'],
    '@floating-ui/core' => ['version' => '1.8.0'],
    '@floating-ui/utils' => ['version' => '0.2.12'],
    '@floating-ui/utils/dom' => ['version' => '0.2.12'],
    'prosemirror-schema-list' => ['version' => '1.6.1'],
    'prosemirror-history' => ['version' => '1.5.2'],
    'prosemirror-view' => ['version' => '1.42.9'],
    'prosemirror-view/style/prosemirror.min.css' => ['version' => '1.42.9', 'type' => 'css'],
];
