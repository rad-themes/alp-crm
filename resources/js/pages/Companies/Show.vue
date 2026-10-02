<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Avatar, Badge, Button, ConfirmationModal, Description, Dropdown, DropdownItem, DropdownMenu, Header,
    Panel, Card, Tabs, TabList, TabTrigger, TabContent,
} from '@statamic/cms/ui';
import StatusBadge from '../../components/StatusBadge.vue';
import DetailList from '../../components/DetailList.vue';
import NotesPanel from '../../components/NotesPanel.vue';
import ActivityTimeline from '../../components/ActivityTimeline.vue';
import SalesPanel from '../../components/SalesPanel.vue';
import TaskList from '../../components/TaskList.vue';
import { formatDate } from '../../components/dates.js';

const props = defineProps({
    company: Object,
    contacts: Array,
    details: Array,
    notes: Array,
    sales: Object,
    tasks: Array,
    activities: Array,
    noteTypes: Object,
    urls: Object,
    canEdit: Boolean,
});

const tab = ref('contacts');
const confirmingDelete = ref(false);
const openTasks = computed(() => props.tasks.filter((task) => !task.done).length);

const overview = computed(() => [
    props.company.email && { label: __('Email'), value: props.company.email, href: `mailto:${props.company.email}` },
    props.company.phone && { label: __('Phone'), value: props.company.phone, href: `tel:${props.company.phone}` },
    props.company.website && { label: __('Website'), value: props.company.website, url: props.company.website },
    props.company.owner && { label: __('Owner'), value: props.company.owner },
    { label: __('Added'), value: formatDate(props.company.created_at) },
].filter(Boolean));

function destroy() {
    router.delete(props.urls.destroy);
}
</script>

<template>
    <Head :title="company.name" />

    <Header>
        <template #title>
            <span class="flex items-center gap-3">
                <Avatar :user="{ name: company.name, initials: company.initials }" class="size-9" />
                {{ company.name }}
                <StatusBadge :status="company.status" :label="company.status_label" />
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

            <Panel v-if="company.tags.length" :heading="__('Tags')">
                <Card class="flex flex-wrap gap-1.5">
                    <Badge v-for="tag in company.tags" :key="tag" :text="tag" />
                </Card>
            </Panel>
        </div>

        <div class="lg:col-span-2">
            <Tabs v-model="tab">
                <TabList class="mb-4">
                    <TabTrigger name="contacts" :text="__('Contacts') + ` (${contacts.length})`" />
                    <TabTrigger name="notes" :text="__('Notes & calls')" />
                    <TabTrigger name="tasks" :text="__('Tasks') + (openTasks ? ` (${openTasks})` : '')" />
                    <TabTrigger name="sales" :text="__('Sales')" />
                    <TabTrigger name="activity" :text="__('Activity')" />
                </TabList>
                <TabContent name="contacts">
                    <Card>
                        <Description v-if="!contacts.length" :text="__('No contacts at this company yet.')" class="py-4 text-center" />
                        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="contact in contacts" :key="contact.id" class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <Link :href="contact.url" class="font-medium hover:underline">{{ contact.name }}</Link>
                                    <div v-if="contact.email" class="truncate text-sm text-gray-500">{{ contact.email }}</div>
                                </div>
                                <Badge v-if="contact.status_label" :text="contact.status_label" size="sm" />
                            </li>
                        </ul>
                    </Card>
                </TabContent>
                <TabContent name="notes">
                    <NotesPanel :notes="notes" :note-types="noteTypes" :store-url="urls.notes" :can-edit="canEdit" />
                </TabContent>
                <TabContent name="tasks">
                    <Panel :heading="__('Tasks')">
                        <template v-if="canEdit" #header-actions>
                            <Button :href="urls.createTask" :text="__('New task')" size="sm" icon="plus" />
                        </template>
                        <Card>
                            <TaskList :tasks="tasks" :can-edit="canEdit" :show-contact="false" :empty="__('No tasks yet.')" />
                        </Card>
                    </Panel>
                </TabContent>
                <TabContent name="sales">
                    <SalesPanel :sales="sales" :can-edit="canEdit" />
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
        :title="__('Delete company')"
        :body-text="__('This permanently deletes :name. Its contacts are kept.', { name: company.name })"
        :button-text="__('Delete')"
        danger
        @confirm="destroy"
    />
</template>
