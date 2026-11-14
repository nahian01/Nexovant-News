<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import {
  Bell,
  ChevronDown,
  CreditCard,
  LayoutDashboard,
  LogOut,
  Search,
  ShieldCheck,
  TrendingUp,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import GlobalSearchModal from '@/components/layout/GlobalSearchModal.vue'
import NotificationDrawer from '@/components/layout/NotificationDrawer.vue'

const authStore = useAuthStore()
const router = useRouter()

const isSearchOpen = ref(false)
const isNotificationsOpen = ref(false)
const isProfileMenuOpen = ref(false)

const handleLogout = async () => {
  await authStore.logout()
  isProfileMenuOpen.value = false
  router.push('/')
}
</script>

<template>
  <header class="sticky top-0 z-40 h-[68px] w-full border-b border-nexo-border/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6">
    <div class="mx-auto flex h-full max-w-[1600px] items-center justify-between gap-5">
      <div class="flex min-w-fit items-center gap-7">
        <RouterLink to="/" class="group flex items-center gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-nexo-accent-cyan text-lg font-semibold text-white shadow-cyan-glow transition-transform group-hover:-rotate-3">N</span>
          <span class="flex flex-col leading-none">
            <span class="font-serif text-[20px] font-semibold tracking-tight text-nexo-text-primary">Nexovant</span>
            <span class="mt-1 text-[9px] font-bold uppercase tracking-[0.18em] text-nexo-accent-cyan">Intelligence Beyond Headlines</span>
          </span>
        </RouterLink>

        <RouterLink
          to="/trends"
          class="hidden items-center gap-2 rounded-full border border-nexo-border bg-nexo-bg px-3 py-1.5 text-xs font-medium text-nexo-text-secondary transition hover:border-nexo-accent-cyan/40 hover:text-nexo-accent-cyan xl:inline-flex"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <TrendingUp class="h-3.5 w-3.5 text-nexo-accent-cyan" />
          Market Pulse
        </RouterLink>
      </div>

      <button
        type="button"
        @click="isSearchOpen = true"
        class="hidden h-10 w-full max-w-[430px] items-center justify-between rounded-xl border border-nexo-border bg-nexo-bg px-3.5 text-sm text-nexo-text-muted transition hover:border-nexo-accent-cyan/40 hover:bg-white md:flex shadow-sm"
        aria-label="Search companies, executives, and stories"
      >
        <span class="flex items-center gap-2.5 text-xs"><Search class="h-4 w-4 text-nexo-text-muted" /> Search companies, executives, filings...</span>
        <kbd class="rounded-md border border-nexo-border bg-white px-1.5 py-0.5 font-mono text-[10px] text-nexo-text-muted shadow-xs">⌘ K</kbd>
      </button>

      <div class="relative flex items-center gap-2.5">
        <button
          type="button"
          @click="isSearchOpen = true"
          class="rounded-xl p-2.5 text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-accent-cyan md:hidden"
          aria-label="Search"
        >
          <Search class="h-5 w-5" />
        </button>

        <div class="relative">
          <button
            type="button"
            @click="isNotificationsOpen = !isNotificationsOpen"
            class="relative rounded-xl border border-transparent p-2.5 text-nexo-text-secondary transition hover:border-nexo-border hover:bg-nexo-bg hover:text-nexo-text-primary"
            aria-label="Notifications"
          >
            <Bell class="h-[18px] w-[18px]" />
            <span class="absolute right-2 top-2 h-2 w-2 rounded-full border-2 border-white bg-nexo-accent-cyan"></span>
          </button>
          <NotificationDrawer :is-open="isNotificationsOpen" @close="isNotificationsOpen = false" />
        </div>

        <div v-if="authStore.isAuthenticated" class="relative">
          <button
            type="button"
            @click="isProfileMenuOpen = !isProfileMenuOpen"
            class="flex items-center gap-2 rounded-xl border border-transparent py-1.5 pl-1.5 pr-2 transition hover:border-nexo-border hover:bg-nexo-bg"
            aria-label="Open account menu"
          >
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#e5f1eb] text-sm font-semibold text-nexo-accent-cyan">
              {{ authStore.user?.name ? authStore.user.name.charAt(0).toUpperCase() : 'U' }}
            </span>
            <span class="hidden max-w-[110px] truncate text-left text-xs font-semibold text-nexo-text-primary sm:block">{{ authStore.user?.name || 'Account' }}</span>
            <ChevronDown class="hidden h-3.5 w-3.5 text-nexo-text-muted sm:block" />
          </button>

          <div v-if="isProfileMenuOpen" class="absolute right-0 top-12 z-50 w-64 rounded-2xl border border-nexo-border bg-white p-2 shadow-xl">
            <div class="border-b border-nexo-border px-3 py-3">
              <div class="truncate text-sm font-semibold text-nexo-text-primary">{{ authStore.user?.name }}</div>
              <div class="mt-0.5 truncate text-xs text-nexo-text-muted">{{ authStore.user?.email }}</div>
              <span class="mt-2 inline-flex rounded-full bg-teal-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-nexo-accent-cyan">{{ authStore.user?.account_type }}</span>
            </div>
            <RouterLink to="/dashboard" @click="isProfileMenuOpen = false" class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary">
              <LayoutDashboard class="h-4 w-4" /> Executive workspace
            </RouterLink>
            <RouterLink to="/billing" @click="isProfileMenuOpen = false" class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary">
              <CreditCard class="h-4 w-4" /> Subscription & billing
            </RouterLink>
            <RouterLink v-if="authStore.user?.account_type === 'admin'" to="/admin/dashboard" @click="isProfileMenuOpen = false" class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm text-nexo-accent-gold transition hover:bg-amber-50">
              <ShieldCheck class="h-4 w-4" /> Admin console
            </RouterLink>
            <button type="button" @click="handleLogout" class="flex w-full items-center gap-2.5 rounded-xl border-t border-nexo-border px-3 py-2.5 text-left text-sm text-nexo-status-danger transition hover:bg-rose-50">
              <LogOut class="h-4 w-4" /> Sign out
            </button>
          </div>
        </div>

        <div v-else class="flex items-center gap-1 sm:gap-2">
          <RouterLink to="/login"><Button variant="ghost" size="sm">Sign in</Button></RouterLink>
          <RouterLink to="/register"><Button variant="primary" size="sm">Get started</Button></RouterLink>
        </div>
      </div>
    </div>
  </header>

  <GlobalSearchModal :is-open="isSearchOpen" @close="isSearchOpen = false" />
</template>
