<script setup>
import { ref } from 'vue';
import { router, useForm } from '@statamic/cms/inertia';
import { Badge, Button, Card, Checkbox, ConfirmationModal, Description, Switch, Text } from '@statamic/cms/ui';
import { fromNow } from './dates.js';

const props = defineProps({ files: Array, storeUrl: String, portalInstalled: Boolean, canEdit: Boolean });

const form = useForm({ files: [], portal: false });
const input = ref(null);
const deleting = ref(null);

function upload(event) {
    form.files = [...event.target.files];
    if (!form.files.length) return;
    form.post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.reset('files');
            input.value.value = '';
        },
    });
}

function setPortal(file, portal) {
    router.patch(file.update_url, { portal }, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4">
        <Card v-if="canEdit" class="flex flex-wrap items-center gap-4">
            <input ref="input" type="file" multiple class="hidden" @change="upload" />
            <Button icon="upload" :text="__('Upload files')" :loading="form.processing" @click="input.click()" />
            <Checkbox v-if="portalInstalled" v-model="form.portal" :label="__('Share new files in the client portal')" />
            <Text v-if="form.errors.files || form.errors['files.0']" size="sm" class="text-red-600" :text="form.errors.files || form.errors['files.0']" />
        </Card>

        <Description v-if="!files.length" :text="__('No files yet. Contracts, briefs, signed quotes…')" class="py-4 text-center" />

        <Card v-else>
            <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <li v-for="file in files" :key="file.id" class="flex flex-wrap items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                    <a :href="file.download_url" class="min-w-0 flex-1 truncate font-medium hover:underline">{{ file.name }}</a>
                    <Text size="sm" variant="subtle" :text="[file.size, file.author, fromNow(file.created_at)].filter(Boolean).join(' · ')" />
                    <template v-if="portalInstalled">
                        <Switch v-if="canEdit" :model-value="file.portal" size="sm" :aria-label="__('Show in client portal')" @update:model-value="(on) => setPortal(file, on)" />
                        <Badge v-if="file.portal" :text="__('In portal')" color="green" size="sm" />
                    </template>
                    <Button v-if="canEdit" icon="trash" variant="ghost" size="xs" :aria-label="__('Delete')" @click="deleting = file" />
                </li>
            </ul>
        </Card>

        <ConfirmationModal
            :open="deleting !== null"
            :title="__('Delete file')"
            :body-text="deleting ? __('Delete “:name”? This can’t be undone.', { name: deleting.name }) : ''"
            :button-text="__('Delete')"
            danger
            @update:open="(open) => !open && (deleting = null)"
            @confirm="router.delete(deleting.destroy_url, { preserveScroll: true, onFinish: () => (deleting = null) })"
        />
    </div>
</template>
