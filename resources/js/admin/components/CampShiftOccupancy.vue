<template>
    <div class="fayno-occupancy">
        <div class="fayno-occupancy__heading">
            <div>
                <h2>{{ card.translations.title }}</h2>
                <p>{{ card.translations.description }}</p>
            </div>
            <span class="fayno-occupancy__total">
                {{ totalBooked }} / {{ totalCapacity }}
                <small>{{ card.translations.occupied }}</small>
            </span>
        </div>

        <div v-if="card.shifts.length" class="fayno-occupancy__grid">
            <article
                v-for="shift in card.shifts"
                :key="shift.id"
                class="fayno-occupancy__item"
            >
                <div class="fayno-occupancy__item-heading">
                    <div>
                        <strong>{{ shift.camp || card.translations.camp }}</strong>
                        <p>{{ shift.name }}</p>
                        <small v-if="shift.startsAt || shift.endsAt">
                            {{ shift.startsAt }} — {{ shift.endsAt }}
                        </small>
                    </div>
                    <b>{{ shift.percentage }}%</b>
                </div>

                <div class="fayno-occupancy__bar">
                    <span :style="{ width: shift.percentage + '%' }"></span>
                </div>

                <div class="fayno-occupancy__legend">
                    <span><i class="fayno-occupancy__dot fayno-occupancy__dot--booked"></i>{{ card.translations.booked }}: <b>{{ shift.booked }}</b></span>
                    <span><i class="fayno-occupancy__dot fayno-occupancy__dot--free"></i>{{ card.translations.available }}: <b>{{ shift.available }}</b></span>
                    <span>{{ card.translations.total }}: <b>{{ shift.capacity }}</b></span>
                </div>
            </article>
        </div>

        <p v-else class="fayno-occupancy__empty">{{ card.translations.empty }}</p>
    </div>
</template>

<script>
export default {
    props: ['card'],

    computed: {
        totalBooked() {
            return this.card.shifts.reduce((total, shift) => total + shift.booked, 0);
        },

        totalCapacity() {
            return this.card.shifts.reduce((total, shift) => total + shift.capacity, 0);
        },
    },
}
</script>
