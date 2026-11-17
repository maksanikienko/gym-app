<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { ArrowLeft } from '@lucide/vue'
import { Card, CardContent } from '@/components/ui/card'
import { api, firstError } from '@/lib/api'
import { fmtDate, fmtNum, fmtSets } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { ExerciseHistory } from '@/types'

const props = defineProps<{ id: string }>()
const router = useRouter()
const { l, locale } = useLocalized()
const data = ref<ExerciseHistory | null>(null)

onMounted(async () => {
  try {
    data.value = await api<ExerciseHistory>(`/exercises/${props.id}/history`)
  } catch (e) {
    toast.error(firstError(e))
  }
})

// Simple e1RM trend line (oldest → newest).
const chart = computed(() => {
  const pts = (data.value?.entries ?? []).filter((e) => e.best_e1rm).slice().reverse()
  if (pts.length < 2) return null
  const vals = pts.map((p) => p.best_e1rm as number)
  const min = Math.min(...vals)
  const max = Math.max(...vals)
  const span = max - min || 1
  const w = 300
  const h = 80
  const xy = vals.map((v, i) => [(i / (vals.length - 1)) * w, h - 6 - ((v - min) / span) * (h - 12)])
  return { w, h, min, max, points: xy.map((p) => p.join(',')).join(' '), last: xy.at(-1)! }
})
</script>

<template>
  <div>
    <button type="button" class="mb-3 flex items-center gap-1 text-sm text-muted-foreground" @click="router.back()">
      <ArrowLeft class="size-4" /> {{ $t('common.back') }}
    </button>
    <p v-if="!data" class="text-muted-foreground">{{ $t('common.loading') }}</p>
    <div v-else class="space-y-4">
      <div>
        <h1 class="text-2xl font-bold">{{ l(data.exercise, 'name') }}</h1>
        <p class="flex flex-wrap gap-x-2 text-sm text-muted-foreground">
          <span v-for="m in data.exercise.muscles" :key="m">{{ $t(`muscles.${m}`) }}</span>
        </p>
      </div>

      <Card v-if="data.best_e1rm">
        <CardContent class="space-y-3 pt-6">
          <div>
            <p class="text-sm text-muted-foreground">{{ $t('exercise.best') }}</p>
            <p class="text-3xl font-bold tabular-nums">{{ fmtNum(data.best_e1rm) }} {{ $t('common.kg') }}</p>
          </div>
          <svg v-if="chart" :viewBox="`0 0 ${chart.w} ${chart.h}`" class="h-20 w-full overflow-visible" preserveAspectRatio="none" role="img"
            :aria-label="`${$t('exercise.e1rm')}: ${chart.min}–${chart.max} ${$t('common.kg')}`">
            <polyline :points="chart.points" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" class="text-primary" />
            <circle :cx="chart.last[0]" :cy="chart.last[1]" r="3.5" class="fill-primary" />
          </svg>
        </CardContent>
      </Card>

      <p v-if="!data.entries.length" class="text-muted-foreground">{{ $t('exercise.empty') }}</p>
      <div class="divide-y rounded-lg border">
        <RouterLink v-for="e in data.entries" :key="e.session_id" :to="`/workout/${e.session_id}`" class="block space-y-0.5 p-3">
          <div class="flex justify-between text-sm">
            <span class="font-medium">{{ fmtDate(e.date, locale) }}</span>
            <span v-if="e.best_e1rm" class="tabular-nums text-muted-foreground">{{ $t('exercise.e1rm') }} {{ fmtNum(e.best_e1rm) }}</span>
          </div>
          <p class="text-sm tabular-nums">{{ fmtSets(e.sets, $t('common.sec')) }}</p>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
