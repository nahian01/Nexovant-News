<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import {
  executiveService,
  type IntelligenceOpportunity,
  type IntelligenceRisk,
} from '@/services/api/executive.service'
import {
  TrendingUp,
  AlertTriangle,
  Plus,
  CheckCircle2,
  Clock,
  CheckSquare,
  ShieldAlert,
  X,
} from 'lucide-vue-next'

const route = useRoute()
const activeTab = ref<'opportunities' | 'risks'>(route.query.tab === 'risks' ? 'risks' : 'opportunities')

const opportunities = ref<IntelligenceOpportunity[]>([])
const risks = ref<IntelligenceRisk[]>([])
const isLoading = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const statusMessage = ref('')

const isCreateModalOpen = ref(false)
const createForm = ref({
  type: 'opportunity', // 'opportunity' or 'risk'
  title: '',
  specific_type: 'new_technologies',
  category: 'Strategic Growth',
  description: '',
  severity: 'high',
  likelihood_score: 0.70,
  time_horizon: 'near_term_90d',
  affected_entities_raw: '',
})

const fetchAll = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const [oppRes, riskRes] = await Promise.all([
      executiveService.getOpportunities(),
      executiveService.getRisks(),
    ])
    opportunities.value = oppRes.data || []
    risks.value = riskRes.data || []
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load opportunity and risk intelligence.'
  } finally {
    isLoading.value = false
  }
}

const handleCreate = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const entities = createForm.value.affected_entities_raw.split(',').map(s => s.trim()).filter(Boolean)
    if (createForm.value.type === 'opportunity') {
      await executiveService.recordOpportunity({
        title: createForm.value.title,
        opportunity_type: createForm.value.specific_type,
        category: createForm.value.category,
        description: createForm.value.description,
        likelihood_score: createForm.value.likelihood_score,
        time_horizon: createForm.value.time_horizon,
        affected_entities: entities,
      })
      statusMessage.value = 'Intelligence opportunity recorded.'
    } else {
      await executiveService.recordRisk({
        title: createForm.value.title,
        risk_type: createForm.value.specific_type,
        category: createForm.value.category,
        description: createForm.value.description,
        severity: createForm.value.severity,
        likelihood_score: createForm.value.likelihood_score,
        time_horizon: createForm.value.time_horizon,
        affected_entities: entities,
      })
      statusMessage.value = 'Intelligence risk recorded.'
    }
    isCreateModalOpen.value = false
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to record entry.'
  } finally {
    isSubmitting.value = false
  }
}

const handleUpdateStatus = async (item: IntelligenceOpportunity | IntelligenceRisk, newStatus: string, kind: 'opp' | 'risk') => {
  try {
    if (kind === 'opp') {
      await executiveService.updateOpportunityStatus(item.id, newStatus)
    } else {
      await executiveService.updateRiskStatus(item.id, newStatus)
    }
    statusMessage.value = `Status updated to ${newStatus}.`
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = 'Failed to update status.'
  }
}

onMounted(() => {
  fetchAll()
})
</script>

