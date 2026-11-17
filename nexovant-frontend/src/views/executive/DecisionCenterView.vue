<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import {
  executiveService,
  type IntelligenceDecision,
} from '@/services/api/executive.service'
import {
  ShieldAlert,
  CheckCircle,
  Plus,
  Clock,
  UserCheck,
  Calendar,
  AlertCircle,
  Check,
  X,
  FileCheck,
  ArrowRight,
  TrendingUp,
  BarChart3,
  BookmarkCheck,
  RotateCcw,
} from 'lucide-vue-next'

const decisions = ref<IntelligenceDecision[]>([])
const selectedDecision = ref<IntelligenceDecision | null>(null)
const isLoading = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const statusMessage = ref('')

const isCreateModalOpen = ref(false)
const createForm = ref({
  title: '',
  business_question: '',
  context_and_background: '',
  deadline: '',
})

const isAddOptionModalOpen = ref(false)
const optionForm = ref({
  option_name: '',
  description: '',
  advantages: '',
  disadvantages: '',
  risks: '',
  assumptions: '',
  expected_outcomes: '',
  score: 80,
})

const isAddMilestoneModalOpen = ref(false)
const milestoneForm = ref({
  title: '',
  target_date: '',
  notes: '',
})

const isApproveModalOpen = ref(false)
const approveForm = ref({
  selected_option_id: '',
  recorded_decision: '',
  approval_action: 'approved',
  next_steps: '',
})

const isPostReviewModalOpen = ref(false)
const reviewForm = ref({
  notes: '',
  changed_assumptions: '',
  observed_results_vs_expected: '',
  action_taken: 'monitor_continuation',
})

const fetchDecisions = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await executiveService.getDecisions()
    decisions.value = res.data || []
    if (decisions.value.length > 0) {
      if (selectedDecision.value) {
        // Refresh selected decision
        const found = decisions.value.find(d => d.id === selectedDecision.value?.id)
        if (found) {
          await loadDecisionDetail(found.id)
        } else {
          await loadDecisionDetail(decisions.value[0].id)
        }
      } else {
        await loadDecisionDetail(decisions.value[0].id)
      }
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load decisions.'
  } finally {
    isLoading.value = false
  }
}

const loadDecisionDetail = async (id: string) => {
  try {
    const detail = await executiveService.getDecision(id)
    selectedDecision.value = detail
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load decision detail.'
  }
}

const handleCreateDecision = async () => {
  if (!createForm.value.title || !createForm.value.business_question) {
    errorMessage.value = 'Please provide title and business question.'
    return
  }
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const newDecision = await executiveService.createDecision(createForm.value)
    statusMessage.value = 'Structured decision workspace created.'
    isCreateModalOpen.value = false
    await fetchDecisions()
    await loadDecisionDetail(newDecision.id)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create decision workspace.'
  } finally {
    isSubmitting.value = false
  }
}

const handleAddOption = async () => {
  if (!selectedDecision.value || !optionForm.value.option_name) return
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const payload = {
      ...optionForm.value,
      advantages: optionForm.value.advantages.split('\n').filter(s => s.trim().length > 0),
      disadvantages: optionForm.value.disadvantages.split('\n').filter(s => s.trim().length > 0),
      risks: optionForm.value.risks.split('\n').filter(s => s.trim().length > 0),
      assumptions: optionForm.value.assumptions.split('\n').filter(s => s.trim().length > 0),
    }
    await executiveService.addDecisionOption(selectedDecision.value.id, payload)
    statusMessage.value = 'Strategic option added to decision matrix.'
    isAddOptionModalOpen.value = false
    optionForm.value = {
      option_name: '',
      description: '',
      advantages: '',
      disadvantages: '',
      risks: '',
      assumptions: '',
      expected_outcomes: '',
      score: 80,
    }
    await loadDecisionDetail(selectedDecision.value.id)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to add option.'
  } finally {
    isSubmitting.value = false
  }
}

const handleAddMilestone = async () => {
  if (!selectedDecision.value || !milestoneForm.value.title) return
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    await executiveService.addDecisionMilestone(selectedDecision.value.id, milestoneForm.value)
    statusMessage.value = 'Execution milestone recorded.'
    isAddMilestoneModalOpen.value = false
    milestoneForm.value = { title: '', target_date: '', notes: '' }
    await loadDecisionDetail(selectedDecision.value.id)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to add milestone.'
  } finally {
    isSubmitting.value = false
  }
}

