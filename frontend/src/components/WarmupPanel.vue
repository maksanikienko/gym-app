<script setup lang="ts">
import { computed, ref } from 'vue'
import { ChevronDown, Flame } from '@lucide/vue'
import { fmtNum } from '@/lib/format'
import { platesPerSide, warmupSets } from '@/lib/load'
import type { Equipment } from '@/types'

const props = defineProps<{ work: number | null; count: number; equipment: Equipment }>()
const open = ref(false)
const sets = computed(() => (props.work ? warmupSets(props.work, props.count, props.equipment) : []))
const barbell = computed(() => props.equipment === 'barbell')
const plates = (w: number) => platesPerSide(w)
</script>

<template>
  <div v-if="sets.length || (barbell && work)" class="rounded-xl border">
    <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-sm" @click="open = !open">
      <Flame class="size-4 text-warning" />
      <span class="font-medium">{{ $t('warmup.title') }}</span>
      <span v-if="sets.length" class="text-muted-foreground">· {{ $t('warmup.sets', { n: sets.length }) }}</span>
      <span class="ml-auto truncate text-xs tabular-nums text-muted-foreground">
        {{ sets.map((s) => `${fmtNum(s.weight)}×${s.reps}`).join(' → ') }}
      </span>
      <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': open }" />
    </button>
    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1"
      leave-active-class="transition-all duration-150" leave-to-class="opacity-0">
      <div v-if="open" class="space-y-1.5 border-t px-3 py-2.5 text-sm">
        <div v-for="(s, i) in sets" :key="i" class="flex items-center gap-3 tabular-nums">
          <span class="w-5 text-xs text-muted-foreground">{{ i + 1 }}</span>
          <span class="w-24 font-medium">{{ fmtNum(s.weight) }} {{ $t('common.kg') }} × {{ s.reps }}</span>
          <span v-if="barbell" class="truncate text-xs text-muted-foreground">
            <template v-if="s.bar">{{ $t('warmup.bar') }}</template>
            <template v-else>{{ plates(s.weight).plates.map(fmtNum).join(' + ') }}</template>
          </span>
        </div>
        <div v-if="barbell && work" class="mt-1 rounded-lg bg-muted/60 px-2.5 py-2 text-xs">
          <span class="font-medium">{{ $t('warmup.plates') }}</span>
          <span class="text-muted-foreground"> {{ $t('warmup.platesFor', { weight: fmtNum(work) }) }}: </span>
          <span class="font-semibold tabular-nums">
            {{ plates(work).plates.length ? plates(work).plates.map(fmtNum).join(' + ') : $t('warmup.onlyBar') }}
          </span>
          <span v-if="plates(work).rest" class="text-warning"> {{ $t('warmup.rest', { rest: fmtNum(plates(work).rest) }) }}</span>
        </div>
      </div>
    </Transition>
  </div>
</template>
