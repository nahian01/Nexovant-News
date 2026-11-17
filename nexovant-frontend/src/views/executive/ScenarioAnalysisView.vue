<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import {
  executiveService,
  type IntelligenceScenario,
} from '@/services/api/executive.service'
import {
  Layers,
  Plus,
  Sparkles,
  Copy,
  Archive,
  CheckCircle,
  AlertTriangle,
  TrendingUp,
  TrendingDown,
  Compass,
  ArrowRight,
  X,
  Eye,
  Columns,
} from 'lucide-vue-next'

const scenarios = ref<IntelligenceScenario[]>([])
const selectedScenario = ref<IntelligenceScenario | null>(null)
const comparedScenarios = ref<IntelligenceScenario[]>([])
const comparisonData = ref<any>(null)
const isLoading = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const statusMessage = ref('')

const filterType = ref<string>('all')
const filterSubject = ref<string>('')
const isGenerateSuiteModalOpen = ref(false)
const suiteSubject = ref('')

const isCreateModalOpen = ref(false)
const createForm = ref({
  title: '',
  subject: '',
  scenario_type: 'baseline' as const,
  description: '',
  initial_conditions: '',
  time_horizon: 'medium_term_12m',
  uncertainty_rating: 'medium' as const,
  key_assumptions: '',
  trigger_events: '',
  risks: '',
  opportunities: '',
})

const isCompareModalOpen = ref(false)
const selectedScenarioIdsForCompare = ref<string[]>([])

const fetchScenarios = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const params: Record<string, any> = {}
    if (filterType.value !== 'all') params.scenario_type = filterType.value
    if (filterSubject.value) params.subject = filterSubject.value
    const res = await executiveService.getScenarios(params)
    scenarios.value = res.data || []
    if (scenarios.value.length > 0 && !selectedScenario.value) {
      selectedScenario.value = scenarios.value[0]
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load scenarios.'
  } finally {
    isLoading.value = false
  }
}

const handleGenerateSuite = async () => {
  if (!suiteSubject.value.trim()) return
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const newScenarios = await executiveService.generateScenarioSuite(suiteSubject.value.trim())
    statusMessage.value = `Successfully synthesized triad of scenarios (Optimistic, Baseline, Adverse).`
    isGenerateSuiteModalOpen.value = false
    suiteSubject.value = ''
    await fetchScenarios()
    if (newScenarios.length > 0) {
      selectedScenario.value = newScenarios[0]
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to generate scenario suite.'
  } finally {
    isSubmitting.value = false
  }
}

const handleCreateScenario = async () => {
  if (!createForm.value.title || !createForm.value.subject || !createForm.value.description) {
    errorMessage.value = 'Please provide title, subject, and narrative description.'
    return
  }
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const payload = {
      ...createForm.value,
      key_assumptions: createForm.value.key_assumptions.split('\n').filter(s => s.trim().length > 0),
      trigger_events: createForm.value.trigger_events.split('\n').filter(s => s.trim().length > 0),
      risks: createForm.value.risks.split('\n').filter(s => s.trim().length > 0),
      opportunities: createForm.value.opportunities.split('\n').filter(s => s.trim().length > 0),
    }
    const created = await executiveService.createScenario(payload)
    statusMessage.value = 'Custom strategic scenario registered.'
    isCreateModalOpen.value = false
    await fetchScenarios()
    selectedScenario.value = created
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create scenario.'
  } finally {
    isSubmitting.value = false
  }
}

const handleDuplicate = async (scenario: IntelligenceScenario) => {
  try {
    const duplicated = await executiveService.duplicateScenario(scenario.id)
    statusMessage.value = `Duplicated as custom scenario: "${duplicated.title}".`
    await fetchScenarios()
    selectedScenario.value = duplicated
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to duplicate scenario.'
  }
}

