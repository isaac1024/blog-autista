/*
 * The facade of an external resource, as ExternalEmbed.html.twig writes it,
 * for the tests of the script of the public site that loads it.
 */

/**
 * @param {object} [options]
 * @param {string} [options.provider]
 * @param {string} [options.src] The address of the frame.
 * @param {string} [options.origin] Where the frame is served from.
 * @param {string} [options.space]
 * @param {number} [options.index] Tells apart the facades of one page.
 * @param {boolean} [options.plays] Whether its facade plays it, as that of a
 *     video or an audio does; that of a post is what the post says.
 */
export const externalEmbed = ({
    provider = 'youtube',
    src = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?hl=es',
    origin = 'https://www.youtube-nocookie.com',
    space = 'landscape',
    index = 1,
    plays = space !== 'reported',
} = {}) => `
    <figure data-external-embed data-provider="${provider}" data-src="${src}" data-origin="${origin}" data-space="${space}" data-title="Un vídeo (YouTube)">
        <div data-external-frame>
            <div data-external-facade>
                <p>Un vídeo</p>
                ${plays ? `<button type="button" aria-controls="notice-${index}" aria-label="Reproducir Un vídeo (${provider})" data-external-play hidden></button>` : ''}
            </div>
            <div id="notice-${index}" role="group" aria-labelledby="notice-${index}-title" data-external-notice hidden>
                <p id="notice-${index}-title" tabindex="-1" data-external-notice-title>Esto carga contenido de ${provider}</p>
                <button type="button" data-external-accept>Aceptar y cargar</button>
                <button type="button" data-external-cancel>Cancelar</button>
            </div>
        </div>
        <figcaption>
            <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" rel="external">Abrir en ${provider}</a>
            <button type="button" aria-controls="notice-${index}" data-external-load hidden>Cargar aquí</button>
            <button type="button" data-external-forget hidden>No cargar más</button>
        </figcaption>
    </figure>
`;
