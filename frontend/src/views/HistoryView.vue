<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RecordsList from '@/components/RecordsList.vue'
import { toast } from 'vue-sonner'
import { Ban, CheckCircle2, ChevronRight, Clock, Dumbbell, History, Loader2, Weight } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Item, ItemActions, ItemContent, ItemDescription, ItemMedia, ItemTitle } from '@/components/ui/item'
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import PageHeader from '@/components/PageHeader.vue'
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
    <PageHeader :title="$t('history.title')" />

    <Tabs v-model="tab">
      <TabsList class="mb-1 w-full">
        <TabsTrigger value="workouts">{{ $t('history.workouts') }}</TabsTrigger>
        <TabsTrigger value="records">{{ $t('records.title') }}</TabsTrigger>
      </TabsList>

      <TabsContent value="records"><RecordsList /></TabsContent>

      <TabsContent value="workouts">
        <div v-if="loading && !items.length" class="space-y-2.5">
          <Skeleton v-for="i in 5" :key="i" class="h-[84px] rounded-2xl" />
        </div>
        <Empty v-else-if="!items.length" class="rise py-12">
          <EmptyHeader>
            <EmptyMedia><History /></EmptyMedia>
            <EmptyTitle class="text-base font-medium">{{ $t('history.empty') }}</EmptyTitle>
          </EmptyHeader>
        </Empty>

        <div class="space-y-2.5">
          <Item v-for="(s, i) in items" :key="s.id" as-child variant="card" class="rise" :style="{ '--i': i % 20 }">
            <RouterLink :to="`/workout/${s.id}`">
              <ItemMedia variant="icon" :class="{
                'bg-success/15 text-success': s.status === 'finished',
                'bg-muted text-muted-foreground': s.status === 'skipped',
                'bg-warning/15 text-warning': s.status === 'in_progress',
              }">
                <CheckCircle2 v-if="s.status === 'finished'" />
                <Ban v-else-if="s.status === 'skipped'" />
                <span v-else class="size-2.5 animate-pulse rounded-full bg-warning" />
              </ItemMedia>
              <ItemContent>
                <ItemTitle :class="{ 'text-muted-foreground line-through': s.status === 'skipped' }">
                  <span class="truncate">{{ s.day ? l(s.day, 'title') : '—' }}</span>
                </ItemTitle>
                <ItemDescription class="capitalize">
                  {{ fmtDate(s.started_at, locale) }}<template v-if="s.day"> · {{ $t('common.week') }} {{ s.day.week_no }}</template>
                </ItemDescription>
                <div v-if="s.status !== 'skipped'" class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs tabular-nums text-muted-foreground">
                  <span v-if="s.duration_min !== null" class="inline-flex items-center gap-1"><Clock class="size-3" /> {{ s.duration_min }} {{ $t('common.min') }}</span>
                  <span class="inline-flex items-center gap-1"><Dumbbell class="size-3" /> {{ s.sets_count }} {{ $t('history.sets') }}</span>
                  <span class="inline-flex items-center gap-1"><Weight class="size-3" /> {{ s.volume_kg }} {{ $t('common.kg') }}</span>
                </div>
              </ItemContent>
              <ItemActions><ChevronRight class="size-5 text-muted-foreground/50" /></ItemActions>
            </RouterLink>
          </Item>
        </div>
        <Button v-if="nextPage && items.length" variant="outline" size="lg" class="mt-4 w-full" :disabled="loading" @click="load">
          <Loader2 v-if="loading" class="animate-spin" /> {{ $t('history.more') }}
        </Button>
      </TabsContent>
    </Tabs>
  </div>
</template>
