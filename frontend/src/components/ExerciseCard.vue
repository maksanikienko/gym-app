<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { Check, CheckCircle2, LineChart, Plus, PlayCircle, Repeat, Trophy, X } from '@lucide/vue'
import AdviceBox from '@/components/AdviceBox.vue'
import WarmupPanel from '@/components/WarmupPanel.vue'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { api, firstError } from '@/lib/api'
import { fmtNum, fmtSets, fmtTarget, youtubeSearch } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { Exercise, LastPerformance, SetLog, Slot } from '@/types'

const props = defineProps<{
  slot: Slot
  sessionId: number
  sets: SetLog[]
  previous: Record<string, LastPerformance>
}>()
const emit = defineEmits<{ saved: [set: SetLog, firstSave: boolean]; deleted: [setId: number]; dirty: [count: number] }>()

const { t, locale } = useI18n()
const { l } = useLocalized()

interface Row { set_no: number; weight: string; amount: string; rpe: string; savedId: number | null; dirty: boolean; busy: boolean; record?: boolean }

const options = computed<Exercise[]>(() => [props.slot.exercise, ...(props.slot.exercise.substitutes ?? [])])
const exerciseId = ref<number>(props.sets[0]?.exercise_id ?? props.slot.exercise.id)
const exercise = computed(() => options.value.find((o) => o.id === exerciseId.value) ?? props.slot.exercise)
const timed = computed(() => props.slot.reps === null && props.slot.seconds !== null)
const prev = computed(() => props.previous[String(exerciseId.value)] ?? null)
const draftKey = computed(() => `gym.draft.${props.sessionId}.${props.slot.id}.${exerciseId.value}`)
const advice = computed(() => props.slot.advice?.[String(exerciseId.value)] ?? null)
/** Working weight for warm-up / plates: today's suggestion, else last time's top set. */
const workWeight = computed(() => advice.value?.weight_kg
  ?? (prev.value ? Math.max(0, ...prev.value.sets.map((s) => s.weight_kg ?? 0)) || null : null))
const warmupCount = computed(() => props.slot.warmup_sets
  ?? (exercise.value.equipment === 'barbell' && props.slot.position <= 1 ? 2 : 0))

const rows = reactive<Row[]>([])

function buildRows() {
  const logged = props.sets.filter((s) => s.exercise_id === exerciseId.value && !s.is_warmup)
  let draft: Record<number, Partial<Row>> = {}
  try { draft = JSON.parse(localStorage.getItem(draftKey.value) ?? '{}') } catch { /* ignore */ }
  const count = Math.max(props.slot.sets, ...logged.map((s) => s.set_no), ...Object.keys(draft).map(Number))
  rows.splice(0, rows.length)
  for (let n = 1; n <= count; n++) {
    const s = logged.find((x) => x.set_no === n)
    const d = draft[n]
    rows.push({
      set_no: n,
      weight: d?.weight ?? (s?.weight_kg != null ? fmtNum(s.weight_kg) : ''),
      amount: d?.amount ?? String((timed.value ? s?.seconds : s?.reps) ?? ''),
      rpe: d?.rpe ?? (s?.rpe != null ? String(s.rpe) : ''),
      savedId: s?.id ?? null,
      dirty: !!d,
      busy: false,
    })
  }
}
buildRows()
watch(exerciseId, buildRows)

watch(rows, () => {
  const dirty = rows.filter((r) => r.dirty)
  emit('dirty', dirty.length)
  try {
    if (dirty.length) {
      localStorage.setItem(draftKey.value, JSON.stringify(Object.fromEntries(
        dirty.map((r) => [r.set_no, { weight: r.weight, amount: r.amount, rpe: r.rpe }]))))
    } else localStorage.removeItem(draftKey.value)
  } catch { /* ignore */ }
}, { deep: true })

/** Placeholder: same set from last time, else the previous row's value. */
function hint(row: Row, field: 'weight' | 'amount'): string {
  // Today's suggestion first, then the same set from last time.
  const a = advice.value
  if (a && field === 'weight' && a.weight_kg) return fmtNum(a.weight_kg)
  if (a && field === 'amount' && (timed.value ? a.seconds : a.reps)) return String(timed.value ? a.seconds : a.reps)
  const p = prev.value?.sets.find((s) => s.set_no === row.set_no) ?? prev.value?.sets.at(-1)
  if (p) {
    if (field === 'weight') return p.weight_kg != null ? fmtNum(p.weight_kg) : ''
    return String((timed.value ? p.seconds : p.reps) ?? '')
  }
  if (field === 'amount') return String((timed.value ? props.slot.seconds : props.slot.reps) ?? '')
  return ''
}

const num = (v: string) => (v.trim() === '' ? null : Number(v.replace(',', '.')))

