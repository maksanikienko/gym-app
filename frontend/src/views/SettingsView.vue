<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { KeyRound, Loader2, LogOut, UserRound } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import LangSwitch from '@/components/LangSwitch.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import FieldError from '@/components/FieldError.vue'
import { useAuth } from '@/stores/auth'
import { useForm } from '@/composables/useForm'
import { NAME_MAX, checkConfirm, checkName, checkPassword } from '@/lib/validation'

const auth = useAuth()
const router = useRouter()
const { t } = useI18n()
const inputClass = 'h-11 w-full rounded-md border bg-transparent px-3 text-base outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30 aria-invalid:border-destructive'

// Profile
const profile = useForm({ name: auth.user?.name ?? '' }, { name: (f) => checkName(f.name) })
watch(() => auth.user?.name, (n) => { if (n) profile.form.name = n })
const saveName = () => profile.submit(async () => {
  await auth.updateName(profile.form.name.trim())
  toast.success(t('settings.nameSaved'))
})

// Password
const pwd = useForm(
  { current_password: '', password: '', password_confirmation: '' },
  {
    current_password: (f) => (f.current_password ? null : { key: 'validation.required' }),
    password: (f) => checkPassword(f.password),
    password_confirmation: (f) => checkConfirm(f.password, f.password_confirmation),
  },
)
const changePassword = () => pwd.submit(async () => {
  await auth.changePassword({ ...pwd.form })
  Object.assign(pwd.form, { current_password: '', password: '', password_confirmation: '' })
  toast.success(t('settings.passwordChanged'))
})

// Account
const deleting = ref(false)
const del = useForm({ password: '' }, { password: (f) => (f.password ? null : { key: 'validation.required' }) })
const deleteAccount = () => del.submit(async () => {
  await auth.deleteAccount(del.form.password)
  router.replace('/register')
})

async function logout() {
  await auth.logout()
  router.replace('/login')
}
</script>

<template>
  <div>
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $t('settings.title') }}</h1>
    <div class="space-y-4">
      <Card class="rise">
        <CardContent class="flex items-center justify-between pt-6">
          <span class="font-medium">{{ $t('settings.language') }}</span>
          <LangSwitch />
        </CardContent>
      </Card>

      <Card class="rise" style="--i: 1">
        <CardHeader><CardTitle class="flex items-center gap-2 text-base"><UserRound class="size-4" /> {{ $t('settings.profile') }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-3" novalidate @submit.prevent="saveName">
            <p class="text-sm text-muted-foreground">{{ auth.user?.email }}</p>
            <div class="space-y-1.5">
              <Label for="name">{{ $t('auth.name') }}</Label>
              <input id="name" v-model="profile.form.name" autocomplete="name" :maxlength="NAME_MAX" :class="inputClass"
                :aria-invalid="!!profile.error('name') || undefined" @blur="profile.touch('name')" />
              <FieldError :message="profile.error('name')" />
            </div>
            <Button type="submit" variant="outline" class="w-full"
              :disabled="profile.busy.value || profile.form.name.trim() === auth.user?.name">
              <Loader2 v-if="profile.busy.value" class="animate-spin" /> {{ $t('settings.saveName') }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card class="rise" style="--i: 2">
        <CardHeader><CardTitle class="flex items-center gap-2 text-base"><KeyRound class="size-4" /> {{ $t('settings.changePassword') }}</CardTitle></CardHeader>
        <CardContent>
          <form class="space-y-3" novalidate @submit.prevent="changePassword">
            <input type="text" autocomplete="username" :value="auth.user?.email" class="hidden" aria-hidden="true" tabindex="-1" />
            <div class="space-y-1.5">
              <Label for="current_password">{{ $t('settings.currentPassword') }}</Label>
              <PasswordInput id="current_password" v-model="pwd.form.current_password" autocomplete="current-password"
                :invalid="!!pwd.error('current_password')" @blur="pwd.touch('current_password')" />
              <FieldError :message="pwd.error('current_password')" />
            </div>
            <div class="space-y-1.5">
              <Label for="new_password">{{ $t('settings.newPassword') }}</Label>
              <PasswordInput id="new_password" v-model="pwd.form.password" autocomplete="new-password"
                :invalid="!!pwd.error('password')" @blur="pwd.touch('password')" />
              <PasswordStrength :password="pwd.form.password" />
              <FieldError :message="pwd.error('password')" />
            </div>
            <div class="space-y-1.5">
              <Label for="new_password_confirmation">{{ $t('settings.repeatPassword') }}</Label>
              <PasswordInput id="new_password_confirmation" v-model="pwd.form.password_confirmation" autocomplete="new-password"
                :invalid="!!pwd.error('password_confirmation')" @blur="pwd.touch('password_confirmation')" />
              <FieldError :message="pwd.error('password_confirmation')" />
            </div>
            <FieldError :message="pwd.general.value" />
            <Button type="submit" variant="outline" class="w-full" :disabled="pwd.busy.value">
              <Loader2 v-if="pwd.busy.value" class="animate-spin" /> {{ $t('settings.changePassword') }}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card class="rise" style="--i: 3">
        <CardHeader><CardTitle class="text-base">{{ $t('settings.account') }}</CardTitle></CardHeader>
        <CardContent class="space-y-2">
          <Button variant="outline" class="w-full" @click="logout"><LogOut /> {{ $t('settings.logout') }}</Button>
          <Button variant="ghost" class="w-full text-destructive" @click="deleting = true">{{ $t('settings.deleteAccount') }}</Button>
        </CardContent>
      </Card>

      <Card class="rise" style="--i: 4">
        <CardHeader><CardTitle class="text-base">{{ $t('settings.install') }}</CardTitle></CardHeader>
        <CardContent class="text-sm text-muted-foreground">{{ $t('settings.installHint') }}</CardContent>
      </Card>
    </div>

    <Dialog v-model:open="deleting">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{{ $t('settings.deleteAccount') }}</DialogTitle>
          <DialogDescription>{{ $t('settings.deleteAccountHint') }}</DialogDescription>
        </DialogHeader>
        <form class="space-y-2" novalidate @submit.prevent="deleteAccount">
          <PasswordInput v-model="del.form.password" :placeholder="$t('auth.password')" autocomplete="current-password"
            :invalid="!!del.error('password')" @blur="del.touch('password')" />
          <FieldError :message="del.error('password') || del.general.value" />
          <DialogFooter class="gap-2 pt-2">
            <Button type="button" variant="outline" @click="deleting = false">{{ $t('common.cancel') }}</Button>
            <Button type="submit" variant="destructive" :disabled="del.busy.value">{{ $t('common.delete') }}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>
