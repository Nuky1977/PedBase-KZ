<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index, store, update } from '@/routes/education-organizations';

type Reference = { code: string; name: string };
type Fields = {
    name: string;
    short_name: string | null;
    bin: string;
    type_code: string;
    region_code: string;
    area_code: string;
    locality: string;
    status: string;
};

const props = defineProps<{
    organization: (Fields & { id: number }) | null;
    identityEditable?: boolean;
    types: Reference[];
    regions: (Reference & { id: number })[];
    areas: (Reference & { region_id: number })[];
}>();

const title = computed(() =>
    props.organization ? 'Ұйымды өңдеу' : 'Ұйым қосу',
);
const form = useForm<Fields>({
    name: props.organization?.name ?? '',
    short_name: props.organization?.short_name ?? '',
    bin: props.organization?.bin ?? '',
    type_code: props.organization?.type_code ?? '',
    region_code: props.organization?.region_code ?? '',
    area_code: props.organization?.area_code ?? '',
    locality: props.organization?.locality ?? '',
    status: props.organization?.status ?? 'active',
});
const areas = computed(() => {
    const region = props.regions.find((item) => item.code === form.region_code);

    return props.areas.filter((item) => item.region_id === region?.id);
});
const fields = [
    ['name', 'Атауы'],
    ['short_name', 'Қысқаша атауы'],
    ['bin', 'БСН'],
    ['locality', 'Елді мекен'],
] as const;

function submit() {
    if (props.organization) {
        form.put(update.url(props.organization.id));
    } else {
        form.post(store.url());
    }
}
</script>

<template>
    <section class="max-w-3xl space-y-5 p-4 md:p-6">
        <Head :title="title" />
        <Link :href="index()" class="text-sm underline">Ұйымдар каталогы</Link>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            {{ title }}
        </h1>
        <p
            v-if="!types.length || !regions.length || !props.areas.length"
            class="text-sm"
            role="status"
        >
            Ұйымды тіркеу үшін ұйым түрлері мен ресми аумақ анықтамалығын
            толтыру қажет.
        </p>
        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <label
                v-for="[key, label] in fields"
                :key="key"
                class="grid gap-1 text-sm"
            >
                {{ label }}
                <input
                    v-model="form[key]"
                    :required="key !== 'short_name'"
                    :readonly="key === 'bin' && identityEditable === false"
                    :maxlength="key === 'bin' ? 12 : 255"
                    :inputmode="key === 'bin' ? 'numeric' : undefined"
                    :pattern="key === 'bin' ? '[0-9]{12}' : undefined"
                    class="rounded-md border bg-background p-2"
                />
                <span v-if="form.errors[key]" class="text-destructive">{{
                    form.errors[key]
                }}</span>
            </label>
            <label class="grid gap-1 text-sm">
                Ұйым түрі
                <select
                    v-model="form.type_code"
                    :disabled="identityEditable === false"
                    required
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">Түрін таңдаңыз</option>
                    <option
                        v-for="item in types"
                        :key="item.code"
                        :value="item.code"
                    >
                        {{ item.name }}
                    </option>
                </select>
                <span v-if="form.errors.type_code" class="text-destructive">{{
                    form.errors.type_code
                }}</span>
            </label>
            <label class="grid gap-1 text-sm">
                Облыс
                <select
                    v-model="form.region_code"
                    :disabled="identityEditable === false"
                    required
                    class="rounded-md border bg-background p-2"
                    @change="form.area_code = ''"
                >
                    <option value="">Облысты таңдаңыз</option>
                    <option
                        v-for="item in regions"
                        :key="item.code"
                        :value="item.code"
                    >
                        {{ item.name }} · {{ item.code }}
                    </option>
                </select>
                <span v-if="form.errors.region_code" class="text-destructive">{{
                    form.errors.region_code
                }}</span>
            </label>
            <label class="grid gap-1 text-sm">
                Аудан немесе қала
                <select
                    v-model="form.area_code"
                    required
                    :disabled="!areas.length || identityEditable === false"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="">Аумақты таңдаңыз</option>
                    <option
                        v-for="item in areas"
                        :key="item.code"
                        :value="item.code"
                    >
                        {{ item.name }} · {{ item.code }}
                    </option>
                </select>
                <span v-if="form.errors.area_code" class="text-destructive">{{
                    form.errors.area_code
                }}</span>
            </label>
            <label class="grid gap-1 text-sm">
                Мәртебе
                <select
                    v-model="form.status"
                    :disabled="identityEditable === false"
                    class="rounded-md border bg-background p-2"
                >
                    <option value="active">Белсенді</option>
                    <option value="inactive">Белсенді емес</option>
                    <option value="archived">Мұрағатталған</option>
                </select>
                <span v-if="form.errors.status" class="text-destructive">{{
                    form.errors.status
                }}</span>
            </label>
            <button
                type="submit"
                :disabled="form.processing || !form.area_code"
                class="w-fit rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50"
            >
                Сақтау
            </button>
        </form>
    </section>
</template>
