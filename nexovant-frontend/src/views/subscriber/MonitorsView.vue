<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import { workspaceService } from '@/services/api/workspace.service'
import { automationService, type EarlyWarning, type IntelligenceSignal } from '@/services/api/automation.service'
import {
  Bell,
  Plus,
  Play,
  Pause,
  Trash2,
  Sliders,
  Sparkles,
  ShieldCheck,
  CheckCircle2,
  Clock,
  Layers,
  Search,
  AlertTriangle,
  Radio,
  FileText,
  Activity,
  ChevronDown,
  ChevronUp,
  Check,
  X,
  AlertOctagon,
  ArrowUpRight
} from 'lucide-vue-next'

// Active tab
const activeTab = ref<'warnings' | 'signals' | 'monitors'>('warnings')

// Monitors state
interface MonitorRecord {
  id: string
  name: string
  keywords: string[]
  excluded_terms?: string[]
  entity_names?: string[]
  min_credibility_score: number
  require_verified_only: boolean
  alert_frequency: string
  delivery_channels?: string[]
  is_active: boolean
  match_count: number
  last_triggered_at?: string
  created_at: string
}

const monitors = ref<MonitorRecord[]>([])
const warnings = ref<EarlyWarning[]>([])
const signals = ref<IntelligenceSignal[]>([])

const isLoading = ref(true)
const isSubmitting = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

// Filter states
const selectedSeverity = ref<string>('all')
const expandedWarnings = ref<Record<string, boolean>>({})

// Transition modal state
const isTransitionModalOpen = ref(false)
const selectedWarningForTransition = ref<EarlyWarning | null>(null)
const targetTransitionStatus = ref<string>('')
const transitionReason = ref<string>('')
const isTransitioning = ref(false)

// Create Modal State
const isCreateModalOpen = ref(false)
const createForm = ref({
  name: '',
  keywordsRaw: '',
  excludedTermsRaw: '',
  entityNamesRaw: '',
  min_credibility_score: 0.75,
  require_verified_only: true,
  alert_frequency: 'real_time',
  delivery_in_app: true,
  delivery_email: true,
})

// Preview state
const isPreviewing = ref(false)
const previewMatches = ref<any[]>([])

const loadAllData = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const [monitorsRes, warningsRes, signalsRes] = await Promise.all([
      workspaceService.getMonitors(),
      automationService.getEarlyWarnings(),
      automationService.getSignals(),
    ])

    if (monitorsRes?.success) {
      monitors.value = monitorsRes.monitors || []
    }
    if (warningsRes?.success) {
      warnings.value = warningsRes.data || []
    }
    if (signalsRes?.success) {
      signals.value = signalsRes.data || []
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load intelligence monitor telemetry.'
  } finally {
    isLoading.value = false
  }
}

const toggleWarningExpanded = (id: string) => {
  expandedWarnings.value[id] = !expandedWarnings.value[id]
}

const openTransitionModal = (warning: EarlyWarning, targetStatus: string) => {
  selectedWarningForTransition.value = warning
  targetTransitionStatus.value = targetStatus
  transitionReason.value = ''
  isTransitionModalOpen.value = true
}

