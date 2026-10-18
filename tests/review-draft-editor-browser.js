'use strict';

// Run the shipped importer against a bounded WordPress editor/REST fixture.
// This exercises lifecycle and reload protection, not a live save or import.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');

const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'
].find(fs.existsSync);
const scriptPath = process.env.LUNARA_IMPORT_SCRIPT || path.join(__dirname, '../assets/js/lunara-review-draft-import-admin.js');

// Synthetic transform captured from the installed WordPress formatting functions.
// The mock recognizes only these exact states; it never treats arbitrary
// whitespace, comments, attributes, or formatting as equivalent.
const formatting = {
    saved: '<!-- Note:\nA saved review.\n-->\n\n<em>Saved standfirst.</em>\n\n\nSaved body with café and 🎬.\n\n<h2>Verdict</h2>\n\nKeep this wording.',
    visual: '<p><!-- Note: A saved review. --></p>\n<p><em>Saved standfirst.</em></p>\n<p>Saved body with café and 🎬.</p>\n<h2>Verdict</h2>\n<p>Keep this wording.</p>\n',
    roundtrip: '<!-- Note: A saved review. -->\n\n<em>Saved standfirst.</em>\n\nSaved body with café and 🎬.\n<h2>Verdict</h2>\nKeep this wording.'
};
const markup = '<!doctype html><html><body><form id="post">' +
    '<input id="title" name="post_title" value="Saved review">' +
    '<textarea id="content" name="content"></textarea>' +
    '<textarea id="excerpt" name="excerpt">Saved excerpt.</textarea>' +
    '<div class="acf-field"><textarea id="acf-note" name="acf[field_note]">Saved pairing argument.</textarea></div>' +
    '<section data-lunara-review-import data-review-id="10">' +
    '<input type="file" data-lunara-review-import-file>' +
    '<textarea data-lunara-review-import-html>&lt;p&gt;Independent import source.&lt;/p&gt;</textarea>' +
    '<button type="button" data-lunara-review-import-preview>Preview</button>' +
    '<button type="button" data-lunara-review-import-apply disabled>Apply</button>' +
    '<p data-lunara-review-import-status></p><p data-lunara-review-import-alert hidden></p>' +
    '</section></form></body></html>';
