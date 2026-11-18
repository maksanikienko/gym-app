<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{ class?: HTMLAttributes["class"]; variant?: "default" | "icon" }>(), { variant: "icon" })
</script>

<template>
  <div
    data-slot="empty-icon"
    :data-variant="variant"
    :class="cn(
      'mb-1 flex shrink-0 items-center justify-center',
      variant === 'icon' && 'bg-primary/10 text-primary size-14 rounded-2xl [&_svg:not([class*=\'size-\'])]:size-7',
      props.class,
    )"
  >
    <slot />
  </div>
</template>
