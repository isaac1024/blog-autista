#!/usr/bin/env node

/**
 * Config-driven UI quality gate.
 *
 * Checks are intentionally kept outside PHPUnit/Infection: they validate
 * rendered output with browsers and external validators, not PHP behaviour,
 * so they do not add noise or runtime to the mutation-testing pipeline.
 *
 * Tools (W3C recommendations):
 *   - HTML : W3C Nu HTML Checker (vnu-jar, requires Java 11+)
 *   - CSS  : W3C CSS Validation Service
 *   - A11y : Lighthouse, whose accessibility audits are powered by axe-core
 *            (both appear in W3C WAI's evaluation tools list)
 *   - Perf : Lighthouse (W3C does not publish a performance validator;
 *            Lighthouse is the de facto standard)
 *
 * Pages, server command, CSS globs, validator endpoints and thresholds live
 * in `ui-check.config.mjs`.
 *
 *   npm run ui:check
 *   npm run ui:check -- --page=cms-login
 *   npm run ui:check -- --only=html,css
 */

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { glob, mkdir, readFile, writeFile } from 'node:fs/promises';
import { request } from 'node:http';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath, pathToFileURL } from 'node:url';

import lighthouse from 'lighthouse';
import * as chromeLauncher from 'chrome-launcher';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const REPORT_DIR = path.join(ROOT, 'var', 'ui-check');
const CONFIG_FILE = process.env.UI_CHECK_CONFIG
    ? path.resolve(ROOT, process.env.UI_CHECK_CONFIG)
    : path.join(ROOT, 'ui-check.config.mjs');
const PORT = Number(process.env.UI_CHECK_PORT ?? 8099);
const VNU_JAR = path.join(ROOT, 'node_modules', 'vnu-jar', 'build', 'dist', 'vnu.jar');
const W3C_CSS_VALIDATOR = 'https://jigsaw.w3.org/css-validator/validator';

const LH_CATEGORIES = ['performance', 'accessibility', 'best-practices'];

const supportsColor = process.stdout.isTTY && !process.env.NO_COLOR;
const paint = (code) => (text) => (supportsColor ? `\u001b[${code}m${text}\u001b[0m` : text);
const bold = paint('1');
const red = paint('31');
const green = paint('32');
const yellow = paint('33');
const dim = paint('2');

const results = [];

class ConfigError extends Error {}

function parseListArg(prefix) {
    return new Set(
        process.argv
            .slice(2)
            .filter((arg) => arg.startsWith(prefix))
            .flatMap((arg) => arg.slice(prefix.length).split(','))
            .filter(Boolean),
    );
}

const only = parseListArg('--only=');
const pageFilter = parseListArg('--page=');
const VALID_CHECKS = ['html', 'css', 'contrast', 'lighthouse'];
const shouldRun = (name) => only.size === 0 || only.has(name);

function validateOnly() {
    for (const check of only) {
        if (!VALID_CHECKS.includes(check)) {
            throw new ConfigError(
                `Unknown check "${check}". Use one of: ${VALID_CHECKS.join(', ')}.`,
            );
        }
    }
}

function header(title) {
    console.log(`\n${bold(`▸ ${title}`)}`);
}

function pass(message, indent = 2) {
    console.log(`${' '.repeat(indent)}${green('✔')} ${message}`);
}

function fail(message, indent = 2) {
    console.log(`${' '.repeat(indent)}${red('✖')} ${message}`);
}

function warn(message, indent = 2) {
    console.log(`${' '.repeat(indent)}${yellow('!')} ${message}`);
}