const handleUpdateMilestone = async (milestoneId: string, status: string) => {
  if (!selectedDecision.value) return
  try {
    await executiveService.updateDecisionMilestone(selectedDecision.value.id, milestoneId, status)
    statusMessage.value = 'Milestone status updated.'
    await loadDecisionDetail(selectedDecision.value.id)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update milestone.'
  }
}

const openApproveModal = () => {
  if (!selectedDecision.value) return
  approveForm.value.selected_option_id = selectedDecision.value.options?.[0]?.id || ''
  approveForm.value.recorded_decision = `Approved execution of recommended strategic path.`
  approveForm.value.approval_action = 'approved'
  approveForm.value.next_steps = ''
  isApproveModalOpen.value = true
}

const handleApproveDecision = async () => {
  if (!selectedDecision.value || !approveForm.value.selected_option_id) {
    errorMessage.value = 'Please select the approved option.'
    return
  }
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    await executiveService.approveDecision(selectedDecision.value.id, approveForm.value)
    statusMessage.value = 'Executive decision formally approved and recorded.'
    isApproveModalOpen.value = false
    await fetchDecisions()
    await loadDecisionDetail(selectedDecision.value.id)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to record approval.'
  } finally {
    isSubmitting.value = false
  }
}

const handlePostReview = async () => {
  if (!selectedDecision.value || !reviewForm.value.notes) return
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const payload = {
      ...reviewForm.value,
      changed_assumptions: reviewForm.value.changed_assumptions.split('\n').filter(s => s.trim().length > 0),
    }
    await executiveService.postDecisionReview(selectedDecision.value.id, payload)
    statusMessage.value = 'Post-decision retrospective review logged.'
    isPostReviewModalOpen.value = false
    reviewForm.value = { notes: '', changed_assumptions: '', observed_results_vs_expected: '', action_taken: 'monitor_continuation' }
    await loadDecisionDetail(selectedDecision.value.id)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to record post review.'
  } finally {
    isSubmitting.value = false
  }
}

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'draft':
      return 'bg-nexo-paper-2 text-nexo-muted border-nexo-border'
    case 'in_review':
      return 'bg-amber-50 text-amber-800 border-amber-200'
    case 'decision_recorded':
      return 'bg-emerald-50 text-emerald-800 border-emerald-200'
    case 'implemented':
      return 'bg-blue-50 text-blue-800 border-blue-200'
    case 'post_review':
      return 'bg-purple-50 text-purple-800 border-purple-200'
    default:
      return 'bg-nexo-paper-2 text-nexo-ink border-nexo-border'
  }
}