async function save(row: Row) {
  // Empty fields fall back to the placeholder so "same as last time" is one tap.
  const weight = num(row.weight || hint(row, 'weight'))
  const amount = num(row.amount || hint(row, 'amount'))
  if (amount === null || Number.isNaN(amount) || (weight !== null && Number.isNaN(weight))) {
    toast.error(t('common.error'))
    return
  }
  row.busy = true
  const firstSave = row.savedId === null
  try {
    const set = await api<SetLog>(`/sessions/${props.sessionId}/sets`, 'POST', {
      day_exercise_id: props.slot.id,
      exercise_id: exerciseId.value,
      set_no: row.set_no,
      weight_kg: weight,
      reps: timed.value ? null : amount,
      seconds: timed.value ? amount : null,
      rpe: num(row.rpe),
    })
    row.weight = weight !== null ? fmtNum(weight) : ''
    row.amount = String(amount)
    row.savedId = set.id
    row.dirty = false
    row.record = !!set.records?.length
    if (set.records?.length) {
      toast.success(`🏆 ${t('records.new')}`, {
        description: `${l(exercise.value, 'name')}: ${set.records.map((r) => t(`records.${r.type}`, { value: fmtNum(r.value) })).join(' · ')}`,
      })
    }
    emit('saved', set, firstSave)
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    row.busy = false
  }
}

/** Fill the suggestion into rows that are still empty. */
function applyAdvice() {
  const a = advice.value
  if (!a) return
  for (const r of rows) {
    if (r.savedId) continue
    if (!r.weight && a.weight_kg) r.weight = fmtNum(a.weight_kg)
    const amount = timed.value ? a.seconds : a.reps
    if (!r.amount && amount) r.amount = String(amount)
    r.dirty = true
  }
  toast(t('advice.applied'))
}

function addRow() {
  const next = Math.max(0, ...rows.map((r) => r.set_no)) + 1
  rows.push({ set_no: next, weight: '', amount: '', rpe: '', savedId: null, dirty: false, busy: false })
}

/** Delete a set: removes it from the log; extra rows disappear, planned rows are cleared. */
async function removeRow(row: Row) {
  if (row.savedId) {
    if (!confirm(`${t('workout.deleteSet')}?`)) return
    row.busy = true
    try {
      await api(`/sets/${row.savedId}`, 'DELETE')
      emit('deleted', row.savedId)
    } catch (e) {
      toast.error(firstError(e))
      row.busy = false
      return
    }
  }
  if (row.set_no > props.slot.sets) {
    rows.splice(rows.indexOf(row), 1)
  } else {
    Object.assign(row, { weight: '', amount: '', rpe: '', savedId: null, dirty: false, busy: false })
  }
}

