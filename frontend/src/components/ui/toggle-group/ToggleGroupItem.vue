<script setup lang="ts">
import type { ToggleGroupItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ToggleGroupItem, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ToggleGroupItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <ToggleGroupItem
    data-slot="toggle-group-item"
    v-bind="forwardedProps"
    :class="cn(
      'text-muted-foreground inline-flex h-8 min-w-9 items-center justify-center gap-1.5 rounded-md px-2.5 text-sm font-medium transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:shadow-sm dark:data-[state=on]:bg-input/60 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4',
      props.class,
    )"
  >
    <slot />
  </ToggleGroupItem>
</template>
