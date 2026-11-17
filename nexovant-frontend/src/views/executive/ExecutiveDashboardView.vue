<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import {
  executiveService,
  type ExecutiveBriefing,
  type StrategicImpactAssessment,
  type IntelligenceOpportunity,
  type IntelligenceRisk,
  type IntelligenceScenario,
  type IntelligenceDecision,
} from '@/services/api/executive.service'
import {
  FileText,
  AlertTriangle,
  Compass,
  GitBranch,
  CheckCircle,
  HelpCircle,
  ArrowRight,
  TrendingUp,
  Layers,
  Sparkles,
} from 'lucide-vue-next'

const isLoading = ref(true)
const errorMessage = ref('')

const metrics = ref<Record<string, number>>({})
const latestBriefing = ref<ExecutiveBriefing | null>(null)
const recentImpacts = ref<StrategicImpactAssessment[]>([])
const activeRisks = ref<IntelligenceRisk[]>([])
const emergingOpportunities = ref<IntelligenceOpportunity[]>([])
const activeScenarios = ref<IntelligenceScenario[]>([])
const openDecisions = ref<IntelligenceDecision[]>([])

const fetchDashboard = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const data = await executiveService.getCockpit()
    metrics.value = data.metrics || {}
    latestBriefing.value = data.latest_briefing || null
    recentImpacts.value = data.recent_impacts || []
    activeRisks.value = data.active_risks || []
    emergingOpportunities.value = data.emerging_opportunities || []
    activeScenarios.value = data.active_scenarios || []
    openDecisions.value = data.open_decisions || []
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load executive cockpit telemetry.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchDashboard()
})
</script>

