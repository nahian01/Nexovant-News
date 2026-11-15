<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { RouterLink, useRouter } from 'vue-router'
import {
  Activity,
  Bell,
  Building2,
  ChevronDown,
  CreditCard,
  LogOut,
  Menu,
  Search,
  Sliders,
  Sparkles,
  User,
  X,
} from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import GlobalSearchModal from '@/components/layout/GlobalSearchModal.vue'
import { workspaceService } from '@/services/api/workspace.service'

const emit = defineEmits<{
  (e: 'toggle-mobile-nav'): void
}>()

const authStore = useAuthStore()
const router = useRouter()
const isSearchOpen = ref(false)
const isProfileDropdownOpen = ref(false)
const unreadNotificationCount = ref(0)

const handleLogout = async () => {
  isProfileDropdownOpen.value = false
  await authStore.logout()
  router.push('/login')
}

const toggleProfileDropdown = () => {
  isProfileDropdownOpen.value = !isProfileDropdownOpen.value
}

const closeProfileDropdown = () => {
  isProfileDropdownOpen.value = false
}

const planTierLabel = computed(() => {
  const plan = (authStore.user as any)?.plan_id || 'executive'
  if (plan === 'enterprise') return 'Enterprise Tier'
  if (plan === 'professional') return 'Professional Tier'
  return 'Executive Tier'
})

onMounted(async () => {
  try {
    const res = await workspaceService.getOverview()
    if (res?.overview?.metrics?.unread_notifications !== undefined) {
      unreadNotificationCount.value = res.overview.metrics.unread_notifications
    }
  } catch {
    //
  }
})
</script>

