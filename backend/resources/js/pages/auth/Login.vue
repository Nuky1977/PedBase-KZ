<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/composables/useTranslation';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const { t } = useTranslation();

defineOptions({
    layout: {
        title: 'Жүйеге кіру',
        description: 'Логин мен құпиясөзіңізді енгізіңіз',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head :title="t('Жүйеге кіру')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="username">{{ t('Логин') }}</Label>
                <Input
                    id="username"
                    type="text"
                    name="username"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('Құпиясөз') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                />
                <InputError :message="errors.password" />
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="w-fit text-sm"
                >
                    {{ t('Құпиясөзді ұмыттыңыз ба?') }}
                </TextLink>
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>{{ t('Мені есте сақтау') }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? t('Кіру орындалуда…') : t('Кіру') }}
            </Button>
        </div>
    </Form>
</template>
