<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { sanitizeNumber } from '@/lib/numbers'

/**
 * Text field that only accepts a non-negative number: digits and (with `decimals`) one "." or ",".
 * Letters, minus, "e", "+" are dropped as you type, and a keystroke that would exceed `max` is ignored,
 * so the value can never leave [0, max]. Uses type="text" — type="number" lets "e", "-" and "+" through.
 */
const props = withDefaults(defineProps<{
  modelValue?: string | number | null
  max: number
  /** Digits allowed after the separator; 0 = integers only. */
  decimals?: number
  /** Emit number | null instead of the raw string. */
  asNumber?: boolean
  class?: HTMLAttributes['class']
}>(), { modelValue: null, decimals: 0, asNumber: false })

const emit = defineEmits<{ 'update:modelValue': [value: string | number | null] }>()

const shown = () => (props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue))

function onInput(e: Event) {
  const el = e.target as HTMLInputElement
  const clean = sanitizeNumber(el.value, { max: props.max, decimals: props.decimals, previous: shown() })
  // Write back to the field directly: if the value is rejected the prop doesn't change and Vue wouldn't re-render.
  if (el.value !== clean) {
    const caret = Math.max(0, (el.selectionStart ?? clean.length) - (el.value.length - clean.length))
    el.value = clean
    el.setSelectionRange?.(caret, caret)
  }
  emit('update:modelValue', props.asNumber ? (clean === '' ? null : Number(clean.replace(',', '.'))) : clean)
}
</script>

<template>
  <input
    :value="shown()"
    type="text"
    :inputmode="decimals ? 'decimal' : 'numeric'"
    autocomplete="off"
    data-slot="input"
    :class="cn(
      'placeholder:text-muted-foreground/70 selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-11 w-full min-w-0 rounded-lg border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow,border-color] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
      'focus-visible:border-ring focus-visible:ring-ring/40 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
    @input="onInput"
  >
</template>
