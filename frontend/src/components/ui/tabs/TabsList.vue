<script setup lang="ts">
import type { TabsListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TabsIndicator, TabsList } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <TabsList
    data-slot="tabs-list"
    v-bind="delegatedProps"
    :class="cn('bg-muted text-muted-foreground relative inline-flex h-10 w-fit items-center justify-center rounded-xl p-1', props.class)"
  >
    <!-- Sliding active pill -->
    <TabsIndicator
      class="absolute top-1 bottom-1 left-0 w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) rounded-lg bg-background shadow-sm transition-[width,translate] duration-300 ease-out dark:bg-input/60"
    />
    <slot />
  </TabsList>
</template>
