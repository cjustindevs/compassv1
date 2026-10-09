// Matches the server-side x-ui-icon component; names refer to the shared sprite.
export function uiIcon(name, classes = '') {
    const glyph = /^[a-z0-9-]+$/.test(name) ? name : 'info';
    const url = document.querySelector('meta[name="compass-icon-sprite"]')?.content || '/images/compass-icons.svg';
    const escape = (value) => value.replace(/[&<>"']/g, (char) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
    return `<svg class="compass-icon ${escape(classes)}" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="${escape(url)}#${glyph}" /></svg>`;
}
