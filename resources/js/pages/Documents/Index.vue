<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { Button, DropdownItem, Header, Listing, Select } from '@statamic/cms/ui';
import DocumentStatusBadge from '../../components/DocumentStatusBadge.vue';
import { formatDate } from '../../components/dates.js';

const props = defineProps({
    columns: Array,
    type: String,
    title: String,
    jsonUrl: String,
    createUrl: String,
    statuses: Array,
    canEdit: Boolean,
});

const status = ref(null);
const parameters = computed(() => (status.value ? { status: status.value } : {}));
const isInvoice = props.type === 'invoice';

</script>

<template>
    <Head :title="title" />

    <Header :title="title" :icon="isInvoice ? 'money-cashier-price-tag' : 'file-content-list'">
        <Select v-model="status" :options="statuses" :placeholder="__('All statuses')" clearable class="w-44" />
        <Button v-if="canEdit" :href="createUrl" :text="isInvoice ? __('Create Invoice') : __('Create Quote')" variant="primary" />
    </Header>

    <Listing
        :key="status ?? 'all'"
        :url="jsonUrl"
        :columns="columns"
        :additional-parameters="parameters"
        :allow-bulk-actions="false"
        sort-column="issue_date"
        sort-direction="desc"
        :preferences-prefix="`radpack-crm.${type}s`"
    >
        <template #cell-number="{ row }">
            <Link :href="row.show_url" class="title-index-field">{{ row.number }}</Link>
        </template>
        <template #cell-client="{ row }">
            <Link v-if="row.contact" :href="row.contact.url" class="hover:underline">{{ row.client }}</Link>
            <span v-else>{{ row.client }}</span>
        </template>
        <template #cell-issue_date="{ value }">{{ formatDate(value) }}</template>
        <template #cell-due_date="{ row }">{{ formatDate(row.second_date) }}</template>
        <template #cell-valid_until="{ row }">{{ formatDate(row.second_date) }}</template>
        <template #cell-status="{ row }">
            <DocumentStatusBadge :status="row.status" :label="row.status_label" />
        </template>
        <template #prepended-row-actions="{ row }">
            <DropdownItem :text="__('View')" :href="row.show_url" icon="eye" />
            <DropdownItem v-if="canEdit" :text="__('Edit')" :href="row.edit_url" icon="edit" />
        </template>
    </Listing>
</template>
