<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { searchService } from '@/services/api/search.service'
import {
  Search,
  Activity,
  AlertCircle,
  Clock,
  ThumbsUp,
  Sparkles,
  TrendingUp,
  HelpCircle,
  Layers,
} from 'lucide-vue-next'

const isLoading = ref(true)
const analyticsData = ref<any>(null)
const activeTab = ref<'top' | 'unanswered' | 'feedback'>('top')
const unansweredList = ref<any[]>([])
const feedbackList = ref<any[]>([])

const loadAnalytics = async () => {
  isLoading.value = true
  try {
    analyticsData.value = await searchService.getAdminSearchAnalytics()
    const unansweredRes = await searchService.getAdminUnansweredQueries()
    unansweredList.value = unansweredRes?.data || []
    const feedbackRes = await searchService.getAdminSearchFeedback()
    feedbackList.value = feedbackRes?.data || []
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadAnalytics()
})
</script>

<template>
  <div class="flex h-screen bg-black text-neutral-100 overflow-hidden font-sans">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <AppHeader />

      <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-serif text-white tracking-tight flex items-center gap-2.5">
              <span>Search Intelligence & Query Telemetry</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-sans font-medium bg-primary-500/10 text-primary-400 border border-primary-500/20">
                Admin Audit
              </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-1">
              Privacy-safe search telemetry, retrieval quality metrics, unanswered queries, and subscriber relevance signals.
            </p>
          </div>
        </div>

        <div v-if="isLoading" class="space-y-4">
          <Skeleton class="h-28 w-full rounded-xl" />
          <Skeleton class="h-64 w-full rounded-xl" />
        </div>

        <div v-else-if="analyticsData" class="space-y-6">
          <!-- KPI Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-1">
              <div class="flex items-center justify-between text-xs text-neutral-400">
                <span>Total Queries</span>
                <Search class="w-4 h-4 text-primary-400" />
              </div>
              <div class="text-2xl font-serif font-bold text-white">
                {{ analyticsData.metrics?.total_searches || 0 }}
              </div>
              <div class="text-[11px] text-neutral-500">Recorded audit events</div>
            </Card>

            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-1">
              <div class="flex items-center justify-between text-xs text-neutral-400">
                <span>Empty Search Rate</span>
                <AlertCircle class="w-4 h-4 text-amber-400" />
              </div>
              <div class="text-2xl font-serif font-bold text-amber-400">
                {{ analyticsData.metrics?.empty_rate_pct || 0 }}%
              </div>
              <div class="text-[11px] text-neutral-500">{{ analyticsData.metrics?.empty_searches || 0 }} unanswered queries</div>
            </Card>

            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-1">
              <div class="flex items-center justify-between text-xs text-neutral-400">
                <span>Avg Search Latency</span>
                <Clock class="w-4 h-4 text-emerald-400" />
              </div>
              <div class="text-2xl font-serif font-bold text-emerald-400">
                {{ analyticsData.metrics?.avg_latency_ms || 0 }}ms
              </div>
              <div class="text-[11px] text-neutral-500">Hybrid ranking duration</div>
            </Card>

            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-1">
              <div class="flex items-center justify-between text-xs text-neutral-400">
                <span>Relevance Feedback</span>
                <ThumbsUp class="w-4 h-4 text-blue-400" />
              </div>
              <div class="text-2xl font-serif font-bold text-blue-400">
                {{ analyticsData.metrics?.total_feedback_count || 0 }}
              </div>
              <div class="text-[11px] text-neutral-500">Subscriber ratings recorded</div>
            </Card>
          </div>

          <!-- Secondary Telemetry: Engines & Feedback Breakdown -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Card class="p-5 border-neutral-800 bg-neutral-900/60 space-y-3">
              <h3 class="text-xs font-mono uppercase tracking-wider text-neutral-400 flex items-center gap-1.5">
                <Sparkles class="w-3.5 h-3.5 text-primary-400" />
                <span>Retrieval Method Distribution</span>
              </h3>
              <div class="space-y-2 text-xs">
                <div
                  v-for="(count, method) in analyticsData.retrieval_methods"
                  :key="method"
                  class="flex items-center justify-between p-2.5 rounded-lg bg-neutral-950/60 border border-neutral-800"
                >
                  <span class="font-mono text-neutral-300 capitalize">{{ String(method).replace('_', ' ') }}</span>
                  <span class="font-mono font-bold text-primary-400">{{ count }}</span>
                </div>
              </div>
            </Card>

            <Card class="p-5 border-neutral-800 bg-neutral-900/60 space-y-3">
              <h3 class="text-xs font-mono uppercase tracking-wider text-neutral-400 flex items-center gap-1.5">
                <ThumbsUp class="w-3.5 h-3.5 text-blue-400" />
                <span>Relevance Rating Distribution</span>
              </h3>
              <div class="space-y-2 text-xs">
                <div
                  v-for="(count, rating) in analyticsData.feedback_distribution"
                  :key="rating"
                  class="flex items-center justify-between p-2.5 rounded-lg bg-neutral-950/60 border border-neutral-800"
                >
                  <span class="font-mono text-neutral-300 capitalize">{{ String(rating).replace('_', ' ') }}</span>
                  <span class="font-mono font-bold text-emerald-400">{{ count }}</span>
                </div>
              </div>
            </Card>
          </div>

          <!-- Tabs for Detailed Tables -->
          <div class="space-y-3">
            <div class="flex border-b border-neutral-800 gap-6">
              <button
                type="button"
                class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
                :class="activeTab === 'top' ? 'border-primary-500 text-primary-400' : 'border-transparent text-neutral-400 hover:text-white'"
                @click="activeTab = 'top'"
              >
                Top Searched Queries
              </button>
              <button
                type="button"
                class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
                :class="activeTab === 'unanswered' ? 'border-primary-500 text-primary-400' : 'border-transparent text-neutral-400 hover:text-white'"
                @click="activeTab = 'unanswered'"
              >
                Unanswered Queries (Knowledge Gaps)
              </button>
              <button
                type="button"
                class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
                :class="activeTab === 'feedback' ? 'border-primary-500 text-primary-400' : 'border-transparent text-neutral-400 hover:text-white'"
                @click="activeTab = 'feedback'"
              >
                Subscriber Relevance Feedback
              </button>
            </div>

            <!-- Tab: Top Queries -->
            <div v-if="activeTab === 'top'" class="space-y-2">
              <div
                v-for="(q, idx) in analyticsData.top_queries"
                :key="idx"
                class="p-3 rounded-lg bg-neutral-900 border border-neutral-800 flex items-center justify-between text-xs"
              >
                <div class="flex items-center gap-3">
                  <span class="font-mono text-neutral-500 w-5">#{{ idx + 1 }}</span>
                  <span class="text-white font-medium">"{{ q.query }}"</span>
                </div>
                <div class="flex items-center gap-4 text-neutral-400">
                  <span>Frequency: <strong class="text-primary-400">{{ q.count }}</strong></span>
                  <span>Avg Results: {{ Math.round(q.avg_results || 0) }}</span>
                </div>
              </div>
            </div>

            <!-- Tab: Unanswered Queries -->
            <div v-else-if="activeTab === 'unanswered'" class="space-y-2">
              <div v-if="unansweredList.length === 0" class="text-center py-8 text-neutral-500 text-xs">
                Zero unanswered queries recorded. Knowledge coverage is optimal.
              </div>
              <div
                v-for="(item, idx) in unansweredList"
                :key="idx"
                class="p-3 rounded-lg bg-neutral-900 border border-neutral-800 flex items-center justify-between text-xs"
              >
                <div class="space-y-0.5">
                  <div class="text-amber-300 font-medium font-mono">"{{ item.query }}"</div>
                  <div class="text-[11px] text-neutral-500">Category: {{ item.search_category }} · {{ new Date(item.created_at).toLocaleString() }}</div>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] uppercase font-mono bg-amber-500/10 text-amber-400 border border-amber-500/20">
                  0 Results Returned
                </span>
              </div>
            </div>

            <!-- Tab: Feedback -->
            <div v-else-if="activeTab === 'feedback'" class="space-y-2">
              <div v-if="feedbackList.length === 0" class="text-center py-8 text-neutral-500 text-xs">
                No user relevance ratings recorded yet.
              </div>
              <div
                v-for="(item, idx) in feedbackList"
                :key="idx"
                class="p-3 rounded-lg bg-neutral-900 border border-neutral-800 space-y-1 text-xs"
              >
                <div class="flex items-center justify-between">
                  <div class="font-medium text-white">
                    Query: "<span class="text-primary-300">{{ item.query }}</span>"
                  </div>
                  <span
                    class="px-2 py-0.5 rounded text-[10px] uppercase font-mono"
                    :class="item.feedback_rating === 'relevant' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'"
                  >
                    {{ item.feedback_rating.replace('_', ' ') }}
                  </span>
                </div>
                <p v-if="item.comments" class="text-neutral-400 text-[11px] italic">
                  "{{ item.comments }}"
                </p>
                <div class="text-[10px] text-neutral-500">
                  User: {{ item.user?.email || 'Anonymous' }} · {{ new Date(item.created_at).toLocaleString() }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>
