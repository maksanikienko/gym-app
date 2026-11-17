<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { CalendarCheck, ListChecks, History, Settings } from '@lucide/vue'
import { Toaster } from '@/components/ui/sonner'
import 'vue-sonner/style.css'
import { useAuth } from '@/stores/auth'

const route = useRoute()
const auth = useAuth()
const { t } = useI18n()

const showNav = computed(() => auth.user && !route.meta.guest && !route.meta.focus)
const tabs = computed(() => [
  { to: '/', label: t('nav.today'), icon: CalendarCheck },
  { to: '/programs', label: t('nav.programs'), icon: ListChecks },
  { to: '/history', label: t('nav.history'), icon: History },
  { to: '/settings', label: t('nav.settings'), icon: Settings },
])
const isActive = (to: string) => (to === '/' ? route.path === '/' : route.path.startsWith(to))
</script>

<template>
  <div class="min-h-dvh bg-background text-foreground">
    <main class="mx-auto max-w-xl px-4 pt-[max(1rem,env(safe-area-inset-top))]" :class="showNav ? 'pb-28' : 'pb-10'">
      <RouterView v-slot="{ Component, route: r }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="r.path" />
        </Transition>
      </RouterView>
    </main>

    <nav v-if="showNav"
      class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur pb-[env(safe-area-inset-bottom)]">
      <div class="mx-auto grid max-w-xl grid-cols-4">
        <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to"
          class="relative flex flex-col items-center gap-1 py-2.5 text-xs transition-colors duration-200"
          :class="isActive(tab.to) ? 'text-primary font-medium' : 'text-muted-foreground'">
          <span class="absolute top-0 h-0.5 rounded-full bg-primary transition-all duration-300"
            :class="isActive(tab.to) ? 'w-8 opacity-100' : 'w-0 opacity-0'" />
          <component :is="tab.icon" class="size-5 transition-transform duration-200" :class="isActive(tab.to) ? 'scale-110' : ''" />
          {{ tab.label }}
        </RouterLink>
      </div>
    </nav>
    <Toaster position="top-center" rich-colors />
  </div>
</template>
