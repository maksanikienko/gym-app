<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <p data-slot="item-description" :class="cn('text-muted-foreground line-clamp-2 text-[13px] leading-normal font-normal', props.class)">
    <slot />
  </p>
</template>
