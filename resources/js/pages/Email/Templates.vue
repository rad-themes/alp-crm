<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Button, ConfirmationModal, Dropdown, DropdownItem, DropdownMenu, EmptyStateItem, EmptyStateMenu, Header,
    Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';
import { formatDate } from '../../components/dates.js';

defineProps({ templates: Array, createUrl: String, canEdit: Boolean });

const deleting = ref(null);

function destroy() {
    router.delete(deleting.value.destroy_url, { preserveScroll: true, onFinish: () => (deleting.value = null) });
}
</script>

<template>
    <Head :title="__('Email templates')" />

    <Header :title="__('Email templates')" icon="mail-chat-bubble-text">
        <Button v-if="canEdit && templates.length" :href="createUrl" :text="__('Create Template')" variant="primary" />
    </Header>

    <EmptyStateMenu v-if="!templates.length" :heading="__('Write it once, send it often')">
        <EmptyStateItem
            icon="mail-chat-bubble-text"
            :heading="__('Create a template')"
            :description="__('Canned replies for contact emails and campaigns, personalised with merge tags.')"
            :href="canEdit ? createUrl : null"
        />
    </EmptyStateMenu>

    <Table v-else>
        <TableColumns>
            <TableColumn>{{ __('Name') }}</TableColumn>
            <TableColumn>{{ __('Subject') }}</TableColumn>
            <TableColumn>{{ __('Updated') }}</TableColumn>
            <TableColumn />
        </TableColumns>
        <TableRows>
            <TableRow v-for="template in templates" :key="template.id">
                <TableCell>
                    <Link v-if="canEdit" :href="template.edit_url" class="title-index-field">{{ template.name }}</Link>
                    <span v-else>{{ template.name }}</span>
                </TableCell>
                <TableCell class="text-gray-600 dark:text-gray-400">{{ template.subject }}</TableCell>
                <TableCell>{{ formatDate(template.updated_at) }}</TableCell>
                <TableCell class="text-end">
                    <Dropdown v-if="canEdit">
                        <template #trigger>
                            <Button icon="dots" variant="ghost" size="sm" :aria-label="__('Actions')" />
                        </template>
                        <DropdownMenu>
                            <DropdownItem :text="__('Edit')" icon="edit" :href="template.edit_url" />
                            <DropdownItem :text="__('Delete')" icon="trash" variant="destructive" @click="deleting = template" />
                        </DropdownMenu>
                    </Dropdown>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>

    <ConfirmationModal
        :open="deleting !== null"
        :title="__('Delete template')"
        :body-text="deleting ? __('Delete “:name”? Emails already sent are not affected.', { name: deleting.name }) : ''"
        :button-text="__('Delete')"
        danger
        @update:open="(open) => !open && (deleting = null)"
        @confirm="destroy"
    />
</template>