<template>
  <ThreeColumnLayout>
    <div class="space-y-6">
      <!-- Executive Cockpit Header -->
      <div class="flex flex-col justify-between gap-4 border-b border-nexo-border pb-5 sm:flex-row sm:items-center">
        <div>
          <div class="flex items-center gap-2">
            <span class="badge-verified">STAGE 15</span>
            <span class="section-label">EXECUTIVE DECISION SUPPORT</span>
          </div>
          <h1 class="font-serif text-3xl font-bold tracking-tight text-nexo-ink mt-1">
            Executive Intelligence Cockpit
          </h1>
          <p class="mt-1 text-sm text-nexo-text-2">
            Evidence-backed decision support, multi-dimensional impact modeling, and scenario forecasting.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <RouterLink
            to="/executive/assistant"
            class="inline-flex items-center gap-1.5 rounded-[4px] border border-nexo-cobalt bg-white px-3 py-1.5 text-xs font-semibold text-nexo-cobalt transition hover:bg-nexo-cobalt-tint/30"
          >
            <Sparkles class="h-3.5 w-3.5" />
            <span>Executive Assistant</span>
          </RouterLink>
          <RouterLink
            to="/executive/briefings"
            class="btn-ink"
          >
            <span>Generate Briefing</span>
          </RouterLink>
        </div>
      </div>

      <!-- Loading and Error States -->
      <div v-if="isLoading" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <div v-for="i in 6" :key="i" class="h-20 shimmer-bg rounded-[2px]" />
        </div>
        <div class="h-64 shimmer-bg rounded-[2px]" />
      </div>

      <div v-else-if="errorMessage" class="rounded-[2px] border border-nexo-breaking/30 bg-nexo-breaking-tint/30 p-4 text-sm text-nexo-breaking">
        {{ errorMessage }}
      </div>

      <div v-else class="space-y-8">
        <!-- 6-Metric Executive Strip -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <RouterLink
            to="/executive/briefings"
            class="card-editorial p-3.5 transition hover:border-nexo-cobalt"
          >
            <p class="section-label text-[10px]">BRIEFINGS</p>
            <p class="mt-1 font-serif text-2xl font-bold text-nexo-ink">{{ metrics.total_briefings || 0 }}</p>
            <span class="mt-1 inline-flex items-center text-[11px] text-nexo-cobalt">View reports →</span>
          </RouterLink>

          <RouterLink
            to="/executive/strategic-impact"
            class="card-editorial p-3.5 transition hover:border-nexo-cobalt"
          >
            <p class="section-label text-[10px]">STRATEGIC IMPACTS</p>
            <p class="mt-1 font-serif text-2xl font-bold text-nexo-ink">{{ metrics.total_impact_assessments || 0 }}</p>
            <span class="mt-1 inline-flex items-center text-[11px] text-nexo-cobalt">10 dimensions →</span>
          </RouterLink>

          <RouterLink
            to="/executive/opportunities-risks?tab=risks"
            class="card-editorial p-3.5 transition hover:border-nexo-breaking"
          >
            <p class="section-label text-[10px] text-nexo-breaking">ACTIVE RISKS</p>
            <p class="mt-1 font-serif text-2xl font-bold text-nexo-breaking">{{ metrics.total_active_risks || 0 }}</p>
            <span class="mt-1 inline-flex items-center text-[11px] text-nexo-breaking">Priority triage →</span>
          </RouterLink>

          <RouterLink
            to="/executive/opportunities-risks?tab=opportunities"
            class="card-editorial p-3.5 transition hover:border-nexo-verified"
          >
            <p class="section-label text-[10px] text-nexo-verified">OPPORTUNITIES</p>
            <p class="mt-1 font-serif text-2xl font-bold text-nexo-verified">{{ metrics.total_opportunities || 0 }}</p>
            <span class="mt-1 inline-flex items-center text-[11px] text-nexo-verified">Growth vectors →</span>
          </RouterLink>

          <RouterLink
            to="/executive/scenarios"
            class="card-editorial p-3.5 transition hover:border-nexo-brass"
          >
            <p class="section-label text-[10px]">SCENARIOS</p>
            <p class="mt-1 font-serif text-2xl font-bold text-nexo-ink">{{ metrics.total_scenarios || 0 }}</p>
            <span class="mt-1 inline-flex items-center text-[11px] text-nexo-brass-text">Projections →</span>
          </RouterLink>

          <RouterLink
            to="/executive/decisions"
            class="card-editorial p-3.5 transition hover:border-nexo-cobalt"
          >
            <p class="section-label text-[10px]">OPEN DECISIONS</p>
            <p class="mt-1 font-serif text-2xl font-bold text-nexo-ink">{{ metrics.total_open_decisions || 0 }}</p>
            <span class="mt-1 inline-flex items-center text-[11px] text-nexo-cobalt">Review matrix →</span>
          </RouterLink>
        </div>

        <!-- Latest Executive Briefing Hero Card -->
        <section v-if="latestBriefing" class="card-editorial border-l-4 border-l-nexo-brass p-6">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <div class="flex items-center gap-2">
              <span class="badge-verified">{{ latestBriefing.period_type.toUpperCase() }} BRIEFING</span>
              <span class="text-xs text-nexo-muted font-mono">v{{ latestBriefing.version }}</span>
            </div>
            <RouterLink :to="`/executive/briefings/${latestBriefing.id}`" class="text-xs font-semibold text-nexo-cobalt hover:underline">
              Read full 7-section report →
            </RouterLink>
          </div>
          <h2 class="mt-3 font-serif text-2xl font-bold text-nexo-ink">
            {{ latestBriefing.title }}
          </h2>
          <p class="mt-2 text-sm text-nexo-text-2 leading-relaxed">
            {{ latestBriefing.executive_summary }}
          </p>

          <div class="mt-4 grid grid-cols-1 gap-3 pt-4 border-t border-nexo-border md:grid-cols-3">
            <div class="rounded-[2px] bg-nexo-surface-2 p-3 border border-nexo-border">
              <p class="section-label text-[10px]">WHAT HAPPENED</p>
              <p class="mt-1 text-xs text-nexo-ink font-medium line-clamp-2">
                {{ latestBriefing.what_happened?.[0]?.headline || 'Verified intelligence consolidated.' }}
              </p>
            </div>
            <div class="rounded-[2px] bg-nexo-surface-2 p-3 border border-nexo-border">
              <p class="section-label text-[10px]">WHY IT MATTERS</p>
              <p class="mt-1 text-xs text-nexo-ink font-medium line-clamp-2">
                {{ latestBriefing.why_it_matters?.industry_implications || 'Operational margins evaluated.' }}
              </p>
            </div>
            <div class="rounded-[2px] bg-nexo-surface-2 p-3 border border-nexo-border">
              <p class="section-label text-[10px]">WHAT COULD HAPPEN NEXT</p>
              <p class="mt-1 text-xs text-nexo-ink font-medium line-clamp-2">
                {{ latestBriefing.what_could_happen_next?.[0]?.scenario || 'Baseline execution outlook.' }}
              </p>
            </div>
          </div>
        </section>

        <!-- Two Column Grid: Critical Risks vs Open Decisions -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <!-- Critical Risks Block -->
          <div class="card-editorial p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-nexo-border pb-3">
              <div class="flex items-center gap-2">
                <AlertTriangle class="h-4 w-4 text-nexo-breaking" />
                <h3 class="font-serif text-lg font-bold text-nexo-ink">Priority Risk Telemetry</h3>
              </div>
              <RouterLink to="/executive/opportunities-risks?tab=risks" class="text-xs font-semibold text-nexo-cobalt hover:underline">
                View all risks →
              </RouterLink>
            </div>

            <div v-if="activeRisks.length === 0" class="py-8 text-center text-xs text-nexo-muted">
              No active high-severity risk vectors detected.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="risk in activeRisks"
                :key="risk.id"
                class="rounded-[2px] border border-nexo-border p-3 hover:border-nexo-breaking transition-colors"
              >
                <div class="flex items-center justify-between">
                  <span class="badge-breaking">{{ risk.severity.toUpperCase() }}</span>
                  <span class="text-[11px] font-mono text-nexo-muted">{{ risk.time_horizon }}</span>
                </div>
                <h4 class="mt-2 text-sm font-semibold text-nexo-ink">{{ risk.title }}</h4>
                <p class="mt-1 text-xs text-nexo-text-2 line-clamp-2">{{ risk.description }}</p>
                <div class="mt-2 flex items-center justify-between text-[11px] text-nexo-muted pt-2 border-t border-nexo-border">
                  <span>Likelihood: {{ Math.round((risk.likelihood_score || 0.5) * 100) }}% (Transparent estimate)</span>
                  <span class="capitalize">{{ risk.status }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Open Decisions Block -->
          <div class="card-editorial p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-nexo-border pb-3">
              <div class="flex items-center gap-2">
                <CheckCircle class="h-4 w-4 text-nexo-cobalt" />
                <h3 class="font-serif text-lg font-bold text-nexo-ink">Executive Decision Center</h3>
              </div>
              <RouterLink to="/executive/decisions" class="text-xs font-semibold text-nexo-cobalt hover:underline">
                Open workspace →
              </RouterLink>
            </div>

            <div v-if="openDecisions.length === 0" class="py-8 text-center text-xs text-nexo-muted">
              No pending executive decisions awaiting committee action.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="dec in openDecisions"
                :key="dec.id"
                class="rounded-[2px] border border-nexo-border p-3 hover:border-nexo-cobalt transition-colors"
              >
                <div class="flex items-center justify-between">
                  <span class="badge-developing">{{ dec.approval_status.replace('_', ' ').toUpperCase() }}</span>
                  <span class="text-[11px] font-mono text-nexo-muted">
                    Deadline: {{ dec.deadline ? new Date(dec.deadline).toLocaleDateString() : 'Rolling' }}
                  </span>
                </div>
                <h4 class="mt-2 text-sm font-semibold text-nexo-ink">{{ dec.title }}</h4>
                <p class="mt-1 text-xs text-nexo-text-2 line-clamp-2">{{ dec.business_question }}</p>
                <div class="mt-2 flex items-center justify-between text-[11px] text-nexo-muted pt-2 border-t border-nexo-border">
                  <span>{{ dec.options?.length || 0 }} options evaluated</span>
                  <RouterLink :to="`/executive/decisions/${dec.id}`" class="text-nexo-cobalt font-semibold hover:underline">
                    Review matrix →
                  </RouterLink>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Strategic Impact Preview Grid -->
        <section class="card-editorial p-5 space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <div class="flex items-center gap-2">
              <Layers class="h-4 w-4 text-nexo-brass-text" />
              <h3 class="font-serif text-lg font-bold text-nexo-ink">Strategic Impact Assessments (10 Dimensions)</h3>
            </div>
            <RouterLink to="/executive/strategic-impact" class="text-xs font-semibold text-nexo-cobalt hover:underline">
              Analyze dimension →
            </RouterLink>
          </div>

          <div v-if="recentImpacts.length === 0" class="py-6 text-center text-xs text-nexo-muted">
            No recent impact models. Run an assessment across Financial, Supply Chain, or Operational vectors.
          </div>

          <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="imp in recentImpacts"
              :key="imp.id"
              class="rounded-[2px] border border-nexo-border bg-nexo-surface-2 p-3.5 space-y-2"
            >
              <div class="flex items-center justify-between">
                <span class="section-label text-[10px] text-nexo-cobalt">{{ imp.dimension.replace(/_/g, ' ') }}</span>
                <span :class="imp.potential_direction === 'negative' ? 'badge-breaking' : 'badge-verified'">
                  {{ imp.potential_direction.toUpperCase() }}
                </span>
              </div>
              <h4 class="text-sm font-semibold text-nexo-ink line-clamp-1">{{ imp.title }}</h4>
              <p class="text-xs text-nexo-text-2 line-clamp-2">
                Magnitude: {{ imp.estimated_magnitude }} | Time horizon: {{ imp.time_horizon.replace('_', ' ') }}
              </p>
              <div class="text-[11px] text-nexo-muted pt-2 border-t border-nexo-border">
                Confidence: <strong class="text-nexo-ink capitalize">{{ imp.confidence_level }}</strong> ({{ imp.supporting_evidence?.length || 0 }} sources)
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
