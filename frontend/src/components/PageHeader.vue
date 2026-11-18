<script setup lang="ts">
import { useRouter } from 'vue-router'
import { ChevronLeft } from '@lucide/vue'

const props = defineProps<{
  title?: string
  subtitle?: string
  eyebrow?: string
  /** Show a back link; a string is the fallback route when there is no history. */
  back?: boolean | string
}>()

const router = useRouter()
function goBack() {
  if (window.history.state?.back) router.back()
  else router.push(typeof props.back === 'string' ? props.back : '/')
}
</script>

<template>
  <header class="mb-5">
    <button v-if="back" type="button"
      class="-ml-2 mb-1 inline-flex h-9 items-center gap-0.5 rounded-full pr-3 pl-1 text-[15px] font-medium text-primary transition-colors active:bg-accent"
      @click="goBack">
      <ChevronLeft class="size-5" /> {{ $t('common.back') }}
    </button>
    <div class="flex items-end justify-between gap-3">
      <div class="min-w-0 flex-1">
        <p v-if="eyebrow" class="mb-0.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ eyebrow }}</p>
        <h1 class="text-[28px] leading-tight font-bold tracking-tight text-balance">
          <slot name="title">{{ title }}</slot>
        </h1>
        <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-muted-foreground">
          <slot name="subtitle">{{ subtitle }}</slot>
        </p>
      </div>
      <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2 pb-0.5">
        <slot name="actions" />
      </div>
    </div>
    <slot />
  </header>
</template>
