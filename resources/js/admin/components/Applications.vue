<template>
    <div class="fayno-applications">
        <section class="fayno-applications__section">
            <div class="fayno-applications__heading">
                <div>
                    <h2>{{ card.translations.recentTitle }}</h2>
                    <p>{{ card.translations.recentDescription }}</p>
                </div>
                <span class="fayno-applications__count">{{ card.recentOrders.length }}</span>
            </div>

            <div v-if="card.recentOrders.length" class="fayno-applications__list">
                <article
                    v-for="order in card.recentOrders"
                    :key="order.id"
                    class="fayno-application"
                >
                    <div class="fayno-application__main">
                        <div class="fayno-application__title">
                            <strong>{{ order.number }}</strong>
                            <span :class="['fayno-status', `fayno-status--${order.status}`]">
                                {{ order.statusLabel }}
                            </span>
                        </div>
                        <p>{{ order.customer || card.translations.noCustomer }}</p>
                        <small>{{ [order.camp, order.shift].filter(Boolean).join(' · ') }}</small>
                    </div>
                    <time>{{ order.createdAt }}</time>
                </article>
            </div>
            <p v-else class="fayno-applications__empty">{{ card.translations.noApplications }}</p>
        </section>

        <section class="fayno-applications__section fayno-applications__section--unanswered">
            <div class="fayno-applications__heading">
                <div>
                    <h2>{{ card.translations.unansweredTitle }}</h2>
                    <p>{{ card.translations.unansweredDescription }}</p>
                </div>
                <span class="fayno-applications__count fayno-applications__count--warning">
                    {{ card.unansweredOrders.length }}
                </span>
            </div>

            <div v-if="card.unansweredOrders.length" class="fayno-applications__list">
                <article
                    v-for="order in card.unansweredOrders"
                    :key="order.id"
                    class="fayno-application"
                >
                    <div class="fayno-application__main">
                        <div class="fayno-application__title">
                            <strong>{{ order.number }}</strong>
                            <span class="fayno-status fayno-status--unanswered">{{ card.translations.noResponse }}</span>
                        </div>
                        <p>{{ order.customer || card.translations.noCustomer }}</p>
                        <small>{{ [order.camp, order.shift].filter(Boolean).join(' · ') }}</small>
                    </div>
                    <div class="fayno-application__waiting">
                        <time>{{ order.createdAt }}</time>
                        <strong>{{ order.waiting }}</strong>
                    </div>
                </article>
            </div>
            <p v-else class="fayno-applications__empty">{{ card.translations.noUnanswered }}</p>
        </section>
    </div>
</template>

<script>
export default {
    props: ['card'],
}
</script>
