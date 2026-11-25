<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/composables/useTranslation';
import { edit, index, status } from '@/routes/education-organizations';
import { index as reports } from '@/routes/education-organizations/reports';

const { t } = useTranslation();

type Status = 'active' | 'inactive' | 'archived';

const props = defineProps<{
    canUpdateStatus: boolean;
    canEdit: boolean;
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
    active: t('Белсенді'),
    inactive: t('Белсенді емес'),
    archived: t('Мұрағатталған'),
};
const form = useForm({ status: props.organization.status });

function saveStatus() {
    form.patch(status.url(props.organization.id), { preserveScroll: true });
}
</script>

<template>
    <section class="max-w-2xl space-y-5 p-4 md:p-6">
        <Head :title="organization.name" />
        <Link :href="index()" class="text-sm underline">{{
            t('Білім беру ұйымдарына қайту')
        }}</Link>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
            {{ organization.name }}
        </h1>
        <Link
            v-if="canEdit"
            :href="edit(organization.id)"
            class="text-sm underline"
        >
            {{ t('Ұйымды өңдеу') }}
        </Link>
        <Link
            :href="reports({ query: { organization_id: organization.id } })"
            class="text-sm underline"
        >
            {{ t('Ұйым есептері') }}
        </Link>
        <dl class="grid gap-3 rounded-lg border p-4">
            <div v-if="organization.short_name">
                <dt class="text-sm text-muted-foreground">
                    {{ t('Қысқаша атауы') }}
                </dt>
                <dd>{{ organization.short_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">{{ t('БСН') }}</dt>
                <dd>{{ organization.bin }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    {{ t('Ұйым түрі') }}
                </dt>
                <dd>{{ t(organization.type_name) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    {{ t('Әкімшілік аумақ') }}
                </dt>
                <dd>
                    {{ organization.region_name }} ·
                    {{ organization.area_name }} · {{ organization.locality }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    {{ t('Мәртебесі') }}
                </dt>
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
            <label class="block text-sm" for="organization-status">{{
                t('Жаңа мәртебе')
            }}</label>
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
                {{ t('Еңбек жазбалары мен тарихи деректер сақталады.') }}
            </p>
            <Button
                :disabled="
                    form.processing || form.status === organization.status
                "
                >{{ t('Мәртебені сақтау') }}</Button
            >
        </form>
    </section>
</template>
