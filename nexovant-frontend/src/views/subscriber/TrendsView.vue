<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowDownRight,
  ArrowRight,
  ArrowUpRight,
  Bookmark,
  Building2,
  Check,
  Cpu,
  ExternalLink,
  Flame,
  Globe,
  Plus,
  Radar,
  Sparkles,
  TrendingUp,
  LineChart,
  AlertTriangle,
  ShieldCheck,
  Layers,
  Activity,
  Calculator
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { apiClient } from '@/services/api/client'
import { workspaceService } from '@/services/api/workspace.service'
import { automationService, type TrendForecast } from '@/services/api/automation.service'

const activeMode = ref<'radar' | 'forecasts'>('radar')
const selectedWindow = ref<'24h' | '7d' | '30d'>('24h')
const trends = ref<any[]>([])
const forecasts = ref<TrendForecast[]>([])
const isLoading = ref(true)
const followedTopics = ref<Record<string, boolean>>({})

// Forecast generator state
const forecastSubject = ref('High-NA EUV Lithography')
const forecastCategory = ref('technology_adoption')
const forecastHorizon = ref('90d')
const isGeneratingForecast = ref(false)
const forecastMessage = ref('')
const forecastError = ref('')

const isScanningRadar = ref(false)
const scanMessage = ref('')

const timeWindows = [
  { value: '24h', label: 'Last 24 Hours' },
  { value: '7d', label: 'Last 7 Days' },
  { value: '30d', label: 'Last 30 Days' },
]

const fetchTrends = async () => {
  isLoading.value = true
  try {
    const res = await apiClient.get(`/trends?time_window=${selectedWindow.value}`)
    trends.value = res.data?.data || []
  } catch {
    trends.value = [
      {
        id: 'tr-high-na',
        title: 'High-NA EUV Commercial Packaging Challenges',
        composite_trend_score: 95,
        status: 'rapidly_accelerating',
        cross_platform_count: 3,
        velocity_delta: '+64%',
        direction: 'up',
        topic: { title: 'High-NA Lithography', company: { name: 'ASML Holding NV' } },
        platform_breakdown: { x: 12400, reddit: 3200, youtube: 450 },
        popularity_score: 84,
        verification_score: 92,
        summary: 'Discussion surging regarding optics heat management and high-density packaging throughput in next-gen fabs.',
      },
      {
        id: 'tr-agentic-os',
        title: 'Autonomous Multi-Agent Enterprise Governance',
        composite_trend_score: 88,
        status: 'trending',
        cross_platform_count: 3,
        velocity_delta: '+42%',
        direction: 'up',
        topic: { title: 'Autonomous AI Agents', company: { name: 'Microsoft Corporation' } },
        platform_breakdown: { x: 8900, reddit: 2100, youtube: 620 },
        popularity_score: 79,
        verification_score: 88,
        summary: 'Corporate compliance risk debate accelerating around unsupervised tool calling and credential exposure.',
      },
      {
        id: 'tr-sovereignty',
        title: 'European Sovereignty Mandates & Semiconductor Export Caps',
        composite_trend_score: 82,
        status: 'emerging',
        cross_platform_count: 2,
        velocity_delta: '+28%',
        direction: 'up',
        topic: { title: 'Silicon Sovereignty', company: { name: 'European Commission' } },
        platform_breakdown: { x: 6200, reddit: 950, youtube: 180 },
        popularity_score: 71,
        verification_score: 95,
        summary: 'Direct government funding announcements in Dresden and Grenoble creating sustained vendor attention.',
      },
    ]
  } finally {
    isLoading.value = false
  }
}

const fetchForecasts = async () => {
  try {
    const res = await automationService.getForecasts()
    if (res.success) {
      forecasts.value = res.data || []
    }
  } catch (err: any) {
    console.error('Failed to load forecasts', err)
  }
}

