<script setup>
import { Text } from '@statamic/cms/ui';

defineProps({
    /** [{ label, value, url?, href? }] */
    items: Array,
});
</script>

<template>
    <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
        <div v-for="item in items" :key="item.label" class="grid grid-cols-3 gap-4 py-2.5 first:pt-0 last:pb-0">
            <dt class="text-gray-500 dark:text-gray-400">{{ item.label }}</dt>
            <dd class="col-span-2 break-words">
                <a v-if="item.url && /^https?:\/\//i.test(item.url)" :href="item.url" target="_blank" rel="noopener" class="text-blue-600 hover:underline dark:text-blue-400">{{ item.value }}</a>
                <a v-else-if="item.href" :href="item.href" class="text-blue-600 hover:underline dark:text-blue-400">{{ item.value }}</a>
                <Text v-else :text="item.value" />
            </dd>
        </div>
    </dl>
</template>
