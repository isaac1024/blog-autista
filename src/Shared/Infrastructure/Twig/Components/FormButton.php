<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Submits its form, or runs a destructive action in the danger variant. It
 * can submit another form than the one it sits in, such as publishing a page
 * from the bar of its edit form, and wait until a form has no unsaved
 * changes, saying why it waits, so it never acts on what isn't saved yet.
 *
 * Its props are declared in mount(), as for every component, so it takes as
 * many parameters as it has props.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 *
 * A flag here is a presentation option of a prop, not a sign of a method
 * doing two jobs.
 * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
 */
#[AsTwigComponent]
final class FormButton
{
    public string $label = '';

    public string $type = 'submit';

    /**
     * Either 'primary', 'secondary' for the other actions next to the
     * primary one, or 'danger' for destructive actions.
     */
    public string $variant = 'primary';

    /**
     * Whether it spans the whole form, as on the login form; otherwise only on
     * narrow screens, where a wide target is easier to reach.
     */
    public bool $fullWidth = false;

    /**
     * Id of the form it submits, when not the one it sits in.
     */
    public ?string $form = null;

    /**
     * Id of the form whose unsaved changes keep it waiting.
     */
    public ?string $waitsFor = null;

    /**
     * What it says while it waits.
     */
    public string $waitingHint = '';

    public function mount(
        string $label,
        string $type = 'submit',
        string $variant = 'primary',
        bool $fullWidth = false,
        ?string $form = null,
        ?string $waitsFor = null,
        string $waitingHint = '',
    ): void {
        $this->label = $label;
        $this->type = $type;
        $this->variant = $variant;
        $this->fullWidth = $fullWidth;
        $this->form = $form;
        $this->waitsFor = $waitsFor;
        $this->waitingHint = $waitingHint;
    }
}
