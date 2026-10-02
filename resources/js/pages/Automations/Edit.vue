<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@statamic/cms/inertia';
import {
    Badge, Button, Card, ConfirmationModal, Description, Field, Header, Input, Panel, Select, Switch, Text, Textarea, ToggleGroup, ToggleItem,
    Table, TableCell, TableColumn, TableColumns, TableRow, TableRows,
} from '@statamic/cms/ui';
import RuleBuilder from '../../components/RuleBuilder.vue';
import { formatDateTime } from '../../components/dates.js';

const props = defineProps({
    title: String,
    values: Object,
    triggers: Array,
    actionTypes: Array,
    templates: Array,
    forms: Array,
    users: Array,
    fields: Array,
    statuses: Array,
    tags: Array,
    runs: Array,
    submitUrl: String,
    submitMethod: String,
    destroyUrl: String,
    cancelUrl: String,
});

const form = useForm({
    ...props.values,
    trigger_options: { tag: '', status: null, form: null, ...props.values.trigger_options },
});
const filtering = ref(form.conditions.length > 0);

const units = [
    { value: 'minutes', label: __('minutes') },
    { value: 'hours', label: __('hours') },
    { value: 'days', label: __('days') },
];
const taskTypes = [
    { value: 'task', label: __('Task') },
    { value: 'call', label: __('Call') },
    { value: 'meeting', label: __('Meeting') },
    { value: 'email', label: __('Email') },
    { value: 'deadline', label: __('Deadline') },
];
const priorities = [
    { value: 'low', label: __('Low') },
    { value: 'normal', label: __('Normal') },
    { value: 'high', label: __('High') },
];
const statusColors = { done: 'green', pending: 'blue', running: 'blue', skipped: 'default', failed: 'red' };

function addAction() {
    form.actions.push({ type: 'add_tag', tags: '', delay: 0, delay_unit: 'minutes' });
}

function move(index, by) {
    const [action] = form.actions.splice(index, 1);
    form.actions.splice(index + by, 0, action);
}

const actionError = (index) => Object.entries(form.errors).filter(([key]) => key.startsWith(`actions.${index}.`)).map(([, message]) => message)[0];

function submit() {
    form.transform((data) => ({ ...data, conditions: filtering.value ? data.conditions : [] })).submit(props.submitMethod, props.submitUrl);
}

const deleting = ref(false);
</script>