const handleArchive = async (scenario: IntelligenceScenario) => {
  try {
    await executiveService.archiveScenario(scenario.id)
    statusMessage.value = `Scenario "${scenario.title}" archived.`
    await fetchScenarios()
    if (selectedScenario.value?.id === scenario.id) {
      selectedScenario.value = scenarios.value[0] || null
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to archive scenario.'
  }
}

const toggleCompareSelect = (id: string) => {
  const index = selectedScenarioIdsForCompare.value.indexOf(id)
  if (index > -1) {
    selectedScenarioIdsForCompare.value.splice(index, 1)
  } else {
    if (selectedScenarioIdsForCompare.value.length >= 4) {
      errorMessage.value = 'Maximum 4 scenarios can be compared side-by-side.'
      return
    }
    selectedScenarioIdsForCompare.value.push(id)
  }
}

const openComparisonMatrix = async () => {
  if (selectedScenarioIdsForCompare.value.length < 2) {
    errorMessage.value = 'Select at least 2 scenarios to compare.'
    return
  }
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const data = await executiveService.compareScenarios(selectedScenarioIdsForCompare.value)
    comparisonData.value = data
    comparedScenarios.value = data.scenarios || []
    isCompareModalOpen.value = true
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to generate comparison matrix.'
  } finally {
    isSubmitting.value = false
  }
}

const filteredScenarios = computed(() => {
  return scenarios.value.filter(s => {
    if (filterType.value !== 'all' && s.scenario_type !== filterType.value) return false
    if (filterSubject.value && !s.subject.toLowerCase().includes(filterSubject.value.toLowerCase())) return false
    return true
  })
})

const getScenarioTypeBadge = (type: string) => {
  switch (type) {
    case 'optimistic':
      return 'bg-emerald-50 text-emerald-800 border-emerald-200'
    case 'baseline':
      return 'bg-blue-50 text-blue-800 border-blue-200'
    case 'adverse':
      return 'bg-rose-50 text-rose-800 border-rose-200'
    default:
      return 'bg-purple-50 text-purple-800 border-purple-200'
  }
}

const getUncertaintyBadge = (rating: string) => {
  switch (rating) {
    case 'low':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'medium':
      return 'bg-amber-50 text-amber-700 border-amber-200'
    case 'high':
      return 'bg-orange-50 text-orange-700 border-orange-200'
    case 'extreme':
      return 'bg-red-50 text-red-700 border-red-200'
    default:
      return 'bg-nexo-paper-2 text-nexo-ink border-nexo-border'
  }
}

