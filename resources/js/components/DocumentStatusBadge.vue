<script setup>
import { Badge } from '@statamic/cms/ui';

defineProps({
    status: String,
    label: String,
});

const colors = {
    draft: 'default',
    sent: 'blue',
    accepted: 'green',
    paid: 'green',
    partial: 'amber',
    declined: 'red',
    overdue: 'red',
    expired: 'orange',
    void: 'default',
    succeeded: 'green',
    pending: 'amber',
    failed: 'red',
    refunded: 'violet',
    cancelled: 'default',
};
</script>

<template>
    <Badge v-if="label" :text="label" :color="colors[status] ?? 'default'" pill />
</template>