const rpeOptions = ['', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10']
const restLabel = computed(() => {
  const { rest_min: a, rest_max: b } = props.slot
  if (a == null) return ''
  return `${a === b || b == null ? a : `${a}–${b}`} ${t('common.min')}`
})
const note = computed(() => l(props.slot, 'notes'))
const subNote = computed(() => {
  const s = props.slot.exercise.substitutes?.find((x) => x.id === exerciseId.value)
  return s ? l(s, 'note') : ''
})
const savedCount = computed(() => rows.filter((r) => r.savedId && !r.dirty).length)
const done = computed(() => rows.length > 0 && savedCount.value === rows.length)
</script>

<template>
  <Card class="gap-0 overflow-hidden py-0 transition-all duration-300"
    :class="done ? 'border-success/60 shadow-md shadow-success/10' : ''">
    <CardHeader class="space-y-1.5 p-4 pb-3">
      <div class="flex items-start justify-between gap-2">
        <div class="flex min-w-0 items-start gap-2.5">
          <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums transition-colors duration-300"
            :class="done ? 'bg-success text-white' : 'bg-primary/10 text-primary'">
            <CheckCircle2 v-if="done" class="pop size-4" />
            <template v-else>{{ slot.position }}</template>
          </span>
          <div class="min-w-0">
            <h3 class="font-semibold leading-tight">{{ l(exercise, 'name') }}</h3>
            <p v-if="locale !== 'en' && exercise.name_ru" class="text-xs text-muted-foreground">{{ exercise.name_en }}</p>
          </div>
        </div>
        <div class="flex shrink-0 gap-0.5">
          <a :href="exercise.video_url || youtubeSearch(exercise.name_en)" target="_blank" rel="noopener"
            class="rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground active:bg-accent" :aria-label="$t('workout.video')"><PlayCircle class="size-5" /></a>
          <RouterLink :to="`/exercises/${exercise.id}`" class="rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground active:bg-accent"
            :aria-label="$t('workout.history')"><LineChart class="size-5" /></RouterLink>
        </div>
      </div>
      <div class="flex flex-wrap gap-1.5 text-xs tabular-nums">
        <span class="rounded-full bg-primary/10 px-2 py-0.5 font-semibold text-primary">{{ fmtTarget(slot, $t('common.sec')) }}</span>
        <span v-if="slot.target_rpe" class="rounded-full bg-muted px-2 py-0.5">RPE {{ slot.target_rpe }}</span>
        <span v-if="slot.load_pct" class="rounded-full bg-muted px-2 py-0.5">{{ slot.load_pct }}% 1RM</span>
        <span v-if="slot.warmup_sets" class="rounded-full bg-warning/15 px-2 py-0.5 text-warning">+{{ slot.warmup_sets }} {{ $t('workout.warmupShort') }}</span>
        <span v-if="restLabel" class="rounded-full bg-muted px-2 py-0.5">{{ $t('workout.rest') }} {{ restLabel }}</span>
        <span class="ml-auto rounded-full px-2 py-0.5 text-muted-foreground">{{ savedCount }}/{{ rows.length }}</span>
      </div>
      <p v-if="note" class="text-xs leading-relaxed text-muted-foreground">{{ note }}</p>
      <label v-if="options.length > 1" class="flex items-center gap-2 text-sm">
        <Repeat class="size-4 text-muted-foreground" />
        <select v-model.number="exerciseId" class="min-w-0 flex-1 rounded-md border bg-background px-2 py-1.5 text-sm">
          <option v-for="(o, i) in options" :key="o.id" :value="o.id">
            {{ i === 0 ? `${$t('workout.original')}: ` : '' }}{{ l(o, 'name') }}
          </option>
        </select>
      </label>
      <p v-if="subNote" class="text-xs font-medium text-warning">{{ subNote }}</p>
      <AdviceBox v-if="advice" :advice="advice" @apply="applyAdvice" />
      <WarmupPanel v-if="!timed" :work="workWeight" :count="warmupCount" :equipment="exercise.equipment" />
      <p class="rounded-md bg-muted/60 px-2.5 py-1.5 text-xs text-muted-foreground">
        {{ $t('workout.previous') }}:
        <span v-if="prev" class="font-medium tabular-nums text-foreground">{{ fmtSets(prev.sets, $t('common.sec')) }}</span>
        <span v-else>{{ $t('workout.noPrevious') }}</span>
      </p>
    </CardHeader>

    <CardContent class="space-y-2 p-4 pt-1">
      <div class="grid grid-cols-[1.25rem_1fr_1fr_4rem_2.75rem_1.75rem] items-center gap-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">
        <span>#</span>
        <span>{{ $t('common.kg') }}</span>
        <span>{{ timed ? $t('workout.seconds') : $t('workout.reps') }}</span>
        <span>{{ $t('workout.rpe') }}</span>
        <span />
        <span />
      </div>
      <TransitionGroup name="row" tag="div" class="relative space-y-2">
        <div v-for="row in rows" :key="row.set_no"
          class="grid grid-cols-[1.25rem_1fr_1fr_4rem_2.75rem_1.75rem] items-center gap-1.5 rounded-lg transition-colors duration-300"
          :class="row.savedId && !row.dirty ? 'bg-success/5' : ''">
          <span class="grid place-items-center text-sm font-medium tabular-nums text-muted-foreground">
            <Trophy v-if="row.record" class="pop size-4 text-warning" />
            <template v-else>{{ row.set_no }}</template>
          </span>
          <input v-model="row.weight" inputmode="decimal" :placeholder="hint(row, 'weight')"
            class="h-11 w-full rounded-lg border bg-background px-1 text-center text-base tabular-nums outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30"
            @input="row.dirty = true" />
          <input v-model="row.amount" inputmode="numeric" :placeholder="hint(row, 'amount')"
            class="h-11 w-full rounded-lg border bg-background px-1 text-center text-base tabular-nums outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30"
            @input="row.dirty = true" />
          <select v-model="row.rpe"
            class="h-11 w-full rounded-lg border bg-background px-0.5 text-center text-base outline-none focus:border-primary focus:ring-2 focus:ring-primary/30"
            @change="row.dirty = true">
            <option v-for="o in rpeOptions" :key="o" :value="o">{{ o || '–' }}</option>
          </select>
          <button type="button" :disabled="row.busy" :aria-label="$t('common.save')"
            class="flex h-11 items-center justify-center rounded-lg border transition-all duration-200 active:scale-90"
            :class="row.savedId && !row.dirty
              ? 'border-success bg-success text-white shadow-sm shadow-success/30'
              : row.dirty ? 'border-primary text-primary' : 'text-muted-foreground'"
            @click="save(row)">
            <Check class="size-5" :class="{ pop: row.savedId && !row.dirty }" />
          </button>
          <button type="button" :disabled="row.busy" :aria-label="$t('workout.deleteSet')"
            class="flex h-11 items-center justify-center rounded-lg text-muted-foreground/60 transition-colors hover:text-destructive active:scale-90"
            @click="removeRow(row)">
            <X class="size-4" />
          </button>
        </div>
      </TransitionGroup>
      <button type="button"
        class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed py-2 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-primary active:scale-[0.99]"
        @click="addRow">
        <Plus class="size-4" /> {{ $t('workout.addSet') }}
      </button>
    </CardContent>
  </Card>
</template>
