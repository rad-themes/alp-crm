<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Description, Field, Input, Select, Switch, Text, Textarea, ToggleGroup, ToggleItem } from '@statamic/cms/ui';
import { formatDateTime, fromNow } from './dates.js';
import MergeTagHint from './MergeTagHint.vue';

const props = defineProps({
    emails: Array,
    templates: Array,
    mergeTags: Array,
    storeUrl: String,
    sms: Object,
    hasEmail: Boolean,
    canEdit: Boolean,
});

const statusColors = { sent: 'green', scheduled: 'blue', failed: 'red', cancelled: 'default', sending: 'default' };
const statusLabels = { sent: __('Sent'), scheduled: __('Scheduled'), failed: __('Failed'), cancelled: __('Cancelled'), sending: __('Sending') };

const form = useForm({ subject: '', body: '', send_at: null });
const mode = ref('email');
const smsForm = useForm({ body: '' });

function sendSms() {
    smsForm.post(props.sms.url, { preserveScroll: true, onSuccess: () => smsForm.reset() });
}
const template = ref(null);
const scheduling = ref(false);
const expanded = ref(null);

const templateOptions = computed(() => props.templates.map((t) => ({ value: t.id, label: t.name })));

function applyTemplate(id) {
    const chosen = props.templates.find((t) => t.id === id);
    if (chosen) {
        form.subject = chosen.subject;
        form.body = chosen.body;
    }
}

function submit() {
    form.transform((data) => ({ ...data, send_at: scheduling.value ? data.send_at : null })).post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            template.value = null;
            scheduling.value = false;
        },
    });
}

function cancel(email) {
    router.post(email.cancel_url, {}, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4">
        <ToggleGroup v-if="canEdit && sms" v-model="mode" size="sm">
            <ToggleItem value="email" :label="__('Email')" />
            <ToggleItem value="sms" :label="__('Text message')" />
        </ToggleGroup>

        <Card v-if="canEdit && sms && mode === 'sms'">
            <form v-if="sms.phone" class="space-y-3" @submit.prevent="sendSms">
                <Field :label="__('Text to :phone', { phone: sms.phone })" :error="smsForm.errors.body">
                    <Textarea v-model="smsForm.body" :rows="3" elastic />
                </Field>
                <div class="flex items-center justify-between">
                    <Text size="sm" variant="subtle" :text="__(':count characters', { count: smsForm.body.length })" />
                    <Button type="submit" variant="primary" :text="__('Send text')" :loading="smsForm.processing" :disabled="!smsForm.body.trim()" />
                </div>
            </form>
            <Description v-else :text="__('Add a phone number to this contact to text them.')" />
        </Card>

        <Card v-else-if="canEdit && hasEmail">
            <form class="space-y-3" @submit.prevent="submit">
                <Field v-if="templates.length" :label="__('Template')">
                    <Select v-model="template" :options="templateOptions" :placeholder="__('Start from a template…')" clearable class="w-full sm:w-72" @update:model-value="applyTemplate" />
                </Field>
                <Field :label="__('Subject')" :error="form.errors.subject">
                    <Input v-model="form.subject" />
                </Field>
                <Field :label="__('Message')" :error="form.errors.body">
                    <Textarea v-model="form.body" :rows="6" elastic />
                    <MergeTagHint :tags="mergeTags" />
                </Field>
                <div class="flex flex-wrap items-center gap-3">
                    <Switch v-model="scheduling" size="sm" :aria-label="__('Send later')" />
                    <Text size="sm" :text="__('Send later')" />
                    <Input v-if="scheduling" v-model="form.send_at" type="datetime-local" class="w-56" />
                    <Text v-if="form.errors.send_at" size="sm" class="text-red-600" :text="form.errors.send_at" />
                    <div class="flex-1" />
                    <Button
                        type="submit"
                        variant="primary"
                        :icon="scheduling ? 'calendar' : 'mail-send-email-attachment-document'"
                        :text="scheduling ? __('Schedule') : __('Send')"
                        :loading="form.processing"
                        :disabled="!form.subject.trim() || !form.body.trim() || (scheduling && !form.send_at)"
                    />
                </div>
            </form>
        </Card>
        <Description v-else-if="canEdit" :text="__('Add an email address to this contact to email them.')" />

        <Description v-if="!emails.length" :text="__('No emails yet.')" class="py-4 text-center" />

        <Card v-for="email in emails" :key="email.id">
            <div class="flex items-start justify-between gap-3">
                <button type="button" class="min-w-0 flex-1 text-start" @click="expanded = expanded === email.id ? null : email.id">
                    <div class="truncate font-medium">{{ email.subject }}</div>
                    <Text size="sm" variant="subtle" :text="[email.sender, email.status === 'scheduled' ? __('for :date', { date: formatDateTime(email.date) }) : fromNow(email.date)].filter(Boolean).join(' · ')" :title="formatDateTime(email.date)" />
                </button>
                <div class="flex items-center gap-2">
                    <Badge :text="statusLabels[email.status] ?? email.status" :color="statusColors[email.status] ?? 'default'" size="sm" />
                    <Button v-if="canEdit && email.status === 'scheduled'" :text="__('Cancel')" size="xs" @click="cancel(email)" />
                </div>
            </div>
            <Text v-if="email.error" size="sm" class="mt-2 text-red-600" :text="email.error" />
            <p v-if="expanded === email.id" class="mt-3 whitespace-pre-line border-t border-gray-100 pt-3 text-sm dark:border-gray-800">{{ email.body }}</p>
        </Card>
    </div>
</template>
