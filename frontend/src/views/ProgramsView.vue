<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { CalendarDays, ChevronRight, FolderPlus, Plus, Upload } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Item, ItemActions, ItemContent, ItemDescription, ItemMedia, ItemTitle } from '@/components/ui/item'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import PageHeader from '@/components/PageHeader.vue'
import { api, firstError } from '@/lib/api'
import { useLocalized } from '@/composables/useLocalized'
import type { ProgramSummary } from '@/types'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const tab = computed<'ready' | 'mine'>({
  get: () => (route.query.tab === 'mine' ? 'mine' : 'ready'),
  set: (v) => router.replace({ query: { tab: v } }),
})
// Ready-made = shared templates + the user's imported programs; Mine = built or copied by the user.
const shown = computed(() => programs.value.filter((p) => (tab.value === 'mine') === (p.kind === 'custom')))
const { l } = useLocalized()
const programs = ref<ProgramSummary[]>([])
const loading = ref(true)
const fileInput = ref<HTMLInputElement>()

async function load() {
  programs.value = await api<ProgramSummary[]>('/programs')
  loading.value = false
}
onMounted(load)

async function onFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try {
    const pack = JSON.parse(await file.text())
    const created = await api<ProgramSummary[]>('/programs/import', 'POST', pack)
    toast.success(t('programs.imported', { n: created.length }))
    tab.value = 'ready'
    await load()
  } catch (err) {
    toast.error(firstError(err))
  } finally {
    if (fileInput.value) fileInput.value.value = ''
  }
}
</script>

<template>
  <div>
    <PageHeader :title="$t('programs.title')">
      <template #actions>
        <Button variant="outline" size="icon" class="rounded-full" :aria-label="$t('programs.import')" :title="$t('programs.import')" @click="fileInput?.click()">
          <Upload />
        </Button>
        <Button size="sm" class="h-9 rounded-full px-4" @click="router.push('/builder')"><Plus /> {{ $t('programs.create') }}</Button>
      </template>
    </PageHeader>
    <input ref="fileInput" type="file" accept="application/json,.json" class="hidden" @change="onFile" />

    <Tabs v-model="tab" class="mb-4">
      <TabsList class="w-full">
        <TabsTrigger value="ready">{{ $t('programs.ready') }}</TabsTrigger>
        <TabsTrigger value="mine">{{ $t('programs.mineTab') }}</TabsTrigger>
      </TabsList>
    </Tabs>

    <div v-if="loading" class="space-y-2.5">
      <Skeleton v-for="i in 4" :key="i" class="h-[88px] rounded-2xl" />
    </div>
    <Empty v-else-if="!shown.length" class="rise py-12">
      <EmptyHeader>
        <EmptyMedia><FolderPlus /></EmptyMedia>
        <EmptyTitle>{{ $t('programs.emptyMine') }}</EmptyTitle>
        <EmptyDescription>{{ $t('programs.emptyMineHint') }}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button size="lg" @click="router.push('/builder')"><Plus /> {{ $t('programs.create') }}</Button>
      </EmptyContent>
    </Empty>
    <div v-else :key="tab" class="space-y-2.5">
      <Item v-for="(p, i) in shown" :key="p.id" as-child variant="card" class="rise" :style="{ '--i': i }">
        <RouterLink :to="`/programs/${p.id}`">
          <ItemMedia class="size-12 rounded-xl text-white" :class="p.kind === 'custom' ? 'hero' : 'bg-gradient-to-br from-zinc-500 to-zinc-700 dark:from-zinc-600 dark:to-zinc-800'">
            <span class="text-lg font-bold tabular-nums">{{ p.days_per_week }}×</span>
          </ItemMedia>
          <ItemContent>
            <ItemTitle class="truncate">{{ l(p, 'name') }}</ItemTitle>
            <ItemDescription class="flex items-center gap-1.5">
              <CalendarDays class="size-3.5" />
              {{ $t('programs.daysPerWeek', { n: p.days_per_week }) }} · {{ $t('programs.weeks', { n: p.weeks_count }) }}
            </ItemDescription>
            <ItemDescription v-if="p.kind !== 'custom' && l(p, 'description')" class="text-xs">{{ l(p, 'description') }}</ItemDescription>
          </ItemContent>
          <ItemActions>
            <ChevronRight class="size-5 text-muted-foreground/60 transition-transform group-hover/item:translate-x-0.5" />
          </ItemActions>
        </RouterLink>
      </Item>
    </div>
  </div>
</template>
