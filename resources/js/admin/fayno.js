import '../../css/admin/fayno.css';
import Applications from './components/Applications.vue';
import CampShiftOccupancy from './components/CampShiftOccupancy.vue';
import DashboardOverview from './components/DashboardOverview.vue';

Nova.booting((app) => {
    app.component('fayno-applications', Applications);
    app.component('fayno-camp-shift-occupancy', CampShiftOccupancy);
    app.component('fayno-dashboard-overview', DashboardOverview);
});
