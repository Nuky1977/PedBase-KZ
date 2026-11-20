<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { edit, index, status } from '@/routes/education-organizations';

type Status = 'active' | 'inactive' | 'archived';

const props = defineProps<{
    canUpdateStatus: boolean;
    organization: {
        id: number;
        name: string;
        short_name: string | null;
        type_name: string;
        region_name: string;
        area_name: string;
        locality: string;
        bin: string;
        status: Status;
    };
}>();

const labels: Record<Status, string> = {
    active: 'Белсенді',
    inactive: 'Белсенді емес',
    archived: 'Мұрағатталған',
};
const form = useForm({ status: props.organization.status });

function saveStatus() {
    form.patch(status.url(props.organization.id), { preserveScroll: true });
}
</script>

<template>
    <section class="max-w-2xl space-y-5 p-4 md:p-6">
        <Head :title="organization.name" />
        <Link :href="index()" class="text-sm underline"
            >Білім беру ұйымдарына қайту</Link
        >
        <h1 class="text-2xl font-semibold">{{ organization.name }}</h1>
        <Link
            v-if="canUpdateStatus"
            :href="edit(organization.id)"
            class="text-sm underline"
        >
            Ұйымды өңдеу
        </Link>
        <dl class="grid gap-3 rounded-lg border p-4">
            <div v-if="organization.short_name">
                <dt class="text-sm text-muted-foreground">Қысқаша атауы</dt>
                <dd>{{ organization.short_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">БСН</dt>
                <dd>{{ organization.bin }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Ұйым түрі</dt>
                <dd>{{ organization.type_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Әкімшілік аумақ</dt>
                <dd>
                    {{ organization.region_name }} ·
                    {{ organization.area_name }} · {{ organization.locality }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Мәртебесі</dt>
                <dd>
                    {{ labels[organization.status] }}
                </dd>
            </div>
        </dl>
        <form
            v-if="canUpdateStatus"
            class="space-y-2"
            @submit.prevent="saveStatus"
        >
            <label class="block text-sm" for="organization-status"
                >Жаңа мәртебе</label
            >
            <select
                id="organization-status"
                v-model="form.status"
                class="rounded-md border bg-background p-2"
                :disabled="form.processing"
            >
                <option
                    v-for="(label, value) in labels"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </option>
            </select>
            <p
                v-if="form.errors.status"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ form.errors.status }}
            </p>
            <p
                v-if="form.status === 'archived'"
                class="text-sm text-muted-foreground"
            >
                Еңбек жазбалары мен тарихи деректер сақталады.
            </p>
            <Button
                :disabled="
                    form.processing || form.status === organization.status
                "
                >Мәртебені сақтау</Button
            >
        </form>
    </section>
</template>
