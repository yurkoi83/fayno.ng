import '../../css/admin/fayno.css';
import Applications from './components/Applications.vue';
import CampShiftOccupancy from './components/CampShiftOccupancy.vue';
import DashboardOverview from './components/DashboardOverview.vue';

Nova.booting((app) => {
    app.component('fayno-applications', Applications);
    app.component('fayno-camp-shift-occupancy', CampShiftOccupancy);
    app.component('fayno-dashboard-overview', DashboardOverview);
});

// The Nova header scrolls with the page, while the sidebar is fixed.
// Keep the 56px offset at the top and reclaim it after the page scrolls.
const syncSidebarOffset = () => {
    const sidebar = document.querySelector('#nova [class*="lg:top-[56px]"]');

    if (!sidebar) {
        return;
    }

    sidebar.classList.toggle('fayno-sidebar--scrolled', window.scrollY > 0);
};

window.addEventListener('scroll', syncSidebarOffset, { passive: true });
syncSidebarOffset();


// Bring Nova's native navigation closer to the Fayno reference:
// consistent line icons and the lavender active overview item.
const syncFaynoOrderNavigation = () => {
    const menu = document.querySelector('#nova [dusk="sidebar-menu"]');

    if (!menu) {
        return;
    }

    const icons = {
        'Огляд': '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'Табори': '<path d="m3 20 9-16 9 16H3Z"/><path d="m9 20 3-9 3 9"/>',
        'Бронювання': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/>',
        'Кемпери': '<path d="M3 7h12v11H3zM15 11h4l2 3v4h-6"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
        'Батьки': '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6m1 3a5 5 0 0 1 3 5v1"/>',
        'Платежі': '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'Сторінки': '<path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h7"/>',
        'Меню': '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'Налаштування': '<circle cx="12" cy="12" r="3"/><path d="m19 13 2 1-2 4-2-1-2 1v2h-5v-2l-2-1-2 1-2-4 2-1v-2l-2-1 2-4 2 1 2-1V4h5v2l2 1 2-1 2 4-2 1z"/>',
        'Users': '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/>',
        'Ролі': '<path d="M12 3 4 7v5c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7z"/><path d="m9 12 2 2 4-4"/>',
    };

    menu.querySelectorAll('a,button,[role="button"]').forEach((item) => {
        const label = (item.textContent || '').trim().replace(/\s+/g, ' ');
        const key = Object.keys(icons).find((name) => label === name || label.startsWith(name + ' '));

        if (!key || item.querySelector('.fayno-nav-icon')) {
            return;
        }

        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('stroke-width', '1.7');
        icon.setAttribute('stroke-linecap', 'round');
        icon.setAttribute('stroke-linejoin', 'round');
        icon.setAttribute('aria-hidden', 'true');
        icon.setAttribute('class', 'fayno-nav-icon');
        icon.innerHTML = icons[key];
        item.prepend(icon);
    });

    const overview = [...menu.querySelectorAll('a,button,[role="button"]')]
        .find((item) => (item.textContent || '').trim().startsWith('Огляд'));

    if (overview) {
        overview.classList.add('fayno-nav-reference-active');
    }
};

syncFaynoOrderNavigation();
const faynoOrderNavigationObserver = new MutationObserver(syncFaynoOrderNavigation);
faynoOrderNavigationObserver.observe(document.body, { childList: true, subtree: true });
