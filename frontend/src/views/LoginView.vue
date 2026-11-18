<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { Loader2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AuthShell from '@/components/AuthShell.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import FieldError from '@/components/FieldError.vue'
import { useAuth } from '@/stores/auth'
import { useForm } from '@/composables/useForm'
import { EMAIL_MAX, checkEmail } from '@/lib/validation'

const auth = useAuth()
const router = useRouter()
const route = useRoute()
const { form, error, touch, submit, busy, general } = useForm(
  { email: '', password: '' },
  {
    email: (f) => checkEmail(f.email),
    password: (f) => (f.password ? null : { key: 'validation.required' }),
  },
)

const onSubmit = () => submit(async () => {
  await auth.login(form.email.trim(), form.password)
  router.replace((route.query.redirect as string) || '/')
})
</script>

<template>
  <AuthShell :title="$t('auth.welcome')" :subtitle="$t('auth.welcomeHint')">
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
      <div class="space-y-2">
        <Label for="email">{{ $t('auth.email') }}</Label>
        <Input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="email" autocapitalize="off"
          spellcheck="false" placeholder="you@example.com" :maxlength="EMAIL_MAX" :aria-invalid="!!error('email') || undefined"
          @blur="touch('email')" />
        <FieldError :message="error('email')" />
      </div>

      <div class="space-y-2">
        <Label for="password">{{ $t('auth.password') }}</Label>
        <PasswordInput id="password" v-model="form.password" autocomplete="current-password"
          :invalid="!!error('password')" @blur="touch('password')" />
        <FieldError :message="error('password')" />
      </div>

      <FieldError :message="general" />
      <Button type="submit" size="xl" class="w-full" :disabled="busy">
        <Loader2 v-if="busy" class="animate-spin" /> {{ $t('auth.submitLogin') }}
      </Button>
    </form>
    <template #footer>
      {{ $t('auth.noAccount') }}
      <RouterLink to="/register" class="font-semibold text-primary">{{ $t('auth.register') }}</RouterLink>
    </template>
  </AuthShell>
</template>
