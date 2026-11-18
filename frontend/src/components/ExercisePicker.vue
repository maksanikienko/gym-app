<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { ChevronRight, Plus, Search } from '@lucide/vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Item, ItemActions, ItemContent, ItemDescription, ItemTitle } from '@/components/ui/item'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
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
  <Sheet v-model:open="open">
    <SheetContent class="h-[88dvh] gap-3">
      <SheetHeader>
        <SheetTitle>{{ $t('builder.pickExercise') }}</SheetTitle>
        <SheetDescription class="sr-only">{{ $t('builder.search') }}</SheetDescription>
      </SheetHeader>
      <div class="relative px-4">
        <Search class="pointer-events-none absolute top-1/2 left-7 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input v-model="query" type="search" :placeholder="$t('builder.search')" class="pl-10" />
      </div>
      <div class="no-scrollbar flex gap-1.5 overflow-x-auto px-4 pb-0.5">
        <Badge v-for="m in [null, ...muscles]" :key="m ?? 'all'" as="button" type="button"
          :variant="muscle === m ? 'default' : 'outline'"
          class="h-8 shrink-0 cursor-pointer px-3 text-[13px] transition-all active:scale-95"
          :class="muscle === m ? '' : 'text-muted-foreground'"
          @click="muscle = m">
          {{ m ? $t(`muscles.${m}`) : $t('builder.all') }}
        </Badge>
      </div>
      <div class="min-h-0 flex-1 space-y-0.5 overflow-y-auto px-2">
        <Item v-for="e in filtered" :key="e.id" as="button" type="button" size="sm" class="w-full rounded-xl text-left" @click="pick(e)">
          <ItemContent>
            <ItemTitle>
              <span class="truncate">{{ l(e, 'name') }}</span>
              <Badge v-if="e.custom" variant="soft" class="px-1.5 text-[10px] uppercase">{{ $t('programs.mine') }}</Badge>
            </ItemTitle>
            <ItemDescription class="text-xs">{{ e.muscles.map((m) => $t(`muscles.${m}`)).join(' · ') }}</ItemDescription>
          </ItemContent>
          <ItemActions><ChevronRight class="size-4 text-muted-foreground/50" /></ItemActions>
        </Item>
        <p v-if="!filtered.length" class="px-3 py-8 text-center text-sm text-muted-foreground">{{ $t('builder.nothingFound') }}</p>
      </div>
      <div v-if="query.trim()" class="px-4">
        <Button variant="outline" size="lg" class="w-full border-dashed text-primary" :disabled="busy" @click="createCustom">
          <Plus /> {{ $t('builder.createExercise', { name: query.trim() }) }}
        </Button>
      </div>
    </SheetContent>
  </Sheet>
</template>
