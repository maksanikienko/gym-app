<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div data-slot="item-title" :class="cn('flex min-w-0 items-center gap-2 text-[15px] leading-snug font-semibold', props.class)">
    <slot />
  </div>
</template>
