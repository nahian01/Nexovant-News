<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowRight,
  ArrowUpRight,
  BellRing,
  Building2,
  CalendarClock,
  CircleCheck,
  FileText,
  Sparkles,
  Telescope,
  TrendingUp,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'

const authStore = useAuthStore()

const breakingDevelopments = ref([
  {
    id: 'int-001',
    title: 'NVIDIA Board Approves $15B Custom Silicon Foundry Expansion in EMEA',
    category: 'Expansion & Capital Expenditure',
    status: 'confirmed' as const,
    timestamp: '14 mins ago',
    entities: ['NVIDIA Corporation', 'Jensen Huang', 'European Commission'],
    significance: 'Secures high-density EU AI infrastructure independence; potential margin compression in FY27.',
    affected: ['TSMC', 'Intel Foundry Services', 'ASML'],
    consequences: 'Accelerates regulatory clearance under the European Chips Act.',
    nextTrigger: 'Q3 Financial Disclosures & Groundbreaking Conference (Nov 28)',
  },
  {
    id: 'int-002',
    title: 'Novartis Discloses Executive Transition: Global Chief Technology Officer Resignation',
    category: 'Leadership Change',
    status: 'reported_reliable' as const,
    timestamp: '42 mins ago',
    entities: ['Novartis AG', 'Vas Narasimhan', 'Dr. Fiona Gallagher'],
    significance: 'Signals structural restructuring in algorithmic drug discovery division.',
    affected: ['BioNTech Partnership Desk', 'Enterprise AI Vendors'],
    consequences: 'Temporary deceleration of pipeline Phase-II AI-assisted molecule screening.',
    nextTrigger: 'Board nomination announcement expected within 14 trading days.',
  },
  {
    id: 'int-003',
    title: 'Cross-Border Supply Chain Disruption Affecting Southeast Asian Semiconductor Packaging Hubs',
    category: 'Emerging Business Risk',
    status: 'developing' as const,
    timestamp: '1 hour ago',
    entities: ['ASE Group', 'Amkor Technology', 'Foxconn'],
    significance: 'Potential 6-week lead time elongation for automotive microcontrollers.',
    affected: ['Global Automotive OEMs', 'Tier-1 Electronics Distributors'],
    consequences: 'Secondary price surge in spot component exchanges.',
    nextTrigger: 'Regional Port Authority customs clearance metric update (Tomorrow 08:00 UTC).',
  },
])

const watchlist = ref([
  { name: 'Microsoft Corp', ticker: 'MSFT', movement: '+1.4%', signal: 'Bullish Institutional Flow' },
  { name: 'Novo Nordisk', ticker: 'NVO', movement: '-0.8%', signal: 'Clinical Trial Milestones Pending' },
  { name: 'Alphabet Inc', ticker: 'GOOGL', movement: '+2.1%', signal: 'Antitrust Appeal Scheduled' },
  { name: 'Tesla Inc', ticker: 'TSLA', movement: '-1.5%', signal: 'Autonomous Regulatory Review' },
])

