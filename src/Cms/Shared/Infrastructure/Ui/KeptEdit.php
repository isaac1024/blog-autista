<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * What an editor sent when saving it was refused, kept for the editor it
 * goes back to: that editor shows it in place of what is saved, so nothing
 * written is lost to a rule the browser couldn't check. It is kept in the
 * session for the next page alone, as the message that tells why, and for
 * the editor it goes back to alone, by its path.
 */
final readonly class KeptEdit
{
    private const string KEY = 'kept_edit';

    /**
     * Keeps what the request sent, its token aside, for the editor it goes
     * back to.
     *
     * @param string $editor the path of that editor
     */
    public function keep(Request $request, string $editor): void
    {
        $fields = $request->request->all();
        unset($fields['_token']);

        self::flashes($request)->set(self::KEY, [['editor' => $editor, 'fields' => $fields]]);
    }

    /**
     * What was kept for the editor the request opens, null when nothing
     * was; taken either way, so it shows once.
     *
     * @return ?array<string, mixed>
     */
    public function taken(Request $request): ?array
    {
        $kept = self::flashes($request)->get(self::KEY)[0] ?? null;

        if (!\is_array($kept) || $request->getPathInfo() !== ($kept['editor'] ?? null) || !\is_array($kept['fields'] ?? null)) {
            return null;
        }

        return array_filter($kept['fields'], \is_string(...), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * The editor with each field that was sent holding what was sent: a
     * field named as one of them, whose value is a text, as a text field,
     * a choice, a picked image or resource, or a rich text, which posts its
     * own document, shows it, as a menu its entries; and its bar says it
     * holds changes not saved. Picked terms are left to the editor, which
     * names them.
     *
     * @param ?array<string, mixed> $fields as taken
     */
    public static function onto(Page $editor, ?array $fields): Page
    {
        if (null === $fields) {
            return $editor;
        }

        return $editor->withMainMapped(static function (PageComponent $component) use ($fields): PageComponent {
            // Its bar knows the form holds changes not saved.
            if ('Shared:ActionBar' === $component->name) {
                return new PageComponent($component->name, ['kept' => true] + $component->props, $component->children);
            }

            $name = $component->props['name'] ?? null;
            $sent = \is_string($name) ? ($fields[$name] ?? null) : null;

            if ('Shared:MenuField' === $component->name && \is_array($sent)) {
                return new PageComponent($component->name, ['items' => self::menuItems($sent)] + $component->props, $component->children);
            }

            if (!\is_string($sent) || !\is_string($component->props['value'] ?? null)) {
                return $component;
            }

            return new PageComponent($component->name, ['value' => $sent] + $component->props, $component->children);
        });
    }

    /**
     * The entries of a menu as sent, each its target under the key of its
     * kind, such as page_id, and its label, as a menu field shows them.
     *
     * @param array<mixed> $entries
     *
     * @return list<array{type: string, id: string, label: ?string}>
     */
    private static function menuItems(array $entries): array
    {
        $items = [];

        foreach (array_filter($entries, \is_array(...)) as $entry) {
            $label = $entry['label'] ?? null;

            foreach ($entry as $key => $id) {
                if (\is_string($key) && \is_string($id) && str_ends_with($key, '_id')) {
                    $items[] = ['type' => substr($key, 0, -3), 'id' => $id, 'label' => \is_string($label) && '' !== $label ? $label : null];
                }
            }
        }

        return $items;
    }

    private static function flashes(Request $request): FlashBagInterface
    {
        $session = $request->getSession();
        \assert($session instanceof FlashBagAwareSessionInterface);

        return $session->getFlashBag();
    }
}
