<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { Check, CheckCircle2, ChevronDown, CloudUpload, ChevronUp, LineChart, Lock, Pencil, Plus, PlayCircle, Repeat, SkipForward, Trophy, Undo2, X } from '@lucide/vue'
import AdviceBox from '@/components/AdviceBox.vue'
import WarmupPanel from '@/components/WarmupPanel.vue'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import NumericInput from '@/components/NumericInput.vue'
import { NativeSelect } from '@/components/ui/native-select'
import { confirmDialog } from '@/composables/useConfirm'
import { api, firstError, isNetworkError } from '@/lib/api'
import { dequeue, enqueue, flush, onSettled, outbox, pending, setKey, type SetBody } from '@/lib/outbox'
import { LIMITS, inRange, parseNumber } from '@/lib/numbers'
import { fmtNum, fmtSets, fmtTarget, youtubeSearch } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { Exercise, LastPerformance, SetLog, Slot } from '@/types'

const props = defineProps<{
  slot: Slot
  sessionId: number
  sets: SetLog[]
  previous: Record<string, LastPerformance>
  /** Collapsed "skipped" state; logged sets are kept. */
  skipped?: boolean
  /** Skip / restore is only offered while the workout is in progress. */
  canSkip?: boolean
  /** Focus mode (workout in progress): highlight the current exercise, collapse finished ones, dim the rest. */
  focusMode?: boolean
  current?: boolean
}>()
const emit = defineEmits<{
  /** Server accepted the set (possibly later, after being queued offline). */
  saved: [set: SetLog]
  /** User ticked a set — fires immediately, online or not (starts the rest timer). */
  confirmed: [firstSave: boolean]
  deleted: [setId: number]; dirty: [count: number]
  skip: [skipped: boolean]; done: [done: boolean]; focus: []
}>()

const { t, locale } = useI18n()
const { l } = useLocalized()

interface Row {
  set_no: number; weight: string; amount: string; rpe: string
  savedId: number | null
  /** Ticked but not yet accepted by the server (offline queue). Counts as done on screen. */
  pending: boolean
  dirty: boolean; busy: boolean; record?: boolean
}

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

const keyFor = (setNo: number) => setKey(props.sessionId, { day_exercise_id: props.slot.id, exercise_id: exerciseId.value, set_no: setNo })

