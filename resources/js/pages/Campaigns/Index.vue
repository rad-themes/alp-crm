<script setup>
import { Head, Link } from '@statamic/cms/inertia';
import {
    Badge, Button, EmptyStateItem, EmptyStateMenu, Header,
    Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';
import { formatDateTime } from '../../components/dates.js';
import { campaignStatus } from '../../components/campaigns.js';

defineProps({ campaigns: Array, createUrl: String, canEdit: Boolean });
</script>

<template>
    <Head :title="__('Campaigns')" />

    <Header :title="__('Campaigns')" icon="mail-send-email-attachment-document">
        <Button v-if="canEdit && campaigns.length" :href="createUrl" :text="__('Create Campaign')" variant="primary" />
    </Header>

    <EmptyStateMenu v-if="!campaigns.length" :heading="__('Email your contacts')">
        <EmptyStateItem
            icon="mail-send-email-attachment-document"
            :heading="__('Create a campaign')"
            :description="__('Newsletters and announcements to a segment, with open and click tracking and unsubscribe links.')"
            :href="canEdit ? createUrl : null"
        />
    </EmptyStateMenu>

    <Table v-else>
        <TableColumns>
            <TableColumn>{{ __('Campaign') }}</TableColumn>
            <TableColumn>{{ __('Audience') }}</TableColumn>
            <TableColumn>{{ __('Status') }}</TableColumn>
            <TableColumn>{{ __('Sent') }}</TableColumn>
            <TableColumn>{{ __('Opens') }}</TableColumn>
            <TableColumn>{{ __('Clicks') }}</TableColumn>
            <TableColumn>{{ __('Date') }}</TableColumn>
        </TableColumns>
        <TableRows>
            <TableRow v-for="campaign in campaigns" :key="campaign.id">
                <TableCell>
                    <Link :href="campaign.url" class="title-index-field">{{ campaign.name }}</Link>
                    <div class="text-xs text-gray-500">{{ campaign.subject }}</div>
                </TableCell>
                <TableCell>{{ campaign.segment }}</TableCell>
                <TableCell><Badge v-bind="campaignStatus(campaign.status)" size="sm" /></TableCell>
                <TableCell>{{ campaign.stats ? campaign.stats.sent : '—' }}</TableCell>
                <TableCell>{{ campaign.stats ? `${campaign.stats.open_rate}%` : '—' }}</TableCell>
                <TableCell>{{ campaign.stats ? `${campaign.stats.click_rate}%` : '—' }}</TableCell>
                <TableCell>{{ formatDateTime(campaign.date) }}</TableCell>
            </TableRow>
        </TableRows>
    </Table>
</template>
