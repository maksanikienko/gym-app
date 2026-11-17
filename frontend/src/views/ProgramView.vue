<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { ArrowLeft, Copy, Pencil, Play, Trash2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
  if (active && !confirm(t('programs.startConfirm'))) return
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
  if (!program.value || !confirm(t('programs.deleteConfirm'))) return
  await api(`/programs/${program.value.id}`, 'DELETE')
  router.replace('/programs')
}
</script>

<template>
  <div>
    <button type="button" class="mb-3 flex items-center gap-1 text-sm text-muted-foreground" @click="router.back()">
      <ArrowLeft class="size-4" /> {{ $t('common.back') }}
    </button>

    <p v-if="!program" class="text-muted-foreground">{{ $t('common.loading') }}</p>
    <div v-else class="space-y-4">
      <div class="space-y-1">
        <h1 class="text-2xl font-bold">{{ l(program, 'name') }}</h1>
        <p class="text-sm text-muted-foreground">
          {{ $t('programs.daysPerWeek', { n: program.days_per_week }) }} · {{ $t('programs.weeks', { n: program.weeks.length }) }}
        </p>
        <p v-if="l(program, 'description')" class="pt-1 text-sm">{{ l(program, 'description') }}</p>
      </div>

      <div class="flex gap-2">
        <Button size="lg" class="h-11 flex-1" :disabled="busy" @click="startProgram"><Play /> {{ $t('programs.start') }}</Button>
        <Button v-if="program.kind === 'custom'" size="lg" variant="outline" class="h-11" @click="remove"><Trash2 /></Button>
      </div>
      <Button v-if="program.kind === 'custom'" variant="outline" class="w-full" @click="router.push(`/builder/${program.id}`)">
        <Pencil /> {{ $t('programs.edit') }}
      </Button>
      <Button v-else variant="outline" class="w-full" :disabled="busy" @click="copy">
        <Copy /> {{ $t('programs.copy') }}
      </Button>

      <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1">
        <button v-for="w in program.weeks" :key="w.week_no" type="button"
          class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm tabular-nums transition-all duration-200 active:scale-95"
          :class="w.week_no === week ? 'bg-primary text-primary-foreground border-primary' : ''"
          @click="week = w.week_no">
          {{ $t('common.week') }} {{ w.week_no }}
        </button>
      </div>
      <p v-if="current?.block" class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ blockLabel(current.block) }}</p>

      <Card v-for="(d, i) in current?.days ?? []" :key="`${week}-${d.id}`" class="rise" :style="{ '--i': i }">
        <CardHeader class="pb-2"><CardTitle class="text-base">{{ l(d, 'title') }}</CardTitle></CardHeader>
        <CardContent>
          <ul class="divide-y text-sm">
            <li v-for="x in d.exercises" :key="x.id" class="flex items-baseline justify-between gap-3 py-2">
              <span>{{ l(x.exercise, 'name') }}</span>
              <span class="shrink-0 text-right tabular-nums text-muted-foreground">
                {{ fmtTarget(x, $t('common.sec')) }}
                <template v-if="x.target_rpe"> · RPE {{ x.target_rpe }}</template>
              <template v-if="x.load_pct"> · {{ x.load_pct }}%</template>
              </span>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
