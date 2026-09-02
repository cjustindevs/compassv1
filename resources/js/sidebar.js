// ════════════════════════════════════════════════════════════════
//  COMPASS — Unified Sidebar Controller
//  Single controller for every role sidebar. Handles:
//    • desktop collapse (persisted via localStorage)
//    • mobile drawer (hamburger / overlay / swipe-free)
//    • close-on-navigate on mobile
//  Loads its own stylesheet so the sidebar is consistent everywhere.
// ════════════════════════════════════════════════════════════════
import '../css/sidebar.css';

const STORAGE_KEY = 'sidebarCollapsed';
const MOBILE_BREAKPOINT = 768;

class SidebarController {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.toggle = document.getElementById('sidebarToggle');
        this.toggleIcon = document.getElementById('toggleIcon');
        this.overlay = document.getElementById('sidebarOverlay');
        this.hamburger = document.getElementById('hamburgerBtn');

        if (!this.sidebar) return;

        this.applyCollapsed();
        this.bind();
    }

    isMobile() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    // ── Desktop collapse ──
    applyCollapsed() {
        const collapsed = localStorage.getItem(STORAGE_KEY) === 'true';
        this.sidebar.classList.toggle('collapsed', collapsed);
        this.updateToggleIcon(collapsed);
    }

    updateToggleIcon(collapsed) {
        if (!this.toggleIcon) return;
        this.toggleIcon.classList.toggle('fa-chevron-left', !collapsed);
        this.toggleIcon.classList.toggle('fa-chevron-right', collapsed);
    }

    toggleCollapse() {
        if (this.isMobile()) return; // collapse is a desktop-only affordance
        const collapsed = this.sidebar.classList.toggle('collapsed');
        localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
        this.updateToggleIcon(collapsed);
    }

    // ── Mobile drawer ──
    openMobile() {
        this.sidebar.classList.add('open');
        if (this.overlay) this.overlay.classList.add('active');
    }

    closeMobile() {
        this.sidebar.classList.remove('open');
        if (this.overlay) this.overlay.classList.remove('active');
    }

    toggleMobile() {
        if (this.sidebar.classList.contains('open')) this.closeMobile();
        else this.openMobile();
    }

    bind() {
        if (this.toggle) {
            this.toggle.addEventListener('click', () => this.toggleCollapse());
        }

        // In collapsed desktop state the arrow is hidden, so the logo icon
        // becomes the expand trigger. Clicking it expands (and does not navigate).
        this.brand = this.sidebar.querySelector('.sidebar-brand');
        if (this.brand) {
            this.brand.addEventListener('click', (e) => {
                if (!this.sidebar.classList.contains('collapsed')) return;
                e.preventDefault();
                this.toggleCollapse();
            });
        }

        if (this.hamburger) {
            this.hamburger.addEventListener('click', () => this.toggleMobile());
        }

        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.closeMobile());
        }

        // Close the drawer after tapping a navigation link on mobile.
        this.sidebar.querySelectorAll('.nav-item, .sidebar-user, .logout-btn').forEach((item) => {
            item.addEventListener('click', () => {
                if (this.isMobile()) this.closeMobile();
            });
        });

        // Keep the desktop layout correct across viewport changes.
        window.addEventListener('resize', () => {
            if (!this.isMobile()) this.closeMobile();
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.compassSidebar = new SidebarController();
});
