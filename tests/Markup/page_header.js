/*
 * The bar on top of the pages of the site, as PageHeader.html.twig writes
 * it, for the tests of the script that shows its menu in line where it fits.
 */

/**
 * @param {object} [options]
 * @param {string[]} [options.entries] The labels of the main menu; none, no menu.
 */
export const pageHeader = ({ entries = ['Sobre este blog', 'Qué es el autismo', 'Glosario', 'Recursos útiles', 'Para familias'] } = {}) => `
    <header class="group/menu relative" data-menu>
        <div class="mx-auto flex max-w-[72rem] flex-wrap items-center gap-x-8 px-8">
            <div class="inline-flex items-center gap-2.5 font-bold"><a href="/">Desde dentro</a></div>
${entries.length === 0 ? '' : `
            <nav class="hidden min-w-0 grow basis-0" data-menu-inline aria-label="Navegación principal">
                <ul class="m-0 flex list-none gap-x-5 p-0">
${entries.map((entry) => `                    <li><a class="inline-flex min-h-11 items-center whitespace-nowrap" href="#${entry}">${entry}</a></li>`).join('\n')}
                </ul>
            </nav>`}
            <div class="ml-auto flex items-center gap-1">
                <a class="flex min-h-11 min-w-11 items-center gap-2 px-2" href="/buscar.html">
                    <span class="group-data-[menu=inline]/menu:sr-only">Buscar</span>
                </a>
${entries.length === 0 ? '' : `
                <details class="group" data-menu-folded>
                    <summary>Menú</summary>
                    <nav aria-label="Navegación principal">
                        <ul>
${entries.map((entry) => `                            <li><a href="#${entry}">${entry}</a></li>`).join('\n')}
                        </ul>
                    </nav>
                </details>`}
            </div>
        </div>
    </header>
`;