<template>
    <Head :title="title" />

    <form @submit.prevent="submit">
        <Header :title="title" icon="flash-bolt-lightning">
            <Button v-if="destroyUrl" icon="trash" variant="ghost" :aria-label="__('Delete')" @click="deleting = true" />
            <Button :href="cancelUrl" :text="__('Cancel')" />
            <Button type="submit" variant="primary" :text="__('Save')" :loading="form.processing" />
        </Header>

        <div class="mx-auto max-w-3xl space-y-6">
            <Panel>
                <Card class="flex flex-wrap items-end gap-4">
                    <Field :label="__('Name')" :error="form.errors.name" class="min-w-64 flex-1">
                        <Input v-model="form.name" />
                    </Field>
                    <div class="flex items-center gap-2 pb-2">
                        <Switch v-model="form.active" size="sm" :aria-label="__('Active')" />
                        <Text size="sm" :text="__('Active')" />
                    </div>
                </Card>
            </Panel>

            <Panel :heading="__('When')">
                <Card class="space-y-4">
                    <Field :error="form.errors.trigger">
                        <Select v-model="form.trigger" :options="triggers" searchable />
                    </Field>
                    <Field v-if="form.trigger === 'contact.tagged'" :label="__('Only for the tag')" :instructions="__('Leave empty for any tag.')">
                        <Input v-model="form.trigger_options.tag" />
                    </Field>
                    <Field v-if="form.trigger === 'contact.status_changed'" :label="__('Only when the status changes to')">
                        <Select v-model="form.trigger_options.status" :options="statuses" clearable :placeholder="__('Any status')" />
                    </Field>
                    <Field v-if="form.trigger === 'form.submitted'" :label="__('Only for the form')" :instructions="__('Forms must be captured in CRM settings → Lead capture.')">
                        <Select v-model="form.trigger_options.form" :options="forms" clearable :placeholder="__('Any captured form')" />
                    </Field>
                </Card>
            </Panel>

            <Panel :heading="__('Only for contacts who…')">
                <template #header-actions>
                    <Switch v-model="filtering" size="sm" :aria-label="__('Filter contacts')" />
                </template>
                <Card v-if="filtering" class="space-y-3">
                    <ToggleGroup v-model="form.match" size="sm">
                        <ToggleItem value="all" :label="__('Match all')" />
                        <ToggleItem value="any" :label="__('Match any')" />
                    </ToggleGroup>
                    <RuleBuilder
                        v-model:conditions="form.conditions"
                        :match="form.match"
                        :fields="fields"
                        :statuses="statuses"
                        :tags="tags"
                        :empty-text="__('No rules yet.')"
                        :has-errors="Object.keys(form.errors).some((key) => key.startsWith('conditions'))"
                    />
                </Card>
                <Card v-else>
                    <Description :text="__('Runs for everyone. Switch on to only run for contacts matching rules, like a segment.')" />
                </Card>
            </Panel>

            <Panel :heading="__('Then')">
                <div class="space-y-3">
                    <Card v-for="(action, index) in form.actions" :key="index" class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :text="String(index + 1)" size="sm" />
                            <Text size="sm" variant="subtle" :text="index ? __('then wait') : __('Wait')" />
                            <div class="w-20"><Input v-model="action.delay" type="number" min="0" /></div>
                            <Select v-model="action.delay_unit" :options="units" class="w-28" />
                            <Text size="sm" variant="subtle" :text="__('and')" />
                            <Select v-model="action.type" :options="actionTypes" class="w-60" />
                            <div class="flex-1" />
                            <Button v-if="index" icon="chevron-up" variant="ghost" size="sm" :aria-label="__('Move up')" @click="move(index, -1)" />
                            <Button v-if="index < form.actions.length - 1" icon="chevron-down" variant="ghost" size="sm" :aria-label="__('Move down')" @click="move(index, 1)" />
                            <Button v-if="form.actions.length > 1" icon="x" variant="ghost" size="sm" :aria-label="__('Remove')" @click="form.actions.splice(index, 1)" />
                        </div>

                        <Field v-if="['add_tag', 'remove_tag'].includes(action.type)" :label="__('Tags')" :instructions="__('Comma separated.')">
                            <Input v-model="action.tags" />
                        </Field>
                        <Field v-else-if="action.type === 'set_status'" :label="__('Status')">
                            <Select v-model="action.status" :options="statuses" />
                        </Field>
                        <Field v-else-if="action.type === 'send_email'" :label="__('Email template')" :instructions="templates.length ? __('Skipped for unsubscribed contacts.') : __('Create an email template first.')">
                            <Select v-model="action.template_id" :options="templates" />
                        </Field>
                        <div v-else-if="action.type === 'create_task'" class="grid gap-3 sm:grid-cols-2">
                            <Field :label="__('Title')" :instructions="__('Merge tags work, e.g. {{ name }}.')" class="sm:col-span-2">
                                <Input v-model="action.title" />
                            </Field>
                            <Field :label="__('Type')"><Select v-model="action.task_type" :options="taskTypes" /></Field>
                            <Field :label="__('Priority')"><Select v-model="action.priority" :options="priorities" /></Field>
                            <Field :label="__('Due in (days)')"><Input v-model="action.due_in_days" type="number" min="0" /></Field>
                            <Field :label="__('Assign to')"><Select v-model="action.assign_to" :options="users" clearable :placeholder="__('The contact’s owner')" /></Field>
                        </div>
                        <Field v-else-if="action.type === 'add_note'" :label="__('Note')">
                            <Textarea v-model="action.body" :rows="3" elastic />
                        </Field>
                        <div v-else-if="action.type === 'notify'" class="space-y-3">
                            <Field :label="__('Send to')" :instructions="__('Email addresses, comma separated.')"><Input v-model="action.to" /></Field>
                            <Field :label="__('Subject')"><Input v-model="action.subject" /></Field>
                            <Field :label="__('Message')"><Textarea v-model="action.message" :rows="3" elastic /></Field>
                        </div>
                        <Field v-else-if="action.type === 'webhook'" :label="__('URL')" :instructions="__('Receives the event and the contact as JSON.')">
                            <Input v-model="action.url" type="url" placeholder="https://" />
                        </Field>
                        <template v-else-if="action.type === 'send_sms'">
                            <Field :label="__('Message')" :instructions="__('Merge tags work. Sent to the contact’s phone number via Twilio.')">
                                <Textarea v-model="action.text" :rows="2" elastic />
                            </Field>
                        </template>
                        <Text v-if="actionError(index)" size="sm" class="text-red-600" :text="actionError(index)" />
                    </Card>
                    <Button :text="__('Add step')" icon="plus" size="sm" @click="addAction" />
                </div>
            </Panel>

            <Panel v-if="runs.length" :heading="__('Recent runs')">
                <Table>
                    <TableColumns>
                        <TableColumn>{{ __('Contact') }}</TableColumn>
                        <TableColumn>{{ __('Step') }}</TableColumn>
                        <TableColumn>{{ __('Result') }}</TableColumn>
                        <TableColumn>{{ __('When') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="run in runs" :key="run.id">
                            <TableCell>
                                <Link v-if="run.contact_url" :href="run.contact_url" class="hover:underline">{{ run.contact }}</Link>
                                <span v-else class="text-gray-400">—</span>
                            </TableCell>
                            <TableCell>{{ run.step }}</TableCell>
                            <TableCell>
                                <Badge :text="run.status" :color="statusColors[run.status]" size="sm" />
                                <span class="ms-2 text-sm">{{ run.result }}</span>
                            </TableCell>
                            <TableCell class="text-sm">{{ formatDateTime(run.run_at) }}</TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </Panel>
        </div>
    </form>

    <ConfirmationModal
        v-model:open="deleting"
        :title="__('Delete automation')"
        :body-text="__('Pending steps will not run.')"
        :button-text="__('Delete')"
        danger
        @confirm="router.delete(destroyUrl)"
    />
</template>
