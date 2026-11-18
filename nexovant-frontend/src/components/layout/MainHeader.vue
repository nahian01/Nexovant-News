<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, RouterLink, useRoute } from 'vue-router'
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
import GlobalSearchModal from '@/components/layout/GlobalSearchModal.vue'
import NotificationDrawer from '@/components/layout/NotificationDrawer.vue'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const isSearchOpen = ref(false)
const isNotificationsOpen = ref(false)
const isProfileMenuOpen = ref(false)

const userInitials = computed(() => {
  if (!authStore.user?.name) return 'AB'
  const parts = authStore.user.name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }
  return authStore.user.name.substring(0, 2).toUpperCase()
})

const handleLogout = async () => {
  await authStore.logout()
  isProfileMenuOpen.value = false
  router.push('/')
}
</script>

<template>
  <!-- Top Real-Time Platform Status Banner -->
  <div class="bg-[#08121C] text-white/90 text-xs py-1 px-4 sm:px-6 flex items-center justify-between border-b border-[#263244]">
    <span class="truncate flex items-center gap-2">
      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
      <span class="font-mono text-[11px] text-white/80">Nexovant Live Intelligence Network</span>
      <span class="hidden sm:inline text-white/40">|</span>
      <span class="hidden sm:inline text-white/70 text-[11px]">Global Corporate Directory & Real-Time Strategic Scanners Active</span>
    </span>
    <span class="text-white/60 text-[11px] shrink-0 ml-4 font-mono">{{ authStore.isAuthenticated ? 'Enterprise Member' : 'Global Access' }}</span>
  </div>

  <header class="sticky top-0 z-40 h-[64px] w-full border-b border-nexo-border bg-white px-4 sm:px-6">
    <div class="mx-auto flex h-full max-w-[1440px] items-center justify-between gap-4">
      <!-- Left: Brand Logo -->
      <div class="flex items-center gap-6">
        <RouterLink to="/" class="flex items-center gap-2.5">
          <span class="flex h-7 w-7 items-center justify-center rounded-[2px] bg-nexo-ink text-sm font-bold text-white">N</span>
          <span class="font-serif text-[22px] font-bold tracking-tight text-nexo-ink">Nexovant</span>
        </RouterLink>
      </div>

      <!-- Center: Universal Search Bar with [/] shortcut -->
      <button
        type="button"
        @click="isSearchOpen = true"
        class="hidden h-10 w-full max-w-[480px] items-center justify-between rounded-[4px] border border-nexo-border bg-white px-3.5 text-sm text-nexo-text-muted transition hover:border-nexo-cobalt md:flex shadow-xs"
        :aria-label="authStore.isAuthenticated ? 'Search stories, companies, people, your library' : 'Search stories, companies, people'"
      >
        <span class="flex items-center gap-2.5 text-xs text-nexo-muted">
          <Search class="h-4 w-4 text-nexo-muted" />
          {{ authStore.isAuthenticated ? 'Search stories, companies, people, your library' : 'Search stories, companies, people' }}
        </span>
        <kbd class="rounded-[2px] border border-nexo-border bg-nexo-paper px-1.5 py-0.5 font-mono text-[11px] text-nexo-muted">/</kbd>
      </button>

      <!-- Center-Right: Public Primary Navigation Links -->
      <nav v-if="!authStore.isAuthenticated" class="hidden items-center gap-6 text-sm font-medium text-nexo-text-2 lg:flex">
        <RouterLink to="/" :class="['transition-colors hover:text-nexo-ink py-1', route.path === '/' ? 'text-nexo-ink font-semibold border-b-2 border-nexo-ink' : '']">Discover</RouterLink>
        <RouterLink to="/trends" :class="['transition-colors hover:text-nexo-ink py-1', route.path === '/trends' ? 'text-nexo-ink font-semibold border-b-2 border-nexo-ink' : '']">Trending</RouterLink>
        <RouterLink to="/categories" :class="['transition-colors hover:text-nexo-ink py-1', route.path === '/categories' ? 'text-nexo-ink font-semibold border-b-2 border-nexo-ink' : '']">Categories</RouterLink>
      </nav>

      <!-- Right: Actions & User Navigation -->
      <div class="relative flex items-center gap-3">
        <!-- Mobile Search Button -->
        <button
          type="button"
          @click="isSearchOpen = true"
          class="rounded-[4px] p-2 text-nexo-text-2 transition hover:bg-nexo-paper md:hidden"
          aria-label="Search"
        >
          <Search class="h-5 w-5" />
        </button>

        <!-- Signed-In View -->
        <template v-if="authStore.isAuthenticated">
          <!-- Upgrade Button (Design Token: Brass text & border) -->
          <RouterLink
            to="/pricing"
            class="hidden sm:inline-flex items-center justify-center rounded-[4px] border border-nexo-brass bg-white px-3.5 py-1.5 text-xs font-semibold text-nexo-brass-text transition hover:bg-nexo-brass-tint/30"
          >
            Upgrade
          </RouterLink>

          <!-- Notification Bell with Unread Badge -->
          <div class="relative">
            <button
              type="button"
              @click="isNotificationsOpen = !isNotificationsOpen"
              class="relative rounded-[4px] border border-nexo-border p-2 text-nexo-text-2 transition hover:bg-nexo-paper hover:text-nexo-ink"
              aria-label="Notifications"
            >
              <Bell class="h-4 w-4" />
              <span class="absolute -right-1.5 -top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-nexo-breaking text-[10px] font-bold text-white">3</span>
            </button>
            <NotificationDrawer :is-open="isNotificationsOpen" @close="isNotificationsOpen = false" />
          </div>

          <!-- User Avatar with Chevron -->
          <div class="relative">
            <button
              type="button"
              @click="isProfileMenuOpen = !isProfileMenuOpen"
              class="flex items-center gap-1.5 rounded-[4px] py-1 pl-1 pr-1.5 transition hover:bg-nexo-paper"
              aria-label="Open account menu"
            >
              <span class="flex h-8 w-8 items-center justify-center rounded-full bg-nexo-ink text-xs font-bold text-white">
                {{ userInitials }}
              </span>
              <ChevronDown class="h-3.5 w-3.5 text-nexo-muted" />
            </button>

            <!-- Dropdown Menu -->
            <div v-if="isProfileMenuOpen" class="absolute right-0 top-11 z-50 w-64 rounded-[2px] border border-nexo-border bg-white p-2 shadow-popover">
              <div class="border-b border-nexo-border px-3 py-2.5">
                <div class="truncate text-sm font-semibold text-nexo-ink">{{ authStore.user?.name || 'Abdullah' }}</div>
                <div class="mt-0.5 truncate text-xs text-nexo-muted">{{ authStore.user?.email || 'abdullah@company.com' }}</div>
                <span class="mt-2 inline-flex rounded-full bg-nexo-paper px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-nexo-text-2">{{ authStore.user?.account_type || 'Free Plan' }}</span>
              </div>
              <RouterLink to="/dashboard" @click="isProfileMenuOpen = false" class="flex items-center gap-2.5 rounded-[4px] px-3 py-2 text-sm text-nexo-text-2 transition hover:bg-nexo-paper hover:text-nexo-ink">
                <LayoutDashboard class="h-4 w-4" /> Dashboard
              </RouterLink>
              <RouterLink to="/settings" @click="isProfileMenuOpen = false" class="flex items-center gap-2.5 rounded-[4px] px-3 py-2 text-sm text-nexo-text-2 transition hover:bg-nexo-paper hover:text-nexo-ink">
                Account settings
              </RouterLink>
              <RouterLink to="/billing" @click="isProfileMenuOpen = false" class="flex items-center gap-2.5 rounded-[4px] px-3 py-2 text-sm text-nexo-text-2 transition hover:bg-nexo-paper hover:text-nexo-ink">
                <CreditCard class="h-4 w-4" /> Subscription & billing
              </RouterLink>
              <RouterLink v-if="authStore.user?.account_type === 'admin'" to="/admin/dashboard" @click="isProfileMenuOpen = false" class="flex items-center gap-2.5 rounded-[4px] px-3 py-2 text-sm text-nexo-brass-text transition hover:bg-nexo-brass-tint/30">
                <ShieldCheck class="h-4 w-4" /> Admin console
              </RouterLink>
              <button type="button" @click="handleLogout" class="flex w-full items-center gap-2.5 rounded-[4px] border-t border-nexo-border px-3 py-2 text-left text-sm text-nexo-breaking transition hover:bg-[#FBE4E1]/40">
                <LogOut class="h-4 w-4" /> Sign out
              </button>
            </div>
          </div>
        </template>

        <!-- Public View: Sign in + Join free (Solid Ink) -->
        <template v-else>
          <RouterLink to="/login" class="text-sm font-medium text-nexo-ink hover:underline px-2 py-1">
            Sign in
          </RouterLink>
          <RouterLink to="/register" class="inline-flex items-center justify-center rounded-[4px] bg-nexo-ink px-4 py-2 text-sm font-medium text-white transition hover:bg-[#1A2C42]">
            Join free
          </RouterLink>
        </template>
      </div>
    </div>
  </header>

  <GlobalSearchModal :is-open="isSearchOpen" @close="isSearchOpen = false" />
</template>
