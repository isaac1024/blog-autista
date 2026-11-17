/*
 * The reading settings of a header, as ReadingSettings.html.twig writes
 * them, for the tests of the script that shows and keeps them.
 */

export const readingSettings = () => `
    <div class="relative hidden scripted:block" data-reading>
        <button type="button" aria-expanded="false" aria-controls="reading-settings" aria-label="Ajustes de lectura" data-reading-toggle>
            <span aria-hidden="true">Aa</span>
        </button>
        <div id="reading-settings" role="group" aria-labelledby="reading-settings-title" data-reading-panel hidden>
            <p id="reading-settings-title">Ajustes de lectura</p>
            <p id="reading-settings-size">Tamaño del texto</p>
            <div role="group" aria-labelledby="reading-settings-size">
                <button type="button" aria-label="Texto más pequeño" data-reading-size="smaller">−</button>
                <output aria-live="polite" data-reading-size-value>100 %</output>
                <button type="button" aria-label="Texto más grande" data-reading-size="bigger">+</button>
            </div>
            <fieldset>
                <legend>Tema</legend>
                <label><input type="radio" name="reading-theme" value="system" checked> Sistema</label>
                <label><input type="radio" name="reading-theme" value="light"> Claro</label>
                <label><input type="radio" name="reading-theme" value="dark"> Oscuro</label>
            </fieldset>
            <p>Se guarda solo en este navegador.</p>
        </div>
    </div>
    <a href="#elsewhere" data-elsewhere>En otra parte de la página</a>
`;