onMounted(() => {
  fetchDecisions()
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
            <span class="section-label">EXECUTIVE DECISION CENTER</span>
          </div>
          <h1 class="font-serif text-3xl font-bold tracking-tight text-nexo-ink mt-1">
            Structured Decision Support
          </h1>
          <p class="text-xs text-nexo-text-2 mt-1">
            Option matrices, trade-off scoring, human authorization, and retrospective tracking.
          </p>
        </div>

        <button
          @click="isCreateModalOpen = true"
          class="inline-flex items-center gap-1.5 rounded-[4px] bg-nexo-ink px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[#1A2C42]"
        >
          <Plus class="h-3.5 w-3.5" />
          New Decision Workspace
        </button>
      </div>

      <!-- Human in the Loop Notice -->
      <div class="rounded-[2px] border border-nexo-brass/40 bg-nexo-brass-tint/20 p-3 text-xs text-nexo-brass-text flex items-start gap-2">
        <ShieldAlert class="h-4 w-4 shrink-0 mt-0.5" />
        <div class="leading-relaxed">
          <strong>Non-Negotiable Human Authorization Policy:</strong>
          Nexovant AI provides structured decision support, trade-off analysis, and scenario forecasting, but never executes or approves decisions autonomously. Every decision requires explicit human executive confirmation and signature.
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

      <!-- Split Layout -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- Decision List -->
        <div class="space-y-3 lg:col-span-4">
          <div v-if="isLoading" class="py-12 text-center text-xs text-nexo-muted">
            Loading decision workspaces...
          </div>
          <div v-else-if="decisions.length === 0" class="rounded-[4px] border border-dashed border-nexo-border bg-white p-8 text-center text-xs text-nexo-muted">
            No decisions logged yet. Click "New Decision Workspace" to initiate.
          </div>
          <div
            v-for="d in decisions"
            :key="d.id"
            @click="loadDecisionDetail(d.id)"
            :class="[
              'cursor-pointer rounded-[4px] border bg-white p-4 transition',
              selectedDecision?.id === d.id
                ? 'border-nexo-cobalt ring-1 ring-nexo-cobalt shadow-sm'
                : 'border-nexo-border hover:border-nexo-border-dark'
            ]"
          >
            <div class="flex items-center justify-between gap-2">
              <span :class="['rounded-[2px] border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider', getStatusBadge(d.status)]">
                {{ d.status.replace(/_/g, ' ') }}
              </span>
              <span v-if="d.approval_status === 'approved'" class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700">
                <CheckCircle class="h-3 w-3" /> Approved
              </span>
              <span v-else class="text-[10px] text-nexo-muted">
                {{ d.approval_status.replace(/_/g, ' ') }}
              </span>
            </div>

            <h3 class="font-serif text-base font-bold text-nexo-ink mt-2">
              {{ d.title }}
            </h3>
            <p class="text-xs text-nexo-text-2 mt-1 line-clamp-2">
              {{ d.business_question }}
            </p>

            <div class="mt-3 flex items-center justify-between border-t border-nexo-border/60 pt-2 text-[11px] text-nexo-muted">
              <span>Options: {{ d.options?.length || 0 }}</span>
              <span>Deadline: {{ d.deadline || 'None' }}</span>
            </div>
          </div>
        </div>

        <!-- Selected Decision Workspace -->
        <div class="lg:col-span-8">
          <div v-if="selectedDecision" class="rounded-[4px] border border-nexo-border bg-white p-6 space-y-6">
            <!-- Header Bar -->
            <div class="border-b border-nexo-border pb-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <span :class="['rounded-[2px] border px-2 py-0.5 text-xs font-bold uppercase', getStatusBadge(selectedDecision.status)]">
                    {{ selectedDecision.status.replace(/_/g, ' ') }}
                  </span>
                  <span v-if="selectedDecision.approval_status === 'approved'" class="rounded-[2px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 flex items-center gap-1">
                    <UserCheck class="h-3.5 w-3.5" /> Approved by Human Executive
                  </span>
                </div>
                <h2 class="font-serif text-2xl font-bold text-nexo-ink mt-2">
                  {{ selectedDecision.title }}
                </h2>
                <p class="text-xs font-semibold text-nexo-cobalt mt-1">
                  Question: {{ selectedDecision.business_question }}
                </p>
              </div>

              <!-- Action Bar -->
              <div class="flex flex-wrap items-center gap-2">
                <button
                  v-if="selectedDecision.approval_status !== 'approved'"
                  @click="openApproveModal"
                  class="inline-flex items-center gap-1.5 rounded-[2px] bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 transition"
                >
                  <UserCheck class="h-3.5 w-3.5" />
                  Approve Decision
                </button>
                <button
                  @click="isPostReviewModalOpen = true"
                  class="inline-flex items-center gap-1.5 rounded-[2px] border border-nexo-border bg-nexo-bg px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2 transition"
                >
                  <RotateCcw class="h-3.5 w-3.5 text-nexo-muted" />
                  Post-Review Retrospective
                </button>
              </div>
            </div>

            <!-- Approved Decision Box (if approved) -->
            <div v-if="selectedDecision.approval_status === 'approved'" class="rounded-[2px] border border-emerald-200 bg-emerald-50/50 p-4 space-y-2">
              <div class="flex items-center justify-between text-xs text-emerald-900 font-semibold">
                <span class="flex items-center gap-1">
                  <CheckCircle class="h-4 w-4 text-emerald-700" />
                  Recorded Decision:
                </span>
                <span>Date: {{ selectedDecision.decision_date || selectedDecision.approved_at }}</span>
              </div>
              <p class="text-xs text-emerald-950 font-medium leading-relaxed">
                {{ selectedDecision.recorded_decision }}
              </p>
              <div v-if="selectedDecision.next_steps" class="text-xs text-emerald-900 pt-1 border-t border-emerald-200/60">
                <strong>Next Steps:</strong> {{ selectedDecision.next_steps }}
              </div>
            </div>

            <!-- Context & Background -->
            <div v-if="selectedDecision.context_and_background">
              <h4 class="section-label mb-1.5">CONTEXT & STRATEGIC BACKGROUND</h4>
              <p class="text-xs text-nexo-text-2 leading-relaxed bg-nexo-bg/60 p-3 rounded-[2px] border border-nexo-border">
                {{ selectedDecision.context_and_background }}
              </p>
            </div>

            <!-- Strategic Options Comparison Matrix -->
            <div>
              <div class="flex items-center justify-between mb-3">
                <h4 class="section-label">STRATEGIC OPTIONS & TRADE-OFF MATRIX</h4>
                <button
                  @click="isAddOptionModalOpen = true"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-cobalt hover:underline"
                >
                  <Plus class="h-3.5 w-3.5" />
                  Add Option
                </button>
              </div>

              <div v-if="!selectedDecision.options || selectedDecision.options.length === 0" class="rounded-[2px] border border-dashed border-nexo-border p-6 text-center text-xs text-nexo-muted">
                No options defined for this decision yet.
              </div>
              <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                  v-for="opt in selectedDecision.options"
                  :key="opt.id"
                  :class="[
                    'rounded-[4px] border p-4 space-y-3 transition',
                    opt.is_selected
                      ? 'border-emerald-600 bg-emerald-50/30 ring-1 ring-emerald-600'
                      : 'border-nexo-border bg-white'
                  ]"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <div class="flex items-center gap-1.5">
                        <span v-if="opt.is_selected" class="rounded-[2px] bg-emerald-700 px-1.5 py-0.5 text-[9px] font-bold text-white uppercase">
                          Selected
                        </span>
                        <h5 class="font-serif text-sm font-bold text-nexo-ink">{{ opt.option_name }}</h5>
                      </div>
                      <p v-if="opt.description" class="text-xs text-nexo-muted mt-0.5">{{ opt.description }}</p>
                    </div>
                    <span v-if="opt.score" class="rounded-[2px] bg-nexo-paper-2 px-2 py-0.5 text-xs font-bold text-nexo-ink border border-nexo-border">
                      Score: {{ opt.score }}
                    </span>
                  </div>

                  <!-- Advantages -->
                  <div v-if="opt.advantages && opt.advantages.length > 0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Advantages</p>
                    <ul class="list-disc list-inside text-xs text-emerald-950 space-y-0.5 mt-0.5">
                      <li v-for="(adv, i) in opt.advantages" :key="i">{{ adv }}</li>
                    </ul>
                  </div>

                  <!-- Disadvantages -->
                  <div v-if="opt.disadvantages && opt.disadvantages.length > 0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Disadvantages</p>
                    <ul class="list-disc list-inside text-xs text-amber-950 space-y-0.5 mt-0.5">
                      <li v-for="(dis, i) in opt.disadvantages" :key="i">{{ dis }}</li>
                    </ul>
                  </div>

                  <!-- Risks -->
                  <div v-if="opt.risks && opt.risks.length > 0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-rose-800">Risks & Trade-offs</p>
                    <ul class="list-disc list-inside text-xs text-rose-950 space-y-0.5 mt-0.5">
                      <li v-for="(risk, i) in opt.risks" :key="i">{{ risk }}</li>
                    </ul>
                  </div>

                  <div v-if="opt.expected_outcomes" class="text-xs text-nexo-text-2 border-t border-nexo-border/60 pt-2">
                    <strong>Expected Outcome:</strong> {{ opt.expected_outcomes }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Execution Milestones -->
            <div>
              <div class="flex items-center justify-between mb-3">
                <h4 class="section-label">EXECUTION MILESTONES & OBSERVABILITY</h4>
                <button
                  @click="isAddMilestoneModalOpen = true"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-cobalt hover:underline"
                >
                  <Plus class="h-3.5 w-3.5" />
                  Add Milestone
                </button>
              </div>

              <div v-if="!selectedDecision.milestones || selectedDecision.milestones.length === 0" class="rounded-[2px] border border-dashed border-nexo-border p-4 text-center text-xs text-nexo-muted">
                No execution milestones defined.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="m in selectedDecision.milestones"
                  :key="m.id"
                  class="flex items-center justify-between rounded-[2px] border border-nexo-border bg-nexo-bg/40 p-3 text-xs"
                >
                  <div class="flex items-center gap-3">
                    <button
                      @click="handleUpdateMilestone(m.id, m.status === 'completed' ? 'pending' : 'completed')"
                      :class="[
                        'flex h-5 w-5 items-center justify-center rounded border transition',
                        m.status === 'completed'
                          ? 'border-emerald-600 bg-emerald-600 text-white'
                          : 'border-nexo-border bg-white text-transparent hover:border-nexo-cobalt'
                      ]"
                    >
                      <Check class="h-3.5 w-3.5" />
                    </button>
                    <div>
                      <p :class="['font-medium text-nexo-ink', m.status === 'completed' ? 'line-through text-nexo-muted' : '']">
                        {{ m.title }}
                      </p>
                      <p v-if="m.notes" class="text-[11px] text-nexo-text-2">{{ m.notes }}</p>
                    </div>
                  </div>
                  <div class="text-right text-[11px] text-nexo-muted">
                    <span>Due: {{ m.target_date || 'TBD' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Retrospective Reviews History -->
            <div v-if="selectedDecision.reviews && selectedDecision.reviews.length > 0">
              <h4 class="section-label mb-2">RETROSPECTIVE REVIEWS & AUDIT TRAIL</h4>
              <div class="space-y-2">
                <div
                  v-for="r in selectedDecision.reviews"
                  :key="r.id"
                  class="rounded-[2px] border border-nexo-border bg-white p-3 text-xs space-y-1"
                >
                  <div class="flex items-center justify-between text-nexo-muted text-[11px]">
                    <span class="font-semibold text-nexo-ink uppercase">{{ r.review_type }}</span>
                    <span>{{ new Date(r.created_at).toLocaleString() }}</span>
                  </div>
                  <p class="text-nexo-text-2">{{ r.notes }}</p>
                  <p class="text-[11px] text-nexo-cobalt"><strong>Action taken:</strong> {{ r.action_taken }}</p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="rounded-[4px] border border-dashed border-nexo-border bg-white p-12 text-center text-xs text-nexo-muted">
            Select a decision workspace to review options, milestone progress, and authorization status.
          </div>
        </div>
      </div>

      <!-- Create Decision Workspace Modal -->
      <div v-if="isCreateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-[4px] border border-nexo-border bg-white p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Initiate Decision Workspace</h3>
            <button @click="isCreateModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Decision Title</label>
            <input
              v-model="createForm.title"
              type="text"
              placeholder="e.g. Foundry Investment & Long-Term Supply Contract Approval"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink focus:border-nexo-cobalt focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Business Question</label>
            <input
              v-model="createForm.business_question"
              type="text"
              placeholder="e.g. Should the organization commit $500M to European foundry expansion?"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink focus:border-nexo-cobalt focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Target Deadline</label>
            <input
              v-model="createForm.deadline"
              type="date"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Context & Strategic Background</label>
            <textarea
              v-model="createForm.context_and_background"
              rows="3"
              placeholder="Provide market intelligence context and strategic urgency..."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="isCreateModalOpen = false"
              class="rounded-[2px] border border-nexo-border px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2"
            >
              Cancel
            </button>
            <button
              @click="handleCreateDecision"
              :disabled="isSubmitting"
              class="rounded-[2px] bg-nexo-ink px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#1A2C42] disabled:opacity-50"
            >
              {{ isSubmitting ? 'Creating...' : 'Create Workspace' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Add Strategic Option Modal -->
      <div v-if="isAddOptionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
        <div class="w-full max-w-xl rounded-[4px] border border-nexo-border bg-white p-6 shadow-xl space-y-4 my-8">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Add Strategic Option</h3>
            <button @click="isAddOptionModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Option Name</label>
            <input
              v-model="optionForm.option_name"
              type="text"
              placeholder="e.g. Option B: Dual-Source Domestic Packaging Consortium"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Description</label>
            <input
              v-model="optionForm.description"
              type="text"
              placeholder="Short summary of this strategic path..."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Advantages (one per line)</label>
              <textarea
                v-model="optionForm.advantages"
                rows="3"
                placeholder="Key strategic advantages..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Disadvantages (one per line)</label>
              <textarea
                v-model="optionForm.disadvantages"
                rows="3"
                placeholder="Downsides and constraints..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Risks & Trade-offs (one per line)</label>
              <textarea
                v-model="optionForm.risks"
                rows="2"
                placeholder="Failure modes..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
            <div>
              <label class="block text-xs font-semibold text-nexo-ink mb-1">Expected Outcomes</label>
              <textarea
                v-model="optionForm.expected_outcomes"
                rows="2"
                placeholder="Targeted financial/strategic outcomes..."
                class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
              ></textarea>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="isAddOptionModalOpen = false"
              class="rounded-[2px] border border-nexo-border px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2"
            >
              Cancel
            </button>
            <button
              @click="handleAddOption"
              :disabled="isSubmitting"
              class="rounded-[2px] bg-nexo-ink px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#1A2C42] disabled:opacity-50"
            >
              {{ isSubmitting ? 'Saving...' : 'Add Option' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Add Milestone Modal -->
      <div v-if="isAddMilestoneModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-[4px] border border-nexo-border bg-white p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Add Execution Milestone</h3>
            <button @click="isAddMilestoneModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Milestone Title</label>
            <input
              v-model="milestoneForm.title"
              type="text"
              placeholder="e.g. Sign Letter of Intent with consortium partners"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Target Date</label>
            <input
              v-model="milestoneForm.target_date"
              type="date"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Observability Notes / Criteria</label>
            <textarea
              v-model="milestoneForm.notes"
              rows="2"
              placeholder="Observable criteria confirming milestone completion..."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="isAddMilestoneModalOpen = false"
              class="rounded-[2px] border border-nexo-border px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2"
            >
              Cancel
            </button>
            <button
              @click="handleAddMilestone"
              :disabled="isSubmitting"
              class="rounded-[2px] bg-nexo-ink px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#1A2C42] disabled:opacity-50"
            >
              {{ isSubmitting ? 'Saving...' : 'Add Milestone' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Human Executive Approval Modal -->
      <div v-if="isApproveModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-lg rounded-[4px] border border-nexo-border bg-white p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <div class="flex items-center gap-2">
              <UserCheck class="h-4 w-4 text-emerald-700" />
              <h3 class="font-serif text-lg font-bold text-nexo-ink">Executive Decision Authorization</h3>
            </div>
            <button @click="isApproveModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <p class="text-xs text-nexo-text-2 leading-relaxed">
            As executive decision maker, you are recording an official decision. This action is permanently logged to the immutable organization audit trail.
          </p>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Select Chosen Option</label>
            <select
              v-model="approveForm.selected_option_id"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            >
              <option v-for="opt in selectedDecision?.options || []" :key="opt.id" :value="opt.id">
                {{ opt.option_name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Recorded Decision Statement</label>
            <textarea
              v-model="approveForm.recorded_decision"
              rows="3"
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Immediate Next Steps</label>
            <input
              v-model="approveForm.next_steps"
              type="text"
              placeholder="e.g. Instruct legal team to draft formal partnership agreement."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="isApproveModalOpen = false"
              class="rounded-[2px] border border-nexo-border px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2"
            >
              Cancel
            </button>
            <button
              @click="handleApproveDecision"
              :disabled="isSubmitting || !approveForm.selected_option_id"
              class="rounded-[2px] bg-emerald-700 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
            >
              {{ isSubmitting ? 'Recording...' : 'Authorize Decision' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Post-Review Retrospective Modal -->
      <div v-if="isPostReviewModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-[4px] border border-nexo-border bg-white p-6 shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Post-Decision Retrospective Review</h3>
            <button @click="isPostReviewModalOpen = false"><X class="h-4 w-4" /></button>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Retrospective Notes</label>
            <textarea
              v-model="reviewForm.notes"
              rows="3"
              placeholder="Evaluate implementation progress and divergence from initial intelligence..."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Changed Assumptions (one per line)</label>
            <textarea
              v-model="reviewForm.changed_assumptions"
              rows="2"
              placeholder="Assumptions that proved inaccurate..."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Observed Results vs Expected</label>
            <textarea
              v-model="reviewForm.observed_results_vs_expected"
              rows="2"
              placeholder="Variance between projection and execution..."
              class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-nexo-ink mb-1">Action Taken</label>
            <select v-model="reviewForm.action_taken" class="w-full rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1.5 text-xs text-nexo-ink">
              <option value="monitor_continuation">Continue Monitoring Execution</option>
              <option value="course_correction_required">Initiate Strategic Course Correction</option>
              <option value="objective_achieved">Decision Objectives Fully Achieved</option>
              <option value="aborted">Execution Aborted</option>
            </select>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button
              @click="isPostReviewModalOpen = false"
              class="rounded-[2px] border border-nexo-border px-3 py-1.5 text-xs font-semibold text-nexo-ink hover:bg-nexo-paper-2"
            >
              Cancel
            </button>
            <button
              @click="handlePostReview"
              :disabled="isSubmitting || !reviewForm.notes"
              class="rounded-[2px] bg-nexo-ink px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#1A2C42] disabled:opacity-50"
            >
              {{ isSubmitting ? 'Logging...' : 'Save Retrospective' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
