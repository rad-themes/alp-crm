<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@statamic/cms/inertia';
import {
    Button, ConfirmationModal, Description, Dropdown, DropdownItem, DropdownMenu, DropdownSeparator, EmptyStateItem, EmptyStateMenu,
    Field, Header, Input, Modal, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';

defineProps({ segments: Array, createUrl: String, canEdit: Boolean });

const deleting = ref(null);
const tagging = ref(null);
const tagForm = useForm({ tags: [] });
const tagInput = ref('');

function openTagger(segment) {
    tagging.value = segment;
    tagInput.value = '';
    tagForm.reset();
}

function applyTags() {
    tagForm
        .transform(() => ({ tags: tagInput.value.split(',').map((tag) => tag.trim()).filter(Boolean) }))
        .post(tagging.value.tag_url, { preserveScroll: true, onSuccess: () => (tagging.value = null) });
}

function destroy() {
    router.delete(deleting.value.destroy_url, { preserveScroll: true, onFinish: () => (deleting.value = null) });
}
</script>

<template>
    <Head :title="__('Segments')" />

    <Header :title="__('Segments')" icon="filter">
        <Button v-if="canEdit && segments.length" :href="createUrl" :text="__('Create Segment')" variant="primary" />
    </Header>

    <EmptyStateMenu v-if="!segments.length" :heading="__('Group contacts by rules')">
        <EmptyStateItem
            icon="filter"
            :heading="__('Create a segment')"
            :description="__('e.g. customers tagged VIP who haven’t been contacted in 90 days. Segments update automatically.')"
            :href="canEdit ? createUrl : null"
        />
    </EmptyStateMenu>

    <Table v-else>
        <TableColumns>
            <TableColumn>{{ __('Segment') }}</TableColumn>
            <TableColumn>{{ __('Rules') }}</TableColumn>
            <TableColumn>{{ __('Contacts') }}</TableColumn>
            <TableColumn />
        </TableColumns>
        <TableRows>
            <TableRow v-for="segment in segments" :key="segment.id">
                <TableCell>
                    <Link :href="segment.url" class="title-index-field">{{ segment.name }}</Link>
                </TableCell>
                <TableCell>{{ segment.rules }}</TableCell>
                <TableCell>
                    <Link :href="segment.url" class="hover:underline">{{ segment.contacts }}</Link>
                </TableCell>
                <TableCell class="text-end">
                    <Dropdown>
                        <template #trigger>
                            <Button icon="dots" variant="ghost" size="sm" :aria-label="__('Actions')" />
                        </template>
                        <DropdownMenu>
                            <DropdownItem :text="__('View contacts')" icon="users" :href="segment.url" />
                            <template v-if="canEdit">
                                <DropdownItem :text="__('Edit rules')" icon="edit" :href="segment.edit_url" />
                                <DropdownItem :text="__('Tag everyone…')" icon="add-tag" @click="openTagger(segment)" />
                                <DropdownItem :text="__('Email this segment')" icon="mail-send-email-attachment-document" :href="segment.campaign_url" />
                                <DropdownSeparator />
                                <DropdownItem :text="__('Delete')" icon="trash" variant="destructive" @click="deleting = segment" />
                            </template>
                        </DropdownMenu>
                    </Dropdown>
                </TableCell>
            </TableRow>
        </TableRows>
    </Table>

    <Modal :open="tagging !== null" :title="tagging ? __('Tag everyone in “:name”', { name: tagging.name }) : ''" @update:open="(open) => !open && (tagging = null)">
        <form class="space-y-4" @submit.prevent="applyTags">
            <Description :text="__('Adds tags to the :count contacts currently in this segment. Existing tags are kept.', { count: tagging?.contacts ?? 0 })" />
            <Field :label="__('Tags')" :instructions="__('Comma separated. New tags are created.')" :error="tagForm.errors.tags">
                <Input v-model="tagInput" autofocus />
            </Field>
            <div class="flex justify-end gap-2">
                <Button :text="__('Cancel')" @click="tagging = null" />
                <Button type="submit" variant="primary" :text="__('Add tags')" :loading="tagForm.processing" :disabled="!tagInput.trim()" />
            </div>
        </form>
    </Modal>

    <ConfirmationModal
        :open="deleting !== null"
        :title="__('Delete segment')"
        :body-text="deleting ? __('Delete “:name”? Contacts are not affected.', { name: deleting.name }) : ''"
        :button-text="__('Delete')"
        danger
        @update:open="(open) => !open && (deleting = null)"
        @confirm="destroy"
    />
</template>
