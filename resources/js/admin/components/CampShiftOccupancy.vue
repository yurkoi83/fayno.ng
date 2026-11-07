<template>
    <div class="fayno-occupancy">
        <div class="fayno-occupancy__heading">
            <div>
                <h2>Заполненность лагерей</h2>
                <p>Занятые и свободные места по активным сменам</p>
            </div>
            <span class="fayno-occupancy__total">
                {{ totalBooked }} / {{ totalCapacity }}
                <small>мест занято</small>
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
                        <strong>{{ shift.camp || 'Лагерь' }}</strong>
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
                    <span><i class="fayno-occupancy__dot fayno-occupancy__dot--booked"></i>Занято: <b>{{ shift.booked }}</b></span>
                    <span><i class="fayno-occupancy__dot fayno-occupancy__dot--free"></i>Свободно: <b>{{ shift.available }}</b></span>
                    <span>Всего: <b>{{ shift.capacity }}</b></span>
                </div>
            </article>
        </div>

        <p v-else class="fayno-occupancy__empty">Нет активных смен</p>
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
