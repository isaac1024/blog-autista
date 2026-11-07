<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

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
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(string $action, string $method = 'post', array $children = [], ?string $id = null): void
    {
        $this->action = $action;
        $this->method = $method;
        $this->children = $children;
        $this->id = $id;
    }
}
