<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { Dumbbell, Loader2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import LangSwitch from '@/components/LangSwitch.vue'
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
  <div class="flex min-h-[85dvh] flex-col justify-center gap-8">
    <div class="flex items-center justify-between">
      <h1 class="flex items-center gap-2 text-3xl font-bold tracking-tight">
        <span class="hero grid size-10 place-items-center rounded-xl text-white"><Dumbbell class="size-5" /></span>
        {{ $t('app.name') }}
      </h1>
      <LangSwitch />
    </div>

    <form class="rise space-y-4 rounded-2xl border bg-card p-5 shadow-sm" novalidate @submit.prevent="onSubmit">
      <h2 class="text-xl font-semibold">{{ $t('auth.login') }}</h2>

      <div class="space-y-1.5">
        <Label for="email">{{ $t('auth.email') }}</Label>
        <input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="email" autocapitalize="off"
          spellcheck="false" :maxlength="EMAIL_MAX" :aria-invalid="!!error('email') || undefined"
          class="h-11 w-full rounded-md border bg-transparent px-3 text-base outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30 aria-invalid:border-destructive"
          @blur="touch('email')" />
        <FieldError :message="error('email')" />
      </div>

      <div class="space-y-1.5">
        <Label for="password">{{ $t('auth.password') }}</Label>
        <PasswordInput id="password" v-model="form.password" autocomplete="current-password"
          :invalid="!!error('password')" @blur="touch('password')" />
        <FieldError :message="error('password')" />
      </div>

      <FieldError :message="general" />
      <Button type="submit" size="lg" class="h-11 w-full" :disabled="busy">
        <Loader2 v-if="busy" class="animate-spin" /> {{ $t('auth.submitLogin') }}
      </Button>
      <p class="text-center text-sm text-muted-foreground">
        {{ $t('auth.noAccount') }}
        <RouterLink to="/register" class="font-medium text-foreground underline">{{ $t('auth.register') }}</RouterLink>
      </p>
    </form>
  </div>
</template>
