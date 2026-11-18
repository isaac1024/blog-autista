<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Ui;

use Cms\Shared\Infrastructure\Ui\KeptEdit;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class KeptEditTest extends TestCase
{
    public function testTheEditorItGoesBackToTakesWhatWasSentOnceWithoutItsToken(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $sent = Request::create('/posts/1', 'POST', ['title' => 'Otro', 'tags' => ['a', 'b'], '_token' => 'secreto']);
        $sent->setSession($session);
        new KeptEdit()->keep($sent, '/posts/1');

        $first = new KeptEdit()->taken(self::opening('/posts/1', $session));
        $second = new KeptEdit()->taken(self::opening('/posts/1', $session));

        self::assertSame([['title' => 'Otro', 'tags' => ['a', 'b']], null], [$first, $second]);
    }

    public function testAnotherEditorTakesNothingAndLeavesNothingBehind(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $sent = Request::create('/posts/1', 'POST', ['title' => 'Otro']);
        $sent->setSession($session);
        new KeptEdit()->keep($sent, '/posts/1');

        $other = new KeptEdit()->taken(self::opening('/posts/2', $session));
        $after = new KeptEdit()->taken(self::opening('/posts/1', $session));

        self::assertSame([null, null], [$other, $after]);
    }

    public function testTheEditorShowsWhatWasSentInPlaceOfWhatIsSavedAndItsBarHoldsChanges(): void
    {
        $editor = Page::create('Editar')->withMain('Shared:Form', ['id' => 'edit'], [
            new PageComponent('Shared:FormInput', ['name' => 'title', 'value' => 'Guardado']),
            new PageComponent('Shared:RichTextEditor', ['name' => 'content', 'value' => '{"type":"doc"}']),
            new PageComponent('Shared:FormSelect', ['name' => 'section', 'value' => 'a']),
            new PageComponent('Shared:TermsField', ['name' => 'tags', 'items' => [['id' => 'a']]]),
            new PageComponent('Shared:MenuField', ['name' => 'main_menu', 'items' => []]),
            new PageComponent('Shared:FormInput', ['name' => 'slug', 'value' => 'guardado']),
            new PageComponent('Shared:ActionBar', ['status' => 'Borrador'], [new PageComponent('Shared:FormButton', ['label' => 'Guardar'])]),
        ]);

        $shown = KeptEdit::onto($editor, [
            'title' => 'Enviado',
            'content' => '{"type":"doc","content":[]}',
            'section' => 'b',
            'tags' => ['b'],
            'main_menu' => [['page_id' => 'p1', 'label' => 'Sobre mí'], ['post_id' => 'p2', 'label' => ''], 'nada', ['label' => 'Sin destino']],
        ]);

        PageComponents::one($shown, 'Shared:FormInput', ['name' => 'title', 'value' => 'Enviado']);
        PageComponents::one($shown, 'Shared:RichTextEditor', ['value' => '{"type":"doc","content":[]}']);
        PageComponents::one($shown, 'Shared:FormSelect', ['value' => 'b']);
        PageComponents::one($shown, 'Shared:TermsField', ['items' => [['id' => 'a']]]);
        PageComponents::one($shown, 'Shared:MenuField', ['items' => [['type' => 'page', 'id' => 'p1', 'label' => 'Sobre mí'], ['type' => 'post', 'id' => 'p2', 'label' => null]]]);
        PageComponents::one($shown, 'Shared:FormInput', ['name' => 'slug', 'value' => 'guardado']);
        PageComponents::one($shown, 'Shared:ActionBar', ['status' => 'Borrador', 'kept' => true]);
        PageComponents::one($shown, 'Shared:FormButton', ['label' => 'Guardar']);
    }

    public function testAnEditorWithNothingKeptStaysAsItIs(): void
    {
        $editor = Page::create('Editar')->withMain('Shared:ActionBar', ['status' => 'Borrador']);

        self::assertSame($editor, KeptEdit::onto($editor, null));
    }

    private static function opening(string $path, Session $session): Request
    {
        $request = Request::create($path);
        $request->setSession($session);

        return $request;
    }
}
