import Applications from './components/Applications.vue';
import CampShiftOccupancy from './components/CampShiftOccupancy.vue';

Nova.booting((app) => {
    app.component('fayno-applications', Applications);
    app.component('fayno-camp-shift-occupancy', CampShiftOccupancy);
});
