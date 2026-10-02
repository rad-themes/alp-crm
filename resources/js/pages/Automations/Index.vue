<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Badge, Button, EmptyStateItem, EmptyStateMenu, Header, Heading, Switch,
    Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';
import { fromNow } from '../../components/dates.js';

defineProps({ automations: Array, recipes: Array, createUrl: String, canEdit: Boolean });
</script>

<template>
    <Head :title="__('Automations')" />

    <Header :title="__('Automations')" icon="flash-bolt-lightning">
        <Button v-if="canEdit && automations.length" :href="createUrl" :text="__('Create Automation')" variant="primary" />
    </Header>

    <Table v-if="automations.length" class="mb-8">
        <TableColumns>
            <TableColumn>{{ __('Automation') }}</TableColumn>
            <TableColumn>{{ __('When') }}</TableColumn>
            <TableColumn>{{ __('Then') }}</TableColumn>
            <TableColumn>{{ __('Runs') }}</TableColumn>
            <TableColumn>{{ __('Active') }}</TableColumn>
        </TableColumns>
        <TableRows>
            <TableRow v-for="automation in automations" :key="automation.id">
                <TableCell>
                    <Link :href="automation.edit_url" class="title-index-field">{{ automation.name }}</Link>
                </TableCell>
                <TableCell>{{ automation.trigger }}</TableCell>
                <TableCell>
                    <div class="flex flex-wrap gap-1">
                        <Badge v-for="(action, i) in automation.actions" :key="i" :text="action" size="sm" />
                    </div>
                </TableCell>
                <TableCell>
                    {{ automation.runs_count }}
                    <span v-if="automation.last_run_at" class="text-xs text-gray-500">· {{ fromNow(automation.last_run_at) }}</span>
                </TableCell>
                <TableCell>
                    <Switch :model-value="automation.active" size="sm" :disabled="!canEdit" :aria-label="__('Active')" @update:model-value="router.post(automation.toggle_url, {}, { preserveScroll: true })" />
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>

    <template v-if="canEdit">
        <Heading v-if="automations.length" size="lg" class="mb-3" :text="__('Start from a recipe')" />
        <EmptyStateMenu :heading="automations.length ? null : __('Put your CRM on autopilot')">
            <EmptyStateItem v-if="!automations.length" icon="flash-bolt-lightning" :heading="__('Start from scratch')" :description="__('When something happens, check who it’s for, then tag, email, create tasks or call a webhook.')" :href="createUrl" />
            <EmptyStateItem v-for="recipe in recipes" :key="recipe.url" :icon="recipe.icon" :heading="recipe.label" :description="recipe.description" :href="recipe.url" />
        </EmptyStateMenu>
    </template>
</template>
