<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { ChevronDown, Globe, KeyRound, Loader2, LogOut, Palette, Smartphone, Trash2, UserRound } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import PageHeader from '@/components/PageHeader.vue'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import LangSwitch from '@/components/LangSwitch.vue'
import ThemeSwitch from '@/components/ThemeSwitch.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import FieldError from '@/components/FieldError.vue'
import { useAuth } from '@/stores/auth'
import { useForm } from '@/composables/useForm'
import { NAME_MAX, checkConfirm, checkName, checkPassword } from '@/lib/validation'

const auth = useAuth()
const router = useRouter()
const { t } = useI18n()

const initials = computed(() => (auth.user?.name ?? '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]!.toUpperCase()).join(''))
const pwdOpen = ref(false)

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
    <PageHeader :title="$t('settings.title')" />

    <div class="space-y-4">
      <!-- Profile -->
      <Card class="rise gap-5">
        <CardContent class="flex items-center gap-4">
          <div class="hero grid size-16 shrink-0 place-items-center rounded-full text-xl font-bold text-white shadow-lg shadow-primary/20">{{ initials }}</div>
          <div class="min-w-0">
            <p class="truncate text-lg font-semibold">{{ auth.user?.name }}</p>
            <p class="truncate text-sm text-muted-foreground">{{ auth.user?.email }}</p>
          </div>
        </CardContent>
        <Separator />
        <CardContent>
          <form class="space-y-3" novalidate @submit.prevent="saveName">
            <div class="space-y-2">
              <Label for="name" class="text-muted-foreground"><UserRound class="size-4" /> {{ $t('auth.name') }}</Label>
              <div class="flex gap-2">
                <Input id="name" v-model="profile.form.name" autocomplete="name" :maxlength="NAME_MAX"
                  :aria-invalid="!!profile.error('name') || undefined" @blur="profile.touch('name')" />
                <Button type="submit" size="lg" variant="secondary"
                  :disabled="profile.busy.value || profile.form.name.trim() === auth.user?.name">
                  <Loader2 v-if="profile.busy.value" class="animate-spin" /> {{ $t('settings.saveName') }}
                </Button>
              </div>
              <FieldError :message="profile.error('name')" />
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Appearance -->
      <Card class="rise gap-0 py-0" style="--i: 1">
        <div class="flex items-center justify-between gap-3 px-5 py-3.5">
          <span class="flex items-center gap-3 font-medium">
            <span class="grid size-8 place-items-center rounded-lg bg-primary/12 text-primary"><Globe class="size-4" /></span>
            {{ $t('settings.language') }}
          </span>
          <LangSwitch />
        </div>
        <Separator />
        <div class="flex items-center justify-between gap-3 px-5 py-3.5">
          <span class="flex items-center gap-3 font-medium">
            <span class="grid size-8 place-items-center rounded-lg bg-primary/12 text-primary"><Palette class="size-4" /></span>
            {{ $t('settings.theme') }}
          </span>
          <ThemeSwitch />
        </div>
      </Card>

      <!-- Password -->
      <Collapsible v-model:open="pwdOpen" as-child>
        <Card class="rise gap-0 py-0" style="--i: 2">
          <CollapsibleTrigger class="group flex w-full items-center gap-3 px-5 py-3.5 text-left font-medium">
            <span class="grid size-8 place-items-center rounded-lg bg-primary/12 text-primary"><KeyRound class="size-4" /></span>
            <span class="flex-1">{{ $t('settings.changePassword') }}</span>
            <ChevronDown class="size-4 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <form class="space-y-3 border-t px-5 pt-4 pb-5" novalidate @submit.prevent="changePassword">
              <input type="text" autocomplete="username" :value="auth.user?.email" class="hidden" aria-hidden="true" tabindex="-1" />
              <div class="space-y-2">
                <Label for="current_password">{{ $t('settings.currentPassword') }}</Label>
                <PasswordInput id="current_password" v-model="pwd.form.current_password" autocomplete="current-password"
                  :invalid="!!pwd.error('current_password')" @blur="pwd.touch('current_password')" />
                <FieldError :message="pwd.error('current_password')" />
              </div>
              <div class="space-y-2">
                <Label for="new_password">{{ $t('settings.newPassword') }}</Label>
                <PasswordInput id="new_password" v-model="pwd.form.password" autocomplete="new-password"
                  :invalid="!!pwd.error('password')" @blur="pwd.touch('password')" />
                <PasswordStrength :password="pwd.form.password" />
                <FieldError :message="pwd.error('password')" />
              </div>
              <div class="space-y-2">
                <Label for="new_password_confirmation">{{ $t('settings.repeatPassword') }}</Label>
                <PasswordInput id="new_password_confirmation" v-model="pwd.form.password_confirmation" autocomplete="new-password"
                  :invalid="!!pwd.error('password_confirmation')" @blur="pwd.touch('password_confirmation')" />
                <FieldError :message="pwd.error('password_confirmation')" />
              </div>
              <FieldError :message="pwd.general.value" />
              <Button type="submit" size="lg" class="w-full" :disabled="pwd.busy.value">
                <Loader2 v-if="pwd.busy.value" class="animate-spin" /> {{ $t('settings.changePassword') }}
              </Button>
            </form>
          </CollapsibleContent>
        </Card>
      </Collapsible>

      <!-- Install -->
      <Card class="rise gap-2 py-4" style="--i: 3">
        <CardHeader class="flex items-center gap-3 px-5">
          <span class="grid size-8 place-items-center rounded-lg bg-primary/12 text-primary"><Smartphone class="size-4" /></span>
          <CardTitle class="text-base">{{ $t('settings.install') }}</CardTitle>
        </CardHeader>
        <CardDescription class="px-5 pl-16">{{ $t('settings.installHint') }}</CardDescription>
      </Card>

      <!-- Account -->
      <div class="rise space-y-2 pt-2" style="--i: 4">
        <Button variant="outline" size="lg" class="w-full" @click="logout"><LogOut /> {{ $t('settings.logout') }}</Button>
        <Button variant="ghost" class="w-full text-destructive hover:bg-destructive/10 hover:text-destructive" @click="deleting = true">
          <Trash2 /> {{ $t('settings.deleteAccount') }}
        </Button>
      </div>
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
            <Button type="button" variant="outline" size="lg" @click="deleting = false">{{ $t('common.cancel') }}</Button>
            <Button type="submit" variant="destructive" size="lg" :disabled="del.busy.value">{{ $t('common.delete') }}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  </div>
</template>