function record(label, ok) {
    results.push({ label, ok });
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const safeName = (name) =>
    name
        .replace(/[^a-z0-9_-]+/gi, '-')
        .replace(/^-+|-+$/g, '') || 'page';

const asArray = (value) => {
    if (!value) {
        return [];
    }

    return Array.isArray(value) ? value : [value];
};

async function loadConfig() {
    const module = await import(pathToFileURL(CONFIG_FILE).href);
    const config = module.default;

    if (!config || !Array.isArray(config.pages) || config.pages.length === 0) {
        throw new ConfigError(`No pages configured in ${CONFIG_FILE}`);
    }

    return config;
}

function normalizePage(page) {
    if (!page || typeof page !== 'object') {
        throw new ConfigError('Every page entry must be an object.');
    }

    const url = page.url ? new URL(page.url) : null;
    const host = page.host ?? url?.hostname;
    const requestPath = page.path ?? (url ? `${url.pathname}${url.search}` : null);

    if (!host || !requestPath) {
        throw new ConfigError(
            `Page "${page.name ?? '?'}" needs either "url" or "host" + "path".`,
        );
    }

    return {
        name: page.name ?? requestPath,
        host,
        path: requestPath,
        headers: { ...(page.headers ?? {}) },
        thresholds: { ...(page.thresholds ?? {}) },
    };
}

function resolveThresholds(config, page) {
    const thresholds = { ...(config.thresholds ?? {}), ...page.thresholds };

    const overrides = {
        performance: process.env.UI_CHECK_PERFORMANCE,
        accessibility: process.env.UI_CHECK_ACCESSIBILITY,
        'best-practices': process.env.UI_CHECK_BEST_PRACTICES,
    };

    for (const [category, value] of Object.entries(overrides)) {
        if (value !== undefined) {
            thresholds[category] = Number(value);
        }
    }

    return thresholds;
}

function httpGet(url, headers) {
    return new Promise((resolve, reject) => {
        const req = request(url, { headers }, (res) => {
            let body = '';
            res.setEncoding('utf8');
            res.on('data', (chunk) => {
                body += chunk;
            });
            res.on('end', () => resolve({ status: res.statusCode ?? 0, body }));
        });
        req.on('error', reject);
        req.setTimeout(15_000, () => req.destroy(new Error(`Timed out requesting ${url}`)));
        req.end();
    });
}

function startServer(config, bind) {
    const command = config.server?.command ?? [
        'php',
        '-S',
        '{bind}:{port}',
        '-t',
        'public',
        'tools/router.php',
    ];

    const [binary, ...args] = command.map((arg) =>
        String(arg).replaceAll('{bind}', bind).replaceAll('{port}', String(PORT)),
    );

    const server = spawn(binary, args, {
        cwd: ROOT,
        env: { ...process.env, ...(config.server?.env ?? {}) },
        stdio: ['ignore', 'pipe', 'pipe'],
    });

    let output = '';
    server.stdout.on('data', (chunk) => {
        output += chunk;
    });
    server.stderr.on('data', (chunk) => {
        output += chunk;
    });
    server.on('exit', (code) => {
        if (code !== 0 && code !== null) {
            process.stderr.write(output);
        }
    });

    return server;
}

async function waitForServer(server, page, bind) {
    const url = `http://${bind}:${PORT}${page.path}`;
    const deadline = Date.now() + 30_000;

    while (Date.now() < deadline) {
        if (server.exitCode !== null) {
            throw new Error(`Server exited early with code ${server.exitCode}`);
        }

        try {
            await httpGet(url, requestHeaders(page));
            return;
        } catch {
            // Server is not ready yet.
        }

        await sleep(250);
    }

    throw new Error(`Server did not become ready at ${url} within 30s`);
}

function requestHeaders(page) {
    return { Host: page.host, Accept: 'text/html', ...page.headers };
}

function publicUrl(page) {
    return `http://${page.host}:${PORT}${page.path}`;
}

function pageFile(page) {
    return path.join(REPORT_DIR, `${safeName(page.name)}.html`);
}

function pathnameOf(url) {
    return new URL(url).pathname;
}

async function persistPage(page, html) {
    await mkdir(REPORT_DIR, { recursive: true });
    await writeFile(pageFile(page), html, 'utf8');
}

function javaMajorVersion(binary) {
    const result = spawnSync(binary, ['-version'], { encoding: 'utf8' });
    const output = `${result.stderr ?? ''}${result.stdout ?? ''}`;
    const match = output.match(/version "(\d+)(?:\.(\d+))?/);

    if (!match) {
        return null;
    }

    return Number(match[1]) === 1 && match[2] ? Number(match[2]) : Number(match[1]);
}

function findJava() {
    const candidates = [];

    if (process.env.JAVA_PATH) {
        candidates.push(process.env.JAVA_PATH);
    }

    if (process.env.JAVA_HOME) {
        candidates.push(
            path.join(
                process.env.JAVA_HOME,
                'bin',
                process.platform === 'win32' ? 'java.exe' : 'java',
            ),
        );
    }

    const jvmDir = '/usr/lib/jvm';
    if (existsSync(jvmDir)) {
        readdirSync(jvmDir)
            .map((entry) => ({ entry, version: Number(entry.match(/^java-(\d+)/)?.[1] ?? 0) }))
            .filter(({ version }) => version >= 11)
            .sort((a, b) => b.version - a.version)
            .forEach(({ entry }) => candidates.push(path.join(jvmDir, entry, 'bin', 'java')));
    }

    candidates.push(process.platform === 'win32' ? 'java.exe' : 'java');

    for (const candidate of candidates) {
        const isBare = candidate === 'java' || candidate === 'java.exe';

        if (!isBare && !existsSync(candidate)) {
            continue;
        }

        const version = javaMajorVersion(candidate);
        if (version !== null && version >= 11) {
            return candidate;
        }
    }

    return null;
}

function parseJsonOutput(stdout, stderr) {
    for (const candidate of [stdout, stderr]) {
        const trimmed = candidate.trim();
        if (trimmed === '') {
            continue;
        }

        try {
            return JSON.parse(trimmed);
        } catch {
            // The checker may print log lines around the JSON object.
        }

        const start = trimmed.indexOf('{');
        const end = trimmed.lastIndexOf('}');

        if (start !== -1 && end > start) {
            try {
                return JSON.parse(trimmed.slice(start, end + 1));
            } catch {
                // Keep looking.
            }
        }
    }

    return null;
}

function runVnu(java, file) {
    return new Promise((resolve, reject) => {
        const child = spawn(java, ['-jar', VNU_JAR, '--format', 'json', file], {
            cwd: ROOT,
        });

        let stdout = '';
        let stderr = '';

        child.stdout.on('data', (chunk) => {
            stdout += chunk;
        });
        child.stderr.on('data', (chunk) => {
            stderr += chunk;
        });
        child.on('error', reject);
        child.on('close', (code) => {
            const parsed = parseJsonOutput(stdout, stderr);

            if (parsed) {
                resolve(parsed);
                return;
            }

            reject(
                new Error(
                    `W3C Nu HTML Checker failed (exit ${code}): ${(stderr || stdout).trim()}`,
                ),
            );
        });
    });
}

async function validateHtml(page, response, java) {
    if (response.status < 200 || response.status >= 300) {
        fail(`${page.name}: HTTP ${response.status} (add auth headers to the page config?)`, 4);
        record(`html:${page.name}`, false);
        return;
    }

    const result = await runVnu(java, pageFile(page));
    const messages = result.messages ?? [];

    let errors = 0;
    let warnings = 0;
    const lines = [];

    for (const message of messages) {
        const isError = message.type === 'error';
        if (isError) {
            errors += 1;
        } else {
            warnings += 1;
        }

        const severity = isError ? 'error' : 'warning';
        const location = `${message.lastLine ?? 0}:${message.firstColumn ?? 0}`;
        const type = `${message.type}${message.subType ? `/${message.subType}` : ''}`;
        lines.push(
            `        ${location.padEnd(8)} ${severity.padEnd(7)} ${message.message} ${dim(`[${type}]`)}`,
        );
    }

    if (errors === 0) {
        if (warnings > 0) {
            warn(`${page.name}: valid, ${warnings} warning(s).`, 4);
            for (const line of lines) {
                console.log(line);
            }
        } else {
            const version = result.version ? ` (v${result.version.split(' ')[0]})` : '';
            pass(`${page.name}: valid per W3C Nu HTML Checker${version}.`, 4);
        }
        record(`html:${page.name}`, true);
        return;
    }

    fail(`${page.name}: ${errors} error(s), ${warnings} warning(s).`, 4);
    for (const line of lines) {
        console.log(line);
    }
    record(`html:${page.name}`, false);
}

async function collectCssFiles(patterns, excludes = []) {
    const files = new Set();
    const excluded = new Set(excludes.map((file) => path.resolve(ROOT, file)));

    for (const pattern of patterns) {
        for await (const file of glob(pattern, { cwd: ROOT })) {
            const absolute = path.resolve(ROOT, file);
            if (excluded.has(absolute)) {
                continue;
            }
            files.add(absolute);
        }
    }

    return [...files].sort();
}

async function validateCssFile(file, config) {
    const endpoint = config.css?.validator ?? W3C_CSS_VALIDATOR;
    const profile = config.css?.profile ?? 'css3svg';
    const css = await readFile(file, 'utf8');
    const relative = path.relative(ROOT, file);

    const params = new URLSearchParams({
        text: css,
        output: 'json',
        profile,
        warning: '1',
    });

    let response;
    try {
        response = await fetch(`${endpoint}?${params}`, {
            headers: { Accept: 'application/json' },
        });
    } catch (error) {
        throw new Error(
            `Could not reach ${endpoint}: ${error instanceof Error ? error.message : String(error)}`,
        );
    }

    if (!response.ok) {
        throw new Error(`${endpoint} returned HTTP ${response.status}`);
    }

    const payload = await response.json();
    const validation = payload.cssvalidation;

    if (!validation || !validation.result) {
        throw new Error(`${endpoint} returned an unexpected payload.`);
    }

    const errors = Number(validation.result?.errorcount ?? 0);
    const warnings = Number(validation.result?.warningcount ?? 0);

    if (errors > 0) {
        fail(`${relative}: ${errors} error(s), ${warnings} warning(s).`, 4);
        for (const error of asArray(validation.errors)) {
            console.log(
                `        ${String(error.line ?? 0).padEnd(8)} error   ${error.message}`,
            );
        }
    } else if (warnings > 0) {
        warn(`${relative}: valid, ${warnings} warning(s).`, 4);
        for (const warning of asArray(validation.warnings)) {
            console.log(
                `        ${String(warning.line ?? 0).padEnd(8)} warning ${warning.message}`,
            );
        }
    } else {
        pass(`${relative}: valid per W3C CSS Validator.`, 4);
    }

    return errors === 0;
}

async function checkCss(config) {
    header('CSS · W3C CSS Validation Service');

    const patterns = config.css?.files ?? ['public/**/*.css'];
    const files = await collectCssFiles(patterns, config.css?.exclude);

    if (files.length === 0) {
        warn(`No CSS files matched ${patterns.join(', ')}.`);
        record('css', true);
        return;
    }

    let ok = true;

    for (const file of files) {
        try {
            ok = (await validateCssFile(file, config)) && ok;
        } catch (error) {
            fail(
                `${path.relative(ROOT, file)}: ${error instanceof Error ? error.message : String(error)}`,
                4,
            );
            ok = false;
        }
    }

    record('css', ok);
}

function parseColor(value) {
    const trimmed = value.trim();
    const hex = trimmed.match(/^#([0-9a-f]{3,8})$/i);

    if (hex) {
        let digits = hex[1];
        if (digits.length === 3 || digits.length === 4) {
            digits = digits.split('').map((digit) => digit + digit).join('');
        }

        if (digits.length === 6 || digits.length === 8) {
            return [
                Number.parseInt(digits.slice(0, 2), 16),
                Number.parseInt(digits.slice(2, 4), 16),
                Number.parseInt(digits.slice(4, 6), 16),
            ];
        }
    }

    const rgb = trimmed.match(/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/i);
    if (rgb) {
        return [Number(rgb[1]), Number(rgb[2]), Number(rgb[3])];
    }

    return null;
}

function relativeLuminance([red, green, blue]) {
    const [r, g, b] = [red, green, blue].map((channel) => {
        const value = channel / 255;
        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrastRatio(foreground, background) {
    const first = relativeLuminance(foreground);
    const second = relativeLuminance(background);
    const [lighter, darker] = first > second ? [first, second] : [second, first];

    return (lighter + 0.05) / (darker + 0.05);
}

function findBracedBlock(source, startIndex) {
    const open = source.indexOf('{', startIndex);
    if (open === -1) {
        return null;
    }

    let depth = 0;
    for (let index = open; index < source.length; index += 1) {
        if (source[index] === '{') {
            depth += 1;
        }
        if (source[index] === '}') {
            depth -= 1;
            if (depth === 0) {
                return { inner: source.slice(open + 1, index), end: index };
            }
        }
    }

    return null;
}

function extractBracedBlock(source, startIndex) {
    return findBracedBlock(source, startIndex)?.inner ?? null;
}

function parseDeclarations(block) {
    const properties = {};
    for (const declaration of block.split(';')) {
        const separator = declaration.indexOf(':');
        if (separator === -1) {
            continue;
        }

        const name = declaration.slice(0, separator).trim();
        const value = declaration.slice(separator + 1).trim();

        if (name.startsWith('--')) {
            properties[name] = value;
        }
    }

    return properties;
}

function parseCustomProperties(source) {
    const properties = {};

    for (const match of source.matchAll(/(?::root|@theme)\s*\{/g)) {
        const block = findBracedBlock(source, match.index);
        if (!block) {
            continue;
        }

        Object.assign(properties, parseDeclarations(block.inner));
    }

    return properties;
}

function extractThemes(css) {
    const withoutComments = css.replace(/\/\*[\s\S]*?\*\//g, '');
    const darkStart = withoutComments.search(
        /@media\s*\(\s*prefers-color-scheme:\s*dark\s*\)/,
    );

    let lightSource = withoutComments;
    let darkSource = null;

    if (darkStart !== -1) {
        const darkBlock = findBracedBlock(withoutComments, darkStart);
        if (darkBlock) {
            lightSource =
                withoutComments.slice(0, darkStart) + withoutComments.slice(darkBlock.end + 1);
            darkSource = darkBlock.inner;
        }
    }

    const light = parseCustomProperties(lightSource);
    const dark = darkSource ? { ...light, ...parseCustomProperties(darkSource) } : { ...light };

    return { light, dark };
}

function checkContrast(config) {
    header('Contrast · WCAG AAA');

    const file = path.resolve(ROOT, config.contrast?.css ?? 'assets/styles/app.css');
    const pairs = config.contrast?.pairs ?? [];

    if (!existsSync(file)) {
        fail(`${path.relative(ROOT, file)}: file not found.`, 4);
        record('contrast', false);
        return;
    }

    const themes = extractThemes(readFileSync(file, 'utf8'));
    let ok = true;

    for (const themeName of ['light', 'dark']) {
        const properties = themes[themeName];
        const failures = [];

        if (!properties || Object.keys(properties).length === 0) {
            fail(`${themeName}: no custom properties found.`, 4);
            ok = false;
            continue;
        }

        for (const pair of pairs) {
            const foreground = parseColor(properties[pair.foreground] ?? '');
            const background = parseColor(properties[pair.background] ?? '');
            const minimum = Number(pair.minimum ?? 7);

            if (!foreground || !background) {
                failures.push(`${pair.foreground} on ${pair.background}: missing color`);
                continue;
            }

            const ratio = contrastRatio(foreground, background);

            if (ratio < minimum) {
                failures.push(
                    `${pair.foreground} on ${pair.background}: ${ratio.toFixed(2)}:1 (min ${minimum}:1)`,
                );
            }
        }

        if (failures.length === 0) {
            pass(`${themeName}: ${pairs.length} pair(s) meet the thresholds.`, 4);
        } else {
            fail(`${themeName}: ${failures.length} pair(s) below the threshold.`, 4);
            for (const failure of failures) {
                console.log(`        ${red('•')} ${failure}`);
            }
            ok = false;
        }
    }

    record('contrast', ok);
}

function findChrome() {
    if (process.env.CHROME_PATH && existsSync(process.env.CHROME_PATH)) {
        return process.env.CHROME_PATH;
    }

    return [
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/snap/bin/chromium',
    ].find((candidate) => existsSync(candidate));
}

async function launchChrome(pages, bind) {
    const chromePath = findChrome();
    if (!chromePath) {
        throw new Error('No Chromium/Chrome binary found. Set CHROME_PATH.');
    }

    const hosts = [...new Set(pages.map((page) => page.host))];
    const hostResolverRules = hosts.map((host) => `MAP ${host} ${bind}`).join(', ');

    return chromeLauncher.launch({
        chromePath,
        chromeFlags: [
            '--headless=new',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-first-run',
            `--host-resolver-rules=${hostResolverRules}`,
        ],
        logLevel: 'error',
    });
}

function lighthouseHeaders(page) {
    const headers = Object.entries(page.headers).filter(
        ([name]) => name.toLowerCase() !== 'host',
    );

    return headers.length > 0 ? Object.fromEntries(headers) : undefined;
}

function failingAudits(lhr, category) {
    return category.auditRefs.flatMap((ref) => {
        const audit = lhr.audits[ref.id];

        if (
            !audit ||
            ref.weight === 0 ||
            audit.score === null ||
            audit.score >= 1 ||
            audit.scoreDisplayMode === 'informative' ||
            audit.scoreDisplayMode === 'notApplicable'
        ) {
            return [];
        }

        return [{
            id: ref.id,
            title: audit.title,
        }];
    });
}

async function checkLighthouse(page, chrome, thresholds) {
    const runnerResult = await lighthouse(publicUrl(page), {
        port: chrome.port,
        output: 'json',
        logLevel: 'error',
        onlyCategories: LH_CATEGORIES,
        formFactor: 'desktop',
        screenEmulation: {
            mobile: false,
            width: 1350,
            height: 940,
            deviceScaleFactor: 1,
            disabled: false,
        },
        throttling: {
            rttMs: 40,
            throughputKbps: 10 * 1024,
            cpuSlowdownMultiplier: 1,
            requestLatencyMs: 0,
            downloadThroughputKbps: 0,
            uploadThroughputKbps: 0,
        },
        extraHeaders: lighthouseHeaders(page),
    });

    if (!runnerResult) {
        throw new Error(`Lighthouse did not return a result for ${publicUrl(page)}`);
    }

    const { lhr, report } = runnerResult;
    await mkdir(REPORT_DIR, { recursive: true });
    await writeFile(
        path.join(REPORT_DIR, `${safeName(page.name)}.lighthouse.json`),
        report,
        'utf8',
    );

    console.log(`    ${bold(page.name)}`);
    let ok = true;

    const finalUrl = lhr.finalDisplayedUrl ?? lhr.finalUrl;
    if (finalUrl && pathnameOf(finalUrl) !== pathnameOf(publicUrl(page))) {
        warn(
            `${page.name}: Lighthouse ended at ${finalUrl} instead of ${publicUrl(page)}; check auth headers.`,
            6,
        );
        ok = false;
    }

    for (const categoryId of LH_CATEGORIES) {
        const category = lhr.categories[categoryId];
        if (!category) {
            continue;
        }

        const score = category.score ?? 0;
        const threshold = thresholds[categoryId];

        if (threshold === undefined || threshold === null) {
            console.log(`      ${dim('·')} ${category.title}: ${Math.round(score * 100)}`);
            continue;
        }

        const label = `${category.title}: ${Math.round(score * 100)} (min ${Math.round(threshold * 100)})`;

        if (score >= threshold) {
            pass(label, 6);
            continue;
        }

        ok = false;
        fail(label, 6);

        for (const audit of failingAudits(lhr, category)) {
            console.log(`          ${red('•')} ${audit.title} ${dim(`[${audit.id}]`)}`);
        }
    }

    record(`lighthouse:${page.name}`, ok);
}

async function main() {
    validateOnly();

    const config = await loadConfig();
    const allPages = config.pages.map(normalizePage);
    const pages =
        pageFilter.size === 0
            ? allPages
            : allPages.filter((page) => pageFilter.has(page.name));

    if (pages.length === 0) {
        throw new ConfigError(`No pages matched --page=${[...pageFilter].join(',')}`);
    }

    console.log(
        dim(`Validating ${pages.length} page(s): ${pages.map((page) => page.name).join(', ')}`),
    );

    const runHtml = shouldRun('html');
    const runCss = shouldRun('css');
    const runContrast = shouldRun('contrast');
    const runLighthouse = shouldRun('lighthouse');
    const needsServer = runHtml || runLighthouse;
    const bind = process.env.UI_CHECK_BIND ?? config.server?.bind ?? '127.0.0.1';

    let server;
    let chrome;

    try {
        if (needsServer) {
            console.log(dim(`Starting server on ${bind}:${PORT}`));
            server = startServer(config, bind);
            await waitForServer(server, pages[0], bind);
        }

        if (runHtml) {
            header('HTML · W3C Nu HTML Checker');

            if (!existsSync(VNU_JAR)) {
                throw new ConfigError(
                    `W3C Nu HTML Checker not found at ${VNU_JAR}. Run "npm install".`,
                );
            }

            const java = findJava();
            if (!java) {
                throw new ConfigError(
                    'Java 11+ is required by vnu-jar. Set JAVA_PATH or JAVA_HOME.',
                );
            }

            for (const page of pages) {
                const url = `http://${bind}:${PORT}${page.path}`;
                const response = await httpGet(url, requestHeaders(page));
                await persistPage(page, response.body);
                await validateHtml(page, response, java);
            }

            console.log(dim(`\n    Rendered pages saved to var/ui-check/`));
        }

        if (runCss) {
            await checkCss(config);
        }

        if (runContrast) {
            checkContrast(config);
        }

        if (runLighthouse) {
            header('Accessibility & performance · Lighthouse (axe-core)');
            chrome = await launchChrome(pages, bind);

            for (const page of pages) {
                await checkLighthouse(page, chrome, resolveThresholds(config, page));
            }
        }
    } finally {
        if (chrome) {
            await chrome.kill();
        }

        if (server && server.exitCode === null) {
            server.kill('SIGTERM');
        }
    }

    const failed = results.filter((result) => !result.ok);
    console.log('');

    if (failed.length > 0) {
        console.error(red(`UI checks failed: ${failed.map((result) => result.label).join(', ')}`));
        process.exitCode = 1;
        return;
    }

    console.log(green('UI checks passed.'));
}

main().catch((error) => {
    if (error instanceof ConfigError) {
        console.error(red(error.message));
    } else {
        console.error(red(error instanceof Error ? error.stack ?? error.message : String(error)));
    }
    process.exitCode = 1;
});
