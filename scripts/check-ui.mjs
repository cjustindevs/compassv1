// Syntax/asset QA only: this does not replace browser or device visual testing.
// Capture fixture HTML with COMPASS_UI_CAPTURE=1 and UiStandardizationTest,
// then run: node --experimental-vm-modules scripts/check-ui.mjs
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import postcss from 'postcss';

let styles = 0;
let scripts = 0;
let screens = 0;
const errors = [];
for (const directory of ['public/css', 'resources/css']) {
    for (const file of fs.readdirSync(directory).filter((file) => file.endsWith('.css'))) {
        const filename = path.join(directory, file);
        try { postcss.parse(fs.readFileSync(filename, 'utf8'), {from: filename}); styles++; }
        catch (error) { errors.push(error.message); }
    }
}
const sprite = fs.readFileSync('public/images/compass-icons.svg', 'utf8');
const glyphs = new Set([...sprite.matchAll(/<symbol id="([^"]+)"/g)].map((match) => match[1]));
if (fs.existsSync('public/sw.js')) {
    const worker = fs.readFileSync('public/sw.js', 'utf8');
    for (const asset of ['/offline.html', '/css/compass-ui.css', '/css/landing-page.css', '/js/compass-ui.js', '/images/compass-icons.svg']) {
        if (!fs.existsSync(path.join('public', asset.slice(1)))) errors.push(`Missing public asset ${asset}.`);
        if (!worker.includes(asset)) errors.push(`Built service worker is missing ${asset}; run npm run build.`);
    }
}
if (fs.existsSync('.ui-preview')) {
    for (const file of fs.readdirSync('.ui-preview').filter((file) => file.endsWith('.html'))) {
        const html = fs.readFileSync(path.join('.ui-preview', file), 'utf8');
        screens++;
        for (const match of html.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)) {
            try { postcss.parse(match[1], {from: file}); styles++; }
            catch (error) { errors.push(error.message); }
        }
        for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
            if (!match[2].trim() || /type="(?:application\/json|text\/html)"/.test(match[1])) continue;
            try {
                if (/type="module"/.test(match[1])) new vm.SourceTextModule(match[2], {identifier: file});
                else new vm.Script(match[2], {filename: file});
                scripts++;
            } catch (error) { errors.push(`${file}: ${error.message}`); }
        }
        for (const match of html.matchAll(/compass-icons\.svg[^"<>]*#([a-z0-9-]+)/g)) {
            if (!glyphs.has(match[1])) errors.push(`${file}: missing glyph ${match[1]}`);
        }
    }
}
if (errors.length) {
    console.error(errors.join('\n'));
    process.exitCode = 1;
} else {
    console.log(`Checked ${styles} stylesheets, ${scripts} inline scripts, ${glyphs.size} glyphs and ${screens} rendered fixture screens.`);
    if (!screens) console.log('No fixture HTML captured; render/inline checks are pending.');
}
