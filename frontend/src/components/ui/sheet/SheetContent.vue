<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { X } from "@lucide/vue"
import { reactiveOmit } from "@vueuse/core"
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

interface SheetContentProps extends DialogContentProps {
  class?: HTMLAttributes["class"]
  side?: "top" | "right" | "bottom" | "left"
}

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<SheetContentProps>(), { side: "bottom" })
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class", "side")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      data-slot="sheet-overlay"
      class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/60 backdrop-blur-[2px]"
    />
    <DialogContent
      data-slot="sheet-content"
      :class="cn(
        'bg-background data-[state=open]:animate-in data-[state=closed]:animate-out fixed z-50 flex flex-col gap-4 shadow-2xl transition ease-in-out data-[state=closed]:duration-200 data-[state=open]:duration-300',
        side === 'right' && 'data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right inset-y-0 right-0 h-full w-3/4 border-l sm:max-w-sm',
        side === 'left' && 'data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left inset-y-0 left-0 h-full w-3/4 border-r sm:max-w-sm',
        side === 'top' && 'data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top inset-x-0 top-0 h-auto border-b',
        side === 'bottom' && 'data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom inset-x-0 bottom-0 mx-auto h-auto max-w-xl rounded-t-3xl border-t pb-[max(1rem,env(safe-area-inset-bottom))]',
        props.class,
      )"
      v-bind="{ ...$attrs, ...forwarded }"
    >
      <div v-if="side === 'bottom'" class="mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-muted-foreground/30" />
      <slot />

      <DialogClose
        class="ring-offset-background focus:ring-ring absolute top-4 right-4 grid size-8 place-items-center rounded-full bg-muted text-muted-foreground transition-colors hover:text-foreground focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none"
      >
        <X class="size-4" />
        <span class="sr-only">Close</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
