<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@statamic/cms/inertia';
import {
    Button, Card, Checkbox, Description, Field, Header, Input, Panel, Select, Text, ToggleGroup, ToggleItem,
    Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';

const props = defineProps({
    type: String,
    step: String,
    uploadUrl: String,
    preview: Object,
    mapping: Array,
    targets: Array,
    importUrl: String,
    cancelUrl: String,
});

const uploadForm = useForm({ type: props.type, file: null });
const fileInput = ref(null);

function upload() {
    uploadForm.post(props.uploadUrl, { forceFormData: true });
}

const targetOptions = computed(() => [{ value: null, label: __('Don’t import') }, ...(props.targets ?? [])]);
const importForm = useForm({ type: props.type, mapping: [...(props.mapping ?? [])], update: false, tags: [], status: '' });
const tagInput = ref('');
const mappedCount = computed(() => importForm.mapping.filter(Boolean).length);
const needs = computed(() => (props.type === 'companies' ? importForm.mapping.includes('name') : importForm.mapping.some((h) => ['email', 'first_name', 'last_name'].includes(h))));

function runImport() {
    importForm
        .transform((data) => ({ ...data, tags: tagInput.value.split(',').map((t) => t.trim()).filter(Boolean) }))
        .post(props.importUrl);
}

const title = computed(() => (props.type === 'companies' ? __('Import companies') : __('Import contacts')));
</script>

<template>
    <Head :title="title" />

    <Header :title="title" icon="upload">
        <Button v-if="step === 'map'" :href="cancelUrl" :text="__('Start over')" />
        <Button v-if="step === 'map'" variant="primary" :text="__('Import :count rows', { count: preview.total })" :loading="importForm.processing" :disabled="!needs" @click="runImport" />
    </Header>

    <Panel v-if="step === 'upload'" :heading="__('Upload a CSV file')">
        <Card>
            <form class="space-y-5" @submit.prevent="upload">
                <Field :label="__('Import')">
                    <ToggleGroup v-model="uploadForm.type">
                        <ToggleItem value="contacts" :label="__('Contacts')" />
                        <ToggleItem value="companies" :label="__('Companies')" />
                    </ToggleGroup>
                </Field>
                <Field :label="__('CSV file')" :instructions="__('The first row must be column headings. Comma, semicolon and tab separated files work. Up to 20 MB.')" :error="uploadForm.errors.file">
                    <input ref="fileInput" type="file" accept=".csv,text/csv" class="block text-sm" @change="uploadForm.file = $event.target.files[0]" />
                </Field>
                <div class="flex justify-end">
                    <Button type="submit" variant="primary" :text="__('Continue')" :loading="uploadForm.processing" :disabled="!uploadForm.file" />
                </div>
            </form>
        </Card>
    </Panel>

    <div v-else class="space-y-6">
        <Panel :heading="__('Match columns to fields')">
            <Table>
                <TableColumns>
                    <TableColumn>{{ __('Column') }}</TableColumn>
                    <TableColumn>{{ __('Example') }}</TableColumn>
                    <TableColumn>{{ __('Import into') }}</TableColumn>
                </TableColumns>
                <TableRows>
                    <TableRow v-for="(header, index) in preview.headers" :key="index">
                        <TableCell class="font-medium">{{ header || __('Column :n', { n: index + 1 }) }}</TableCell>
                        <TableCell class="max-w-64 truncate text-gray-500">{{ preview.rows.map((row) => row[index]).filter(Boolean).slice(0, 2).join(' · ') }}</TableCell>
                        <TableCell class="w-64">
                            <Select v-model="importForm.mapping[index]" :options="targetOptions" searchable />
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>
            <Text v-if="!needs" size="sm" class="mt-2 text-red-600" :text="type === 'companies' ? __('Map a column to Name.') : __('Map a column to Email or a name.')" />
        </Panel>

        <Panel :heading="__('Options')">
            <Card class="space-y-4">
                <Checkbox v-model="importForm.update" :label="type === 'companies' ? __('Update companies that already exist (same name)') : __('Update contacts that already exist (same email)')" :description="__('Otherwise they’re skipped.')" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field :label="__('Status for new records')" :instructions="__('Leave empty for “lead”, or map a Status column.')">
                        <Input v-model="importForm.status" />
                    </Field>
                    <Field :label="__('Tag everyone imported')" :instructions="__('Comma separated, e.g. “Imported, Trade show”.')">
                        <Input v-model="tagInput" />
                    </Field>
                </div>
                <Description :text="__(':mapped of :total columns mapped · :rows rows', { mapped: mappedCount, total: preview.headers.length, rows: preview.total })" />
            </Card>
        </Panel>
    </div>
</template>
