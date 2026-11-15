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
 */
export const externalEmbed = ({
    provider = 'youtube',
    src = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?hl=es',
    origin = 'https://www.youtube-nocookie.com',
    space = 'landscape',
    index = 1,
} = {}) => `
    <figure data-external-embed data-provider="${provider}" data-src="${src}" data-origin="${origin}" data-space="${space}" data-title="Un vídeo (YouTube)">
        <div data-external-frame>
            <div data-external-facade><p>Un vídeo</p></div>
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
