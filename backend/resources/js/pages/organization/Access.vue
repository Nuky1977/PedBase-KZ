<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PageLinks from '@/components/PageLinks.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/composables/useTranslation';
import { index } from '@/routes/organization';
import { save } from '@/routes/organization/access';

const { t } = useTranslation();

type Page<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

defineProps<{
    districts: { id: number; name: string }[];
    schoolOptions: { id: number; name: string }[];
    schools: Page<{ id: number; name: string; district_id: number | null }>;
    staff: Page<{
        id: number;
        name: string;
        role: 'school_admin' | 'methodist';
        school_id: number | null;
        district_id: number | null;
    }>;
}>();
const selectClass =
    'h-9 max-w-full rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-5 p-4 sm:p-8">
        <Head :title="t('Ұйымдық өкілеттіктер')" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">
                {{ t('Ұйымдық өкілеттіктер') }}
            </h1>
            <Button variant="outline" as-child
                ><Link :href="index()">{{ t('Ұйымдар') }}</Link></Button
            >
        </div>
        <Card>
            <CardHeader
                ><CardTitle>{{ t('Аудан қосу') }}</CardTitle></CardHeader
            >
            <CardContent>
                <Form
                    v-bind="save.form('district')"
                    reset-on-success
                    v-slot="{ errors, processing }"
                    class="space-y-2"
                >
                    <Label for="district-name">{{ t('Аудан атауы') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <Input
                            id="district-name"
                            name="name"
                            required
                            maxlength="150"
                            class="w-full sm:w-72"
                        />
                        <Button :disabled="processing">{{ t('Қосу') }}</Button>
                    </div>
                    <InputError :message="errors.name" />
                </Form>
            </CardContent>
        </Card>
        <Card>
            <CardHeader
                ><CardTitle>{{ t('Мектептің ауданы') }}</CardTitle></CardHeader
            >
            <CardContent class="space-y-3">
                <p v-if="!schools.data.length" class="text-muted-foreground">
                    {{ t('Мектеп тіркелмеген.') }}
                </p>
                <Form
                    v-for="item in schools.data"
                    :key="item.id"
                    v-bind="save.form('school')"
                    v-slot="{ errors, processing }"
                    class="border-b pb-3 last:border-0"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Label
                            :for="'school-district-' + item.id"
                            class="min-w-40"
                            >{{ item.name }}</Label
                        >
                        <input
                            type="hidden"
                            name="school_id"
                            :value="item.id"
                        />
                        <select
                            :id="'school-district-' + item.id"
                            name="district_id"
                            :value="item.district_id ?? ''"
                            :class="selectClass"
                        >
                            <option value="">
                                {{ t('Аудан бекітілмеген') }}
                            </option>
                            <option
                                v-for="district in districts"
                                :key="district.id"
                                :value="district.id"
                            >
                                {{ district.name }}
                            </option>
                        </select>
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="processing"
                            >{{ t('Сақтау') }}</Button
                        >
                    </div>
                    <InputError
                        :message="errors.district_id || errors.school_id"
                    />
                </Form>
            </CardContent>
        </Card>
        <Card>
            <CardHeader
                ><CardTitle>{{
                    t('Қызметкердің қолжетімділігі')
                }}</CardTitle></CardHeader
            >
            <CardContent class="space-y-3">
                <p v-if="!staff.data.length" class="text-muted-foreground">
                    {{ t('Мектеп әкімшісі немесе әдіскер тіркелмеген.') }}
                </p>
                <Form
                    v-for="person in staff.data"
                    :key="person.id"
                    v-bind="
                        save.form(
                            person.role === 'school_admin'
                                ? 'school-admin'
                                : 'methodist',
                        )
                    "
                    v-slot="{ errors, processing }"
                    class="border-b pb-3 last:border-0"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Label
                            :for="'staff-scope-' + person.id"
                            class="min-w-40"
                            >{{ person.name }} ·
                            {{
                                person.role === 'school_admin'
                                    ? t('Мектеп әкімшісі')
                                    : t('Әдіскер')
                            }}</Label
                        >
                        <input
                            type="hidden"
                            name="user_id"
                            :value="person.id"
                        />
                        <select
                            :id="'staff-scope-' + person.id"
                            :name="
                                person.role === 'school_admin'
                                    ? 'school_id'
                                    : 'district_id'
                            "
                            :value="
                                (person.role === 'school_admin'
                                    ? person.school_id
                                    : person.district_id) ?? ''
                            "
                            :class="selectClass"
                        >
                            <option value="">
                                {{ t('Өкілеттік бекітілмеген') }}
                            </option>
                            <option
                                v-for="scope in person.role === 'school_admin'
                                    ? schoolOptions
                                    : districts"
                                :key="scope.id"
                                :value="scope.id"
                            >
                                {{ scope.name }}
                            </option>
                        </select>
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="processing"
                            >{{ t('Сақтау') }}</Button
                        >
                    </div>
                    <InputError
                        :message="
                            errors.school_id ||
                            errors.district_id ||
                            errors.user_id
                        "
                    />
                </Form>
            </CardContent>
        </Card>
        <PageLinks
            :previous="schools.prev_page_url"
            :next="schools.next_page_url"
        />
        <PageLinks
            :previous="staff.prev_page_url"
            :next="staff.next_page_url"
        />
    </div>
</template>
