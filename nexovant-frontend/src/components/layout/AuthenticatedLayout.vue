<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  Bookmark,
  Building2,
  ChartNoAxesCombined,
  Compass,
  CreditCard,
  FileText,
  Globe2,
  LayoutDashboard,
  Library,
  LogOut,
  Menu,
  Radar,
  Search,
  Sliders,
  Sparkles,
  Users,
  X,
} from 'lucide-vue-next'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import RightIntelligencePanel from '@/components/layout/RightIntelligencePanel.vue'
import GlobalSearchModal from '@/components/layout/GlobalSearchModal.vue'
import { useAuthStore } from '@/stores/auth'

withDefaults(
  defineProps<{
    showRightPanel?: boolean
  }>(),
  {
    showRightPanel: true,
  }
)

const route = useRoute()
const authStore = useAuthStore()
const isMobileDrawerOpen = ref(false)
const isSearchModalOpen = ref(false)

const mobileBottomNav = [
  { name: 'Feed', path: '/feed', icon: Sparkles },
  { name: 'Following', path: '/following', icon: Users },
  { name: 'Search', action: 'search', icon: Search },
  { name: 'Saved', path: '/saved', icon: Library },
  { name: 'Menu', action: 'menu', icon: Menu },
]

const handleBottomNavAction = (item: any) => {
  if (item.action === 'search') {
    isSearchModalOpen.value = true
  } else if (item.action === 'menu') {
    isMobileDrawerOpen.value = true
  }
}

const handleLogout = async () => {
  isMobileDrawerOpen.value = false
  await authStore.logout()
}

