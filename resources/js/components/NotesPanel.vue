<script setup>
import { ref } from 'vue';
import { useForm, router } from '@statamic/cms/inertia';
import { Button, Card, ConfirmationModal, Description, Field, Select, Textarea, Badge, Text } from '@statamic/cms/ui';
import { fromNow, formatDateTime } from './dates.js';

const props = defineProps({
    notes: Array,
    noteTypes: Object,
    storeUrl: String,
    canEdit: Boolean,
});

const typeOptions = Object.entries(props.noteTypes).map(([value, label]) => ({ value, label }));
const typeColors = { note: 'default', call: 'green', meeting: 'violet', email: 'blue', sms: 'amber' };

const form = useForm({ type: 'note', body: '' });
const deleting = ref(null);

function submit() {
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}

function destroy() {
    router.delete(deleting.value.destroy_url, { preserveScroll: true, onFinish: () => (deleting.value = null) });
}
</script>

<template>
    <div class="space-y-4">
        <Card v-if="canEdit">
            <form class="space-y-3" @submit.prevent="submit">
                <Field :label="__('Log')" :error="form.errors.type">
                    <Select v-model="form.type" :options="typeOptions" class="w-48" />
                </Field>
                <Field :label="__('Details')" :error="form.errors.body">
                    <Textarea v-model="form.body" :rows="3" elastic :placeholder="__('What happened?')" />
                </Field>
                <div class="flex justify-end">
                    <Button type="submit" variant="primary" :text="__('Save')" :loading="form.processing" :disabled="!form.body.trim()" />
                </div>
            </form>
        </Card>

        <Description v-if="!notes.length" :text="__('Nothing logged yet.')" class="py-4 text-center" />

        <Card v-for="note in notes" :key="note.id">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Badge :text="note.type_label" :color="typeColors[note.type] ?? 'default'" size="sm" />
                    <Text size="sm" variant="subtle" :text="[note.author, fromNow(note.created_at)].filter(Boolean).join(' · ')" :title="formatDateTime(note.created_at)" />
                </div>
                <Button v-if="canEdit" icon="trash" variant="ghost" size="xs" :aria-label="__('Delete')" @click="deleting = note" />
            </div>
            <p class="mt-2 whitespace-pre-line text-sm">{{ note.body }}</p>
        </Card>

        <ConfirmationModal
            :open="deleting !== null"
            :title="__('Delete')"
            :body-text="__('Are you sure you want to delete this?')"
            :button-text="__('Delete')"
            danger
            @update:open="(open) => !open && (deleting = null)"
            @confirm="destroy"
        />
    </div>
</template>
