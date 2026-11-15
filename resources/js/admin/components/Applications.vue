<template>
    <div class="fayno-applications">
        <section class="fayno-applications__section">
            <div class="fayno-applications__heading">
                <div class="fayno-applications__heading-copy">
                    <span class="fayno-applications__eyebrow">CRM · INBOX</span>
                    <h2>{{ card.translations.recentTitle }}</h2>
                    <p>{{ card.translations.recentDescription }}</p>
                </div>
                <span class="fayno-applications__count">{{ card.recentOrders.length }}</span>
            </div>

            <div v-if="card.recentOrders.length" class="fayno-applications__list">
                <a
                    v-for="order in card.recentOrders"
                    :key="order.id"
                    :href="`/nova/resources/orders/${order.id}`"
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
                    <div class="fayno-application__meta">
                        <time>{{ order.createdAt }}</time>
                        <span class="fayno-application__open" aria-hidden="true">↗</span>
                    </div>
                </a>
            </div>
            <div v-else class="fayno-applications__empty-state">
                <span class="fayno-applications__empty-icon">✓</span>
                <strong>{{ card.translations.noApplications }}</strong>
                <small>Нові заявки від батьків з’являться тут</small>
            </div>
            <a class="fayno-applications__footer-link" href="/nova/resources/orders">
                Усі заявки <span aria-hidden="true">→</span>
            </a>
        </section>

        <section class="fayno-applications__section fayno-applications__section--unanswered">
            <div class="fayno-applications__heading">
                <div class="fayno-applications__heading-copy">
                    <span class="fayno-applications__eyebrow">ПІД КОНТРОЛЕМ</span>
                    <h2>{{ card.translations.unansweredTitle }}</h2>
                    <p>{{ card.translations.unansweredDescription }}</p>
                </div>
                <span class="fayno-applications__count fayno-applications__count--warning">
                    {{ card.unansweredOrders.length }}
                </span>
            </div>

            <div v-if="card.unansweredOrders.length" class="fayno-applications__list">
                <a
                    v-for="order in card.unansweredOrders"
                    :key="order.id"
                    :href="`/nova/resources/orders/${order.id}`"
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
                </a>
            </div>
            <div v-else class="fayno-applications__empty-state fayno-applications__empty-state--success">
                <span class="fayno-applications__empty-icon">✓</span>
                <strong>{{ card.translations.noUnanswered }}</strong>
                <small>Усі звернення під контролем</small>
            </div>
            <a class="fayno-applications__footer-link" href="/nova/resources/orders">
                Переглянути заявки <span aria-hidden="true">→</span>
            </a>
        </section>
    </div>
</template>

<script>
export default {
    props: ['card'],
}
</script>
