<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useWakeLock } from '@vueuse/core'
import { toast } from 'vue-sonner'
import { Ban, CheckCircle2, ChevronLeft, Flag, MoreHorizontal, Timer, Trash2, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Skeleton } from '@/components/ui/skeleton'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { confirmDialog } from '@/composables/useConfirm'
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
  const ok = await confirmDialog({
    title: question,
    description: unsaved.value ? t('workout.unsaved') : undefined,
    confirmText: kind === 'finish' ? t('workout.finish') : t('workout.annul'),
    destructive: kind === 'skip',
  })
  if (!ok) return
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
  if (!session.value) return
  const ok = await confirmDialog({ title: t('workout.deleteConfirm'), description: t('workout.deleteHint'), confirmText: t('common.delete'), destructive: true })
  if (!ok) return
  await api(`/sessions/${session.value.id}`, 'DELETE')
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
      </header>

      <div v-if="!active" class="rise flex items-center gap-3 rounded-2xl border p-3.5 text-sm"
        :class="session.status === 'finished' ? 'border-success/30 bg-success/10' : 'bg-muted/50'">
        <CheckCircle2 v-if="session.status === 'finished'" class="size-5 text-success" />
        <Ban v-else class="size-5 text-muted-foreground" />
        <span class="font-semibold">{{ $t(`status.${session.status}`) }}</span>
      </div>

      <ExerciseCard v-for="(slot, i) in session.slots" :key="slot.id" class="rise" :style="{ '--i': i }"
        :slot="slot" :session-id="session.id" :sets="slotSets(slot.id)" :previous="session.previous"
        @saved="(set, first) => onSaved(set, first, slot.rest_min)"
        @deleted="onDeleted"
        @dirty="(n) => (dirty[slot.id] = n)" />

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
