<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { ChevronDown } from "@lucide/vue"
import { cn } from "@/lib/utils"

defineOptions({ inheritAttrs: false })

const model = defineModel<any>()
const props = defineProps<{ class?: HTMLAttributes["class"]; selectClass?: HTMLAttributes["class"]; hideIcon?: boolean }>()
</script>

<template>
  <div data-slot="native-select-wrapper" :class="cn('relative w-full min-w-0', props.class)">
    <select
      v-bind="$attrs"
      v-model="model"
      data-slot="native-select"
      :class="cn(
        'border-input dark:bg-input/30 h-11 w-full min-w-0 appearance-none rounded-lg border bg-transparent py-1 pr-8 pl-3 text-base shadow-xs transition-[color,box-shadow,border-color] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        'focus-visible:border-ring focus-visible:ring-ring/40 focus-visible:ring-[3px]',
        hideIcon && 'px-1 text-center',
        props.selectClass,
      )"
    >
      <slot />
    </select>
    <ChevronDown v-if="!hideIcon" class="text-muted-foreground pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 opacity-60 select-none" aria-hidden="true" />
  </div>
</template>
