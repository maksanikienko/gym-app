<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Loader2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AuthShell from '@/components/AuthShell.vue'
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
const nameLeft = computed(() => NAME_MAX - form.name.length)

const onSubmit = () => submit(async () => {
  await auth.register({ ...form, name: form.name.trim(), email: form.email.trim() })
  router.replace('/programs')
})
</script>

<template>
  <AuthShell :title="$t('auth.joinTitle')" :subtitle="$t('auth.joinHint')">
    <form class="space-y-4" novalidate @submit.prevent="onSubmit">
      <div class="space-y-2">
        <div class="flex items-baseline justify-between">
          <Label for="name">{{ $t('auth.name') }}</Label>
          <span v-if="nameLeft < 20" class="text-xs tabular-nums text-muted-foreground">{{ nameLeft }}</span>
        </div>
        <Input id="name" v-model="form.name" autocomplete="name" :maxlength="NAME_MAX"
          :aria-invalid="!!error('name') || undefined" @blur="touch('name')" />
        <FieldError :message="error('name')" />
      </div>

      <div class="space-y-2">
        <Label for="email">{{ $t('auth.email') }}</Label>
        <Input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="email" autocapitalize="off"
          spellcheck="false" placeholder="you@example.com" :maxlength="EMAIL_MAX" :aria-invalid="!!error('email') || undefined"
          @blur="touch('email')" />
        <FieldError :message="error('email')" />
      </div>

      <div class="space-y-2">
        <Label for="password">{{ $t('auth.password') }}</Label>
        <PasswordInput id="password" v-model="form.password" autocomplete="new-password"
          :invalid="!!error('password')" @blur="touch('password')" />
        <PasswordStrength :password="form.password" />
        <FieldError :message="error('password')" />
      </div>

      <div class="space-y-2">
        <Label for="password_confirmation">{{ $t('auth.passwordConfirm') }}</Label>
        <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password"
          :invalid="!!error('password_confirmation')" @blur="touch('password_confirmation')" />
        <FieldError :message="error('password_confirmation')" />
      </div>

      <FieldError :message="general" />
      <Button type="submit" size="xl" class="w-full" :disabled="busy">
        <Loader2 v-if="busy" class="animate-spin" /> {{ $t('auth.submitRegister') }}
      </Button>
    </form>
    <template #footer>
      {{ $t('auth.haveAccount') }}
      <RouterLink to="/login" class="font-semibold text-primary">{{ $t('auth.login') }}</RouterLink>
    </template>
  </AuthShell>
</template>
