<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    achievementDocument,
    certificate,
    destroy,
    store,
    update,
} from '@/routes/dossiers';
import { organizationEmployment } from '@/routes/teacher';
import { edit as editProfile } from '@/routes/teacher/profile';

type Kind = 'diplomas' | 'courses' | 'attestations' | 'achievements';
type Entry = {
    id: number;
    has_certificate?: boolean;
    [key: string]: string | number | boolean | null | undefined;
};
type Records = {
    data: Entry[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Field = {
    key: string;
    label: string;
    type?: 'date' | 'number';
    optional?: boolean;
};

const props = defineProps<{
    teacher: { id: number; name: string };
    canManage: boolean;
    isOwn: boolean;
    showCatalogEmployment: boolean;
    profile: {
        education_level: string | null;
        total_experience_months: number | null;
        teaching_experience_months: number | null;
    } | null;
    diplomas: Records | null;
    courses: Records;
    attestations: Records;
    achievements: Records | null;
}>();

const sections: { key: Kind; title: string; fields: Field[] }[] = [
    {
        key: 'achievements',
        title: 'Марапаттар және кәсіби жетістіктер',
        fields: [
            { key: 'title', label: 'Атауы' },
            { key: 'issuer', label: 'Берген ұйым' },
            { key: 'awarded_on', label: 'Берілген күні', type: 'date' },
            { key: 'level', label: 'Деңгейі' },
            { key: 'description', label: 'Сипаттамасы', optional: true },
        ],
    },
    {
        key: 'diplomas',
        title: 'Дипломдар',
        fields: [
            { key: 'education_level', label: 'Білім деңгейі' },
            { key: 'institution', label: 'Оқу орны' },
            { key: 'specialty', label: 'Мамандық' },
            { key: 'diploma_number', label: 'Диплом нөмірі', optional: true },
            { key: 'graduation_year', label: 'Бітірген жылы', type: 'number' },
        ],
    },
    {
        key: 'courses',
        title: 'Біліктілікті арттыру курстары',
        fields: [
            { key: 'title', label: 'Курс атауы' },
            { key: 'provider', label: 'Ұйым' },
            { key: 'hours', label: 'Сағат саны', type: 'number' },
            { key: 'started_on', label: 'Басталған күні', type: 'date' },
            { key: 'ended_on', label: 'Аяқталған күні', type: 'date' },
            {
                key: 'certificate_number',
                label: 'Сертификат нөмірі',
                optional: true,
            },
            {
                key: 'certificate_issued_on',
                label: 'Сертификат берілген күн',
                type: 'date',
                optional: true,
            },
        ],
    },
    {
        key: 'attestations',
        title: 'Аттестаттау тарихы',
        fields: [
            { key: 'category', label: 'Біліктілік санаты' },
            { key: 'awarded_on', label: 'Берілген күні', type: 'date' },
            {
                key: 'valid_until',
                label: 'Қолданылу мерзімі',
                type: 'date',
                optional: true,
            },
        ],
    },
];
const selected = ref<Kind | null>(null);
const editing = ref<number | null>(null);
const form = useForm({
    fields: {} as Record<string, string>,
    certificate: null as File | null,
});

function openEditor(kind: Kind, entry?: Entry) {
    selected.value = kind;
    editing.value = entry?.id ?? null;
    form.fields = Object.fromEntries(
        (sections.find((section) => section.key === kind)?.fields ?? []).map(
            (field) => [field.key, String(entry?.[field.key] ?? '')],
        ),
    );
    form.certificate = null;
    form.clearErrors();
}

function saveRecord() {
    if (!selected.value) {
        return;
    }

    const params = { teacher: props.teacher.id, kind: selected.value };
    const action =
        editing.value === null
            ? store(params)
            : update({ ...params, record: editing.value });
    form.transform(({ fields, certificate: file }) => ({
        ...fields,
        certificate: file,
    })).submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
        },
    });
}

function setCertificate(event: Event) {
    const input = event.target as HTMLInputElement;
    form.certificate = input.files?.[0] ?? null;
}

function remove(kind: Kind, entry: Entry) {
    if (window.confirm('Бұл жазбаны алып тастайсыз ба?')) {
        router.visit(
            destroy({ teacher: props.teacher.id, kind, record: entry.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (selected.value === kind && editing.value === entry.id) {
                        selected.value = null;
                    }
                },
            },
        );
    }
}