const firstName = computed(() => authStore.user?.name?.split(' ')[0] || 'there')
const briefingDate = computed(() => new Intl.DateTimeFormat('en', { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date()))
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-left lg:flex">
    <Sidebar />

    <div class="min-w-0 flex-1">
      <AppHeader />

      <main class="mx-auto w-full max-w-[1500px] space-y-7 px-4 py-6 sm:px-6 lg:px-9 lg:py-9">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
          <div>
            <p class="eyebrow">Your executive workspace</p>
            <h1 class="mt-2 font-serif text-3xl font-medium tracking-tight text-nexo-text-primary sm:text-[40px]">A clearer view, {{ firstName }}.</h1>
            <p class="mt-2 text-sm text-nexo-text-secondary">Your business briefing, connected to the signals that matter.</p>
          </div>
          <div class="flex items-center gap-2 text-xs font-medium text-nexo-text-muted"><CalendarClock class="h-4 w-4" /> {{ briefingDate }}</div>
        </div>

        <section class="relative overflow-hidden rounded-[26px] border border-[#d5e5d9] bg-gradient-to-br from-[#eaf4ed] via-[#f4f6f1] to-[#f3eee2] p-6 sm:p-8 lg:p-9">
          <div class="pointer-events-none absolute -right-12 -top-24 h-80 w-80 rounded-full border border-[#cddfd2]"></div>
          <div class="pointer-events-none absolute -right-1 -top-12 h-56 w-56 rounded-full border border-[#cddfd2]"></div>
          <div class="relative flex flex-col items-start justify-between gap-7 lg:flex-row lg:items-center">
            <div class="max-w-[600px]">
              <div class="inline-flex items-center gap-2 rounded-full border border-[#cfe2d5] bg-white/70 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-nexo-accent-cyan"><Sparkles class="h-3.5 w-3.5" /> Your daily intelligence</div>
              <h2 class="mt-4 font-serif text-[32px] font-medium leading-[1.08] tracking-tight text-nexo-text-primary sm:text-[42px]">Your world, in focus.</h2>
              <p class="mt-3 max-w-[500px] text-sm leading-6 text-nexo-text-secondary">Follow a development from the first verified fact to its business impact, affected players and what to watch next.</p>
              <div class="mt-6 flex flex-wrap gap-3"><RouterLink to="/feed"><Button variant="primary">Open global feed <ArrowRight class="ml-2 h-4 w-4" /></Button></RouterLink><RouterLink to="/reports"><Button variant="secondary"><FileText class="mr-2 h-4 w-4" /> View briefings</Button></RouterLink></div>
            </div>
            <div class="relative grid w-full max-w-[420px] grid-cols-2 gap-3 lg:w-[390px]">
              <div class="rounded-2xl border border-white/80 bg-white/80 p-4 shadow-sm"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#eaf3ed] text-nexo-accent-cyan"><Telescope class="h-[18px] w-[18px]" /></span><div class="mt-5 font-serif text-3xl font-medium text-nexo-text-primary">10</div><div class="mt-1 text-[11px] font-medium text-nexo-text-secondary">dimensions of context</div></div>
              <div class="rounded-2xl border border-white/80 bg-white/80 p-4 shadow-sm"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#edf0e7] text-nexo-accent-gold"><Building2 class="h-[18px] w-[18px]" /></span><div class="mt-5 font-serif text-3xl font-medium text-nexo-text-primary">{{ watchlist.length }}</div><div class="mt-1 text-[11px] font-medium text-nexo-text-secondary">companies in focus</div></div>
              <div class="col-span-2 flex items-center justify-between rounded-2xl border border-white/80 bg-white/80 p-4 shadow-sm"><div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#e9f3ed] text-nexo-accent-cyan"><CircleCheck class="h-[18px] w-[18px]" /></span><span><span class="block text-xs font-semibold text-nexo-text-primary">Source-aware intelligence</span><span class="mt-1 block text-[10px] text-nexo-text-muted">Signals are labeled by verification status</span></span></div><ArrowUpRight class="h-4 w-4 text-nexo-text-muted" /></div>
            </div>
          </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-3">
          <div class="flex items-center justify-between rounded-2xl border border-nexo-border bg-white px-5 py-4 shadow-sm"><div><p class="text-xs font-medium text-nexo-text-muted">Developments in this briefing</p><p class="mt-1 font-serif text-2xl font-medium text-nexo-text-primary">{{ breakingDevelopments.length }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#edf4ef] text-nexo-accent-cyan"><BellRing class="h-5 w-5" /></span></div>
          <div class="flex items-center justify-between rounded-2xl border border-nexo-border bg-white px-5 py-4 shadow-sm"><div><p class="text-xs font-medium text-nexo-text-muted">Companies on your watchlist</p><p class="mt-1 font-serif text-2xl font-medium text-nexo-text-primary">{{ watchlist.length }}</p></div><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#edf0e7] text-nexo-accent-gold"><Building2 class="h-5 w-5" /></span></div>
          <div class="flex items-center justify-between rounded-2xl border border-nexo-border bg-white px-5 py-4 shadow-sm"><div><p class="text-xs font-medium text-nexo-text-muted">Your next executive briefing</p><p class="mt-1 text-sm font-semibold text-nexo-text-primary">Daily global outlook</p></div><RouterLink to="/reports" class="flex h-10 w-10 items-center justify-center rounded-xl bg-nexo-bg text-nexo-accent-cyan transition hover:bg-[#e9f3ed]" aria-label="View briefings"><ArrowUpRight class="h-5 w-5" /></RouterLink></div>
        </section>

        <div class="grid gap-7 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,0.85fr)]">
          <section class="space-y-4">
            <div class="flex items-end justify-between gap-3">
              <div><p class="eyebrow">The latest signals</p><h2 class="mt-1.5 font-serif text-2xl font-medium tracking-tight text-nexo-text-primary">Developments to know</h2></div>
              <RouterLink to="/feed" class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-accent-cyan transition hover:gap-2">View all <ArrowRight class="h-3.5 w-3.5" /></RouterLink>
            </div>

            <Card v-for="item in breakingDevelopments" :key="item.id" padding="md" class="group transition hover:border-nexo-accent-cyan/30 hover:shadow-lg">
              <div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2.5"><Badge :variant="item.status" size="sm" /><span class="text-xs font-medium text-nexo-text-secondary">{{ item.category }}</span></div><span class="text-[11px] text-nexo-text-muted">{{ item.timestamp }}</span></div>
              <h3 class="mt-4 font-serif text-[21px] font-medium leading-snug tracking-tight text-nexo-text-primary transition group-hover:text-nexo-accent-cyan">{{ item.title }}</h3>
              <div class="mt-3 flex flex-wrap gap-1.5"><span v-for="entity in item.entities" :key="entity" class="rounded-lg border border-nexo-border bg-nexo-bg px-2.5 py-1 text-[10px] font-medium text-nexo-text-secondary">{{ entity }}</span></div>
              <div class="mt-4 grid gap-3 border-t border-nexo-border pt-4 sm:grid-cols-2">
                <div class="rounded-xl bg-[#f2f6f2] p-3.5"><p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-nexo-accent-gold">Why it matters</p><p class="mt-1.5 text-xs leading-5 text-nexo-text-secondary">{{ item.significance }}</p></div>
                <div class="rounded-xl bg-[#f2f6f2] p-3.5"><p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-nexo-accent-cyan">What to watch</p><p class="mt-1.5 text-xs leading-5 text-nexo-text-secondary">{{ item.nextTrigger }}</p></div>
              </div>
            </Card>
          </section>

          <aside class="space-y-5">
            <Card padding="md">
              <template #header><div class="flex w-full items-center justify-between"><div><p class="eyebrow">Your watchlist</p><h3 class="mt-1 font-serif text-lg font-medium text-nexo-text-primary">Companies in focus</h3></div><RouterLink to="/companies" class="rounded-lg p-2 text-nexo-accent-cyan transition hover:bg-[#edf4ef]" aria-label="Manage companies"><ArrowUpRight class="h-4 w-4" /></RouterLink></div></template>
              <div class="space-y-1">
                <div v-for="co in watchlist" :key="co.ticker" class="flex items-center justify-between gap-3 rounded-xl px-2.5 py-3 transition hover:bg-nexo-bg">
                  <div class="flex min-w-0 items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#eef2ed] text-[10px] font-bold text-nexo-text-secondary">{{ co.ticker.slice(0, 2) }}</span><span class="min-w-0"><span class="block truncate text-xs font-semibold text-nexo-text-primary">{{ co.name }}</span><span class="mt-0.5 block truncate text-[10px] text-nexo-text-muted">{{ co.signal }}</span></span></div>
                  <span :class="['shrink-0 rounded-lg px-2 py-1 text-[10px] font-semibold', co.movement.startsWith('+') ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800']">{{ co.movement }}</span>
                </div>
              </div>
              <template #footer><RouterLink to="/companies" class="inline-flex items-center gap-1.5 text-xs font-semibold text-nexo-accent-cyan">Manage your watchlist <ArrowRight class="h-3.5 w-3.5" /></RouterLink></template>
            </Card>

            <div class="overflow-hidden rounded-2xl border border-[#2a594b] bg-[#173b32] p-5 text-white shadow-executive">
              <div class="flex items-center justify-between"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-[#c5e5ce]"><FileText class="h-[18px] w-[18px]" /></span><span class="rounded-full border border-white/15 px-2.5 py-1 text-[9px] font-semibold uppercase tracking-[0.12em] text-[#c5e5ce]">Your daily read</span></div>
              <h3 class="mt-5 font-serif text-xl font-medium text-white">A considered start to the day.</h3>
              <p class="mt-2 text-xs leading-5 text-[#c4d6cd]">A concise view of developments across the regions and industries you follow, with context attached.</p>
              <RouterLink to="/reports" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-3.5 py-2.5 text-xs font-semibold text-[#173b32] transition hover:bg-[#e9f3ed]">Explore briefings <ArrowRight class="h-3.5 w-3.5" /></RouterLink>
            </div>

            <div class="rounded-2xl border border-nexo-border bg-white p-5">
              <div class="flex items-center gap-2 text-nexo-accent-cyan"><TrendingUp class="h-4 w-4" /><span class="text-[10px] font-semibold uppercase tracking-[0.14em]">Market pulse</span></div>
              <p class="mt-2 font-serif text-lg font-medium text-nexo-text-primary">Signals are moving across sectors.</p>
              <p class="mt-1.5 text-xs leading-5 text-nexo-text-secondary">See which topics are accelerating and how the conversation is changing.</p>
              <RouterLink to="/trends" class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-nexo-accent-cyan">Open trend radar <ArrowRight class="h-3.5 w-3.5" /></RouterLink>
            </div>
          </aside>
        </div>
      </main>
    </div>
  </div>
</template>
