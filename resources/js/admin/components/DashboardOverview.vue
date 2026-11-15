<template>
    <div class="fayno-dashboard">
        <div class="fayno-dashboard__intro">
            <h1>{{ card.translations.title }}</h1>
            <p>{{ card.translations.description }}</p>
        </div>

        <section class="fayno-dashboard__stats">
            <article v-for="item in statItems" :key="item.key" class="fayno-dashboard__stat">
                <span :class="['fayno-dashboard__stat-icon', 'is-' + item.tone]" aria-hidden="true">
                    <svg v-if="item.key === 'camps'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 19.5 12 5l8.5 14.5h-17Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 19.5v-5h5v5M12 5V2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <svg v-else-if="item.key === 'bookings'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3.5" y="5" width="17" height="16" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M7.5 3v4M16.5 3v4M3.5 9.5h17M8 13h3M8 16.5h3M14 13h2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <svg v-else-if="item.key === 'campers'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19h-11Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M16 5.5a3 3 0 0 1 0 5.8M17 13.2a4.7 4.7 0 0 1 3.5 4.6V19h-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5.5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M3 9.5h18M7 15h3M16 14.5h1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16.5 3.5 19 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <div><span class="fayno-dashboard__stat-label">{{ item.label }}</span><strong>{{ item.value }}</strong></div>
            </article>
        </section>

        <section class="fayno-dashboard__middle">
            <article class="fayno-panel">
                <div class="fayno-panel__heading"><h2>{{ card.translations.bookingDynamics }}</h2><span>{{ card.translations.lastMonths }}</span></div>
                <svg class="fayno-chart" viewBox="0 0 700 220" preserveAspectRatio="none">
                    <line v-for="n in 5" :key="n" x1="20" :y1="n * 38" x2="690" :y2="n * 38" class="fayno-chart__grid"/>
                    <polyline :points="chartPoints" class="fayno-chart__line"/>
                    <circle v-for="(point, i) in chartCoordinates" :key="i" :cx="point.x" :cy="point.y" r="4" class="fayno-chart__point"/>
                </svg>
                <div class="fayno-chart__labels"><span v-for="(item, i) in card.chart" :key="i">{{ item.label }}</span></div>
            </article>

            <article class="fayno-panel">
                <div class="fayno-panel__heading"><h2>{{ card.translations.occupancy }}</h2><span>{{ card.translations.activeShifts }}</span></div>
                <div v-if="card.shifts.length">
                    <div v-for="shift in card.shifts" :key="shift.id" class="fayno-dashboard__shift">
                        <div class="fayno-dashboard__shift-top"><div><strong>{{ shift.camp }}</strong><small>{{ shift.name }} · {{ shift.booked }} {{ card.translations.of }} {{ shift.capacity }} {{ card.translations.places }}</small></div><b>{{ shift.percentage }}%</b></div>
                        <div class="fayno-progress"><span :style="{ width: shift.percentage + '%' }"></span></div>
                    </div>
                </div>
                <p v-else class="fayno-dashboard__empty">{{ card.translations.noActiveShifts }}</p>
            </article>
        </section>

        <section class="fayno-dashboard__bottom">
            <article class="fayno-panel">
                <div class="fayno-panel__heading"><h2>{{ card.translations.latestBookings }}</h2></div>
                <div class="fayno-table-wrap">
                    <table class="fayno-dashboard__table">
                        <thead><tr><th>{{ card.translations.camper }}</th><th>{{ card.translations.campShift }}</th><th>{{ card.translations.dates }}</th><th>{{ card.translations.status }}</th><th>{{ card.translations.amount }}</th></tr></thead>
                        <tbody>
                            <tr v-for="booking in card.bookingsList" :key="booking.id">
                                <td class="fayno-dashboard__cell-camper"><strong>{{ booking.camper }}</strong></td>
                                <td class="fayno-dashboard__cell-program"><strong>{{ booking.camp }}</strong><small>{{ booking.shift }}</small></td>
                                <td class="fayno-dashboard__cell-dates">{{ booking.dates }}</td>
                                <td class="fayno-dashboard__cell-status"><span :class="['fayno-booking-status', 'is-' + booking.status]">{{ booking.statusLabel }}</span></td>
                                <td class="fayno-dashboard__cell-amount"><strong>{{ money(booking.amount) }}</strong><small>{{ card.translations.paid }}: {{ money(booking.paid) }}</small></td>
                            </tr>
                            <tr v-if="!card.bookingsList.length"><td colspan="5" class="fayno-dashboard__empty">{{ card.translations.noBookings }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </article>
            <div class="fayno-dashboard__aside">
                <article class="fayno-panel">
                    <div class="fayno-panel__heading"><h2>{{ card.translations.needsAttention }}</h2></div>
                    <div class="fayno-dashboard__alert"><b class="is-red">{{ card.attention.unanswered }}</b><div><strong>{{ card.attention.unanswered }} {{ card.translations.unanswered }}</strong><small>{{ card.translations.contactParents }}</small></div></div>
                    <div class="fayno-dashboard__alert"><b class="is-orange">{{ card.attention.pendingPayments }}</b><div><strong>{{ card.attention.pendingPayments }} {{ card.translations.pendingPayments }}</strong><small>{{ card.translations.checkPayments }}</small></div></div>
                </article>
                <article class="fayno-dashboard__banner"><span>FAYNO CAMP</span><strong>{{ card.translations.tagline }}</strong></article>
            </div>
        </section>
    </div>
</template>

<script>
export default {
    props: ['card'],
    computed: {
        statItems() {
            return [
                { key: 'camps', label: this.card.translations.activeCamps, value: this.card.stats.camps, tone: 'green' },
                { key: 'bookings', label: this.card.translations.bookings, value: this.card.stats.bookings, tone: 'blue' },
                { key: 'campers', label: this.card.translations.participants, value: this.card.stats.campers.toLocaleString('uk-UA'), tone: 'purple' },
                { key: 'revenue', label: this.card.translations.receivedPayments, value: this.money(this.card.stats.revenue), tone: 'orange' },
            ];
        },
        chartCoordinates() {
            const values = this.card.chart.map(item => item.value);
            const max = Math.max(1, ...values);
            return values.map((value, index) => ({
                x: 20 + index * (670 / Math.max(1, values.length - 1)),
                y: 190 - (value / max) * 160,
            }));
        },
        chartPoints() {
            return this.chartCoordinates.map(point => point.x + ',' + point.y).join(' ');
        },
    },
    methods: {
        money(value) {
            return new Intl.NumberFormat('uk-UA', { style: 'currency', currency: 'UAH', maximumFractionDigits: 0 }).format(value || 0);
        },
    },
}
</script>
