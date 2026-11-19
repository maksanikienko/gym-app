<script setup lang="ts">
import { computed } from 'vue'
import { CalendarCheck, Clock, Dumbbell, Flag, ListRestart, Repeat, SkipForward, TrendingDown, TrendingUp, Trophy, Weight } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { fmtNum } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { CycleSummary } from '@/types'

const props = defineProps<{ summary: CycleSummary | null; busy?: boolean }>()
defineEmits<{ repeat: []; other: []; finish: [] }>()
const { l } = useLocalized()

const hours = computed(() => {
  const m = props.summary?.training_minutes ?? 0
  return m >= 60 ? `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}` : String(m)
})
/** Volume in tonnes once it gets big — "48.2 т" reads better than "48210 кг". */
const volume = computed(() => {
  const kg = props.summary?.volume_kg ?? 0
  return kg >= 10_000 ? { value: fmtNum(Math.round(kg / 100) / 10), unit: 't' } : { value: String(kg), unit: 'kg' }
})
const attendance = computed(() => {
  const w = props.summary?.workouts
  return w && w.total ? Math.round((w.finished / w.total) * 100) : 0
})
</script>

<template>
  <Card class="rise gap-0 overflow-hidden py-0" style="--i: 1">
    <div class="flex flex-col items-center gap-2 bg-warning/10 px-5 pt-6 pb-5 text-center">
      <span class="pop grid size-14 place-items-center rounded-2xl bg-warning/20 text-warning"><Trophy class="size-7" /></span>
      <h2 class="text-xl font-bold">{{ $t('today.completed') }}</h2>
      <p v-if="summary" class="text-sm text-muted-foreground">{{ l(summary.program, 'name') }}</p>
    </div>

    <div v-if="!summary" class="grid grid-cols-2 gap-2 p-4">
      <Skeleton v-for="i in 4" :key="i" class="h-[68px] rounded-xl" />
    </div>

    <template v-else>
      <!-- Key numbers -->
      <div class="grid grid-cols-2 gap-2 p-4">
        <div class="rounded-xl bg-muted/60 p-3">
          <p class="flex items-center gap-1.5 text-xs text-muted-foreground"><CalendarCheck class="size-3.5" /> {{ $t('cycle.workouts') }}</p>
          <p class="mt-1 text-xl font-bold tabular-nums">{{ summary.workouts.finished }}<span class="text-sm font-medium text-muted-foreground">/{{ summary.workouts.total }}</span></p>
          <p class="text-[11px] text-muted-foreground">
            {{ $t('cycle.attendance', { pct: attendance }) }}<template v-if="summary.workouts.skipped"> · {{ $t('cycle.annulled', { n: summary.workouts.skipped }) }}</template>
          </p>
        </div>
        <div class="rounded-xl bg-muted/60 p-3">
          <p class="flex items-center gap-1.5 text-xs text-muted-foreground"><Clock class="size-3.5" /> {{ $t('cycle.time') }}</p>
          <p class="mt-1 text-xl font-bold tabular-nums">{{ hours }}<span class="ml-1 text-sm font-medium text-muted-foreground">{{ summary.training_minutes >= 60 ? $t('cycle.hoursUnit') : $t('common.min') }}</span></p>
        </div>
        <div class="rounded-xl bg-muted/60 p-3">
          <p class="flex items-center gap-1.5 text-xs text-muted-foreground"><Dumbbell class="size-3.5" /> {{ $t('cycle.sets') }}</p>
          <p class="mt-1 text-xl font-bold tabular-nums">{{ summary.sets_count }}</p>
        </div>
        <div class="rounded-xl bg-muted/60 p-3">
          <p class="flex items-center gap-1.5 text-xs text-muted-foreground"><Weight class="size-3.5" /> {{ $t('cycle.volume') }}</p>
          <p class="mt-1 text-xl font-bold tabular-nums">{{ volume.value }}<span class="ml-1 text-sm font-medium text-muted-foreground">{{ volume.unit === 't' ? $t('cycle.tonnes') : $t('common.kg') }}</span></p>
        </div>
      </div>

      <!-- Strength progress -->
      <section v-if="summary.progress.length" class="border-t px-4 py-3.5">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $t('cycle.progress') }}</h3>
        <ul class="space-y-2">
          <li v-for="p in summary.progress" :key="p.exercise.id" class="flex items-center gap-3 text-sm">
            <span class="min-w-0 flex-1 truncate font-medium">{{ l(p.exercise, 'name') }}</span>
            <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ fmtNum(p.start_e1rm) }} → {{ fmtNum(p.best_e1rm) }}</span>
            <span class="inline-flex w-16 shrink-0 items-center justify-end gap-0.5 text-xs font-semibold tabular-nums"
              :class="(p.change_pct ?? 0) > 0 ? 'text-success' : 'text-muted-foreground'">
              <TrendingUp v-if="(p.change_pct ?? 0) > 0" class="size-3.5" /><TrendingDown v-else-if="(p.change_pct ?? 0) < 0" class="size-3.5" />
              {{ (p.change_pct ?? 0) > 0 ? '+' : '' }}{{ fmtNum(p.change_pct ?? 0) }}%
            </span>
          </li>
        </ul>
        <p class="mt-2 text-[11px] text-muted-foreground">{{ $t('cycle.progressHint') }}</p>
      </section>

      <!-- Skipped most often -->
      <section v-if="summary.skipped_exercises.length" class="border-t px-4 py-3.5">
        <h3 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          <SkipForward class="size-3.5" /> {{ $t('cycle.skippedOften') }}
        </h3>
        <ul class="space-y-1 text-sm">
          <li v-for="s in summary.skipped_exercises" :key="s.exercise.id" class="flex justify-between gap-3">
            <span class="truncate">{{ l(s.exercise, 'name') }}</span>
            <span class="shrink-0 tabular-nums text-muted-foreground">×{{ s.times }}</span>
          </li>
        </ul>
      </section>
    </template>

    <!-- What next -->
    <div class="space-y-2 border-t p-4">
      <Button size="xl" class="w-full" :disabled="busy || !summary" @click="$emit('repeat')">
        <Repeat /> {{ $t('cycle.repeat') }}
      </Button>
      <Button variant="outline" size="lg" class="w-full" :disabled="busy" @click="$emit('other')">
        <ListRestart /> {{ $t('cycle.chooseOther') }}
      </Button>
      <Button variant="ghost" size="sm" class="w-full text-muted-foreground" :disabled="busy" @click="$emit('finish')">
        <Flag /> {{ $t('today.finishCycle') }}
      </Button>
    </div>
  </Card>
</template>
