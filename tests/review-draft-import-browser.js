'use strict';

// Exercise the shipped click handlers and real browser FileReader. The REST
// response is a transport fixture; parser/storage contracts are tested in PHP.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');
const executablePath = process.env.LUNARA_BROWSER_EXECUTABLE || [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'
].find(fs.existsSync);
const scriptPath = path.join(__dirname, '../assets/js/lunara-review-draft-import-admin.js');
const markup = '<form id="post"><input name="post_title" value="Saved review">' +
    '<section data-lunara-review-import data-review-id="10">' +
    '<input type="file" data-lunara-review-import-file>' +
    '<textarea data-lunara-review-import-html></textarea>' +
    '<button type="button" data-lunara-review-import-preview>Preview</button>' +
    '<button type="button" data-lunara-review-import-apply disabled>Apply</button>' +
    '<p data-lunara-review-import-status></p><p data-lunara-review-import-alert hidden></p>' +
    '</section></form>';
const selectors = {
    file: '[data-lunara-review-import-file]',
    html: '[data-lunara-review-import-html]',
    preview: '[data-lunara-review-import-preview]',
    apply: '[data-lunara-review-import-apply]',
    alert: '[data-lunara-review-import-alert]'
};
const phrase = 'It’s “Amélie” — café, naïve… 🎬 🧑🏽‍🎨 é ❤️ 🇫🇷';
const utf8 = '<p>' + phrase + '</p>';
const legacyBody = Buffer.from('<p>It\x92s \x93Am\xe9lie\x94 \x97 caf\xe9, na\xefve\x85</p>', 'latin1');
const legacyExpected = '<p>It’s “Amélie” — café, naïve…</p>';
const legacyWith = header => Buffer.concat([Buffer.from(header), legacyBody]);
const unicodeWith = header => Buffer.from(header + utf8);
const utf16be = Buffer.from(utf8, 'utf16le').swap16();
const cases = [
    ['UTF-8 punctuation, accents, emoji and combining sequences', Buffer.from(utf8), utf8],
    ['UTF-8 BOM', Buffer.concat([Buffer.from([0xef, 0xbb, 0xbf]), Buffer.from(utf8)]), utf8],
    ['Windows-1252 declared', legacyWith('<meta charset="windows-1252">'), '<meta charset="windows-1252">' + legacyExpected],
    ['Windows-1252 without a declaration', legacyBody, legacyExpected],
    ['Legacy http-equiv declaration', legacyWith('<meta content="text/html; charset=WINDOWS-1252" http-equiv="Content-Type">'), '<meta content="text/html; charset=WINDOWS-1252" http-equiv="Content-Type">' + legacyExpected],
    ['Data attributes do not declare encoding', legacyWith('<meta name="review-settings" data-charset="shift_jis">'), '<meta name="review-settings" data-charset="shift_jis">' + legacyExpected],
    ['Commented declarations do not select encoding', legacyWith('<!-- Export note: <meta charset="shift_jis"> -->'), '<!-- Export note: <meta charset="shift_jis"> -->' + legacyExpected],
    ['Quoted attribute markup does not select encoding', legacyWith('<div data-note=\'<meta charset="shift_jis">\'>Review note</div>'), '<div data-note=\'<meta charset="shift_jis">\'>Review note</div>' + legacyExpected],
    ['Description content does not declare encoding', legacyWith('<meta name="description" content="A charset=shift_jis example">'), '<meta name="description" content="A charset=shift_jis example">' + legacyExpected],
    ['Unrelated http-equiv does not declare encoding', legacyWith('<meta http-equiv="refresh" content="charset=shift_jis">'), '<meta http-equiv="refresh" content="charset=shift_jis">' + legacyExpected],
    ['First duplicate charset attribute wins', legacyWith('<meta charset="windows-1252" charset="shift_jis">'), '<meta charset="windows-1252" charset="shift_jis">' + legacyExpected],
    ['First actual charset declaration wins', legacyWith('<meta charset="windows-1252"><meta charset="shift_jis">'), '<meta charset="windows-1252"><meta charset="shift_jis">' + legacyExpected],
    ['Charset attribute entities are decoded', legacyWith('<meta charset="windows&#45;1252">'), '<meta charset="windows&#45;1252">' + legacyExpected],
    ['ISO-8859-1 HTML alias keeps Windows punctuation', legacyWith('<meta charset=iso-8859-1>'), '<meta charset=iso-8859-1>' + legacyExpected],
    ['Unknown legacy label falls back safely', legacyWith('<meta charset=x-obsolete-export>'), '<meta charset=x-obsolete-export>' + legacyExpected],
    ['Stale UTF-8 declaration on Windows bytes', legacyWith('<meta charset=utf-8>'), '<meta charset=utf-8>' + legacyExpected],
    ['UTF-8 with stale ANSI metadata', unicodeWith('<meta charset="windows-1252">'), '<meta charset="windows-1252">' + utf8],
    ['UTF-8 with unknown metadata', unicodeWith('<meta charset=x-obsolete-export>'), '<meta charset=x-obsolete-export>' + utf8],
    ['UTF-8 BOM overrides stale metadata', Buffer.concat([Buffer.from([0xef, 0xbb, 0xbf]), unicodeWith('<meta charset="windows-1252">')]), '<meta charset="windows-1252">' + utf8],
    ['Mixed Windows punctuation and intact UTF-8', Buffer.concat([Buffer.from('<meta charset="windows-1252"><p>\x93', 'latin1'), Buffer.from(phrase), Buffer.from('\x94</p>', 'latin1')]), '<meta charset="windows-1252"><p>“' + phrase + '”</p>'],
    ['Mixed input keeps an internal Unicode no-break space', Buffer.concat([Buffer.from('<p>\x93', 'latin1'), Buffer.from('\uFEFF' + phrase), Buffer.from('\x94</p>', 'latin1')]), '<p>“\uFEFF' + phrase + '”</p>'],
    ['Declared Shift-JIS', Buffer.concat([Buffer.from('<meta charset="shift_jis"><p>'), Buffer.from([0x93, 0xfa, 0x96, 0x7b, 0x8c, 0xea]), Buffer.from('</p>')]), '<meta charset="shift_jis"><p>日本語</p>'],
    ['UTF-16LE BOM', Buffer.concat([Buffer.from([0xff, 0xfe]), Buffer.from(utf8, 'utf16le')]), utf8],
    ['UTF-16BE BOM', Buffer.concat([Buffer.from([0xfe, 0xff]), utf16be]), utf8]
];
for (const tag of ['script', 'style', 'textarea', 'title']) {
    const header = '<' + tag + '><meta charset="shift_jis"></' + tag + '>';
    cases.push(['Markup inside ' + tag + ' text does not declare encoding', legacyWith(header), header + legacyExpected]);
}

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    page.setDefaultTimeout(5000);
    let checks = 0;
    let completed = 0;
    const failures = [];
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    const equal = (actual, expected, message) => { assert.deepEqual(actual, expected, message); checks++; };

    async function reset(config = {}) {
        await page.goto('about:blank');
        await page.setContent(markup);
        pageErrors.length = 0;
        await page.evaluate(settings => {
            window.LunaraReviewDraftImport = Object.assign({ reviewId: 10, restBase: '/review-import/', nonce: 'fixture' }, settings);
            window.importRequests = [];
            window.fetch = async (url, options) => {
                window.importRequests.push({ url, payload: JSON.parse(options.body), credentials: options.credentials, headers: options.headers });
                return { ok: true, json: async () => ({ valid: true, existing: { content: false }, alreadyImported: url.endsWith('/apply') }) };
            };
        }, config);
        await page.addScriptTag({ path: scriptPath });
    }

    async function run(name, callback) {
        try {
            await callback();
            equal(pageErrors, [], 'No unhandled browser errors');
            completed++;
            console.log('PASS: ' + name);
        } catch (error) {
            failures.push(name);
            console.error('FAIL: ' + name + ': ' + error.message);
        }
    }

    async function upload(buffer, name = 'Amélie — review.htm') {
        await page.locator(selectors.file).setInputFiles({ name, mimeType: 'application/octet-stream', buffer });
    }

    async function previewAndApply(expected) {
        await page.locator(selectors.preview).click();
        await page.waitForFunction(() => window.importRequests.length || !document.querySelector('[data-lunara-review-import-alert]').hidden);
        const requests = await page.evaluate(() => window.importRequests);
        equal(requests.length, 1, 'Preview sends one request');
        equal(requests[0].url, '/review-import/preview', 'Preview endpoint');
        equal(requests[0].payload, Object.assign({ review_id: 10 }, expected), 'Preview preserves the exact source');
        equal(requests[0].credentials, 'same-origin', 'Authenticated request stays same-origin');
        equal(requests[0].headers['X-WP-Nonce'], 'fixture', 'Nonce remains attached');
        await page.locator(selectors.apply).click();
        await page.waitForFunction(() => window.importRequests.length === 2);
        const applied = await page.evaluate(() => window.importRequests[1]);
        equal(applied.url, '/review-import/apply', 'Apply endpoint');
        equal(applied.payload, requests[0].payload, 'Apply retains the exact preview source');
    }

    async function expectBlocked(message) {
        await page.locator(selectors.preview).click();
        await page.waitForFunction(() => !document.querySelector('[data-lunara-review-import-alert]').hidden);
        const alert = await page.locator(selectors.alert).textContent();
        assert.match(alert, message); checks++;
        equal(await page.evaluate(() => window.importRequests), [], 'Failure must not send partial input');
        equal(await page.locator(selectors.apply).isDisabled(), true, 'Apply stays disabled');
        equal(await page.locator(selectors.preview).isEnabled(), true, 'Preview is not stuck busy');
        equal(await page.locator(selectors.file).isEnabled(), true, 'A corrected file can be selected');
    }

    try {
        for (const [name, buffer, expected] of cases) {
            await run(name, async () => {
                await reset();
                await upload(buffer);
                await previewAndApply({ html: expected, source_format: 'html', source_name: 'Amélie — review.htm' });
            });
        }

        for (const [name, buffer] of [
            ['Malformed UTF-8 with BOM', Buffer.from([0xef, 0xbb, 0xbf, 0xc3])],
            ['Odd-byte UTF-16LE with BOM', Buffer.from([0xff, 0xfe, 0x61])],
            ['Unpaired UTF-16BE surrogate', Buffer.from([0xfe, 0xff, 0xd8, 0x00])],
            ['Malformed declared Shift-JIS', Buffer.concat([Buffer.from('<meta charset=shift_jis>'), Buffer.from([0x82])])]
        ]) {
            await run(name, async () => {
                await reset();
                await upload(buffer);
                await expectBlocked(/text encoding could not be read/);
            });
        }

        for (const event of ['error', 'abort']) {
            await run('FileReader ' + event + ' is recoverable', async () => {
                await reset();
                await page.evaluate(type => {
                    window.FileReader = class extends EventTarget {
                        readAsArrayBuffer() { queueMicrotask(() => this.dispatchEvent(new Event(type))); }
                    };
                }, event);
                await upload(Buffer.from(utf8));
                await expectBlocked(event === 'error' ? /could not be read/ : /was cancelled/);
            });
        }

        for (const format of ['docx', 'zip']) {
            await run(format.toUpperCase() + ' bytes bypass text decoding', async () => {
                await reset();
                const buffer = Buffer.from([0x50, 0x4b, 0x03, 0x04, 0xff, 0xfe, 0x00, 0x92, 0xf0]);
                await page.evaluate(() => { window.TextDecoder = class { constructor() { throw new Error('Binary payload must not be decoded'); } }; });
                await upload(buffer, 'review.' + format);
                await previewAndApply({ document: buffer.toString('base64'), source_format: format, source_name: 'review.' + format });
            });
        }

        await run('Pasted Unicode HTML bypasses byte decoding', async () => {
            await reset();
            await page.evaluate(() => { window.TextDecoder = class { constructor() { throw new Error('Already-decoded text must not be decoded'); } }; });
            await page.locator(selectors.html).fill(utf8);
            await previewAndApply({ html: utf8, source_format: 'html', source_name: 'pasted-html' });
        });

        await run('Rich clipboard preserves Unicode and existing sanitization', async () => {
            await reset();
            await page.locator(selectors.html).evaluate((textarea, html) => {
                const clipboard = new DataTransfer();
                clipboard.setData('text/html', html + '<script>unsafe()</script><style>body{color:red}</style><iframe></iframe>');
                textarea.dispatchEvent(new ClipboardEvent('paste', { clipboardData: clipboard, bubbles: true, cancelable: true }));
            }, utf8);
            equal(await page.locator(selectors.html).inputValue(), utf8, 'Clipboard retains text without active wrappers');
            await previewAndApply({ html: utf8, source_format: 'html', source_name: 'pasted-html' });
        });

        await run('Decoded byte size still respects the import limit', async () => {
            await reset({ maxBytes: 32 });
            await upload(Buffer.concat([Buffer.from('<p>'), Buffer.alloc(20, 0x92), Buffer.from('</p>')]));
            await expectBlocked(/import limit/);
        });

        await run('Unsaved editor content still blocks imports', async () => {
            await reset();
            await upload(Buffer.from(utf8));
            await page.locator('[name="post_title"]').fill('Unsaved title');
            await expectBlocked(/Save the Review draft/);
        });
    } finally {
        await browser.close();
    }
    console.log('Review import browser: ' + checks + ' checks, ' + completed + ' passing cases, ' + failures.length + ' failed cases.');
    process.exitCode = failures.length ? 1 : 0;
})().catch(error => { console.error(error); process.exitCode = 1; });