const handleConfirmTransition = async () => {
  if (!selectedWarningForTransition.value || !targetTransitionStatus.value) return
  isTransitioning.value = true
  try {
    const res = await automationService.updateWarningStatus(selectedWarningForTransition.value.id, {
      status: targetTransitionStatus.value,
      reason: transitionReason.value,
    })
    if (res.success) {
      statusMessage.value = `Warning transitioned to "${targetTransitionStatus.value}".`
      isTransitionModalOpen.value = false
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to transition early warning.'
  } finally {
    isTransitioning.value = false
  }
}

const handleToggleActive = async (id: string) => {
  try {
    const res = await workspaceService.toggleMonitor(id)
    if (res.success) {
      statusMessage.value = res.message || 'Monitor state updated.'
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to toggle monitor.'
  }
}

const handleDeleteMonitor = async (id: string) => {
  if (!confirm('Are you sure you want to delete this monitor rule?')) return
  try {
    const res = await workspaceService.deleteMonitor(id)
    if (res.success) {
      statusMessage.value = 'Monitor rule deleted.'
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete monitor.'
  }
}

const handlePreviewRule = async () => {
  const keywords = createForm.value.keywordsRaw.split(',').map(s => s.trim()).filter(Boolean)
  if (keywords.length === 0) {
    errorMessage.value = 'Please provide at least one keyword to test matching.'
    return
  }

  isPreviewing.value = true
  errorMessage.value = ''
  try {
    const res = await workspaceService.previewMonitorMatches({
      keywords,
      excluded_terms: createForm.value.excludedTermsRaw.split(',').map(s => s.trim()).filter(Boolean),
      entity_names: createForm.value.entityNamesRaw.split(',').map(s => s.trim()).filter(Boolean),
      min_credibility_score: createForm.value.min_credibility_score,
      require_verified_only: createForm.value.require_verified_only,
    })
    if (res.success) {
      previewMatches.value = res.matches || []
      statusMessage.value = `Rule evaluated: ${previewMatches.value.length} existing articles match these criteria.`
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Preview matching failed.'
  } finally {
    isPreviewing.value = false
  }
}

const handleCreateMonitor = async () => {
  const keywords = createForm.value.keywordsRaw.split(',').map(s => s.trim()).filter(Boolean)
  if (keywords.length === 0) {
    errorMessage.value = 'At least one keyword is required.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const channels = []
    if (createForm.value.delivery_in_app) channels.push('in_app')
    if (createForm.value.delivery_email) channels.push('email')

    const res = await workspaceService.createMonitor({
      name: createForm.value.name,
      keywords,
      excluded_terms: createForm.value.excludedTermsRaw.split(',').map(s => s.trim()).filter(Boolean),
      entity_names: createForm.value.entityNamesRaw.split(',').map(s => s.trim()).filter(Boolean),
      min_credibility_score: createForm.value.min_credibility_score,
      require_verified_only: createForm.value.require_verified_only,
      alert_frequency: createForm.value.alert_frequency,
      delivery_channels: channels,
      is_active: true,
    })

    if (res.success) {
      statusMessage.value = `Monitor "${res.monitor.name}" activated.`
      isCreateModalOpen.value = false
      previewMatches.value = []
      createForm.value = {
        name: '',
        keywordsRaw: '',
        excludedTermsRaw: '',
        entityNamesRaw: '',
        min_credibility_score: 0.75,
        require_verified_only: true,
        alert_frequency: 'real_time',
        delivery_in_app: true,
        delivery_email: true,
      }
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.name?.[0] || 'Failed to activate monitor.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  loadAllData()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Header (per Mockup P22 Monitors.png) -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-[11px] font-mono text-[#17794D] uppercase">
            <span>Predictive Intelligence</span>
            <span>/</span>
            <span>Automated Surveillance & Early Warning</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Early Warning & Automated Monitors
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875]">
            Continuous multi-source surveillance, transparent 9-point early warnings, anomaly tracking, and custom rules.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="activeTab === 'monitors'"
            type="button"
            class="btn-ink text-xs px-4 py-2 flex items-center gap-1.5"
            @click="isCreateModalOpen = true"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>New Monitor Rule</span>
          </button>
          <button
            type="button"
            class="px-3.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
            @click="loadAllData"
            :disabled="isLoading"
          >
            <Activity class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
            <span>Refresh</span>
          </button>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="flex border-b border-[#D0D9CF] space-x-6 text-xs sm:text-sm font-medium">
        <button
          @click="activeTab = 'warnings'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'warnings' ? 'border-[#0E1A2B] text-[#0E1A2B] font-semibold' : 'border-transparent text-[#5F6875] hover:text-[#0E1A2B]'"
        >
          <AlertTriangle class="w-4 h-4 text-[#8A5A08]" />
          <span>Early Warnings</span>
          <span class="ml-1 px-1.5 py-0.5 rounded-[2px] text-[10px] bg-[#F6E7C8] text-[#8A5A08] font-mono font-bold">
            {{ warnings.length }}
          </span>
        </button>
        <button
          @click="activeTab = 'signals'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'signals' ? 'border-[#0E1A2B] text-[#0E1A2B] font-semibold' : 'border-transparent text-[#5F6875] hover:text-[#0E1A2B]'"
        >
          <Radio class="w-4 h-4 text-[#274888]" />
          <span>Detected Signals</span>
          <span class="ml-1 px-1.5 py-0.5 rounded-[2px] text-[10px] bg-[#E4E9F7] text-[#274888] font-mono font-bold">
            {{ signals.length }}
          </span>
        </button>
        <button
          @click="activeTab = 'monitors'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'monitors' ? 'border-[#0E1A2B] text-[#0E1A2B] font-semibold' : 'border-transparent text-[#5F6875] hover:text-[#0E1A2B]'"
        >
          <Bell class="w-4 h-4 text-[#17794D]" />
          <span>Custom Monitor Rules</span>
          <span class="ml-1 px-1.5 py-0.5 rounded-[2px] text-[10px] bg-[#EEEBE4] text-[#0E1A2B] font-mono font-bold">
            {{ monitors.length }}
          </span>
        </button>
      </div>

      <!-- Status & Error Alerts -->
      <div v-if="statusMessage" class="p-3 bg-[#E3F1EA] border border-[#17794D]/30 text-[#17794D] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✓ {{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="text-[#17794D] font-bold ml-2">✕</button>
      </div>

      <div v-if="errorMessage" class="p-3 bg-[#FBE4E1] border border-[#B3261E]/30 text-[#B3261E] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✕ {{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="text-[#B3261E] font-bold ml-2">✕</button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-xs text-[#5F6875] py-12 text-center">
        Surveilling intelligence feeds and evaluating early warnings...
      </div>

      <!-- TAB 1: EARLY WARNINGS -->
      <div v-else-if="activeTab === 'warnings'" class="space-y-4">
        <div v-if="warnings.length === 0" class="card-editorial p-12 text-center space-y-2">
          <ShieldCheck class="w-10 h-10 text-[#17794D] mx-auto" />
          <h3 class="font-serif font-medium text-[#0E1A2B] text-base">All Monitored Trajectories Normal</h3>
          <p class="text-xs text-[#5F6875] max-w-md mx-auto">
            No anomalies or critical early warnings exceed the surveillance threshold in the current operational window.
          </p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="w in warnings"
            :key="w.id"
            class="card-editorial p-5 sm:p-6 space-y-4 transition-colors"
            :class="w.warning_level === 'critical' ? 'border-l-4 border-l-[#B3261E]' : ''"
          >
            <!-- Card Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#D0D9CF]/60 pb-3">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase tracking-wider"
                  :class="w.warning_level === 'critical' ? 'badge-breaking' : 'badge-developing'"
                >
                  Level: {{ w.warning_level }}
                </span>
                <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono uppercase bg-[#EEEBE4] text-[#3F4A5A]">
                  Status: {{ w.status.replace('_', ' ') }}
                </span>
                <span v-if="w.organization_id" class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono uppercase badge-sponsored">
                  Tenant Private
                </span>
              </div>
              <div class="text-[11px] font-mono text-[#5F6875]">
                Detected: {{ new Date(w.created_at).toLocaleString() }}
              </div>
            </div>

            <!-- Title & Key Takeaway -->
            <div>
              <h3 class="editorial-headline text-lg sm:text-xl font-medium text-[#0E1A2B]">
                {{ w.title }}
              </h3>
              <p class="text-xs text-[#3F4A5A] mt-1 leading-relaxed">
                {{ w.explanation.what_changed }}
              </p>
            </div>

            <!-- Factor Breakdown Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-[#F8FAF7] p-3 rounded-[2px] border border-[#D0D9CF] text-xs">
              <div>
                <div class="text-[10px] font-mono text-[#5F6875] uppercase">Independent Sources</div>
                <div class="font-bold text-[#0E1A2B]">{{ w.factor_breakdown.independent_sources }} verifiable outlets</div>
              </div>
              <div>
                <div class="text-[10px] font-mono text-[#5F6875] uppercase">Source Credibility</div>
                <div class="font-bold text-[#0E1A2B]">{{ Math.round(w.factor_breakdown.credibility_score * 100) }}%</div>
              </div>
              <div>
                <div class="text-[10px] font-mono text-[#5F6875] uppercase">Baseline Deviation</div>
                <div class="font-bold text-[#0E1A2B] capitalize">{{ w.factor_breakdown.baseline_deviation.replace('_', ' ') }}</div>
              </div>
              <div>
                <div class="text-[10px] font-mono text-[#5F6875] uppercase">Potential Impact</div>
                <div class="font-bold text-[#0E1A2B] capitalize">{{ w.factor_breakdown.potential_impact }}</div>
              </div>
            </div>

            <!-- 9-Point Transparent Explanation Toggle -->
            <div>
              <button
                @click="toggleWarningExpanded(w.id)"
                class="flex items-center gap-1.5 text-xs font-mono font-bold text-[#274888] hover:underline transition-colors"
              >
                <component :is="expandedWarnings[w.id] ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                <span>{{ expandedWarnings[w.id] ? 'Hide Transparent 9-Point Explanation' : 'Inspect 9-Point Methodology & Verified Evidence' }}</span>
              </button>

              <div v-if="expandedWarnings[w.id]" class="mt-3 p-4 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] space-y-3 text-xs">
                <div class="font-mono text-[11px] font-bold text-[#0E1A2B] uppercase border-b border-[#D0D9CF] pb-1">
                  Transparent Early-Warning Audit Breakdown
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <div class="font-bold text-[#0E1A2B]">1. What Changed:</div>
                    <div class="text-[#3F4A5A] mt-0.5">{{ w.explanation.what_changed }}</div>
                  </div>
                  <div>
                    <div class="font-bold text-[#0E1A2B]">2. When First Observed:</div>
                    <div class="text-[#3F4A5A] mt-0.5">{{ w.explanation.when_first_observed }}</div>
                  </div>
                  <div>
                    <div class="font-bold text-[#0E1A2B]">3. Supporting Sources:</div>
                    <div class="text-[#3F4A5A] mt-0.5">{{ w.explanation.supporting_sources.join(', ') }} ({{ w.explanation.independent_sources_count }} independent)</div>
                  </div>
                  <div>
                    <div class="font-bold text-[#0E1A2B]">4. What Makes It Unusual:</div>
                    <div class="text-[#3F4A5A] mt-0.5">{{ w.explanation.what_makes_it_unusual }}</div>
                  </div>
                  <div>
                    <div class="font-bold text-[#0E1A2B]">5. What Is Verified:</div>
                    <div class="text-[#17794D] font-medium mt-0.5">{{ w.explanation.what_is_verified }}</div>
                  </div>
                  <div>
                    <div class="font-bold text-[#0E1A2B]">6. What Remains Uncertain:</div>
                    <div class="text-[#8A5A08] font-medium mt-0.5">{{ w.explanation.what_remains_uncertain }}</div>
                  </div>
                  <div>
                    <div class="font-bold text-[#0E1A2B]">7. Why Considered Important:</div>
                    <div class="text-[#3F4A5A] mt-0.5">{{ w.explanation.why_considered_important }}</div>
                  </div>
                  <div>
                    <div class="font-bold text-[#0E1A2B]">8. Additional Info Needed:</div>
                    <div class="text-[#3F4A5A] mt-0.5">{{ w.explanation.additional_info_needed }}</div>
                  </div>
                </div>

                <div v-if="w.recommended_watch_points && w.recommended_watch_points.length > 0" class="pt-2 border-t border-[#D0D9CF]">
                  <div class="font-bold text-[#0E1A2B]">9. Recommended Watch Points:</div>
                  <ul class="list-disc pl-4 text-[#3F4A5A] mt-1 space-y-0.5">
                    <li v-for="(wp, i) in w.recommended_watch_points" :key="i">{{ wp }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Lifecycle Transition Actions -->
            <div class="flex items-center justify-between pt-2 border-t border-[#D0D9CF]/60">
              <div class="text-[11px] text-[#5F6875]">
                Current Status: <span class="font-bold uppercase text-[#0E1A2B]">{{ w.status.replace('_', ' ') }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <button
                  v-if="w.status === 'newly_detected'"
                  type="button"
                  class="px-2.5 py-1 text-xs rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] font-semibold"
                  @click="openTransitionModal(w, 'under_review')"
                >
                  Acknowledge
                </button>
                <button
                  v-if="w.status !== 'escalated'"
                  type="button"
                  class="px-2.5 py-1 text-xs rounded-[4px] border border-[#B7791F]/30 bg-[#F6E7C8]/40 hover:bg-[#F6E7C8] text-[#8A5A08] font-semibold"
                  @click="openTransitionModal(w, 'escalated')"
                >
                  Escalate
                </button>
                <button
                  v-if="w.status !== 'resolved'"
                  type="button"
                  class="px-2.5 py-1 text-xs rounded-[4px] border border-[#17794D]/30 bg-[#E3F1EA] hover:bg-[#17794D]/20 text-[#17794D] font-semibold"
                  @click="openTransitionModal(w, 'resolved')"
                >
                  Resolve
                </button>
                <button
                  v-if="w.status !== 'dismissed'"
                  type="button"
                  class="px-2.5 py-1 text-xs rounded-[4px] text-[#5F6875] hover:text-[#0E1A2B] hover:bg-[#EEEBE4]"
                  @click="openTransitionModal(w, 'dismissed')"
                >
                  Dismiss
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: DETECTED SIGNALS -->
      <div v-else-if="activeTab === 'signals'" class="space-y-4">
        <div v-if="signals.length === 0" class="card-editorial p-12 text-center space-y-2">
          <Radio class="w-8 h-8 text-[#5F6875] mx-auto" />
          <h3 class="font-serif font-medium text-[#0E1A2B] text-base">No Intelligence Signals Detected</h3>
          <p class="text-xs text-[#5F6875] max-w-md mx-auto">
            Automated surveillance rules have not flagged any anomalous signals within the past monitoring window.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="s in signals"
            :key="s.id"
            class="card-editorial p-5 space-y-3 flex flex-col justify-between"
          >
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <span
                  class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase tracking-wider"
                  :class="s.severity === 'critical' ? 'badge-breaking' : s.severity === 'high' ? 'badge-developing' : 'badge-verified'"
                >
                  {{ s.severity }}
                </span>
                <span class="text-[10px] font-mono text-[#5F6875] uppercase">
                  {{ s.signal_category.replace(/_/g, ' ') }}
                </span>
              </div>

              <h4 class="editorial-headline text-base font-medium text-[#0E1A2B] leading-snug">
                {{ s.title }}
              </h4>

              <p class="text-xs text-[#3F4A5A] line-clamp-3 leading-relaxed">
                {{ s.description }}
              </p>

              <!-- Evidences & Entities -->
              <div class="pt-2 border-t border-[#D0D9CF]/60 flex flex-wrap gap-1.5 items-center">
                <span
                  v-for="entity in s.related_entities"
                  :key="entity.id"
                  class="px-2 py-0.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] text-[10px] text-[#0E1A2B] font-mono"
                >
                  {{ entity.name }}
                </span>
                <span
                  v-for="topic in s.related_topics"
                  :key="topic"
                  class="px-2 py-0.5 rounded-[2px] bg-[#E4E9F7] text-[#274888] text-[10px] font-mono"
                >
                  {{ topic }}
                </span>
              </div>
            </div>

            <div class="pt-2 border-t border-[#D0D9CF]/60 text-[11px] font-mono text-[#5F6875] flex items-center justify-between">
              <span>{{ s.independent_sources_count }} independent sources</span>
              <span>{{ new Date(s.first_observed_at).toLocaleDateString() }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: CUSTOM MONITORS -->
      <div v-else class="space-y-4">
        <div v-if="monitors.length === 0" class="card-editorial p-12 text-center space-y-2">
          <Bell class="w-8 h-8 text-[#5F6875] mx-auto" />
          <h3 class="font-serif font-medium text-[#0E1A2B] text-base">No Custom Monitors Active</h3>
          <p class="text-xs text-[#5F6875] max-w-md mx-auto">
            Set up autonomous rules to automatically alert you when verified developments matching your critical parameters break.
          </p>
          <button
            type="button"
            class="btn-ink text-xs px-4 py-2 mt-2"
            @click="isCreateModalOpen = true"
          >
            Create Monitor Rule
          </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="m in monitors"
            :key="m.id"
            class="card-editorial p-5 space-y-4 flex flex-col justify-between transition-colors"
            :class="m.is_active ? '' : 'opacity-70 bg-[#F8FAF7] border-dashed'"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span
                  class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase"
                  :class="m.is_active ? 'badge-verified' : 'bg-[#EEEBE4] text-[#5F6875]'"
                >
                  {{ m.is_active ? 'Active Daemon' : 'Paused' }}
                </span>
                <span class="text-[10px] font-mono text-[#5F6875] uppercase">
                  {{ m.alert_frequency.replace('_', ' ') }}
                </span>
              </div>

              <div>
                <h3 class="editorial-headline text-base font-medium text-[#0E1A2B]">
                  {{ m.name }}
                </h3>
                <div class="flex flex-wrap gap-1 mt-2">
                  <span
                    v-for="(kw, idx) in m.keywords"
                    :key="idx"
                    class="px-2 py-0.5 bg-[#E4E9F7] text-[#274888] text-[10px] rounded-[2px] font-mono"
                  >
                    + {{ kw }}
                  </span>
                  <span
                    v-for="(ex, idx) in (m.excluded_terms || [])"
                    :key="'ex-' + idx"
                    class="px-2 py-0.5 bg-[#FBE4E1] text-[#B3261E] text-[10px] rounded-[2px] font-mono"
                  >
                    - {{ ex }}
                  </span>
                </div>
              </div>

              <div class="space-y-1.5 text-xs text-[#5F6875] pt-2 border-t border-[#D0D9CF]/60">
                <div class="flex items-center justify-between">
                  <span>Min Credibility:</span>
                  <span class="font-mono font-bold text-[#0E1A2B]">{{ Math.round(m.min_credibility_score * 100) }}%</span>
                </div>
                <div class="flex items-center justify-between">
                  <span>Verified Only:</span>
                  <span class="font-mono font-bold" :class="m.require_verified_only ? 'text-[#17794D]' : 'text-[#5F6875]'">
                    {{ m.require_verified_only ? 'Yes' : 'No' }}
                  </span>
                </div>
              </div>
            </div>

            <div class="pt-3 border-t border-[#D0D9CF]/60 flex items-center justify-between">
              <div class="text-[10px] font-mono text-[#5F6875]">
                Matched: <strong class="text-[#0E1A2B]">{{ m.match_count }}</strong>
              </div>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="p-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] transition-colors"
                  @click="handleToggleActive(m.id)"
                  :title="m.is_active ? 'Pause Monitor' : 'Resume Monitor'"
                >
                  <component :is="m.is_active ? Pause : Play" class="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  class="p-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#FBE4E1] text-[#5F6875] hover:text-[#B3261E] transition-colors"
                  @click="handleDeleteMonitor(m.id)"
                  title="Delete Monitor"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TRANSITION STATUS MODAL -->
      <Modal
        :isOpen="isTransitionModalOpen"
        title="Transition Early Warning Status"
        @close="isTransitionModalOpen = false"
      >
        <div v-if="selectedWarningForTransition" class="space-y-4 text-left">
          <div class="p-3 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] text-xs">
            <div class="font-bold text-[#0E1A2B]">{{ selectedWarningForTransition.title }}</div>
            <div class="text-[#5F6875] font-mono mt-1">Current Status: {{ selectedWarningForTransition.status }}</div>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Target Status</label>
            <select
              v-model="targetTransitionStatus"
              class="w-full text-xs p-2.5 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B] font-medium"
            >
              <option value="under_review">Under Review</option>
              <option value="escalated">Escalated</option>
              <option value="resolved">Resolved</option>
              <option value="dismissed">Dismissed</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Transition Reason / Analyst Notes</label>
            <textarea
              v-model="transitionReason"
              rows="3"
              class="w-full text-xs p-2.5 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B]"
              placeholder="Explain the justification for updating the lifecycle state..."
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#D0D9CF]">
            <button
              type="button"
              class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
              @click="isTransitionModalOpen = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn-ink text-xs px-4 py-1.5"
              @click="handleConfirmTransition"
              :disabled="isTransitioning"
            >
              Confirm Transition
            </button>
          </div>
        </div>
      </Modal>

      <!-- CREATE MONITOR MODAL -->
      <Modal
        :isOpen="isCreateModalOpen"
        title="Create Custom Intelligence Monitor"
        @close="isCreateModalOpen = false"
      >
        <div class="space-y-4 text-left">
          <div>
            <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Monitor Name</label>
            <input
              v-model="createForm.name"
              placeholder="e.g. EUV High-NA Packaging Bottlenecks"
              class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Include Keywords (comma-separated)</label>
            <input
              v-model="createForm.keywordsRaw"
              placeholder="High-NA, ASML, 2nm, optics"
              class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Exclude Keywords (comma-separated)</label>
            <input
              v-model="createForm.excludedTermsRaw"
              placeholder="rumors, speculative, leak"
              class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Min Credibility</label>
              <select
                v-model="createForm.min_credibility_score"
                class="w-full text-xs p-2.5 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B]"
              >
                <option :value="0.90">90% — Tier 1 Only</option>
                <option :value="0.75">75% — Authoritative</option>
                <option :value="0.60">60% — Broad Coverage</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Alert Frequency</label>
              <select
                v-model="createForm.alert_frequency"
                class="w-full text-xs p-2.5 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B]"
              >
                <option value="real_time">Real Time</option>
                <option value="daily_digest">Daily Digest</option>
                <option value="weekly_digest">Weekly Digest</option>
              </select>
            </div>
          </div>

          <div class="flex items-center gap-4 text-xs">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="createForm.require_verified_only" />
              <span>Require Multi-Source Verification</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="createForm.delivery_in_app" />
              <span>In-App Alert</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="createForm.delivery_email" />
              <span>Email Digest</span>
            </label>
          </div>

          <div class="flex items-center justify-between pt-3 border-t border-[#D0D9CF]">
            <button
              type="button"
              class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
              @click="handlePreviewRule"
              :disabled="isPreviewing"
            >
              Test Rule Match
            </button>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
                @click="isCreateModalOpen = false"
              >
                Cancel
              </button>
              <button
                type="button"
                class="btn-ink text-xs px-4 py-1.5"
                @click="handleCreateMonitor"
                :disabled="isSubmitting"
              >
                Activate Monitor
              </button>
            </div>
          </div>
        </div>
      </Modal>
    </div>
  </AuthenticatedLayout>
</template>
