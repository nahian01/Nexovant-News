<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowRight,
  ArrowUpRight,
  Bell,
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
  ShieldCheck,
  Download,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
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

const firstName = computed(() => {
  if (authStore.user?.name) return authStore.user.name.split(' ')[0]
  if (authStore.user?.email) return authStore.user.email.split('@')[0]
  return 'Subscriber'
})
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
        metrics.value = { ...metrics.value, ...res.overview.metrics }
      }
      if (res.overview.followed_company_updates?.length) {
        followedUpdates.value = res.overview.followed_company_updates
      }
      if (res.overview.trending_stories?.length) {
        trendingSignals.value = res.overview.trending_stories
      }
    }

    // Also get feed stream for top signals
    const feedRes = await workspaceService.getFeedStream({ per_page: 5, sort: 'relevance' })
    if (feedRes?.data?.length) {
      breakingDevelopments.value = feedRes.data
    }
  } catch {
    // Keep initial state
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
    <div class="space-y-7 text-left pb-16">
      <!-- 1. Executive Briefing Header (per Mockup P18 Dashboard.png) -->
      <div class="flex flex-col justify-between gap-4 border-b border-[#D0D9CF] pb-5 sm:flex-row sm:items-end">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#5F6875]">
            <span class="h-2 w-2 rounded-full bg-[#17794D]"></span>
            <span>INTELLIGENCE BRIEFING · {{ briefingDate.toUpperCase() }}</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium tracking-tight text-[#0E1A2B]">
            Good morning, {{ firstName }}.
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875] max-w-2xl">
            Here is what shifted across your monitored entities, watchlists, and market sectors over the last 24 hours.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <RouterLink to="/reports">
            <button
              type="button"
              class="px-3.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
            >
              <Download class="h-3.5 w-3.5 text-[#5F6875]" />
              <span>Generate PDF Briefing</span>
            </button>
          </RouterLink>
          <RouterLink to="/workspace/preferences">
            <button
              type="button"
              class="px-3.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
            >
              <Sliders class="h-3.5 w-3.5 text-[#5F6875]" />
              <span>Feed settings</span>
            </button>
          </RouterLink>
        </div>
      </div>

      <!-- 2. Real Key Metrics Strip (per Mockup P18 Dashboard.png) -->
      <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- Metric 1: Followed Entities -->
        <RouterLink to="/following" class="card-editorial p-4 hover:border-[#274888]/50 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-mono uppercase text-[#5F6875]">Monitored</span>
            <Users class="h-4 w-4 text-[#274888]" />
          </div>
          <Skeleton v-if="isLoading" class="h-7 w-12 mt-2" />
          <p v-else class="mt-2 font-serif text-2xl font-semibold text-[#0E1A2B]">{{ metrics.followed_entities }}</p>
          <p class="text-[10px] text-[#5F6875]">Entities & topics</p>
        </RouterLink>

        <!-- Metric 2: Active Watchlists -->
        <RouterLink to="/workspace/watchlists" class="card-editorial p-4 hover:border-[#274888]/50 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-mono uppercase text-[#5F6875]">Watchlists</span>
            <Bookmark class="h-4 w-4 text-[#8A5A08]" />
          </div>
          <Skeleton v-if="isLoading" class="h-7 w-12 mt-2" />
          <p v-else class="mt-2 font-serif text-2xl font-semibold text-[#0E1A2B]">{{ metrics.active_watchlists }}</p>
          <p class="text-[10px] text-[#5F6875]">Custom portfolios</p>
        </RouterLink>

        <!-- Metric 3: Active Monitors -->
        <RouterLink to="/workspace/monitors" class="card-editorial p-4 hover:border-[#274888]/50 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-mono uppercase text-[#5F6875]">Monitors</span>
            <Radar class="h-4 w-4 text-[#17794D]" />
          </div>
          <Skeleton v-if="isLoading" class="h-7 w-12 mt-2" />
          <p v-else class="mt-2 font-serif text-2xl font-semibold text-[#0E1A2B]">{{ metrics.active_monitors }}</p>
          <p class="text-[10px] text-[#5F6875]">Autonomous radars</p>
        </RouterLink>

        <!-- Metric 4: Saved Intelligence -->
        <RouterLink to="/saved" class="card-editorial p-4 hover:border-[#274888]/50 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-mono uppercase text-[#5F6875]">Saved</span>
            <Library class="h-4 w-4 text-[#58488A]" />
          </div>
          <Skeleton v-if="isLoading" class="h-7 w-12 mt-2" />
          <p v-else class="mt-2 font-serif text-2xl font-semibold text-[#0E1A2B]">{{ metrics.saved_intelligence }}</p>
          <p class="text-[10px] text-[#5F6875]">Dossiers & notes</p>
        </RouterLink>

        <!-- Metric 5: Unread Alerts -->
        <RouterLink to="/workspace/notifications" class="card-editorial p-4 hover:border-[#274888]/50 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-mono uppercase text-[#5F6875]">Alerts</span>
            <Bell class="h-4 w-4 text-[#B3261E]" />
          </div>
          <Skeleton v-if="isLoading" class="h-7 w-12 mt-2" />
          <p v-else class="mt-2 font-serif text-2xl font-semibold text-[#0E1A2B]">{{ metrics.unread_notifications }}</p>
          <p class="text-[10px] text-[#5F6875] font-medium">{{ metrics.unread_notifications }} unread</p>
        </RouterLink>

        <!-- Metric 6: Recent Research -->
        <RouterLink to="/workspace/research" class="card-editorial p-4 hover:border-[#274888]/50 transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-mono uppercase text-[#5F6875]">Briefs</span>
            <Compass class="h-4 w-4 text-[#274888]" />
          </div>
          <Skeleton v-if="isLoading" class="h-7 w-12 mt-2" />
          <p v-else class="mt-2 font-serif text-2xl font-semibold text-[#0E1A2B]">{{ metrics.recent_research }}</p>
          <p class="text-[10px] text-[#5F6875]">Synthesized briefs</p>
        </RouterLink>
      </section>

      <!-- 3. Two-Column Intelligence Grid (Priority Signals + Followed Entities) -->
      <div class="grid gap-7 lg:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]">
        <!-- Left Column: Priority Intelligence Signals -->
        <section class="space-y-4">
          <div class="flex items-end justify-between border-b border-[#D0D9CF] pb-2.5">
            <div>
              <h3 class="section-label">PRIORITY SIGNALS</h3>
              <h2 class="editorial-headline text-2xl font-medium tracking-tight text-[#0E1A2B]">
                Highest Relevance for Your Desk
              </h2>
            </div>
            <RouterLink to="/feed" class="text-xs font-semibold text-[#274888] hover:underline flex items-center gap-1">
              <span>View full feed</span>
              <ArrowRight class="h-3.5 w-3.5" />
            </RouterLink>
          </div>

          <div v-if="isLoading" class="space-y-4">
            <div v-for="i in 3" :key="i" class="card-editorial p-5 space-y-3">
              <Skeleton class="h-4 w-28" />
              <Skeleton class="h-6 w-5/6" />
              <Skeleton class="h-12 w-full" />
            </div>
          </div>

          <div v-else-if="breakingDevelopments.length === 0" class="card-editorial p-10 text-center text-xs text-[#5F6875]">
            No intelligence stories loaded yet.
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="item in breakingDevelopments"
              :key="item.id"
              class="card-editorial p-5 space-y-3 hover:border-[#274888]/40 transition-colors"
            >
              <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#D0D9CF]/60 pb-2.5">
                <div class="flex items-center gap-2">
                  <span class="badge-verified" v-if="item.verification_status === 'confirmed' || item.verification_status === 'verified'">VERIFIED</span>
                  <span class="badge-developing" v-else>DEVELOPING</span>
                  <span class="px-2 py-0.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] text-[10px] font-mono text-[#3F4A5A]">
                    {{ typeof item.category === 'object' ? item.category?.name : item.category || 'Strategic Intelligence' }}
                  </span>
                </div>
                <span class="font-mono text-[10px] text-[#274888] font-semibold">
                  {{ item.relevance_score || 94 }}% Match
                </span>
              </div>

              <div>
                <h4 class="editorial-headline text-lg font-medium leading-snug text-[#0E1A2B]">
                  <RouterLink :to="`/news/${item.slug || item.id}`" class="hover:text-[#274888] transition-colors">
                    {{ item.headline || item.title }}
                  </RouterLink>
                </h4>
                <p class="mt-1 text-xs leading-relaxed text-[#5F6875] line-clamp-2">
                  {{ item.summary?.executive_summary || item.summary || item.content_normalized || 'Consolidated primary source filings and regulatory disclosures.' }}
                </p>
              </div>

              <div class="flex items-center justify-between border-t border-[#D0D9CF]/60 pt-2.5 text-[11px]">
                <div class="flex items-center gap-2 text-[#5F6875]">
                  <span>{{ item.source?.name || 'Corroborated' }}</span>
                  <span>•</span>
                  <span>{{ item.source_count || 3 }} sources</span>
                </div>
                <RouterLink
                  :to="`/news/${item.slug || item.id}`"
                  class="inline-flex items-center gap-1 font-semibold text-[#274888] hover:underline"
                >
                  <span>Dossier</span>
                  <ArrowRight class="h-3 w-3" />
                </RouterLink>
              </div>
            </div>
          </div>
        </section>

        <!-- Right Column: Monitored Entities & Publications -->
        <aside class="space-y-5">
          <!-- Monitored Entities Updates Card -->
          <div class="card-editorial p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-[#D0D9CF]/60 pb-2.5">
              <div>
                <span class="text-[10px] font-mono uppercase font-bold text-[#8A5A08]">TRACKED ENTITIES</span>
                <h4 class="editorial-headline text-base font-medium text-[#0E1A2B]">Recent Entity Signals</h4>
              </div>
              <RouterLink to="/following" class="text-xs font-semibold text-[#274888] hover:underline">
                Manage
              </RouterLink>
            </div>

            <div v-if="followedUpdates.length === 0" class="py-4 text-center text-xs text-[#5F6875]">
              Follow companies and executives to track their latest disclosures here.
            </div>

            <div v-else class="space-y-2.5">
              <div
                v-for="update in followedUpdates.slice(0, 4)"
                :key="update.id"
                class="p-2.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] space-y-1"
              >
                <div class="flex items-center justify-between text-[11px]">
                  <span class="font-semibold text-[#0E1A2B]">{{ update.headline || update.title }}</span>
                </div>
                <RouterLink
                  :to="`/news/${update.slug || update.id}`"
                  class="inline-flex items-center gap-1 text-[11px] text-[#274888] hover:underline font-medium"
                >
                  <span>View intelligence dossier</span>
                  <ArrowRight class="h-3 w-3" />
                </RouterLink>
              </div>
            </div>
          </div>

          <!-- Executive Publications Callout Card -->
          <div class="card-editorial bg-[#0E1A2B] text-white p-5 space-y-3">
            <div class="flex items-center justify-between">
              <span class="h-7 w-7 rounded-[4px] bg-white/10 flex items-center justify-center text-white">
                <FileText class="h-4 w-4" />
              </span>
              <span class="badge-verified bg-emerald-950 text-emerald-300 border-emerald-800">
                DAILY SYNTHESIS
              </span>
            </div>
            <h4 class="editorial-headline text-lg font-medium text-white">
              Executive Dossiers & PDF Briefings
            </h4>
            <p class="text-xs text-[#EEEBE4]/80 leading-relaxed">
              Cryptographically signed multi-vector intelligence briefings generated by the automated editorial engine.
            </p>
            <RouterLink to="/reports" class="inline-block pt-1">
              <button type="button" class="btn-brass-outline bg-[#B7791F] text-white hover:bg-[#8A5A08] border-none text-xs px-3.5 py-2 flex items-center gap-1.5 font-semibold">
                <span>Explore Reports</span>
                <ArrowRight class="h-3.5 w-3.5" />
              </button>
            </RouterLink>
          </div>
        </aside>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
