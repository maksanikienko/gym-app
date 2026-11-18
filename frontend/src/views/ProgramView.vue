<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { CalendarDays, Copy, Dumbbell, Layers, MoreHorizontal, Pencil, Play, Trash2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import PageHeader from '@/components/PageHeader.vue'
import { confirmDialog } from '@/composables/useConfirm'
import { api, firstError } from '@/lib/api'
import { fmtTarget } from '@/lib/format'
import { useLocalized } from '@/composables/useLocalized'
import type { Cycle, Program } from '@/types'

const props = defineProps<{ id: string }>()
const router = useRouter()
const { t, te } = useI18n()
const { l } = useLocalized()
const program = ref<Program | null>(null)
const week = ref(1)
const busy = ref(false)

onMounted(async () => {
  try {
    program.value = await api<Program>(`/programs/${props.id}`)
  } catch (e) {
    toast.error(firstError(e))
    router.replace('/programs')
  }
})

const current = computed(() => program.value?.weeks.find((w) => w.week_no === week.value))
function blockLabel(b: string | null): string {
  if (!b) return ''
  if (te(`programs.block.${b}`)) return t(`programs.block.${b}`)
  const m = b.match(/^(block|wave)-(\d+)$/)
  return m ? t(`programs.${m[1]}N`, { n: m[2] }) : b
}

async function startProgram() {
  if (!program.value) return
  const active = await api<Cycle | undefined>('/cycles/current')
  if (active && !(await confirmDialog({ title: t('programs.startConfirm'), confirmText: t('programs.start') }))) return
  busy.value = true
  try {
    await api('/cycles', 'POST', { program_id: program.value.id })
    toast.success(t('programs.started'))
    router.push('/')
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    busy.value = false
  }
}

async function copy() {
  if (!program.value) return
  busy.value = true
  try {
    const p = await api<{ id: number }>(`/programs/${program.value.id}/copy`, 'POST')
    toast.success(t('programs.copiedToMine'))
    router.push(`/builder/${p.id}`)
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    busy.value = false
  }
}

async function remove() {
  if (!program.value || !(await confirmDialog({ title: t('programs.deleteConfirm'), confirmText: t('common.delete'), destructive: true }))) return
  await api(`/programs/${program.value.id}`, 'DELETE')
  router.replace('/programs')
}
</script>

<template>
  <div>
    <div v-if="!program" class="space-y-4">
      <Skeleton class="h-9 w-24 rounded-full" />
      <Skeleton class="h-10 w-3/4" />
      <Skeleton class="h-12 rounded-xl" />
      <Skeleton v-for="i in 3" :key="i" class="h-40 rounded-2xl" />
    </div>
    <template v-else>
      <PageHeader back="/programs" :title="l(program, 'name')">
        <template #actions>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="outline" size="icon" class="rounded-full" :aria-label="$t('common.more')"><MoreHorizontal /></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <template v-if="program.kind === 'custom'">
                <DropdownMenuItem @select="router.push(`/builder/${program.id}`)"><Pencil /> {{ $t('programs.edit') }}</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" @select="remove"><Trash2 /> {{ $t('common.delete') }}</DropdownMenuItem>
              </template>
              <DropdownMenuItem v-else :disabled="busy" @select="copy"><Copy /> {{ $t('programs.copy') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </template>
        <div class="mt-3 flex flex-wrap gap-1.5">
          <Badge variant="secondary"><CalendarDays /> {{ $t('programs.daysPerWeek', { n: program.days_per_week }) }}</Badge>
          <Badge variant="secondary"><Layers /> {{ $t('programs.weeks', { n: program.weeks.length }) }}</Badge>
          <Badge v-if="program.kind === 'custom'" variant="soft">{{ $t('programs.mine') }}</Badge>
        </div>
        <p v-if="l(program, 'description')" class="mt-3 text-[15px] leading-relaxed text-muted-foreground">{{ l(program, 'description') }}</p>
      </PageHeader>

      <div class="space-y-4">
        <Button size="xl" class="w-full" :disabled="busy" @click="startProgram"><Play class="fill-current" /> {{ $t('programs.start') }}</Button>

        <Tabs v-model="week">
          <TabsList class="no-scrollbar w-full justify-start overflow-x-auto">
            <TabsTrigger v-for="w in program.weeks" :key="w.week_no" :value="w.week_no" class="flex-none px-3.5 tabular-nums">
              {{ $t('common.week') }} {{ w.week_no }}
            </TabsTrigger>
          </TabsList>
        </Tabs>
        <p v-if="current?.block" class="px-1 text-xs font-semibold uppercase tracking-wider text-primary">{{ blockLabel(current.block) }}</p>

        <Card v-for="(d, i) in current?.days ?? []" :key="`${week}-${d.id}`" class="rise gap-0 overflow-hidden py-0" :style="{ '--i': i }">
          <div class="flex items-center gap-3 border-b bg-muted/30 px-4 py-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/12 text-sm font-bold text-primary">{{ i + 1 }}</span>
            <h3 class="min-w-0 flex-1 truncate font-semibold">{{ l(d, 'title') }}</h3>
            <Badge variant="outline" class="text-muted-foreground"><Dumbbell /> {{ d.exercises.length }}</Badge>
          </div>
          <ul class="divide-y text-sm">
            <li v-for="x in d.exercises" :key="x.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
              <span class="min-w-0">{{ l(x.exercise, 'name') }}</span>
              <span class="flex shrink-0 flex-wrap justify-end gap-1">
                <Badge variant="secondary" class="font-semibold">{{ fmtTarget(x, $t('common.sec')) }}</Badge>
                <Badge v-if="x.target_rpe" variant="outline" class="text-muted-foreground">RPE {{ x.target_rpe }}</Badge>
                <Badge v-if="x.load_pct" variant="outline" class="text-muted-foreground">{{ x.load_pct }}%</Badge>
              </span>
            </li>
          </ul>
        </Card>
      </div>
    </template>
  </div>
</template>