onMounted(() => {
  fetchScenarios()
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
            <span class="section-label">SCENARIO ANALYSIS ENGINE</span>
          </div>
          <h1 class="font-serif text-3xl font-bold tracking-tight text-nexo-ink mt-1">
            Strategic Scenario Modeling
          </h1>
          <p class="text-xs text-nexo-text-2 mt-1">
            Evidence-backed scenario triads (Optimistic, Baseline, Adverse) with explicit trigger events and sensitivity conditions.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="selectedScenarioIdsForCompare.length >= 2"
            @click="openComparisonMatrix"
            class="inline-flex items-center gap-1.5 rounded-[4px] border border-nexo-cobalt bg-nexo-cobalt/10 px-3 py-1.5 text-xs font-semibold text-nexo-cobalt transition hover:bg-nexo-cobalt/20"
          >
            <Columns class="h-3.5 w-3.5" />
            Compare Selected ({{ selectedScenarioIdsForCompare.length }})
          </button>
          <button
            @click="isGenerateSuiteModalOpen = true"
            class="inline-flex items-center gap-1.5 rounded-[4px] border border-nexo-border bg-white px-3 py-1.5 text-xs font-semibold text-nexo-ink transition hover:bg-nexo-paper-2"
          >
            <Sparkles class="h-3.5 w-3.5 text-nexo-brass" />
            Synthesize Scenario Triad
          </button>
          <button
            @click="isCreateModalOpen = true"
            class="inline-flex items-center gap-1.5 rounded-[4px] bg-nexo-ink px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#1A2C42]"
          >
            <Plus class="h-3.5 w-3.5" />
            New Custom Scenario
          </button>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="errorMessage" class="rounded-[2px] border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 flex justify-between items-center">
        <span>{{ errorMessage }}</span>
        <button @click="errorMessage = ''"><X class="h-4 w-4" /></button>
      </div>
      <div v-if="statusMessage" class="rounded-[2px] border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 flex justify-between items-center">
        <span>{{ statusMessage }}</span>
        <button @click="statusMessage = ''"><X class="h-4 w-4" /></button>
      </div>

      <!-- Filters & Matrix Selector Bar -->
      <div class="flex flex-col gap-3 rounded-[4px] border border-nexo-border bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-xs font-semibold text-nexo-muted uppercase tracking-wider">Scenario Type:</span>
          <button
            v-for="t in ['all', 'optimistic', 'baseline', 'adverse', 'alternative']"
            :key="t"
            @click="filterType = t"
            :class="[
              'rounded-[2px] px-2 py-1 text-xs capitalize transition',
              filterType === t
                ? 'bg-nexo-ink font-semibold text-white'
                : 'text-nexo-text-2 hover:bg-nexo-paper-2'
            ]"
          >
            {{ t }}
          </button>
        </div>

        <div class="flex items-center gap-2">
          <input
            v-model="filterSubject"
            type="text"
            placeholder="Filter by subject or topic..."
            class="rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1 text-xs text-nexo-ink placeholder-nexo-muted focus:border-nexo-cobalt focus:outline-none"
          />
        </div>
      </div>

      <!-- Content Split Grid -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- Scenario List Column -->
        <div class="space-y-3 lg:col-span-5">
          <div v-if="isLoading" class="py-12 text-center text-xs text-nexo-muted">
            Evaluating strategic scenario matrix...
          </div>
          <div v-else-if="filteredScenarios.length === 0" class="rounded-[4px] border border-dashed border-nexo-border bg-white p-8 text-center text-xs text-nexo-muted">
            No scenarios found matching current filters.
          </div>
          <div
            v-for="s in filteredScenarios"
            :key="s.id"
            @click="selectedScenario = s"
            :class="[
              'group cursor-pointer rounded-[4px] border bg-white p-4 transition',
              selectedScenario?.id === s.id
                ? 'border-nexo-cobalt ring-1 ring-nexo-cobalt shadow-sm'
                : 'border-nexo-border hover:border-nexo-border-dark'
            ]"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span :class="['rounded-[2px] border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider', getScenarioTypeBadge(s.scenario_type)]">
                  {{ s.scenario_type }}
                </span>
                <span :class="['rounded-[2px] border px-1.5 py-0.5 text-[10px] font-medium capitalize', getUncertaintyBadge(s.uncertainty_rating)]">
                  {{ s.uncertainty_rating }} uncertainty
                </span>
                <span v-if="s.is_custom" class="rounded-[2px] border border-purple-200 bg-purple-50 px-1.5 py-0.5 text-[10px] font-medium text-purple-700">
                  Custom
                </span>
              </div>
              <input
                type="checkbox"
                :checked="selectedScenarioIdsForCompare.includes(s.id)"
                @click.stop="toggleCompareSelect(s.id)"
                title="Select for comparison matrix"
                class="rounded text-nexo-cobalt focus:ring-0"
              />
            </div>

            <h3 class="font-serif text-base font-bold text-nexo-ink mt-2 group-hover:text-nexo-cobalt">
              {{ s.title }}
            </h3>
            <p class="text-xs font-medium text-nexo-text-2 mt-0.5">
              Subject: {{ s.subject }}
            </p>
            <p class="text-xs text-nexo-muted mt-2 line-clamp-2">
              {{ s.description }}
            </p>

            <div class="mt-3 flex items-center justify-between border-t border-nexo-border/60 pt-2 text-[11px] text-nexo-muted">
              <span>Horizon: {{ s.time_horizon?.replace(/_/g, ' ') }}</span>
              <div class="flex items-center gap-2">
                <button
                  @click.stop="handleDuplicate(s)"
                  class="hover:text-nexo-ink transition"
                  title="Branch scenario"
                >
                  <Copy class="h-3.5 w-3.5" />
                </button>
                <button
                  @click.stop="handleArchive(s)"
                  class="hover:text-rose-600 transition"
                  title="Archive scenario"
                >
                  <Archive class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Selected Scenario Detail Column -->
        <div class="lg:col-span-7">
          <div v-if="selectedScenario" class="rounded-[4px] border border-nexo-border bg-white p-6 space-y-6">
            <!-- Title Bar -->
            <div class="border-b border-nexo-border pb-4">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['rounded-[2px] border px-2 py-0.5 text-xs font-bold uppercase', getScenarioTypeBadge(selectedScenario.scenario_type)]">
                  {{ selectedScenario.scenario_type }}
                </span>
                <span :class="['rounded-[2px] border px-2 py-0.5 text-xs font-medium capitalize', getUncertaintyBadge(selectedScenario.uncertainty_rating)]">
                  Uncertainty: {{ selectedScenario.uncertainty_rating }}
                </span>
                <span class="text-xs text-nexo-muted">
                  Horizon: {{ selectedScenario.time_horizon?.replace(/_/g, ' ') }}
                </span>
              </div>
              <h2 class="font-serif text-2xl font-bold text-nexo-ink mt-2">
                {{ selectedScenario.title }}
              </h2>
              <p class="text-xs font-semibold text-nexo-cobalt mt-1">
                Strategic Subject: {{ selectedScenario.subject }}
              </p>
            </div>

            <!-- Narrative Description -->
            <div>
              <h4 class="section-label mb-2">SCENARIO NARRATIVE & DEVELOPMENT PATH</h4>
              <p class="text-xs text-nexo-ink leading-relaxed whitespace-pre-line bg-nexo-bg/60 p-3 rounded-[2px] border border-nexo-border">
                {{ selectedScenario.description }}
              </p>
            </div>

            <!-- Initial Conditions & Assumptions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="rounded-[2px] border border-nexo-border p-3 bg-white">
                <h4 class="section-label mb-1.5">INITIAL CONDITIONS</h4>
                <p class="text-xs text-nexo-text-2 leading-relaxed">
                  {{ selectedScenario.initial_conditions || 'Derived from current baseline intelligence environment.' }}
                </p>
              </div>

              <div class="rounded-[2px] border border-nexo-border p-3 bg-white">
                <h4 class="section-label mb-1.5">KEY ASSUMPTIONS</h4>
                <ul v-if="selectedScenario.key_assumptions && selectedScenario.key_assumptions.length > 0" class="list-disc list-inside space-y-1 text-xs text-nexo-text-2">
                  <li v-for="(assump, i) in selectedScenario.key_assumptions" :key="i">
                    {{ assump }}
                  </li>
                </ul>
                <p v-else class="text-xs text-nexo-muted italic">No explicit assumptions recorded.</p>
              </div>
            </div>

            <!-- Trigger Events & Watch Points -->
            <div>
              <h4 class="section-label mb-2">TRIGGER EVENTS & SENSITIVITY CONDITIONS</h4>
              <div v-if="selectedScenario.trigger_events && selectedScenario.trigger_events.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div
                  v-for="(trig, i) in selectedScenario.trigger_events"
                  :key="i"
                  class="flex items-start gap-2 rounded-[2px] border border-amber-200 bg-amber-50/50 p-2 text-xs text-amber-900"
                >
                  <AlertTriangle class="h-3.5 w-3.5 shrink-0 text-amber-600 mt-0.5" />
                  <span>{{ trig }}</span>
                </div>
              </div>
              <p v-else class="text-xs text-nexo-muted italic">None specified.</p>
            </div>

            <!-- Opportunities & Risks under this scenario -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="rounded-[2px] border border-emerald-200 bg-emerald-50/30 p-3">
                <div class="flex items-center gap-1.5 mb-2">
                  <TrendingUp class="h-3.5 w-3.5 text-emerald-700" />
                  <h4 class="section-label text-emerald-900">EMERGING OPPORTUNITIES</h4>
                </div>
                <ul v-if="selectedScenario.opportunities && selectedScenario.opportunities.length > 0" class="list-disc list-inside space-y-1 text-xs text-emerald-950">
                  <li v-for="(opp, i) in selectedScenario.opportunities" :key="i">{{ opp }}</li>
                </ul>
                <p v-else class="text-xs text-nexo-muted italic">No opportunities defined.</p>
              </div>

              <div class="rounded-[2px] border border-rose-200 bg-rose-50/30 p-3">
                <div class="flex items-center gap-1.5 mb-2">
                  <TrendingDown class="h-3.5 w-3.5 text-rose-700" />
                  <h4 class="section-label text-rose-900">DOWNSIDE RISKS</h4>
                </div>
                <ul v-if="selectedScenario.risks && selectedScenario.risks.length > 0" class="list-disc list-inside space-y-1 text-xs text-rose-950">
                  <li v-for="(r, i) in selectedScenario.risks" :key="i">{{ r }}</li>
                </ul>
                <p v-else class="text-xs text-nexo-muted italic">No downside risks defined.</p>
              </div>
            </div>

            <!-- Monitoring Indicators -->
            <div>
              <h4 class="section-label mb-2">EARLY-WARNING MONITORING INDICATORS</h4>
              <div v-if="selectedScenario.indicators_to_monitor && selectedScenario.indicators_to_monitor.length > 0" class="flex flex-wrap gap-1.5">
                <span
                  v-for="(ind, i) in selectedScenario.indicators_to_monitor"
                  :key="i"
                  class="rounded-[2px] border border-nexo-border bg-nexo-paper-2 px-2.5 py-1 text-xs text-nexo-ink"
                >
                  {{ ind }}
                </span>
              </div>
              <p v-else class="text-xs text-nexo-muted italic">Continuous automated monitors active.</p>
            </div>
          </div>
          <div v-else class="rounded-[4px] border border-dashed border-nexo-border bg-white p-12 text-center text-xs text-nexo-muted">
            Select a scenario to view comprehensive narrative, trigger events, and monitoring indicators.
          </div>
        </div>
      </div>

      <!-- Synthesize Triad Modal -->
      <div v-if="isGenerateSuiteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-[4px] border border-nexo-border bg-white p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <div class="flex items-center gap-2">
              <Sparkles class="h-4 w-4 text-nexo-brass" />
              <h3 class="font-serif text-lg font-bold text-nexo-ink">Synthesize Scenario Triad</h3>
            </div>
            <button @click="isGenerateSuiteModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <p class="text-xs text-nexo-text-2 leading-relaxed">
            Generate an evidence-backed triad consisting of <strong>Optimistic</strong>, <strong>Baseline</strong>, and <strong>Adverse</strong> scenarios rooted in recent intelligence and verified facts.
          </p>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Strategic Subject / Policy / Market Question</label>
            <input
              v-model="suiteSubject"
              type="text"
              placeholder="e.g. Global Semiconductor Subsidies & Supply Chain Re-shoring 2026-2028"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-3 py-2 text-xs text-nexo-ink focus:border-nexo-cobalt focus:outline-none"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="isGenerateSuiteModalOpen = false"
              class="rounded-[2px] border border-nexo-border px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2"
            >
              Cancel
            </button>
            <button
              @click="handleGenerateSuite"
              :disabled="isSubmitting || !suiteSubject.trim()"
              class="rounded-[2px] bg-nexo-ink px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#1A2C42] disabled:opacity-50"
            >
              {{ isSubmitting ? 'Synthesizing...' : 'Generate Triad' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Create Custom Scenario Modal -->
      <div v-if="isCreateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
        <div class="w-full max-w-2xl rounded-[4px] border border-nexo-border bg-white p-6 shadow-xl space-y-4 my-8">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Define Custom Scenario</h3>
            <button @click="isCreateModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Scenario Title</label>
              <input
                v-model="createForm.title"
                type="text"
                placeholder="e.g. Accelerated Multilateral Subsidy Accord"
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Subject</label>
              <input
                v-model="createForm.subject"
                type="text"
                placeholder="e.g. Semiconductor Trade Policy"
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Scenario Type</label>
              <select v-model="createForm.scenario_type" class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink">
                <option value="optimistic">Optimistic</option>
                <option value="baseline">Baseline</option>
                <option value="adverse">Adverse</option>
                <option value="alternative">Alternative</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Horizon</label>
              <select v-model="createForm.time_horizon" class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink">
                <option value="immediate_30d">Immediate (30 days)</option>
                <option value="near_term_90d">Near-term (90 days)</option>
                <option value="medium_term_12m">Medium-term (12 months)</option>
                <option value="long_term_24m_plus">Long-term (24+ months)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Uncertainty Rating</label>
              <select v-model="createForm.uncertainty_rating" class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink">
                <option value="low">Low Uncertainty</option>
                <option value="medium">Medium Uncertainty</option>
                <option value="high">High Uncertainty</option>
                <option value="extreme">Extreme Uncertainty</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Narrative Description</label>
            <textarea
              v-model="createForm.description"
              rows="3"
              placeholder="Detailed logical flow of events under this scenario..."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            ></textarea>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Key Assumptions (one per line)</label>
              <textarea
                v-model="createForm.key_assumptions"
                rows="2"
                placeholder="Key geopolitical and market prerequisites..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Trigger Events (one per line)</label>
              <textarea
                v-model="createForm.trigger_events"
                rows="2"
                placeholder="Specific signals indicating transition to this branch..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Opportunities (one per line)</label>
              <textarea
                v-model="createForm.opportunities"
                rows="2"
                placeholder="Market and operational upsides..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Risks (one per line)</label>
              <textarea
                v-model="createForm.risks"
                rows="2"
                placeholder="Operational and market threats..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="isCreateModalOpen = false"
              class="rounded-[2px] border border-nexo-border px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2"
            >
              Cancel
            </button>
            <button
              @click="handleCreateScenario"
              :disabled="isSubmitting"
              class="rounded-[2px] bg-nexo-ink px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#1A2C42] disabled:opacity-50"
            >
              {{ isSubmitting ? 'Saving...' : 'Create Scenario' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Side-by-Side Comparison Matrix Modal -->
      <div v-if="isCompareModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="w-full max-w-5xl rounded-[4px] border border-nexo-border bg-white p-6 shadow-2xl space-y-4 my-8">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <div class="flex items-center gap-2">
              <Columns class="h-4 w-4 text-nexo-cobalt" />
              <h3 class="font-serif text-lg font-bold text-nexo-ink">Strategic Scenario Comparison Matrix</h3>
            </div>
            <button @click="isCompareModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
              <thead>
                <tr class="border-b border-nexo-border bg-nexo-paper-2">
                  <th class="p-3 font-semibold text-nexo-ink w-40">Dimension</th>
                  <th v-for="s in comparedScenarios" :key="s.id" class="p-3 font-semibold text-nexo-ink min-w-[220px]">
                    <div class="flex items-center gap-1.5">
                      <span :class="['rounded-[2px] border px-1.5 py-0.2 text-[9px] font-bold uppercase', getScenarioTypeBadge(s.scenario_type)]">
                        {{ s.scenario_type }}
                      </span>
                      <span>{{ s.title }}</span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border text-nexo-text-2">
                <tr>
                  <td class="p-3 font-semibold text-nexo-ink">Horizon & Uncertainty</td>
                  <td v-for="s in comparedScenarios" :key="s.id" class="p-3">
                    <span :class="['rounded-[2px] border px-1.5 py-0.5 text-[10px] capitalize font-medium', getUncertaintyBadge(s.uncertainty_rating)]">
                      {{ s.uncertainty_rating }} uncertainty
                    </span>
                    <div class="text-[11px] text-nexo-muted mt-1">{{ s.time_horizon?.replace(/_/g, ' ') }}</div>
                  </td>
                </tr>
                <tr>
                  <td class="p-3 font-semibold text-nexo-ink">Core Narrative</td>
                  <td v-for="s in comparedScenarios" :key="s.id" class="p-3 text-[11px] leading-relaxed">
                    {{ s.description }}
                  </td>
                </tr>
                <tr>
                  <td class="p-3 font-semibold text-nexo-ink">Key Triggers</td>
                  <td v-for="s in comparedScenarios" :key="s.id" class="p-3">
                    <ul v-if="s.trigger_events && s.trigger_events.length > 0" class="list-disc list-inside space-y-0.5 text-[11px]">
                      <li v-for="(t, i) in s.trigger_events" :key="i">{{ t }}</li>
                    </ul>
                    <span v-else class="text-nexo-muted italic text-[11px]">None</span>
                  </td>
                </tr>
                <tr>
                  <td class="p-3 font-semibold text-nexo-ink">Opportunities</td>
                  <td v-for="s in comparedScenarios" :key="s.id" class="p-3 text-emerald-900">
                    <ul v-if="s.opportunities && s.opportunities.length > 0" class="list-disc list-inside space-y-0.5 text-[11px]">
                      <li v-for="(opp, i) in s.opportunities" :key="i">{{ opp }}</li>
                    </ul>
                    <span v-else class="text-nexo-muted italic text-[11px]">None</span>
                  </td>
                </tr>
                <tr>
                  <td class="p-3 font-semibold text-nexo-ink">Risks</td>
                  <td v-for="s in comparedScenarios" :key="s.id" class="p-3 text-rose-900">
                    <ul v-if="s.risks && s.risks.length > 0" class="list-disc list-inside space-y-0.5 text-[11px]">
                      <li v-for="(r, i) in s.risks" :key="i">{{ r }}</li>
                    </ul>
                    <span v-else class="text-nexo-muted italic text-[11px]">None</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-end pt-2">
            <button
              @click="isCompareModalOpen = false"
              class="rounded-[2px] bg-nexo-ink px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#1A2C42]"
            >
              Close Matrix
            </button>
          </div>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