<template>
  <header class="sticky top-0 z-30 flex h-[68px] items-center justify-between border-b border-nexo-border/80 bg-white/95 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
    <!-- Left Section: Mobile Menu Trigger + Status Indicators -->
    <div class="flex items-center gap-3 sm:gap-4">
      <!-- Mobile Menu Toggle Button -->
      <button
        type="button"
        @click="emit('toggle-mobile-nav')"
        class="flex h-9 w-9 items-center justify-center rounded-xl border border-nexo-border text-nexo-text-secondary transition hover:bg-nexo-bg lg:hidden"
        aria-label="Toggle navigation menu"
      >
        <Menu class="h-5 w-5" />
      </button>

      <!-- Brand (Mobile/Tablet view) -->
      <RouterLink to="/" class="flex items-center gap-2 lg:hidden">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-nexo-accent-cyan text-sm font-semibold text-white">N</span>
        <span class="font-serif text-sm font-semibold tracking-tight text-nexo-text-primary">Nexovant</span>
      </RouterLink>

      <!-- Active Feed Indicator -->
      <div class="hidden items-center gap-2 rounded-full border border-nexo-border bg-nexo-bg px-3 py-1.5 text-xs font-medium text-nexo-text-primary sm:flex">
        <span class="relative flex h-2 w-2">
          <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-60"></span>
          <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600"></span>
        </span>
        <span>Intelligence Feed Active</span>
      </div>

      <!-- Verification Engine Status -->
      <div class="hidden items-center gap-1.5 text-xs font-medium text-nexo-text-secondary md:flex">
        <Activity class="h-3.5 w-3.5 text-nexo-accent-cyan" />
        <span>Verification Engine <span class="font-mono text-[11px] text-nexo-text-muted">v1.0</span></span>
      </div>
    </div>

    <!-- Right Section: Global Search + Notifications + Account Menu -->
    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Global Search Trigger -->
      <button
        type="button"
        @click="isSearchOpen = true"
        class="flex items-center gap-2 rounded-xl border border-nexo-border bg-nexo-bg px-3 py-1.5 text-xs text-nexo-text-muted transition hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary sm:w-56 sm:justify-between"
        aria-label="Search intelligence"
      >
        <span class="flex items-center gap-2">
          <Search class="h-3.5 w-3.5 text-nexo-text-muted" />
          <span class="hidden sm:inline">Search intelligence...</span>
          <span class="sm:hidden">Search</span>
        </span>
        <kbd class="hidden rounded border border-nexo-border bg-white px-1.5 py-0.5 text-[10px] font-mono text-nexo-text-muted sm:inline-block">⌘K</kbd>
      </button>

      <!-- Notification Bell -->
      <RouterLink
        to="/workspace/notifications"
        class="relative flex h-9 w-9 items-center justify-center rounded-xl border border-nexo-border text-nexo-text-secondary transition hover:border-nexo-accent-cyan/40 hover:bg-nexo-bg hover:text-nexo-text-primary"
        aria-label="View notifications"
      >
        <Bell class="h-4 w-4" />
        <span
          v-if="unreadNotificationCount > 0"
          class="absolute -right-1 -top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-rose-600 px-1 font-mono text-[9px] font-bold text-white shadow-xs"
        >
          {{ unreadNotificationCount > 9 ? '9+' : unreadNotificationCount }}
        </span>
      </RouterLink>

      <!-- User Account Dropdown -->
      <div v-if="authStore.user" class="relative">
        <button
          type="button"
          @click="toggleProfileDropdown"
          class="flex items-center gap-2 rounded-xl border border-transparent p-1 transition hover:border-nexo-border hover:bg-nexo-bg focus:outline-none"
          aria-label="User account menu"
        >
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e9f3ed] font-serif text-xs font-semibold text-nexo-accent-cyan ring-1 ring-nexo-accent-cyan/20">
            {{ (authStore.user.name || 'U').charAt(0).toUpperCase() }}
          </span>
          <div class="hidden text-left sm:block">
            <p class="max-w-[120px] truncate text-xs font-semibold text-nexo-text-primary">{{ authStore.user.name }}</p>
            <p class="text-[10px] text-nexo-text-muted">{{ planTierLabel }}</p>
          </div>
          <ChevronDown class="hidden h-3.5 w-3.5 text-nexo-text-muted sm:block" />
        </button>

        <!-- Dropdown Menu Overlay & Card -->
        <div
          v-if="isProfileDropdownOpen"
          @click="closeProfileDropdown"
          class="fixed inset-0 z-40"
        ></div>

        <div
          v-if="isProfileDropdownOpen"
          class="absolute right-0 top-full z-50 mt-2 w-64 rounded-2xl border border-nexo-border bg-white p-2 text-left shadow-xl"
        >
          <!-- User Details Header -->
          <div class="border-b border-nexo-border/70 px-3 py-2.5">
            <p class="font-serif text-sm font-semibold text-nexo-text-primary">{{ authStore.user.name }}</p>
            <p class="truncate text-xs text-nexo-text-muted">{{ authStore.user.email }}</p>
            <div class="mt-2 flex items-center justify-between">
              <Badge variant="confirmed" size="sm" :dot="false">{{ planTierLabel }}</Badge>
              <span v-if="authStore.user.account_type === 'admin'" class="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase text-nexo-accent-gold">Admin</span>
            </div>
          </div>

          <!-- Quick Navigation Links -->
          <div class="py-1.5">
            <RouterLink
              to="/dashboard"
              @click="closeProfileDropdown"
              class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"
            >
              <Sparkles class="h-3.5 w-3.5 text-nexo-accent-cyan" />
              <span>Workspace Overview</span>
            </RouterLink>
            <RouterLink
              to="/organization"
              @click="closeProfileDropdown"
              class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"
            >
              <Building2 class="h-3.5 w-3.5 text-nexo-accent-gold" />
              <span>Team Workspace</span>
            </RouterLink>
            <RouterLink
              to="/billing"
              @click="closeProfileDropdown"
              class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"
            >
              <CreditCard class="h-3.5 w-3.5 text-nexo-text-muted" />
              <span>Subscription & Invoices</span>
            </RouterLink>
            <RouterLink
              to="/workspace/preferences"
              @click="closeProfileDropdown"
              class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"
            >
              <Sliders class="h-3.5 w-3.5 text-nexo-text-muted" />
              <span>Feed & Alert Preferences</span>
            </RouterLink>
          </div>

          <!-- Logout Button -->
          <div class="border-t border-nexo-border/70 pt-1.5">
            <button
              type="button"
              @click="handleLogout"
              class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
            >
              <LogOut class="h-3.5 w-3.5" />
              <span>Sign out</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Sign In Button if not logged in -->
      <RouterLink v-else to="/login">
        <Button variant="primary" size="sm">Sign In</Button>
      </RouterLink>
    </div>
  </header>

  <!-- Global Search Modal -->
  <GlobalSearchModal :is-open="isSearchOpen" @close="isSearchOpen = false" />
</template>
