<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { CalendarDays, ChevronRight, FolderPlus, Plus, Upload } from '@lucide/vue'
import { Button } from '@/components/ui/button'
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
    <h1 class="mb-4 text-2xl font-bold tracking-tight">{{ $t('programs.title') }}</h1>

    <div class="relative mb-4 grid grid-cols-2 rounded-xl bg-muted p-1">
      <span class="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-lg bg-background shadow-sm transition-transform duration-300 ease-out"
        :class="tab === 'mine' ? 'translate-x-full' : ''" />
      <button v-for="k in (['ready', 'mine'] as const)" :key="k" type="button"
        class="relative z-10 py-2 text-sm font-medium transition-colors"
        :class="tab === k ? 'text-foreground' : 'text-muted-foreground'" @click="tab = k">
        {{ k === 'ready' ? $t('programs.ready') : $t('programs.mineTab') }}
      </button>
    </div>

    <div v-if="tab === 'mine'" class="mb-3">
      <Button class="w-full" @click="router.push('/builder')"><Plus /> {{ $t('programs.create') }}</Button>
    </div>
    <div v-else class="mb-3 flex justify-end">
      <Button variant="outline" size="sm" @click="fileInput?.click()"><Upload /> {{ $t('programs.import') }}</Button>
    </div>
    <input ref="fileInput" type="file" accept="application/json,.json" class="hidden" @change="onFile" />

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-xl bg-muted" />
    </div>
    <div v-else-if="!shown.length" class="rise flex flex-col items-center gap-2 rounded-2xl border border-dashed px-6 py-10 text-center">
      <FolderPlus class="size-10 text-muted-foreground/60" />
      <p class="font-medium">{{ $t('programs.emptyMine') }}</p>
      <p class="text-sm text-muted-foreground">{{ $t('programs.emptyMineHint') }}</p>
    </div>
    <div v-else :key="tab" class="space-y-2">
      <RouterLink v-for="(p, i) in shown" :key="p.id" :to="`/programs/${p.id}`"
        class="rise group flex items-center gap-4 rounded-xl border bg-card p-4 transition-all hover:border-primary/50 active:scale-[0.99]"
        :style="{ '--i': i }">
        <div class="grid size-12 shrink-0 place-items-center rounded-xl text-white"
          :class="p.kind === 'custom' ? 'hero' : 'bg-muted-foreground/70'">
          <span class="text-lg font-bold">{{ p.days_per_week }}×</span>
        </div>
        <div class="min-w-0 flex-1 space-y-1">
          <span class="block truncate font-semibold">{{ l(p, 'name') }}</span>
          <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
            <CalendarDays class="size-3.5" />
            {{ $t('programs.daysPerWeek', { n: p.days_per_week }) }} · {{ $t('programs.weeks', { n: p.weeks_count }) }}
          </p>
          <p v-if="p.kind !== 'custom' && l(p, 'description')" class="line-clamp-2 text-xs text-muted-foreground">{{ l(p, 'description') }}</p>
        </div>
        <ChevronRight class="size-5 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
      </RouterLink>
    </div>
  </div>
</template>
