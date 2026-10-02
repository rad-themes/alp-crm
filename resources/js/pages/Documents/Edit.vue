<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@statamic/cms/inertia';
import {
    Button, Card, Combobox, Description, Field, Header, Input, Panel, Select, Text, Textarea,
} from '@statamic/cms/ui';
import { calculateTotals, formatMoney } from '../../components/money.js';

const props = defineProps({
    type: String,
    title: String,
    values: Object,
    contact: Object,
    currencies: Array,
    taxRates: Array,
    pricesIncludeTax: Boolean,
    contactSearchUrl: String,
    submitUrl: String,
    submitMethod: String,
    cancelUrl: String,
    secondDateLabel: String,
});

const form = useForm({
    ...props.values,
    items: props.values.items.map((item) => ({ ...item, tax_key: taxKey(item) })),
});

// --- Client search ---------------------------------------------------------
const contactOptions = ref(props.contact ? [props.contact] : []);
let searchTimer = null;

function searchContacts(query) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        const response = await fetch(`${props.contactSearchUrl}?q=${encodeURIComponent(query ?? '')}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const results = response.ok ? await response.json() : [];
        const selected = contactOptions.value.find((option) => option.value === form.contact_id);
        contactOptions.value = selected && !results.some((r) => r.value === selected.value) ? [selected, ...results] : results;
    }, 200);
}

searchContacts('');

// --- Taxes -----------------------------------------------------------------
const taxOptions = computed(() => [
    { value: 'none', label: __('No tax') },
    ...props.taxRates.map((rate, index) => ({ value: String(index), label: `${rate.name} (${rate.rate}%)` })),
]);

function taxKey(item) {
    if (!item.tax_rate) return 'none';
    const index = props.taxRates.findIndex((rate) => rate.name === item.tax_name && Number(rate.rate) === Number(item.tax_rate));
    return index === -1 ? 'none' : String(index);
}

function setTax(item, key) {
    item.tax_key = key;
    const rate = props.taxRates[Number(key)];
    item.tax_name = key === 'none' || !rate ? null : rate.name;
    item.tax_rate = key === 'none' || !rate ? 0 : Number(rate.rate);
}

// --- Lines & totals --------------------------------------------------------
function addLine() {
    form.items.push({ description: '', quantity: 1, unit_price: 0, tax_name: null, tax_rate: 0, tax_key: 'none' });
}

function removeLine(index) {
    form.items.splice(index, 1);
    if (!form.items.length) addLine();
}

const totals = computed(() => calculateTotals(form.items, form.discount, props.pricesIncludeTax));
const money = (amount) => formatMoney(amount, form.currency || 'USD');

function submit() {
    form.transform((data) => ({
        ...data,
        items: data.items.map(({ tax_key, ...item }) => item),
    }))[props.submitMethod](props.submitUrl, { preserveScroll: true });
}

const lineError = (index, key) => form.errors[`items.${index}.${key}`];
</script>

<template>
    <Head :title="title" />

    <form @submit.prevent="submit">
        <Header :title="title" :icon="type === 'invoice' ? 'money-cashier-price-tag' : 'file-content-list'">
            <Button :href="cancelUrl" :text="__('Cancel')" variant="ghost" />
            <Button type="submit" :text="__('Save')" variant="primary" :loading="form.processing" />
        </Header>

        <div class="space-y-6">
            <Panel :heading="__('Details')">
                <Card class="grid gap-5 md:grid-cols-2">
                    <Field :label="__('Client')" :error="form.errors.contact_id" class="md:col-span-2">
                        <Combobox
                            v-model="form.contact_id"
                            :options="contactOptions"
                            :placeholder="__('Search contacts…')"
                            searchable
                            ignore-filter
                            clearable
                            @search="searchContacts"
                        />
                    </Field>
                    <Field :label="__('Title')" :error="form.errors.title" class="md:col-span-2">
                        <Input v-model="form.title" :placeholder="type === 'invoice' ? __('e.g. Website build — phase 1') : __('e.g. Website redesign proposal')" />
                    </Field>
                    <Field :label="__('Issue date')" :error="form.errors.issue_date">
                        <Input v-model="form.issue_date" type="date" />
                    </Field>
                    <Field :label="secondDateLabel" :error="form.errors.second_date">
                        <Input v-model="form.second_date" type="date" />
                    </Field>
                    <Field :label="__('Currency')" :error="form.errors.currency">
                        <Combobox v-model="form.currency" :options="currencies" searchable />
                    </Field>
                </Card>
            </Panel>

            <Panel :heading="__('Items')">
                <Card class="space-y-3">
                    <div class="hidden grid-cols-12 gap-3 text-xs font-medium uppercase tracking-wide text-gray-500 md:grid">
                        <div class="col-span-5">{{ __('Description') }}</div>
                        <div class="col-span-1 text-end">{{ __('Qty') }}</div>
                        <div class="col-span-2 text-end">{{ __('Price') }}</div>
                        <div class="col-span-2">{{ __('Tax') }}</div>
                        <div class="col-span-2 text-end">{{ __('Amount') }}</div>
                    </div>

                    <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 items-start gap-3 border-b border-gray-100 pb-3 last:border-0 dark:border-gray-800">
                        <div class="col-span-12 md:col-span-5">
                            <Textarea v-model="item.description" :rows="1" elastic :placeholder="__('Description')" :aria-label="__('Description')" />
                            <Text v-if="lineError(index, 'description')" variant="danger" size="sm" :text="lineError(index, 'description')" />
                        </div>
                        <div class="col-span-4 md:col-span-1">
                            <Input v-model="item.quantity" type="number" step="any" min="0" :aria-label="__('Quantity')" input-class="text-end" />
                        </div>
                        <div class="col-span-8 md:col-span-2">
                            <Input v-model="item.unit_price" type="number" step="0.01" :aria-label="__('Price')" input-class="text-end" />
                        </div>
                        <div class="col-span-8 md:col-span-2">
                            <Select :model-value="item.tax_key" :options="taxOptions" :aria-label="__('Tax')" @update:model-value="(key) => setTax(item, key)" />
                        </div>
                        <div class="col-span-4 flex items-center justify-end gap-1 md:col-span-2">
                            <span class="py-2 text-sm font-medium tabular-nums">{{ money(totals.lines[index]) }}</span>
                            <Button icon="trash" variant="ghost" size="xs" :aria-label="__('Remove line')" @click="removeLine(index)" />
                        </div>
                    </div>

                    <Button icon="plus" :text="__('Add line')" size="sm" @click="addLine" />
                    <Description v-if="!taxRates.length" :text="__('Add tax rates in CRM → Settings to charge tax.')" />
                </Card>
            </Panel>

            <div class="grid gap-6 md:grid-cols-2">
                <Panel :heading="__('Notes & terms')">
                    <Card class="space-y-4">
                        <Field :label="__('Notes')" :instructions="__('Shown to the client.')" :error="form.errors.notes">
                            <Textarea v-model="form.notes" :rows="3" elastic />
                        </Field>
                        <Field :label="__('Terms')" :error="form.errors.terms">
                            <Textarea v-model="form.terms" :rows="3" elastic />
                        </Field>
                    </Card>
                </Panel>

                <Panel :heading="__('Totals')">
                    <Card>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ money(totals.subtotal) }}</dd></div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-gray-500">{{ __('Discount') }}</dt>
                                <dd class="w-32"><Input v-model="form.discount" type="number" step="0.01" min="0" size="sm" input-class="text-end" :aria-label="__('Discount')" /></dd>
                            </div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ pricesIncludeTax ? __('Tax (included)') : __('Tax') }}</dt><dd class="tabular-nums">{{ money(totals.tax) }}</dd></div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold dark:border-gray-700"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ money(totals.total) }}</dd></div>
                        </dl>
                    </Card>
                </Panel>
            </div>
        </div>
    </form>
</template>
