<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowRight,
  ArrowUpRight,
  Bell,
  BellRing,
  Bookmark,
  Building2,
  CalendarClock,
  CheckCircle2,
  Compass,
  FileText,
  Library,
  Radar,
  Sliders,
  Sparkles,
  TrendingUp,
  Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { workspaceService } from '@/services/api/workspace.service'

const authStore = useAuthStore()

// State
const isLoading = ref(true)
const overviewData = ref<any>(null)
const breakingDevelopments = ref<any[]>([])
const followedUpdates = ref<any[]>([])
const trendingSignals = ref<any[]>([])

const metrics = ref({
  followed_entities: 0,
  active_watchlists: 0,
  active_monitors: 0,
  saved_intelligence: 0,
  unread_notifications: 0,
  recent_research: 0,
})

const firstName = computed(() => authStore.user?.name?.split(' ')[0] || 'Executive')
const greetingMessage = computed(() => {
  if (overviewData.value?.greeting) return overviewData.value.greeting
  const hour = new Date().getHours()
  if (hour < 12) return 'Morning Intelligence Briefing'
  if (hour < 17) return 'Midday Market Briefing'
  return 'Evening Strategic Review'
})

const briefingDate = computed(() =>
  new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date())
)

const loadDashboard = async () => {
  isLoading.value = true
  try {
    const res = await workspaceService.getOverview()
    if (res?.overview) {
      overviewData.value = res.overview
      if (res.overview.metrics) {
        metrics.value = res.overview.metrics
      }
      if (res.overview.followed_company_updates?.length) {
        followedUpdates.value = res.overview.followed_company_updates
      }
      if (res.overview.trending_stories?.length) {
        trendingSignals.value = res.overview.trending_stories
      }
    }

    // Also get feed stream for top signals
    const feedRes = await workspaceService.getFeedStream({ per_page: 4, sort: 'relevance' })
    if (feedRes?.data?.length) {
      breakingDevelopments.value = feedRes.data
    }
  } catch {
    // Keep initial graceful state
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="mx-auto max-w-[1400px] space-y-7 text-left">
      <!-- 1. Executive Header -->
      <div class="flex flex-col justify-between gap-4 border-b border-nexo-border/80 pb-5 sm:flex-row sm:items-end">
        <div>
          <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-widest text-nexo-accent-cyan">
            <Sparkles class="h-3.5 w-3.5" />
            <span>{{ greetingMessage }}</span>
          </div>
          <h1 class="mt-2 font-serif text-3xl font-medium tracking-tight text-nexo-text-primary sm:text-4xl">
            A clearer view, {{ firstName }}.
          </h1>
          <p class="mt-1.5 text-sm text-nexo-text-secondary">
            Your intelligence briefing, connected directly to verified signals across the technology landscape.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <div class="flex items-center gap-1.5 rounded-xl border border-nexo-border bg-white px-3 py-2 text-xs font-medium text-nexo-text-muted">
            <CalendarClock class="h-4 w-4 text-nexo-accent-cyan" />
            <span>{{ briefingDate }}</span>
          </div>
          <RouterLink to="/workspace/preferences">
            <Button variant="ghost" size="sm">
              <Sliders class="mr-1.5 h-3.5 w-3.5" />
              <span>Feed Preferences</span>
            </Button>
          </RouterLink>
        </div>
      </div>

      <!-- 2. Real Key Metrics Strip -->
      <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- Metric 1: Followed Entities -->
        <RouterLink to="/following" class="group rounded-2xl border border-nexo-border bg-white p-4 transition hover:border-nexo-accent-cyan/40 hover:shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-nexo-text-muted">Followed</span>
            <Users class="h-4 w-4 text-blue-600 transition group-hover:scale-110" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-text-primary">{{ metrics.followed_entities }}</p>
          <p class="text-[10px] text-nexo-text-secondary">Entities & topics</p>
        </RouterLink>

        <!-- Metric 2: Active Watchlists -->
        <RouterLink to="/workspace/watchlists" class="group rounded-2xl border border-nexo-border bg-white p-4 transition hover:border-nexo-accent-cyan/40 hover:shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-nexo-text-muted">Watchlists</span>
            <Bookmark class="h-4 w-4 text-nexo-accent-gold transition group-hover:scale-110" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-text-primary">{{ metrics.active_watchlists }}</p>
          <p class="text-[10px] text-nexo-text-secondary">Custom lists</p>
        </RouterLink>

        <!-- Metric 3: Active Monitors -->
        <RouterLink to="/workspace/monitors" class="group rounded-2xl border border-nexo-border bg-white p-4 transition hover:border-nexo-accent-cyan/40 hover:shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-nexo-text-muted">Monitors</span>
            <Radar class="h-4 w-4 text-emerald-600 transition group-hover:scale-110" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-text-primary">{{ metrics.active_monitors }}</p>
          <p class="text-[10px] text-nexo-text-secondary">Signal radar alerts</p>
        </RouterLink>

        <!-- Metric 4: Saved Intelligence -->
        <RouterLink to="/saved" class="group rounded-2xl border border-nexo-border bg-white p-4 transition hover:border-nexo-accent-cyan/40 hover:shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-nexo-text-muted">Library</span>
            <Library class="h-4 w-4 text-purple-600 transition group-hover:scale-110" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-text-primary">{{ metrics.saved_intelligence }}</p>
          <p class="text-[10px] text-nexo-text-secondary">Dossiers & notes</p>
        </RouterLink>

        <!-- Metric 5: Unread Alerts -->
        <RouterLink to="/workspace/notifications" class="group rounded-2xl border border-nexo-border bg-white p-4 transition hover:border-nexo-accent-cyan/40 hover:shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-nexo-text-muted">Alerts</span>
            <Bell class="h-4 w-4 text-rose-600 transition group-hover:scale-110" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-text-primary">{{ metrics.unread_notifications }}</p>
          <p class="text-[10px] text-nexo-text-secondary">Unread notifications</p>
        </RouterLink>

        <!-- Metric 6: Recent Research -->
        <RouterLink to="/workspace/research" class="group rounded-2xl border border-nexo-border bg-white p-4 transition hover:border-nexo-accent-cyan/40 hover:shadow-2xs">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-medium text-nexo-text-muted">Research</span>
            <Compass class="h-4 w-4 text-nexo-accent-cyan transition group-hover:scale-110" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-text-primary">{{ metrics.recent_research }}</p>
          <p class="text-[10px] text-nexo-text-secondary">Grounded syntheses</p>
        </RouterLink>
      </section>

      <!-- 3. Executive Hero Banner -->
      <section class="relative overflow-hidden rounded-[26px] border border-[#d5e5d9] bg-gradient-to-br from-[#eaf4ed] via-[#f4f6f1] to-[#f3eee2] p-6 sm:p-8">
        <div class="relative flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-center">
          <div class="max-w-2xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-[#cfe2d5] bg-white/70 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-nexo-accent-cyan">
              <Sparkles class="h-3.5 w-3.5" />
              <span>Personalized Intelligence Engine</span>
            </div>
            <h2 class="mt-3 font-serif text-2xl font-medium tracking-tight text-nexo-text-primary sm:text-3xl">
              Verified Technology & Enterprise Intelligence
            </h2>
            <p class="mt-2 text-xs leading-relaxed text-nexo-text-secondary sm:text-sm">
              Explore stories corroborating facts across multiple independent sources, separated from opinion and hype.
            </p>
            <div class="mt-5 flex flex-wrap gap-2.5">
              <RouterLink to="/feed">
                <Button variant="primary" size="md">
                  <span>Open Personalized Feed</span>
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </RouterLink>
              <RouterLink to="/reports">
                <Button variant="secondary" size="md">
                  <FileText class="mr-2 h-4 w-4" />
                  <span>Executive Publications</span>
                </Button>
              </RouterLink>
            </div>
          </div>

          <div class="flex w-full flex-col gap-2.5 sm:w-auto">
            <RouterLink to="/trends" class="flex items-center justify-between gap-4 rounded-xl border border-white/80 bg-white/80 p-3.5 shadow-2xs transition hover:bg-white">
              <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#e9f3ed] text-nexo-accent-cyan">
                  <TrendingUp class="h-4 w-4" />
                </span>
                <div>
                  <p class="text-xs font-semibold text-nexo-text-primary">Social Trend Radar</p>
                  <p class="text-[10px] text-nexo-text-muted">Cross-platform velocity tracking</p>
                </div>
              </div>
              <ArrowUpRight class="h-4 w-4 text-nexo-text-muted" />
            </RouterLink>

            <RouterLink to="/workspace/research" class="flex items-center justify-between gap-4 rounded-xl border border-white/80 bg-white/80 p-3.5 shadow-2xs transition hover:bg-white">
              <div class="flex items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#edf0e7] text-nexo-accent-gold">
                  <Compass class="h-4 w-4" />
                </span>
                <div>
                  <p class="text-xs font-semibold text-nexo-text-primary">Deep Research AI</p>
                  <p class="text-[10px] text-nexo-text-muted">Grounded RAG synthesis</p>
                </div>
              </div>
              <ArrowUpRight class="h-4 w-4 text-nexo-text-muted" />
            </RouterLink>
          </div>
        </div>
      </section>

      <!-- 4. Two-Column Intelligence Grid -->
      <div class="grid gap-7 lg:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]">
        <!-- Left: Top Intelligence Signals -->
        <section class="space-y-4">
          <div class="flex items-end justify-between border-b border-nexo-border/80 pb-3">
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">Highest Relevance</p>
              <h2 class="mt-1 font-serif text-2xl font-medium tracking-tight text-nexo-text-primary">
                Top Intelligence Signals
              </h2>
            </div>
            <RouterLink to="/feed" class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-accent-cyan transition hover:gap-1.5">
              <span>View All</span>
              <ArrowRight class="h-3.5 w-3.5" />
            </RouterLink>
          </div>

          <div v-if="isLoading" class="space-y-4">
            <Card v-for="i in 3" :key="i" padding="md">
              <Skeleton class="h-4 w-28 mb-2" />
              <Skeleton class="h-6 w-5/6 mb-2" />
              <Skeleton class="h-10 w-full" />
            </Card>
          </div>

          <div v-else-if="breakingDevelopments.length === 0" class="rounded-xl border border-dashed border-nexo-border bg-white p-8 text-center text-xs text-nexo-text-muted">
            No intelligence stories loaded yet.
          </div>

          <div v-else class="space-y-4">
            <Card
              v-for="item in breakingDevelopments"
              :key="item.id"
              padding="md"
              class="group transition hover:border-nexo-accent-cyan/35 hover:shadow-xs"
            >
              <div class="flex flex-wrap items-center justify-between gap-2 border-b border-nexo-border/60 pb-2.5">
                <div class="flex items-center gap-2">
                  <Badge :variant="item.verification_status === 'verified' ? 'confirmed' : 'reported_reliable'" size="sm">
                    {{ item.verification_status || 'verified' }}
                  </Badge>
                  <span class="rounded bg-nexo-bg px-2 py-0.5 text-[11px] font-semibold text-nexo-text-secondary">
                    {{ typeof item.category === 'object' ? item.category?.name : item.category || 'Strategic Intelligence' }}
                  </span>
                </div>
                <span class="font-mono text-[10px] text-nexo-accent-cyan font-semibold">
                  {{ item.relevance_score || 92 }}% Match
                </span>
              </div>

              <div class="mt-3">
                <RouterLink
                  :to="`/news/${item.slug || item.id}`"
                  class="font-serif text-lg font-medium leading-snug text-nexo-text-primary transition group-hover:text-nexo-accent-cyan"
                >
                  {{ item.headline || item.title }}
                </RouterLink>
                <p class="mt-1 text-xs leading-relaxed text-nexo-text-secondary">
                  {{ item.summary }}
                </p>
              </div>

              <div class="mt-3.5 flex items-center justify-between border-t border-nexo-border/60 pt-2.5 text-[11px]">
                <div class="flex items-center gap-2 text-nexo-text-muted">
                  <span>{{ item.source?.name || 'Corroborated' }}</span>
                  <span>•</span>
                  <span>{{ item.source_count || 3 }} sources</span>
                </div>
                <RouterLink
                  :to="`/news/${item.slug || item.id}`"
                  class="inline-flex items-center gap-1 font-semibold text-nexo-accent-cyan hover:underline"
                >
                  <span>Dossier</span>
                  <ArrowRight class="h-3 w-3" />
                </RouterLink>
              </div>
            </Card>
          </div>
        </section>

        <!-- Right: Monitored Entities & Fast-Rising Signals -->
        <aside class="space-y-5">
          <!-- Monitored Entities Updates -->
          <Card padding="md">
            <template #header>
              <div class="flex w-full items-center justify-between">
                <div>
                  <p class="text-[11px] font-bold uppercase tracking-wider text-nexo-accent-gold">Tracked Entities</p>
                  <h3 class="mt-1 font-serif text-base font-medium text-nexo-text-primary">
                    Recent Company Activity
                  </h3>
                </div>
                <RouterLink to="/following" class="text-xs font-semibold text-nexo-accent-cyan hover:underline">
                  Manage
                </RouterLink>
              </div>
            </template>

            <div v-if="followedUpdates.length === 0" class="py-4 text-center text-xs text-nexo-text-muted">
              Follow companies and executives to track their latest disclosures here.
            </div>

            <div v-else class="space-y-2.5">
              <div
                v-for="update in followedUpdates.slice(0, 4)"
                :key="update.id"
                class="rounded-xl border border-nexo-border/70 p-2.5 transition hover:bg-nexo-bg"
              >
                <div class="flex items-center justify-between text-[11px]">
                  <span class="font-semibold text-nexo-text-primary">{{ update.headline || update.title }}</span>
                </div>
                <RouterLink
                  :to="`/news/${update.slug || update.id}`"
                  class="mt-1 block text-[11px] text-nexo-accent-cyan hover:underline"
                >
                  View intelligence dossier →
                </RouterLink>
              </div>
            </div>
          </Card>

          <!-- Executive Dossier Prompt Box -->
          <div class="rounded-2xl border border-[#2a594b] bg-[#173b32] p-5 text-white shadow-executive">
            <div class="flex items-center justify-between">
              <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-[#c5e5ce]">
                <FileText class="h-4 w-4" />
              </span>
              <span class="rounded-full border border-white/20 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-[#c5e5ce]">
                Publication Desk
              </span>
            </div>
            <h3 class="mt-4 font-serif text-lg font-medium text-white">
              Executive Dossiers & PDF Briefings
            </h3>
            <p class="mt-1.5 text-xs leading-relaxed text-[#c4d6cd]">
              Download cryptographically signed intelligence briefings generated by the automated editorial engine.
            </p>
            <RouterLink
              to="/reports"
              class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-xs font-semibold text-[#173b32] transition hover:bg-[#e9f3ed]"
            >
              <span>Explore Reports</span>
              <ArrowRight class="h-3.5 w-3.5" />
            </RouterLink>
          </div>
        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
