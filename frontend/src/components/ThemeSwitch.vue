<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { setTheme, theme, type Theme } from '@/lib/theme'

const options: { value: Theme; icon: typeof Moon; label: string }[] = [
  { value: 'dark', icon: Moon, label: 'settings.themeDark' },
  { value: 'light', icon: Sun, label: 'settings.themeLight' },
  { value: 'system', icon: Monitor, label: 'settings.themeSystem' },
]
function select(v: unknown) {
  if (v === 'dark' || v === 'light' || v === 'system') setTheme(v)
}
</script>

<template>
  <ToggleGroup type="single" :model-value="theme" :aria-label="$t('settings.theme')" @update:model-value="select">
    <ToggleGroupItem v-for="o in options" :key="o.value" :value="o.value" :aria-label="$t(o.label)" :title="$t(o.label)">
      <component :is="o.icon" />
    </ToggleGroupItem>
  </ToggleGroup>
</template>
