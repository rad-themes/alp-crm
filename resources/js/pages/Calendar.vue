<script setup>
import { computed } from 'vue';
import { Head, Link } from '@statamic/cms/inertia';
import { Button, Header, Switch } from '@statamic/cms/ui';
import { router } from '@statamic/cms/inertia';

const props = defineProps({
    month: String,
    monthLabel: String,
    from: String,
    to: String,
    today: String,
    events: Object,
    urls: Object,
    mine: Boolean,
    canEdit: Boolean,
});

const toIso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

const days = computed(() => {
    const result = [];
    const cursor = new Date(`${props.from}T00:00:00`);
    const end = new Date(`${props.to}T00:00:00`);

    while (cursor <= end) {
        const iso = toIso(cursor);
        result.push({
            iso,
            day: cursor.getDate(),
            inMonth: iso.slice(0, 7) === props.month,
            isToday: iso === props.today,
            events: props.events[iso] ?? [],
        });
        cursor.setDate(cursor.getDate() + 1);
    }

    return result;
});

const weekdays = computed(() => days.value.slice(0, 7).map((day) => new Date(`${day.iso}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short' })));

const time = (event) => (event.starts_at && !event.all_day ? new Date(event.starts_at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) : '');

function eventClass(event) {
    if (event.kind === 'invoice') return event.overdue ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    if (event.done) return 'bg-gray-100 text-gray-400 line-through dark:bg-gray-800';
    if (event.overdue) return 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300';
    return 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200';
}
</script>

<template>
    <Head :title="__('Calendar')" />

    <Header :title="monthLabel" icon="calendar">
        <label class="flex items-center gap-2 text-sm">
            <Switch :model-value="mine" @update:model-value="router.get(urls.toggleMine)" />
            {{ __('Only mine') }}
        </label>
        <Button :href="urls.today" :text="__('Today')" />
        <Button :href="urls.previous" icon="chevron-left" :aria-label="__('Previous month')" />
        <Button :href="urls.next" icon="chevron-right" :aria-label="__('Next month')" />
        <Button v-if="canEdit" :href="urls.create" :text="__('Create Task')" variant="primary" />
    </Header>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800">
            <div v-for="weekday in weekdays" :key="weekday" class="py-2">{{ weekday }}</div>
        </div>
        <div class="grid grid-cols-7">
            <div
                v-for="day in days"
                :key="day.iso"
                class="group min-h-28 border-b border-e border-gray-100 p-1.5 text-sm dark:border-gray-800 [&:nth-child(7n)]:border-e-0"
                :class="{ 'bg-gray-50/60 dark:bg-gray-950': !day.inMonth }"
            >
                <div class="mb-1 flex items-center justify-between">
                    <span
                        class="flex size-6 items-center justify-center rounded-full text-xs"
                        :class="day.isToday ? 'bg-blue-600 font-semibold text-white' : day.inMonth ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400'"
                    >{{ day.day }}</span>
                    <Link
                        v-if="canEdit"
                        :href="`${urls.create}?date=${day.iso}`"
                        class="hidden size-5 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700 group-hover:flex dark:hover:bg-gray-800"
                        :aria-label="__('Add task on :date', { date: day.iso })"
                    >+</Link>
                </div>
                <ul class="space-y-1">
                    <li v-for="event in day.events" :key="event.id">
                        <Link :href="event.edit_url" class="block truncate rounded px-1.5 py-0.5 text-xs" :class="eventClass(event)" :title="event.title">
                            <span v-if="time(event)" class="font-medium">{{ time(event) }}</span>
                            {{ event.title }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
