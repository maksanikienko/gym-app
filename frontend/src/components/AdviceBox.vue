<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowDown, ArrowUp, Equal, Lightbulb, Wand2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { fmtNum } from '@/lib/format'
import type { Advice } from '@/types'

const props = defineProps<{ advice: Advice }>()
const emit = defineEmits<{ apply: [] }>()
const { t, te } = useI18n()

const trend = computed(() => {
  const b = props.advice.basis
  if (b.startsWith('increase') || b === 'longer' || b === 'add_reps') return 'up'
  if (b === 'decrease') return 'down'
  if (b.startsWith('hold')) return 'same'
  return null
})

const headline = computed(() => {
  const a = props.advice
  if (a.weight_kg && a.reps) return t('advice.target', { weight: fmtNum(a.weight_kg), reps: a.reps })
  if (a.weight_kg) return `${fmtNum(a.weight_kg)} ${t('common.kg')}`
  if (a.seconds) return t('advice.targetTime', { seconds: a.seconds })
  if (a.reps) return t('advice.targetReps', { reps: a.reps })
  return null
})

const reason = computed(() => {
  const key = `advice.basis.${props.advice.basis}`
  if (!te(key)) return ''
  const p = Object.fromEntries(Object.entries(props.advice.params).map(([k, v]) => [k, typeof v === 'number' ? fmtNum(v) : v]))
  return t(key, p)
})
const canApply = computed(() => !!(props.advice.weight_kg || props.advice.reps || props.advice.seconds))
</script>

<template>
  <div class="relative overflow-hidden rounded-xl border border-primary/25 bg-gradient-to-br from-primary/10 to-primary/[0.03] p-3">
    <div class="flex items-start gap-2.5">
      <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/15 text-primary">
        <Lightbulb class="size-4" />
      </div>
      <div class="min-w-0 flex-1 space-y-0.5">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-primary">{{ $t('advice.title') }}</p>
        <p v-if="headline" class="flex items-center gap-1.5 text-lg font-bold tabular-nums leading-tight">
          {{ headline }}
          <ArrowUp v-if="trend === 'up'" class="size-4 text-success" />
          <ArrowDown v-else-if="trend === 'down'" class="size-4 text-warning" />
          <Equal v-else-if="trend === 'same'" class="size-4 text-muted-foreground" />
        </p>
        <p class="text-xs leading-relaxed text-muted-foreground">{{ reason }}</p>
      </div>
      <Button v-if="canApply" size="sm" class="shrink-0 rounded-lg" @click="emit('apply')">
        <Wand2 /> {{ $t('advice.apply') }}
      </Button>
    </div>
  </div>
</template>
