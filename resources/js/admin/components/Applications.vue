<template>
    <div class="fayno-applications">
        <section class="fayno-applications__section">
            <div class="fayno-applications__heading">
                <div>
                    <h2>Последние заявки</h2>
                    <p>Недавно поступившие заявки от родителей</p>
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
                        <p>{{ order.customer || 'Клиент не указан' }}</p>
                        <small>{{ [order.camp, order.shift].filter(Boolean).join(' · ') }}</small>
                    </div>
                    <time>{{ order.createdAt }}</time>
                </article>
            </div>
            <p v-else class="fayno-applications__empty">Заявок пока нет</p>
        </section>

        <section class="fayno-applications__section fayno-applications__section--unanswered">
            <div class="fayno-applications__heading">
                <div>
                    <h2>Заявки без ответа</h2>
                    <p>Требуют внимания администратора</p>
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
                            <span class="fayno-status fayno-status--unanswered">Без ответа</span>
                        </div>
                        <p>{{ order.customer || 'Клиент не указан' }}</p>
                        <small>{{ [order.camp, order.shift].filter(Boolean).join(' · ') }}</small>
                    </div>
                    <div class="fayno-application__waiting">
                        <time>{{ order.createdAt }}</time>
                        <strong>{{ order.waiting }}</strong>
                    </div>
                </article>
            </div>
            <p v-else class="fayno-applications__empty">Нет заявок, ожидающих ответа</p>
        </section>
    </div>
</template>

<script>
export default {
    props: ['card'],
}
</script>
