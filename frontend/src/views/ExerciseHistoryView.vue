<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { ChevronRight, LineChart, TrendingUp } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import PageHeader from '@/components/PageHeader.vue'
import { api, firstError } from '@/lib/api'
import { fmtDate, fmtNum, fmtSets } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { ExerciseHistory } from '@/types'

const props = defineProps<{ id: string }>()
const { l, locale } = useLocalized()
const data = ref<ExerciseHistory | null>(null)

onMounted(async () => {
  try {
    data.value = await api<ExerciseHistory>(`/exercises/${props.id}/history`)
  } catch (e) {
    toast.error(firstError(e))
  }
})

// e1RM trend (oldest → newest) as an area chart.
const chart = computed(() => {
  const pts = (data.value?.entries ?? []).filter((e) => e.best_e1rm).slice().reverse()
  if (pts.length < 2) return null
  const vals = pts.map((p) => p.best_e1rm as number)
  const min = Math.min(...vals)
  const max = Math.max(...vals)
  const span = max - min || 1
  const w = 300
  const h = 96
  const xy = vals.map((v, i) => [(i / (vals.length - 1)) * w, h - 8 - ((v - min) / span) * (h - 20)])
  const line = xy.map((p) => p.join(',')).join(' ')
  return { w, h, min, max, line, area: `0,${h} ${line} ${w},${h}`, dots: xy, last: xy.at(-1)! }
})
const delta = computed(() => {
  const pts = (data.value?.entries ?? []).filter((e) => e.best_e1rm)
  if (pts.length < 2) return null
  return (pts[0].best_e1rm as number) - (pts.at(-1)!.best_e1rm as number)
})
</script>

<template>
  <div>
    <div v-if="!data" class="space-y-4">
      <Skeleton class="h-9 w-24 rounded-full" />
      <Skeleton class="h-10 w-2/3" />
      <Skeleton class="h-48 rounded-2xl" />
      <Skeleton class="h-40 rounded-2xl" />
    </div>
    <template v-else>
      <PageHeader back :title="l(data.exercise, 'name')">
        <div v-if="data.exercise.muscles.length" class="mt-2.5 flex flex-wrap gap-1.5">
          <Badge v-for="m in data.exercise.muscles" :key="m" variant="secondary">{{ $t(`muscles.${m}`) }}</Badge>
        </div>
      </PageHeader>

      <div class="space-y-4">
        <Card v-if="data.best_e1rm" class="rise gap-4 overflow-hidden pb-0">
          <div class="flex items-start justify-between gap-3 px-5">
            <div>
              <p class="text-sm text-muted-foreground">{{ $t('exercise.best') }}</p>
              <p class="mt-1 text-4xl font-bold tracking-tight tabular-nums">
                {{ fmtNum(data.best_e1rm) }}<span class="ml-1 text-lg font-semibold text-muted-foreground">{{ $t('common.kg') }}</span>
              </p>
            </div>
            <Badge v-if="delta !== null && delta !== 0" :variant="delta > 0 ? 'success' : 'warning'" class="mt-1 text-sm">
              <TrendingUp :class="{ 'rotate-180': delta < 0 }" /> {{ delta > 0 ? '+' : '' }}{{ fmtNum(Math.round(delta * 10) / 10) }}
            </Badge>
          </div>
          <svg v-if="chart" :viewBox="`0 0 ${chart.w} ${chart.h}`" class="h-28 w-full overflow-visible text-primary" preserveAspectRatio="none" role="img"
            :aria-label="`${$t('exercise.e1rm')}: ${chart.min}–${chart.max} ${$t('common.kg')}`">
            <defs>
              <linearGradient id="e1rm-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="currentColor" stop-opacity="0.35" />
                <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
              </linearGradient>
            </defs>
            <polygon :points="chart.area" fill="url(#e1rm-fill)" />
            <polyline :points="chart.line" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
          </svg>
          <div v-else class="h-4" />
        </Card>

        <Empty v-if="!data.entries.length" class="py-10">
          <EmptyHeader>
            <EmptyMedia><LineChart /></EmptyMedia>
            <EmptyTitle class="text-base font-medium">{{ $t('exercise.empty') }}</EmptyTitle>
          </EmptyHeader>
        </Empty>
        <Card v-else class="rise gap-0 overflow-hidden py-0" style="--i: 1">
          <RouterLink v-for="e in data.entries" :key="e.session_id" :to="`/workout/${e.session_id}`"
            class="flex items-center gap-3 border-b px-4 py-3 transition-colors last:border-b-0 hover:bg-accent/50 active:bg-accent">
            <div class="min-w-0 flex-1 space-y-0.5">
              <div class="flex items-center justify-between gap-2 text-sm">
                <span class="font-semibold capitalize">{{ fmtDate(e.date, locale) }}</span>
                <span v-if="e.best_e1rm" class="text-xs tabular-nums text-muted-foreground">{{ $t('exercise.e1rm') }} <b class="font-semibold text-foreground">{{ fmtNum(e.best_e1rm) }}</b></span>
              </div>
              <p class="truncate text-sm tabular-nums text-muted-foreground">{{ fmtSets(e.sets, $t('common.sec')) }}</p>
            </div>
            <ChevronRight class="size-4 shrink-0 text-muted-foreground/50" />
          </RouterLink>
        </Card>
      </div>
    </template>
  </div>
</template>