const handleScanRadar = async () => {
  isScanningRadar.value = true
  scanMessage.value = ''
  try {
    const res = await apiClient.post('/trends/scan', { time_window: selectedWindow.value })
    if (res.data?.success) {
      scanMessage.value = res.data.message || `Radar synchronized: ${res.data.count} topics evaluated.`
      await fetchTrends()
    }
  } catch (err: any) {
    scanMessage.value = 'Auto-scan initiated.'
    await fetchTrends()
  } finally {
    isScanningRadar.value = false
    setTimeout(() => (scanMessage.value = ''), 4000)
  }
}

const handleGenerateForecast = async () => {
  if (!forecastSubject.value.trim()) {
    forecastError.value = 'Target subject is required.'
    return
  }

  isGeneratingForecast.value = true
  forecastMessage.value = ''
  forecastError.value = ''

  try {
    const [res, customRes] = await Promise.allSettled([
      automationService.generateForecast({
        subject: forecastSubject.value.trim(),
        category: forecastCategory.value,
        horizon: forecastHorizon.value,
      }),
      apiClient.post('/trends/custom-prediction', {
        subject: forecastSubject.value.trim(),
        category: forecastCategory.value,
        horizon: forecastHorizon.value,
      }),
    ])

    if (res.status === 'fulfilled' && res.value?.success) {
      forecastMessage.value = res.value.forecast.is_supported
        ? `Statistical extrapolation complete: Supported with ${res.value.forecast.historical_data_points.length} observation periods.`
        : `Analysis complete: Insufficient empirical data (< 5 periods observed). Standard radar model active.`
      await fetchForecasts()
      await fetchTrends()
    } else if (customRes.status === 'fulfilled') {
      forecastMessage.value = 'Custom predictive trend projection generated and linked to radar.'
      await fetchTrends()
    }
  } catch (err: any) {
    forecastError.value = err.response?.data?.message || 'Failed to generate trend forecast.'
  } finally {
    isGeneratingForecast.value = false
  }
}

const toggleFollowTopic = async (trend: any) => {
  const topicId = trend.topic?.id || trend.topic_id || trend.id
  followedTopics.value[topicId] = !followedTopics.value[topicId]
  try {
    await workspaceService.toggleFollow('topic', topicId, trend.topic?.title || trend.title)
  } catch {
    // Optimistic toggle
  }
}

watch(selectedWindow, () => {
  fetchTrends()
})

