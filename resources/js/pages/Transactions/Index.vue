<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { Button, Card, Description, DropdownItem, Header, Heading, Listing, Select } from '@statamic/cms/ui';
import DocumentStatusBadge from '../../components/DocumentStatusBadge.vue';
import { formatDate } from '../../components/dates.js';

defineProps({
    jsonUrl: String,
    createUrl: String,
    statuses: Array,
    summary: Array,
    canEdit: Boolean,
});

const status = ref(null);
const parameters = computed(() => (status.value ? { status: status.value } : {}));

const columns = [
    { field: 'date', label: __('Date'), sortable: true, visible: true },
    { field: 'title', label: __('Title'), sortable: true, visible: true },
    { field: 'contact', label: __('Contact'), sortable: false, visible: true },
    { field: 'invoice', label: __('Invoice'), sortable: false, visible: true },
    { field: 'reference', label: __('Reference'), sortable: false, visible: false },
    { field: 'source', label: __('Source'), sortable: false, visible: false },
    { field: 'amount', label: __('Amount'), sortable: true, visible: true },
    { field: 'status', label: __('Status'), sortable: true, visible: true },
];
</script>

<template>
    <Head :title="__('Transactions')" />

    <Header :title="__('Transactions')" icon="money-cash-bill">
        <Select v-model="status" :options="statuses" :placeholder="__('All statuses')" clearable class="w-44" />
        <Button v-if="canEdit" :href="createUrl" :text="__('Create Transaction')" variant="primary" />
    </Header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <Card v-for="stat in summary" :key="stat.label">
            <Description :text="stat.label" />
            <Heading size="xl" :text="stat.value" class="mt-1" />
        </Card>
    </div>

    <Listing
        :key="status ?? 'all'"
        :url="jsonUrl"
        :columns="columns"
        :additional-parameters="parameters"
        :allow-bulk-actions="false"
        sort-column="date"
        sort-direction="desc"
        preferences-prefix="radpack-crm.transactions"
    >
        <template #cell-date="{ value }">{{ formatDate(value) }}</template>
        <template #cell-title="{ row }">
            <Link v-if="canEdit" :href="row.edit_url" class="title-index-field">{{ row.title }}</Link>
            <span v-else>{{ row.title }}</span>
        </template>
        <template #cell-contact="{ row }">
            <Link v-if="row.contact_url" :href="row.contact_url" class="hover:underline">{{ row.contact }}</Link>
        </template>
        <template #cell-invoice="{ row }">
            <Link v-if="row.invoice_url" :href="row.invoice_url" class="hover:underline">{{ row.invoice }}</Link>
        </template>
        <template #cell-amount="{ row }">
            <span class="tabular-nums" :class="{ 'text-red-600': row.type === 'refund' }">{{ row.amount }}</span>
        </template>
        <template #cell-status="{ row }">
            <DocumentStatusBadge :status="row.status" :label="__(row.status.charAt(0).toUpperCase() + row.status.slice(1))" />
        </template>
        <template #prepended-row-actions="{ row }">
            <DropdownItem v-if="canEdit" :text="__('Edit')" :href="row.edit_url" icon="edit" />
        </template>
    </Listing>
</template>
