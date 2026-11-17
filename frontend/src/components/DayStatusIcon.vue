<script setup lang="ts">
import { Ban, CheckCircle2, Lock, PlayCircle } from '@lucide/vue'
import type { DayStatus } from '@/types'

defineProps<{ status: DayStatus }>()
</script>

<template>
  <CheckCircle2 v-if="status === 'finished'" class="size-5 shrink-0 text-success" />
  <Ban v-else-if="status === 'skipped'" class="size-5 shrink-0 text-muted-foreground" />
  <span v-else-if="status === 'in_progress'" class="relative flex size-5 shrink-0 items-center justify-center">
    <span class="absolute size-3 animate-ping rounded-full bg-warning opacity-60" />
    <span class="size-2.5 rounded-full bg-warning" />
  </span>
  <PlayCircle v-else-if="status === 'next'" class="size-5 shrink-0 text-primary" />
  <Lock v-else class="size-4 shrink-0 text-muted-foreground" />
</template>
