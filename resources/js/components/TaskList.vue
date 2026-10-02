<script setup>
import { Link, router } from '@statamic/cms/inertia';
import { Badge, Checkbox, Description, Text } from '@statamic/cms/ui';
import { formatDate, formatDateTime } from './dates.js';

defineProps({
    tasks: Array,
    canEdit: Boolean,
    showContact: { type: Boolean, default: true },
    empty: { type: String, default: () => __('No tasks.') },
});

function toggle(task, done) {
    router.post(task.toggle_url, { done }, { preserveScroll: true, preserveState: true });
}

const when = (task) => (task.starts_at ? (task.all_day ? formatDate(task.starts_at) : formatDateTime(task.starts_at)) : null);
</script>

<template>
    <Description v-if="!tasks.length" :text="empty" />
    <ul v-else class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
        <li v-for="task in tasks" :key="task.id" class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
            <Checkbox
                solo
                :model-value="task.done"
                :disabled="!canEdit"
                :aria-label="task.done ? __('Mark as not done') : __('Mark as done')"
                class="mt-0.5"
                @update:model-value="(done) => toggle(task, done)"
            />
            <div class="min-w-0 flex-1">
                <Link :href="task.edit_url" class="font-medium hover:underline" :class="{ 'text-gray-400 line-through': task.done }">{{ task.title }}</Link>
                <Text as="div" size="sm" variant="subtle">
                    <span :class="{ 'font-medium text-red-600 dark:text-red-400': task.overdue }">{{ when(task) }}</span>
                    <template v-if="showContact && task.contact"> · <Link :href="task.contact_url" class="hover:underline">{{ task.contact }}</Link></template>
                    <template v-if="task.assignee"> · {{ task.assignee }}</template>
                </Text>
            </div>
            <Badge v-if="task.type !== 'task'" :text="task.type_label" size="sm" />
            <Badge v-if="task.priority === 'high' && !task.done" :text="__('High')" color="red" size="sm" />
        </li>
    </ul>
</template>
