<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@statamic/cms/inertia';
import {
    Badge, Button, Card, ConfirmationModal, Description, Dropdown, DropdownItem, DropdownMenu, Field, Header, Input, Modal, Panel, Select, Text, Textarea,
} from '@statamic/cms/ui';
import MergeTagHint from '../../components/MergeTagHint.vue';
import { campaignStatus } from '../../components/campaigns.js';
import { formatDateTime } from '../../components/dates.js';

const props = defineProps({
    title: String,
    values: Object,
    status: String,
    scheduledAt: String,
    segments: Array,
    audienceCounts: Object,
    templates: Array,
    mergeTags: Array,
    submitUrl: String,
    submitMethod: String,
    urls: Object,
    indexUrl: String,
    userEmail: String,
});

const form = useForm({ ...props.values });
const segmentOptions = computed(() => [{ value: null, label: __('All subscribed contacts') }, ...props.segments]);
const audience = computed(() => props.audienceCounts[form.segment_id ?? 'all'] ?? 0);
const template = ref(null);
const templateOptions = computed(() => props.templates.map((t) => ({ value: t.id, label: t.name })));

function applyTemplate(id) {
    const chosen = props.templates.find((t) => t.id === id);
    if (chosen) {
        form.subject = chosen.subject;
        form.body = chosen.body;
    }
}

function save(onSuccess) {
    form.submit(props.submitMethod, props.submitUrl, { preserveScroll: true, onSuccess });
}

const testing = ref(false);
const testForm = useForm({ email: props.userEmail });
function sendTest() {
    save(() => testForm.post(props.urls.test, { preserveScroll: true, onSuccess: () => (testing.value = false) }));
}

const sending = ref(false);
const sendForm = useForm({ scheduled_at: null });
const later = ref(false);
function send() {
    save(() => sendForm.transform((data) => ({ scheduled_at: later.value ? data.scheduled_at : null })).post(props.urls.send));
}

const deleting = ref(false);
</script>

<template>
    <Head :title="title" />

    <form @submit.prevent="save()">
        <Header :title="title" icon="mail-send-email-attachment-document">
            <template #title>
                <span class="flex items-center gap-3">
                    {{ title }}
                    <Badge v-bind="campaignStatus(status)" />
                </span>
            </template>
            <Dropdown v-if="urls">
                <template #trigger>
                    <Button icon="dots" variant="ghost" :aria-label="__('More')" />
                </template>
                <DropdownMenu>
                    <DropdownItem :text="__('Send a test')" icon="mail-send-email-attachment-document" @click="testing = true" />
                    <DropdownItem v-if="status === 'scheduled'" :text="__('Unschedule')" icon="x" @click="router.post(urls.cancel)" />
                    <DropdownItem :text="__('Delete')" icon="trash" variant="destructive" @click="deleting = true" />
                </DropdownMenu>
            </Dropdown>
            <Button type="submit" :text="__('Save')" :loading="form.processing" />
            <Button v-if="urls" variant="primary" :text="__('Send…')" :disabled="!audience" @click="sending = true" />
        </Header>

        <Description v-if="status === 'scheduled'" class="mb-4" :text="__('Scheduled for :date. Edits are saved into the scheduled send.', { date: formatDateTime(scheduledAt) })" />

        <div class="grid gap-6 lg:grid-cols-3">
            <Panel :heading="__('Email')" class="lg:col-span-2">
                <Card class="space-y-4">
                    <Field v-if="templates.length" :label="__('Template')">
                        <Select v-model="template" :options="templateOptions" :placeholder="__('Start from a template…')" clearable class="w-full sm:w-72" @update:model-value="applyTemplate" />
                    </Field>
                    <Field :label="__('Subject')" :error="form.errors.subject">
                        <Input v-model="form.subject" />
                    </Field>
                    <Field :label="__('Message')" :instructions="__('Markdown. Links are tracked; an unsubscribe link is added automatically.')" :error="form.errors.body">
                        <Textarea v-model="form.body" :rows="16" elastic class="font-mono" />
                        <MergeTagHint :tags="mergeTags" />
                    </Field>
                </Card>
            </Panel>

            <div class="space-y-6">
                <Panel :heading="__('Campaign')">
                    <Card class="space-y-4">
                        <Field :label="__('Name')" :instructions="__('Only you see this.')" :error="form.errors.name">
                            <Input v-model="form.name" />
                        </Field>
                        <Field :label="__('Audience')" :error="form.errors.segment_id">
                            <Select v-model="form.segment_id" :options="segmentOptions" />
                        </Field>
                        <Text size="sm" variant="subtle" :text="__('Recipients: :count subscribed contacts with an email address', { count: audience })" />
                    </Card>
                </Panel>
                <Description v-if="!urls" :text="__('Save the campaign to send a test or send it.')" />
            </div>
        </div>
    </form>

    <Modal v-model:open="testing" :title="__('Send a test')">
        <form class="space-y-4" @submit.prevent="sendTest">
            <Field :label="__('Send to')" :error="testForm.errors.email">
                <Input v-model="testForm.email" type="email" />
            </Field>
            <div class="flex justify-end gap-2">
                <Button :text="__('Cancel')" @click="testing = false" />
                <Button type="submit" variant="primary" :text="__('Send test')" :loading="testForm.processing" />
            </div>
        </form>
    </Modal>

    <Modal v-model:open="sending" :title="__('Send campaign')">
        <form class="space-y-4" @submit.prevent="send">
            <Description :text="__('“:subject” goes to :count contacts.', { subject: form.subject, count: audience })" />
            <Field :label="__('When')">
                <Select v-model="later" :options="[{ value: false, label: __('Now') }, { value: true, label: __('Later') }]" />
            </Field>
            <Field v-if="later" :label="__('Send at')" :error="sendForm.errors.scheduled_at">
                <Input v-model="sendForm.scheduled_at" type="datetime-local" />
            </Field>
            <div class="flex justify-end gap-2">
                <Button :text="__('Cancel')" @click="sending = false" />
                <Button type="submit" variant="primary" :text="later ? __('Schedule') : __('Send now')" :loading="sendForm.processing" :disabled="later && !sendForm.scheduled_at" />
            </div>
        </form>
    </Modal>

    <ConfirmationModal
        v-model:open="deleting"
        :title="__('Delete campaign')"
        :body-text="__('Delete this campaign?')"
        :button-text="__('Delete')"
        danger
        @confirm="router.delete(urls.destroy)"
    />
</template>
