<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ value: number; size?: number; stroke?: number }>(), { size: 88, stroke: 8 })
const r = computed(() => (props.size - props.stroke) / 2)
const c = computed(() => 2 * Math.PI * r.value)
const offset = computed(() => c.value * (1 - Math.min(100, Math.max(0, props.value)) / 100))
</script>

<template>
  <div class="relative grid place-items-center" :style="{ width: `${size}px`, height: `${size}px` }">
    <svg :width="size" :height="size" class="-rotate-90">
      <circle :cx="size / 2" :cy="size / 2" :r="r" fill="none" stroke="currentColor" :stroke-width="stroke" class="opacity-20" />
      <circle :cx="size / 2" :cy="size / 2" :r="r" fill="none" stroke="currentColor" :stroke-width="stroke"
        stroke-linecap="round" :stroke-dasharray="c" :stroke-dashoffset="offset"
        class="transition-[stroke-dashoffset] duration-1000 ease-out" />
    </svg>
    <div class="absolute inset-0 grid place-items-center"><slot /></div>
  </div>
</template>
