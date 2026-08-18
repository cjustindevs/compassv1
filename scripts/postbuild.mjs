import { copyFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const swSource = join(root, 'public', 'build', 'serviceworker.js');
const swTarget = join(root, 'public', 'sw.js');

if (!existsSync(swSource)) {
    console.error('[postbuild] Built service worker not found at public/build/serviceworker.js');
    process.exit(1);
}

copyFileSync(swSource, swTarget);
console.log('[postbuild] Service worker copied to public/sw.js (scope: /)');