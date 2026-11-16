<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import Modal from '@/components/ui/Modal.vue'
import { adminService } from '@/services/api/admin.service'
import {
  automationService,
  type DetectionRule,
  type AnomalyObservation,
  type AnalystReview
} from '@/services/api/automation.service'
import {
  Zap,
  RefreshCw,
  XCircle,
  Sliders,
  AlertTriangle,
  Radio,
  ShieldCheck,
  Check,
  X,
  FileCheck,
  Activity,
  SlidersHorizontal
} from 'lucide-vue-next'

const activeTab = ref<'pipeline' | 'rules' | 'anomalies' | 'reviews'>('pipeline')

// 1. Pipeline Jobs State
const jobs = ref<any[]>([])
const stages = ref<string[]>([])
const isLoading = ref(true)
const isScanning = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

const headers = ['Job ID', 'Source & Target', 'Stage & Status', 'AI Model', 'Tokens & Cost', 'Latency', 'Actions']

// 2. Detection Rules State
const rules = ref<DetectionRule[]>([])
const isLoadingRules = ref(false)

// 3. Anomalies State
const anomalies = ref<AnomalyObservation[]>([])
const isLoadingAnomalies = ref(false)

// 4. Analyst Review Queue State
const reviews = ref<any[]>([])
const isLoadingReviews = ref(false)
const isReviewModalOpen = ref(false)
const activeReviewItem = ref<any | null>(null)
const reviewDecision = ref<'confirmed' | 'false_positive' | 'calibrated'>('confirmed')
const reviewReason = ref('')
const thresholdAdjustment = ref(0.0)
const isSubmittingReview = ref(false)

const fetchJobs = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getAutomationJobs()
    jobs.value = res.data || []
    stages.value = res.stages || []
  } catch (err: any) {
    errorMessage.value = 'Failed to load pipeline jobs.'
  } finally {
    isLoading.value = false
  }
}

const fetchRules = async () => {
  isLoadingRules.value = true
  try {
    const res = await automationService.getAdminRules()
    if (res.success) {
      rules.value = res.rules || []
    }
  } catch (err: any) {
    console.error('Failed to load rules', err)
  } finally {
    isLoadingRules.value = false
  }
}

const fetchAnomalies = async () => {
  isLoadingAnomalies.value = true
  try {
    const res = await automationService.getAdminAnomalies()
    if (res.success) {
      anomalies.value = res.anomalies || []
    }
  } catch (err: any) {
    console.error('Failed to load anomalies', err)
  } finally {
    isLoadingAnomalies.value = false
  }
}

const fetchReviews = async () => {
  isLoadingReviews.value = true
  try {
    const res = await automationService.getAdminReviews('pending')
    if (res.success) {
      reviews.value = res.reviews || []
    }
  } catch (err: any) {
    console.error('Failed to load reviews', err)
  } finally {
    isLoadingReviews.value = false
  }
}

const handleRetry = async (id: string) => {
  try {
    const res = await adminService.retryJob(id)
    statusMessage.value = res.message || 'Job queued for re-execution.'
    await fetchJobs()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to retry job.'
  }
}

const handleCancel = async (id: string) => {
  try {
    const res = await adminService.cancelJob(id)
    statusMessage.value = res.message || 'Job cancelled.'
    await fetchJobs()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to cancel job.'
  }
}

const handleBatchScan = async () => {
  isScanning.value = true
  try {
    const res = await automationService.triggerManualScan()
    statusMessage.value = res.message || 'Ingestion and early-warning scan completed.'
    await Promise.all([fetchJobs(), fetchRules(), fetchAnomalies(), fetchReviews()])
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to trigger scan.'
  } finally {
    isScanning.value = false
  }
}

