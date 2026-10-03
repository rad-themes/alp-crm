<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@statamic/cms/inertia';
import {
    Badge, Button, Card, Checkbox, ConfirmationModal, Description, Dropdown, DropdownItem, DropdownMenu, Field, Header, Input, Modal, Panel, Switch, Text,
    Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';
import { formatDate, fromNow } from '../components/dates.js';

const props = defineProps({ apiUrl: String, keys: Array, webhooks: Array, events: Array, urls: Object, newKey: String });

// API keys
const creatingKey = ref(false);
const keyForm = useForm({ name: '', can_write: true });
function storeKey() {
    keyForm.post(props.urls.storeKey, { preserveScroll: true, onSuccess: () => { creatingKey.value = false; keyForm.reset(); } });
}
const revoking = ref(null);
const showNewKey = ref(!!props.newKey);
const copied = ref(false);
function copy(text) {
    navigator.clipboard
        ?.writeText(text)
        .then(() => {
            copied.value = true;
            setTimeout(() => (copied.value = false), 1500);
        })
        .catch(() => Statamic.$toast.error(__('Couldn’t copy to the clipboard')));
}

// Webhooks
const editing = ref(null);
const webhookForm = useForm({ name: '', url: '', events: [], active: true });
const eventLabels = computed(() => Object.fromEntries(props.events.map((e) => [e.value, e.label])));
function openWebhook(webhook = null) {
    editing.value = webhook ?? {};
    webhookForm.defaults({ name: webhook?.name ?? '', url: webhook?.url ?? '', events: [...(webhook?.events ?? ['contact.created'])], active: webhook?.active ?? true });
    webhookForm.reset();
    webhookForm.clearErrors();
}
function toggleEvent(value, on) {
    webhookForm.events = on ? [...webhookForm.events, value] : webhookForm.events.filter((e) => e !== value);
}
function saveWebhook() {
    const options = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    editing.value.update_url ? webhookForm.patch(editing.value.update_url, options) : webhookForm.post(props.urls.storeWebhook, options);
}
const deletingWebhook = ref(null);
const statusColor = (status) => (!status ? 'red' : status < 300 ? 'green' : 'red');
</script>

<template>
    <Head :title="__('API & webhooks')" />

    <Header :title="__('API & webhooks')" icon="git" />

    <div class="space-y-8">
        <Panel :heading="__('REST API')">
            <template #header-actions>
                <Button :text="__('Create API key')" size="sm" icon="plus" @click="creatingKey = true" />
            </template>
            <Card class="space-y-3">
                <Description :text="__('Send the key as a bearer token: “Authorization: Bearer YOUR_KEY”. Endpoints: /contacts, /contacts/upsert, /companies, /tasks, /transactions, /quotes, /invoices, /hooks — see the README for details.')" />
                <div class="flex items-center gap-2">
                    <code class="rounded bg-gray-100 px-2 py-1 text-sm dark:bg-gray-800">{{ apiUrl }}</code>
                    <Button size="xs" icon="clipboard" :aria-label="__('Copy')" @click="copy(apiUrl)" />
                </div>
            </Card>
            <Table v-if="keys.length" class="mt-3">
                <TableColumns>
                    <TableColumn>{{ __('Name') }}</TableColumn>
                    <TableColumn>{{ __('Key') }}</TableColumn>
                    <TableColumn>{{ __('Access') }}</TableColumn>
                    <TableColumn>{{ __('Last used') }}</TableColumn>
                    <TableColumn />
                </TableColumns>
                <TableRows>
                    <TableRow v-for="key in keys" :key="key.id">
                        <TableCell class="font-medium">{{ key.name }}</TableCell>
                        <TableCell><code class="text-xs">alp_…{{ key.hint }}</code></TableCell>
                        <TableCell><Badge :text="key.can_write ? __('Read & write') : __('Read only')" size="sm" /></TableCell>
                        <TableCell>{{ key.last_used_at ? fromNow(key.last_used_at) : __('Never') }}</TableCell>
                        <TableCell class="text-end">
                            <Button :text="__('Revoke')" size="xs" variant="ghost" @click="revoking = key" />
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>
        </Panel>

        <Panel :heading="__('Webhooks')">
            <template #header-actions>
                <Button :text="__('Add webhook')" size="sm" icon="plus" @click="openWebhook()" />
            </template>
            <Card v-if="!webhooks.length">
                <Description :text="__('Webhooks POST a JSON event to your URL when something happens in the CRM — use them with Zapier, Make, n8n or your own code. Each request is signed: X-Alp-Signature is sha256=HMAC(body, secret).')" />
            </Card>
            <Table v-else>
                <TableColumns>
                    <TableColumn>{{ __('Webhook') }}</TableColumn>
                    <TableColumn>{{ __('Events') }}</TableColumn>
                    <TableColumn>{{ __('Last delivery') }}</TableColumn>
                    <TableColumn />
                </TableColumns>
                <TableRows>
                    <TableRow v-for="webhook in webhooks" :key="webhook.id">
                        <TableCell>
                            <div class="flex items-center gap-2 font-medium">
                                {{ webhook.name }}
                                <Badge v-if="!webhook.active" :text="__('Paused')" size="sm" />
                                <Badge v-if="webhook.source === 'api'" :text="__('REST hook')" size="sm" color="blue" />
                            </div>
                            <div class="max-w-80 truncate text-xs text-gray-500">{{ webhook.url }}</div>
                        </TableCell>
                        <TableCell class="text-sm">{{ webhook.events.map((e) => (e === '*' ? __('Everything') : eventLabels[e] ?? e)).join(', ') }}</TableCell>
                        <TableCell>
                            <template v-if="webhook.last_sent_at">
                                <Badge :text="webhook.last_status ? String(webhook.last_status) : __('Failed')" :color="statusColor(webhook.last_status)" size="sm" />
                                <span class="ms-2 text-xs text-gray-500" :title="webhook.last_error">{{ fromNow(webhook.last_sent_at) }}</span>
                            </template>
                            <span v-else class="text-gray-400">—</span>
                        </TableCell>
                        <TableCell class="text-end">
                            <Dropdown>
                                <template #trigger>
                                    <Button icon="dots" variant="ghost" size="sm" :aria-label="__('Actions')" />
                                </template>
                                <DropdownMenu>
                                    <DropdownItem :text="__('Edit')" icon="edit" @click="openWebhook(webhook)" />
                                    <DropdownItem :text="__('Send test event')" icon="mail-send-email-attachment-document" @click="router.post(webhook.test_url, {}, { preserveScroll: true })" />
                                    <DropdownItem :text="__('Copy signing secret')" icon="clipboard" @click="copy(webhook.secret)" />
                                    <DropdownItem :text="__('Delete')" icon="trash" variant="destructive" @click="deletingWebhook = webhook" />
                                </DropdownMenu>
                            </Dropdown>
                        </TableCell>
                    </TableRow>
                </TableRows>
            </Table>
        </Panel>
    </div>

    <Modal v-model:open="creatingKey" :title="__('Create API key')">
        <form class="space-y-4" @submit.prevent="storeKey">
            <Field :label="__('Name')" :instructions="__('Where it’s used, e.g. “Zapier”.')" :error="keyForm.errors.name">
                <Input v-model="keyForm.name" autofocus />
            </Field>
            <Checkbox v-model="keyForm.can_write" :label="__('Allow changes')" :description="__('Untick for a read-only key.')" />
            <div class="flex justify-end gap-2">
                <Button :text="__('Cancel')" @click="creatingKey = false" />
                <Button type="submit" variant="primary" :text="__('Create')" :loading="keyForm.processing" :disabled="!keyForm.name.trim()" />
            </div>
        </form>
    </Modal>

    <Modal v-model:open="showNewKey" :title="__('Your new API key')">
        <div class="space-y-4">
            <Description :text="__('Copy it now — for security it won’t be shown again.')" />
            <div class="flex items-center gap-2">
                <code class="min-w-0 flex-1 break-all rounded bg-gray-100 px-2 py-1.5 text-sm dark:bg-gray-800">{{ newKey }}</code>
                <Button :text="copied ? __('Copied') : __('Copy')" icon="clipboard" @click="copy(newKey)" />
            </div>
            <div class="flex justify-end"><Button variant="primary" :text="__('Done')" @click="showNewKey = false" /></div>
        </div>
    </Modal>

    <Modal :open="editing !== null" :title="editing?.id ? __('Edit webhook') : __('Add webhook')" @update:open="(open) => !open && (editing = null)">
        <form class="space-y-4" @submit.prevent="saveWebhook">
            <Field :label="__('Name')" :error="webhookForm.errors.name">
                <Input v-model="webhookForm.name" />
            </Field>
            <Field :label="__('Payload URL')" :error="webhookForm.errors.url">
                <Input v-model="webhookForm.url" type="url" placeholder="https://" />
            </Field>
            <Field :label="__('Events')" :error="webhookForm.errors.events">
                <div class="grid max-h-64 grid-cols-1 gap-1.5 overflow-y-auto sm:grid-cols-2">
                    <Checkbox :model-value="webhookForm.events.includes('*')" :label="__('Everything')" @update:model-value="(on) => toggleEvent('*', on)" />
                    <Checkbox v-for="event in events" :key="event.value" :model-value="webhookForm.events.includes(event.value)" :label="event.label" @update:model-value="(on) => toggleEvent(event.value, on)" />
                </div>
            </Field>
            <div class="flex items-center gap-2">
                <Switch v-model="webhookForm.active" size="sm" :aria-label="__('Active')" />
                <Text size="sm" :text="__('Active')" />
            </div>
            <div class="flex justify-end gap-2">
                <Button :text="__('Cancel')" @click="editing = null" />
                <Button type="submit" variant="primary" :text="__('Save')" :loading="webhookForm.processing" />
            </div>
        </form>
    </Modal>

    <ConfirmationModal
        :open="revoking !== null"
        :title="__('Revoke API key')"
        :body-text="revoking ? __('Anything using “:name” will stop working.', { name: revoking.name }) : ''"
        :button-text="__('Revoke')"
        danger
        @update:open="(open) => !open && (revoking = null)"
        @confirm="router.delete(revoking.destroy_url, { preserveScroll: true, onFinish: () => (revoking = null) })"
    />
    <ConfirmationModal
        :open="deletingWebhook !== null"
        :title="__('Delete webhook')"
        :body-text="__('Stop sending events to this URL?')"
        :button-text="__('Delete')"
        danger
        @update:open="(open) => !open && (deletingWebhook = null)"
        @confirm="router.delete(deletingWebhook.destroy_url, { preserveScroll: true, onFinish: () => (deletingWebhook = null) })"
    />
</template>
