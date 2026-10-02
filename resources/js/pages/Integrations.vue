<script setup>
import { ref } from 'vue';
import { Head, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Description, Header, Heading, Panel, Text } from '@statamic/cms/ui';
import { fromNow } from '../components/dates.js';

defineProps({ groups: Array, settingsUrl: String });

const busy = ref(null);

function post(url, key) {
    busy.value = key;
    router.post(url, {}, { preserveScroll: true, onFinish: () => (busy.value = null) });
}

const cp = (path) => `${Statamic.$config.get('cpUrl')}/crm/integrations/${path}`;
</script>

<template>
    <Head :title="__('Integrations')" />

    <Header :title="__('Integrations')" icon="link">
        <Button :href="settingsUrl" :text="__('Settings')" icon="cog" />
    </Header>

    <div class="space-y-8">
        <section v-for="group in groups" :key="group.heading">
            <Heading size="lg" class="mb-3" :text="group.heading" />
            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="item in group.items" :key="item.key" class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold">{{ item.name }}</div>
                            <Description :text="item.description" />
                        </div>
                        <Badge v-if="item.key !== 'lists'" :text="item.configured ? (item.connectable && !item.connected ? __('Not connected') : __('Active')) : __('Not set up')" :color="item.configured && (!item.connectable || item.connected) ? 'green' : 'default'" size="sm" />
                    </div>
                    <Text v-if="item.note" size="xs" variant="subtle" class="break-all" :text="item.note" />
                    <Text v-if="item.last" size="xs" variant="subtle" :text="__('Last import :when', { when: fromNow(item.last) })" />
                    <div class="mt-auto flex flex-wrap items-center gap-2">
                        <template v-if="item.connectable">
                            <Button v-if="!item.connected" :href="cp(`${item.key}/connect`)" :text="__('Connect')" size="sm" variant="primary" />
                            <Button v-else :text="__('Disconnect')" size="sm" :loading="busy === `${item.key}-off`" @click="post(cp(`${item.key}/disconnect`), `${item.key}-off`)" />
                        </template>
                        <Button v-if="item.sync" :text="__('Sync now')" icon="sync" size="sm" :loading="busy === item.key" @click="post(cp(`${item.key}/sync`), item.key)" />
                        <Text v-if="item.syncing" size="xs" variant="subtle" :text="__('Syncs every hour')" />
                        <Button v-if="!item.configured && item.key !== 'lists'" :href="settingsUrl" :text="__('Set up')" size="sm" variant="ghost" />
                    </div>
                </Card>
            </div>
        </section>
    </div>
</template>
