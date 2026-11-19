<script setup lang="ts">
import NumericInput from '@/components/NumericInput.vue'
import { LIMITS, inRange } from '@/lib/numbers'
import { computed, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { ArrowDown, ArrowUp, ChevronLeft, Copy, Lock, Minus, MoreHorizontal, Plus, Save, StickyNote, Trash2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { confirmDialog } from '@/composables/useConfirm'
import ExercisePicker from '@/components/ExercisePicker.vue'
import { api, firstError } from '@/lib/api'
import { useExercises } from '@/stores/exercises'
import { useLocalized } from '@/composables/useLocalized'
import type { Exercise, Program } from '@/types'

const props = defineProps<{ id?: string }>()
const router = useRouter()
const { t } = useI18n()
const { l, locale } = useLocalized()
const exercises = useExercises()

interface BRow {
  key: number; id?: number; exercise_id: number; sets: number; reps: number | null; seconds: number | null
  reps_max: number | null; reps_text: string | null; warmup_sets: number | null; load_pct: number | null
  target_rpe: number | null; rest_min: number | null; rest_max: number | null
  notes_ru: string | null; notes_en: string | null; showNote: boolean
}
interface BDay { key: number; id?: number; title_ru: string | null; title_en: string | null; used: boolean; exercises: BRow[] }
interface BWeek { key: number; id?: number; block: string | null; days: BDay[] }

let seq = 0
const key = () => ++seq

const form = reactive({
  name_ru: '' as string,
  name_en: '' as string,
  description_ru: '' as string,
  description_en: '' as string,
  weeks: [] as BWeek[],
})
const weekIdx = ref(0)
const loading = ref(!!props.id)
const saving = ref(false)
const snapshot = ref('')
const picker = ref(false)
let pickTarget: { day: BDay; row?: BRow } | null = null

const isNew = computed(() => !props.id)
const week = computed(() => form.weeks[weekIdx.value])
const other = computed(() => (locale.value === 'ru' ? 'en' : 'ru'))
const nameField = computed(() => `name_${locale.value}` as 'name_ru' | 'name_en')
const otherNameField = computed(() => `name_${other.value}` as 'name_ru' | 'name_en')
const descField = computed(() => `description_${locale.value}` as 'description_ru' | 'description_en')
const titleField = computed(() => `title_${locale.value}` as 'title_ru' | 'title_en')
const notesField = computed(() => `notes_${locale.value}` as 'notes_ru' | 'notes_en')

const serialize = () => JSON.stringify({ ...form, weeks: form.weeks.map(stripWeek) })
const dirty = computed(() => !loading.value && serialize() !== snapshot.value)

function emptyDay(n: number): BDay {
  return { key: key(), title_ru: locale.value === 'ru' ? `День ${n}` : null, title_en: locale.value === 'en' ? `Day ${n}` : null, used: false, exercises: [] }
}

function cloneDay(d: BDay, keepIds = false): BDay {
  return {
    ...d, key: key(), id: keepIds ? d.id : undefined, used: keepIds ? d.used : false,
    exercises: d.exercises.map((r) => ({ ...r, key: key(), id: keepIds ? r.id : undefined, showNote: false })),
  }
}

// A new program starts with one week / one day right away (before the first render).
if (!props.id) {
  form.weeks = [{ key: key(), block: null, days: [emptyDay(1)] }]
  snapshot.value = serialize()
}

onMounted(async () => {
  void exercises.load()
  if (!props.id) return
  try {
    const p = await api<Program>(`/programs/${props.id}`)
    if (p.kind !== 'custom') {
      router.replace(`/programs/${p.id}`)
      return
    }
    form.name_ru = p.name_ru ?? ''
    form.name_en = p.name_en ?? ''
    form.description_ru = p.description_ru ?? ''
    form.description_en = p.description_en ?? ''
    form.weeks = p.weeks.map((w) => ({
      key: key(), id: w.id, block: w.block,
      days: w.days.map((d) => ({
        key: key(), id: d.id, title_ru: d.title_ru, title_en: d.title_en, used: !!d.used,
        exercises: d.exercises.map((x) => ({
          key: key(), id: x.id, exercise_id: x.exercise.id, sets: x.sets, reps: x.reps, seconds: x.seconds,
          reps_max: x.reps_max, reps_text: x.reps_text, warmup_sets: x.warmup_sets, load_pct: x.load_pct,
          target_rpe: x.target_rpe, rest_min: x.rest_min, rest_max: x.rest_max,
          notes_ru: x.notes_ru, notes_en: x.notes_en, showNote: false,
        })),
      })),
    }))
    snapshot.value = serialize()
  } catch (e) {
    toast.error(firstError(e))
    router.replace('/programs')
  } finally {
    loading.value = false
  }
})

onBeforeRouteLeave(async () => (dirty.value && !saving.value
  ? confirmDialog({ title: t('builder.unsaved'), confirmText: t('common.confirm'), destructive: true })
  : true))

// ---------- weeks ----------
function addWeek() {
  const last = form.weeks.at(-1)
  form.weeks.push({ key: key(), block: last?.block ?? null, days: last ? last.days.map((d) => cloneDay(d)) : [emptyDay(1)] })
  weekIdx.value = form.weeks.length - 1
}
const weekUsed = (w: BWeek) => w.days.some((d) => d.used)
async function removeWeek() {
  if (form.weeks.length < 2 || weekUsed(week.value)) return
  if (!(await confirmDialog({ title: t('builder.removeWeekConfirm', { n: weekIdx.value + 1 }), confirmText: t('common.delete'), destructive: true }))) return
  form.weeks.splice(weekIdx.value, 1)
  weekIdx.value = Math.max(0, weekIdx.value - 1)
}
/** Copy the current week into all others, keeping ids by position so history stays attached. */
async function copyToAll() {
  if (!(await confirmDialog({ title: t('builder.copyToAllConfirm', { n: weekIdx.value + 1 }), confirmText: t('builder.copyToAll') }))) return
  const src = week.value
  form.weeks.forEach((w, wi) => {
    if (wi === weekIdx.value) return
    const days = src.days.map((sd, i) => {
      const old = w.days[i]
      const copy = cloneDay(sd)
      if (old) {
        copy.id = old.id
        copy.used = old.used
        copy.exercises.forEach((r, ri) => { r.id = old.exercises[ri]?.id })
      }
      return copy
    })
    // Days that already have workouts are kept even if the source week is shorter.
    w.days = [...days, ...w.days.slice(days.length).filter((d) => d.used)]
  })
  toast.success(t('builder.copied'))
}

// ---------- days ----------
function addDay() {
  if (week.value.days.length >= 7) return
  week.value.days.push(emptyDay(week.value.days.length + 1))
}
function removeDay(i: number) {
  const d = week.value.days[i]
  if (d.used) return toast.info(t('builder.used'))
  week.value.days.splice(i, 1)
}

// ---------- exercises ----------
function openPicker(day: BDay, row?: BRow) {
  pickTarget = { day, row }
  picker.value = true
}
function onPick(e: Exercise) {
  if (!pickTarget) return
  if (pickTarget.row) {
    pickTarget.row.exercise_id = e.id
  } else {
    pickTarget.day.exercises.push({
      key: key(), exercise_id: e.id, sets: 3, reps: e.muscles.includes('abs') ? 15 : 10, seconds: null,
      reps_max: null, reps_text: null, warmup_sets: null, load_pct: null,
      target_rpe: 8, rest_min: 2, rest_max: 3, notes_ru: null, notes_en: null, showNote: false,
    })
  }
}
function move(day: BDay, i: number, dir: -1 | 1) {
  const j = i + dir
  if (j < 0 || j >= day.exercises.length) return
  const [r] = day.exercises.splice(i, 1)
  day.exercises.splice(j, 0, r)
}
function toggleTimed(r: BRow) {
  if (r.seconds !== null) { r.reps = 10; r.seconds = null } else { r.seconds = 30; r.reps = null; r.reps_max = null }
}
/** Empty number inputs give '' — send null instead. */
const n = (v: unknown) => (v === '' || v === undefined ? null : (v as number | null))
const exName = (id: number) => l(exercises.byId(id), 'name') || '…'

// ---------- save ----------
function stripWeek(w: BWeek) {
  return {
    id: w.id, block: w.block,
    days: w.days.map((d) => ({
      id: d.id, title_ru: d.title_ru || null, title_en: d.title_en || null,
      exercises: d.exercises.map(({ key: _k, showNote: _s, ...r }) => ({
        ...r, reps: n(r.reps), reps_max: n(r.reps_max), seconds: n(r.seconds),
      })),
    })),
  }
}

async function save() {
  if (!form.name_ru.trim() && !form.name_en.trim()) return toast.error(t('builder.nameRequired'))
  for (const [wi, w] of form.weeks.entries()) {
    const di = w.days.findIndex((d) => !d.exercises.length)
    if (di >= 0) {
      weekIdx.value = wi
      return toast.error(t('builder.dayEmpty', { w: wi + 1, d: di + 1 }))
    }
    // Same limits as the API, with a message pointing at the exact exercise.
    for (const [dj, d] of w.days.entries()) {
      for (const r of d.exercises) {
        const where = { w: wi + 1, d: dj + 1, name: exName(r.exercise_id) }
        const fail = (key: string, params: Record<string, unknown> = {}) => {
          weekIdx.value = wi
          toast.error(`${t('builder.invalidAt', where)}: ${t(key, params)}`)
          return true
        }
        if (r.seconds !== null) {
          if (!inRange(n(r.seconds), 1, LIMITS.seconds, true)) return fail('validation.planSeconds', { max: LIMITS.seconds })
        } else {
          if (!inRange(n(r.reps), 1, LIMITS.planReps, true)) return fail('validation.planReps', { max: LIMITS.planReps })
          const rmax = n(r.reps_max)
          if (rmax !== null && (!inRange(rmax, 1, LIMITS.planReps, true) || rmax < (r.reps ?? 0))) return fail('validation.repsMax')
        }
      }
    }
  }
  saving.value = true
  try {
    const body = {
      name_ru: form.name_ru.trim() || null, name_en: form.name_en.trim() || null,
      description_ru: form.description_ru.trim() || null, description_en: form.description_en.trim() || null,
      weeks: form.weeks.map(stripWeek),
    }
    const p = props.id
      ? await api<Program>(`/programs/${props.id}`, 'PUT', body)
      : await api<Program>('/programs', 'POST', body)
    snapshot.value = serialize()
    toast.success(t('builder.saved'))
    router.replace(`/programs/${p.id}`)
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    saving.value = false
  }
}

const rpeOptions = [null, 6, 6.5, 7, 7.5, 8, 8.5, 9, 9.5, 10]
const restOptions = [0, 1, 2, 3, 4, 5]
</script>

<template>
  <div class="pb-28">
    <header class="sticky top-[env(safe-area-inset-top)] z-30 -mx-4 -mt-4 mb-4 flex items-center gap-2 bg-background/80 px-4 py-2 backdrop-blur-xl backdrop-saturate-150">
      <Button variant="ghost" size="icon" class="-ml-2 rounded-full" :aria-label="$t('common.back')" @click="router.back()">
        <ChevronLeft class="size-6" />
      </Button>
      <h1 class="flex-1 truncate text-[17px] font-semibold">{{ isNew ? $t('builder.newTitle') : $t('builder.editTitle') }}</h1>
      <span v-if="dirty" class="size-2 animate-pulse rounded-full bg-warning" />
    </header>

    <div v-if="loading || !week" class="space-y-3">
      <Skeleton class="h-44 rounded-2xl" />
      <Skeleton class="h-10 rounded-xl" />
      <Skeleton v-for="i in 2" :key="i" class="h-56 rounded-2xl" />
    </div>

    <div v-else class="space-y-5">
      <!-- Name & description -->
      <Card class="rise gap-4 p-4">
        <div class="space-y-2">
          <Label for="b-name">{{ $t('builder.name') }}</Label>
          <Input id="b-name" v-model="form[nameField]" maxlength="150" class="font-semibold" />
        </div>
        <div class="space-y-2">
          <Label for="b-name-other" class="text-xs font-normal text-muted-foreground">{{ other === 'en' ? $t('builder.nameEn') : $t('builder.nameRu') }}</Label>
          <Input id="b-name-other" v-model="form[otherNameField]" maxlength="150" class="h-10 text-sm" />
        </div>
        <div class="space-y-2">
          <Label for="b-desc">{{ $t('builder.description') }}</Label>
          <Textarea id="b-desc" v-model="form[descField]" rows="2" maxlength="5000" class="text-sm" />
        </div>
      </Card>

      <!-- Weeks -->
      <section class="space-y-3">
        <div class="flex items-center gap-2">
          <Tabs :model-value="weekIdx" class="min-w-0 flex-1" @update:model-value="(v) => (weekIdx = Number(v))">
            <TabsList class="no-scrollbar w-full justify-start overflow-x-auto">
              <TabsTrigger v-for="(w, i) in form.weeks" :key="w.key" :value="i" class="flex-none px-3.5 tabular-nums">
                {{ $t('builder.week') }} {{ i + 1 }}
              </TabsTrigger>
            </TabsList>
          </Tabs>
          <Button variant="outline" size="icon" class="size-10 shrink-0 rounded-xl text-primary" :aria-label="$t('builder.addWeek')" @click="addWeek">
            <Plus />
          </Button>
          <DropdownMenu v-if="form.weeks.length > 1">
            <DropdownMenuTrigger as-child>
              <Button variant="outline" size="icon" class="size-10 shrink-0 rounded-xl" :aria-label="$t('common.more')"><MoreHorizontal /></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuItem @select="copyToAll"><Copy /> {{ $t('builder.copyToAll') }}</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant="destructive" :disabled="weekUsed(week)" @select="removeWeek"><Trash2 /> {{ $t('builder.removeWeek') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <!-- Days of the selected week -->
        <TransitionGroup name="row" tag="div" class="relative space-y-3">
          <Card v-for="(day, di) in week.days" :key="day.key" class="gap-0 overflow-hidden py-0">
            <div class="flex items-center gap-2 border-b bg-muted/30 px-3 py-2">
              <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/12 text-sm font-bold text-primary">{{ di + 1 }}</span>
              <input v-model="day[titleField]" :placeholder="$t('builder.dayTitle')" maxlength="150"
                class="h-9 min-w-0 flex-1 rounded-lg bg-transparent px-2 font-semibold outline-none transition-shadow placeholder:text-muted-foreground/60 focus:bg-background focus:ring-[3px] focus:ring-ring/40" />
              <Lock v-if="day.used" class="mx-2 size-4 text-muted-foreground" :aria-label="$t('builder.used')" />
              <Button v-else variant="ghost" size="icon-sm" class="text-muted-foreground hover:text-destructive"
                :aria-label="$t('builder.removeDay')" @click="removeDay(di)">
                <Trash2 />
              </Button>
            </div>

            <TransitionGroup name="row" tag="div" class="relative divide-y">
              <div v-for="(r, ri) in day.exercises" :key="r.key" class="space-y-3 bg-card p-3">
                <div class="flex items-center gap-1">
                  <span class="w-5 shrink-0 text-center text-xs font-semibold tabular-nums text-muted-foreground">{{ ri + 1 }}</span>
                  <button type="button" class="min-w-0 flex-1 truncate rounded-lg px-2 py-1.5 text-left font-semibold transition-colors hover:bg-accent active:bg-accent"
                    @click="openPicker(day, r)">
                    {{ exName(r.exercise_id) }}
                  </button>
                  <div class="flex shrink-0 text-muted-foreground">
                    <Button variant="ghost" size="icon-sm" :disabled="ri === 0" :aria-label="$t('builder.moveUp')" @click="move(day, ri, -1)"><ArrowUp /></Button>
                    <Button variant="ghost" size="icon-sm" :disabled="ri === day.exercises.length - 1" :aria-label="$t('builder.moveDown')" @click="move(day, ri, 1)"><ArrowDown /></Button>
                    <Button variant="ghost" size="icon-sm" :class="r.showNote || r[notesField] ? 'text-primary' : ''"
                      :aria-label="$t('builder.note')" @click="r.showNote = !r.showNote"><StickyNote /></Button>
                    <Button variant="ghost" size="icon-sm" class="hover:text-destructive" :aria-label="$t('common.delete')" @click="day.exercises.splice(ri, 1)"><Trash2 /></Button>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-x-2 gap-y-2.5 pl-6 text-xs">
                  <div class="space-y-1">
                    <span class="font-medium text-muted-foreground">{{ $t('builder.sets') }}</span>
                    <div class="flex h-10 items-center overflow-hidden rounded-lg border bg-background dark:bg-input/20">
                      <button type="button" class="grid h-full w-9 place-items-center text-muted-foreground active:bg-accent" @click="r.sets = Math.max(1, r.sets - 1)"><Minus class="size-4" /></button>
                      <span class="flex-1 text-center text-base font-semibold tabular-nums">{{ r.sets }}</span>
                      <button type="button" class="grid h-full w-9 place-items-center text-muted-foreground active:bg-accent" @click="r.sets = Math.min(20, r.sets + 1)"><Plus class="size-4" /></button>
                    </div>
                  </div>
                  <div class="space-y-1">
                    <button type="button" class="font-medium text-primary" @click="toggleTimed(r)">
                      {{ r.seconds !== null ? $t('builder.seconds') : $t('builder.reps') }} ⇄
                    </button>
                    <div v-if="r.seconds === null" class="flex items-center gap-1">
                      <NumericInput v-model="r.reps" as-number :max="LIMITS.planReps" class="h-10 px-1 text-center tabular-nums" />
                      <span class="text-muted-foreground">–</span>
                      <NumericInput v-model="r.reps_max" as-number :max="LIMITS.planReps" :placeholder="$t('builder.repsMax')"
                        class="h-10 px-1 text-center tabular-nums placeholder:text-xs" />
                    </div>
                    <NumericInput v-else v-model="r.seconds" as-number :max="LIMITS.seconds" class="h-10 text-center tabular-nums" />
                  </div>
                  <div class="space-y-1">
                    <span class="font-medium text-muted-foreground">{{ $t('builder.rpe') }}</span>
                    <NativeSelect v-model="r.target_rpe" select-class="h-10">
                      <option v-for="o in rpeOptions" :key="String(o)" :value="o">{{ o ?? '–' }}</option>
                    </NativeSelect>
                  </div>
                  <div class="space-y-1">
                    <span class="font-medium text-muted-foreground">{{ $t('builder.rest') }}</span>
                    <div class="flex items-center gap-1">
                      <NativeSelect v-model="r.rest_min" hide-icon select-class="h-10">
                        <option v-for="o in restOptions" :key="o" :value="o">{{ o }}</option>
                      </NativeSelect>
                      <span class="text-muted-foreground">–</span>
                      <NativeSelect v-model="r.rest_max" hide-icon select-class="h-10">
                        <option v-for="o in restOptions" :key="o" :value="o">{{ o }}</option>
                      </NativeSelect>
                    </div>
                  </div>
                </div>
                <Textarea v-if="r.showNote" v-model="r[notesField]" rows="2" maxlength="1000" :placeholder="$t('builder.note')"
                  class="ml-6 w-[calc(100%-1.5rem)] text-sm" />
              </div>
            </TransitionGroup>

            <button type="button"
              class="flex w-full items-center justify-center gap-1.5 border-t py-3 text-sm font-semibold text-primary transition-colors hover:bg-primary/5 active:bg-primary/10"
              @click="openPicker(day)">
              <Plus class="size-4" /> {{ $t('builder.addExercise') }}
            </button>
          </Card>
        </TransitionGroup>

        <Button v-if="week.days.length < 7" variant="ghost" size="lg"
          class="h-14 w-full rounded-2xl border border-dashed text-muted-foreground hover:border-primary hover:text-primary"
          @click="addDay">
          <Plus /> {{ $t('builder.addDay') }}
        </Button>
      </section>
    </div>

    <!-- Save bar -->
    <div class="fixed inset-x-0 bottom-0 z-40 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
      <div class="mx-auto flex max-w-xl items-center gap-3 rounded-3xl border bg-card/90 p-2 pl-4 shadow-2xl shadow-black/15 backdrop-blur-xl dark:shadow-black/50">
        <div class="flex flex-1 flex-wrap gap-1.5">
          <Badge variant="secondary">{{ $t('builder.weeks', { n: form.weeks.length }) }}</Badge>
          <Badge variant="secondary">{{ $t('programs.daysPerWeek', { n: Math.max(0, ...form.weeks.map((w) => w.days.length)) }) }}</Badge>
        </div>
        <Button size="lg" class="px-6" :disabled="saving || loading" @click="save">
          <Save /> {{ $t('builder.save') }}
        </Button>
      </div>
    </div>

    <ExercisePicker v-model:open="picker" @select="onPick" />
  </div>
</template>
