<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { useAuth } from '@/stores/auth'
import type { Locale } from '@/types'

const { locale } = useI18n()
const auth = useAuth()
const langs: { code: Locale; label: string }[] = [
  { code: 'ru', label: 'RU' },
  { code: 'en', label: 'EN' },
]
function select(v: unknown) {
  if (v === 'ru' || v === 'en') void auth.setLocale(v)
}
</script>

<template>
  <ToggleGroup type="single" :model-value="locale" aria-label="Language" @update:model-value="select">
    <ToggleGroupItem v-for="l in langs" :key="l.code" :value="l.code" class="px-3">{{ l.label }}</ToggleGroupItem>
  </ToggleGroup>
</template>
