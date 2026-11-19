<script setup lang="ts">
import { computed, nextTick, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDebounceFn, useDocumentVisibility, useOnline, useWakeLock } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Ban, Bell, Check, CheckCircle2, ChevronLeft, CloudOff, CloudUpload, Flag, Loader2, MoreHorizontal, NotebookPen, Timer, Trash2, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { confirmDialog } from '@/composables/useConfirm'
import ExerciseCard from '@/components/ExerciseCard.vue'
import { api, cachedGet, firstError, isNetworkError, writeCache } from '@/lib/api'
import { flush, outbox, setKey } from '@/lib/outbox'
import { fmtDate } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import { useRestTimer } from '@/composables/useRestTimer'
import type { Session, SessionSummary, SetLog } from '@/types'

const props = defineProps<{ id: string }>()
const router = useRouter()
const { t } = useI18n()
const { l, locale } = useLocalized()
const session = ref<Session | null>(null)
const dirty = reactive<Record<number, number>>({})
const busy = ref(false)
const timer = useRestTimer()
const wakeLock = useWakeLock()
const now = ref(Date.now())
const clock = window.setInterval(() => (now.value = Date.now()), 1000)

const active = computed(() => session.value?.status === 'in_progress')

onMounted(async () => {
  try {
    const { data, offline } = await cachedGet<Session>(`/sessions/${props.id}`)
    session.value = data
    if (offline) toast(t('offline.cachedCopy'), { id: 'offline-copy' })
    note.value = savedNote = session.value.note ?? ''
    if (active.value && wakeLock.isSupported.value) await wakeLock.request('screen').catch(() => {})
  } catch (e) {
    toast.error(firstError(e))
    router.replace('/')
  }
})
// Keep the offline copy current (sets saved, skips…), so a reload without network shows the real state.
watch(session, (s) => { if (s) writeCache(`/sessions/${s.id}`, s) }, { deep: true })

const online = useOnline()
/** Sets of this workout ticked offline and not yet on the server. */
const pendingHere = computed(() => outbox.items.filter((i) => i.sessionId === session.value?.id))

// The server auto-closes workouts left idle for 3h; a PWA resumes without reloading, so re-check on return.
const visibility = useDocumentVisibility()
watch(visibility, async (v) => {
  if (v !== 'visible' || !active.value || !session.value) return
  try {
    const fresh = await api<Session>(`/sessions/${session.value.id}`)
    // Only the status: sets/drafts on screen stay untouched.
    session.value.status = fresh.status
    session.value.finished_at = fresh.finished_at
    if (!active.value) timer.stop()
  } catch { /* offline — keep what we have */ }
})
onBeforeUnmount(() => {
  void saveNote() // flush a note typed right before leaving
  clearInterval(clock)
  void wakeLock.release().catch(() => {})
})

const elapsed = computed(() => {
  if (!session.value) return ''
  const end = session.value.finished_at ? Date.parse(session.value.finished_at) : now.value
  const m = Math.max(0, Math.floor((end - Date.parse(session.value.started_at)) / 60000))
  return `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`
})
const slotSets = (slotId: number) => session.value?.sets.filter((s) => s.day_exercise_id === slotId) ?? []
const skipped = computed(() => new Set(session.value?.skipped_slots ?? []))
// Skipped exercises don't count towards progress or unsaved drafts (their sets stay in the log).
const unsaved = computed(() => Object.entries(dirty).reduce((a, [id, n]) => a + (skipped.value.has(Number(id)) ? 0 : n), 0))
const planned = computed(() => session.value?.slots.reduce((a, s) => a + (skipped.value.has(s.id) ? 0 : s.sets), 0) ?? 0)
const logged = computed(() => {
  if (!session.value) return 0
  const sid = session.value.id
  const onServer = session.value.sets.filter((s) => !s.is_warmup && !(s.day_exercise_id && skipped.value.has(s.day_exercise_id)))
  const serverKeys = new Set(onServer.map((s) => setKey(sid, { day_exercise_id: s.day_exercise_id ?? 0, exercise_id: s.exercise_id, set_no: s.set_no })))
  const queuedOnly = pendingHere.value.filter((i) => !skipped.value.has(i.body.day_exercise_id) && !serverKeys.has(i.key))
  return onServer.length + queuedOnly.length
})

