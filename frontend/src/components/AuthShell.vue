<script setup lang="ts">
import { Dumbbell } from '@lucide/vue'
import LangSwitch from '@/components/LangSwitch.vue'

defineProps<{ title: string; subtitle?: string }>()
</script>

<template>
  <div class="flex min-h-[85dvh] flex-col">
    <div class="flex justify-end"><LangSwitch /></div>
    <div class="flex flex-1 flex-col justify-center gap-8 py-6">
      <div class="rise flex flex-col items-center gap-4 text-center">
        <span class="hero grid size-16 place-items-center rounded-[1.25rem] text-white shadow-xl shadow-primary/25">
          <Dumbbell class="size-8" />
        </span>
        <div class="space-y-1">
          <h1 class="text-3xl font-bold tracking-tight">{{ title }}</h1>
          <p v-if="subtitle" class="text-muted-foreground">{{ subtitle }}</p>
        </div>
      </div>
      <div class="rise rounded-3xl border bg-card p-5 shadow-xl shadow-black/5 dark:shadow-black/30" style="--i: 1">
        <slot />
      </div>
      <p class="rise text-center text-sm text-muted-foreground" style="--i: 2"><slot name="footer" /></p>
    </div>
  </div>
</template>
