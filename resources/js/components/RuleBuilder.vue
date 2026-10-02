<script setup>
import { computed } from 'vue';
import { Button, Description, Input, Select, Text } from '@statamic/cms/ui';

const conditions = defineModel('conditions', { type: Array, required: true });
const props = defineProps({
    match: String,
    fields: Array,
    statuses: Array,
    tags: Array,
    emptyText: String,
    hasErrors: Boolean,
});

const fieldMap = computed(() => Object.fromEntries(props.fields.map((field) => [field.value, field])));
const fieldOptions = computed(() => props.fields.map(({ value, label }) => ({ value, label })));
const operatorOptions = (condition) => Object.entries(fieldMap.value[condition.field]?.operators ?? {}).map(([value, label]) => ({ value, label }));
const inputFor = (condition) => (['set', 'empty', 'never'].includes(condition.operator) ? 'none' : fieldMap.value[condition.field]?.input);

function changeField(condition, field) {
    condition.field = field;
    condition.operator = Object.keys(fieldMap.value[field].operators)[0];
    condition.value = null;
    condition.key = null;
}
</script>

<template>
    <div class="space-y-3">
        <Description v-if="!conditions.length" :text="emptyText ?? __('No rules: every contact matches.')" />
        <div v-for="(condition, index) in conditions" :key="index" class="flex flex-wrap items-center gap-2">
            <Text size="sm" variant="subtle" class="w-10" :text="index ? (match === 'any' ? __('or') : __('and')) : __('Where')" />
            <Select :model-value="condition.field" :options="fieldOptions" class="w-44" @update:model-value="(value) => changeField(condition, value)" />
            <div v-if="condition.field === 'field'" class="w-36">
                <Input v-model="condition.key" :placeholder="__('field handle')" />
            </div>
            <Select v-model="condition.operator" :options="operatorOptions(condition)" class="w-56" />
            <Select v-if="inputFor(condition) === 'status'" v-model="condition.value" :options="statuses" class="w-40" />
            <Select v-else-if="inputFor(condition) === 'tag'" v-model="condition.value" :options="tags" searchable class="w-44" />
            <div v-else-if="['days', 'number'].includes(inputFor(condition))" class="w-28">
                <Input v-model="condition.value" type="number" min="0" />
            </div>
            <div v-else-if="['text', 'field'].includes(inputFor(condition))" class="w-44">
                <Input v-model="condition.value" />
            </div>
            <div class="flex-1" />
            <Button icon="x" variant="ghost" size="sm" :aria-label="__('Remove rule')" @click="conditions.splice(index, 1)" />
        </div>
        <Text v-if="hasErrors" size="sm" class="text-red-600" :text="__('Check the rules: each needs a field and an operator.')" />
        <Button :text="__('Add rule')" icon="plus" size="sm" @click="conditions.push({ field: 'tag', operator: 'has', value: null })" />
    </div>
</template>
