<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { Plus, Search } from '@lucide/vue'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useExercises } from '@/stores/exercises'
import { useLocalized } from '@/composables/useLocalized'
import { firstError } from '@/lib/api'
import type { Exercise } from '@/types'

const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ select: [exercise: Exercise] }>()

const { t } = useI18n()
const { l, locale } = useLocalized()
const store = useExercises()
const query = ref('')
const muscle = ref<string | null>(null)
const busy = ref(false)
const muscles = ['chest', 'back', 'lats', 'shoulders', 'biceps', 'triceps', 'quads', 'hamstrings', 'glutes', 'calves', 'abs']

watch(open, (v) => {
  if (v) {
    query.value = ''
    void store.load()
  }
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return store.list
    .filter((e) => !muscle.value || e.muscles.includes(muscle.value))
    .filter((e) => !q || `${e.name_en} ${e.name_ru ?? ''}`.toLowerCase().includes(q))
    .sort((a, b) => Number(b.custom) - Number(a.custom) || l(a, 'name').localeCompare(l(b, 'name')))
})

function pick(e: Exercise) {
  emit('select', e)
  open.value = false
}

async function createCustom() {
  const name = query.value.trim()
  if (!name) return
  busy.value = true
  try {
    const ex = await store.create({ [`name_${locale.value}`]: name, muscles: muscle.value ? [muscle.value] : [] })
    toast.success(t('builder.exerciseCreated'))
    pick(ex)
  } catch (e) {
    toast.error(firstError(e))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="flex max-h-[85dvh] flex-col gap-3 p-4 sm:max-w-lg">
      <DialogHeader><DialogTitle>{{ $t('builder.pickExercise') }}</DialogTitle></DialogHeader>
      <label class="flex items-center gap-2 rounded-lg border px-3 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/30">
        <Search class="size-4 text-muted-foreground" />
        <input v-model="query" :placeholder="$t('builder.search')" class="h-11 w-full bg-transparent text-base outline-none" />
      </label>
      <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4 pb-1">
        <button v-for="m in [null, ...muscles]" :key="m ?? 'all'" type="button"
          class="shrink-0 rounded-full border px-3 py-1 text-xs transition-all active:scale-95"
          :class="muscle === m ? 'border-primary bg-primary text-primary-foreground' : 'text-muted-foreground'"
          @click="muscle = m">
          {{ m ? $t(`muscles.${m}`) : $t('builder.all') }}
        </button>
      </div>
      <div class="-mx-1 min-h-0 flex-1 overflow-y-auto">
        <button v-for="e in filtered" :key="e.id" type="button"
          class="flex w-full flex-col items-start rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-accent active:bg-accent"
          @click="pick(e)">
          <span class="flex items-center gap-2 font-medium">
            {{ l(e, 'name') }}
            <span v-if="e.custom" class="rounded bg-primary/10 px-1.5 text-[10px] font-semibold uppercase text-primary">{{ $t('programs.mine') }}</span>
          </span>
          <span class="text-xs text-muted-foreground">{{ e.muscles.map((m) => $t(`muscles.${m}`)).join(' · ') }}</span>
        </button>
        <p v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-muted-foreground">{{ $t('builder.nothingFound') }}</p>
      </div>
      <button v-if="query.trim()" type="button" :disabled="busy"
        class="flex items-center justify-center gap-2 rounded-lg border border-dashed py-2.5 text-sm font-medium text-primary transition-colors hover:border-primary active:scale-[0.99]"
        @click="createCustom">
        <Plus class="size-4" /> {{ $t('builder.createExercise', { name: query.trim() }) }}
      </button>
    </DialogContent>
  </Dialog>
</template>
