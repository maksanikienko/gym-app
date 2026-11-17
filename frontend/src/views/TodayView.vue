<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { ChevronDown, Dumbbell, Play, SkipForward } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import ProgressRing from '@/components/ProgressRing.vue'
import DayStatusIcon from '@/components/DayStatusIcon.vue'
import { api, firstError } from '@/lib/api'
import { fmtTarget } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { Cycle, CycleDay, Session } from '@/types'

const router = useRouter()
const { t } = useI18n()
const { l, locale } = useLocalized()
const cycle = ref<Cycle | null>(null)
const loading = ref(true)
const busy = ref(false)

async function load() {
  try {
    cycle.value = (await api<Cycle | undefined>('/cycles/current')) ?? null
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)

const nextDay = computed(() => cycle.value?.days.find((d) => d.id === cycle.value?.next_day_id) ?? null)
const resuming = computed(() => nextDay.value?.status === 'in_progress')
const pct = computed(() => (cycle.value ? Math.round((cycle.value.progress.done / Math.max(1, cycle.value.progress.total)) * 100) : 0))
const weeks = computed(() => {
  const map = new Map<number, CycleDay[]>()
  for (const d of cycle.value?.days ?? []) {
    if (!map.has(d.week_no)) map.set(d.week_no, [])
    map.get(d.week_no)!.push(d)
  }
  return [...map.entries()]
})
const currentWeek = computed(() => nextDay.value?.week_no ?? weeks.value.at(-1)?.[0] ?? 1)
const startedTime = computed(() => {
  const s = cycle.value?.open_session?.started_at
  return s ? new Date(s).toLocaleTimeString(locale.value === 'ru' ? 'ru-RU' : 'en-GB', { hour: '2-digit', minute: '2-digit' }) : ''
})

async function open(day: CycleDay) {
  if (day.status === 'locked') {
    toast.info(t('today.locked'))
    return
  }
  if (day.session_id) {
    router.push(`/workout/${day.session_id}`)
    return
  }
  busy.value = true
  try {
    const s = await api<Session>('/sessions', 'POST', { program_day_id: day.id })
    router.push(`/workout/${s.id}`)
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    busy.value = false
  }
}

async function skipNext() {
  if (!cycle.value || !confirm(t('today.skipNextConfirm'))) return
  busy.value = true
  try {
    if (cycle.value.open_session) await api(`/sessions/${cycle.value.open_session.id}/skip`, 'POST')
    else await api(`/cycles/${cycle.value.id}/skip-next`, 'POST')
    toast(t('today.skipped'))
    await load()
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    busy.value = false
  }
}

async function finishCycle() {
  if (!cycle.value || !confirm(t('today.finishCycle') + '?')) return
  await api(`/cycles/${cycle.value.id}/finish`, 'POST')
  cycle.value = null
}
</script>

<template>
  <div>
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $t('nav.today') }}</h1>

    <div v-if="loading" class="space-y-3">
      <div class="h-32 animate-pulse rounded-2xl bg-muted" />
      <div class="h-48 animate-pulse rounded-2xl bg-muted" />
    </div>

    <!-- No program yet -->
    <div v-else-if="!cycle" class="rise flex flex-col items-center gap-4 rounded-2xl border border-dashed px-6 py-12 text-center">
      <div class="grid size-16 place-items-center rounded-full bg-primary/10 text-primary">
        <Dumbbell class="size-8" />
      </div>
      <div>
        <p class="text-lg font-semibold">{{ $t('today.noCycle') }}</p>
        <p class="text-sm text-muted-foreground">{{ $t('today.noCycleHint') }}</p>
      </div>
      <Button size="lg" class="h-12 px-8" @click="router.push('/programs')">{{ $t('today.choose') }}</Button>
    </div>

    <div v-else class="space-y-5">
      <!-- Progress hero -->
      <RouterLink :to="`/programs/${cycle.program.id}`"
        class="hero rise flex items-center gap-5 rounded-2xl p-5 text-white shadow-lg shadow-primary/20">
        <ProgressRing :value="pct" class="text-white">
          <span class="text-xl font-bold tabular-nums">{{ pct }}%</span>
        </ProgressRing>
        <div class="min-w-0">
          <p class="text-xs uppercase tracking-wider text-white/70">{{ $t('common.week') }} {{ currentWeek }} / {{ cycle.program.weeks_count }}</p>
          <p class="truncate text-lg font-bold">{{ l(cycle.program, 'name') }}</p>
          <p class="text-sm text-white/80">{{ $t('today.progress', cycle.progress) }}</p>
        </div>
      </RouterLink>

      <!-- Next / unfinished workout -->
      <section v-if="nextDay" class="rise overflow-hidden rounded-2xl border bg-card shadow-sm" style="--i: 1"
        :class="resuming ? 'border-warning/60 ring-1 ring-warning/30' : ''">
        <div class="space-y-1 p-5 pb-3">
          <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider"
            :class="resuming ? 'text-warning' : 'text-primary'">
            <DayStatusIcon :status="nextDay.status" />
            {{ resuming ? $t('today.resumeTitle') : $t('today.next') }} · {{ $t('common.week') }} {{ nextDay.week_no }}
          </p>
          <h2 class="text-2xl font-bold tracking-tight">{{ l(nextDay, 'title') }}</h2>
          <p v-if="resuming && cycle.open_session" class="text-sm text-muted-foreground">
            {{ $t('today.startedAt', { time: startedTime }) }} · {{ $t('today.setsLogged', { n: cycle.open_session.sets_count }) }}
          </p>
        </div>
        <ul class="space-y-1.5 px-5 pb-4 text-sm">
          <li v-for="(ex, i) in nextDay.exercises" :key="i" class="flex justify-between gap-3">
            <span class="flex items-center gap-2">
              <span class="grid size-5 place-items-center rounded-full bg-muted text-[10px] font-semibold tabular-nums">{{ i + 1 }}</span>
              {{ l(ex, 'name') }}
            </span>
            <span class="shrink-0 tabular-nums text-muted-foreground">{{ fmtTarget(ex, $t('common.sec')) }}</span>
          </li>
        </ul>
        <div class="flex gap-2 border-t bg-muted/40 p-3">
          <Button size="lg" class="h-12 flex-1 text-base" :disabled="busy" @click="open(nextDay)">
            <Play class="fill-current" /> {{ resuming ? $t('today.resume') : $t('today.start') }}
          </Button>
          <Button size="lg" variant="ghost" class="h-12 text-muted-foreground" :disabled="busy" @click="skipNext">
            <SkipForward /> {{ $t('today.skipNext') }}
          </Button>
        </div>
      </section>

      <section v-else class="rise space-y-3 rounded-2xl border p-6 text-center" style="--i: 1">
        <p class="text-lg font-semibold">{{ $t('today.completed') }}</p>
        <Button variant="outline" @click="finishCycle">{{ $t('today.finishCycle') }}</Button>
      </section>

      <!-- All days -->
      <section class="space-y-2">
        <h2 class="font-semibold">{{ $t('today.allDays') }}</h2>
        <details v-for="([week, days], wi) in weeks" :key="week" :open="week === currentWeek"
          class="rise group rounded-xl border bg-card" :style="{ '--i': wi + 2 }">
          <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
            <span class="font-medium">{{ $t('common.week') }} {{ week }}</span>
            <span class="flex gap-1">
              <span v-for="d in days" :key="d.id" class="size-2 rounded-full transition-colors"
                :class="{
                  'bg-success': d.status === 'finished',
                  'bg-muted-foreground/40': d.status === 'skipped',
                  'bg-warning': d.status === 'in_progress',
                  'bg-primary': d.status === 'next',
                  'bg-muted': d.status === 'locked',
                }" />
            </span>
            <ChevronDown class="ml-auto size-4 text-muted-foreground transition-transform duration-200 group-open:rotate-180" />
          </summary>
          <div class="space-y-1 px-2 pb-2">
            <button v-for="d in days" :key="d.id" type="button" :disabled="busy"
              class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-colors active:bg-accent"
              :class="d.status === 'locked' ? 'opacity-50' : 'hover:bg-accent/60'"
              @click="open(d)">
              <DayStatusIcon :status="d.status" />
              <span class="flex-1" :class="{ 'line-through text-muted-foreground': d.status === 'skipped' }">{{ l(d, 'title') }}</span>
              <span class="text-xs text-muted-foreground">{{ $t(`status.${d.status}`) }}</span>
            </button>
          </div>
        </details>
      </section>
    </div>
  </div>
</template>
