<script setup lang="ts">
import { ref } from 'vue'
import { Eye, EyeOff } from '@lucide/vue'
import { PASSWORD_MAX } from '@/lib/validation'

defineOptions({ inheritAttrs: false })
const model = defineModel<string>({ required: true })
defineProps<{ invalid?: boolean }>()
const shown = ref(false)
</script>

<template>
  <div class="relative">
    <input v-bind="$attrs" v-model="model" :type="shown ? 'text' : 'password'" :maxlength="PASSWORD_MAX"
      autocapitalize="off" autocorrect="off" spellcheck="false" :aria-invalid="invalid || undefined"
      class="h-11 w-full rounded-md border bg-transparent px-3 pr-11 text-base outline-none transition-shadow focus:border-primary focus:ring-2 focus:ring-primary/30 aria-invalid:border-destructive aria-invalid:ring-destructive/20" />
    <button type="button" tabindex="-1" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-muted-foreground"
      :aria-label="shown ? $t('auth.hidePassword') : $t('auth.showPassword')" @click="shown = !shown">
      <EyeOff v-if="shown" class="size-4" />
      <Eye v-else class="size-4" />
    </button>
  </div>
</template>
