<script setup>
import { ref, watch } from 'vue';
import { Head, useForm } from '@statamic/cms/inertia';
import { Button, Card, Field, Header, Input, Panel, ToggleGroup, ToggleItem } from '@statamic/cms/ui';
import RuleBuilder from '../../components/RuleBuilder.vue';

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
                    <Card>
                        <RuleBuilder
                            v-model:conditions="form.conditions"
                            :match="form.match"
                            :fields="fields"
                            :statuses="statuses"
                            :tags="tags"
                            :has-errors="Object.keys(form.errors).some((key) => key.startsWith('conditions'))"
                        />
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
