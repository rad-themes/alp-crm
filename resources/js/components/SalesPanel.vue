<script setup>
import { Link } from '@statamic/cms/inertia';
import { Button, Card, Description, Heading, Panel } from '@statamic/cms/ui';
import DocumentStatusBadge from './DocumentStatusBadge.vue';
import { formatDate } from './dates.js';

defineProps({
    sales: Object,
    canEdit: Boolean,
});

const sections = [
    { key: 'invoices', heading: __('Invoices'), create: 'invoice', createLabel: __('New invoice') },
    { key: 'quotes', heading: __('Quotes'), create: 'quote', createLabel: __('New quote') },
    { key: 'transactions', heading: __('Transactions'), create: 'transaction', createLabel: __('New transaction') },
];
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <Card>
                <Description :text="__('Lifetime value')" />
                <Heading size="xl" :text="sales.lifetime_value" class="mt-1" />
            </Card>
            <Card>
                <Description :text="__('Outstanding')" />
                <Heading size="xl" :text="sales.outstanding" class="mt-1" />
            </Card>
        </div>

        <Panel v-for="section in sections" :key="section.key" :heading="section.heading">
            <template v-if="canEdit && sales.create[section.create]" #header-actions>
                <Button :href="sales.create[section.create]" :text="section.createLabel" size="sm" icon="plus" />
            </template>
            <Card>
                <Description v-if="!sales[section.key].length" :text="__('None yet.')" />
                <ul v-else class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <li v-for="item in sales[section.key]" :key="item.id" class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <Link :href="item.url" class="font-medium hover:underline">{{ item.number ?? item.title }}</Link>
                            <div class="truncate text-gray-500">{{ [item.number ? item.title : null, formatDate(item.date)].filter(Boolean).join(' · ') }}</div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="tabular-nums">{{ item.total ?? item.amount }}</span>
                            <DocumentStatusBadge :status="item.status" :label="item.status_label ?? __(item.status.charAt(0).toUpperCase() + item.status.slice(1))" />
                        </div>
                    </li>
                </ul>
            </Card>
        </Panel>
    </div>
</template>
