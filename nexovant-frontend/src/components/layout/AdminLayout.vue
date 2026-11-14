<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  Activity,
  Bot,
  BrainCircuit,
  CheckCheck,
  CircleDollarSign,
  FileText,
  Globe2,
  Layers3,
  Megaphone,
  Menu,
  Newspaper,
  Radio,
  Settings2,
  ShieldCheck,
  Users,
  Wrench,
  X,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
const mobileNavOpen = ref(false)

const adminNavGroups = [
  { title: 'Operations', items: [{ name: 'Dashboard overview', path: '/admin/dashboard', icon: Activity }] },
  { title: 'Editorial & content', items: [
    { name: 'Articles & reviews', path: '/admin/articles', icon: Newspaper },
    { name: 'Story clusters', path: '/admin/clusters', icon: Layers3 },
    { name: 'Collection sources', path: '/admin/sources', icon: Radio },
  ] },
  { title: 'Cognitive systems', items: [
    { name: 'Automation pipeline', path: '/admin/automation', icon: Wrench },
    { name: 'AI router & gateway', path: '/admin/ai-router', icon: Bot },
    { name: 'RAG knowledge center', path: '/admin/rag-knowledge', icon: BrainCircuit },
    { name: 'Social intelligence', path: '/admin/social-intelligence', icon: Globe2 },
  ] },
  { title: 'Business & users', items: [
    { name: 'Users & organizations', path: '/admin/users', icon: Users },
    { name: 'Payment review queue', path: '/admin/payments', icon: CircleDollarSign },
    { name: 'Advertising & campaigns', path: '/admin/advertising', icon: Megaphone },
    { name: 'Reports & publications', path: '/admin/reports', icon: FileText },
  ] },
  { title: 'Platform governance', items: [
    { name: 'System configuration', path: '/admin/system', icon: Settings2 },
    { name: 'Security & audit logs', path: '/admin/audit-logs', icon: ShieldCheck },
  ] },
]

const isActive = (path: string) => route.path === path || route.path.startsWith(`${path}/`)
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-left lg:flex">
    <aside class="sticky top-0 hidden h-screen w-[260px] shrink-0 flex-col border-r border-nexo-border/80 bg-white lg:flex">
      <div class="flex h-[68px] items-center justify-between border-b border-nexo-border px-5">
        <RouterLink to="/" class="flex items-center gap-3">
          <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#fff4dc] text-sm font-semibold text-nexo-accent-gold">N</span>
          <span class="flex flex-col leading-none"><span class="font-serif text-base font-semibold tracking-tight text-nexo-text-primary">Nexovant</span><span class="mt-1 text-[9px] font-semibold uppercase tracking-[0.15em] text-nexo-accent-gold">Control room</span></span>
        </RouterLink>
        <span class="rounded-full bg-amber-50 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-nexo-accent-gold">Admin</span>
      </div>

      <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-5">
        <div v-for="group in adminNavGroups" :key="group.title" class="space-y-1">
          <p class="px-3 pb-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">{{ group.title }}</p>
          <RouterLink
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            :class="['flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[12px] font-medium transition', isActive(item.path) ? 'bg-amber-50 text-nexo-accent-gold' : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary']"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ item.name }}</span>
          </RouterLink>
        </div>
      </nav>

      <div class="border-t border-nexo-border bg-nexo-bg/70 p-4">
        <div class="flex items-center gap-2.5">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#fff4dc] text-xs font-semibold text-nexo-accent-gold">{{ authStore.user?.name?.charAt(0).toUpperCase() || 'A' }}</span>
          <div class="min-w-0 flex-1"><div class="truncate text-xs font-semibold text-nexo-text-primary">{{ authStore.user?.name || 'Administrator' }}</div><div class="truncate text-[10px] text-nexo-text-muted">{{ authStore.user?.email }}</div></div>
        </div>
        <RouterLink to="/" class="mt-3 flex items-center gap-2 px-1 text-[11px] font-medium text-nexo-accent-cyan transition hover:text-nexo-text-primary"><Globe2 class="h-3.5 w-3.5" /> Return to public network</RouterLink>
      </div>
    </aside>

    <div class="min-w-0 flex-1">
      <header class="sticky top-0 z-30 flex h-[68px] items-center justify-between border-b border-nexo-border/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
          <button type="button" class="rounded-lg border border-nexo-border p-2 text-nexo-text-secondary lg:hidden" @click="mobileNavOpen = true" aria-label="Open admin navigation"><Menu class="h-4 w-4" /></button>
          <div class="flex items-center gap-2 text-xs text-nexo-text-muted"><RouterLink to="/admin/dashboard" class="font-medium transition hover:text-nexo-text-primary">Admin</RouterLink><span>/</span><span class="font-semibold capitalize text-nexo-text-primary">{{ route.path.split('/')[2] || 'Overview' }}</span></div>
        </div>
        <div class="flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-medium text-emerald-800"><CheckCheck class="h-3.5 w-3.5" /><span class="hidden sm:inline">All systems operational</span><span class="sm:hidden">Operational</span></div>
      </header>

      <main class="mx-auto w-full max-w-[1480px] space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
        <slot />
      </main>
    </div>

    <div v-if="mobileNavOpen" class="fixed inset-0 z-50 flex lg:hidden" @click.self="mobileNavOpen = false">
      <div class="absolute inset-0 bg-[#10291f]/40 backdrop-blur-sm" @click="mobileNavOpen = false"></div>
      <aside class="relative flex h-full w-[min(86vw,320px)] flex-col bg-white shadow-2xl">
        <div class="flex h-[68px] items-center justify-between border-b border-nexo-border px-5"><span class="font-serif text-lg font-semibold text-nexo-text-primary">Admin navigation</span><button type="button" class="rounded-lg p-2 text-nexo-text-secondary hover:bg-nexo-bg" aria-label="Close navigation" @click="mobileNavOpen = false"><X class="h-4 w-4" /></button></div>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-5">
          <div v-for="group in adminNavGroups" :key="group.title" class="space-y-1">
            <p class="px-3 pb-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">{{ group.title }}</p>
            <RouterLink v-for="item in group.items" :key="item.path" :to="item.path" @click="mobileNavOpen = false" :class="['flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-medium transition', isActive(item.path) ? 'bg-amber-50 text-nexo-accent-gold' : 'text-nexo-text-secondary hover:bg-nexo-bg']"><component :is="item.icon" class="h-4 w-4" />{{ item.name }}</RouterLink>
          </div>
        </nav>
      </aside>
    </div>
  </div>
</template>
