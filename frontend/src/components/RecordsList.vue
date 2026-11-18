<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import { ChevronRight, Trophy } from '@lucide/vue'
import { Skeleton } from '@/components/ui/skeleton'
import { Item, ItemActions, ItemContent, ItemMedia, ItemTitle } from '@/components/ui/item'
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
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
    <div v-if="!items" class="space-y-2.5">
      <Skeleton v-for="i in 4" :key="i" class="h-24 rounded-2xl" />
    </div>
    <Empty v-else-if="!items.length" class="rise py-12">
      <EmptyHeader>
        <EmptyMedia class="bg-warning/15 text-warning"><Trophy /></EmptyMedia>
        <EmptyTitle class="text-base font-medium">{{ $t('records.empty') }}</EmptyTitle>
      </EmptyHeader>
    </Empty>
    <div v-else class="space-y-2.5">
      <Item v-for="(r, i) in items" :key="r.exercise.id" as-child variant="card" class="rise items-start" :style="{ '--i': i % 20 }">
        <RouterLink :to="`/exercises/${r.exercise.id}`">
          <ItemMedia variant="icon" class="bg-warning/15 text-warning"><Trophy /></ItemMedia>
          <ItemContent>
            <ItemTitle><span class="truncate">{{ l(r.exercise, 'name') }}</span></ItemTitle>
            <div class="mt-2 grid grid-cols-2 gap-2">
              <div class="rounded-xl bg-muted/60 px-3 py-2">
                <p class="text-[11px] text-muted-foreground">{{ $t('records.heaviest') }}</p>
                <p class="font-semibold tabular-nums">{{ fmtNum(r.heaviest.weight_kg) }} {{ $t('common.kg') }} <span class="text-muted-foreground">× {{ r.heaviest.reps }}</span></p>
              </div>
              <div v-if="r.best_e1rm" class="rounded-xl bg-muted/60 px-3 py-2">
                <p class="text-[11px] text-muted-foreground">{{ $t('records.best') }}</p>
                <p class="font-semibold tabular-nums">{{ fmtNum(r.best_e1rm.e1rm) }} {{ $t('common.kg') }}</p>
              </div>
            </div>
            <p class="mt-1.5 text-xs text-muted-foreground">
              {{ fmtDate(r.last_date, locale) }} · {{ $t('records.sessions', { n: r.sessions }) }}
            </p>
          </ItemContent>
          <ItemActions class="self-center"><ChevronRight class="size-5 text-muted-foreground/50" /></ItemActions>
        </RouterLink>
      </Item>
    </div>
  </div>
</template>
