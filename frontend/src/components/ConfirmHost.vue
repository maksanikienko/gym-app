<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import {
  AlertDialog, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { confirmState as state, settleConfirm } from '@/composables/useConfirm'
</script>

<template>
  <AlertDialog :open="state.open" @update:open="(v) => !v && settleConfirm(false)">
    <AlertDialogContent>
      <AlertDialogHeader class="items-center text-center sm:items-start sm:text-left">
        <div v-if="state.options.destructive" class="mb-1 grid size-12 place-items-center rounded-full bg-destructive/12 text-destructive">
          <TriangleAlert class="size-6" />
        </div>
        <AlertDialogTitle class="text-balance">{{ state.options.title }}</AlertDialogTitle>
        <AlertDialogDescription :class="{ 'sr-only': !state.options.description }">
          {{ state.options.description || state.options.title }}
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel @click="settleConfirm(false)">{{ state.options.cancelText || $t('common.cancel') }}</AlertDialogCancel>
        <!-- Plain Button, not AlertDialogAction: its built-in close fires before our @click and would settle as "cancel" -->
        <Button size="lg" :variant="state.options.destructive ? 'destructive' : 'default'" @click="settleConfirm(true)">
          {{ state.options.confirmText || $t('common.confirm') }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
