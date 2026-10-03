<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import { Badge, Button, Checkbox, DropdownItem, Header, Listing, ToggleGroup, ToggleItem } from '@statamic/cms/ui';
import { formatDate, formatDateTime } from '../../components/dates.js';

const props = defineProps({
    columns: Array,
    jsonUrl: String,
    createUrl: String,
    calendarUrl: String,
    actionUrl: String,
    view: String,
    views: Array,
    canEdit: Boolean,
});

const currentView = ref(props.view);
const parameters = computed(() => ({ view: currentView.value }));
const listing = ref(0);


function toggle(task, done) {
    router.post(task.toggle_url, { done }, { preserveScroll: true, onSuccess: () => listing.value++ });
}

const when = (task) => (task.starts_at ? (task.all_day ? formatDate(task.starts_at) : formatDateTime(task.starts_at)) : '—');
</script>

<template>
    <Head :title="__('Tasks')" />

    <Header :title="__('Tasks')" icon="checkbox">
        <Button :href="calendarUrl" :text="__('Calendar')" icon="calendar" />
        <Button v-if="canEdit" :href="createUrl" :text="__('Create Task')" variant="primary" />
    </Header>

    <ToggleGroup v-model="currentView" class="mb-4">
        <ToggleItem v-for="option in views" :key="option.value" :value="option.value" :label="option.label" />
    </ToggleGroup>

    <Listing
        :key="`${currentView}-${listing}`"
        :url="jsonUrl"
        :columns="columns"
        :additional-parameters="parameters"
        :action-url="actionUrl"
        sort-column="starts_at"
        sort-direction="asc"
        preferences-prefix="alp-crm.tasks"
    >
        <template #cell-done="{ row }">
            <Checkbox solo :model-value="row.done" :disabled="!canEdit" :aria-label="__('Done')" @update:model-value="(done) => toggle(row, done)" />
        </template>
        <template #cell-title="{ row }">
            <Link :href="row.edit_url" class="title-index-field" :class="{ 'text-gray-400 line-through': row.done }">{{ row.title }}</Link>
            <Badge v-if="row.type !== 'task'" :text="row.type_label" size="sm" class="ms-2" />
        </template>
        <template #cell-starts_at="{ row }">
            <span :class="{ 'font-medium text-red-600 dark:text-red-400': row.overdue }">{{ when(row) }}</span>
        </template>
        <template #cell-contact="{ row }">
            <Link v-if="row.contact_url" :href="row.contact_url" class="hover:underline">{{ row.contact }}</Link>
        </template>
        <template #cell-priority="{ value }">
            <Badge v-if="value === 'high'" :text="__('High')" color="red" size="sm" />
            <Badge v-else-if="value === 'low'" :text="__('Low')" size="sm" />
        </template>
        <template #prepended-row-actions="{ row }">
            <DropdownItem v-if="canEdit" :text="__('Edit')" :href="row.edit_url" icon="edit" />
        </template>
    </Listing>
</template>
