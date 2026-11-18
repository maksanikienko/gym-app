<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { CalendarCheck, ListChecks, History, Settings } from '@lucide/vue'
import { Toaster } from '@/components/ui/sonner'
import 'vue-sonner/style.css'
import ConfirmHost from '@/components/ConfirmHost.vue'
import { useAuth } from '@/stores/auth'
import { isDark } from '@/lib/theme'

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
  <div class="relative min-h-dvh bg-background text-foreground">
    <div class="app-glow pointer-events-none absolute inset-x-0 top-0 h-80" aria-hidden="true" />
    <!-- Covers the iOS status bar so scrolled content doesn't show through it -->
    <div class="pointer-events-none fixed inset-x-0 top-0 z-50 h-[env(safe-area-inset-top)] bg-background" />

    <main class="relative mx-auto max-w-xl px-4 pt-[calc(env(safe-area-inset-top)+1rem)]"
      :class="showNav ? 'pb-[calc(env(safe-area-inset-bottom)+6.5rem)]' : 'pb-10'">
      <RouterView v-slot="{ Component, route: r }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="r.path" />
        </Transition>
      </RouterView>
    </main>

    <!-- Floating tab bar -->
    <Transition name="timer">
      <nav v-if="showNav" class="pointer-events-none fixed inset-x-0 bottom-0 z-40 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div class="pointer-events-auto mx-auto grid max-w-md grid-cols-4 gap-1 rounded-[1.375rem] border bg-background/90 p-1.5 shadow-xl shadow-black/10 backdrop-blur-xl backdrop-saturate-150 dark:bg-card/90 dark:shadow-black/40">
          <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to"
            class="relative flex flex-col items-center gap-0.5 rounded-2xl py-2 text-[11px] font-medium transition-colors duration-200"
            :class="isActive(tab.to) ? 'text-primary' : 'text-muted-foreground active:text-foreground'">
            <span class="absolute inset-0 rounded-2xl bg-primary/12 transition-all duration-300"
              :class="isActive(tab.to) ? 'scale-100 opacity-100' : 'scale-75 opacity-0'" />
            <component :is="tab.icon" class="relative size-[22px] transition-transform duration-200"
              :stroke-width="isActive(tab.to) ? 2.4 : 2" />
            <span class="relative">{{ tab.label }}</span>
          </RouterLink>
        </div>
      </nav>
    </Transition>

    <ConfirmHost />
    <Toaster position="top-center" rich-colors close-button :theme="isDark ? 'dark' : 'light'"
      :mobile-offset="{ top: 'calc(env(safe-area-inset-top) + 8px)' }"
      :offset="{ top: 'calc(env(safe-area-inset-top) + 16px)' }" />
  </div>
</template>
