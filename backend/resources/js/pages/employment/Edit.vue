<script setup lang="ts">
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EmploymentWorkload from '@/components/EmploymentWorkload.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/composables/useTranslation';
import {
    archive,
    save,
    school as schoolIndex,
} from '@/routes/admin/employment';
import { destroy, store, update } from '@/routes/admin/employment/assignments';
import { store as storeSubject } from '@/routes/admin/employment/subjects';
import type { TeacherEmployment, TeachingAssignment } from '@/types/employment';

const { t } = useTranslation();

const props = defineProps<{
    school: { id: number; name: string };
    teacher: { id: number; name: string };
    isPrimary: boolean;
    employment: TeacherEmployment | null;
    subjects: { id: number; code: string; name_kk: string }[];
    academicYear: number;
    canCreateSubject: boolean;
}>();

const routeParams = computed(() => ({
    school: props.school.id,
    teacher: props.teacher.id,
}));
const editing = ref<number | null>(null);
const assignmentForm = useForm({
    subject_id: '',
    academic_year: props.academicYear,
    weekly_hours: '',
});
const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

function selectAssignment(assignment?: TeachingAssignment) {
    editing.value = assignment?.id ?? null;
    assignmentForm.subject_id = assignment ? String(assignment.subject_id) : '';
    assignmentForm.academic_year =
        assignment?.academic_year ?? props.academicYear;
    assignmentForm.weekly_hours = assignment?.weekly_hours ?? '';
    assignmentForm.clearErrors();
}

function saveAssignment() {
    const action =
        editing.value === null
            ? store(routeParams.value)
            : update({ ...routeParams.value, assignment: editing.value });

    assignmentForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => selectAssignment(),
    });
}

function removeAssignment(assignment: TeachingAssignment) {
    if (window.confirm(t('Осы пәннің жүктемесін алып тастайсыз ба?'))) {
        router.visit(
            destroy({ ...routeParams.value, assignment: assignment.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (editing.value === assignment.id) {
                        selectAssignment();
                    }
                },
            },
        );
    }
}

