<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useWakeLock } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { ArrowLeft, Ban, CheckCircle2, Flag, Timer, Trash2, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import ExerciseCard from '@/components/ExerciseCard.vue'
import { api, firstError } from '@/lib/api'
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
    session.value = await api<Session>(`/sessions/${props.id}`)
    if (active.value && wakeLock.isSupported.value) await wakeLock.request('screen').catch(() => {})
  } catch (e) {
    toast.error(firstError(e))
    router.replace('/')
  }
})
onBeforeUnmount(() => {
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
const unsaved = computed(() => Object.values(dirty).reduce((a, b) => a + b, 0))
const planned = computed(() => session.value?.slots.reduce((a, s) => a + s.sets, 0) ?? 0)
const logged = computed(() => session.value?.sets.filter((s) => !s.is_warmup).length ?? 0)
const pct = computed(() => Math.min(100, Math.round((logged.value / Math.max(1, planned.value)) * 100)))

function onSaved(set: SetLog, firstSave: boolean, restMin: number | null) {
  if (!session.value) return
  const i = session.value.sets.findIndex((s) => s.id === set.id)
  if (i >= 0) session.value.sets[i] = set
  else session.value.sets.push(set)
  if (firstSave && active.value) timer.start((restMin ?? 1.5) * 60)
}

function onDeleted(setId: number) {
  if (session.value) session.value.sets = session.value.sets.filter((s) => s.id !== setId)
}

async function act(kind: 'finish' | 'skip') {
  if (!session.value) return
  const question = kind === 'finish' ? t('workout.finishConfirm') : t('workout.annulConfirm')
  if (!confirm(unsaved.value ? `${t('workout.unsaved')}. ${question}` : question)) return
  busy.value = true
  try {
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
  if (!session.value || !confirm(`${t('workout.deleteConfirm')}\n${t('workout.deleteHint')}`)) return
  await api(`/sessions/${session.value.id}`, 'DELETE')
  timer.stop()
  router.replace('/')
}
</script>

<template>
  <div>
    <div v-if="!session" class="space-y-3 pt-14">
      <div v-for="i in 3" :key="i" class="h-56 animate-pulse rounded-xl bg-muted" />
    </div>

    <div v-else class="space-y-4" :class="timer.active.value ? 'pb-28' : ''">
      <header class="sticky top-0 z-30 -mx-4 border-b bg-background/90 px-4 pt-[env(safe-area-inset-top)] backdrop-blur-lg">
        <div class="flex items-center gap-3 py-2">
          <button type="button" class="rounded-full p-1.5 transition-colors active:bg-accent" :aria-label="$t('common.back')" @click="router.push('/')">
            <ArrowLeft class="size-5" />
          </button>
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold leading-tight">{{ l(session.day, 'title') }}</p>
            <p class="text-xs text-muted-foreground">
              <template v-if="session.day">{{ $t('common.week') }} {{ session.day.week_no }} · </template>{{ fmtDate(session.started_at, locale) }}
            </p>
          </div>
          <span class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-sm font-medium tabular-nums">
            <Timer class="size-3.5" />{{ elapsed }}
          </span>
        </div>
        <div class="-mx-4 h-1 bg-muted">
          <div class="h-full bg-primary transition-[width] duration-500 ease-out" :style="{ width: `${pct}%` }" />
        </div>
      </header>

      <div v-if="!active" class="rise flex items-center gap-3 rounded-xl border p-3 text-sm"
        :class="session.status === 'finished' ? 'border-success/40 bg-success/10' : 'bg-muted/50'">
        <CheckCircle2 v-if="session.status === 'finished'" class="size-5 text-success" />
        <Ban v-else class="size-5 text-muted-foreground" />
        <span class="font-medium">{{ $t(`status.${session.status}`) }}</span>
      </div>

      <ExerciseCard v-for="(slot, i) in session.slots" :key="slot.id" class="rise" :style="{ '--i': i }"
        :slot="slot" :session-id="session.id" :sets="slotSets(slot.id)" :previous="session.previous"
        @saved="(set, first) => onSaved(set, first, slot.rest_min)"
        @deleted="onDeleted"
        @dirty="(n) => (dirty[slot.id] = n)" />

      <div class="space-y-2 pt-2">
        <template v-if="active">
          <Button size="lg" class="h-12 w-full text-base shadow-lg shadow-primary/25" :disabled="busy" @click="act('finish')">
            <Flag /> {{ $t('workout.finish') }}
          </Button>
          <Button size="lg" variant="outline" class="h-11 w-full" :disabled="busy" @click="act('skip')">
            <Ban /> {{ $t('workout.annul') }}
          </Button>
        </template>
        <Button variant="ghost" class="w-full text-destructive hover:text-destructive" :disabled="busy" @click="remove">
          <Trash2 /> {{ $t('workout.delete') }}
        </Button>
      </div>

      <Transition name="timer">
        <div v-if="timer.active.value"
          class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_30px_rgb(0_0_0/0.12)] backdrop-blur-lg">
          <div class="h-1 bg-muted">
            <div class="h-full bg-primary transition-[width] duration-300 ease-linear" :style="{ width: `${timer.progress.value}%` }" />
          </div>
          <div class="mx-auto flex max-w-xl items-center gap-2 px-4 py-3">
            <div class="flex-1">
              <p class="text-[11px] uppercase tracking-wider text-muted-foreground">{{ $t('workout.restTimer') }}</p>
              <span class="text-4xl font-bold tabular-nums leading-none" :class="{ urgent: timer.remaining.value <= 5 }">{{ timer.label.value }}</span>
            </div>
            <Button variant="outline" size="lg" class="h-11 px-3 tabular-nums" @click="timer.add(-15)">−15</Button>
            <Button variant="outline" size="lg" class="h-11 px-3 tabular-nums" @click="timer.add(30)">+30</Button>
            <Button variant="ghost" size="icon-lg" :aria-label="$t('workout.skipRest')" @click="timer.stop()"><X /></Button>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>
