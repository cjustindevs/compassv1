// Local visual QA harness (dev-only). Logs in as each local demo account, screenshots
// role pages at several widths and reports layout problems:
//   - interactive elements covered by fixed/sticky elements
//   - horizontal page overflow
//   - fixed/sticky elements overlapping each other
//
// Usage:  node scripts/ui-screenshots.mjs [--roles=admin,helper] [--widths=1440,390]
//         [--out=.ui-preview/before] [--base=http://127.0.0.1:8000] [--pages=dashboard,reports]
// Needs the local app running and the demo accounts from TestUsersSeeder. Never point it at
// production; it logs in and out with demo credentials only.
import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const arg = (name, fallback) => {
    const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
    return hit ? hit.slice(name.length + 3) : fallback;
};
const base = arg('base', 'http://127.0.0.1:8000');
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base)) {
    console.error('Refusing to run against a non-local base URL.');
    process.exit(2);
}
const outDir = arg('out', '.ui-preview/current');
const widths = arg('widths', '1440,1024,768,390').split(',').map(Number);
const pageFilter = arg('pages', '').split(',').filter(Boolean);

const accounts = {
    admin: ['admin@compass.local', 'Admin@123'],
    adviser: ['maria.santos@compass.local', 'Adviser@123'],
    helper: ['rina@compass.local', 'Helper@123'],
    seeker: ['SilentWillow52', 'Seeker@123'],
    moderator: ['moderator@compass.local', 'Moderator@123'],
    professional: ['anna.cruz@compass.local', 'Professional@123'],
};

const pages = {
    admin: ['admin/dashboard', 'admin/users', 'admin/roles-permissions', 'admin/audit-logs', 'admin/reports', 'admin/system-health', 'admin/backup-restore', 'admin/settings'],
    adviser: ['adviser/dashboard', 'adviser/screenings', 'adviser/evaluations', 'adviser/helpers', 'adviser/referrals', 'adviser/emergencies', 'adviser/calendar', 'adviser/schedule', 'adviser/reports', 'adviser/resources', 'adviser/training', 'adviser/transcripts', 'adviser/notifications', 'adviser/settings', 'queue-performance', 'concerns/manage'],
    helper: ['helper/dashboard', 'helper/readiness', 'helper/cases', 'helper/sessions', 'helper/chat', 'helper/calendar', 'helper/competency', 'helper/reports', 'helper/resources', 'helper/self-help', 'helper/notifications', 'helper/profile', 'helper/settings'],
    seeker: ['seeker/dashboard', 'request/screening', 'request/preferences', 'request/matching', 'seeker/requests', 'seeker/referrals', 'session/history', 'selfhelp', 'emergency', 'notifications', 'settings', 'seeker/privacy'],
    moderator: ['moderator/dashboard', 'moderator/queue', 'moderator/sessions', 'moderator/emergency', 'moderator/schedules', 'moderator/manage', 'moderator/reports', 'moderator/referrals/unassigned', 'moderator/reconnections', 'moderator/notifications', 'moderator/settings', 'queue-performance'],
    professional: ['professional/dashboard', 'professional/referrals', 'professional/cases', 'professional/reports', 'professional/profile', 'professional/settings'],
};
const roles = arg('roles', Object.keys(accounts).join(',')).split(',');