function archiveEmployment() {
    if (
        window.confirm(
            t(
                'Еңбек жазбасы мұрағатталады. Мектепке бекіту сақталады. Жалғастырасыз ба?',
            ),
        )
    ) {
        router.visit(archive(routeParams.value), {
            preserveScroll: true,
            onSuccess: () => selectAssignment(),
        });
    }
}
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-5 px-4 py-6 sm:px-8">
        <Head :title="t('Педагогтің еңбек деректері')" />
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ teacher.name }}
                </h1>
                <p class="text-muted-foreground">{{ school.name }}</p>
                <Badge :variant="isPrimary ? 'default' : 'secondary'">
                    {{
                        isPrimary
                            ? t('Негізгі жұмыс орны')
                            : t('Қосымша жұмыс орны')
                    }}
                </Badge>
            </div>
            <Button variant="outline" as-child>
                <Link :href="schoolIndex(school.id)">{{
                    t('Мектептің еңбек деректері')
                }}</Link>
            </Button>
        </header>

        <Card>
            <CardHeader
                ><CardTitle>{{ t('Еңбек деректері') }}</CardTitle></CardHeader
            >
            <CardContent>
                <Form
                    v-bind="save.form(routeParams)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="position-title">{{
                                t('Лауазымы')
                            }}</Label>
                            <Input
                                id="position-title"
                                name="position_title"
                                :default-value="
                                    employment?.position_title ?? ''
                                "
                                maxlength="150"
                                required
                            />
                            <InputError :message="errors.position_title" />
                        </div>
                        <div class="space-y-2">
                            <Label for="employment-status">{{
                                t('Еңбек күйі')
                            }}</Label>
                            <select
                                id="employment-status"
                                name="status"
                                :value="employment?.status ?? 'active'"
                                :class="selectClass"
                            >
                                <option value="active">
                                    {{ t('Жұмыс істейді') }}
                                </option>
                                <option value="on_leave">
                                    {{ t('Демалыста') }}
                                </option>
                            </select>
                            <InputError :message="errors.status" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button :disabled="processing">{{
                            t('Еңбек деректерін сақтау')
                        }}</Button>
                        <Button
                            v-if="employment"
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="archiveEmployment"
                        >
                            {{ t('Мұрағаттау') }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader
                ><CardTitle>{{
                    t('Пәндер мен апталық жүктеме')
                }}</CardTitle></CardHeader
            >
            <CardContent class="space-y-5">
                <EmploymentWorkload :employment="employment">
                    <template #actions="{ assignment }">
                        <div class="flex flex-wrap gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="selectAssignment(assignment)"
                                >{{ t('Өңдеу') }}</Button
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeAssignment(assignment)"
                                >{{ t('Алып тастау') }}</Button
                            >
                        </div>
                    </template>
                </EmploymentWorkload>
                <p v-if="!employment" class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Пән жүктемесін енгізу үшін алдымен еңбек деректерін сақтаңыз.',
                        )
                    }}
                </p>
                <template v-else>
                    <form
                        v-if="subjects.length"
                        class="space-y-4 border-t pt-4"
                        @submit.prevent="saveAssignment"
                    >
                        <h2 class="font-medium">
                            {{
                                editing === null
                                    ? t('Пән жүктемесін қосу')
                                    : t('Пән жүктемесін өңдеу')
                            }}
                        </h2>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <Label for="subject">{{ t('Пән') }}</Label>
                                <select
                                    id="subject"
                                    v-model="assignmentForm.subject_id"
                                    :class="selectClass"
                                    required
                                >
                                    <option disabled value="">
                                        {{ t('Пәнді таңдаңыз') }}
                                    </option>
                                    <option
                                        v-for="subject in subjects"
                                        :key="subject.id"
                                        :value="String(subject.id)"
                                    >
                                        {{ subject.name_kk }} ({{
                                            subject.code
                                        }})
                                    </option>
                                </select>
                                <InputError
                                    :message="assignmentForm.errors.subject_id"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="academic-year">{{
                                    t('Оқу жылының басталуы')
                                }}</Label>
                                <Input
                                    id="academic-year"
                                    v-model.number="
                                        assignmentForm.academic_year
                                    "
                                    type="number"
                                    min="1900"
                                    max="9998"
                                    required
                                />
                                <InputError
                                    :message="
                                        assignmentForm.errors.academic_year
                                    "
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="weekly-hours">{{
                                    t('Апталық сағат')
                                }}</Label>
                                <Input
                                    id="weekly-hours"
                                    v-model="assignmentForm.weekly_hours"
                                    inputmode="decimal"
                                    placeholder="1,5"
                                    required
                                />
                                <InputError
                                    :message="
                                        assignmentForm.errors.weekly_hours
                                    "
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button :disabled="assignmentForm.processing">{{
                                editing === null ? t('Қосу') : t('Сақтау')
                            }}</Button>
                            <Button
                                v-if="editing !== null"
                                type="button"
                                variant="outline"
                                @click="selectAssignment()"
                                >{{ t('Бас тарту') }}</Button
                            >
                        </div>
                    </form>
                    <details
                        v-if="canCreateSubject"
                        :open="!subjects.length"
                        class="rounded-lg border p-3"
                    >
                        <summary class="cursor-pointer text-sm font-medium">
                            {{ t('Анықтамалыққа пән қосу') }}
                        </summary>
                        <Form
                            v-bind="storeSubject.form(routeParams)"
                            :reset-on-success="true"
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing }"
                            class="mt-3 space-y-3"
                        >
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="subject-code">{{
                                        t('Пән коды')
                                    }}</Label>
                                    <Input
                                        id="subject-code"
                                        name="code"
                                        maxlength="50"
                                        required
                                    />
                                    <InputError :message="errors.code" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="subject-name">{{
                                        t('Қазақша атауы')
                                    }}</Label>
                                    <Input
                                        id="subject-name"
                                        name="name_kk"
                                        maxlength="150"
                                        required
                                    />
                                    <InputError :message="errors.name_kk" />
                                </div>
                            </div>
                            <Button variant="outline" :disabled="processing">{{
                                t('Пәнді сақтау')
                            }}</Button>
                        </Form>
                    </details>
                </template>
            </CardContent>
        </Card>
    </div>
</template>