<template>
  <ThreeColumnLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col justify-between gap-4 border-b border-nexo-border pb-5 sm:flex-row sm:items-center">
        <div>
          <div class="flex items-center gap-2">
            <span class="badge-verified">STAGE 15</span>
            <span class="section-label">OPPORTUNITY & RISK INTELLIGENCE</span>
          </div>
          <h1 class="font-serif text-3xl font-bold tracking-tight text-nexo-ink mt-1">
            Opportunities & Risks
          </h1>
          <p class="mt-1 text-sm text-nexo-text-2">
            Evidence-backed detection of strategic growth openings and operational downside vectors.
          </p>
        </div>
        <div>
          <button @click="isCreateModalOpen = true" class="btn-ink flex items-center gap-2">
            <Plus class="h-4 w-4" />
            <span>Record Signal</span>
          </button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-4 border-b border-nexo-border">
        <button
          @click="activeTab = 'opportunities'"
          :class="[
            'pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2',
            activeTab === 'opportunities'
              ? 'border-nexo-verified text-nexo-verified'
              : 'border-transparent text-nexo-text-2 hover:text-nexo-ink'
          ]"
        >
          <TrendingUp class="h-4 w-4" />
          <span>Strategic Opportunities ({{ opportunities.length }})</span>
        </button>
        <button
          @click="activeTab = 'risks'"
          :class="[
            'pb-3 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2',
            activeTab === 'risks'
              ? 'border-nexo-breaking text-nexo-breaking'
              : 'border-transparent text-nexo-text-2 hover:text-nexo-ink'
          ]"
        >
          <AlertTriangle class="h-4 w-4" />
          <span>Priority Risks ({{ risks.length }})</span>
        </button>
      </div>

      <!-- Alerts -->
      <div v-if="statusMessage" class="rounded-[2px] border border-nexo-verified/40 bg-nexo-verified-tint/40 p-3 text-xs text-nexo-verified font-medium flex items-center justify-between">
        <span>{{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="hover:underline">Dismiss</button>
      </div>
      <div v-if="errorMessage" class="rounded-[2px] border border-nexo-breaking/30 bg-nexo-breaking-tint/30 p-3 text-xs text-nexo-breaking flex items-center justify-between">
        <span>{{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="hover:underline">Dismiss</button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="i in 4" :key="i" class="h-44 shimmer-bg rounded-[2px]" />
      </div>

      <!-- OPPORTUNITIES TAB -->
      <div v-else-if="activeTab === 'opportunities'" class="space-y-4">
        <div v-if="opportunities.length === 0" class="card-editorial p-12 text-center">
          <TrendingUp class="mx-auto h-12 w-12 text-nexo-muted/40" />
          <h3 class="mt-3 font-serif text-lg font-bold text-nexo-ink">No Opportunities Detected</h3>
          <p class="mt-1 text-xs text-nexo-text-2 max-w-md mx-auto">
            Log emerging market expansions, technology breakthroughs, or competitor vulnerabilities.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="opp in opportunities"
            :key="opp.id"
            class="card-editorial p-5 space-y-3"
          >
            <div class="flex items-center justify-between border-b border-nexo-border pb-2.5">
              <span class="badge-verified capitalize">{{ opp.opportunity_type.replace(/_/g, ' ') }}</span>
              <span class="text-xs font-mono text-nexo-muted">{{ opp.time_horizon }}</span>
            </div>
            <h3 class="font-serif text-base font-bold text-nexo-ink">{{ opp.title }}</h3>
            <p class="text-xs text-nexo-text-2 leading-relaxed">{{ opp.description }}</p>

            <div class="rounded-[2px] bg-nexo-surface-2 p-3 border border-nexo-border text-xs space-y-1">
              <div class="flex items-center justify-between text-[11px]">
                <span class="font-semibold text-nexo-ink">Likelihood Assessment:</span>
                <span class="font-mono text-nexo-verified font-bold">{{ Math.round((opp.likelihood_score || 0.6) * 100) }}%</span>
              </div>
              <p class="text-[10px] text-nexo-muted italic">Note: Transparent heuristic estimation; not statistically calibrated.</p>
            </div>

            <!-- Monitoring Checklist -->
            <div v-if="opp.recommended_monitoring_indicators?.length" class="space-y-1">
              <p class="section-label text-[9px]">RECOMMENDED WATCH POINTS</p>
              <ul class="text-xs text-nexo-text-2 space-y-1">
                <li v-for="(ind, idx) in opp.recommended_monitoring_indicators" :key="idx" class="flex items-start gap-1.5">
                  <CheckSquare class="h-3.5 w-3.5 text-nexo-cobalt shrink-0 mt-0.5" />
                  <span>{{ ind }}</span>
                </li>
              </ul>
            </div>

            <div class="flex items-center justify-between text-xs text-nexo-muted pt-3 border-t border-nexo-border">
              <span>Status: <strong class="text-nexo-ink capitalize">{{ opp.status }}</strong></span>
              <select
                :value="opp.status"
                @change="handleUpdateStatus(opp, ($event.target as HTMLSelectElement).value, 'opp')"
                class="rounded-[4px] border border-nexo-border p-1 text-[11px]"
              >
                <option value="detected">Detected</option>
                <option value="evaluating">Evaluating</option>
                <option value="pursuing">Pursuing</option>
                <option value="archived">Archived</option>
                <option value="dismissed">Dismissed</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- RISKS TAB -->
      <div v-else-if="activeTab === 'risks'" class="space-y-4">
        <div v-if="risks.length === 0" class="card-editorial p-12 text-center">
          <AlertTriangle class="mx-auto h-12 w-12 text-nexo-muted/40" />
          <h3 class="mt-3 font-serif text-lg font-bold text-nexo-ink">No Active Risks Detected</h3>
          <p class="mt-1 text-xs text-nexo-text-2 max-w-md mx-auto">
            Log supply chain bottlenecks, regulatory interventions, or geopolitical vulnerabilities.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="risk in risks"
            :key="risk.id"
            class="card-editorial p-5 space-y-3"
          >
            <div class="flex items-center justify-between border-b border-nexo-border pb-2.5">
              <span class="badge-breaking capitalize">{{ risk.severity.toUpperCase() }}</span>
              <span class="text-xs font-mono text-nexo-muted">{{ risk.time_horizon }}</span>
            </div>
            <h3 class="font-serif text-base font-bold text-nexo-ink">{{ risk.title }}</h3>
            <p class="text-xs text-nexo-text-2 leading-relaxed">{{ risk.description }}</p>

            <div class="rounded-[2px] bg-nexo-surface-2 p-3 border border-nexo-border text-xs space-y-1">
              <div class="flex items-center justify-between text-[11px]">
                <span class="font-semibold text-nexo-ink">Likelihood Estimate:</span>
                <span class="font-mono text-nexo-breaking font-bold">{{ Math.round((risk.likelihood_score || 0.7) * 100) }}%</span>
              </div>
              <p class="text-[10px] text-nexo-muted italic">Note: Transparent heuristic estimation; not statistically calibrated.</p>
            </div>

            <!-- Monitoring Checklist -->
            <div v-if="risk.recommended_monitoring_indicators?.length" class="space-y-1">
              <p class="section-label text-[9px]">RECOMMENDED WATCH POINTS</p>
              <ul class="text-xs text-nexo-text-2 space-y-1">
                <li v-for="(ind, idx) in risk.recommended_monitoring_indicators" :key="idx" class="flex items-start gap-1.5">
                  <CheckSquare class="h-3.5 w-3.5 text-nexo-breaking shrink-0 mt-0.5" />
                  <span>{{ ind }}</span>
                </li>
              </ul>
            </div>

            <div class="flex items-center justify-between text-xs text-nexo-muted pt-3 border-t border-nexo-border">
              <span>Status: <strong class="text-nexo-ink capitalize">{{ risk.status }}</strong></span>
              <select
                :value="risk.status"
                @change="handleUpdateStatus(risk, ($event.target as HTMLSelectElement).value, 'risk')"
                class="rounded-[4px] border border-nexo-border p-1 text-[11px]"
              >
                <option value="detected">Detected</option>
                <option value="escalating">Escalating</option>
                <option value="mitigating">Mitigating</option>
                <option value="resolved">Resolved</option>
                <option value="dismissed">Dismissed</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Modal -->
      <div v-if="isCreateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-[2px] bg-white p-6 shadow-xl border border-nexo-border space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Record Strategic Intelligence</h3>
            <button @click="isCreateModalOpen = false" class="text-nexo-muted hover:text-nexo-ink">
              <X class="h-4 w-4" />
            </button>
          </div>

          <form @submit.prevent="handleCreate" class="space-y-3.5 text-xs">
            <div>
              <label class="block font-medium text-nexo-ink mb-1">Intelligence Type</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  @click="createForm.type = 'opportunity'"
                  :class="[
                    'rounded-[4px] py-1.5 font-semibold text-xs border text-center transition',
                    createForm.type === 'opportunity' ? 'bg-nexo-verified text-white border-nexo-verified' : 'border-nexo-border bg-white text-nexo-text-2'
                  ]"
                >
                  Opportunity
                </button>
                <button
                  type="button"
                  @click="createForm.type = 'risk'"
                  :class="[
                    'rounded-[4px] py-1.5 font-semibold text-xs border text-center transition',
                    createForm.type === 'risk' ? 'bg-nexo-breaking text-white border-nexo-breaking' : 'border-nexo-border bg-white text-nexo-text-2'
                  ]"
                >
                  Risk Vector
                </button>
              </div>
            </div>

            <div>
              <label class="block font-medium text-nexo-ink mb-1">Title</label>
              <input
                v-model="createForm.title"
                type="text"
                required
                placeholder="e.g. Rare Earth Processing Export Restriction"
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              />
            </div>

            <div v-if="createForm.type === 'opportunity'">
              <label class="block font-medium text-nexo-ink mb-1">Opportunity Type</label>
              <select
                v-model="createForm.specific_type"
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              >
                <option value="emerging_markets">Emerging Markets</option>
                <option value="new_technologies">New Technologies</option>
                <option value="industry_expansion">Industry Expansion</option>
                <option value="competitor_weaknesses">Competitor Weaknesses</option>
                <option value="regulatory_openings">Regulatory Openings</option>
                <option value="supply_chain_alternatives">Supply Chain Alternatives</option>
              </select>
            </div>

            <div v-else>
              <label class="block font-medium text-nexo-ink mb-1">Risk Type & Severity</label>
              <div class="grid grid-cols-2 gap-2">
                <select
                  v-model="createForm.specific_type"
                  class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
                >
                  <option value="supply_chain_disruption">Supply Chain Disruption</option>
                  <option value="geopolitical_instability">Geopolitical Instability</option>
                  <option value="regulatory_changes">Regulatory Changes</option>
                  <option value="technology_obsolescence">Technology Obsolescence</option>
                  <option value="cybersecurity_developments">Cybersecurity</option>
                </select>
                <select
                  v-model="createForm.severity"
                  class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block font-medium text-nexo-ink mb-1">Description</label>
              <textarea
                v-model="createForm.description"
                rows="2"
                required
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              />
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-nexo-border">
              <button
                type="button"
                @click="isCreateModalOpen = false"
                class="rounded-[4px] border border-nexo-border px-3 py-1.5 font-medium text-nexo-ink hover:bg-nexo-paper-2"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="btn-ink"
              >
                <span>{{ isSubmitting ? 'Recording...' : 'Record Intelligence' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
