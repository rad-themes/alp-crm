<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@statamic/cms/inertia';
import { Button, Card, Description, Field, Header, Input, Panel, Select, Text, ToggleGroup, ToggleItem } from '@statamic/cms/ui';

const props = defineProps({
    title: String,
    values: Object,
    fields: Array,
    statuses: Array,
    tags: Array,
    previewUrl: String,
    submitUrl: String,
    submitMethod: String,
    cancelUrl: String,
});

const form = useForm({
    name: props.values.name ?? '',
    match: props.values.match ?? 'all',
    conditions: props.values.conditions.length ? props.values.conditions : [{ field: 'status', operator: 'is', value: null }],
});

const fieldMap = computed(() => Object.fromEntries(props.fields.map((field) => [field.value, field])));
const fieldOptions = computed(() => props.fields.map(({ value, label }) => ({ value, label })));
const operatorOptions = (condition) => Object.entries(fieldMap.value[condition.field]?.operators ?? {}).map(([value, label]) => ({ value, label }));
const inputFor = (condition) => {
    const input = fieldMap.value[condition.field]?.input;
    if (['set', 'empty', 'never'].includes(condition.operator)) return 'none';
    return input;
};

function changeField(condition, field) {
    condition.field = field;
    condition.operator = Object.keys(fieldMap.value[field].operators)[0];
    condition.value = null;
    condition.key = null;
}

function add() {
    form.conditions.push({ field: 'tag', operator: 'has', value: null });
}

function remove(index) {
    form.conditions.splice(index, 1);
}

const preview = ref(null);
let timer;
watch(
    () => [form.match, JSON.stringify(form.conditions)],
    () => {
        clearTimeout(timer);
        timer = setTimeout(refreshPreview, 300);
    },
    { immediate: true },
);

async function refreshPreview() {
    const response = await fetch(props.previewUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': Statamic.$config.get('csrfToken') },
        body: JSON.stringify({ match: form.match, conditions: form.conditions }),
    });
    if (response.ok) preview.value = await response.json();
}

function submit() {
    form.submit(props.submitMethod, props.submitUrl);
}
</script>

<template>
    <Head :title="title" />

    <form @submit.prevent="submit">
        <Header :title="title" icon="filter">
            <Button :href="cancelUrl" :text="__('Cancel')" />
            <Button type="submit" variant="primary" :text="__('Save')" :loading="form.processing" />
        </Header>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Panel>
                    <Card>
                        <Field :label="__('Name')" :error="form.errors.name">
                            <Input v-model="form.name" :placeholder="__('e.g. VIP customers')" />
                        </Field>
                    </Card>
                </Panel>

                <Panel :heading="__('Rules')">
                    <template #header-actions>
                        <ToggleGroup v-model="form.match" size="sm">
                            <ToggleItem value="all" :label="__('Match all')" />
                            <ToggleItem value="any" :label="__('Match any')" />
                        </ToggleGroup>
                    </template>
                    <Card class="space-y-3">
                        <Description v-if="!form.conditions.length" :text="__('No rules: every contact matches.')" />
                        <div v-for="(condition, index) in form.conditions" :key="index" class="flex flex-wrap items-center gap-2">
                            <Text v-if="index" size="sm" variant="subtle" class="w-10" :text="form.match === 'all' ? __('and') : __('or')" />
                            <Text v-else size="sm" variant="subtle" class="w-10" :text="__('Where')" />
                            <Select :model-value="condition.field" :options="fieldOptions" class="w-44" @update:model-value="(value) => changeField(condition, value)" />
                            <div v-if="condition.field === 'field'" class="w-36">
                                <Input v-model="condition.key" :placeholder="__('field handle')" />
                            </div>
                            <Select v-model="condition.operator" :options="operatorOptions(condition)" class="w-56" />
                            <template v-if="inputFor(condition) === 'status'">
                                <Select v-model="condition.value" :options="statuses" class="w-40" />
                            </template>
                            <template v-else-if="inputFor(condition) === 'tag'">
                                <Select v-model="condition.value" :options="tags" searchable class="w-44" />
                            </template>
                            <div v-else-if="['days', 'number'].includes(inputFor(condition))" class="w-28">
                                <Input v-model="condition.value" type="number" min="0" />
                            </div>
                            <div v-else-if="['text', 'field'].includes(inputFor(condition))" class="w-44">
                                <Input v-model="condition.value" />
                            </div>
                            <div class="flex-1" />
                            <Button icon="x" variant="ghost" size="sm" :aria-label="__('Remove rule')" @click="remove(index)" />
                        </div>
                        <Text v-if="Object.keys(form.errors).some((key) => key.startsWith('conditions'))" size="sm" class="text-red-600" :text="__('Check the rules: each needs a field and an operator.')" />
                        <Button :text="__('Add rule')" icon="plus" size="sm" @click="add" />
                    </Card>
                </Panel>
            </div>

            <Panel :heading="__('Matching contacts')">
                <Card>
                    <div class="text-3xl font-semibold">{{ preview?.count ?? '—' }}</div>
                    <ul v-if="preview?.sample.length" class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li v-for="name in preview.sample" :key="name">{{ name }}</li>
                        <li v-if="preview.count > preview.sample.length">{{ __('and :count more', { count: preview.count - preview.sample.length }) }}</li>
                    </ul>
                </Card>
            </Panel>
        </div>
    </form>
</template>
