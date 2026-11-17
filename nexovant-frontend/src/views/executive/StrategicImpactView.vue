<script setup lang="ts">
import { ref, onMounted } from 'vue'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import {
  executiveService,
  type StrategicImpactAssessment,
} from '@/services/api/executive.service'
import {
  Layers,
  Plus,
  ShieldAlert,
  Clock,
  ArrowUpRight,
  TrendingDown,
  TrendingUp,
  Info,
  X,
} from 'lucide-vue-next'

const dimensions = [
  { id: '', label: 'All Dimensions' },
  { id: 'financial_exposure', label: 'Financial Exposure' },
  { id: 'operational_disruption', label: 'Operational Disruption' },
  { id: 'supply_chain_impact', label: 'Supply Chain Impact' },
  { id: 'regulatory_compliance', label: 'Regulatory Compliance' },
  { id: 'competitive_positioning', label: 'Competitive Positioning' },
  { id: 'customer_market_demand', label: 'Customer Demand' },
  { id: 'technology_disruption', label: 'Technology Disruption' },
  { id: 'reputation_perception', label: 'Reputation Perception' },
  { id: 'geopolitical_exposure', label: 'Geopolitical Exposure' },
  { id: 'strategic_opportunity', label: 'Strategic Opportunity' },
]

const selectedDimension = ref('')
const assessments = ref<StrategicImpactAssessment[]>([])
const isLoading = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const statusMessage = ref('')

const isCreateModalOpen = ref(false)
const createForm = ref({
  title: '',
  dimension: 'supply_chain_impact',
  affected_industry_or_market: 'Semiconductor Equipment',
  affected_entities_raw: 'ASML, TSMC, Intel',
  potential_direction: 'negative',
  estimated_magnitude: 'moderate',
  time_horizon: 'near_term_90d',
  evidence_excerpt: 'Supply chain inventory backlog extended to 18 weeks.',
})

const fetchAssessments = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const params: Record<string, any> = {}
    if (selectedDimension.value) {
      params.dimension = selectedDimension.value
    }
    const res = await executiveService.getStrategicImpacts(params)
    assessments.value = res.data || []
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load strategic impact assessments.'
  } finally {
    isLoading.value = false
  }
}

