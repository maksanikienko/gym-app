<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RecordsList from '@/components/RecordsList.vue'
import { toast } from 'vue-sonner'
import { Ban, CheckCircle2, ChevronRight, History } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { api, firstError } from '@/lib/api'
import { fmtDate } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { SessionSummary } from '@/types'

const { l, locale } = useLocalized()
const route = useRoute()
const router = useRouter()
const tab = computed<'workouts' | 'records'>({
  get: () => (route.query.tab === 'records' ? 'records' : 'workouts'),
  set: (v) => router.replace({ query: { tab: v } }),
})
const items = ref<SessionSummary[]>([])
const nextPage = ref<number | null>(1)
const loading = ref(false)

async function load() {
  if (!nextPage.value || loading.value) return
  loading.value = true
  try {
    const res = await api<{ data: SessionSummary[]; next_page: number | null }>(`/sessions?page=${nextPage.value}`)
    items.value.push(...res.data)
    nextPage.value = res.next_page
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <div>
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $t('history.title') }}</h1>

    <div class="relative mb-4 grid grid-cols-2 rounded-xl bg-muted p-1">
      <span class="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-lg bg-background shadow-sm transition-transform duration-300 ease-out"
        :class="tab === 'records' ? 'translate-x-full' : ''" />
      <button v-for="k in (['workouts', 'records'] as const)" :key="k" type="button"
        class="relative z-10 py-2 text-sm font-medium transition-colors"
        :class="tab === k ? 'text-foreground' : 'text-muted-foreground'" @click="tab = k">
        {{ k === 'workouts' ? $t('history.workouts') : $t('records.title') }}
      </button>
    </div>

    <RecordsList v-if="tab === 'records'" />
    <template v-else>

    <div v-if="loading && !items.length" class="space-y-2">
      <div v-for="i in 4" :key="i" class="h-20 animate-pulse rounded-xl bg-muted" />
    </div>
    <div v-else-if="!items.length" class="rise flex flex-col items-center gap-3 rounded-2xl border border-dashed py-12 text-muted-foreground">
      <History class="size-10 opacity-50" />
      {{ $t('history.empty') }}
    </div>

    <div class="space-y-2">
      <RouterLink v-for="(s, i) in items" :key="s.id" :to="`/workout/${s.id}`"
        class="rise flex items-center gap-3 rounded-xl border bg-card p-4 transition-all active:scale-[0.99] active:bg-accent"
        :style="{ '--i': i % 20 }">
        <div class="grid size-10 shrink-0 place-items-center rounded-full"
          :class="{
            'bg-success/15 text-success': s.status === 'finished',
            'bg-muted text-muted-foreground': s.status === 'skipped',
            'bg-warning/15 text-warning': s.status === 'in_progress',
          }">
          <CheckCircle2 v-if="s.status === 'finished'" class="size-5" />
          <Ban v-else-if="s.status === 'skipped'" class="size-5" />
          <span v-else class="size-2.5 animate-pulse rounded-full bg-warning" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold" :class="{ 'text-muted-foreground line-through': s.status === 'skipped' }">
            {{ s.day ? l(s.day, 'title') : '—' }}
          </p>
          <p class="text-sm text-muted-foreground">
            {{ fmtDate(s.started_at, locale) }}<template v-if="s.day"> · {{ $t('common.week') }} {{ s.day.week_no }}</template>
          </p>
          <p v-if="s.status !== 'skipped'" class="text-xs tabular-nums text-muted-foreground">
            <template v-if="s.duration_min !== null">{{ s.duration_min }} {{ $t('common.min') }} · </template>
            {{ s.sets_count }} {{ $t('history.sets') }} · {{ s.volume_kg }} {{ $t('common.kg') }}
          </p>
          <p v-else class="text-xs text-muted-foreground">{{ $t('status.skipped') }}</p>
        </div>
        <ChevronRight class="size-5 text-muted-foreground" />
      </RouterLink>
    </div>
    <Button v-if="nextPage && items.length" variant="outline" class="mt-4 w-full" :disabled="loading" @click="load">
      {{ $t('history.more') }}
    </Button>
    </template>
  </div>
</template>
