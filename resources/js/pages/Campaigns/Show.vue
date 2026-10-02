<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Badge, Button, Card, ConfirmationModal, Header, Panel, Text,
    Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';
import { campaignStatus } from '../../components/campaigns.js';
import { formatDateTime } from '../../components/dates.js';

const props = defineProps({ campaign: Object, stats: Object, recipients: Array, urls: Object, canEdit: Boolean });

const cards = [
    { label: __('Recipients'), value: props.stats.recipients },
    { label: __('Delivered'), value: props.stats.sent, hint: props.stats.failed ? __(':count failed', { count: props.stats.failed }) : null },
    { label: __('Opened'), value: `${props.stats.open_rate}%`, hint: __(':count contacts', { count: props.stats.opened }) },
    { label: __('Clicked'), value: `${props.stats.click_rate}%`, hint: __(':count contacts', { count: props.stats.clicked }) },
    { label: __('Unsubscribed'), value: props.stats.unsubscribed },
];

const recipientStatus = {
    queued: { text: __('Queued'), color: 'default' },
    sent: { text: __('Sent'), color: 'green' },
    failed: { text: __('Failed'), color: 'red' },
    unsubscribed: { text: __('Skipped'), color: 'default' },
};

const stopping = ref(false);
</script>

<template>
    <Head :title="campaign.name" />

    <Header :title="campaign.name" icon="mail-send-email-attachment-document">
        <template #title>
            <span class="flex items-center gap-3">
                {{ campaign.name }}
                <Badge v-bind="campaignStatus(campaign.status)" />
            </span>
        </template>
        <Button v-if="canEdit && campaign.status === 'sending'" :text="__('Stop sending')" @click="stopping = true" />
        <Button :href="urls.index" :text="__('All campaigns')" />
    </Header>

    <div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-4">
        <Card v-for="card in cards" :key="card.label">
            <Text size="sm" variant="subtle" :text="card.label" />
            <div class="mt-1 text-2xl font-semibold">{{ card.value }}</div>
            <Text v-if="card.hint !== null && card.hint !== undefined" size="xs" variant="subtle" :text="String(card.hint)" />
        </Card>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <Panel :heading="__('Recipients')" class="lg:col-span-3">
            <Table>
                <TableColumns>
                    <TableColumn>{{ __('Contact') }}</TableColumn>
                    <TableColumn>{{ __('Status') }}</TableColumn>
                    <TableColumn>{{ __('Opened') }}</TableColumn>
                    <TableColumn>{{ __('Clicks') }}</TableColumn>
                </TableColumns>
                <TableRows>
                    <TableRow v-for="recipient in recipients" :key="recipient.id">
                        <TableCell>
                            <Link v-if="recipient.url" :href="recipient.url" class="hover:underline">{{ recipient.name }}</Link>
                            <div class="text-xs text-gray-500">{{ recipient.email }}</div>
                        </TableCell>
                        <TableCell><Badge v-bind="recipientStatus[recipient.status]" size="sm" /></TableCell>
                        <TableCell>{{ recipient.opened ? __('Yes') : '—' }}</TableCell>
                        <TableCell>{{ recipient.clicks || '—' }}</TableCell>
                    </TableRow>
                </TableRows>
            </Table>
        </Panel>

        <Panel :heading="__('Email')" class="lg:col-span-2">
            <Card>
                <Text size="sm" variant="subtle" :text="[campaign.segment, campaign.started_at && formatDateTime(campaign.started_at)].filter(Boolean).join(' · ')" />
                <div class="mt-1 font-semibold">{{ campaign.subject }}</div>
                <div class="prose prose-sm mt-4 max-w-none border-t border-gray-100 pt-4 dark:prose-invert dark:border-gray-800" v-html="campaign.html" />
            </Card>
        </Panel>
    </div>

    <ConfirmationModal
        v-model:open="stopping"
        :title="__('Stop sending')"
        :body-text="__('Emails already sent can’t be recalled. The rest won’t be sent.')"
        :button-text="__('Stop sending')"
        danger
        @confirm="router.post(urls.cancel)"
    />
</template>
