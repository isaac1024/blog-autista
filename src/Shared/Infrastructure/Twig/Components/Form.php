<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A flag here is a presentation option of a prop, not a sign of a method
 * doing two jobs.
 *
 * @SuppressWarnings("PHPMD.BooleanArgumentFlag")
 */
#[AsTwigComponent]
final class Form
{
    public string $action = '';

    public string $method = 'post';

    /**
     * Lets fields outside the form, such as a document title, join it.
     */
    public ?string $id = null;

    /**
     * Whether it shows nothing, as a form only buttons elsewhere submit,
     * through their form attribute.
     */
    public bool $hidden = false;

    /**
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(string $action, string $method = 'post', array $children = [], ?string $id = null, bool $hidden = false): void
    {
        $this->action = $action;
        $this->method = $method;
        $this->children = $children;
        $this->id = $id;
        $this->hidden = $hidden;
    }
}
