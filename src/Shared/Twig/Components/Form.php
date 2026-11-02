<?php

declare(strict_types=1);

namespace Shared\Twig\Components;

use Shared\Ui\Page\PageComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Form
{
    public string $action = '';

    public string $method = 'post';

    /**
     * @var list<PageComponent>
     */
    public array $children = [];

    /**
     * @param list<PageComponent> $children
     */
    public function mount(string $action, string $method = 'post', array $children = []): void
    {
        $this->action = $action;
        $this->method = $method;
        $this->children = $children;
    }
}
