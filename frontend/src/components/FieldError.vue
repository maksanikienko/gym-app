<script setup lang="ts">
defineProps<{ message?: string | null }>()
</script>

<template>
  <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-1"
    leave-active-class="transition duration-150" leave-to-class="opacity-0">
    <p v-if="message" class="text-sm text-destructive" role="alert">{{ message }}</p>
  </Transition>
</template>
