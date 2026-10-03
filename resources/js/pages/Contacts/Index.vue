<script setup>
import { Head, Link } from '@statamic/cms/inertia';
import { Badge, Button, Dropdown, DropdownMenu, DropdownItem, Header, Listing } from '@statamic/cms/ui';
import StatusBadge from '../../components/StatusBadge.vue';
import { fromNow, formatDate } from '../../components/dates.js';

defineProps({
    columns: Array,
    filters: Array,
    jsonUrl: String,
    actionUrl: String,
    createUrl: String,
    importUrl: String,
    exportUrl: String,
    canEdit: Boolean,
});

</script>

<template>
    <Head :title="__('Contacts')" />

    <Header :title="__('Contacts')" icon="users">
        <Dropdown>
            <template #trigger>
                <Button icon="dots" variant="ghost" :aria-label="__('More')" />
            </template>
            <DropdownMenu>
                <DropdownItem v-if="canEdit" :text="__('Import CSV')" icon="upload" :href="importUrl" />
                <DropdownItem :text="__('Export CSV')" icon="download" :href="exportUrl" target="_blank" />
            </DropdownMenu>
        </Dropdown>
        <Button v-if="canEdit" :href="createUrl" :text="__('Create Contact')" variant="primary" />
    </Header>

    <Listing
        :url="jsonUrl"
        :columns="columns"
        :filters="filters"
        :action-url="actionUrl"
        sort-column="created_at"
        sort-direction="desc"
        preferences-prefix="alp-crm.contacts"
        push-query
    >
        <template #cell-name="{ row }">
            <Link :href="row.show_url" class="title-index-field">{{ row.name }}</Link>
        </template>
        <template #cell-email="{ value }">
            <a v-if="value" :href="`mailto:${value}`" class="hover:underline">{{ value }}</a>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" :label="row.status_label" />
        </template>
        <template #cell-tags="{ value }">
            <div class="flex flex-wrap gap-1">
                <Badge v-for="tag in value" :key="tag" :text="tag" size="sm" />
            </div>
        </template>
        <template #cell-last_contacted_at="{ value }">
            <span :title="formatDate(value)">{{ fromNow(value) }}</span>
        </template>
        <template #cell-created_at="{ value }">
            <span :title="fromNow(value)">{{ formatDate(value) }}</span>
        </template>
        <template #prepended-row-actions="{ row }">
            <DropdownItem :text="__('View')" :href="row.show_url" icon="eye" />
            <DropdownItem v-if="canEdit" :text="__('Edit')" :href="row.edit_url" icon="edit" />
        </template>
    </Listing>
</template>
