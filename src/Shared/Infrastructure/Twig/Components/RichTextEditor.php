<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Rich text field: a Tiptap editor, mounted by the rich-text-editor Stimulus
 * controller, that posts its document in a hidden input. The toolbar only
 * offers what the features of the module allow.
 *
 * Without JavaScript the field can't be edited, but the hidden input still
 * posts the current content, so saving the rest of the form keeps it.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * A flag here is a presentation option of a prop, not a sign of a method
 * doing two jobs.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
 */
#[AsTwigComponent]
final class RichTextEditor
{
    public string $name = '';

    public string $id = '';

    public string $label = '';

    /**
     * Whether the label is only announced, when a heading around the field
     * already names it on screen.
     */
    public bool $hideLabel = false;

    public string $placeholder = '';

    /**
     * Whether it holds a single paragraph, such as a caption: no kinds of
     * block to pick, no blocks to move, no outline, and Enter adds nothing.
     */
    public bool $singleParagraph = false;

    /**
     * Characters of text it takes at most, counted as the editor types; null
     * for no limit.
     */
    public ?int $maxLength = null;

    /**
     * What to write and how, under the label; null for the default help on
     * picking blocks.
     */
    public ?string $hint = null;

    /**
     * Where the editor searches the images to embed, and looks up those it
     * embeds already, as JSON; null when it embeds none.
     */
    public ?string $imageSearchUrl = null;

    public ?string $imageLookupUrl = null;

    /**
     * The Tiptap document, as JSON.
     */
    public string $value = '';

    /**
     * @var array{blocks: list<string>, headingLevels: list<int>, calloutVariants: list<string>, marks: list<string>}
     */
    public array $features = ['blocks' => [], 'headingLevels' => [], 'calloutVariants' => [], 'marks' => []];

    /**
     * @param array{blocks: list<string>, headingLevels: list<int>, calloutVariants: list<string>, marks: list<string>} $features
     */
    public function mount(
        string $name,
        string $id,
        string $label,
        string $value,
        array $features,
        bool $hideLabel = false,
        string $placeholder = '',
        bool $singleParagraph = false,
        ?int $maxLength = null,
        ?string $hint = null,
        ?string $imageSearchUrl = null,
        ?string $imageLookupUrl = null,
    ): void {
        $this->name = $name;
        $this->id = $id;
        $this->label = $label;
        $this->value = $value;
        $this->features = $features;
        $this->hideLabel = $hideLabel;
        $this->placeholder = $placeholder;
        $this->singleParagraph = $singleParagraph;
        $this->maxLength = $maxLength;
        $this->hint = $hint;
        $this->imageSearchUrl = $imageSearchUrl;
        $this->imageLookupUrl = $imageLookupUrl;
    }

    /**
     * Kinds of block the selector turns the current one into. Each value
     * names a block and, after a colon, its variant.
     *
     * @return list<array{value: string, label: string, labelParameters: array<string, int>}>
     */
    public function blockOptions(): array
    {
        $options = [self::option('paragraph', 'ui.editor.paragraph')];

        if ($this->allows('heading')) {
            foreach ($this->features['headingLevels'] as $level) {
                $options[] = self::option('heading:'.$level, 'ui.editor.heading', ['%level%' => $level]);
            }
        }

        if ($this->allows('quote')) {
            $options[] = self::option('quote', 'ui.editor.quote');
        }

        if ($this->allows('list')) {
            $options[] = self::option('bulletList', 'ui.editor.bullet_list');
            $options[] = self::option('orderedList', 'ui.editor.ordered_list');
        }

        if ($this->allows('callout')) {
            foreach ($this->features['calloutVariants'] as $variant) {
                $options[] = self::option('callout:'.$variant, 'ui.editor.callout.'.$variant);
            }
        }

        return $options;
    }

    public function allowsBold(): bool
    {
        return \in_array('bold', $this->features['marks'], true);
    }

    public function allowsTables(): bool
    {
        return $this->allows('table');
    }

    /**
     * Whether images can be embedded: the module allows them, and the editor
     * knows where to find them.
     */
    public function allowsImages(): bool
    {
        return $this->allows('image') && null !== $this->imageSearchUrl && null !== $this->imageLookupUrl;
    }

    public function allowsQuotes(): bool
    {
        return $this->allows('quote');
    }

    /**
     * Labels of the callout variants, which the editor shows on each callout.
     *
     * @return array<string, string> translation keys keyed by variant
     */
    public function calloutLabels(): array
    {
        $labels = [];

        foreach ($this->features['calloutVariants'] as $variant) {
            $labels[$variant] = 'ui.editor.callout.'.$variant;
        }

        return $labels;
    }

    private function allows(string $block): bool
    {
        return \in_array($block, $this->features['blocks'], true);
    }

    /**
     * @param array<string, int> $labelParameters
     *
     * @return array{value: string, label: string, labelParameters: array<string, int>}
     */
    private static function option(string $value, string $label, array $labelParameters = []): array
    {
        return ['value' => $value, 'label' => $label, 'labelParameters' => $labelParameters];
    }
}
