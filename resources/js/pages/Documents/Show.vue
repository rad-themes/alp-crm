<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@statamic/cms/inertia';
import {
    Button, Card, ConfirmationModal, Description, Dropdown, DropdownItem, DropdownMenu, DropdownSeparator,
    Field, Header, Input, Modal, Panel, Text, Textarea,
} from '@statamic/cms/ui';
import DocumentStatusBadge from '../../components/DocumentStatusBadge.vue';
import ActivityTimeline from '../../components/ActivityTimeline.vue';
import DetailList from '../../components/DetailList.vue';
import { formatDate, formatDateTime } from '../../components/dates.js';

const props = defineProps({
    document: Object,
    activities: Array,
    payments: Array,
    quote: Object,
    invoice: Object,
    responded: Object,
    urls: Object,
    canEdit: Boolean,
    canDelete: Boolean,
});

const isInvoice = props.document.type === 'invoice';
const label = isInvoice ? __('Invoice') : __('Quote');

const sending = ref(false);
const sendForm = useForm({ to: props.document.client_email ?? '', message: '' });
function send() {
    sendForm.post(props.urls.send, { preserveScroll: true, onSuccess: () => (sending.value = false) });
}

const paying = ref(false);
const paymentForm = useForm({ amount: props.document.balance_raw ?? 0, date: new Date().toISOString().slice(0, 10), reference: '', method: 'bank transfer' });
function recordPayment() {
    paymentForm.post(props.urls.payment, { preserveScroll: true, onSuccess: () => (paying.value = false) });
}

const confirming = ref(null);
const confirmations = {
    delete: { title: __('Delete'), body: __('This permanently deletes :number.', { number: props.document.number }), button: __('Delete'), danger: true, run: () => router.delete(props.urls.destroy) },
    void: { title: __('Void invoice'), body: __('A void invoice can no longer be paid. This can’t be undone.'), button: __('Void'), danger: true, run: () => router.post(props.urls.void, {}, { preserveScroll: true }) },
};

const post = (url, data = {}) => router.post(url, data, { preserveScroll: true });