// Runs in the page: finds layout problems at the current viewport.
const audit = () => {
    const visible = (el) => {
        const s = getComputedStyle(el);
        const r = el.getBoundingClientRect();
        return s.display !== 'none' && s.visibility !== 'hidden' && +s.opacity > 0 && r.width > 1 && r.height > 1;
    };
    const label = (el) => {
        const id = el.id ? `#${el.id}` : '';
        const cls = typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
        const text = (el.innerText || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 40);
        return `${el.tagName.toLowerCase()}${id}${cls}${text ? ` "${text}"` : ''}`;
    };
    const intersects = (a, b) => a.left < b.right - 2 && b.left < a.right - 2 && a.top < b.bottom - 2 && b.top < a.bottom - 2;
    const layers = [...document.querySelectorAll('body *')].filter((el) => {
        const p = getComputedStyle(el).position;
        return (p === 'fixed' || p === 'sticky') && visible(el) && !el.closest('dialog:not([open])');
    }).filter((el, _, all) => !all.some((o) => o !== el && o.contains(el)));
    const problems = [];
    // An open modal is intentional; report it once instead of every control beneath it.
    const modal = [...document.querySelectorAll('dialog[open], [aria-modal="true"]:not([hidden])')].find(visible);
    if (modal) return [`modal-open: ${label(modal)}`];
    const sidebar = (el) => el.matches('aside, nav, .sidebar, [class*="sidebar"]') || el.closest('aside, .sidebar');
    for (const [i, a] of layers.entries()) {
        for (const b of layers.slice(i + 1)) {
            if (sidebar(a) && sidebar(b)) continue;
            if (intersects(a.getBoundingClientRect(), b.getBoundingClientRect())) {
                problems.push(`fixed-overlap: ${label(a)} <> ${label(b)}`);
            }
        }
    }
    const controls = [...document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, [role=button]')].filter(visible);
    for (const el of controls) {
        const r = el.getBoundingClientRect();
        // Skip controls that are off-screen (e.g. a closed mobile drawer translated away).
        if (r.bottom < 0 || r.top > innerHeight || r.right <= 0 || r.left >= innerWidth) continue;
        const cx = Math.min(Math.max(r.left + r.width / 2, 0), innerWidth - 1);
        const cy = Math.min(Math.max(r.top + r.height / 2, 0), innerHeight - 1);
        const top = document.elementFromPoint(cx, cy);
        if (!top || el.contains(top) || top.contains(el)) continue;
        const cover = layers.find((l) => l.contains(top));
        if (cover && !cover.contains(el)) problems.push(`covered: ${label(el)} under ${label(cover)}`);
    }
    if (document.documentElement.scrollWidth > innerWidth + 1) {
        const wide = [...document.querySelectorAll('body *')].filter((el) => visible(el) && el.getBoundingClientRect().right > innerWidth + 1 && !el.closest('[style*="overflow"], .table-container, .overflow-x-auto, [class*="table-wrap"], [class*="scroll"]'))
            .slice(0, 3).map(label);
        if (!wide.length) wide.push(...[...document.querySelectorAll('body *')].filter((el) => visible(el) && el.getBoundingClientRect().right > innerWidth + 1).sort((a, b) => b.getBoundingClientRect().right - a.getBoundingClientRect().right).slice(0, 3).map((el) => label(el) + ' right=' + Math.round(el.getBoundingClientRect().right)));
        problems.push(`h-overflow: page ${document.documentElement.scrollWidth}px > ${innerWidth}px ${wide.length ? '(' + wide.join(', ') + ')' : ''}`);
    }
    // Desktop only: content that uses well under the space beside the sidebar reads as "not compact".
    if (innerWidth >= 1600) {
        const rail = document.querySelector('aside#sidebar, .sidebar, .admin-sidebar');
        const railRight = rail && visible(rail) ? rail.getBoundingClientRect().right : 0;
        const available = innerWidth - railRight - 48;
        const host = document.querySelector('main, .main-content, .workspace-content');
        if (host) {
            const blocks = [...host.querySelectorAll(':scope > *, :scope > * > *')].filter((el) => visible(el) && el.getBoundingClientRect().height > 40);
            const widest = Math.max(0, ...blocks.map((el) => el.getBoundingClientRect().width));
            if (widest && widest < available * 0.8) problems.push(`narrow: content ${Math.round(widest)}px of ${Math.round(available)}px available`);
        }
    }
    return [...new Set(problems)].slice(0, 25);
};

const browser = await chromium.launch();
const report = {};
for (const role of roles) {
    const [login, password] = accounts[role] ?? [];
    if (!login) continue;
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name="email"]', login);
    await page.fill('input[name="password"]', password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('button[type="submit"]')]);
    if (/\/login$|\/$/.test(new URL(page.url()).pathname) && !page.url().includes('dashboard')) {
        console.error(`${role}: login failed (still at ${page.url()}). An earlier session may still be live; wait 5 minutes.`);
        await context.close();
        continue;
    }
    report[role] = {};
    for (const route of pages[role].filter((p) => !pageFilter.length || pageFilter.some((f) => p.includes(f)))) {
        for (const width of widths) {
            await page.setViewportSize({ width, height: width < 600 ? 844 : 900 });
            let status = 0;
            try {
                // COMPASS polls notifications every few seconds, so "networkidle" never settles.
                const res = await page.goto(`${base}/${route}`, { waitUntil: 'load', timeout: 30000 });
                status = res?.status() ?? 0;
            } catch (e) {
                status = -1;
            }
            await page.waitForTimeout(900);
            // Charts lazy-load Chart.js; wait until every card has drawn or reported failure.
            await page.waitForFunction(() => ![...document.querySelectorAll('[data-chart-state]')].some((s) => !s.hidden && s.textContent.startsWith('Loading')), null, { timeout: 6000 }).catch(() => {});
            const finalPath = new URL(page.url()).pathname.replace(/^\//, '') || 'home';
            const file = path.join(outDir, role, `${route.replace(/\//g, '_')}-${width}.png`);
            await mkdir(path.dirname(file), { recursive: true });
            await page.screenshot({ path: file, fullPage: true });
            const problems = await page.evaluate(audit).catch(() => ['audit failed']);
            report[role][`${route}@${width}`] = { status, finalPath, problems };
            const flag = problems.length ? `  ${problems.length} issue(s)` : '';
            console.log(`${role} ${route} @${width}: ${status}${finalPath !== route ? ` -> /${finalPath}` : ''}${flag}`);
        }
    }
    // Log out so the single-device login rule does not block the next run.
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${base}/${pages[role][0]}`);
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
        if (token) await fetch('/logout', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'text/html' }, credentials: 'same-origin' });
    }).catch(() => {});
    await context.close();
}
await browser.close();
await mkdir(outDir, { recursive: true });
await writeFile(path.join(outDir, 'report.json'), JSON.stringify(report, null, 2));
const total = Object.values(report).flatMap((r) => Object.values(r)).reduce((n, v) => n + v.problems.length, 0);
console.log(`\nDone. ${total} issue(s). Report: ${path.join(outDir, 'report.json')}`);
