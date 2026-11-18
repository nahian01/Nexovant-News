<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  Lock,
  MoreHorizontal,
  ShieldCheck,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
const mobileMenuOpen = ref(false)

const publicExploreItems = [
  { name: 'Home', path: '/' },
  { name: 'Discover', path: '/' },
  { name: 'Trending', path: '/trends' },
]

const publicBrowseItems = [
  { name: 'Companies & Markets', path: '/companies' },
  { name: 'Trending Intelligence', path: '/trends' },
  { name: 'Research Engine', path: '/search' },
  { name: 'Executive Briefings', path: '/blog' },
]

const signedInFeedItems = [
  { name: 'For You', path: '/feed' },
  { name: 'Latest Stream', path: '/feed?filter=latest' },
  { name: 'Trending Intelligence', path: '/trends' },
  { name: 'Following', path: '/following' },
]

const signedInBrowseItems = [
  { name: 'Global Companies', path: '/companies' },
  { name: 'Deep Research', path: '/workspace/research' },
  { name: 'Custom Monitors', path: '/workspace/monitors' },
]

const signedInLibraryItems = [
  { name: 'Saved', path: '/saved' },
  { name: 'Reports', path: '/reports' },
  { name: 'Notifications', path: '/notifications', badge: '3' },
]

const signedInExecutiveItems = [
  { name: 'Executive Cockpit', path: '/executive/dashboard' },
  { name: 'Briefings', path: '/executive/briefings' },
  { name: 'Strategic Impact', path: '/executive/strategic-impact' },
  { name: 'Opportunities & Risks', path: '/executive/opportunities-risks' },
  { name: 'Scenario Analysis', path: '/executive/scenarios' },
  { name: 'Decision Center', path: '/executive/decisions' },
  { name: 'Executive Assistant', path: '/executive/assistant' },
]