const copied = ref(false);
async function copyLink() {
    await navigator.clipboard.writeText(props.document.public_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

const summary = computed(() => [
    props.document.contact
        ? { label: __('Client'), value: props.document.client, href: props.document.contact.url }
        : props.document.client && { label: __('Client'), value: props.document.client },
    props.document.company && props.document.contact && { label: __('Company'), value: props.document.company.name, href: props.document.company.url },
    { label: __('Issued'), value: formatDate(props.document.issue_date) },
    props.document.second_date && { label: isInvoice ? __('Due') : __('Valid until'), value: formatDate(props.document.second_date) },
    props.document.sent_at && { label: __('Sent'), value: formatDateTime(props.document.sent_at) },
    props.responded && { label: props.document.status === 'accepted' ? __('Accepted') : __('Declined'), value: [props.responded.by, formatDateTime(props.responded.at)].filter(Boolean).join(' · ') },
    props.quote && { label: __('From quote'), value: props.quote.number, href: props.quote.url },
    props.invoice && { label: __('Invoice'), value: props.invoice.number, href: props.invoice.url },
].filter(Boolean));
</script>

<template>
    <Head :title="`${label} ${document.number}`" />

    <Header>
        <template #title>
            <span class="flex items-center gap-3">
                {{ label }} {{ document.number }}
                <DocumentStatusBadge :status="document.status" :label="document.status_label" />
            </span>
        </template>

        <Dropdown>
            <template #trigger>
                <Button icon="dots" variant="ghost" :aria-label="__('More')" />
            </template>
            <DropdownMenu>
                <DropdownItem :text="__('Download PDF')" icon="download" :href="urls.pdf" target="_blank" />
                <DropdownItem v-if="document.status !== 'draft'" :text="copied ? __('Copied!') : __('Copy client link')" icon="link" @click="copyLink" />
                <template v-if="canEdit">
                    <DropdownItem v-if="document.status === 'draft'" :text="__('Mark as sent')" icon="checkmark" @click="post(urls.markSent)" />
                    <DropdownItem :text="__('Duplicate')" icon="duplicate" :href="urls.duplicate" />
                    <template v-if="!isInvoice && ['draft', 'sent', 'expired'].includes(document.status)">
                        <DropdownSeparator />
                        <DropdownItem :text="__('Mark as accepted')" icon="checkmark" @click="post(urls.respond, { accepted: true })" />
                        <DropdownItem :text="__('Mark as declined')" icon="x" @click="post(urls.respond, { accepted: false })" />
                    </template>
                    <DropdownItem v-if="isInvoice && document.status !== 'void' && document.status !== 'paid'" :text="__('Void')" icon="x" variant="destructive" @click="confirming = 'void'" />
                </template>
                <template v-if="canDelete">
                    <DropdownSeparator />
                    <DropdownItem :text="__('Delete')" icon="trash" variant="destructive" @click="confirming = 'delete'" />
                </template>
            </DropdownMenu>
        </Dropdown>
        <template v-if="canEdit">
            <Button v-if="!isInvoice && !invoice && document.status === 'accepted'" :text="__('Convert to invoice')" @click="post(urls.convert)" />
            <Button v-if="isInvoice && ['sent', 'partial', 'overdue'].includes(document.status)" :text="__('Record payment')" @click="paying = true" />
            <Button :href="urls.edit" :text="__('Edit')" />
            <Button :text="document.sent_at ? __('Send again') : __('Send')" icon="mail-send-email-attachment-document" variant="primary" @click="sending = true" />
        </template>
    </Header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <Panel>
                <Card class="space-y-6">
                    <div v-if="document.title" class="text-lg font-semibold">{{ document.title }}</div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700">
                                <th class="py-2 pe-3 font-medium">{{ __('Description') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('Qty') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('Price') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('Tax') }}</th>
                                <th class="py-2 ps-3 text-end font-medium">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in document.items" :key="index" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="whitespace-pre-line py-2.5 pe-3">{{ item.description }}</td>
                                <td class="px-3 py-2.5 text-end tabular-nums">{{ Number(item.quantity) }}</td>
                                <td class="px-3 py-2.5 text-end tabular-nums">{{ item.unit_price_formatted }}</td>
                                <td class="px-3 py-2.5 text-end">{{ item.tax_rate ? `${item.tax_name ?? ''} ${Number(item.tax_rate)}%` : '—' }}</td>
                                <td class="py-2.5 ps-3 text-end tabular-nums">{{ item.total_formatted }}</td>
                            </tr>
                            <tr v-if="!document.items.length">
                                <td colspan="5" class="py-6 text-center text-gray-500">{{ __('No items yet.') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <dl class="ms-auto w-full max-w-xs space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ document.subtotal }}</dd></div>
                        <div v-if="document.discount > 0" class="flex justify-between"><dt class="text-gray-500">{{ __('Discount') }}</dt><dd class="tabular-nums">−{{ document.discount_formatted }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">{{ __('Tax') }}</dt><dd class="tabular-nums">{{ document.tax_total }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold dark:border-gray-700"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ document.total }}</dd></div>
                        <template v-if="isInvoice">
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('Paid') }}</dt><dd class="tabular-nums">{{ document.amount_paid }}</dd></div>
                            <div class="flex justify-between font-semibold"><dt>{{ __('Balance due') }}</dt><dd class="tabular-nums">{{ document.balance }}</dd></div>
                        </template>
                    </dl>

                    <div v-if="document.notes" class="whitespace-pre-line text-sm">{{ document.notes }}</div>
                    <div v-if="document.terms" class="whitespace-pre-line text-sm text-gray-500">{{ document.terms }}</div>
                </Card>
            </Panel>

            <Panel v-if="isInvoice" :heading="__('Payments')">
                <Card>
                    <Description v-if="!payments.length" :text="__('No payments yet.')" />
                    <ul v-else class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <li v-for="payment in payments" :key="payment.id" class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                            <div>
                                <Link :href="payment.url" class="font-medium tabular-nums hover:underline">{{ payment.amount }}</Link>
                                <Text as="div" size="sm" variant="subtle" :text="[formatDate(payment.date), payment.source, payment.reference].filter(Boolean).join(' · ')" />
                            </div>
                            <DocumentStatusBadge :status="payment.status" :label="__(payment.status.charAt(0).toUpperCase() + payment.status.slice(1))" />
                        </li>
                    </ul>
                </Card>
            </Panel>
        </div>

        <div class="space-y-6">
            <Panel :heading="__('Summary')">
                <Card>
                    <DetailList :items="summary" />
                </Card>
            </Panel>
            <Panel :heading="__('Activity')">
                <Card>
                    <ActivityTimeline :activities="activities" />
                </Card>
            </Panel>
        </div>
    </div>

    <Modal v-model:open="sending" :title="__('Email :label', { label: label.toLowerCase() })">
        <form class="space-y-4" @submit.prevent="send">
            <Field :label="__('To')" :error="sendForm.errors.to" required>
                <Input v-model="sendForm.to" type="email" required />
            </Field>
            <Field :label="__('Message')" :instructions="__('Leave empty to use the standard message. The PDF is attached.')" :error="sendForm.errors.message">
                <Textarea v-model="sendForm.message" :rows="4" elastic />
            </Field>
            <div class="flex justify-end gap-2">
                <Button :text="__('Cancel')" variant="ghost" @click="sending = false" />
                <Button type="submit" :text="__('Send')" variant="primary" icon="mail-send-email-attachment-document" :loading="sendForm.processing" />
            </div>
        </form>
    </Modal>

    <Modal v-if="isInvoice" v-model:open="paying" :title="__('Record payment')">
        <form class="space-y-4" @submit.prevent="recordPayment">
            <div class="grid gap-4 sm:grid-cols-2">
                <Field :label="__('Amount')" :error="paymentForm.errors.amount" required>
                    <Input v-model="paymentForm.amount" type="number" step="0.01" min="0.01" required />
                </Field>
                <Field :label="__('Date')" :error="paymentForm.errors.date" required>
                    <Input v-model="paymentForm.date" type="date" required />
                </Field>
                <Field :label="__('Method')" :error="paymentForm.errors.method">
                    <Input v-model="paymentForm.method" />
                </Field>
                <Field :label="__('Reference')" :error="paymentForm.errors.reference">
                    <Input v-model="paymentForm.reference" />
                </Field>
            </div>
            <div class="flex justify-end gap-2">
                <Button :text="__('Cancel')" variant="ghost" @click="paying = false" />
                <Button type="submit" :text="__('Record payment')" variant="primary" :loading="paymentForm.processing" />
            </div>
        </form>
    </Modal>

    <ConfirmationModal
        :open="confirming !== null"
        :title="confirming ? confirmations[confirming].title : ''"
        :body-text="confirming ? confirmations[confirming].body : ''"
        :button-text="confirming ? confirmations[confirming].button : ''"
        :danger="confirming ? confirmations[confirming].danger : false"
        @update:open="(open) => !open && (confirming = null)"
        @confirm="confirmations[confirming].run(); confirming = null"
    />
</template>