function buildRows() {
  const logged = props.sets.filter((s) => s.exercise_id === exerciseId.value && !s.is_warmup)
  let draft: Record<number, Partial<Row>> = {}
  try { draft = JSON.parse(localStorage.getItem(draftKey.value) ?? '{}') } catch { /* ignore */ }
  const queued = new Map<number, SetBody>()
  for (let n = 1; n <= 50; n++) {
    const q = pending(keyFor(n))
    if (q) queued.set(n, q.body)
  }
  const count = Math.max(props.slot.sets, ...logged.map((s) => s.set_no), ...Object.keys(draft).map(Number), ...queued.keys())
  rows.splice(0, rows.length)
  for (let n = 1; n <= count; n++) {
    // Newest wins: unsaved draft → queued offline → saved on the server.
    const s = queued.get(n) ?? logged.find((x) => x.set_no === n)
    const d = draft[n]
    rows.push({
      set_no: n,
      weight: d?.weight ?? (s?.weight_kg != null ? fmtNum(s.weight_kg) : ''),
      amount: d?.amount ?? String((timed.value ? s?.seconds : s?.reps) ?? ''),
      rpe: d?.rpe ?? (s?.rpe != null ? String(s.rpe) : ''),
      savedId: logged.find((x) => x.set_no === n)?.id ?? null,
      pending: queued.has(n),
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

const num = parseNumber
const amountMax = computed(() => (timed.value ? LIMITS.seconds : LIMITS.reps))

async function save(row: Row) {
  // Empty fields fall back to the placeholder so "same as last time" is one tap.
  const weight = num(row.weight || hint(row, 'weight'))
  const amount = num(row.amount || hint(row, 'amount'))
  // The fields can't hold anything else, but placeholders / old drafts could — same limits as the API.
  if (!inRange(amount, 0, amountMax.value, true)) {
    toast.error(timed.value ? t('validation.seconds', { max: LIMITS.seconds }) : t('validation.reps', { max: LIMITS.reps }))
    return
  }
  if (weight !== null && !inRange(weight, 0, LIMITS.weightKg)) {
    toast.error(t('validation.weight', { max: LIMITS.weightKg }))
    return
  }
  const firstSave = row.savedId === null && !row.pending
  // Queue first, then send: with no network the set stays on the phone and syncs later.
  enqueue(props.sessionId, {
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
  row.pending = true
  row.dirty = false
  emit('confirmed', firstSave)
  row.busy = true
  await flush()
  row.busy = false
  // Still queued after our own send attempt (not just waiting behind another card's sync) → we're offline.
  if (row.pending && firstSave && !outbox.syncing) toast(t('offline.queued'), { id: 'offline-queued' })
}

// Result for this card's sets — right away when online, or minutes later after an offline stretch.
const stopListening = onSettled((item, result) => {
  const b = item.body
  if (item.sessionId !== props.sessionId || b.day_exercise_id !== props.slot.id || b.exercise_id !== exerciseId.value) return
  const row = rows.find((r) => r.set_no === b.set_no)
  if (!row) return
  row.pending = false
  if ('error' in result) {
    row.dirty = true // back to "not saved" so the user sees it and can fix / retry
    toast.error(t('offline.rejected', { name: l(exercise.value, 'name'), n: b.set_no, error: firstError(result.error) }))
    return
  }
  const set = result.set
  row.savedId = set.id
  row.record = !!set.records?.length
  if (set.records?.length) {
    toast.success(`🏆 ${t('records.new')}`, {
      description: `${l(exercise.value, 'name')}: ${set.records.map((r) => t(`records.${r.type}`, { value: fmtNum(r.value) })).join(' · ')}`,
    })
  }
  emit('saved', set)
})
onBeforeUnmount(stopListening)

/** Fill the suggestion into rows that are still empty. */
function applyAdvice() {
  const a = advice.value
  if (!a) return
  for (const r of rows) {
    if (r.savedId || r.pending) continue
    if (!r.weight && a.weight_kg) r.weight = fmtNum(a.weight_kg)
    const amount = timed.value ? a.seconds : a.reps
    if (!r.amount && amount) r.amount = String(amount)
    r.dirty = true
  }
  toast(t('advice.applied'))
}

function addRow() {
  const next = Math.max(0, ...rows.map((r) => r.set_no)) + 1
  rows.push({ set_no: next, weight: '', amount: '', rpe: '', savedId: null, pending: false, dirty: false, busy: false })
}

/** Delete a set: removes it from the log; extra rows disappear, planned rows are cleared. */
async function removeRow(row: Row) {
  if (row.savedId || row.pending) {
    if (!(await confirmDialog({ title: `${t('workout.deleteSet')} ${row.set_no}?`, confirmText: t('common.delete'), destructive: true }))) return
  }
  if (row.savedId) {
    row.busy = true
    try {
      await api(`/sets/${row.savedId}`, 'DELETE')
      emit('deleted', row.savedId)
    } catch (e) {
      // Deleting needs the server; the set stays as it was.
      toast.error(isNetworkError(e) ? t('offline.needNetwork') : firstError(e))
      row.busy = false
      return
    }
  }
  dequeue(keyFor(row.set_no)) // a set that never reached the server is just dropped from the queue
  if (row.set_no > props.slot.sets) {
    rows.splice(rows.indexOf(row), 1)
  } else {
    Object.assign(row, { weight: '', amount: '', rpe: '', savedId: null, pending: false, dirty: false, busy: false })
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
const isSaved = (r: Row) => (r.savedId !== null || r.pending) && !r.dirty
const savedCount = computed(() => rows.filter(isSaved).length)
const done = computed(() => rows.length > 0 && savedCount.value === rows.length)
/** All sets confirmed → card is locked until the user explicitly unlocks it. */
const editing = ref(false)
const locked = computed(() => done.value && !editing.value)
// Re-confirming every set (or switching exercise) locks the card again.
watch(done, (d) => { if (!d) editing.value = false })
watch(exerciseId, () => (editing.value = false))

/** A finished exercise collapses to a one-line summary; tap to expand it again. */
const expanded = ref(false)
watch(done, (d) => {
  if (d) expanded.value = false
  emit('done', d)
}, { immediate: true })
const collapsed = computed(() => props.focusMode && done.value && !expanded.value)
const dimmed = computed(() => props.focusMode && !props.current && !done.value)
// From the rows (not props.sets) so sets still waiting in the offline queue are included.
const loggedSummary = computed(() => rows.filter(isSaved)
  .map((r) => {
    const amount = timed.value ? `${r.amount}${t('common.sec')}` : r.amount
    return r.weight ? `${r.weight}×${amount}` : amount
  })
  .join(' · '))
</script>

<template>
  <Card v-if="skipped" class="gap-0 border-dashed bg-muted/30 py-0 shadow-none">
    <div class="flex items-center gap-3 p-3.5">
      <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground">
        <SkipForward class="size-4" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="truncate font-semibold text-muted-foreground line-through decoration-muted-foreground/40">{{ l(exercise, 'name') }}</p>
        <p class="text-xs text-muted-foreground">
          {{ $t('workout.exerciseSkipped') }}<template v-if="savedCount"> · {{ savedCount }} {{ $t('history.sets') }}</template>
        </p>
      </div>
      <Button v-if="canSkip" variant="ghost" size="sm" class="shrink-0 text-muted-foreground hover:text-primary" @click="emit('skip', false)">
        <Undo2 /> {{ $t('workout.restoreExercise') }}
      </Button>
    </div>
  </Card>

  <Card v-else-if="collapsed" class="gap-0 border-success/40 bg-success/5 py-0 shadow-none">
    <button type="button" class="flex w-full items-center gap-3 p-3.5 text-left" @click="expanded = true">
      <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-success text-white">
        <CheckCircle2 class="size-4" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="truncate font-semibold">{{ l(exercise, 'name') }}</p>
        <p class="truncate text-xs font-medium tabular-nums text-success">{{ loggedSummary }}</p>
      </div>
      <ChevronDown class="size-5 shrink-0 text-muted-foreground" />
    </button>
  </Card>

  <Card v-else class="gap-0 overflow-hidden py-0 transition-all duration-300"
    :class="[
      done ? 'border-success/50 shadow-md shadow-success/10' : '',
      current ? 'border-primary/60 shadow-lg shadow-primary/15 ring-2 ring-primary/25' : '',
      /* .rise animates opacity with fill-mode both, so dim the children rather than the card */
      dimmed ? '[&>*]:opacity-55 [&>*]:transition-opacity' : '',
    ]"
    @pointerdown="emit('focus')" @focusin="emit('focus')">
    <CardHeader class="gap-2.5 p-4 pb-3">
      <div class="flex items-start justify-between gap-2">
        <div class="flex min-w-0 items-start gap-3">
          <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-xl text-sm font-bold tabular-nums transition-colors duration-300"
            :class="done ? 'bg-success text-white' : 'bg-primary/12 text-primary'">
            <CheckCircle2 v-if="done" class="pop size-4" />
            <template v-else>{{ slot.position }}</template>
          </span>
          <div class="min-w-0">
            <h3 class="text-[17px] font-semibold leading-tight">{{ l(exercise, 'name') }}</h3>
            <p v-if="locale !== 'en' && exercise.name_ru" class="text-xs text-muted-foreground">{{ exercise.name_en }}</p>
          </div>
        </div>
        <div class="-mt-1 -mr-1.5 flex shrink-0">
          <Button as-child variant="ghost" size="icon" class="rounded-full text-muted-foreground">
            <a :href="exercise.video_url || youtubeSearch(exercise.name_en)" target="_blank" rel="noopener" :aria-label="$t('workout.video')"><PlayCircle class="size-5" /></a>
          </Button>
          <Button as-child variant="ghost" size="icon" class="rounded-full text-muted-foreground">
            <RouterLink :to="`/exercises/${exercise.id}`" :aria-label="$t('workout.history')"><LineChart class="size-5" /></RouterLink>
          </Button>
          <Button v-if="focusMode && done" variant="ghost" size="icon" class="rounded-full text-muted-foreground" :aria-label="$t('workout.collapse')" @click="expanded = false">
            <ChevronUp class="size-5" />
          </Button>
          <Button v-if="canSkip" variant="ghost" size="icon" class="rounded-full text-muted-foreground" :aria-label="$t('workout.skipExercise')" :title="$t('workout.skipExercise')" @click="emit('skip', true)">
            <SkipForward class="size-5" />
          </Button>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-1.5">
        <Badge v-if="current" class="font-semibold">{{ $t('workout.current') }}</Badge>
        <Badge variant="soft" class="font-semibold">{{ fmtTarget(slot, $t('common.sec')) }}</Badge>
        <Badge v-if="slot.target_rpe" variant="secondary">RPE {{ slot.target_rpe }}</Badge>
        <Badge v-if="slot.load_pct" variant="secondary">{{ slot.load_pct }}% 1RM</Badge>
        <Badge v-if="slot.warmup_sets" variant="warning">+{{ slot.warmup_sets }} {{ $t('workout.warmupShort') }}</Badge>
        <Badge v-if="restLabel" variant="secondary">{{ $t('workout.rest') }} {{ restLabel }}</Badge>
        <span class="ml-auto text-xs font-semibold tabular-nums" :class="done ? 'text-success' : 'text-muted-foreground'">{{ savedCount }}/{{ rows.length }}</span>
      </div>
      <p v-if="note" class="text-[13px] leading-relaxed text-muted-foreground">{{ note }}</p>
      <div v-if="options.length > 1" class="flex items-center gap-2">
        <Repeat class="size-4 shrink-0 text-muted-foreground" />
        <NativeSelect v-model="exerciseId" select-class="h-9 text-sm">
          <option v-for="(o, i) in options" :key="o.id" :value="o.id">
            {{ i === 0 ? `${$t('workout.original')}: ` : '' }}{{ l(o, 'name') }}
          </option>
        </NativeSelect>
      </div>
      <p v-if="subNote" class="text-xs font-medium text-warning">{{ subNote }}</p>
      <AdviceBox v-if="advice" :advice="advice" @apply="applyAdvice" />
      <WarmupPanel v-if="!timed" :work="workWeight" :count="warmupCount" :equipment="exercise.equipment" />
      <p class="flex flex-wrap items-baseline gap-x-1.5 rounded-xl bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
        {{ $t('workout.previous') }}:
        <span v-if="prev" class="font-semibold tabular-nums text-foreground">{{ fmtSets(prev.sets, $t('common.sec')) }}</span>
        <span v-else>{{ $t('workout.noPrevious') }}</span>
      </p>
    </CardHeader>

    <CardContent class="space-y-2 border-t bg-muted/20 p-3 pt-2.5">
      <div class="grid grid-cols-[1.5rem_1fr_1fr_3.75rem_2.75rem_1.75rem] items-center gap-1.5 px-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
        <span class="text-center">#</span>
        <span class="text-center">{{ $t('common.kg') }}</span>
        <span class="text-center">{{ timed ? $t('workout.seconds') : $t('workout.reps') }}</span>
        <span class="text-center">{{ $t('workout.rpe') }}</span>
        <span />
        <span />
      </div>
      <TransitionGroup name="row" tag="div" class="relative space-y-1.5">
        <div v-for="row in rows" :key="row.set_no"
          class="grid grid-cols-[1.5rem_1fr_1fr_3.75rem_2.75rem_1.75rem] items-center gap-1.5 rounded-xl p-0.5 transition-colors duration-300"
          :class="isSaved(row) ? (row.pending && !row.busy ? 'bg-warning/8' : 'bg-success/8') : ''">
          <span class="grid place-items-center text-sm font-semibold tabular-nums text-muted-foreground">
            <Trophy v-if="row.record" class="pop size-4 text-warning" />
            <template v-else>{{ row.set_no }}</template>
          </span>
          <NumericInput v-model="row.weight" :max="LIMITS.weightKg" :decimals="2" :disabled="locked" :placeholder="hint(row, 'weight')"
            class="bg-background px-1 text-center font-semibold tabular-nums dark:bg-input/20" @input="row.dirty = true" />
          <NumericInput v-model="row.amount" :max="amountMax" :disabled="locked" :placeholder="hint(row, 'amount')"
            class="bg-background px-1 text-center font-semibold tabular-nums dark:bg-input/20" @input="row.dirty = true" />
          <NativeSelect v-model="row.rpe" :disabled="locked" hide-icon select-class="bg-background dark:bg-input/20" @change="row.dirty = true">
            <option v-for="o in rpeOptions" :key="o" :value="o">{{ o || '–' }}</option>
          </NativeSelect>
          <button type="button" :disabled="row.busy || locked" :aria-label="$t('common.save')"
            class="flex h-11 items-center justify-center rounded-lg border transition-all duration-200 active:scale-90 disabled:active:scale-100 [&:disabled:not(.bg-success):not(.bg-warning)]:opacity-60"
            :class="isSaved(row) && row.pending && !row.busy
              ? 'border-warning bg-warning text-white shadow-sm shadow-warning/30'
              : isSaved(row)
              ? 'border-success bg-success text-white shadow-sm shadow-success/30'
              : row.dirty ? 'border-primary bg-primary text-primary-foreground shadow-sm shadow-primary/30' : 'bg-background text-muted-foreground dark:bg-input/20'"
            @click="save(row)">
            <!-- Amber cloud = ticked, waiting for the network; turns green once the server has it -->
            <CloudUpload v-if="isSaved(row) && row.pending && !row.busy" class="size-5" :title="$t('offline.pendingHint')" />
            <Check v-else class="size-5" :class="{ pop: isSaved(row) }" />
          </button>
          <button type="button" :disabled="row.busy || locked" :aria-label="$t('workout.deleteSet')"
            class="flex h-11 items-center justify-center rounded-lg text-muted-foreground/50 transition-colors hover:text-destructive active:scale-90 disabled:invisible"
            @click="removeRow(row)">
            <X class="size-4" />
          </button>
        </div>
      </TransitionGroup>
      <Button v-if="locked" variant="ghost" class="w-full text-muted-foreground hover:text-primary" @click="editing = true">
        <Pencil /> {{ $t('workout.editSets') }}
      </Button>
      <div v-else class="flex gap-1.5">
        <Button variant="ghost" class="flex-1 border border-dashed text-muted-foreground hover:border-primary hover:text-primary" @click="addRow">
          <Plus /> {{ $t('workout.addSet') }}
        </Button>
        <Button v-if="done" variant="ghost" class="border text-muted-foreground hover:text-primary" @click="editing = false">
          <Lock /> {{ $t('workout.lockSets') }}
        </Button>
      </div>
    </CardContent>
  </Card>
</template>
