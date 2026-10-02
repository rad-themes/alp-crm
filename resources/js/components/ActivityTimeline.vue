<script setup>
import { Description, Text } from '@statamic/cms/ui';
import { fromNow, formatDateTime } from './dates.js';

defineProps({
    activities: Array,
});
</script>

<template>
    <Description v-if="!activities.length" :text="__('No activity yet.')" class="py-4 text-center" />

    <ol v-else class="relative space-y-4 border-s border-gray-200 ps-5 dark:border-gray-700">
        <li v-for="activity in activities" :key="activity.id" class="relative">
            <span class="absolute -start-[1.6rem] top-1.5 size-2.5 rounded-full bg-gray-300 ring-4 ring-white dark:bg-gray-600 dark:ring-gray-900" aria-hidden="true" />
            <div><slot name="subject" :activity="activity" /></div>
            <Text as="div" :text="activity.description" />
            <Text as="div" size="sm" variant="subtle" :text="[activity.causer, fromNow(activity.created_at)].filter(Boolean).join(' · ')" :title="formatDateTime(activity.created_at)" />
        </li>
    </ol>
</template>
