<script setup lang="ts">
import { ref } from 'vue'
import { Eye, EyeOff } from '@lucide/vue'
import { Input } from '@/components/ui/input'
import { PASSWORD_MAX } from '@/lib/validation'

defineOptions({ inheritAttrs: false })
const model = defineModel<string>({ required: true })
defineProps<{ invalid?: boolean }>()
const shown = ref(false)
</script>

<template>
  <div class="relative">
    <Input v-bind="$attrs" v-model="model" :type="shown ? 'text' : 'password'" :maxlength="PASSWORD_MAX"
      autocapitalize="off" autocorrect="off" spellcheck="false" :aria-invalid="invalid || undefined" class="pr-11" />
    <button type="button" tabindex="-1" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-muted-foreground transition-colors hover:text-foreground"
      :aria-label="shown ? $t('auth.hidePassword') : $t('auth.showPassword')" @click="shown = !shown">
      <EyeOff v-if="shown" class="size-4" />
      <Eye v-else class="size-4" />
    </button>
  </div>
</template>
