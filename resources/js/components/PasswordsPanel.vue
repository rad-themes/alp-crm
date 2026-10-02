<script setup>
import { reactive, ref } from 'vue';
import { router, useForm } from '@statamic/cms/inertia';
import { Button, Card, ConfirmationModal, Description, Field, Input, Modal, Text, Textarea } from '@statamic/cms/ui';

const props = defineProps({ passwords: Array, storeUrl: String });

const revealed = reactive({});
const editing = ref(null);
const deleting = ref(null);
const form = useForm({ label: '', url: '', username: '', password: '', notes: '' });

async function reveal(item) {
    if (revealed[item.id]) {
        delete revealed[item.id];
        return;
    }
    const response = await fetch(item.reveal_url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': Statamic.$config.get('csrfToken') },
    });
    if (response.ok) revealed[item.id] = await response.json();
}

function copy(text) {
    navigator.clipboard
        ?.writeText(text ?? '')
        .then(() => Statamic.$toast.success(__('Copied')))
        .catch(() => Statamic.$toast.error(__('Couldn’t copy to the clipboard')));
}

async function copyPassword(item) {
    if (!revealed[item.id]) await reveal(item);
    copy(revealed[item.id]?.password);
}

function open(item = null) {
    editing.value = item ?? {};
    form.defaults({ label: item?.label ?? '', url: item?.url ?? '', username: item?.username ?? '', password: '', notes: revealed[item?.id]?.notes ?? '' });
    form.reset();
    form.clearErrors();
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => { if (editing.value.id) delete revealed[editing.value.id]; editing.value = null; } };
    editing.value.update_url ? form.patch(editing.value.update_url, options) : form.post(props.storeUrl, options);
}
</script>

<template>
    <div class="space-y-4">
        <Card class="flex flex-wrap items-center justify-between gap-3">
            <Description :text="__('Logins you keep for this client. Encrypted with your app key; each time one is revealed it’s logged in Activity.')" />
            <Button icon="plus" :text="__('Add password')" size="sm" @click="open()" />
        </Card>

        <Description v-if="!passwords.length" :text="__('No saved passwords.')" class="py-4 text-center" />

        <Card v-for="item in passwords" :key="item.id">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ item.label }}</div>
                    <a v-if="item.url && /^https?:\/\//i.test(item.url)" :href="item.url" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-600 hover:underline">{{ item.url }}</a>
                    <Text v-else-if="item.url" size="sm" variant="subtle" :text="item.url" />
                </div>
                <Button :text="revealed[item.id] ? __('Hide') : __('Reveal')" size="xs" icon="eye" @click="reveal(item)" />
                <Button :text="__('Copy password')" size="xs" icon="clipboard" @click="copyPassword(item)" />
                <Button :aria-label="__('Edit')" size="xs" icon="edit" variant="ghost" @click="open(item)" />
                <Button :aria-label="__('Delete')" size="xs" icon="trash" variant="ghost" @click="deleting = item" />
            </div>
            <dl class="mt-3 grid grid-cols-[7rem_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-gray-500">{{ __('Username') }}</dt>
                <dd class="flex items-center gap-2">
                    <span class="font-mono">{{ item.username || '—' }}</span>
                    <button v-if="item.username" type="button" class="text-xs text-blue-600 hover:underline" @click="copy(item.username)">{{ __('Copy') }}</button>
                </dd>
                <dt class="text-gray-500">{{ __('Password') }}</dt>
                <dd class="font-mono break-all">{{ revealed[item.id] ? revealed[item.id].password || '—' : '••••••••••' }}</dd>
                <template v-if="revealed[item.id]?.notes">
                    <dt class="text-gray-500">{{ __('Notes') }}</dt>
                    <dd class="whitespace-pre-line">{{ revealed[item.id].notes }}</dd>
                </template>
            </dl>
        </Card>

        <Modal :open="editing !== null" :title="editing?.id ? __('Edit password') : __('Add password')" @update:open="(value) => !value && (editing = null)">
            <form class="space-y-4" autocomplete="off" @submit.prevent="save">
                <Field :label="__('Label')" :error="form.errors.label"><Input v-model="form.label" :placeholder="__('e.g. Hosting control panel')" /></Field>
                <Field :label="__('URL')" :error="form.errors.url"><Input v-model="form.url" /></Field>
                <Field :label="__('Username')" :error="form.errors.username"><Input v-model="form.username" autocomplete="off" /></Field>
                <Field :label="__('Password')" :instructions="editing?.id ? __('Leave empty to keep the current password.') : null" :error="form.errors.password">
                    <Input v-model="form.password" type="password" autocomplete="new-password" viewable />
                </Field>
                <Field :label="__('Notes')" :error="form.errors.notes"><Textarea v-model="form.notes" :rows="3" elastic /></Field>
                <div class="flex justify-end gap-2">
                    <Button :text="__('Cancel')" @click="editing = null" />
                    <Button type="submit" variant="primary" :text="__('Save')" :loading="form.processing" />
                </div>
            </form>
        </Modal>

        <ConfirmationModal
            :open="deleting !== null"
            :title="__('Delete password')"
            :body-text="deleting ? __('Delete “:label”?', { label: deleting.label }) : ''"
            :button-text="__('Delete')"
            danger
            @update:open="(value) => !value && (deleting = null)"
            @confirm="router.delete(deleting.destroy_url, { preserveScroll: true, onFinish: () => (deleting = null) })"
        />
    </div>
</template>
