<template>
    <div class="fayno-dashboard">
        <div class="fayno-dashboard__intro">
            <h1>{{ card.translations.title }}</h1>
            <p>{{ card.translations.description }}</p>
        </div>

        <section class="fayno-dashboard__stats">
            <article v-for="item in statItems" :key="item.key" class="fayno-dashboard__stat">
                <span :class="['fayno-dashboard__stat-icon', 'is-' + item.tone]">{{ item.symbol }}</span>
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
                        <div class="fayno-dashboard__shift-top"><div><strong>{{ shift.camp }}</strong><small>{{ shift.name }} · {{ shift.booked }} {{ shift.capacity }} {{ card.translations.places }}</small></div><b>{{ shift.percentage }}%</b></div>
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
                                <td><strong>{{ booking.camper }}</strong></td>
                                <td><strong>{{ booking.camp }}</strong><small>{{ booking.shift }}</small></td>
                                <td>{{ booking.dates }}</td>
                                <td><span :class="['fayno-booking-status', 'is-' + booking.status]">{{ booking.statusLabel }}</span></td>
                                <td>{{ money(booking.amount) }}<small>{{ card.translations.paid }}: {{ money(booking.paid) }}</small></td>
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
                <article class="fayno-dashboard__banner"><span>FAYNO CAMP</span><strong>Детство, которое вдохновляет на большее</strong></article>
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
                { key: 'camps', label: this.card.translations.activeCamps, value: this.card.stats.camps, tone: 'green', symbol: '⌂' },
                { key: 'bookings', label: this.card.translations.bookings, value: this.card.stats.bookings, tone: 'blue', symbol: '▣' },
                { key: 'campers', label: this.card.translations.participants, value: this.card.stats.campers.toLocaleString('uk-UA'), tone: 'purple', symbol: '♧' },
                { key: 'revenue', label: this.card.translations.receivedPayments, value: this.money(this.card.stats.revenue), tone: 'orange', symbol: '₴' },
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
