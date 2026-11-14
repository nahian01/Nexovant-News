<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import { ref } from 'vue'
import {
  Activity,
  Banknote,
  Building2,
  ChartNoAxesCombined,
  FileText,
  Globe2,
  LayoutDashboard,
  MoreHorizontal,
  ShieldCheck,
  Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
const mobileMoreOpen = ref(false)

const navItems = [
  { name: 'Dashboard', path: '/dashboard', icon: LayoutDashboard },
  { name: 'Global feed', path: '/feed', icon: Globe2 },
  { name: 'Trending signals', path: '/trends', icon: ChartNoAxesCombined },
  { name: 'Companies', path: '/companies', icon: Building2 },
  { name: 'Briefings & reports', path: '/reports', icon: FileText },
  { name: 'Billing', path: '/billing', icon: Banknote },
  { name: 'Team workspace', path: '/organization', icon: Users },
]

const adminItems = [
  { name: 'AI gateway', path: '/admin/ai-router', icon: Activity },
  { name: 'Payment review', path: '/admin/payments', icon: ShieldCheck },
]

const mobileItems = navItems.slice(0, 4)
const isActive = (path: string) => route.path === path || route.path.startsWith(`${path}/`)
</script>

<template>
  <aside class="sticky top-0 hidden h-screen w-[252px] shrink-0 flex-col justify-between border-r border-nexo-border/80 bg-white px-3 py-5 lg:flex">
    <div class="overflow-y-auto">
      <RouterLink to="/" class="mb-8 flex items-center gap-3 px-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-nexo-accent-cyan text-lg font-semibold text-white">N</span>
        <span class="flex flex-col leading-none">
          <span class="font-serif text-[18px] font-semibold tracking-tight text-nexo-text-primary">Nexovant</span>
          <span class="mt-1 text-[9px] font-semibold uppercase tracking-[0.15em] text-nexo-text-muted">Executive intelligence</span>
        </span>
      </RouterLink>

      <nav class="space-y-1">
        <p class="px-3 pb-2 pt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">Workspace</p>
        <RouterLink
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          :class="['flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium transition', isActive(item.path) ? 'bg-[#e9f3ed] text-nexo-accent-cyan' : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary']"
        >
          <component :is="item.icon" class="h-[17px] w-[17px]" />
          {{ item.name }}
        </RouterLink>
      </nav>

      <nav v-if="authStore.user?.account_type === 'admin'" class="mt-6 space-y-1 border-t border-nexo-border pt-5">
        <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-nexo-accent-gold">Administration</p>
        <RouterLink
          v-for="item in adminItems"
          :key="item.path"
          :to="item.path"
          :class="['flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium transition', isActive(item.path) ? 'bg-amber-50 text-nexo-accent-gold' : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary']"
        >
          <component :is="item.icon" class="h-[17px] w-[17px]" />
          {{ item.name }}
        </RouterLink>
      </nav>
    </div>

    <div class="rounded-2xl border border-nexo-border bg-nexo-bg p-4">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold text-nexo-text-primary">Your intelligence plan</span>
        <span class="rounded-full bg-[#e9f3ed] px-2 py-1 text-[9px] font-semibold uppercase tracking-wide text-nexo-accent-cyan">Active</span>
      </div>
      <p class="mt-2 text-[11px] leading-relaxed text-nexo-text-secondary">Research, context and daily briefings in one focused workspace.</p>
    </div>
  </aside>

  <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-nexo-border bg-white/95 px-2 pb-[env(safe-area-inset-bottom)] pt-2 shadow-[0_-8px_30px_rgba(20,48,35,0.08)] backdrop-blur-xl lg:hidden" aria-label="Workspace navigation">
    <RouterLink v-for="item in mobileItems" :key="item.path" :to="item.path" :class="['flex flex-col items-center gap-1 px-1 py-1.5 text-[10px] font-medium transition', isActive(item.path) ? 'text-nexo-accent-cyan' : 'text-nexo-text-muted']">
      <component :is="item.icon" class="h-[18px] w-[18px]" />
      <span>{{ item.name === 'Trending signals' ? 'Trending' : item.name === 'Dashboard' ? 'Home' : item.name === 'Global feed' ? 'Feed' : item.name }}</span>
    </RouterLink>
    <button type="button" @click="mobileMoreOpen = !mobileMoreOpen" :class="['flex flex-col items-center gap-1 px-1 py-1.5 text-[10px] font-medium transition', mobileMoreOpen ? 'text-nexo-accent-cyan' : 'text-nexo-text-muted']" aria-label="More workspace links"><MoreHorizontal class="h-[18px] w-[18px]" /><span>More</span></button>
  </nav>
  <div v-if="mobileMoreOpen" class="fixed bottom-[4.75rem] right-3 z-50 w-60 rounded-2xl border border-nexo-border bg-white p-2 shadow-xl lg:hidden">
    <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-nexo-text-muted">Workspace</p>
    <RouterLink v-for="item in navItems.slice(4)" :key="item.path" :to="item.path" @click="mobileMoreOpen = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"><component :is="item.icon" class="h-4 w-4" />{{ item.name }}</RouterLink>
    <RouterLink v-if="authStore.user?.account_type === 'admin'" to="/admin/dashboard" @click="mobileMoreOpen = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-medium text-nexo-accent-gold transition hover:bg-amber-50"><ShieldCheck class="h-4 w-4" />Admin console</RouterLink>
  </div>
</template>
