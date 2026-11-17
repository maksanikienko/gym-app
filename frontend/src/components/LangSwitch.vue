<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/stores/auth'
import type { Locale } from '@/types'

const { locale } = useI18n()
const auth = useAuth()
const langs: { code: Locale; label: string }[] = [
  { code: 'ru', label: 'RU' },
  { code: 'en', label: 'EN' },
]
</script>

<template>
  <div class="inline-flex rounded-md border p-0.5">
    <button v-for="l in langs" :key="l.code" type="button"
      class="rounded px-3 py-1 text-sm font-medium transition-colors"
      :class="locale === l.code ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
      @click="auth.setLocale(l.code)">
      {{ l.label }}
    </button>
  </div>
</template>