const selectors = {
    root: '[data-lunara-review-import]',
    preview: '[data-lunara-review-import-preview]',
    apply: '[data-lunara-review-import-apply]',
    alert: '[data-lunara-review-import-alert]',
    status: '[data-lunara-review-import-status]'
};

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    let checks = 0;
    let passed = 0;
    const failures = [];
    const equal = (actual, expected, message) => { assert.deepEqual(actual, expected, message); checks++; };
    const matches = (actual, expected, message) => { assert.match(actual, expected, message); checks++; };

    async function fixture(settings = {}) {
        const page = await browser.newPage();
        page.setDefaultTimeout(4000);
        const errors = [];
        let navigations = 0;
        page.on('pageerror', error => errors.push(error.message));
        await page.route('http://lunara-editor.test/**', async route => {
            navigations++;
            await route.fulfill({ contentType: 'text/html', body: markup });
        });
        await page.clock.install({ time: new Date('2026-10-18T01:00:00Z') });
        await page.clock.pauseAt(new Date('2026-10-18T01:00:01Z'));
        await page.goto('http://lunara-editor.test/fixture');
        await page.evaluate(({ config, forms }) => {
            const root = document.querySelector('[data-lunara-review-import]');
            const content = document.querySelector('#content');
            content.value = forms.saved;
            const subscribers = [];
            const managerEvents = {};
            const storeState = { saving: false, autosaving: false, succeeded: false, dirty: false };
            const store = {
                isSavingPost: () => storeState.saving,
                isAutosavingPost: () => storeState.autosaving,
                didPostSaveRequestSucceed: () => storeState.succeeded,
                isEditedPostDirty: () => storeState.dirty,
                getEditedPostContent: () => content.value
            };
            window.LunaraReviewDraftImport = { reviewId: 10, restBase: '/review-import/', nonce: 'editor-fixture' };
            window.switchEditors = {
                wpautop(value) { return value === forms.saved || value === forms.roundtrip ? forms.visual : value; },
                pre_wpautop(value) { return value === forms.visual ? forms.roundtrip : value; }
            };
            window.fixture = {
                forms,
                root,
                requests: [],
                pendingApply: null,
                storeState,
                editor: null,
                store(patch) {
                    Object.assign(storeState, patch);
                    subscribers.forEach(callback => callback());
                },
                addEditor() {
                    if (this.editor) return this.editor;
                    const events = {};
                    this.editor = {
                        id: 'content',
                        initialized: false,
                        hidden: false,
                        dirty: false,
                        raw: forms.visual,
                        startContent: forms.visual,
                        on(names, callback) {
                            names.split(/\s+/).forEach(name => (events[name] ||= []).push(callback));
                        },
                        emit(name) { (events[name] || []).forEach(callback => callback({ target: this })); },
                        isDirty() { return this.dirty; },
                        isHidden() { return this.hidden; },
                        getContent() { return this.raw; },
                        getElement() { return content; }
                    };
                    window.tinymce.editors.push(this.editor);
                    (managerEvents.AddEditor || []).forEach(callback => callback({ editor: this.editor }));
                    return this.editor;
                },
                initialize({ normalize = true } = {}) {
                    const editor = this.addEditor();
                    editor.raw = window.switchEditors.wpautop(content.value);
                    editor.startContent = editor.raw;
                    if (normalize) content.value = window.switchEditors.pre_wpautop(editor.raw);
                    editor.initialized = true;
                    editor.emit('init');
                },
                field(selector, value, event = 'input') {
                    const field = document.querySelector(selector);
                    field.value = value;
                    if (event) field.dispatchEvent(new Event(event, { bubbles: true }));
                },
                raw(value, { dirty = true, event = 'change' } = {}) {
                    this.editor.raw = value;
                    this.editor.dirty = dirty;
                    if (event) this.editor.emit(event);
                },
                toggle(hidden) {
                    if (hidden) {
                        content.value = window.switchEditors.pre_wpautop(this.editor.raw);
                    } else {
                        this.editor.raw = window.switchEditors.wpautop(content.value);
                    }
                    this.editor.hidden = hidden;
                    this.editor.emit(hidden ? 'hide' : 'show');
                },
                resolveApply() {
                    if (!this.pendingApply) throw new Error('No queued Apply request');
                    this.pendingApply({ ok: true, json: async () => ({ valid: true, alreadyImported: false }) });
                    this.pendingApply = null;
                }
            };
            window.tinymce = {
                editors: [],
                get(id) { return this.editors.find(editor => editor.id === id) || null; },
                on(name, callback) { (managerEvents[name] ||= []).push(callback); }
            };
            if (config.gutenberg) {
                window.wp = { data: {
                    select(name) { return name === 'core/editor' ? store : null; },
                    subscribe(callback) {
                        subscribers.push(callback);
                        return () => { const index = subscribers.indexOf(callback); if (index >= 0) subscribers.splice(index, 1); };
                    }
                } };
            }
            window.fetch = (url, options) => {
                window.fixture.requests.push({ url, payload: JSON.parse(options.body) });
                if (url.endsWith('/apply') && config.deferApply) {
                    return new Promise(resolve => { window.fixture.pendingApply = resolve; });
                }
                return Promise.resolve({ ok: true, json: async () => ({ valid: true, existing: { content: false }, alreadyImported: false }) });
            };
            if (config.tiny === 'ready') window.fixture.initialize();
            else if (config.tiny === 'uninitialized') window.fixture.addEditor();
        }, { config: settings, forms: formatting });
        await page.addScriptTag({ path: scriptPath });
        return { page, errors, navigations: () => navigations };
    }

    async function run(name, settings, callback) {
        let context;
        try {
            context = await fixture(settings);
            await callback(context);
            equal(context.errors, [], 'No unhandled browser errors');
            passed++;
            console.log('PASS: ' + name);
        } catch (error) {
            failures.push(name);
            console.error('FAIL: ' + name + ': ' + error.message);
        } finally {
            if (context) await context.page.close();
        }
    }

    async function initialize(page) {
        await page.evaluate(() => window.fixture.initialize());
    }

    async function expectPreview(page) {
        const before = await page.evaluate(() => window.fixture.requests.length);
        await page.locator(selectors.preview).click();
        await page.waitForFunction(previous => window.fixture.requests.length > previous || !document.querySelector('[data-lunara-review-import-alert]').hidden, before);
        const state = await page.evaluate(() => ({ requests: window.fixture.requests, alert: document.querySelector('[data-lunara-review-import-alert]').textContent }));
        equal(state.requests.length, before + 1, 'A clean editor must issue exactly one Preview request: ' + state.alert);
        equal(state.requests.at(-1).url, '/review-import/preview', 'The permitted request is Preview');
        await page.waitForFunction(() => !document.querySelector('[data-lunara-review-import-apply]').disabled);
        equal(await page.locator(selectors.alert).isHidden(), true, 'No false unsaved-change warning remains');
    }

    async function expectBlocked(page) {
        const before = await page.evaluate(() => window.fixture.requests.length);
        await page.locator(selectors.preview).click();
        await page.waitForFunction(() => !document.querySelector('[data-lunara-review-import-alert]').hidden);
        matches(await page.locator(selectors.alert).textContent(), /Save the Review draft/, 'Unsaved edits must produce an actionable save warning');
        equal(await page.evaluate(() => window.fixture.requests.length), before, 'Dirty editors must not send a Preview request');
        equal(await page.locator(selectors.apply).isDisabled(), true, 'Dirty editors must disable Apply');
        equal(await page.locator(selectors.preview).isEnabled(), true, 'The blocked guard must not leave the importer busy');
    }

    async function expectNoReload(context) {
        await context.page.clock.runFor(600);
        // Allow the browser's navigation queue to settle; its 500ms source timer
        // has already advanced deterministically under the installed clock.
        await context.page.waitForTimeout(25);
        equal(context.navigations(), 1, 'Unsaved edits must prevent navigation/reload');
    }

    try {
        await run('Unmodified Text editor remains importable', {}, async ({ page }) => { await expectPreview(page); });
        await run('Already initialized clean Visual editor remains importable', { tiny: 'ready' }, async ({ page }) => { await expectPreview(page); });
        await run('Delayed clean TinyMCE startup permits only the known WordPress roundtrip', {}, async ({ page }) => {
            const original = await page.evaluate(() => window.fixture.root._lunaraEditorState.find(entry => entry.field.id === 'content').value);
            await page.clock.runFor(10000);
            await initialize(page);
            equal(await page.locator('#content').inputValue(), formatting.roundtrip, 'Startup applies the captured formatting roundtrip');
            equal(await page.evaluate(() => window.fixture.root._lunaraEditorState.find(entry => entry.field.id === 'content').value), original, 'Startup must preserve the original saved baseline');
            await expectPreview(page);
        });
        await run('A registered but uninitialized editor cannot authorize formatting drift', { tiny: 'uninitialized' }, async ({ page }) => {
            await page.evaluate(() => window.fixture.field('#content', window.fixture.forms.roundtrip, null));
            await expectBlocked(page);
        });
        await run('A missing TinyMCE editor cannot authorize formatting drift', {}, async ({ page }) => {
            await page.evaluate(() => window.fixture.field('#content', window.fixture.forms.roundtrip, null));
            await expectBlocked(page);
        });
        await run('Missing WordPress formatting functions fail closed', {}, async ({ page }) => {
            await initialize(page);
            await page.evaluate(() => { delete window.switchEditors; });
            await expectBlocked(page);
        });

        for (const [label, selector, value] of [
            ['title', '#title', 'Unsaved title'],
            ['excerpt', '#excerpt', 'Unsaved excerpt.'],
            ['ACF pairing argument', '#acf-note', 'Unsaved pairing argument.'],
            ['body wording', '#content', formatting.roundtrip + '\nAn unsaved sentence.'],
            ['body formatting', '#content', formatting.roundtrip.replace('<em>', '<strong>').replace('</em>', '</strong>')],
            ['body attribute', '#content', formatting.roundtrip.replace('<h2>', '<h2 class="unsaved">')],
            ['body comment', '#content', formatting.roundtrip.replace('A saved review.', 'An edited review.')]
        ]) {
            await run('Programmatic ' + label + ' changes remain dirty without an event', {}, async ({ page }) => {
                await initialize(page);
                await page.evaluate(({ selector, value }) => window.fixture.field(selector, value, null), { selector, value });
                await expectBlocked(page);
            });
        }

        await run('A form input event remains dirty even if the field is restored', {}, async ({ page }) => {
            await initialize(page);
            await page.evaluate(() => {
                window.fixture.field('#title', 'Unsaved title');
                window.fixture.field('#title', 'Saved review', null);
            });
            await expectBlocked(page);
        });
        await run('TinyMCE dirty flag wins without a form event', { tiny: 'ready' }, async ({ page }) => {
            await page.evaluate(() => { window.fixture.editor.dirty = true; });
            await expectBlocked(page);
        });
        await run('TinyMCE raw edits are blocked even with clean dirty flags', { tiny: 'ready' }, async ({ page }) => {
            await page.evaluate(() => window.fixture.raw(window.fixture.forms.visual + '<p>Unsaved visual text.</p>', { dirty: false, event: null }));
            await expectBlocked(page);
        });
        await run('Clean Visual and Text toggles preserve a usable Preview', {}, async ({ page }) => {
            await initialize(page);
            await page.evaluate(() => window.fixture.toggle(true));
            await expectPreview(page);
            await page.evaluate(() => window.fixture.toggle(false));
            await expectPreview(page);
        });
        await run('Edited Visual content stays blocked after toggling to Text', {}, async ({ page }) => {
            await initialize(page);
            await page.evaluate(() => {
                window.fixture.raw(window.fixture.forms.visual + '<p>Unsaved visual text.</p>');
                window.fixture.toggle(true);
            });
            await expectBlocked(page);
        });
        await run('Edited Text content stays blocked after toggling to Visual', {}, async ({ page }) => {
            await initialize(page);
            await page.evaluate(() => {
                window.fixture.toggle(true);
                window.fixture.field('#content', window.fixture.forms.roundtrip + '\nUnsaved text.');
                window.fixture.toggle(false);
            });
            await expectBlocked(page);
        });
        await run('Edits before TinyMCE initialization are never swallowed', {}, async ({ page }) => {
            await page.evaluate(() => window.fixture.field('#content', window.fixture.forms.saved + '\nEarly edit.', null));
            await initialize(page);
            await expectBlocked(page);
        });
        await run('Other field edits before TinyMCE initialization are never swallowed', {}, async ({ page }) => {
            await page.evaluate(() => window.fixture.field('#excerpt', 'Early excerpt edit.'));
            await initialize(page);
            await expectBlocked(page);
        });

        await run('Successful explicit Gutenberg save resets the same-generation baseline', { gutenberg: true }, async ({ page }) => {
            await page.evaluate(() => {
                window.fixture.field('#title', 'New saved title');
                window.fixture.store({ dirty: true, saving: true });
                window.fixture.store({ dirty: false, saving: false, succeeded: true });
            });
            await page.clock.runFor(1);
            await expectPreview(page);
            equal(await page.evaluate(() => window.fixture.root._lunaraEditorState.find(entry => entry.field.id === 'title').value), 'New saved title', 'A successful explicit save captures the new saved value');
        });

        for (const scenario of ['failed', 'autosave', 'in-flight edit', 'queued post-save edit', 'programmatic in-flight edit', 'programmatic queued post-save edit']) {
            await run(scenario + ' cannot clear genuine dirty state', { gutenberg: true }, async ({ page }) => {
                await page.evaluate(kind => {
                    window.fixture.field('#title', 'First unsaved title');
                    window.fixture.store({ dirty: true, saving: true, autosaving: kind === 'autosave' });
                    if (kind.includes('in-flight edit')) window.fixture.field('#excerpt', 'Edit while save is in flight.', kind.startsWith('programmatic') ? null : 'input');
                    window.fixture.store({ dirty: false, saving: false, autosaving: false, succeeded: kind !== 'failed' });
                    if (kind.includes('queued post-save edit')) window.fixture.field('#acf-note', 'Edit before queued baseline refresh.', kind.startsWith('programmatic') ? null : 'input');
                }, scenario);
                await page.clock.runFor(1);
                await expectBlocked(page);
            });
        }

        await run('A failed second save cannot let the first queued callback adopt newer edits', { gutenberg: true }, async ({ page }) => {
            await page.evaluate(() => {
                window.fixture.field('#title', 'First saved title');
                window.fixture.store({ dirty: true, saving: true });
                window.fixture.store({ dirty: false, saving: false, succeeded: true });
                // The first save's zero-delay baseline callback has not run.
                // A later save must not overwrite the callback's evidence.
                window.fixture.field('#acf-note', 'New pairing argument not saved by either request.');
                window.fixture.store({ dirty: true, saving: true, succeeded: false });
                window.fixture.store({ dirty: false, saving: false, succeeded: false });
            });
            await page.clock.runFor(1);
            await expectBlocked(page);
            equal(await page.evaluate(() => window.fixture.root._lunaraEditorState.find(entry => entry.field.id === 'acf-note').value), 'Saved pairing argument.', 'The older callback must not baseline fields from a failed newer save');
        });

        await run('Gutenberg dirty flag wins even when form fields are unchanged', { gutenberg: true }, async ({ page }) => {
            await page.evaluate(() => window.fixture.store({ dirty: true }));
            await expectBlocked(page);
        });
        await run('Successful save can baseline TinyMCE raw content without mutating its dirty flag', { gutenberg: true }, async ({ page }) => {
            await initialize(page);
            await page.evaluate(() => {
                window.fixture.raw('<p>A newly saved visual revision.</p>');
                window.fixture.field('#content', '<p>A newly saved visual revision.</p>');
                window.fixture.store({ dirty: true, saving: true });
                window.fixture.store({ dirty: false, saving: false, succeeded: true });
            });
            await page.clock.runFor(1);
            await expectPreview(page);
            equal(await page.evaluate(() => window.fixture.editor.dirty), true, 'Importer must not reset TinyMCE dirty tracking itself');
            await page.evaluate(() => window.fixture.raw('<p>An unsaved change after saving.</p>', { dirty: true, event: null }));
            await expectBlocked(page);
        });

        await run('Unchanged Apply reloads after its intended delay', { deferApply: true }, async context => {
            const { page } = context;
            await expectPreview(page);
            await page.locator(selectors.apply).click();
            await page.waitForFunction(() => !!window.fixture.pendingApply);
            await page.evaluate(() => window.fixture.resolveApply());
            await page.waitForFunction(() => document.querySelector('[data-lunara-review-import-status]').textContent.includes('Reloading'));
            await page.clock.runFor(499);
            equal(context.navigations(), 1, 'A successful Apply does not reload early');
            const loaded = page.waitForEvent('domcontentloaded');
            await page.clock.runFor(1);
            await loaded;
            equal(context.navigations(), 2, 'A clean successful Apply reloads once');
        });
        for (const scenario of ['during request', 'during delay', 'programmatic during request', 'TinyMCE during delay']) {
            await run('An edit ' + scenario + ' prevents Apply reload', { tiny: 'ready', deferApply: true }, async context => {
                const { page } = context;
                await expectPreview(page);
                await page.locator(selectors.apply).click();
                await page.waitForFunction(() => !!window.fixture.pendingApply);
                if (scenario.includes('during request')) {
                    await page.evaluate(programmatic => window.fixture.field('#title', 'Unsaved while applying', programmatic ? null : 'input'), scenario.startsWith('programmatic'));
                }
                await page.evaluate(() => window.fixture.resolveApply());
                await page.waitForFunction(() => document.querySelector('[data-lunara-review-import]').getAttribute('aria-busy') === 'false');
                if (scenario === 'during delay') await page.evaluate(() => window.fixture.field('#excerpt', 'Unsaved during reload delay.'));
                if (scenario === 'TinyMCE during delay') await page.evaluate(() => window.fixture.raw(window.fixture.forms.visual + '<p>Late unsaved visual edit.</p>', { dirty: false, event: null }));
                await expectNoReload(context);
                equal(await page.locator(selectors.apply).isDisabled(), true, 'A completed Apply cannot be repeated after a reload is withheld');
            });
        }
    } finally {
        await browser.close();
    }
    console.log('Review editor browser: ' + checks + ' checks, ' + passed + ' passing cases, ' + failures.length + ' failed cases.');
    process.exitCode = failures.length ? 1 : 0;
})().catch(error => { console.error(error); process.exitCode = 1; });
