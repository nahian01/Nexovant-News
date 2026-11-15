<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { workspaceService } from '@/services/api/workspace.service'
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
  Search
} from 'lucide-vue-next'

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
const isLoading = ref(true)
const isSubmitting = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

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

const loadMonitors = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await workspaceService.getMonitors()
    if (res.success) {
      monitors.value = res.monitors
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load custom intelligence monitors.'
  } finally {
    isLoading.value = false
  }
}

const handleToggleActive = async (id: string) => {
  try {
    const res = await workspaceService.toggleMonitor(id)
    if (res.success) {
      statusMessage.value = res.message
      await loadMonitors()
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
      await loadMonitors()
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
      await loadMonitors()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.name?.[0] || 'Failed to activate monitor.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  loadMonitors()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Personal Workspace</span>
              <span>/</span>
              <span>Automated Intelligence Monitors</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
              Custom Intelligence Monitors
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Configure real-time monitoring rules across keywords, exclusion terms, verified sources, and target corporate entities.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="primary" size="sm" @click="isCreateModalOpen = true">
              <Plus class="w-3.5 h-3.5 mr-1" /> New Monitor Rule
            </Button>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="statusMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✓ {{ statusMessage }}</span>
          <button @click="statusMessage = ''" class="text-emerald-600 font-bold ml-2">✕</button>
        </div>

        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✕ {{ errorMessage }}</span>
          <button @click="errorMessage = ''" class="text-rose-600 font-bold ml-2">✕</button>
        </div>

        <!-- Monitors Grid -->
        <div v-if="isLoading" class="text-xs text-nexo-text-muted py-12 text-center">
          Loading active intelligence monitors...
        </div>

        <div v-else-if="monitors.length === 0" class="p-12 bg-white rounded-lg border border-nexo-border text-center space-y-2">
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
              <!-- Top Badges -->
              <div class="flex items-center justify-between">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase"
                  :class="m.is_active ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500'"
                >
                  {{ m.is_active ? 'Active Daemon' : 'Paused' }}
                </span>
                <span class="text-[11px] font-mono text-nexo-text-muted">
                  {{ m.match_count }} Matches
                </span>
              </div>

              <!-- Title -->
              <div>
                <h3 class="font-serif font-bold text-nexo-text-primary text-base leading-snug">
                  {{ m.name }}
                </h3>
                <span class="text-[10px] font-mono text-amber-800 uppercase block mt-0.5">
                  Cadence: {{ m.alert_frequency.replace('_', ' ') }}
                </span>
              </div>

              <!-- Keyword Pills -->
              <div class="space-y-1.5">
                <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Matching Terms:</div>
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="(kw, idx) in m.keywords"
                    :key="idx"
                    class="px-2 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200 text-[11px] font-mono"
                  >
                    {{ kw }}
                  </span>
                </div>
              </div>

              <!-- Excluded terms if present -->
              <div v-if="m.excluded_terms && m.excluded_terms.length > 0" class="space-y-1">
                <div class="text-[10px] font-mono uppercase text-rose-500">Excluded Noise Terms:</div>
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="(ex, idx) in m.excluded_terms"
                    :key="'ex-' + idx"
                    class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-mono line-through"
                  >
                    {{ ex }}
                  </span>
                </div>
              </div>

              <!-- Verification Gate Status -->
              <div class="p-2 bg-slate-50 rounded border border-slate-100 text-[11px] font-mono text-nexo-text-secondary space-y-0.5">
                <div class="flex items-center justify-between">
                  <span>Min Credibility:</span>
                  <span class="font-bold text-nexo-text-primary">{{ Math.round(m.min_credibility_score * 100) }}%</span>
                </div>
                <div class="flex items-center justify-between">
                  <span>Verified Only:</span>
                  <span :class="m.require_verified_only ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    {{ m.require_verified_only ? 'Enforced' : 'All Tiers' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Footer Controls -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[10px] font-mono text-nexo-text-muted">
                {{ m.last_triggered_at ? `Triggered: ${new Date(m.last_triggered_at).toLocaleDateString()}` : 'No alerts yet' }}
              </span>

              <div class="flex items-center gap-1.5">
                <Button
                  variant="outline"
                  size="sm"
                  @click="handleToggleActive(m.id)"
                  :title="m.is_active ? 'Pause Monitor' : 'Resume Monitor'"
                >
                  <component :is="m.is_active ? Pause : Play" class="w-3.5 h-3.5" />
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  @click="handleDeleteMonitor(m.id)"
                  title="Delete Monitor"
                >
                  <Trash2 class="w-3.5 h-3.5 text-rose-500" />
                </Button>
              </div>
            </div>
          </Card>
        </div>
      </main>
    </div>

    <!-- MODAL: Create Monitor -->
    <Modal :is-open="isCreateModalOpen" title="Configure Custom Intelligence Monitor" @close="isCreateModalOpen = false">
      <form @submit.prevent="handleCreateMonitor" class="space-y-4 text-left max-h-[75vh] overflow-y-auto pr-1">
        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Monitor Rule Name</label>
          <Input v-model="createForm.name" placeholder="e.g. EU Advanced Packaging & Subsidies" required />
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Keywords / Search Terms (Comma-separated)</label>
          <Input v-model="createForm.keywordsRaw" placeholder="e.g. advanced packaging, High-NA, Dresden fab" required />
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Noise Filter: Excluded Terms (Comma-separated)</label>
          <Input v-model="createForm.excludedTermsRaw" placeholder="e.g. unconfirmed rumor, opinion editorial" />
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Target Entities (Optional)</label>
          <Input v-model="createForm.entityNamesRaw" placeholder="e.g. TSMC, ASML, Intel" />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Alert Frequency</label>
            <select
              v-model="createForm.alert_frequency"
              class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
            >
              <option value="real_time">Real-Time (Instant)</option>
              <option value="daily_digest">Daily Morning Digest</option>
              <option value="weekly">Weekly Summary</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">
              Min Credibility ({{ Math.round(createForm.min_credibility_score * 100) }}%)
            </label>
            <input
              type="range"
              min="0.5"
              max="1.0"
              step="0.05"
              v-model.number="createForm.min_credibility_score"
              class="w-full accent-amber-500 mt-2"
            />
          </div>
        </div>

        <div class="p-3 bg-slate-50 border border-slate-200 rounded space-y-2 text-xs">
          <div class="flex items-center gap-2">
            <input type="checkbox" id="mon_ver" v-model="createForm.require_verified_only" class="rounded border-slate-300" />
            <label for="mon_ver" class="font-medium text-nexo-text-primary">
              Strictly require verified or corroborated status (reject unverified claims)
            </label>
          </div>

          <div class="flex items-center gap-4 pt-1 font-mono text-[11px]">
            <span class="text-nexo-text-muted">Delivery Channels:</span>
            <label class="flex items-center gap-1 cursor-pointer">
              <input type="checkbox" v-model="createForm.delivery_in_app" /> In-App
            </label>
            <label class="flex items-center gap-1 cursor-pointer">
              <input type="checkbox" v-model="createForm.delivery_email" /> Outbound Email
            </label>
          </div>
        </div>

        <!-- Preview Test Button & Match Box -->
        <div class="border-t border-slate-200 pt-3 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-mono text-nexo-text-muted">Test Rule against Recent Corpus:</span>
            <Button variant="outline" size="sm" type="button" :disabled="isPreviewing" @click="handlePreviewRule">
              <Search class="w-3.5 h-3.5 mr-1" />
              {{ isPreviewing ? 'Evaluating...' : 'Preview Matches' }}
            </Button>
          </div>

          <div v-if="previewMatches.length > 0" class="p-2.5 bg-slate-100 rounded border border-slate-200 text-xs space-y-1">
            <div class="font-bold text-nexo-text-primary text-[11px]">Sample Matched Records:</div>
            <div v-for="m in previewMatches.slice(0, 3)" :key="m.id" class="text-[11px] text-nexo-text-secondary truncate">
              • {{ m.title }}
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="outline" size="sm" type="button" @click="isCreateModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">Activate Monitor Rule</Button>
        </div>
      </form>
    </Modal>
  </div>
</template>
