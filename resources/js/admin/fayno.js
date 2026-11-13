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
