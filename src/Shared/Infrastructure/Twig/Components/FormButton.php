<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Submits its form, or runs a destructive action in the danger variant. With
 * a form action, it submits the form somewhere else than the form does, such
 * as publishing what an edit form holds.
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
     * Where it submits the form to, when not where the form does.
     */
    public ?string $formaction = null;

    public function mount(
        string $label,
        string $type = 'submit',
        string $variant = 'primary',
        bool $fullWidth = false,
        ?string $formaction = null,
    ): void {
        $this->label = $label;
        $this->type = $type;
        $this->variant = $variant;
        $this->fullWidth = $fullWidth;
        $this->formaction = $formaction;
    }
}
