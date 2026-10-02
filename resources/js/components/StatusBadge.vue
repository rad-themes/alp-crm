<script setup>
import { computed } from 'vue';
import { Badge } from '@statamic/cms/ui';

const props = defineProps({
    status: String,
    label: String,
});

const colors = {
    lead: 'blue',
    customer: 'green',
    partner: 'violet',
    refused: 'orange',
    former: 'amber',
    blacklisted: 'red',
};

const color = computed(() => colors[props.status] ?? 'default');
</script>

<template>
    <Badge v-if="label" :text="label" :color="color" pill />
</template>