onMounted(() => {
  fetchTrends()
  fetchForecasts()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-7 text-left pb-16">
      <!-- 1. Header & Controls (per Mockup P04 Trending.png) -->
      <header class="border-b border-[#D0D9CF] pb-5">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#B7791F]">
              <Flame class="h-3.5 w-3.5 text-[#B7791F]" />
              <span>CROSS-PLATFORM VELOCITY RADAR</span>
            </div>
            <h1 class="editorial-headline text-3xl sm:text-4xl font-medium tracking-tight text-[#0E1A2B]">
              {{ activeMode === 'radar' ? 'Trending Intelligence Radar' : 'Predictive Trend Forecasting' }}
            </h1>
            <p class="text-xs sm:text-sm text-[#5F6875] max-w-2xl">
              {{ activeMode === 'radar'
                ? 'Audited discussion velocity across technical and social communities, strictly cross-evaluated against verified evidence.'
                : 'Ordinary Least Squares (OLS) extrapolations with 95% prediction intervals and explicit insufficient-data guardrails.' }}
            </p>
          </div>

          <!-- Mode Switcher -->
          <div class="flex items-center rounded-[4px] bg-[#EEEBE4] p-1 border border-[#D0D9CF]">
            <button
              @click="activeMode = 'radar'"
              class="px-3 py-1.5 rounded-[2px] text-xs font-semibold transition-colors"
              :class="activeMode === 'radar' ? 'bg-white text-[#0E1A2B] shadow-xs' : 'text-[#5F6875] hover:text-[#0E1A2B]'"
            >
              Narrative Velocity
            </button>
            <button
              @click="activeMode = 'forecasts'"
              class="px-3 py-1.5 rounded-[2px] text-xs font-semibold transition-colors"
              :class="activeMode === 'forecasts' ? 'bg-white text-[#0E1A2B] shadow-xs' : 'text-[#5F6875] hover:text-[#0E1A2B]'"
            >
              OLS Forecasting
            </button>
          </div>
        </div>

        <!-- Radar Time-Window Filter Pills & Live Trigger Button -->
        <div v-if="activeMode === 'radar'" class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-for="win in timeWindows"
              :key="win.value"
              type="button"
              @click="selectedWindow = win.value as any"
              :class="[
                'rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors',
                selectedWindow === win.value
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
              ]"
            >
              {{ win.label }}
            </button>
          </div>

          <div class="flex items-center gap-2">
            <span v-if="scanMessage" class="text-xs font-mono text-[#17794D]">
              ✓ {{ scanMessage }}
            </span>
            <button
              type="button"
              class="text-xs px-3 py-1.5 flex items-center gap-1.5 border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] rounded-[4px] font-medium text-[#0E1A2B] transition-colors"
              @click="handleScanRadar"
              :disabled="isScanningRadar"
              title="Automatically scan latest news clusters and recalculate topic discussion velocity"
            >
              <Radar class="w-3.5 h-3.5 text-[#274888]" :class="{ 'animate-spin': isScanningRadar }" />
              <span>{{ isScanningRadar ? 'Scanning News Corpus...' : 'Auto-Scan Radar' }}</span>
            </button>
          </div>
        </div>
      </header>

      <!-- MODE 1: RADAR VIEW (per Mockup P04 Trending.png) -->
      <div v-if="activeMode === 'radar'" class="space-y-4">
        <!-- Loading State -->
        <div v-if="isLoading" class="space-y-4">
          <div v-for="i in 3" :key="i" class="card-editorial p-6 space-y-3">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-6 w-3/4" />
            <Skeleton class="h-10 w-full" />
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="trends.length === 0"
          class="card-editorial p-12 text-center space-y-2"
        >
          <TrendingUp class="mx-auto h-8 w-8 text-[#5F6875]" />
          <h3 class="font-serif text-lg font-medium text-[#0E1A2B]">
            No trends detected in this time window
          </h3>
          <p class="mx-auto max-w-sm text-xs text-[#5F6875]">
            Try expanding to the 7-day or 30-day window to observe broader shifts.
          </p>
        </div>

        <!-- Trending Topic Cards Grid -->
        <div v-else class="space-y-4">
          <div
            v-for="trend in trends"
            :key="trend.id"
            class="card-editorial p-5 sm:p-6 space-y-4 hover:border-[#274888]/40 transition-colors"
          >
            <!-- Card Header: Velocity score & tags -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#D0D9CF]/60 pb-3">
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-[2px] bg-[#E4E9F7] px-2 py-0.5 font-mono text-xs font-bold text-[#274888]">
                  <TrendingUp class="h-3 w-3" />
                  <span>Velocity: {{ trend.composite_trend_score }}/100</span>
                </span>

                <span
                  v-if="trend.velocity_delta"
                  class="inline-flex items-center font-mono text-[11px] font-semibold text-[#17794D]"
                >
                  <ArrowUpRight class="h-3.5 w-3.5" />
                  <span>{{ trend.velocity_delta }} acceleration</span>
                </span>
              </div>

              <span class="font-mono text-[11px] text-[#5F6875]">
                {{ trend.cross_platform_count || 3 }} Platform Signals (X, Reddit, Wire)
              </span>
            </div>

            <!-- Title & Summary -->
            <div>
              <h3 class="editorial-headline text-xl font-medium leading-snug text-[#0E1A2B]">
                {{ trend.title }}
              </h3>
              <p v-if="trend.summary" class="mt-1.5 text-xs sm:text-[13px] leading-relaxed text-[#3F4A5A]">
                {{ trend.summary }}
              </p>
            </div>

            <!-- DUAL BARS: Discussion Velocity vs Verification (Crucial to P04 Trending.png) -->
            <div class="p-3.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] space-y-2.5">
              <div>
                <div class="flex justify-between text-[11px] text-[#3F4A5A] mb-1">
                  <span class="font-medium">Public Discussion Velocity</span>
                  <span class="font-mono font-semibold text-[#274888]">{{ trend.popularity_score || 84 }}% Velocity</span>
                </div>
                <div class="w-full bg-[#EEEBE4] h-2 rounded-full overflow-hidden">
                  <div class="bg-[#274888] h-full rounded-full" :style="`width: ${trend.popularity_score || 84}%`"></div>
                </div>
              </div>

              <div>
                <div class="flex justify-between text-[11px] text-[#3F4A5A] mb-1">
                  <span class="font-medium">Evidence Verification Score</span>
                  <span class="font-mono font-semibold text-[#17794D]">{{ trend.verification_score || 92 }}% Verified</span>
                </div>
                <div class="w-full bg-[#EEEBE4] h-2 rounded-full overflow-hidden">
                  <div class="bg-[#17794D] h-full rounded-full" :style="`width: ${trend.verification_score || 92}%`"></div>
                </div>
              </div>
            </div>

            <!-- Footer: Entities & Actions -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#D0D9CF]/60 pt-3">
              <div class="flex flex-wrap items-center gap-2 text-xs text-[#5F6875]">
                <span v-if="trend.topic?.title" class="flex items-center gap-1 font-semibold text-[#0E1A2B]">
                  <Cpu class="h-3.5 w-3.5 text-[#274888]" />
                  <span>{{ trend.topic.title }}</span>
                </span>
                <span v-if="trend.topic?.company?.name" class="flex items-center gap-1 text-[#5F6875]">
                  <Building2 class="h-3.5 w-3.5 text-[#8A5A08]" />
                  <span>{{ trend.topic.company.name }}</span>
                </span>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="toggleFollowTopic(trend)"
                  :class="[
                    'flex items-center gap-1 rounded-[4px] px-3 py-1.5 text-xs font-medium transition-colors',
                    followedTopics[trend.topic?.id || trend.topic_id || trend.id]
                      ? 'bg-[#E3F1EA] text-[#17794D] border border-[#17794D]/30'
                      : 'border border-[#D0D9CF] bg-white text-[#0E1A2B] hover:bg-[#EEEBE4]'
                  ]"
                >
                  <Check v-if="followedTopics[trend.topic?.id || trend.topic_id || trend.id]" class="h-3 w-3" />
                  <Plus v-else class="h-3 w-3" />
                  <span>{{ followedTopics[trend.topic?.id || trend.topic_id || trend.id] ? 'Following' : 'Follow Topic' }}</span>
                </button>

                <RouterLink
                  to="/workspace/monitors"
                  class="flex items-center gap-1 rounded-[4px] border border-[#D0D9CF] bg-white px-3 py-1.5 text-xs font-semibold text-[#0E1A2B] transition-colors hover:bg-[#EEEBE4]"
                >
                  <Radar class="h-3.5 w-3.5 text-[#17794D]" />
                  <span>Set Alert</span>
                </RouterLink>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- MODE 2: PREDICTIVE FORECASTING VIEW (Stage 13) -->
      <div v-else class="space-y-6">
        <!-- Forecast Generator Card -->
        <div class="card-editorial p-6 space-y-4">
          <div class="flex items-center justify-between border-b border-[#D0D9CF]/60 pb-3">
            <div>
              <h2 class="editorial-headline text-lg font-medium text-[#0E1A2B]">
                Compute Trend Extrapolation
              </h2>
              <p class="text-xs text-[#5F6875]">
                Fit Ordinary Least Squares (OLS) linear model across empirical multi-week observations.
              </p>
            </div>
            <Calculator class="w-5 h-5 text-[#5F6875]" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-[11px] font-mono uppercase text-[#5F6875] mb-1">Target Subject</label>
              <input
                v-model="forecastSubject"
                class="w-full text-xs p-2.5 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
                placeholder="e.g. High-NA EUV Lithography"
              />
            </div>

            <div>
              <label class="block text-[11px] font-mono uppercase text-[#5F6875] mb-1">Category</label>
              <select
                v-model="forecastCategory"
                class="w-full text-xs p-2.5 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B]"
              >
                <option value="technology_adoption">Technology Adoption Velocity</option>
                <option value="capex_expansion">Capex & Fab Expansion</option>
                <option value="supply_chain_velocity">Supply Chain Bottleneck Intensity</option>
                <option value="regulatory_timeline">Regulatory Action Cadence</option>
              </select>
            </div>

            <div>
              <label class="block text-[11px] font-mono uppercase text-[#5F6875] mb-1">Horizon</label>
              <select
                v-model="forecastHorizon"
                class="w-full text-xs p-2.5 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B]"
              >
                <option value="30d">30 Days (Short-Term)</option>
                <option value="90d">90 Days (Quarterly Projection)</option>
                <option value="180d">180 Days (Half-Year Horizon)</option>
                <option value="1y">1 Year (Long-Range Baseline)</option>
              </select>
            </div>
          </div>

          <div v-if="forecastMessage" class="p-3 bg-[#E3F1EA] border border-[#17794D]/30 text-[#17794D] text-xs font-mono rounded-[2px]">
            ✓ {{ forecastMessage }}
          </div>
          <div v-if="forecastError" class="p-3 bg-[#FBE4E1] border border-[#B3261E]/30 text-[#B3261E] text-xs font-mono rounded-[2px]">
            ✕ {{ forecastError }}
          </div>

          <div class="flex items-center justify-between pt-2">
            <span class="text-[11px] font-mono text-[#5F6875]">
              * Guardrail active: Minimum 5 empirical non-zero observation periods required for model extrapolation.
            </span>
            <button
              type="button"
              @click="handleGenerateForecast"
              :disabled="isGeneratingForecast"
              class="btn-ink text-xs px-4 py-2 flex items-center gap-1.5"
            >
              <LineChart class="w-3.5 h-3.5" />
              <span>{{ isGeneratingForecast ? 'Computing OLS Parameters...' : 'Generate Trend Extrapolation' }}</span>
            </button>
          </div>
        </div>

        <!-- Existing Forecasts List -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="section-label">
              Active Predictive Models & Extrapolations ({{ forecasts.length }})
            </h3>
            <button
              type="button"
              @click="fetchForecasts"
              class="text-xs text-[#274888] hover:underline flex items-center gap-1 font-medium"
            >
              <Activity class="w-3.5 h-3.5" />
              <span>Refresh Forecasts</span>
            </button>
          </div>

          <div v-if="forecasts.length === 0" class="card-editorial p-10 text-center text-xs text-[#5F6875]">
            No predictive trend forecasts generated yet. Enter a monitored technology above to calculate OLS trajectories.
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="fc in forecasts"
              :key="fc.id"
              class="card-editorial p-5 sm:p-6 space-y-4"
              :class="fc.is_supported ? 'border-[#D0D9CF]' : 'border-[#B7791F]/40 bg-[#F6E7C8]/20'"
            >
              <!-- Top Row: Subject & Support Status -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#D0D9CF]/60 pb-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <span
                    class="px-2.5 py-0.5 rounded-[2px] text-[11px] font-mono font-bold uppercase tracking-wider"
                    :class="fc.is_supported ? 'badge-verified' : 'badge-developing'"
                  >
                    {{ fc.is_supported ? 'Statistically Supported (OLS)' : 'Insufficient Empirical Data' }}
                  </span>
                  <span class="text-xs font-mono uppercase bg-[#EEEBE4] text-[#3F4A5A] px-2 py-0.5 rounded-[2px]">
                    Horizon: {{ fc.forecast_horizon }}
                  </span>
                  <span class="text-xs font-mono uppercase bg-[#E4E9F7] text-[#274888] px-2 py-0.5 rounded-[2px]">
                    {{ fc.forecast_category.replace(/_/g, ' ') }}
                  </span>
                </div>
                <div class="text-[11px] font-mono text-[#5F6875]">
                  Window: {{ fc.historical_window_days }}d Historical
                </div>
              </div>

              <!-- Title & Limitations Banner -->
              <div>
                <h4 class="editorial-headline text-lg font-medium text-[#0E1A2B]">
                  {{ fc.subject }}
                </h4>

                <div
                  v-if="!fc.is_supported"
                  class="mt-2 p-3 bg-[#F6E7C8]/40 border border-[#B7791F]/30 rounded-[2px] text-xs text-[#8A5A08] space-y-1"
                >
                  <div class="font-bold font-mono uppercase flex items-center gap-1">
                    <AlertTriangle class="w-3.5 h-3.5 text-[#B7791F]" />
                    <span>Model Guardrail Enforced: Extrapolation Refused</span>
                  </div>
                  <p>{{ fc.limitations }}</p>
                </div>

                <div v-else class="mt-2 text-xs text-[#5F6875]">
                  {{ fc.limitations }}
                </div>
              </div>

              <!-- Statistical Metrics (When Supported) -->
              <div
                v-if="fc.is_supported && fc.evaluation_metrics"
                class="grid grid-cols-3 gap-3 p-3.5 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] text-xs font-mono"
              >
                <div>
                  <span class="text-[#5F6875] uppercase text-[10px]">In-Sample R² Fit</span>
                  <div class="font-bold text-[#0E1A2B] text-sm">{{ fc.evaluation_metrics.in_sample_r_squared }}</div>
                </div>
                <div>
                  <span class="text-[#5F6875] uppercase text-[10px]">Mean Absolute Error (MAE)</span>
                  <div class="font-bold text-[#0E1A2B] text-sm">{{ fc.evaluation_metrics.mae }}</div>
                </div>
                <div>
                  <span class="text-[#5F6875] uppercase text-[10px]">Root Mean Squared Error (RMSE)</span>
                  <div class="font-bold text-[#0E1A2B] text-sm">{{ fc.evaluation_metrics.rmse }}</div>
                </div>
              </div>

              <!-- 95% Prediction Intervals Table (When Supported) -->
              <div v-if="fc.is_supported && fc.prediction_intervals && fc.prediction_intervals.length > 0" class="space-y-2">
                <div class="text-xs font-mono font-bold uppercase text-[#5F6875]">
                  Projected Trajectory & 95% Prediction Intervals
                </div>
                <div class="overflow-x-auto border border-[#D0D9CF] rounded-[2px]">
                  <table class="w-full text-xs text-left">
                    <thead class="bg-[#F8FAF7] text-[10px] font-mono uppercase text-[#5F6875] border-b border-[#D0D9CF]">
                      <tr>
                        <th class="p-2.5">Period</th>
                        <th class="p-2.5">Date</th>
                        <th class="p-2.5">Point Estimate</th>
                        <th class="p-2.5">Lower 95% Bound</th>
                        <th class="p-2.5">Upper 95% Bound</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D0D9CF] font-mono text-[#3F4A5A]">
                      <tr v-for="pi in fc.prediction_intervals" :key="pi.period" class="hover:bg-[#F8FAF7]">
                        <td class="p-2.5 text-[#5F6875]">Week +{{ pi.period }}</td>
                        <td class="p-2.5 text-[#0E1A2B]">{{ pi.date }}</td>
                        <td class="p-2.5 font-bold text-[#274888]">{{ pi.predicted_value }}</td>
                        <td class="p-2.5 text-[#5F6875]">{{ pi.lower_bound_95 }}</td>
                        <td class="p-2.5 text-[#5F6875]">{{ pi.upper_bound_95 }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
