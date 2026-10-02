<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import { Card, Description, Header, Panel, Select, Text } from '@statamic/cms/ui';

const props = defineProps({ period: String, periods: Array, kpis: Array, funnel: Array, months: Array, topCustomers: Array, sources: Array });

const maxRevenue = computed(() => Math.max(1, ...props.months.map((m) => m.revenue)));
const maxContacts = computed(() => Math.max(1, ...props.months.map((m) => m.contacts)));
const maxSource = computed(() => Math.max(1, ...props.sources.map((s) => s.total)));

function changePeriod(period) {
    router.get(window.location.pathname, { period }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head :title="__('Reports')" />

    <Header :title="__('Reports')" icon="chart-monitoring-indicator">
        <Select :model-value="period" :options="periods" class="w-44" @update:model-value="changePeriod" />
    </Header>

    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-4">
        <Card v-for="kpi in kpis" :key="kpi.label">
            <Text size="sm" variant="subtle" :text="kpi.label" />
            <div class="mt-1 text-2xl font-semibold">{{ kpi.value }}</div>
            <Text v-if="kpi.hint" size="xs" variant="subtle" :text="kpi.hint" />
        </Card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <Panel :heading="__('Revenue · last 12 months')">
            <Card>
                <div class="flex h-48 items-end gap-1.5">
                    <div v-for="month in months" :key="month.month" class="group flex h-full flex-1 flex-col items-center justify-end gap-1" :title="`${month.label}: ${month.revenue_formatted}`">
                        <div class="w-full rounded-t bg-blue-500/80 transition group-hover:bg-blue-600 dark:bg-blue-400/70" :style="{ height: `${Math.max(month.revenue > 0 ? 2 : 0, (month.revenue / maxRevenue) * 100)}%` }" />
                        <span class="text-2xs text-gray-500">{{ month.label }}</span>
                    </div>
                </div>
            </Card>
        </Panel>

        <Panel :heading="__('New contacts · last 12 months')">
            <Card>
                <div class="flex h-48 items-end gap-1.5">
                    <div v-for="month in months" :key="month.month" class="group flex h-full flex-1 flex-col items-center justify-end gap-1" :title="`${month.label}: ${month.contacts}`">
                        <div class="w-full rounded-t bg-emerald-500/80 transition group-hover:bg-emerald-600 dark:bg-emerald-400/70" :style="{ height: `${Math.max(month.contacts > 0 ? 2 : 0, (month.contacts / maxContacts) * 100)}%` }" />
                        <span class="text-2xs text-gray-500">{{ month.label }}</span>
                    </div>
                </div>
            </Card>
        </Panel>

        <Panel :heading="__('Funnel')">
            <Card class="space-y-3">
                <Description :text="__('Contacts by status, in order. “Reached” counts everyone at this stage or further along.')" />
                <div v-for="stage in funnel" :key="stage.status">
                    <div class="mb-1 flex items-baseline justify-between text-sm">
                        <span class="font-medium">{{ stage.label }}</span>
                        <span class="text-gray-500">{{ __(':count now · :reached reached · :rate%', { count: stage.count, reached: stage.reached, rate: stage.rate }) }}</span>
                    </div>
                    <div class="h-6 rounded bg-gray-100 dark:bg-gray-800">
                        <div class="h-6 rounded bg-violet-500/80 dark:bg-violet-400/70" :style="{ width: `${Math.max(stage.reached ? 1 : 0, stage.rate)}%` }" />
                    </div>
                </div>
            </Card>
        </Panel>

        <div class="space-y-6">
            <Panel :heading="__('Top customers')">
                <Card>
                    <Description v-if="!topCustomers.length" :text="__('No sales in this period.')" />
                    <ol v-else class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <li v-for="(customer, i) in topCustomers" :key="customer.url" class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                            <span class="w-4 text-gray-400">{{ i + 1 }}</span>
                            <Link :href="customer.url" class="flex-1 hover:underline">{{ customer.name }}</Link>
                            <span class="font-medium">{{ customer.total }}</span>
                        </li>
                    </ol>
                </Card>
            </Panel>
            <Panel :heading="__('Sales by source')">
                <Card class="space-y-2">
                    <Description v-if="!sources.length" :text="__('No sales in this period.')" />
                    <div v-for="source in sources" :key="source.source" class="flex items-center gap-3 text-sm">
                        <span class="w-20 shrink-0">{{ source.source }}</span>
                        <div class="h-3 flex-1 rounded bg-gray-100 dark:bg-gray-800">
                            <div class="h-3 rounded bg-blue-500/70" :style="{ width: `${(source.total / maxSource) * 100}%` }" />
                        </div>
                        <span class="w-24 shrink-0 text-end font-medium">{{ source.formatted }}</span>
                    </div>
                </Card>
            </Panel>
        </div>
    </div>
</template>
