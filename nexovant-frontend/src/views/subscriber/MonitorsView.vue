<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'
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
      automationService.getSignals()
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
    errorMessage.value = err.response?.data?.message || 'Failed to load monitoring data.'
  } finally {
    isLoading.value = false
  }
}

const toggleWarningExpanded = (id: string) => {
  expandedWarnings.value[id] = !expandedWarnings.value[id]
}

const openTransitionModal = (warning: EarlyWarning, newStatus: string) => {
  selectedWarningForTransition.value = warning
  targetTransitionStatus.value = newStatus
  transitionReason.value = `Analyst transition to ${newStatus} based on evaluated developments.`
  isTransitionModalOpen.value = true
}

const handleConfirmTransition = async () => {
  if (!selectedWarningForTransition.value) return
  isTransitioning.value = true
  errorMessage.value = ''
  try {
    const res = await automationService.updateWarningStatus(
      selectedWarningForTransition.value.id,
      {
        status: targetTransitionStatus.value,
        reason: transitionReason.value
      }
    )
    if (res.success) {
      statusMessage.value = `Warning transitioned to ${targetTransitionStatus.value}.`
      isTransitionModalOpen.value = false
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update warning status.'
  } finally {
    isTransitioning.value = false
  }
}

const handleToggleActive = async (id: string) => {
  try {
    const res = await workspaceService.toggleMonitor(id)
    if (res.success) {
      statusMessage.value = res.message
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to toggle monitor.'
  }
}

const handleDeleteMonitor = async (id: string) => {
  if (!confirm('Are you sure you want to delete this custom monitor?')) return
  try {
    const res = await workspaceService.deleteMonitor(id)
    if (res.success) {
      statusMessage.value = 'Monitor deleted.'
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete monitor.'
  }
}

const handlePreviewRule = async () => {
  const keywords = createForm.value.keywordsRaw.split(',').map(s => s.trim()).filter(Boolean)
  if (keywords.length === 0) {
    errorMessage.value = 'Provide at least one keyword to test matching.'
    return
  }

  isPreviewing.value = true
  errorMessage.value = ''
  try {
    const res = await workspaceService.previewMonitorMatches({
      keywords,
      excluded_terms: createForm.value.excludedTermsRaw.split(',').map(s => s.trim()).filter(Boolean),
      entity_names: createForm.value.entityNamesRaw.split(',').map(s => s.trim()).filter(Boolean),
      require_verified_only: createForm.value.require_verified_only,
    })
    if (res.success) {
      previewMatches.value = res.matches
      statusMessage.value = `Rule evaluated: ${res.match_count} recent articles match this condition.`
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

const getWarningLevelBadge = (level: string) => {
  switch (level) {
    case 'critical':
      return 'bg-red-500/10 text-red-700 border-red-300 dark:border-red-800'
    case 'elevated':
      return 'bg-amber-500/10 text-amber-700 border-amber-300 dark:border-amber-800'
    case 'watch':
      return 'bg-blue-500/10 text-blue-700 border-blue-300 dark:border-blue-800'
    default:
      return 'bg-slate-500/10 text-slate-700 border-slate-300 dark:border-slate-800'
  }
}

const getSignalSeverityBadge = (sev: string) => {
  switch (sev) {
    case 'critical':
      return 'bg-red-600 text-white'
    case 'high':
      return 'bg-amber-600 text-white'
    case 'medium':
      return 'bg-blue-600 text-white'
    default:
      return 'bg-slate-600 text-white'
  }
}

onMounted(() => {
  loadAllData()
})
</script>

<template>
  <div class="flex h-screen bg-nexo-bg font-sans antialiased text-nexo-text-primary overflow-hidden">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Predictive Intelligence</span>
              <span>/</span>
              <span>Automated Monitoring & Early Warning</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
              Early Warning & Automated Monitors
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Continuous multi-source surveillance, transparent 9-point early warnings, anomaly tracking, and custom rules.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button
              v-if="activeTab === 'monitors'"
              variant="primary"
              size="sm"
              @click="isCreateModalOpen = true"
            >
              <Plus class="w-3.5 h-3.5 mr-1" /> New Monitor Rule
            </Button>
            <Button
              variant="secondary"
              size="sm"
              @click="loadAllData"
              :disabled="isLoading"
            >
              <Activity class="w-3.5 h-3.5 mr-1" :class="{ 'animate-spin': isLoading }" /> Refresh
            </Button>
          </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex border-b border-nexo-border space-x-6 text-sm font-medium">
          <button
            @click="activeTab = 'warnings'"
            class="pb-3 border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'warnings' ? 'border-amber-600 text-amber-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
          >
            <AlertTriangle class="w-4 h-4" />
            Early Warnings
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-800 font-mono">
              {{ warnings.length }}
            </span>
          </button>
          <button
            @click="activeTab = 'signals'"
            class="pb-3 border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'signals' ? 'border-cyan-600 text-cyan-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
          >
            <Radio class="w-4 h-4" />
            Detected Signals
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-cyan-100 text-cyan-800 font-mono">
              {{ signals.length }}
            </span>
          </button>
          <button
            @click="activeTab = 'monitors'"
            class="pb-3 border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'monitors' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
          >
            <Bell class="w-4 h-4" />
            Custom Monitor Rules
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-800 font-mono">
              {{ monitors.length }}
            </span>
          </button>
        </div>

        <!-- Status & Error Alerts -->
        <div v-if="statusMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✓ {{ statusMessage }}</span>
          <button @click="statusMessage = ''" class="text-emerald-600 font-bold ml-2">✕</button>
        </div>

        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✕ {{ errorMessage }}</span>
          <button @click="errorMessage = ''" class="text-rose-600 font-bold ml-2">✕</button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-xs text-nexo-text-muted py-12 text-center">
          Surveilling intelligence feeds and evaluating early warnings...
        </div>

        <!-- TAB 1: EARLY WARNINGS -->
        <div v-else-if="activeTab === 'warnings'" class="space-y-4">
          <div v-if="warnings.length === 0" class="p-12 bg-white rounded-lg border border-nexo-border text-center space-y-2">
            <ShieldCheck class="w-10 h-10 text-emerald-500 mx-auto" />
            <h3 class="font-serif font-bold text-nexo-text-primary text-base">All Monitored Trajectories Normal</h3>
            <p class="text-xs text-nexo-text-secondary max-w-md mx-auto">
              No anomalies or critical early warnings exceed the surveillance threshold in the current operational window.
            </p>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="w in warnings"
              :key="w.id"
              class="bg-white border rounded-lg p-5 shadow-xs transition-all space-y-4"
              :class="w.warning_level === 'critical' ? 'border-red-300 ring-1 ring-red-100' : 'border-nexo-border'"
            >
              <!-- Card Header -->
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-nexo-border/40 pb-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <span
                    class="px-2.5 py-1 rounded-sm text-[11px] font-mono font-bold uppercase tracking-wider border"
                    :class="getWarningLevelBadge(w.warning_level)"
                  >
                    Level: {{ w.warning_level }}
                  </span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-slate-100 text-slate-700">
                    Status: {{ w.status.replace('_', ' ') }}
                  </span>
                  <span v-if="w.organization_id" class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-purple-50 text-purple-700 border border-purple-200">
                    Tenant Private
                  </span>
                </div>
                <div class="text-[11px] font-mono text-nexo-text-secondary">
                  Detected: {{ new Date(w.created_at).toLocaleString() }}
                </div>
              </div>

              <!-- Title & Key Takeaway -->
              <div>
                <h3 class="text-base sm:text-lg font-serif font-bold text-nexo-text-primary">
                  {{ w.title }}
                </h3>
                <p class="text-xs text-nexo-text-secondary mt-1">
                  {{ w.explanation.what_changed }}
                </p>
              </div>

              <!-- Factor Breakdown Grid -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-slate-50/80 p-3 rounded border border-slate-200/60 text-xs">
                <div>
                  <div class="text-[10px] font-mono text-slate-500 uppercase">Independent Sources</div>
                  <div class="font-bold text-slate-800">{{ w.factor_breakdown.independent_sources }} verifiable outlets</div>
                </div>
                <div>
                  <div class="text-[10px] font-mono text-slate-500 uppercase">Source Credibility</div>
                  <div class="font-bold text-slate-800">{{ Math.round(w.factor_breakdown.credibility_score * 100) }}%</div>
                </div>
                <div>
                  <div class="text-[10px] font-mono text-slate-500 uppercase">Baseline Deviation</div>
                  <div class="font-bold text-slate-800 capitalize">{{ w.factor_breakdown.baseline_deviation.replace('_', ' ') }}</div>
                </div>
                <div>
                  <div class="text-[10px] font-mono text-slate-500 uppercase">Potential Impact</div>
                  <div class="font-bold text-slate-800 capitalize">{{ w.factor_breakdown.potential_impact }}</div>
                </div>
              </div>

              <!-- 9-Point Transparent Explanation Toggle -->
              <div>
                <button
                  @click="toggleWarningExpanded(w.id)"
                  class="flex items-center gap-1.5 text-xs font-mono font-bold text-blue-600 hover:text-blue-800 transition-colors"
                >
                  <component :is="expandedWarnings[w.id] ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                  {{ expandedWarnings[w.id] ? 'Hide Transparent 9-Point Explanation' : 'Inspect 9-Point Methodology & Verified Evidence' }}
                </button>

                <div v-if="expandedWarnings[w.id]" class="mt-3 p-4 bg-slate-50 rounded border border-slate-200 space-y-3 text-xs">
                  <div class="font-mono text-[11px] font-bold text-slate-700 uppercase border-b border-slate-200 pb-1">
                    Transparent Early-Warning Audit Breakdown
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <div class="font-bold text-slate-700">1. What Changed:</div>
                      <div class="text-slate-600 mt-0.5">{{ w.explanation.what_changed }}</div>
                    </div>
                    <div>
                      <div class="font-bold text-slate-700">2. When First Observed:</div>
                      <div class="text-slate-600 mt-0.5">{{ w.explanation.when_first_observed }}</div>
                    </div>
                    <div>
                      <div class="font-bold text-slate-700">3. Supporting Sources:</div>
                      <div class="text-slate-600 mt-0.5">{{ w.explanation.supporting_sources.join(', ') }} ({{ w.explanation.independent_sources_count }} independent)</div>
                    </div>
                    <div>
                      <div class="font-bold text-slate-700">4. What Makes It Unusual:</div>
                      <div class="text-slate-600 mt-0.5">{{ w.explanation.what_makes_it_unusual }}</div>
                    </div>
                    <div>
                      <div class="font-bold text-slate-700">5. What Is Verified:</div>
                      <div class="text-slate-600 mt-0.5 text-emerald-800">{{ w.explanation.what_is_verified }}</div>
                    </div>
                    <div>
                      <div class="font-bold text-slate-700">6. What Remains Uncertain:</div>
                      <div class="text-slate-600 mt-0.5 text-amber-800">{{ w.explanation.what_remains_uncertain }}</div>
                    </div>
                    <div>
                      <div class="font-bold text-slate-700">7. Why Considered Important:</div>
                      <div class="text-slate-600 mt-0.5">{{ w.explanation.why_considered_important }}</div>
                    </div>
                    <div>
                      <div class="font-bold text-slate-700">8. Additional Info Needed:</div>
                      <div class="text-slate-600 mt-0.5">{{ w.explanation.additional_info_needed }}</div>
                    </div>
                  </div>

                  <div v-if="w.recommended_watch_points && w.recommended_watch_points.length > 0" class="pt-2 border-t border-slate-200">
                    <div class="font-bold text-slate-700">9. Recommended Watch Points:</div>
                    <ul class="list-disc pl-4 text-slate-600 mt-1 space-y-0.5">
                      <li v-for="(wp, i) in w.recommended_watch_points" :key="i">{{ wp }}</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Lifecycle Transition Actions -->
              <div class="flex items-center justify-between pt-2 border-t border-nexo-border/30">
                <div class="text-[11px] text-nexo-text-secondary">
                  Current Status: <span class="font-bold uppercase">{{ w.status.replace('_', ' ') }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <Button
                    v-if="w.status === 'newly_detected'"
                    size="xs"
                    variant="secondary"
                    @click="openTransitionModal(w, 'under_review')"
                  >
                    Acknowledge
                  </Button>
                  <Button
                    v-if="w.status !== 'escalated'"
                    size="xs"
                    variant="outline"
                    class="text-amber-700 hover:bg-amber-50"
                    @click="openTransitionModal(w, 'escalated')"
                  >
                    Escalate
                  </Button>
                  <Button
                    v-if="w.status !== 'resolved'"
                    size="xs"
                    variant="outline"
                    class="text-emerald-700 hover:bg-emerald-50"
                    @click="openTransitionModal(w, 'resolved')"
                  >
                    Resolve
                  </Button>
                  <Button
                    v-if="w.status !== 'dismissed'"
                    size="xs"
                    variant="ghost"
                    class="text-slate-500 hover:bg-slate-100"
                    @click="openTransitionModal(w, 'dismissed')"
                  >
                    Dismiss
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: DETECTED SIGNALS -->
        <div v-else-if="activeTab === 'signals'" class="space-y-4">
          <div v-if="signals.length === 0" class="p-12 bg-white rounded-lg border border-nexo-border text-center space-y-2">
            <Radio class="w-10 h-10 text-slate-300 mx-auto" />
            <h3 class="font-serif font-bold text-nexo-text-primary text-base">No Intelligence Signals Detected</h3>
            <p class="text-xs text-nexo-text-secondary max-w-md mx-auto">
              Automated ingestion rules have not flagged any anomalous signals within the past surveillance window.
            </p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Card
              v-for="s in signals"
              :key="s.id"
              class="p-5 bg-white border border-nexo-border shadow-xs space-y-3 flex flex-col justify-between"
            >
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider"
                    :class="getSignalSeverityBadge(s.severity)"
                  >
                    {{ s.severity }}
                  </span>
                  <span class="text-[10px] font-mono text-slate-500 uppercase">
                    {{ s.signal_category.replace(/_/g, ' ') }}
                  </span>
                </div>

                <h4 class="font-serif font-bold text-nexo-text-primary text-base leading-snug">
                  {{ s.title }}
                </h4>

                <p class="text-xs text-nexo-text-secondary line-clamp-3">
                  {{ s.description }}
                </p>

                <!-- Evidences & Entities -->
                <div class="pt-2 border-t border-slate-100 flex flex-wrap gap-1.5 items-center">
                  <span
                    v-for="entity in s.related_entities"
                    :key="entity.id"
                    class="px-2 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700 font-medium"
                  >
                    {{ entity.name }}
                  </span>
                  <span
                    v-for="topic in s.related_topics"
                    :key="topic"
                    class="px-2 py-0.5 rounded text-[10px] bg-blue-50 text-blue-700 font-medium"
                  >
                    {{ topic }}
                  </span>
                </div>
              </div>

              <div class="pt-2 border-t border-slate-100 text-[11px] font-mono text-slate-500 flex items-center justify-between">
                <span>{{ s.independent_sources_count }} independent sources</span>
                <span>{{ new Date(s.first_observed_at).toLocaleDateString() }}</span>
              </div>
            </Card>
          </div>
        </div>

        <!-- TAB 3: CUSTOM MONITORS (PREVIOUS VIEW) -->
        <div v-else class="space-y-4">
          <div v-if="monitors.length === 0" class="p-12 bg-white rounded-lg border border-nexo-border text-center space-y-2">
            <Bell class="w-10 h-10 text-slate-300 mx-auto" />
            <h3 class="font-serif font-bold text-nexo-text-primary text-base">No Custom Monitors Active</h3>
            <p class="text-xs text-nexo-text-secondary max-w-md mx-auto">
              Set up rules to automatically alert you when verified developments matching your critical parameters break.
            </p>
            <Button variant="primary" size="sm" class="mt-2" @click="isCreateModalOpen = true">
              Create Monitor Rule
            </Button>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <Card
              v-for="m in monitors"
              :key="m.id"
              class="p-5 bg-white border shadow-xs space-y-4 flex flex-col justify-between transition-all"
              :class="m.is_active ? 'border-nexo-border' : 'border-dashed border-slate-300 opacity-70 bg-slate-50/50'"
            >
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                    :class="m.is_active ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500'"
                  >
                    {{ m.is_active ? 'Active Daemon' : 'Paused' }}
                  </span>
                  <span class="text-[10px] font-mono text-nexo-text-secondary uppercase">
                    {{ m.alert_frequency.replace('_', ' ') }}
                  </span>
                </div>

                <div>
                  <h3 class="font-serif font-bold text-nexo-text-primary text-base">
                    {{ m.name }}
                  </h3>
                  <div class="flex flex-wrap gap-1 mt-2">
                    <span
                      v-for="(kw, idx) in m.keywords"
                      :key="idx"
                      class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] rounded font-mono"
                    >
                      + {{ kw }}
                    </span>
                    <span
                      v-for="(ex, idx) in (m.excluded_terms || [])"
                      :key="'ex-' + idx"
                      class="px-2 py-0.5 bg-rose-50 text-rose-700 text-[10px] rounded font-mono"
                    >
                      - {{ ex }}
                    </span>
                  </div>
                </div>

                <div class="space-y-1.5 text-xs text-nexo-text-secondary pt-2 border-t border-nexo-border/40">
                  <div class="flex items-center justify-between">
                    <span>Min Credibility:</span>
                    <span class="font-mono font-bold text-nexo-text-primary">{{ Math.round(m.min_credibility_score * 100) }}%</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span>Verified Only:</span>
                    <span class="font-mono font-bold" :class="m.require_verified_only ? 'text-emerald-700' : 'text-slate-500'">
                      {{ m.require_verified_only ? 'Yes' : 'No' }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="pt-3 border-t border-nexo-border/60 flex items-center justify-between">
                <div class="text-[10px] font-mono text-nexo-text-secondary">
                  Matched: <span class="font-bold text-nexo-text-primary">{{ m.match_count }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="xs"
                    @click="handleToggleActive(m.id)"
                    :title="m.is_active ? 'Pause Monitor' : 'Resume Monitor'"
                  >
                    <component :is="m.is_active ? Pause : Play" class="w-3.5 h-3.5" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="xs"
                    class="text-rose-600 hover:bg-rose-50"
                    @click="handleDeleteMonitor(m.id)"
                    title="Delete Monitor"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </Button>
                </div>
              </div>
            </Card>
          </div>
        </div>

        <!-- TRANSITION STATUS MODAL -->
        <Modal
          :isOpen="isTransitionModalOpen"
          title="Transition Early Warning Status"
          @close="isTransitionModalOpen = false"
        >
          <div v-if="selectedWarningForTransition" class="space-y-4">
            <div class="p-3 bg-slate-50 rounded border border-slate-200 text-xs">
              <div class="font-bold text-slate-800">{{ selectedWarningForTransition.title }}</div>
              <div class="text-slate-500 font-mono mt-1">Current Status: {{ selectedWarningForTransition.status }}</div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Target Status</label>
              <select
                v-model="targetTransitionStatus"
                class="w-full text-xs p-2 border rounded border-slate-300 font-medium"
              >
                <option value="under_review">Under Review</option>
                <option value="escalated">Escalated</option>
                <option value="resolved">Resolved</option>
                <option value="dismissed">Dismissed</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Transition Reason / Analyst Notes</label>
              <textarea
                v-model="transitionReason"
                rows="3"
                class="w-full text-xs p-2 border rounded border-slate-300 font-sans"
                placeholder="Explain the justification for updating the lifecycle state..."
              />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <Button variant="secondary" size="sm" @click="isTransitionModalOpen = false">
                Cancel
              </Button>
              <Button
                variant="primary"
                size="sm"
                @click="handleConfirmTransition"
                :disabled="isTransitioning"
              >
                Confirm Transition
              </Button>
            </div>
          </div>
        </Modal>

        <!-- CREATE MONITOR MODAL -->
        <Modal
          :isOpen="isCreateModalOpen"
          title="Create Custom Intelligence Monitor"
          @close="isCreateModalOpen = false"
        >
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Monitor Name</label>
              <Input v-model="createForm.name" placeholder="e.g. EUV High-NA Packaging Bottlenecks" />
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Include Keywords (comma-separated)</label>
              <Input v-model="createForm.keywordsRaw" placeholder="High-NA, ASML, 2nm, optics" />
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Exclude Keywords (comma-separated)</label>
              <Input v-model="createForm.excludedTermsRaw" placeholder="rumors, speculative, leak" />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Min Credibility</label>
                <select
                  v-model="createForm.min_credibility_score"
                  class="w-full text-xs p-2 border rounded border-slate-300"
                >
                  <option :value="0.90">90% — Tier 1 Only</option>
                  <option :value="0.75">75% — Authoritative</option>
                  <option :value="0.60">60% — Broad Coverage</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Alert Frequency</label>
                <select
                  v-model="createForm.alert_frequency"
                  class="w-full text-xs p-2 border rounded border-slate-300"
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

            <div class="flex items-center justify-between pt-3 border-t">
              <Button variant="ghost" size="sm" @click="handlePreviewRule" :disabled="isPreviewing">
                Test Rule Match
              </Button>
              <div class="flex items-center gap-2">
                <Button variant="secondary" size="sm" @click="isCreateModalOpen = false">Cancel</Button>
                <Button variant="primary" size="sm" @click="handleCreateMonitor" :disabled="isSubmitting">
                  Activate Monitor
                </Button>
              </div>
            </div>
          </div>
        </Modal>
      </main>
    </div>
  </div>
</template>