const handleToggleRule = async (rule: DetectionRule) => {
  try {
    const res = await automationService.updateAdminRule(rule.id, {
      is_active: !rule.is_active,
    })
    if (res.success) {
      rule.is_active = !rule.is_active
      statusMessage.value = `Rule "${rule.name}" updated.`
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update rule.'
  }
}

const openReviewModal = (item: any) => {
  activeReviewItem.value = item
  reviewDecision.value = 'confirmed'
  reviewReason.value = 'Verified corroborated factual reporting.'
  thresholdAdjustment.value = 0.0
  isReviewModalOpen.value = true
}

const handleSubmitReview = async () => {
  if (!activeReviewItem.value) return
  isSubmittingReview.value = true
  errorMessage.value = ''
  try {
    const res = await automationService.submitAnalystReview({
      reviewable_type: 'signal',
      reviewable_id: activeReviewItem.value.id,
      decision: reviewDecision.value,
      reason: reviewReason.value,
      threshold_adjustment: thresholdAdjustment.value,
    })
    if (res.success) {
      statusMessage.value = `Review logged: ${reviewDecision.value}. Calibration recorded.`
      isReviewModalOpen.value = false
      await fetchReviews()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to submit analyst review.'
  } finally {
    isSubmittingReview.value = false
  }
}

onMounted(() => {
  fetchJobs()
  fetchRules()
  fetchAnomalies()
  fetchReviews()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Explanation -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Intelligence Automation & Surveillance Control Room
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Real-time control over the 14-stage ingestion pipeline, detection rule calibration, anomaly tracking, and analyst review workflows.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button
            variant="primary"
            size="sm"
            :disabled="isScanning"
            @click="handleBatchScan"
            class="bg-nexo-accent-gold hover:bg-[#8e6e18]"
          >
            <span v-if="isScanning" class="flex items-center gap-1.5">
              <RefreshCw class="h-3.5 w-3.5 animate-spin" /> Scanning Surveillance Feeds...
            </span>
            <span v-else class="flex items-center gap-1.5">
              <Zap class="h-3.5 w-3.5" /> Trigger Ingestion Scan
            </span>
          </Button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-nexo-border space-x-6 text-sm font-medium">
        <button
          @click="activeTab = 'pipeline'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'pipeline' ? 'border-amber-600 text-amber-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <Activity class="w-4 h-4" />
          Processing Pipeline ({{ jobs.length }})
        </button>
        <button
          @click="activeTab = 'rules'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'rules' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <SlidersHorizontal class="w-4 h-4" />
          Detection Rules & Sensitivity ({{ rules.length }})
        </button>
        <button
          @click="activeTab = 'anomalies'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'anomalies' ? 'border-purple-600 text-purple-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <AlertTriangle class="w-4 h-4" />
          Anomaly Observations ({{ anomalies.length }})
        </button>
        <button
          @click="activeTab = 'reviews'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'reviews' ? 'border-emerald-600 text-emerald-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <FileCheck class="w-4 h-4" />
          Analyst Review Queue ({{ reviews.length }})
        </button>
      </div>

      <!-- Alerts -->
      <Alert v-if="statusMessage" variant="success" dismissible @dismiss="statusMessage = ''">
        {{ statusMessage }}
      </Alert>
      <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
        {{ errorMessage }}
      </Alert>

      <!-- TAB 1: 14-STAGE PROCESSING PIPELINE -->
      <div v-if="activeTab === 'pipeline'" class="space-y-4">
        <Card class="p-4 space-y-3 bg-white border border-nexo-border">
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted tracking-wider font-bold">
            Pipeline Processing Architecture (Sequential VPS Safe)
          </span>
          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 text-[10px] font-mono">
            <div
              v-for="(st, i) in stages"
              :key="i"
              class="p-2 rounded-lg bg-nexo-surface/40 border border-nexo-border text-center truncate"
              :title="st"
            >
              <span class="text-nexo-accent-gold font-bold block">{{ i + 1 }}</span>
              <span class="text-nexo-text-primary truncate block font-medium">{{ st.replace(/^\d+\.\s*/, '') }}</span>
            </div>
          </div>
        </Card>

        <!-- Jobs Table -->
        <Table :headers="headers" :is-empty="jobs.length === 0">
          <tr v-for="j in jobs" :key="j.id" class="hover:bg-nexo-surface/40 transition-colors">
            <td class="py-3.5 px-4 font-mono font-bold text-nexo-accent-cyan">
              {{ j.id }}
            </td>
            <td class="py-3.5 px-4">
              <div class="font-medium text-nexo-text-primary">{{ j.item_title }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted">{{ j.source }}</div>
            </td>
            <td class="py-3.5 px-4 font-mono">
              <div class="text-xs text-nexo-text-primary font-semibold">{{ j.current_stage }}</div>
              <span
                :class="[
                  'px-1.5 py-0.2 rounded text-[9px] uppercase font-bold',
                  j.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                  j.status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800 animate-pulse'
                ]"
              >
                {{ j.status }}
              </span>
            </td>
            <td class="py-3.5 px-4 font-mono text-[11px] text-nexo-text-secondary">
              {{ j.ai_provider }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <span class="text-nexo-text-primary">{{ j.tokens_consumed }} tok</span>
              <span class="text-nexo-accent-gold block text-[10px]">${{ j.cost_usd }}</span>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-muted">
              {{ j.duration_seconds }}s
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <div class="flex items-center space-x-2">
                <button
                  v-if="j.status === 'processing'"
                  @click="handleCancel(j.id)"
                  class="px-2 py-1 rounded bg-white hover:bg-red-50 border border-red-200 text-red-600 text-xs"
                >
                  Cancel
                </button>
                <button
                  v-else
                  @click="handleRetry(j.id)"
                  class="px-2.5 py-1 rounded bg-white hover:bg-nexo-bg border border-nexo-border text-xs text-nexo-text-primary shadow-xs font-semibold"
                >
                  Retry
                </button>
              </div>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 2: DETECTION RULES & SENSITIVITY -->
      <div v-else-if="activeTab === 'rules'" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="text-xs text-slate-500 font-mono">
            Configured heuristic and corroboration rules across 12 signal classifications.
          </div>
          <Button variant="ghost" size="xs" @click="fetchRules">
            <RefreshCw class="w-3.5 h-3.5 mr-1" /> Reload Rules
          </Button>
        </div>

        <div class="overflow-x-auto border rounded bg-white">
          <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-[10px] font-mono uppercase text-slate-500 border-b">
              <tr>
                <th class="p-3">Rule Name</th>
                <th class="p-3">Category</th>
                <th class="p-3">Target Pattern</th>
                <th class="p-3">Sensitivity</th>
                <th class="p-3">Min Credibility</th>
                <th class="p-3">Status</th>
                <th class="p-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y font-mono">
              <tr v-for="rule in rules" :key="rule.id" class="hover:bg-slate-50/50">
                <td class="p-3 font-bold text-slate-800">{{ rule.name }}</td>
                <td class="p-3 text-slate-600">{{ rule.rule_category.replace(/_/g, ' ') }}</td>
                <td class="p-3 text-slate-500 max-w-xs truncate" :title="rule.target_pattern">
                  {{ rule.target_pattern }}
                </td>
                <td class="p-3 capitalize font-bold" :class="rule.sensitivity === 'high' ? 'text-rose-600' : 'text-blue-600'">
                  {{ rule.sensitivity }}
                </td>
                <td class="p-3 text-slate-700">{{ Math.round(rule.min_credibility_threshold * 100) }}%</td>
                <td class="p-3">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                    :class="rule.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                  >
                    {{ rule.is_active ? 'Active' : 'Disabled' }}
                  </span>
                </td>
                <td class="p-3 text-right">
                  <Button
                    variant="ghost"
                    size="xs"
                    @click="handleToggleRule(rule)"
                  >
                    {{ rule.is_active ? 'Deactivate' : 'Activate' }}
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: ANOMALY OBSERVATIONS -->
      <div v-else-if="activeTab === 'anomalies'" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="text-xs text-slate-500 font-mono">
            Automated z-score baseline observations with sample-size guardrails.
          </div>
          <Button variant="ghost" size="xs" @click="fetchAnomalies">
            <RefreshCw class="w-3.5 h-3.5 mr-1" /> Reload Observations
          </Button>
        </div>

        <div v-if="anomalies.length === 0" class="p-12 bg-white rounded border text-center text-xs text-slate-500">
          No statistical anomalies recorded in the recent observation window.
        </div>

        <div v-else class="overflow-x-auto border rounded bg-white">
          <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-[10px] font-mono uppercase text-slate-500 border-b">
              <tr>
                <th class="p-3">Subject</th>
                <th class="p-3">Metric</th>
                <th class="p-3">Baseline Mean</th>
                <th class="p-3">Observed</th>
                <th class="p-3">Z-Score Deviation</th>
                <th class="p-3">Sufficiency</th>
                <th class="p-3">Classification</th>
              </tr>
            </thead>
            <tbody class="divide-y font-mono">
              <tr v-for="an in anomalies" :key="an.id" class="hover:bg-slate-50/50">
                <td class="p-3 font-bold text-slate-800">{{ an.subject_name }} ({{ an.subject_type }})</td>
                <td class="p-3 text-slate-600">{{ an.metric_type.replace(/_/g, ' ') }}</td>
                <td class="p-3 text-slate-500">{{ an.baseline_mean.toFixed(2) }} (σ={{ an.baseline_stddev.toFixed(2) }})</td>
                <td class="p-3 font-bold text-slate-800">{{ an.observed_value.toFixed(2) }}</td>
                <td class="p-3 font-bold" :class="Math.abs(an.deviation_z_score) >= 2.0 ? 'text-amber-600' : 'text-slate-700'">
                  {{ an.deviation_z_score > 0 ? '+' : '' }}{{ an.deviation_z_score.toFixed(2) }}σ
                </td>
                <td class="p-3 capitalize" :class="an.data_sufficiency === 'sufficient' ? 'text-emerald-700' : 'text-amber-700'">
                  {{ an.data_sufficiency.replace(/_/g, ' ') }}
                </td>
                <td class="p-3">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                    :class="an.is_anomaly ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-slate-100 text-slate-500'"
                  >
                    {{ an.is_anomaly ? 'Anomaly Detected' : 'Within Normal Bound' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 4: ANALYST REVIEW QUEUE -->
      <div v-else class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="text-xs text-slate-500 font-mono">
            Human-in-the-loop analyst feedback queue for verifying alerts and tuning detection thresholds.
          </div>
          <Button variant="ghost" size="xs" @click="fetchReviews">
            <RefreshCw class="w-3.5 h-3.5 mr-1" /> Reload Queue
          </Button>
        </div>

        <div v-if="reviews.length === 0" class="p-12 bg-white rounded border text-center text-xs text-slate-500">
          Analyst queue is clean. All active signals have been validated or triaged.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="item in reviews"
            :key="item.id"
            class="p-4 bg-white border rounded shadow-xs flex items-center justify-between gap-4"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-amber-100 text-amber-800">
                  {{ item.severity || 'HIGH' }}
                </span>
                <span class="text-xs font-mono text-slate-500 uppercase">
                  {{ (item.signal_category || 'SIGNAL').replace(/_/g, ' ') }}
                </span>
              </div>
              <h4 class="font-serif font-bold text-sm text-slate-900">{{ item.title }}</h4>
              <p class="text-xs text-slate-600 line-clamp-1">{{ item.description }}</p>
            </div>

            <Button variant="primary" size="xs" @click="openReviewModal(item)">
              Review Signal
            </Button>
          </div>
        </div>
      </div>

      <!-- REVIEW & CALIBRATION MODAL -->
      <Modal
        :isOpen="isReviewModalOpen"
        title="Analyst Review & Threshold Calibration"
        @close="isReviewModalOpen = false"
      >
        <div v-if="activeReviewItem" class="space-y-4">
          <div class="p-3 bg-slate-50 rounded border text-xs">
            <div class="font-bold text-slate-900">{{ activeReviewItem.title }}</div>
            <div class="text-slate-500 text-[11px] mt-1">{{ activeReviewItem.description }}</div>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Analyst Decision</label>
            <select
              v-model="reviewDecision"
              class="w-full text-xs p-2 border rounded border-slate-300 font-medium"
            >
              <option value="confirmed">Confirm Signal (Accurate Detection)</option>
              <option value="false_positive">Mark as False Positive (Non-Critical or Misattributed)</option>
              <option value="calibrated">Calibrate Sensitivity (Tune Credibility Threshold)</option>
            </select>
          </div>

          <div v-if="reviewDecision === 'calibrated' || reviewDecision === 'false_positive'">
            <label class="block text-xs font-mono uppercase text-slate-500 mb-1">
              Threshold Adjustment (+ / - credibility delta)
            </label>
            <select
              v-model="thresholdAdjustment"
              class="w-full text-xs p-2 border rounded border-slate-300 font-medium"
            >
              <option :value="0.05">+5% Credibility Requirement (Tighter Filtering)</option>
              <option :value="0.10">+10% Credibility Requirement (Strict Gating)</option>
              <option :value="-0.05">-5% Credibility Requirement (Broader Sensitivity)</option>
              <option :value="0.0">No Adjustment (Record Note Only)</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-mono uppercase text-slate-500 mb-1">Analyst Notes & Audit Trail</label>
            <textarea
              v-model="reviewReason"
              rows="3"
              class="w-full text-xs p-2 border rounded border-slate-300 font-sans"
              placeholder="State verifiable reasons for this decision..."
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t">
            <Button variant="secondary" size="sm" @click="isReviewModalOpen = false">Cancel</Button>
            <Button
              variant="primary"
              size="sm"
              @click="handleSubmitReview"
              :disabled="isSubmittingReview"
            >
              Submit Analyst Decision
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>
