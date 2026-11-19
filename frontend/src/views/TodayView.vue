<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { ChevronDown, ChevronRight, Dumbbell, Play, SkipForward, Trophy } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import PageHeader from '@/components/PageHeader.vue'
import { confirmDialog } from '@/composables/useConfirm'
import ProgressRing from '@/components/ProgressRing.vue'
import DayStatusIcon from '@/components/DayStatusIcon.vue'
import { api, firstError, cachedGet } from '@/lib/api'
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
    cycle.value = (await cachedGet<Cycle | undefined>('/cycles/current')).data ?? null
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
const todayLabel = computed(() => new Date().toLocaleDateString(locale.value === 'ru' ? 'ru-RU' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' }))
const openWeeks = ref<Record<number, boolean>>({})
const isOpen = (w: number) => openWeeks.value[w] ?? w === currentWeek.value
const dotClass: Record<string, string> = {
  finished: 'bg-success',
  skipped: 'bg-muted-foreground/40',
  in_progress: 'bg-warning',
  next: 'bg-primary ring-2 ring-primary/30',
  locked: 'bg-muted-foreground/15',
}
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
  if (!cycle.value || !(await confirmDialog({ title: t('today.skipNextConfirm'), confirmText: t('today.skipNext') }))) return
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
  if (!cycle.value || !(await confirmDialog({ title: t('today.finishCycle') + '?', confirmText: t('today.finishCycle') }))) return
  await api(`/cycles/${cycle.value.id}/finish`, 'POST')
  cycle.value = null
}
</script>

<template>
  <div>
    <PageHeader :eyebrow="todayLabel" :title="$t('nav.today')" />

    <div v-if="loading" class="space-y-4">
      <Skeleton class="h-32 rounded-3xl" />
      <Skeleton class="h-64 rounded-2xl" />
      <Skeleton class="h-14 rounded-2xl" />
    </div>

    <!-- No program yet -->
    <Empty v-else-if="!cycle" class="rise bg-card/50 py-12">
      <EmptyHeader>
        <EmptyMedia><Dumbbell /></EmptyMedia>
        <EmptyTitle>{{ $t('today.noCycle') }}</EmptyTitle>
        <EmptyDescription>{{ $t('today.noCycleHint') }}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button size="xl" class="px-8" @click="router.push('/programs')">{{ $t('today.choose') }}</Button>
      </EmptyContent>
    </Empty>

    <div v-else class="space-y-5">
      <!-- Progress hero -->
      <RouterLink :to="`/programs/${cycle.program.id}`"
        class="hero rise relative flex items-center gap-5 overflow-hidden rounded-3xl p-5 text-white shadow-xl shadow-primary/15 transition-transform active:scale-[0.985]">
        <ProgressRing :value="pct" :size="84" :stroke="7" class="text-white">
          <span class="text-xl font-bold tabular-nums">{{ pct }}<span class="text-sm opacity-70">%</span></span>
        </ProgressRing>
        <div class="min-w-0 flex-1 space-y-1">
          <span class="inline-flex rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider backdrop-blur">
            {{ $t('common.week') }} {{ currentWeek }} / {{ cycle.program.weeks_count }}
          </span>
          <p class="truncate text-xl font-bold tracking-tight">{{ l(cycle.program, 'name') }}</p>
          <p class="text-sm text-white/80">{{ $t('today.progress', cycle.progress) }}</p>
        </div>
        <ChevronRight class="size-5 shrink-0 text-white/60" />
      </RouterLink>

      <!-- Next / unfinished workout -->
      <Card v-if="nextDay" class="rise gap-0 overflow-hidden py-0" style="--i: 1"
        :class="resuming ? 'border-warning/50 ring-1 ring-warning/25' : ''">
        <div class="space-y-2 p-5 pb-4">
          <Badge :variant="resuming ? 'warning' : 'soft'" class="gap-1.5 uppercase tracking-wider">
            <DayStatusIcon :status="nextDay.status" class="size-3.5!" />
            {{ resuming ? $t('today.resumeTitle') : $t('today.next') }} · {{ $t('common.week') }} {{ nextDay.week_no }}
          </Badge>
          <h2 class="text-2xl font-bold tracking-tight">{{ l(nextDay, 'title') }}</h2>
          <p v-if="resuming && cycle.open_session" class="text-sm text-muted-foreground">
            {{ $t('today.startedAt', { time: startedTime }) }} · {{ $t('today.setsLogged', { n: cycle.open_session.sets_count }) }}
          </p>
        </div>
        <ol class="mx-5 mb-4 divide-y rounded-xl border bg-muted/30 text-sm">
          <li v-for="(ex, i) in nextDay.exercises" :key="i" class="flex items-center justify-between gap-3 px-3 py-2.5">
            <span class="flex min-w-0 items-center gap-2.5">
              <span class="grid size-6 shrink-0 place-items-center rounded-full bg-background text-[11px] font-semibold tabular-nums text-muted-foreground shadow-xs">{{ i + 1 }}</span>
              <span class="truncate">{{ l(ex, 'name') }}</span>
            </span>
            <span class="shrink-0 font-medium tabular-nums text-muted-foreground">{{ fmtTarget(ex, $t('common.sec')) }}</span>
          </li>
        </ol>
        <div class="flex gap-2 px-5 pb-5">
          <Button size="xl" class="flex-1" :disabled="busy" @click="open(nextDay)">
            <Play class="fill-current" /> {{ resuming ? $t('today.resume') : $t('today.start') }}
          </Button>
          <Button size="icon-lg" variant="outline" class="size-12" :disabled="busy" :aria-label="$t('today.skipNext')" :title="$t('today.skipNext')" @click="skipNext">
            <SkipForward />
          </Button>
        </div>
      </Card>

      <Empty v-else class="rise border-solid bg-card py-10" style="--i: 1">
        <EmptyHeader>
          <EmptyMedia class="bg-warning/15 text-warning"><Trophy /></EmptyMedia>
          <EmptyTitle>{{ $t('today.completed') }}</EmptyTitle>
        </EmptyHeader>
        <EmptyContent>
          <Button variant="outline" size="lg" @click="finishCycle">{{ $t('today.finishCycle') }}</Button>
        </EmptyContent>
      </Empty>

      <!-- All days -->
      <section class="space-y-2.5">
        <h2 class="px-1 text-sm font-semibold uppercase tracking-wider text-muted-foreground">{{ $t('today.allDays') }}</h2>
        <Collapsible v-for="([week, days], wi) in weeks" :key="week" :open="isOpen(week)"
          class="rise overflow-hidden rounded-2xl border bg-card shadow-xs" :style="{ '--i': wi + 2 }"
          @update:open="(v: boolean) => (openWeeks[week] = v)">
          <CollapsibleTrigger class="group flex w-full items-center gap-3 px-4 py-3.5 text-left">
            <span class="font-semibold">{{ $t('common.week') }} {{ week }}</span>
            <Badge v-if="week === currentWeek" variant="soft">{{ $t('common.current') }}</Badge>
            <span class="ml-auto flex gap-1">
              <span v-for="d in days" :key="d.id" class="size-2 rounded-full transition-colors" :class="dotClass[d.status]" />
            </span>
            <ChevronDown class="size-4 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div class="space-y-0.5 border-t px-1.5 py-1.5">
              <button v-for="d in days" :key="d.id" type="button" :disabled="busy"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition-colors active:bg-accent"
                :class="d.status === 'locked' ? 'opacity-50' : 'hover:bg-accent/60'"
                @click="open(d)">
                <DayStatusIcon :status="d.status" />
                <span class="flex-1 font-medium" :class="{ 'font-normal text-muted-foreground line-through': d.status === 'skipped' }">{{ l(d, 'title') }}</span>
                <span class="text-xs text-muted-foreground">{{ $t(`status.${d.status}`) }}</span>
              </button>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </section>
    </div>
  </div>
</template>