const isActive = (path: string) => {
  if (path === '/') return route.path === '/'
  if (path.includes('?')) {
    const [p, q] = path.split('?')
    return route.path === p && route.fullPath.includes(q)
  }
  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<template>
  <aside class="sticky top-[64px] hidden h-[calc(100vh-64px)] w-[224px] shrink-0 flex-col justify-between overflow-y-auto border-r border-nexo-border bg-nexo-bg px-4 py-6 text-left md:flex">
    <!-- Public Navigation Sidebar (matches Component sidebar.png & 1 News-first homepage) -->
    <template v-if="!authStore.isAuthenticated">
      <div class="space-y-6">
        <!-- EXPLORE -->
        <nav class="space-y-1">
          <p class="section-label px-2 pb-2">EXPLORE</p>
          <RouterLink
            v-for="item in publicExploreItems"
            :key="item.name"
            :to="item.path"
            :class="[
              'flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm transition-colors',
              isActive(item.path)
                ? 'bg-nexo-paper-2 font-semibold text-nexo-ink'
                : 'text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink'
            ]"
          >
            <span>{{ item.name }}</span>
          </RouterLink>
        </nav>

        <!-- BROWSE -->
        <nav class="space-y-1">
          <p class="section-label px-2 pb-2">BROWSE</p>
          <RouterLink
            v-for="item in publicBrowseItems"
            :key="item.name"
            :to="item.path"
            :class="[
              'flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm transition-colors',
              isActive(item.path)
                ? 'bg-nexo-paper-2 font-semibold text-nexo-ink'
                : 'text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink'
            ]"
          >
            <span>{{ item.name }}</span>
          </RouterLink>
        </nav>

        <!-- YOUR ACCOUNT -->
        <nav class="space-y-1">
          <p class="section-label px-2 pb-2">YOUR ACCOUNT</p>
          <RouterLink
            to="/register?prompt=following"
            class="flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink"
          >
            <span>Following</span>
            <Lock class="h-3 w-3 text-nexo-muted" />
          </RouterLink>
          <RouterLink
            to="/register?prompt=reports"
            class="flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink"
          >
            <span>Reports</span>
            <Lock class="h-3 w-3 text-nexo-muted" />
          </RouterLink>
        </nav>
      </div>

      <!-- Public Bottom Callout Box -->
      <div class="mt-6 rounded-[2px] border border-nexo-border bg-white p-4">
        <p class="text-xs font-semibold text-nexo-ink">Build a feed around your interests.</p>
        <p class="mt-1 text-xs text-nexo-text-2 leading-relaxed">Follow companies, executives and industries. Free to join.</p>
        <RouterLink
          to="/register"
          class="mt-3 inline-flex w-full items-center justify-center rounded-[4px] bg-nexo-ink px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#1A2C42]"
        >
          Join free
        </RouterLink>
      </div>
    </template>

    <!-- Signed-in Navigation Sidebar (matches Component sidebar.png & P18 Dashboard) -->
    <template v-else>
      <div class="space-y-6">
        <!-- YOUR FEED -->
        <nav class="space-y-1">
          <p class="section-label px-2 pb-2">YOUR FEED</p>
          <RouterLink
            v-for="item in signedInFeedItems"
            :key="item.name"
            :to="item.path"
            :class="[
              'flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm transition-colors',
              isActive(item.path)
                ? 'bg-nexo-paper-2 font-semibold text-nexo-ink'
                : 'text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink'
            ]"
          >
            <span>{{ item.name }}</span>
          </RouterLink>
        </nav>

        <!-- BROWSE -->
        <nav class="space-y-1">
          <p class="section-label px-2 pb-2">BROWSE</p>
          <RouterLink
            v-for="item in signedInBrowseItems"
            :key="item.name"
            :to="item.path"
            :class="[
              'flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm transition-colors',
              isActive(item.path)
                ? 'bg-nexo-paper-2 font-semibold text-nexo-ink'
                : 'text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink'
            ]"
          >
            <span>{{ item.name }}</span>
          </RouterLink>
        </nav>

        <!-- LIBRARY -->
        <nav class="space-y-1">
          <p class="section-label px-2 pb-2">LIBRARY</p>
          <RouterLink
            v-for="item in signedInLibraryItems"
            :key="item.name"
            :to="item.path"
            :class="[
              'flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm transition-colors',
              isActive(item.path)
                ? 'bg-nexo-paper-2 font-semibold text-nexo-ink'
                : 'text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink'
            ]"
          >
            <span>{{ item.name }}</span>
            <span v-if="item.badge" class="flex h-4 w-4 items-center justify-center rounded-full bg-nexo-breaking text-[10px] font-bold text-white">
              {{ item.badge }}
            </span>
          </RouterLink>
        </nav>

        <!-- EXECUTIVE INTELLIGENCE -->
        <nav class="space-y-1 border-t border-nexo-border pt-4">
          <p class="section-label px-2 pb-2 text-nexo-cobalt">EXECUTIVE</p>
          <RouterLink
            v-for="item in signedInExecutiveItems"
            :key="item.name"
            :to="item.path"
            :class="[
              'flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm transition-colors',
              isActive(item.path)
                ? 'bg-nexo-paper-2 font-semibold text-nexo-ink'
                : 'text-nexo-text-2 hover:bg-nexo-paper-2/60 hover:text-nexo-ink'
            ]"
          >
            <span>{{ item.name }}</span>
          </RouterLink>
        </nav>

        <!-- ADMIN LINK IF APPLICABLE -->
        <nav v-if="authStore.user?.account_type === 'admin'" class="space-y-1 border-t border-nexo-border pt-4">
          <p class="section-label px-2 pb-2 text-nexo-brass-text">ADMINISTRATION</p>
          <RouterLink to="/admin/dashboard" class="flex items-center justify-between rounded-[4px] px-2.5 py-1.5 text-sm font-semibold text-nexo-brass-text hover:bg-nexo-brass-tint/30">
            <span>Admin console</span>
            <ShieldCheck class="h-3.5 w-3.5" />
          </RouterLink>
        </nav>
      </div>

      <!-- Signed-in Bottom Plan Card -->
      <div class="mt-6 rounded-[2px] border border-nexo-border bg-white p-4">
        <p class="section-label text-[10px]">YOUR PLAN</p>
        <p class="mt-1 font-serif text-lg font-bold text-nexo-ink">Free</p>
        <p class="mt-1 text-xs text-nexo-text-2 leading-relaxed">2 of 3 alerts used. Premium unlocks monitoring and briefings.</p>
        <RouterLink
          to="/pricing"
          class="mt-3 inline-flex w-full items-center justify-center rounded-[4px] border border-nexo-border bg-white px-3 py-1.5 text-xs font-semibold text-nexo-ink transition hover:border-nexo-brass hover:text-nexo-brass-text"
        >
          See plans
        </RouterLink>
      </div>
    </template>
  </aside>

  <!-- Mobile Bottom Nav -->
  <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-nexo-border bg-white px-2 py-2 md:hidden">
    <RouterLink to="/" class="flex flex-col items-center gap-1 text-[11px] font-medium text-nexo-text-2">
      <span>Discover</span>
    </RouterLink>
    <RouterLink to="/trends" class="flex flex-col items-center gap-1 text-[11px] font-medium text-nexo-text-2">
      <span>Trending</span>
    </RouterLink>
    <RouterLink to="/feed" class="flex flex-col items-center gap-1 text-[11px] font-medium text-nexo-text-2">
      <span>Feed</span>
    </RouterLink>
    <RouterLink :to="authStore.isAuthenticated ? '/dashboard' : '/login'" class="flex flex-col items-center gap-1 text-[11px] font-medium text-nexo-text-2">
      <span>{{ authStore.isAuthenticated ? 'Account' : 'Sign in' }}</span>
    </RouterLink>
  </nav>
</template>