const isActive = (path?: string) => {
  if (!path) return false
  if (path === '/dashboard') return route.path === '/dashboard'
  if (path === '/feed') return route.path === '/feed'
  if (path === '/following') return route.path === '/following'
  if (path === '/saved') return route.path === '/saved' || route.path === '/workspace/library'
  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-left">
    <!-- Top Bar for Authenticated Subscribers -->
    <AppHeader @toggle-mobile-nav="isMobileDrawerOpen = true" />

    <!-- 3-Column Shell (Desktop) -->
    <div class="flex min-h-[calc(100vh-68px)] w-full">
      <!-- 1. Left Navigation Sidebar -->
      <Sidebar />

      <!-- 2. Main Content Stream -->
      <main class="min-w-0 flex-1 px-4 py-6 pb-24 sm:px-6 md:pb-12 lg:px-8">
        <slot />
      </main>

      <!-- 3. Right Intelligence Panel (Desktop Wide) -->
      <RightIntelligencePanel v-if="showRightPanel" />
    </div>

    <!-- Mobile Bottom Navigation Bar (Hidden on Desktop) -->
    <nav class="fixed bottom-0 left-0 right-0 z-40 flex h-16 items-center justify-around border-t border-nexo-border/80 bg-white/95 px-2 backdrop-blur-xl md:hidden">
      <template v-for="item in mobileBottomNav" :key="item.name">
        <RouterLink
          v-if="item.path"
          :to="item.path"
          :class="[
            'flex flex-col items-center justify-center gap-1 rounded-xl px-3 py-1.5 text-[11px] font-medium transition',
            isActive(item.path)
              ? 'text-nexo-accent-cyan font-semibold'
              : 'text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          <component :is="item.icon" class="h-5 w-5" />
          <span>{{ item.name }}</span>
        </RouterLink>

        <button
          v-else
          type="button"
          @click="handleBottomNavAction(item)"
          class="flex flex-col items-center justify-center gap-1 rounded-xl px-3 py-1.5 text-[11px] font-medium text-nexo-text-secondary transition hover:text-nexo-text-primary"
        >
          <component :is="item.icon" class="h-5 w-5" />
          <span>{{ item.name }}</span>
        </button>
      </template>
    </nav>

    <!-- Mobile Navigation Drawer Overlay -->
    <div
      v-if="isMobileDrawerOpen"
      @click="isMobileDrawerOpen = false"
      class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs lg:hidden"
    ></div>

    <!-- Mobile Navigation Drawer Content -->
    <div
      :class="[
        'fixed inset-y-0 left-0 z-50 flex w-[280px] flex-col justify-between bg-white p-5 shadow-2xl transition-transform duration-300 ease-in-out lg:hidden',
        isMobileDrawerOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <div class="overflow-y-auto">
        <!-- Drawer Header -->
        <div class="flex items-center justify-between border-b border-nexo-border/70 pb-4">
          <RouterLink to="/" @click="isMobileDrawerOpen = false" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-nexo-accent-cyan text-base font-semibold text-white">N</span>
            <span class="flex flex-col leading-none">
              <span class="font-serif text-base font-semibold text-nexo-text-primary">Nexovant</span>
              <span class="mt-1 text-[8px] font-semibold uppercase tracking-widest text-nexo-text-muted">Executive Intel</span>
            </span>
          </RouterLink>
          <button
            type="button"
            @click="isMobileDrawerOpen = false"
            class="rounded-lg p-1.5 text-nexo-text-muted hover:bg-nexo-bg"
            aria-label="Close menu"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <!-- Full Mobile Navigation Hierarchy -->
        <div class="mt-4 space-y-4">
          <div>
            <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-nexo-text-muted">Workspace</p>
            <div class="space-y-0.5">
              <RouterLink
                to="/dashboard"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/dashboard') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <LayoutDashboard class="h-4 w-4" /> Overview
              </RouterLink>
              <RouterLink
                to="/feed"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/feed') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <Sparkles class="h-4 w-4" /> For You (Feed)
              </RouterLink>
              <RouterLink
                to="/following"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/following') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <Users class="h-4 w-4" /> Following
              </RouterLink>
              <RouterLink
                to="/trends"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/trends') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <ChartNoAxesCombined class="h-4 w-4" /> Trending Intelligence
              </RouterLink>
              <RouterLink
                to="/workspace/watchlists"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/workspace/watchlists') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <Bookmark class="h-4 w-4" /> Watchlists
              </RouterLink>
              <RouterLink
                to="/workspace/monitors"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/workspace/monitors') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <Radar class="h-4 w-4" /> Custom Monitors
              </RouterLink>
              <RouterLink
                to="/saved"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/saved') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <Library class="h-4 w-4" /> Saved Intelligence
              </RouterLink>
              <RouterLink
                to="/workspace/research"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/workspace/research') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <Compass class="h-4 w-4" /> Deep Research
              </RouterLink>
              <RouterLink
                to="/reports"
                @click="isMobileDrawerOpen = false"
                :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium', isActive('/reports') ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold' : 'text-nexo-text-secondary']"
              >
                <FileText class="h-4 w-4" /> Intelligence Reports
              </RouterLink>
            </div>
          </div>

          <div class="border-t border-nexo-border/70 pt-3">
            <p class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-widest text-nexo-text-muted">Account & Organization</p>
            <div class="space-y-0.5">
              <RouterLink
                to="/organization"
                @click="isMobileDrawerOpen = false"
                class="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium text-nexo-text-secondary"
              >
                <Building2 class="h-4 w-4" /> Team Workspace
              </RouterLink>
              <RouterLink
                to="/billing"
                @click="isMobileDrawerOpen = false"
                class="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium text-nexo-text-secondary"
              >
                <CreditCard class="h-4 w-4" /> Subscription & Billing
              </RouterLink>
              <RouterLink
                to="/workspace/preferences"
                @click="isMobileDrawerOpen = false"
                class="flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium text-nexo-text-secondary"
              >
                <Sliders class="h-4 w-4" /> Feed Preferences
              </RouterLink>
            </div>
          </div>
        </div>
      </div>

      <!-- Drawer Footer -->
      <div class="border-t border-nexo-border/70 pt-4">
        <button
          type="button"
          @click="handleLogout"
          class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
        >
          <LogOut class="h-4 w-4" />
          <span>Sign Out</span>
        </button>
      </div>
    </div>

    <!-- Search Modal -->
    <GlobalSearchModal :is-open="isSearchModalOpen" @close="isSearchModalOpen = false" />
  </div>
</template>
