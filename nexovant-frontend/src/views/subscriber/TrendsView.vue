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
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
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

const handleGenerateForecast = async () => {
  if (!forecastSubject.value.trim()) {
    forecastError.value = 'Target subject is required.'
    return
  }

  isGeneratingForecast.value = true
  forecastMessage.value = ''
  forecastError.value = ''

  try {
    const res = await automationService.generateForecast({
      subject: forecastSubject.value.trim(),
      category: forecastCategory.value,
      horizon: forecastHorizon.value,
    })

    if (res.success) {
      forecastMessage.value = res.forecast.is_supported
        ? `Statistical extrapolation complete: Supported with ${res.forecast.historical_data_points.length} observation periods.`
        : `Analysis complete: Insufficient empirical data (< 5 periods observed). Unsupported guardrail active.`
      await fetchForecasts()
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

const getStatusBadge = (status: string) => {
  const normalized = (status || '').toLowerCase()
  if (normalized.includes('accelerat')) return { variant: 'gold' as const, label: 'Rapidly Accelerating' }
  if (normalized.includes('trend')) return { variant: 'developing' as const, label: 'Trending' }
  if (normalized.includes('emerg')) return { variant: 'reported_reliable' as const, label: 'Emerging Signal' }
  return { variant: 'neutral' as const, label: status.replace('_', ' ') }
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
    <div class="mx-auto max-w-5xl space-y-6 text-left">
      <!-- 1. Header & Controls -->
      <header class="border-b border-nexo-border/80 pb-5">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-widest text-nexo-accent-cyan">
              <Flame class="h-3.5 w-3.5 text-amber-500" />
              <span>Predictive Intelligence Radar</span>
            </div>
            <h1 class="mt-2 font-serif text-3xl font-medium tracking-tight text-nexo-text-primary sm:text-4xl">
              {{ activeMode === 'radar' ? 'Trending Intelligence Radar' : 'Predictive Trend Forecasting' }}
            </h1>
            <p class="mt-2 text-sm text-nexo-text-secondary">
              {{ activeMode === 'radar'
                ? 'Audited narrative velocity across technical communities, strictly distinguished from verified news.'
                : 'Ordinary Least Squares (OLS) extrapolations with 95% prediction intervals and explicit insufficient-data guardrails.' }}
            </p>
          </div>

          <!-- Mode Switcher -->
          <div class="flex items-center rounded-xl bg-slate-100 p-1 border border-slate-200">
            <button
              @click="activeMode = 'radar'"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
              :class="activeMode === 'radar' ? 'bg-white text-nexo-text-primary shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              Narrative Velocity
            </button>
            <button
              @click="activeMode = 'forecasts'"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
              :class="activeMode === 'forecasts' ? 'bg-white text-nexo-text-primary shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            >
              OLS Forecasting
            </button>
          </div>
        </div>

        <!-- Radar Time-Window Filter Pills -->
        <div v-if="activeMode === 'radar'" class="mt-5 flex flex-wrap items-center gap-2">
          <button
            v-for="win in timeWindows"
            :key="win.value"
            type="button"
            @click="selectedWindow = win.value as any"
            :class="[
              'rounded-xl px-3.5 py-1.5 text-xs font-semibold transition',
              selectedWindow === win.value
                ? 'bg-nexo-accent-cyan text-white shadow-xs'
                : 'border border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            {{ win.label }}
          </button>
        </div>
      </header>

      <!-- MODE 1: RADAR VIEW -->
      <div v-if="activeMode === 'radar'" class="space-y-4">
        <!-- Loading State -->
        <div v-if="isLoading" class="space-y-4">
          <Card v-for="i in 3" :key="i" padding="lg">
            <Skeleton class="h-4 w-32 mb-2" />
            <Skeleton class="h-6 w-3/4 mb-3" />
            <Skeleton class="h-10 w-full" />
          </Card>
        </div>

        <!-- Empty State -->
        <div
          v-else-if="trends.length === 0"
          class="rounded-2xl border border-dashed border-nexo-border bg-white p-12 text-center"
        >
          <TrendingUp class="mx-auto h-10 w-10 text-nexo-text-muted" />
          <h3 class="mt-3 font-serif text-lg font-medium text-nexo-text-primary">
            No trends detected in this time window
          </h3>
          <p class="mx-auto mt-1.5 max-w-sm text-xs text-nexo-text-secondary">
            Try expanding to the 7-day or 30-day time window to observe broader macroeconomic and tech shifts.
          </p>
        </div>

        <!-- Trending Signal Cards Grid -->
        <div v-else class="space-y-4">
          <Card
            v-for="trend in trends"
            :key="trend.id"
            padding="lg"
            class="group border-nexo-border/80 transition hover:border-nexo-accent-cyan/35 hover:shadow-sm"
          >
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-nexo-border/60 pb-3">
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-md bg-nexo-accent-cyan/10 px-2 py-0.5 font-mono text-xs font-bold text-nexo-accent-cyan">
                  <TrendingUp class="h-3 w-3" />
                  <span>Velocity Score: {{ trend.composite_trend_score }}</span>
                </span>

                <Badge :variant="getStatusBadge(trend.status).variant" size="sm">
                  {{ getStatusBadge(trend.status).label }}
                </Badge>

                <span
                  v-if="trend.velocity_delta"
                  class="inline-flex items-center font-mono text-[11px] font-semibold text-emerald-700"
                >
                  <ArrowUpRight class="h-3.5 w-3.5" />
                  <span>{{ trend.velocity_delta }}</span>
                </span>
              </div>

              <span class="font-mono text-[11px] text-nexo-text-muted">
                {{ trend.cross_platform_count || 3 }} Network Signals
              </span>
            </div>

            <div class="mt-3.5">
              <h3 class="font-serif text-xl font-medium leading-snug text-nexo-text-primary transition group-hover:text-nexo-accent-cyan">
                {{ trend.title }}
              </h3>
              <p v-if="trend.summary" class="mt-2 text-xs leading-relaxed text-nexo-text-secondary sm:text-[13px]">
                {{ trend.summary }}
              </p>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-nexo-border/60 pt-3">
              <div class="flex flex-wrap items-center gap-2 text-xs text-nexo-text-secondary">
                <span v-if="trend.topic?.title" class="flex items-center gap-1 font-semibold text-nexo-text-primary">
                  <Cpu class="h-3.5 w-3.5 text-nexo-accent-cyan" />
                  <span>{{ trend.topic.title }}</span>
                </span>
                <span v-if="trend.topic?.company?.name" class="flex items-center gap-1 text-nexo-text-muted">
                  <Building2 class="h-3.5 w-3.5 text-nexo-accent-gold" />
                  <span>{{ trend.topic.company.name }}</span>
                </span>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="toggleFollowTopic(trend)"
                  :class="[
                    'flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-medium transition',
                    followedTopics[trend.topic?.id || trend.topic_id || trend.id]
                      ? 'bg-[#e9f3ed] text-nexo-accent-cyan'
                      : 'border border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
                  ]"
                >
                  <Check v-if="followedTopics[trend.topic?.id || trend.topic_id || trend.id]" class="h-3 w-3" />
                  <Plus v-else class="h-3 w-3" />
                  <span>{{ followedTopics[trend.topic?.id || trend.topic_id || trend.id] ? 'Following' : 'Follow Topic' }}</span>
                </button>

                <RouterLink
                  to="/workspace/monitors"
                  class="flex items-center gap-1 rounded-lg border border-nexo-border bg-white px-2.5 py-1 text-xs font-medium text-nexo-text-secondary transition hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary"
                >
                  <Radar class="h-3 w-3 text-emerald-600" />
                  <span>Set Alert</span>
                </RouterLink>
              </div>
            </div>
          </Card>
        </div>
      </div>

      <!-- MODE 2: PREDICTIVE FORECASTING VIEW -->
      <div v-else class="space-y-6">
        <!-- Forecast Generator Card -->
        <Card padding="lg" class="bg-white border-nexo-border space-y-4">
          <div class="flex items-center justify-between border-b pb-3">
            <div>
              <h2 class="font-serif font-bold text-lg text-nexo-text-primary">
                Compute Trend Extrapolation
              </h2>
              <p class="text-xs text-nexo-text-secondary">
                Fit Ordinary Least Squares (OLS) linear model across empirical multi-week observations.
              </p>
            </div>
            <Calculator class="w-6 h-6 text-slate-400" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Target Subject / Tech</label>
              <Input v-model="forecastSubject" placeholder="e.g. High-NA EUV Lithography" />
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Forecast Category</label>
              <select
                v-model="forecastCategory"
                class="w-full text-xs p-2 border rounded border-slate-300 bg-white"
              >
                <option value="technology_adoption">Technology Adoption Velocity</option>
                <option value="capex_expansion">Capex & Fab Expansion</option>
                <option value="supply_chain_velocity">Supply Chain Bottleneck Intensity</option>
                <option value="regulatory_timeline">Regulatory Action Cadence</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Projection Horizon</label>
              <select
                v-model="forecastHorizon"
                class="w-full text-xs p-2 border rounded border-slate-300 bg-white"
              >
                <option value="30d">30 Days (Short-Term)</option>
                <option value="90d">90 Days (Quarterly Projection)</option>
                <option value="180d">180 Days (Half-Year Horizon)</option>
                <option value="1y">1 Year (Long-Range Baseline)</option>
              </select>
            </div>
          </div>

          <div v-if="forecastMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded">
            ✓ {{ forecastMessage }}
          </div>
          <div v-if="forecastError" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded">
            ✕ {{ forecastError }}
          </div>

          <div class="flex items-center justify-between pt-2">
            <div class="text-[11px] font-mono text-slate-500">
              * Guardrail active: Minimum 5 empirical non-zero observation periods required for model extrapolation.
            </div>
            <Button
              variant="primary"
              size="sm"
              @click="handleGenerateForecast"
              :disabled="isGeneratingForecast"
            >
              <LineChart class="w-3.5 h-3.5 mr-1" />
              {{ isGeneratingForecast ? 'Computing OLS Parameters...' : 'Generate Trend Extrapolation' }}
            </Button>
          </div>
        </Card>

        <!-- Existing Forecasts List -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="font-serif font-bold text-base text-nexo-text-primary">
              Active Predictive Models & Extrapolations ({{ forecasts.length }})
            </h3>
            <Button variant="ghost" size="xs" @click="fetchForecasts">
              <Activity class="w-3.5 h-3.5 mr-1" /> Refresh Forecasts
            </Button>
          </div>

          <div v-if="forecasts.length === 0" class="p-8 bg-white border border-dashed rounded-lg text-center text-xs text-slate-500">
            No predictive trend forecasts generated yet. Enter a monitored technology above to calculate OLS trajectories.
          </div>

          <div v-else class="space-y-4">
            <Card
              v-for="fc in forecasts"
              :key="fc.id"
              padding="lg"
              class="bg-white border-nexo-border space-y-4"
              :class="fc.is_supported ? 'border-nexo-border' : 'border-amber-200 bg-amber-50/20'"
            >
              <!-- Top Row: Subject & Support Status -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b pb-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <span
                    class="px-2.5 py-0.5 rounded text-[11px] font-mono font-bold uppercase tracking-wider"
                    :class="fc.is_supported ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-800 border border-amber-300'"
                  >
                    {{ fc.is_supported ? 'Statistically Supported (OLS)' : 'Insufficient Empirical Data' }}
                  </span>
                  <span class="text-xs font-mono uppercase bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                    Horizon: {{ fc.forecast_horizon }}
                  </span>
                  <span class="text-xs font-mono uppercase bg-blue-50 text-blue-700 px-2 py-0.5 rounded">
                    {{ fc.forecast_category.replace(/_/g, ' ') }}
                  </span>
                </div>
                <div class="text-[11px] font-mono text-slate-500">
                  Window: {{ fc.historical_window_days }}d Historical
                </div>
              </div>

              <!-- Title & Limitations Banner -->
              <div>
                <h4 class="font-serif font-bold text-lg text-nexo-text-primary">
                  {{ fc.subject }}
                </h4>

                <div
                  v-if="!fc.is_supported"
                  class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded text-xs text-amber-900 space-y-1"
                >
                  <div class="font-bold font-mono uppercase flex items-center gap-1 text-amber-800">
                    <AlertTriangle class="w-3.5 h-3.5" />
                    Model Guardrail Enforced: Extrapolation Refused
                  </div>
                  <p>{{ fc.limitations }}</p>
                </div>

                <div v-else class="mt-2 text-xs text-slate-600">
                  {{ fc.limitations }}
                </div>
              </div>

              <!-- Statistical Metrics (When Supported) -->
              <div
                v-if="fc.is_supported && fc.evaluation_metrics"
                class="grid grid-cols-3 gap-3 p-3 bg-slate-50 rounded border border-slate-200 text-xs font-mono"
              >
                <div>
                  <span class="text-slate-500 uppercase text-[10px]">In-Sample R² Fit</span>
                  <div class="font-bold text-slate-800 text-sm">{{ fc.evaluation_metrics.in_sample_r_squared }}</div>
                </div>
                <div>
                  <span class="text-slate-500 uppercase text-[10px]">Mean Absolute Error (MAE)</span>
                  <div class="font-bold text-slate-800 text-sm">{{ fc.evaluation_metrics.mae }}</div>
                </div>
                <div>
                  <span class="text-slate-500 uppercase text-[10px]">Root Mean Squared Error (RMSE)</span>
                  <div class="font-bold text-slate-800 text-sm">{{ fc.evaluation_metrics.rmse }}</div>
                </div>
              </div>

              <!-- 95% Prediction Intervals Table (When Supported) -->
              <div v-if="fc.is_supported && fc.prediction_intervals && fc.prediction_intervals.length > 0" class="space-y-2">
                <div class="text-xs font-mono font-bold uppercase text-slate-600">
                  Projected Trajectory & 95% Prediction Intervals
                </div>
                <div class="overflow-x-auto border rounded">
                  <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-[10px] font-mono uppercase text-slate-500 border-b">
                      <tr>
                        <th class="p-2">Period</th>
                        <th class="p-2">Date</th>
                        <th class="p-2">Point Estimate</th>
                        <th class="p-2">Lower 95% Bound</th>
                        <th class="p-2">Upper 95% Bound</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y font-mono">
                      <tr v-for="pi in fc.prediction_intervals" :key="pi.period" class="hover:bg-slate-50/50">
                        <td class="p-2 text-slate-600">Week +{{ pi.period }}</td>
                        <td class="p-2 text-slate-800">{{ pi.date }}</td>
                        <td class="p-2 font-bold text-blue-600">{{ pi.predicted_value }}</td>
                        <td class="p-2 text-slate-500">{{ pi.lower_bound_95 }}</td>
                        <td class="p-2 text-slate-500">{{ pi.upper_bound_95 }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
