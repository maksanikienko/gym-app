<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Dumbbell, Loader2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import LangSwitch from '@/components/LangSwitch.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import FieldError from '@/components/FieldError.vue'
import { useAuth } from '@/stores/auth'
import { useForm } from '@/composables/useForm'
import { EMAIL_MAX, NAME_MAX, checkConfirm, checkEmail, checkName, checkPassword } from '@/lib/validation'

const auth = useAuth()
const router = useRouter()
const { form, error, touch, submit, busy, general } = useForm(
  { name: '', email: '', password: '', password_confirmation: '' },
  {
    name: (f) => checkName(f.name),
    email: (f) => checkEmail(f.email),
    password: (f) => checkPassword(f.password),
    password_confirmation: (f) => checkConfirm(f.password, f.password_confirmation),
  },
)
const inputClass = 'h-11 w-full rounded-md border bg-transparent px-3 text-base outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30 aria-invalid:border-destructive'
const nameLeft = computed(() => NAME_MAX - form.name.length)

const onSubmit = () => submit(async () => {
  await auth.register({ ...form, name: form.name.trim(), email: form.email.trim() })
  router.replace('/programs')
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
      <h2 class="text-xl font-semibold">{{ $t('auth.register') }}</h2>

      <div class="space-y-1.5">
        <div class="flex items-baseline justify-between">
          <Label for="name">{{ $t('auth.name') }}</Label>
          <span v-if="nameLeft < 20" class="text-xs tabular-nums text-muted-foreground">{{ nameLeft }}</span>
        </div>
        <input id="name" v-model="form.name" autocomplete="name" :maxlength="NAME_MAX" :class="inputClass"
          :aria-invalid="!!error('name') || undefined" @blur="touch('name')" />
        <FieldError :message="error('name')" />
      </div>

      <div class="space-y-1.5">
        <Label for="email">{{ $t('auth.email') }}</Label>
        <input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="email" autocapitalize="off"
          spellcheck="false" :maxlength="EMAIL_MAX" :class="inputClass" :aria-invalid="!!error('email') || undefined"
          @blur="touch('email')" />
        <FieldError :message="error('email')" />
      </div>

      <div class="space-y-1.5">
        <Label for="password">{{ $t('auth.password') }}</Label>
        <PasswordInput id="password" v-model="form.password" autocomplete="new-password"
          :invalid="!!error('password')" @blur="touch('password')" />
        <PasswordStrength :password="form.password" />
        <FieldError :message="error('password')" />
      </div>

      <div class="space-y-1.5">
        <Label for="password_confirmation">{{ $t('auth.passwordConfirm') }}</Label>
        <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password"
          :invalid="!!error('password_confirmation')" @blur="touch('password_confirmation')" />
        <FieldError :message="error('password_confirmation')" />
      </div>

      <FieldError :message="general" />
      <Button type="submit" size="lg" class="h-11 w-full" :disabled="busy">
        <Loader2 v-if="busy" class="animate-spin" /> {{ $t('auth.submitRegister') }}
      </Button>
      <p class="text-center text-sm text-muted-foreground">
        {{ $t('auth.haveAccount') }}
        <RouterLink to="/login" class="font-medium text-foreground underline">{{ $t('auth.login') }}</RouterLink>
      </p>
    </form>
  </div>
</template>
