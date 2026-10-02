<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Avatar, Badge, Button, ConfirmationModal, Dropdown, DropdownItem, DropdownMenu, Header,
    Panel, Card, Tabs, TabList, TabTrigger, TabContent,
} from '@statamic/cms/ui';
import StatusBadge from '../../components/StatusBadge.vue';
import DetailList from '../../components/DetailList.vue';
import NotesPanel from '../../components/NotesPanel.vue';
import ActivityTimeline from '../../components/ActivityTimeline.vue';
import { formatDate, fromNow } from '../../components/dates.js';

const props = defineProps({
    contact: Object,
    details: Array,
    notes: Array,
    activities: Array,
    noteTypes: Object,
    urls: Object,
    canEdit: Boolean,
});

const tab = ref('notes');
const confirmingDelete = ref(false);

const overview = computed(() => [
    props.contact.email && { label: __('Email'), value: props.contact.email, href: `mailto:${props.contact.email}` },
    props.contact.phone && { label: __('Phone'), value: props.contact.phone, href: `tel:${props.contact.phone}` },
    props.contact.company && { label: __('Company'), value: props.contact.company.name, href: props.contact.company.url },
    props.contact.owner && { label: __('Owner'), value: props.contact.owner },
    props.contact.aliases.length && { label: __('Other emails'), value: props.contact.aliases.join(', ') },
    { label: __('Added'), value: formatDate(props.contact.created_at) },
    { label: __('Last contacted'), value: props.contact.last_contacted_at ? fromNow(props.contact.last_contacted_at) : __('Never') },
].filter(Boolean));

function destroy() {
    router.delete(props.urls.destroy);
}
</script>

<template>
    <Head :title="contact.name" />

    <Header>
        <template #title>
            <span class="flex items-center gap-3">
                <Avatar :user="{ name: contact.name, initials: contact.initials }" class="size-9" />
                {{ contact.name }}
                <StatusBadge :status="contact.status" :label="contact.status_label" />
            </span>
        </template>

        <Dropdown v-if="canEdit">
            <template #trigger>
                <Button icon="dots" variant="ghost" :aria-label="__('More')" />
            </template>
            <DropdownMenu>
                <DropdownItem :text="__('Delete')" icon="trash" variant="destructive" @click="confirmingDelete = true" />
            </DropdownMenu>
        </Dropdown>
        <Button v-if="canEdit" :href="urls.edit" :text="__('Edit')" variant="primary" />
    </Header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <Panel :heading="__('Overview')">
                <Card>
                    <DetailList :items="overview" />
                </Card>
            </Panel>

            <Panel v-if="details.length" :heading="__('Details')">
                <Card>
                    <DetailList :items="details" />
                </Card>
            </Panel>

            <Panel v-if="contact.tags.length" :heading="__('Tags')">
                <Card class="flex flex-wrap gap-1.5">
                    <Badge v-for="tag in contact.tags" :key="tag" :text="tag" />
                </Card>
            </Panel>
        </div>

        <div class="lg:col-span-2">
            <Tabs v-model="tab">
                <TabList class="mb-4">
                    <TabTrigger name="notes" :text="__('Notes & calls')" />
                    <TabTrigger name="activity" :text="__('Activity')" />
                </TabList>
                <TabContent name="notes">
                    <NotesPanel :notes="notes" :note-types="noteTypes" :store-url="urls.notes" :can-edit="canEdit" />
                </TabContent>
                <TabContent name="activity">
                    <Card>
                        <ActivityTimeline :activities="activities" />
                    </Card>
                </TabContent>
            </Tabs>
        </div>
    </div>

    <ConfirmationModal
        v-model:open="confirmingDelete"
        :title="__('Delete contact')"
        :body-text="__('This permanently deletes :name and their notes and activity.', { name: contact.name })"
        :button-text="__('Delete')"
        danger
        @confirm="destroy"
    />
</template>
