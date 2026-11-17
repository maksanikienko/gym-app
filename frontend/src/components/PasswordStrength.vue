<script setup lang="ts">
import { computed } from 'vue'
import { passwordStrength } from '@/lib/validation'

const props = defineProps<{ password: string }>()
const score = computed(() => passwordStrength(props.password))
const colors = ['bg-destructive', 'bg-destructive', 'bg-warning', 'bg-success', 'bg-success']
</script>

<template>
  <div class="space-y-1">
    <div class="grid grid-cols-4 gap-1">
      <span v-for="i in 4" :key="i" class="h-1 rounded-full transition-colors duration-300"
        :class="password && i <= Math.max(1, score) ? colors[score] : 'bg-muted'" />
    </div>
    <p class="text-xs text-muted-foreground">
      <template v-if="password">{{ $t(`auth.strength.${score}`) }} · </template>{{ $t('auth.passwordHint') }}
    </p>
  </div>
</template>
