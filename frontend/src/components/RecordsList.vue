<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { ChevronRight, Trophy } from '@lucide/vue'
import { api, firstError } from '@/lib/api'
import { fmtDate, fmtNum } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { ExerciseRecord } from '@/types'

const { l, locale } = useLocalized()
const items = ref<ExerciseRecord[] | null>(null)

onMounted(async () => {
  try {
    items.value = await api<ExerciseRecord[]>('/records')
  } catch (e) {
    toast.error(firstError(e))
    items.value = []
  }
})
</script>

<template>
  <div>
    <div v-if="!items" class="space-y-2">
      <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-xl bg-muted" />
    </div>
    <div v-else-if="!items.length" class="rise flex flex-col items-center gap-3 rounded-2xl border border-dashed py-12 text-center text-muted-foreground">
      <Trophy class="size-10 opacity-50" />
      {{ $t('records.empty') }}
    </div>
    <div v-else class="space-y-2">
      <RouterLink v-for="(r, i) in items" :key="r.exercise.id" :to="`/exercises/${r.exercise.id}`"
        class="rise flex items-center gap-3 rounded-xl border bg-card p-4 transition-all active:scale-[0.99] active:bg-accent"
        :style="{ '--i': i % 20 }">
        <div class="grid size-10 shrink-0 place-items-center rounded-full bg-warning/15 text-warning">
          <Trophy class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold">{{ l(r.exercise, 'name') }}</p>
          <div class="mt-1 grid grid-cols-2 gap-2 text-xs">
            <div>
              <p class="text-muted-foreground">{{ $t('records.heaviest') }}</p>
              <p class="font-semibold tabular-nums">{{ fmtNum(r.heaviest.weight_kg) }} {{ $t('common.kg') }} × {{ r.heaviest.reps }}</p>
            </div>
            <div v-if="r.best_e1rm">
              <p class="text-muted-foreground">{{ $t('records.best') }}</p>
              <p class="font-semibold tabular-nums">{{ fmtNum(r.best_e1rm.e1rm) }} {{ $t('common.kg') }}</p>
            </div>
          </div>
          <p class="mt-1 text-[11px] text-muted-foreground">
            {{ fmtDate(r.last_date, locale) }} · {{ $t('records.sessions', { n: r.sessions }) }}
          </p>
        </div>
        <ChevronRight class="size-5 text-muted-foreground" />
      </RouterLink>
    </div>
  </div>
</template>
