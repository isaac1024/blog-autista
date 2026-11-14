<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\DataTable;
use Shared\Infrastructure\Twig\Components\Form;
use Shared\Infrastructure\Twig\Components\FormButton;
use Shared\Infrastructure\Twig\Components\FormInput;
use Shared\Infrastructure\Twig\Components\FormTextarea;
use Shared\Infrastructure\Twig\Components\Panel;

/**
 * What the components of forms and panels decide of themselves: what they
 * take when not told, what they refuse, and what they work out for their
 * templates.
 */
final class FormAndPanelComponentsTest extends TestCase
{
    /**
     * A panel is named by an id of its own, the same for the same title.
     */
    public function testAPanelNamesItsHeadingAfterItsTitle(): void
    {
        $panel = new Panel();
        $panel->mount('Datos del blog');

        self::assertSame('panel-08ed1f7b', $panel->headingId());
    }

    /**
     * A disclosure opens by its title, and a description is painted under
     * one, so neither goes without it; any other panel may.
     */
    public function testAPanelRefusesWhatItCantPaintWithoutATitle(): void
    {
        $untitled = new Panel();
        $untitled->mount(children: []);
        self::assertNull($untitled->title);

        foreach ([[null, 'disclosure', null], ['', 'disclosure', null], [null, 'default', 'Descripción'], ['', 'default', 'Descripción']] as [$title, $variant, $description]) {
            try {
                new Panel()->mount($title, $description, [], $variant);
                self::fail('A panel without a title took what it paints under one.');
            } catch (\InvalidArgumentException) {
                // Refused, as it must be.
            }
        }
    }

    /**
     * A field of text is three rows high and holds several lines unless
     * told; it counts its characters, as readers see them, only against a
     * recommended maximum.
     */
    public function testATextareaCountsItsCharactersAgainstAMaximumOnly(): void
    {
        $plain = new FormTextarea();
        $plain->mount('description', 'description', 'Ñandú');
        $counted = new FormTextarea();
        $counted->mount('description', 'description', 'Ñandú', recommendedMax: 160);

        self::assertSame([3, false, false], [$plain->rows, $plain->singleLine, $plain->counted()]);
        self::assertSame([true, 5], [$counted->counted(), $counted->length()]);
    }

    /**
     * An input without an id of its own is named by its field.
     */
    public function testAnInputIsNamedByItsFieldWithoutAnIdOfItsOwn(): void
    {
        $named = new FormInput();
        $named->mount('title');
        $identified = new FormInput();
        $identified->mount('title', 'page-title');

        self::assertSame(['title', 'page-title'], [$named->id, $identified->id]);
    }

    /**
     * A button takes its own width, and a form shows, unless told.
     */
    public function testAButtonAndAFormShowAsTheyAreUnlessTold(): void
    {
        $button = new FormButton();
        $button->mount('Guardar');
        $form = new Form();
        $form->mount('/pages');

        self::assertSame([false, false], [$button->fullWidth, $form->hidden]);
    }

    /**
     * A table refuses a row that doesn't fill its columns: a name, then one
     * figure for each column after.
     */
    public function testATableRefusesARowThatDoesntFillItsColumns(): void
    {
        $table = new DataTable();
        $table->mount('Páginas', ['Página', 'Vistas'], [['name' => 'Sobre mí', 'meta' => null, 'figures' => ['3'], 'share' => 1.0]]);
        self::assertCount(1, $table->rows);

        $this->expectException(\InvalidArgumentException::class);

        new DataTable()->mount('Páginas', ['Página', 'Vistas'], [
            ['name' => 'Sobre mí', 'meta' => null, 'figures' => ['3'], 'share' => 1.0],
            ['name' => 'Contacto', 'meta' => null, 'figures' => ['3', '2'], 'share' => 1.0],
        ]);
    }
}