const handleAssess = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const entities = createForm.value.affected_entities_raw
      .split(',')
      .map(s => s.trim())
      .filter(Boolean)

    await executiveService.assessStrategicImpact({
      title: createForm.value.title,
      dimension: createForm.value.dimension,
      affected_industry_or_market: createForm.value.affected_industry_or_market,
      affected_entities: entities,
      potential_direction: createForm.value.potential_direction,
      estimated_magnitude: createForm.value.estimated_magnitude,
      time_horizon: createForm.value.time_horizon,
      supporting_evidence: [
        { source: 'Executive Analyst Synthesis', excerpt: createForm.value.evidence_excerpt, credibility: 0.85 },
      ],
    })

    statusMessage.value = 'Strategic impact assessment completed.'
    isCreateModalOpen.value = false
    await fetchAssessments()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to evaluate strategic impact.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  fetchAssessments()
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
            <span class="section-label">STRATEGIC IMPACT ANALYSIS</span>
          </div>
          <h1 class="font-serif text-3xl font-bold tracking-tight text-nexo-ink mt-1">
            Strategic Impact Analysis
          </h1>
          <p class="mt-1 text-sm text-nexo-text-2">
            Multi-dimensional evaluation of how intelligence developments affect business continuity and market positioning.
          </p>
        </div>
        <div>
          <button @click="isCreateModalOpen = true" class="btn-ink flex items-center gap-2">
            <Plus class="h-4 w-4" />
            <span>Assess Impact</span>
          </button>
        </div>
      </div>

      <!-- Public Intelligence Notice Banner -->
      <div class="rounded-[2px] border border-nexo-cobalt/30 bg-nexo-cobalt-tint/30 p-3.5 text-xs text-nexo-text flex items-start gap-2.5">
        <Info class="h-4 w-4 text-nexo-cobalt shrink-0 mt-0.5" />
        <div class="leading-relaxed">
          <strong>Methodology Disclosure:</strong> Strategic impact assessments correlate public intelligence disclosures, trade filings, and verified signals. Where private internal financials are unlinked, assessments strictly disclose public origin rather than fabricating synthetic balance sheets.
        </div>
      </div>

      <!-- Dimension Filter Pills -->
      <div class="flex flex-wrap items-center gap-2 border-b border-nexo-border pb-3">
        <button
          v-for="d in dimensions"
          :key="d.id"
          @click="selectedDimension = d.id; fetchAssessments()"
          :class="[
            'rounded-[4px] px-3 py-1.5 text-xs font-semibold transition-colors',
            selectedDimension === d.id
              ? 'bg-nexo-ink text-white'
              : 'border border-nexo-border bg-white text-nexo-text-2 hover:bg-nexo-paper-2'
          ]"
        >
          {{ d.label }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="i in 4" :key="i" class="h-48 shimmer-bg rounded-[2px]" />
      </div>

      <!-- Empty State -->
      <div v-else-if="assessments.length === 0" class="card-editorial p-12 text-center">
        <Layers class="mx-auto h-12 w-12 text-nexo-muted/40" />
        <h3 class="mt-3 font-serif text-lg font-bold text-nexo-ink">No Assessments in Selected Dimension</h3>
        <p class="mt-1 text-xs text-nexo-text-2 max-w-md mx-auto">
          Evaluate how critical supply chain, regulatory, or operational signals affect your enterprise.
        </p>
        <button @click="isCreateModalOpen = true" class="btn-ink mt-4">
          Run Impact Assessment
        </button>
      </div>

      <!-- Assessment Cards Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="imp in assessments"
          :key="imp.id"
          class="card-editorial p-5 space-y-3"
        >
          <div class="flex items-center justify-between border-b border-nexo-border pb-2.5">
            <span class="section-label text-[10px] text-nexo-cobalt">
              {{ imp.dimension.replace(/_/g, ' ') }}
            </span>
            <div class="flex items-center gap-1.5">
              <span :class="imp.potential_direction === 'negative' ? 'badge-breaking' : 'badge-verified'">
                {{ imp.potential_direction.toUpperCase() }}
              </span>
              <span class="badge-developing">{{ imp.estimated_magnitude.toUpperCase() }}</span>
            </div>
          </div>

          <h3 class="font-serif text-lg font-bold text-nexo-ink">{{ imp.title }}</h3>

          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="entity in imp.affected_entities || []"
              :key="entity"
              class="rounded-full bg-nexo-paper-2 px-2 py-0.5 text-[10px] font-medium text-nexo-ink border border-nexo-border"
            >
              {{ entity }}
            </span>
          </div>

          <p class="text-xs text-nexo-text-2 leading-relaxed">
            <strong>Timeframe:</strong> {{ imp.time_horizon.replace(/_/g, ' ') }} ·
            <strong>Industry:</strong> {{ imp.affected_industry_or_market || 'Cross-Sector' }}
          </p>

          <!-- Supporting Evidence Snippets -->
          <div v-if="imp.supporting_evidence?.length" class="rounded-[2px] bg-nexo-surface-2 p-3 border border-nexo-border text-xs space-y-1">
            <p class="section-label text-[9px]">GROUNDED EVIDENCE</p>
            <p class="text-nexo-text-2 italic line-clamp-2">"{{ imp.supporting_evidence[0].excerpt }}"</p>
            <p class="text-[10px] text-nexo-muted font-medium">— {{ imp.supporting_evidence[0].source }}</p>
          </div>

          <div class="text-[11px] text-nexo-muted pt-2 border-t border-nexo-border flex items-center justify-between">
            <span>Confidence: <strong class="text-nexo-ink capitalize">{{ imp.confidence_level }}</strong></span>
            <span>{{ new Date(imp.created_at).toLocaleDateString() }}</span>
          </div>
        </div>
      </div>

      <!-- Create Modal -->
      <div v-if="isCreateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-[2px] bg-white p-6 shadow-xl border border-nexo-border space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Assess Strategic Impact</h3>
            <button @click="isCreateModalOpen = false" class="text-nexo-muted hover:text-nexo-ink">
              <X class="h-4 w-4" />
            </button>
          </div>

          <form @submit.prevent="handleAssess" class="space-y-3.5 text-xs">
            <div>
              <label class="block font-medium text-nexo-ink mb-1">Assessment Title</label>
              <input
                v-model="createForm.title"
                type="text"
                required
                placeholder="e.g. Rare Earth Export Restrictions Impact"
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              />
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-medium text-nexo-ink mb-1">Dimension</label>
                <select
                  v-model="createForm.dimension"
                  class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
                >
                  <option v-for="d in dimensions.slice(1)" :key="d.id" :value="d.id">{{ d.label }}</option>
                </select>
              </div>
              <div>
                <label class="block font-medium text-nexo-ink mb-1">Direction</label>
                <select
                  v-model="createForm.potential_direction"
                  class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
                >
                  <option value="negative">Negative Exposure</option>
                  <option value="positive">Positive Opportunity</option>
                  <option value="neutral">Neutral Shift</option>
                  <option value="bifurcated">Bifurcated Impact</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-medium text-nexo-ink mb-1">Estimated Magnitude</label>
                <select
                  v-model="createForm.estimated_magnitude"
                  class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
                >
                  <option value="low">Low</option>
                  <option value="moderate">Moderate</option>
                  <option value="severe">Severe</option>
                  <option value="transformative">Transformative</option>
                </select>
              </div>
              <div>
                <label class="block font-medium text-nexo-ink mb-1">Time Horizon</label>
                <select
                  v-model="createForm.time_horizon"
                  class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
                >
                  <option value="immediate_30d">Immediate (0–30 Days)</option>
                  <option value="near_term_90d">Near-Term (30–90 Days)</option>
                  <option value="medium_term_12m">Medium-Term (12 Months)</option>
                  <option value="long_term_24m_plus">Long-Term (24+ Months)</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block font-medium text-nexo-ink mb-1">Affected Entities (Comma-separated)</label>
              <input
                v-model="createForm.affected_entities_raw"
                type="text"
                placeholder="e.g. ASML, TSMC, Intel"
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              />
            </div>

            <div>
              <label class="block font-medium text-nexo-ink mb-1">Primary Evidence Excerpt</label>
              <textarea
                v-model="createForm.evidence_excerpt"
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
                <span>{{ isSubmitting ? 'Evaluating...' : 'Complete Assessment' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
