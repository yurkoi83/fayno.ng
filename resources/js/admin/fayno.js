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


// Add the Fayno Camp promotional tile to the order-detail sidebar.
// Nova renders its sidebar independently, so keep this safe across SPA navigation.
const syncFaynoOrderSidebar = () => {
    const orderDetail = document.querySelector('#nova [dusk="orders-detail-component"]');
    const sidebar = document.querySelector('#nova [dusk="content"] > div[class*="lg:absolute"][class*="w-60"]');

    if (!orderDetail || !sidebar || sidebar.querySelector('.fayno-sidebar-promo')) {
        return;
    }

    const promo = document.createElement('a');
    promo.className = 'fayno-sidebar-promo';
    promo.href = 'https://fayno.camp/';
    promo.target = '_blank';
    promo.rel = 'noopener';
    promo.innerHTML = '<span>Разом<br>до пригод!</span><i aria-hidden="true">→</i>';
    sidebar.appendChild(promo);
};

syncFaynoOrderSidebar();

const faynoSidebarObserver = new MutationObserver(syncFaynoOrderSidebar);
faynoSidebarObserver.observe(document.body, { childList: true, subtree: true });
