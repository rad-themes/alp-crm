<script setup>
import { computed } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { Avatar, Badge, Button, Card, Description, EmptyStateItem, EmptyStateMenu, Header, Heading, Panel, Text } from '@statamic/cms/ui';
import ActivityTimeline from '../components/ActivityTimeline.vue';
import TaskList from '../components/TaskList.vue';

const props = defineProps({
    crmName: String,
    stats: Array,
    statuses: Array,
    recentContacts: Array,
    activity: Array,
    myTasks: Array,
    urls: Object,
    canEdit: Boolean,
});

const isEmpty = computed(() => props.stats[0].value === 0 && props.stats[1].value === 0);
const statusTotal = computed(() => Math.max(1, props.statuses.reduce((sum, status) => sum + status.total, 0)));
</script>

<template>
    <Head :title="crmName" />

    <Header :title="crmName" icon="users">
        <template v-if="canEdit">
            <Button :href="urls.createCompany" :text="__('Create Company')" />
            <Button :href="urls.createContact" :text="__('Create Contact')" variant="primary" />
        </template>
    </Header>

    <EmptyStateMenu v-if="isEmpty && canEdit" :heading="__('Get started with your CRM')">
        <EmptyStateItem icon="users" :heading="__('Add a contact')" :description="__('Leads, customers and anyone else you work with.')" :href="urls.createContact" />
        <EmptyStateItem icon="building-generic" :heading="__('Add a company')" :description="__('Group contacts by the business they work for.')" :href="urls.createCompany" />
    </EmptyStateMenu>

    <template v-else>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <component :is="stat.url ? Link : 'div'" v-for="stat in stats" :key="stat.label" :href="stat.url">
                <Card class="h-full">
                    <Description :text="stat.label" />
                    <Heading size="xl" :text="String(stat.value)" class="mt-1" />
                </Card>
            </component>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Panel :heading="__('My tasks this week')">
                    <template #header-actions>
                        <Button :href="urls.tasks" :text="__('All tasks')" size="sm" variant="ghost" />
                    </template>
                    <Card>
                        <TaskList :tasks="myTasks" :can-edit="canEdit" :empty="__('Nothing due. Nice!')" />
                    </Card>
                </Panel>

                <Panel :heading="__('Recent contacts')">
                    <Card>
                        <Description v-if="!recentContacts.length" :text="__('No contacts yet.')" />
                        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                            <li v-for="contact in recentContacts" :key="contact.id" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                                <Avatar :user="{ name: contact.name, initials: contact.initials }" class="size-8" />
                                <div class="min-w-0 flex-1">
                                    <Link :href="contact.url" class="block font-medium hover:underline">{{ contact.name }}</Link>
                                    <Text v-if="contact.company" as="div" size="sm" variant="subtle" :text="contact.company" />
                                </div>
                                <Badge v-if="contact.status_label" :text="contact.status_label" size="sm" />
                            </li>
                        </ul>
                    </Card>
                </Panel>

                <Panel :heading="__('Contacts by status')">
                    <Card class="space-y-3">
                        <div v-for="status in statuses" :key="status.value">
                            <div class="mb-1 flex justify-between text-sm">
                                <span>{{ status.label }}</span>
                                <span class="text-gray-500">{{ status.total }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded-full bg-blue-500" :style="{ width: `${(status.total / statusTotal) * 100}%` }" />
                            </div>
                        </div>
                    </Card>
                </Panel>
            </div>

            <Panel :heading="__('Latest activity')">
                <Card>
                    <ActivityTimeline :activities="activity">
                        <template #subject="{ activity: item }">
                            <Link :href="item.url" class="font-medium hover:underline">{{ item.subject }}</Link>
                        </template>
                    </ActivityTimeline>
                </Card>
            </Panel>
        </div>
    </template>
</template>
