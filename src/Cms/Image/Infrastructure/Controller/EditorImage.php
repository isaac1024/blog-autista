<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageResponse;

/**
 * An image as the rich text editor shows it, to pick it or once embedded:
 * its thumbnail, its texts and where it stands for the site.
 */
final readonly class EditorImage
{
    /**
     * Where the CMS host serves the files of the images.
     */
    private const string MEDIA_URL = '/originales/';

    public function __construct(private ImageState $imageState) {}

    /**
     * @return array{id: string, alt: string, caption: string, thumbnail: string, status: string, published: bool}
     */
    public function __invoke(ImageResponse $image): array
    {
        return [
            'id' => $image->id,
            'alt' => $image->alt,
            'caption' => self::plain($image->caption),
            'thumbnail' => self::MEDIA_URL.$image->path,
            'status' => $this->imageState->label($image),
            'published' => 'published' === $image->status,
        ];
    }

    /**
     * The text of a caption, without its links.
     *
     * @param ?array{version: int, blocks: list<array<string, mixed>>} $caption
     */
    private static function plain(?array $caption): string
    {
        $text = '';

        foreach ($caption['blocks'] ?? [] as $block) {
            foreach (\is_array($block['text'] ?? null) ? $block['text'] : [] as $run) {
                $text .= \is_array($run) && \is_string($run['text'] ?? null) ? $run['text'] : '';
            }
        }

        return $text;
    }
}