/* Focus: the current exercise is the one tapped last, else the first one not done and not skipped. */
const doneMap = reactive<Record<number, boolean>>({})
const focusId = ref<number | null>(null)
const isOpen = (id: number) => !skipped.value.has(id) && !doneMap[id]
const currentId = computed(() => {
  if (!active.value || !session.value) return null
  if (focusId.value !== null && isOpen(focusId.value)) return focusId.value
  return session.value.slots.find((s) => isOpen(s.id))?.id ?? null
})

function onDone(slotId: number, done: boolean) {
  const justFinishedCurrent = done && doneMap[slotId] === false && currentId.value === slotId
  doneMap[slotId] = done
  if (!justFinishedCurrent || !active.value) return
  if (focusId.value === slotId) focusId.value = null
  void nextTick(() => {
    if (currentId.value !== null) document.getElementById(`slot-${currentId.value}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  })
}

async function toggleSkip(slotId: number, skip: boolean) {
  if (!session.value) return
  const before = session.value.skipped_slots
  const next = skip ? [...before.filter((id) => id !== slotId), slotId] : before.filter((id) => id !== slotId)
  session.value.skipped_slots = next // optimistic
  try {
    const res = await api<{ skipped_slots: number[] }>(`/sessions/${session.value.id}`, 'PATCH', { skipped_slots: next })
    session.value.skipped_slots = res.skipped_slots
  } catch (e) {
    session.value.skipped_slots = before
    toast.error(firstError(e))
  }
}
const pct = computed(() => Math.min(100, Math.round((logged.value / Math.max(1, planned.value)) * 100)))

function onSaved(set: SetLog) {
  if (!session.value) return
  const i = session.value.sets.findIndex((s) => s.id === set.id)
  if (i >= 0) session.value.sets[i] = set
  else session.value.sets.push(set)
}

/** Runs right on the tap (also offline), so the rest timer and its sound start inside the user gesture. */
function onConfirmed(firstSave: boolean, restMin: number | null) {
  if (firstSave && active.value) timer.start((restMin ?? 1.5) * 60)
}

function onDeleted(setId: number) {
  if (session.value) session.value.sets = session.value.sets.filter((s) => s.id !== setId)
}

/* Workout note: autosaved while typing and on blur. */
const note = ref('')
let savedNote = ''
const noteState = ref<'idle' | 'saving' | 'saved'>('idle')
async function saveNote() {
  if (!session.value || note.value === savedNote) return
  const value = note.value
  noteState.value = 'saving'
  try {
    await api(`/sessions/${session.value.id}`, 'PATCH', { note: value.trim() || null })
    savedNote = value
    session.value.note = value.trim() || null
    noteState.value = 'saved'
  } catch (e) {
    noteState.value = 'idle'
    if (!isNetworkError(e)) toast.error(firstError(e)) // offline: kept in the field, retried when back online
  }
}
watch(online, (on) => { if (on) void saveNote() })
const saveNoteSoon = useDebounceFn(saveNote, 1000)

async function enableNotifications() {
  const result = await timer.enableNotifications()
  if (result === 'granted') toast.success(t('workout.notifyEnabled'))
  else if (result === 'denied') toast.error(t('workout.notifyDenied'))
}

async function act(kind: 'finish' | 'skip') {
  if (!session.value) return
  const question = kind === 'finish' ? t('workout.finishConfirm') : t('workout.annulConfirm')
  const ok = await confirmDialog({
    title: question,
    description: unsaved.value ? t('workout.unsaved') : undefined,
    confirmText: kind === 'finish' ? t('workout.finish') : t('workout.annul'),
    destructive: kind === 'skip',
  })
  if (!ok) return
  busy.value = true
  try {
    // Push sets ticked offline first, so the summary includes them.
    await flush()
    await saveNote()
    const summary = await api<SessionSummary>(`/sessions/${session.value.id}/${kind}`, 'POST')
    timer.stop()
    if (kind === 'finish') {
      toast.success(t('workout.finished'), {
        description: `${summary.duration_min ?? 0} ${t('common.min')} · ${summary.sets_count} ${t('history.sets')} · ${summary.volume_kg} ${t('common.kg')}`,
      })
    } else {
      toast(t('workout.annulled'))
    }
    router.replace('/')
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    busy.value = false
  }
}

async function remove() {
  if (!session.value) return
  const ok = await confirmDialog({ title: t('workout.deleteConfirm'), description: t('workout.deleteHint'), confirmText: t('common.delete'), destructive: true })
  if (!ok) return
  try {
    await api(`/sessions/${session.value.id}`, 'DELETE')
  } catch (e) {
    toast.error(firstError(e))
    return
  }
  timer.stop()
  router.replace('/')
}
</script>

<template>
  <div>
    <div v-if="!session" class="space-y-3 pt-2">
      <Skeleton class="h-12 rounded-xl" />
      <Skeleton v-for="i in 3" :key="i" class="h-64 rounded-2xl" />
    </div>

    <div v-else class="space-y-4" :class="timer.active.value ? 'pb-32' : ''">
      <header class="sticky top-[env(safe-area-inset-top)] z-30 -mx-4 -mt-4 bg-background/80 px-4 pt-2 pb-3 backdrop-blur-xl backdrop-saturate-150">
        <div class="flex items-center gap-2">
          <Button variant="ghost" size="icon" class="-ml-2 rounded-full" :aria-label="$t('common.back')" @click="router.push('/')">
            <ChevronLeft class="size-6" />
          </Button>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[17px] font-semibold leading-tight">{{ l(session.day, 'title') }}</p>
            <p class="text-xs text-muted-foreground capitalize">
              <template v-if="session.day">{{ $t('common.week') }} {{ session.day.week_no }} · </template>{{ fmtDate(session.started_at, locale) }}
            </p>
          </div>
          <Badge variant="secondary" class="h-7 gap-1.5 px-2.5 text-sm font-semibold">
            <Timer class="size-3.5! text-primary" />{{ elapsed }}
          </Badge>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" size="icon" class="-mr-2 rounded-full" :aria-label="$t('common.more')"><MoreHorizontal /></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <template v-if="active">
                <DropdownMenuItem :disabled="busy" @select="act('skip')"><Ban /> {{ $t('workout.annul') }}</DropdownMenuItem>
                <DropdownMenuSeparator />
              </template>
              <DropdownMenuItem variant="destructive" :disabled="busy" @select="remove"><Trash2 /> {{ $t('workout.delete') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        <div class="mt-2.5 flex items-center gap-3">
          <Progress :model-value="pct" class="h-1.5" />
          <span class="shrink-0 text-xs font-semibold tabular-nums text-muted-foreground">{{ logged }}/{{ planned }}</span>
        </div>
        <p v-if="!online || pendingHere.length" class="mt-2 flex items-center gap-1.5 text-xs font-medium text-warning"
          :title="$t('offline.pendingHint')">
          <CloudOff v-if="!online" class="size-3.5" /><CloudUpload v-else class="size-3.5 animate-pulse" />
          <template v-if="!online">{{ $t('offline.noNetwork') }}</template>
          <template v-if="!online && pendingHere.length"> · </template>
          <template v-if="pendingHere.length">{{ $t('offline.pending', { n: pendingHere.length }) }}</template>
        </p>
      </header>

      <div v-if="!active" class="rise flex items-center gap-3 rounded-2xl border p-3.5 text-sm"
        :class="session.status === 'finished' ? 'border-success/30 bg-success/10' : 'bg-muted/50'">
        <CheckCircle2 v-if="session.status === 'finished'" class="size-5 text-success" />
        <Ban v-else class="size-5 text-muted-foreground" />
        <span class="font-semibold">{{ $t(`status.${session.status}`) }}</span>
      </div>

      <ExerciseCard v-for="(slot, i) in session.slots" :id="`slot-${slot.id}`" :key="slot.id"
        class="rise scroll-mt-[calc(env(safe-area-inset-top)+7.5rem)]" :style="{ '--i': i }"
        :slot="slot" :session-id="session.id" :sets="slotSets(slot.id)" :previous="session.previous"
        :skipped="skipped.has(slot.id)" :can-skip="active"
        :focus-mode="active" :current="currentId === slot.id"
        @done="(d) => onDone(slot.id, d)" @focus="focusId = slot.id"
        @skip="(on) => toggleSkip(slot.id, on)"
        @saved="onSaved"
        @confirmed="(first) => onConfirmed(first, slot.rest_min)"
        @deleted="onDeleted"
        @dirty="(n) => (dirty[slot.id] = n)" />

      <div class="space-y-1.5 pt-2">
        <label for="workout-note" class="flex items-center gap-1.5 px-1 text-sm font-medium text-muted-foreground">
          <NotebookPen class="size-4" /> {{ $t('workout.note') }}
          <Loader2 v-if="noteState === 'saving'" class="ml-auto size-3.5 animate-spin" />
          <span v-else-if="noteState === 'saved'" class="ml-auto inline-flex items-center gap-1 text-xs text-success">
            <Check class="size-3.5" /> {{ $t('workout.noteSaved') }}
          </span>
        </label>
        <Textarea id="workout-note" v-model="note" maxlength="2000" :placeholder="$t('workout.notePlaceholder')"
          class="bg-card text-[15px]" @input="noteState = 'idle'; saveNoteSoon()" @blur="saveNote" />
      </div>

      <div v-if="active" class="pt-2">
        <Button size="xl" class="w-full shadow-lg shadow-primary/25" :disabled="busy" @click="act('finish')">
          <Flag /> {{ $t('workout.finish') }}
        </Button>
      </div>

      <!-- Rest timer -->
      <Transition name="timer">
        <div v-if="timer.active.value" class="fixed inset-x-0 bottom-0 z-40 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
          <div class="mx-auto max-w-xl overflow-hidden rounded-3xl border bg-card/90 shadow-2xl shadow-black/20 backdrop-blur-xl dark:shadow-black/50">
            <div class="flex items-center gap-2 px-4 pt-3 pb-2.5">
              <div class="flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{{ $t('workout.restTimer') }}</p>
                <span class="text-[40px] leading-none font-bold tracking-tight tabular-nums" :class="{ urgent: timer.remaining.value <= 5 }">{{ timer.label.value }}</span>
              </div>
              <Button v-if="timer.notifyPermission.value === 'default'" variant="ghost" size="icon-lg" class="rounded-full text-muted-foreground"
                :aria-label="$t('workout.notifyEnable')" :title="$t('workout.notifyEnable')" @click="enableNotifications">
                <Bell />
              </Button>
              <Button variant="secondary" size="lg" class="px-3.5 tabular-nums" @click="timer.add(-15)">−15</Button>
              <Button variant="secondary" size="lg" class="px-3.5 tabular-nums" @click="timer.add(30)">+30</Button>
              <Button variant="ghost" size="icon-lg" class="rounded-full" :aria-label="$t('workout.skipRest')" @click="timer.stop()"><X /></Button>
            </div>
            <Progress :model-value="timer.progress.value" class="h-1 rounded-none bg-transparent" indicator-class="rounded-none duration-300 ease-linear" />
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>
