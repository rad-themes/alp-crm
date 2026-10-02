<script setup>
defineProps({ tags: Array });
</script>

<template>
    <div class="mt-1.5 flex flex-wrap items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
        <span>{{ __('Merge tags:') }}</span>
        <code v-for="tag in tags" :key="tag.tag" :title="tag.label" class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800">{{ tag.tag }}</code>
    </div>
</template>