const errors = () => form.errors as Record<string, string | undefined>;
const inputClass =
    'h-9 w-full min-w-0 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-8">
        <Head title="Педагогтің жеке ісі" />
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Педагогтің жеке ісі
                </h1>
                <p class="mt-1 text-muted-foreground">{{ teacher.name }}</p>
            </div>
            <div v-if="isOwn" class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="editProfile()">Жеке мәліметтерді өңдеу</Link>
                </Button>
                <Button v-if="showCatalogEmployment" variant="outline" as-child>
                    <Link :href="organizationEmployment()"
                        >Ұйымдардағы жұмыс орындарым</Link
                    >
                </Button>
            </div>
        </header>
        <Card>
            <CardHeader
                ><CardTitle>Профиль және еңбек өтілі</CardTitle></CardHeader
            >
            <CardContent>
                <dl class="grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted-foreground">Білім деңгейі</dt>
                        <dd>{{ profile?.education_level ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Жалпы өтілі (ай)</dt>
                        <dd>{{ profile?.total_experience_months ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Педагогикалық өтілі (ай)
                        </dt>
                        <dd>
                            {{ profile?.teaching_experience_months ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
        <template v-for="section in sections" :key="section.key">
            <Card v-if="$props[section.key]">
                <CardHeader
                    class="flex flex-row flex-wrap items-center justify-between gap-2"
                >
                    <CardTitle>{{ section.title }}</CardTitle>
                    <Button
                        v-if="canManage"
                        size="sm"
                        variant="outline"
                        @click="openEditor(section.key)"
                        >Жазба қосу</Button
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <form
                        v-if="selected === section.key"
                        class="space-y-4 rounded-lg border bg-muted/40 p-3"
                        @submit.prevent="saveRecord"
                    >
                        <h3 class="font-medium">
                            {{
                                editing === null
                                    ? 'Жаңа жазба'
                                    : 'Жазбаны өңдеу'
                            }}
                        </h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="field in section.fields"
                                :key="field.key"
                                class="space-y-1"
                            >
                                <Label :for="field.key">{{
                                    field.label
                                }}</Label>
                                <input
                                    :id="field.key"
                                    v-model="form.fields[field.key]"
                                    :type="field.type ?? 'text'"
                                    :required="!field.optional"
                                    :class="inputClass"
                                />
                                <InputError :message="errors()[field.key]" />
                            </div>
                            <div
                                v-if="
                                    section.key === 'courses' ||
                                    section.key === 'achievements'
                                "
                                class="space-y-1"
                            >
                                <Label for="certificate"
                                    >Растайтын құжат (PDF, 5 МБ дейін)</Label
                                >
                                <input
                                    id="certificate"
                                    type="file"
                                    accept="application/pdf,.pdf"
                                    class="block w-full text-sm"
                                    @change="setCertificate"
                                />
                                <InputError :message="errors().certificate" />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button :disabled="form.processing">Сақтау</Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="selected = null"
                                >Бас тарту</Button
                            >
                        </div>
                    </form>
                    <p
                        v-if="!$props[section.key]?.data.length"
                        class="text-sm text-muted-foreground"
                    >
                        Жазба жоқ.
                    </p>
                    <article
                        v-for="entry in $props[section.key]?.data"
                        :key="entry.id"
                        class="space-y-3 rounded-lg border p-3"
                    >
                        <dl
                            class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <template
                                v-for="field in section.fields"
                                :key="field.key"
                            >
                                <div v-if="Object.hasOwn(entry, field.key)">
                                    <dt class="text-muted-foreground">
                                        {{ field.label }}
                                    </dt>
                                    <dd class="break-words">
                                        {{ entry[field.key] ?? '—' }}
                                    </dd>
                                </div>
                            </template>
                        </dl>
                        <div v-if="canManage" class="flex flex-wrap gap-2">
                            <Button
                                v-if="entry.has_certificate"
                                as-child
                                size="sm"
                                variant="outline"
                                ><a
                                    :href="
                                        section.key === 'achievements'
                                            ? achievementDocument({
                                                  teacher: teacher.id,
                                                  achievement: entry.id,
                                              }).url
                                            : certificate({
                                                  teacher: teacher.id,
                                                  course: entry.id,
                                              }).url
                                    "
                                    >PDF жүктеу</a
                                ></Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                @click="openEditor(section.key, entry)"
                                >Өңдеу</Button
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="remove(section.key, entry)"
                                >Алып тастау</Button
                            >
                        </div>
                    </article>
                    <PageLinks
                        :previous="$props[section.key]?.prev_page_url ?? null"
                        :next="$props[section.key]?.next_page_url ?? null"
                    />
                </CardContent>
            </Card>
        </template>
    </div>
</template>
