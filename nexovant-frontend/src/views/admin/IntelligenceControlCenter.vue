<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Activity,
  Radio,
  Layers3,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Zap,
  Pause,
  Play,
  Eye,
  GitMerge,
  Split,
  Check,
  X,
  ExternalLink,
  Sliders,
  Clock,
  BarChart3,
  Search,
  Filter,
  ShieldCheck,
  ChevronRight,
  Sparkles,
  Lock,
  FileText
} from 'lucide-vue-next'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'

const route = useRoute()
const router = useRouter()

// Active tab management
const activeTab = ref<'dashboard' | 'ingestion' | 'discoveries' | 'clusters' | 'facts' | 'pipeline' | 'automation'>('dashboard')

// State
const isLoading = ref(true)
const actionInProgress = ref(false)
const notificationMessage = ref('')
const errorMessage = ref('')

// Data
const dashboardData = ref<any>(null)
const ingestionSources = ref<any[]>([])
const discoveries = ref<any[]>([])
const clusters = ref<any[]>([])
const facts = ref<any[]>([])
const pipelineJobs = ref<any[]>([])
const automationSettings = ref<any>({
  publishing_mode: 'AUTO_REVIEW',
  min_source_credibility: 0.70,
  min_fact_consensus_sources: 2,
  feed_fetch_interval_minutes: 15,
  feed_timeout_seconds: 15,
  auto_clustering_threshold: 0.45,
  is_ingestion_paused: false,
})

// Modal states
const inspectStory = ref<any>(null)
const isInspectModalOpen = ref(false)

const inspectCluster = ref<any>(null)
const isClusterModalOpen = ref(false)

const isMergeModalOpen = ref(false)
const mergePrimaryId = ref('')
const mergeSecondaryId = ref('')
const mergeReason = ref('')

const isFactModalOpen = ref(false)
const editingFact = ref<any>(null)
const factStatusChoice = ref('supported_multiple')
const factAuditNotes = ref('')

// Filters
const discoveryFilterStatus = ref('all')
const factFilterStatus = ref('all')
const searchQuery = ref('')

// Helper for banner notifications
const notify = (msg: string, isError = false) => {
  if (isError) {
    errorMessage.value = msg
    notificationMessage.value = ''
  } else {
    notificationMessage.value = msg
    errorMessage.value = ''
  }
  setTimeout(() => {
    notificationMessage.value = ''
    errorMessage.value = ''
  }, 6000)
}

// Fetch tab data
const fetchTabData = async () => {
  isLoading.value = true
  try {
    if (activeTab.value === 'dashboard') {
      dashboardData.value = await adminService.getIntelligenceDashboard()
    } else if (activeTab.value === 'ingestion') {
      const res = await adminService.getIntelligenceIngestion()
      ingestionSources.value = res.sources || []
      automationSettings.value.is_ingestion_paused = res.is_paused || false
    } else if (activeTab.value === 'discoveries') {
      const res = await adminService.getIntelligenceDiscoveries({
        status: discoveryFilterStatus.value !== 'all' ? discoveryFilterStatus.value : undefined,
        search: searchQuery.value || undefined,
      })
      discoveries.value = res.data?.data || res.data || []
    } else if (activeTab.value === 'clusters') {
      const res = await adminService.getIntelligenceClusters({
        search: searchQuery.value || undefined,
      })
      clusters.value = res.data?.data || res.data || []
    } else if (activeTab.value === 'facts') {
      const res = await adminService.getIntelligenceFacts({
        status: factFilterStatus.value !== 'all' ? factFilterStatus.value : undefined,
        search: searchQuery.value || undefined,
      })
      facts.value = res.data?.data || res.data || []
    } else if (activeTab.value === 'pipeline') {
      const res = await adminService.getIntelligencePipeline()
      pipelineJobs.value = res.data?.data || res.data || []
    } else if (activeTab.value === 'automation') {
      automationSettings.value = await adminService.getIntelligenceAutomationSettings()
    }
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Error loading intelligence data', true)
  } finally {
    isLoading.value = false
  }
}

// Switch tabs and update route query if desirable
const switchTab = (tab: typeof activeTab.value) => {
  activeTab.value = tab
  fetchTabData()
}

// Ingestion Actions
const triggerIngestion = async (sourceId?: string) => {
  actionInProgress.value = true
  try {
    const res = await adminService.triggerIntelligenceIngestion(sourceId)
    notify(res.message || 'Intelligence ingestion dispatched successfully.')
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Failed to dispatch ingestion', true)
  } finally {
    actionInProgress.value = false
  }
}

const togglePauseIngestion = async () => {
  actionInProgress.value = true
  const newPaused = !automationSettings.value.is_ingestion_paused
  try {
    const res = await adminService.toggleIntelligencePause(newPaused, 'Toggled from Control Center UI')
    automationSettings.value.is_ingestion_paused = res.is_paused
    notify(res.message)
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Failed to toggle pause state', true)
  } finally {
    actionInProgress.value = false
  }
}

// Discovery Actions
const handleFalseDuplicate = async (story: any) => {
  if (!confirm(`Mark "${story.title}" as FALSE DUPLICATE? This restores it for independent clustering.`)) return
  actionInProgress.value = true
  try {
    const res = await adminService.markDiscoveryFalseDuplicate(story.id, 'Manually un-flagged via Admin Control Center')
    notify(res.message)
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Action failed', true)
  } finally {
    actionInProgress.value = false
  }
}

const handleExcludeStory = async (story: any) => {
  const reason = prompt(`Reason for excluding "${story.title}":`, 'Editorial irrelevance or low quality')
  if (!reason) return
  actionInProgress.value = true
  try {
    const res = await adminService.excludeDiscovery(story.id, reason)
    notify(res.message)
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Action failed', true)
  } finally {
    actionInProgress.value = false
  }
}

// Cluster Actions
const handleMergeClusters = async () => {
  if (!mergePrimaryId.value || !mergeSecondaryId.value) {
    notify('Both primary and secondary cluster IDs are required', true)
    return
  }
  actionInProgress.value = true
  try {
    const res = await adminService.mergeIntelligenceClusters(
      mergePrimaryId.value,
      mergeSecondaryId.value,
      mergeReason.value || 'Manual editorial consolidation'
    )
    notify(res.message)
    isMergeModalOpen.value = false
    mergePrimaryId.value = ''
    mergeSecondaryId.value = ''
    mergeReason.value = ''
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Merge failed', true)
  } finally {
    actionInProgress.value = false
  }
}

const handleSplitCluster = async (cluster: any) => {
  if (!confirm(`Are you sure you want to split secondary reports from cluster "${cluster.headline}" into a new event?`)) return
  actionInProgress.value = true
  try {
    const secondaryIds = cluster.stories?.filter((s: any) => !s.pivot?.is_primary)?.map((s: any) => s.id) || []
    const res = await adminService.splitIntelligenceCluster(cluster.id, secondaryIds, 'Manual split from UI')
    notify(res.message)
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Split failed', true)
  } finally {
    actionInProgress.value = false
  }
}

// Fact Verification Actions
const openEditFact = (fact: any) => {
  editingFact.value = fact
  factStatusChoice.value = fact.verification_status || 'supported_multiple'
  factAuditNotes.value = fact.verification_notes || ''
  isFactModalOpen.value = true
}

const saveFactStatus = async () => {
  if (!editingFact.value) return
  actionInProgress.value = true
  try {
    const res = await adminService.updateFactStatus(
      editingFact.value.id,
      factStatusChoice.value,
      factAuditNotes.value
    )
    notify(res.message)
    isFactModalOpen.value = false
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Failed to update fact verification', true)
  } finally {
    actionInProgress.value = false
  }
}

// Pipeline Actions
const retryPipelineJob = async (id: string) => {
  actionInProgress.value = true
  try {
    const res = await adminService.retryPipelineJob(id)
    notify(res.message)
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Failed to retry job', true)
  } finally {
    actionInProgress.value = false
  }
}

const cancelPipelineJob = async (id: string) => {
  actionInProgress.value = true
  try {
    const res = await adminService.cancelPipelineJob(id)
    notify(res.message)
    await fetchTabData()
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Failed to cancel job', true)
  } finally {
    actionInProgress.value = false
  }
}

// Save Automation Settings
const saveAutomationSettings = async () => {
  actionInProgress.value = true
  try {
    const res = await adminService.updateIntelligenceAutomationSettings(automationSettings.value)
    notify(res.message)
  } catch (err: any) {
    notify(err?.response?.data?.message || 'Failed to save settings', true)
  } finally {
    actionInProgress.value = false
  }
}

onMounted(() => {
  // Check if route has tab param
  if (route.query.tab) {
    activeTab.value = route.query.tab as any
  }
  fetchTabData()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Top Header & Primary Controls -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-nexo-border/80 pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-nexo-accent-gold">
            <Sparkles class="h-4 w-4" />
            <span>Autonomous Intelligence & Editorial Automation</span>
          </div>
          <h1 class="editorial-title text-2xl md:text-3xl font-medium text-nexo-text-primary mt-1">
            Intelligence Control Center
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1 max-w-3xl">
            Continuous technology & business signal ingestion, cross-publisher deduplication, atomic fact extraction, multi-source consensus verification, and editorial routing.
          </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <Button
            variant="outline"
            size="sm"
            :disabled="actionInProgress"
            @click="togglePauseIngestion"
            :class="automationSettings.is_ingestion_paused ? 'border-amber-500 text-amber-600 bg-amber-50' : ''"
          >
            <component :is="automationSettings.is_ingestion_paused ? Play : Pause" class="h-3.5 w-3.5 mr-1.5 inline" />
            {{ automationSettings.is_ingestion_paused ? 'Resume Pipeline' : 'Pause Pipeline' }}
          </Button>

          <Button
            variant="primary"
            size="sm"
            :disabled="actionInProgress"
            @click="triggerIngestion()"
            class="bg-nexo-accent-gold hover:bg-[#8e6e18] text-white shadow-xs"
          >
            <RefreshCw :class="['h-3.5 w-3.5 mr-1.5 inline', actionInProgress ? 'animate-spin' : '']" />
            Trigger Ingestion Cycle
          </Button>
        </div>
      </div>

      <!-- Alert Banners -->
      <div v-if="notificationMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded-lg flex items-center gap-2">
        <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
        <span>{{ notificationMessage }}</span>
      </div>
      <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded-lg flex items-center gap-2">
        <AlertTriangle class="h-4 w-4 shrink-0 text-rose-600" />
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-1 overflow-x-auto border-b border-nexo-border pb-1">
        <button
          v-for="t in [
            { id: 'dashboard', label: 'Overview & Funnel', icon: Activity },
            { id: 'ingestion', label: 'Ingestion Sources', icon: Radio },
            { id: 'discoveries', label: 'Discovered Stream', icon: FileText },
            { id: 'clusters', label: 'Story Clusters', icon: Layers3 },
            { id: 'facts', label: 'Fact Verification', icon: ShieldCheck },
            { id: 'pipeline', label: '14-Stage Pipeline', icon: Zap },
            { id: 'automation', label: 'Automation Policy', icon: Sliders },
          ]"
          :key="t.id"
          @click="switchTab(t.id as any)"
          :class="[
            'flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-lg transition whitespace-nowrap',
            activeTab === t.id
              ? 'bg-amber-100 text-nexo-accent-gold font-semibold border border-amber-300'
              : 'text-nexo-text-secondary hover:text-nexo-text-primary hover:bg-nexo-bg'
          ]"
        >
          <component :is="t.icon" class="h-3.5 w-3.5" />
          {{ t.label }}
        </button>
      </div>

      <!-- TAB 1: OVERVIEW & FUNNEL -->
      <div v-if="activeTab === 'dashboard'" class="space-y-6">
        <!-- Metric Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Card class="p-4 border border-nexo-border">
            <span class="text-[10px] font-mono uppercase tracking-wider text-nexo-text-muted">Total Discovered</span>
            <div class="text-2xl font-bold text-nexo-text-primary mt-1">
              {{ dashboardData?.metrics?.total_discovered ?? '...' }}
            </div>
            <div class="text-[11px] text-nexo-text-secondary mt-1 flex items-center gap-1">
              <span>{{ dashboardData?.metrics?.pending_processing ?? 0 }} pending processing</span>
            </div>
          </Card>

          <Card class="p-4 border border-nexo-border">
            <span class="text-[10px] font-mono uppercase tracking-wider text-nexo-text-muted">Active Clusters</span>
            <div class="text-2xl font-bold text-nexo-text-primary mt-1">
              {{ dashboardData?.metrics?.active_clusters ?? '...' }}
            </div>
            <div class="text-[11px] text-nexo-text-secondary mt-1">
              Multi-source synthesized stories
            </div>
          </Card>

          <Card class="p-4 border border-nexo-border">
            <span class="text-[10px] font-mono uppercase tracking-wider text-nexo-text-muted">Verified Facts</span>
            <div class="text-2xl font-bold text-emerald-600 mt-1">
              {{ dashboardData?.metrics?.verified_facts ?? '...' }}
            </div>
            <div class="text-[11px] text-nexo-text-secondary mt-1">
              Multi-publisher consensus verified
            </div>
          </Card>

          <Card class="p-4 border border-nexo-border">
            <span class="text-[10px] font-mono uppercase tracking-wider text-nexo-text-muted">Pipeline Health</span>
            <div class="text-2xl font-bold text-nexo-text-primary mt-1">
              {{ dashboardData?.pipeline_summary?.health_status ?? 'HEALTHY' }}
            </div>
            <div class="text-[11px] text-nexo-text-secondary mt-1">
              {{ dashboardData?.metrics?.active_sources ?? 0 }} monitored wire sources
            </div>
          </Card>
        </div>

        <!-- Funnel Architecture Graphic -->
        <Card class="p-6 border border-nexo-border">
          <div class="flex items-center justify-between border-b border-nexo-border/60 pb-3 mb-4">
            <div>
              <h2 class="text-sm font-semibold text-nexo-text-primary">Autonomous Ingestion & Synthesis Funnel</h2>
              <p class="text-xs text-nexo-text-secondary">Stage-by-stage progression from raw wire ingest to publication</p>
            </div>
            <span class="font-mono text-[10px] px-2 py-1 rounded bg-slate-100 text-slate-700 border border-slate-200 uppercase font-semibold">
              MODE: {{ automationSettings.publishing_mode }}
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-5 gap-3 text-center">
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
              <span class="text-[10px] font-mono uppercase text-slate-500">1. Feeds & Extraction</span>
              <div class="text-lg font-bold text-slate-800 mt-1">{{ dashboardData?.metrics?.total_discovered ?? 0 }}</div>
              <p class="text-[10px] text-slate-600 mt-0.5">SSRF-protected, canonicalized</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
              <span class="text-[10px] font-mono uppercase text-slate-500">2. Deduplication</span>
              <div class="text-lg font-bold text-slate-800 mt-1">{{ dashboardData?.metrics?.deduplicated_count ?? 0 }}</div>
              <p class="text-[10px] text-slate-600 mt-0.5">Hash & fuzzy match filtered</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
              <span class="text-[10px] font-mono uppercase text-slate-500">3. Multi-Wire Clusters</span>
              <div class="text-lg font-bold text-slate-800 mt-1">{{ dashboardData?.metrics?.active_clusters ?? 0 }}</div>
              <p class="text-[10px] text-slate-600 mt-0.5">Semantic grouping (72h)</p>
            </div>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
              <span class="text-[10px] font-mono uppercase text-slate-500">4. Fact Consensus</span>
              <div class="text-lg font-bold text-emerald-700 mt-1">{{ dashboardData?.metrics?.verified_facts ?? 0 }}</div>
              <p class="text-[10px] text-slate-600 mt-0.5">Cross-source supported</p>
            </div>
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
              <span class="text-[10px] font-mono uppercase text-amber-700">5. Articles Generated</span>
              <div class="text-lg font-bold text-amber-900 mt-1">{{ dashboardData?.metrics?.articles_generated ?? 0 }}</div>
              <p class="text-[10px] text-amber-700 mt-0.5">Preserving manual locks</p>
            </div>
          </div>
        </Card>

        <!-- Recent Discoveries & Active Pipeline Jobs -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card class="p-5 border border-nexo-border">
            <div class="flex items-center justify-between border-b border-nexo-border/60 pb-3 mb-3">
              <h3 class="text-xs font-semibold text-nexo-text-primary uppercase tracking-wider">Latest Wire Ingestions</h3>
              <button @click="switchTab('discoveries')" class="text-xs text-nexo-accent-gold hover:underline flex items-center gap-1">
                View all <ChevronRight class="h-3 w-3" />
              </button>
            </div>
            <div class="space-y-3">
              <div
                v-for="item in dashboardData?.recent_discoveries || []"
                :key="item.id"
                class="p-2.5 bg-nexo-bg/50 rounded-lg border border-nexo-border/60 flex items-start justify-between gap-3"
              >
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2 text-[10px] font-mono text-nexo-text-muted">
                    <span class="text-amber-600 font-semibold">{{ item.source?.name || 'Feed' }}</span>
                    <span>•</span>
                    <span>{{ new Date(item.discovered_at || item.created_at).toLocaleTimeString() }}</span>
                  </div>
                  <p class="text-xs font-medium text-nexo-text-primary truncate mt-0.5">{{ item.title }}</p>
                </div>
                <span
                  :class="[
                    'text-[9px] uppercase font-mono px-2 py-0.5 rounded font-semibold',
                    item.processing_status === 'processed' ? 'bg-emerald-100 text-emerald-800' :
                    item.processing_status === 'duplicate' ? 'bg-slate-100 text-slate-700' :
                    'bg-amber-100 text-amber-800'
                  ]"
                >
                  {{ item.processing_status }}
                </span>
              </div>
              <div v-if="!dashboardData?.recent_discoveries?.length" class="text-center py-6 text-xs text-nexo-text-muted">
                No wire items ingested in recent cycle.
              </div>
            </div>
          </Card>

          <Card class="p-5 border border-nexo-border">
            <div class="flex items-center justify-between border-b border-nexo-border/60 pb-3 mb-3">
              <h3 class="text-xs font-semibold text-nexo-text-primary uppercase tracking-wider">Active Pipeline Jobs</h3>
              <button @click="switchTab('pipeline')" class="text-xs text-nexo-accent-gold hover:underline flex items-center gap-1">
                Monitor queue <ChevronRight class="h-3 w-3" />
              </button>
            </div>
            <div class="space-y-3">
              <div
                v-for="job in dashboardData?.recent_pipeline_jobs || []"
                :key="job.id"
                class="p-2.5 bg-nexo-bg/50 rounded-lg border border-nexo-border/60 flex items-center justify-between gap-3"
              >
                <div>
                  <div class="text-xs font-mono font-medium text-nexo-text-primary">{{ job.job_name || job.stage }}</div>
                  <div class="text-[10px] text-nexo-text-muted font-mono">Stage: {{ job.stage }} • Target: {{ job.target_type }}</div>
                </div>
                <span
                  :class="[
                    'text-[9px] uppercase font-mono px-2 py-0.5 rounded font-semibold',
                    job.status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' :
                    job.status === 'FAILED' ? 'bg-rose-100 text-rose-800' :
                    'bg-amber-100 text-amber-800'
                  ]"
                >
                  {{ job.status }}
                </span>
              </div>
              <div v-if="!dashboardData?.recent_pipeline_jobs?.length" class="text-center py-6 text-xs text-nexo-text-muted">
                No active background tasks running.
              </div>
            </div>
          </Card>
        </div>
      </div>

      <!-- TAB 2: INGESTION SOURCES -->
      <div v-if="activeTab === 'ingestion'" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="text-xs text-nexo-text-secondary">
            Configured RSS 2.0 / Atom wire feeds with conditional caching and SSRF immunity.
          </div>
          <Button size="sm" variant="outline" @click="fetchTabData">
            <RefreshCw class="h-3.5 w-3.5 mr-1" /> Refresh Feeds
          </Button>
        </div>

        <div class="overflow-x-auto border border-nexo-border rounded-xl bg-white shadow-2xs">
          <table class="w-full text-xs text-left">
            <thead class="bg-nexo-bg/70 text-nexo-text-muted font-mono uppercase text-[10px] border-b border-nexo-border">
              <tr>
                <th class="p-3">Source & Publisher</th>
                <th class="p-3">Category & Region</th>
                <th class="p-3">Trust Tier</th>
                <th class="p-3">Cache Headers</th>
                <th class="p-3">Last Fetch</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border">
              <tr v-for="source in ingestionSources" :key="source.id" class="hover:bg-slate-50/50">
                <td class="p-3 font-medium text-nexo-text-primary">
                  <div>{{ source.name }}</div>
                  <div class="text-[10px] text-nexo-text-muted font-mono truncate max-w-xs">{{ source.url }}</div>
                </td>
                <td class="p-3">
                  <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-[10px] font-mono text-slate-700">
                    {{ source.source_category || 'general' }}
                  </span>
                  <span class="text-[10px] text-nexo-text-muted ml-1.5 font-mono">({{ source.geographic_region || 'GLOBAL' }})</span>
                </td>
                <td class="p-3">
                  <span
                    :class="[
                      'text-[9px] uppercase font-mono px-2 py-0.5 rounded font-semibold',
                      source.trust_tier === 'TIER_1' ? 'bg-emerald-100 text-emerald-800' :
                      source.trust_tier === 'TIER_2' ? 'bg-amber-100 text-amber-800' :
                      'bg-slate-100 text-slate-700'
                    ]"
                  >
                    {{ source.trust_tier || 'TIER_2' }}
                  </span>
                </td>
                <td class="p-3 font-mono text-[10px] text-nexo-text-muted">
                  <div v-if="source.etag">ETag: {{ source.etag.substring(0, 14) }}...</div>
                  <div v-if="source.last_modified_header">{{ source.last_modified_header }}</div>
                  <div v-if="!source.etag && !source.last_modified_header" class="text-slate-400">None cached</div>
                </td>
                <td class="p-3 font-mono text-[10px] text-nexo-text-secondary">
                  <div>{{ source.last_successful_fetch_at ? new Date(source.last_successful_fetch_at).toLocaleString() : 'Never' }}</div>
                  <div class="text-slate-400">Interval: {{ source.fetch_interval_minutes || 15 }}m</div>
                </td>
                <td class="p-3 text-right">
                  <Button
                    size="sm"
                    variant="secondary"
                    :disabled="actionInProgress"
                    @click="triggerIngestion(source.id)"
                    class="font-mono text-[10px]"
                  >
                    <Zap class="h-3 w-3 mr-1 inline" /> Fetch Now
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: DISCOVERED STORIES STREAM -->
      <div v-if="activeTab === 'discoveries'" class="space-y-4">
        <!-- Filter Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <select
              v-model="discoveryFilterStatus"
              @change="fetchTabData"
              class="px-3 py-1.5 text-xs bg-white border border-nexo-border rounded-lg text-nexo-text-primary focus:outline-none"
            >
              <option value="all">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="processed">Processed</option>
              <option value="duplicate">Duplicate</option>
              <option value="excluded">Excluded</option>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <div class="relative">
              <Search class="h-3.5 w-3.5 absolute left-2.5 top-2.5 text-nexo-text-muted" />
              <input
                v-model="searchQuery"
                @keyup.enter="fetchTabData"
                placeholder="Search stories by headline..."
                class="pl-8 pr-3 py-1.5 text-xs bg-white border border-nexo-border rounded-lg text-nexo-text-primary focus:outline-none w-64"
              />
            </div>
            <Button size="sm" variant="outline" @click="fetchTabData">
              Filter
            </Button>
          </div>
        </div>

        <!-- Table of Discoveries -->
        <div class="overflow-x-auto border border-nexo-border rounded-xl bg-white shadow-2xs">
          <table class="w-full text-xs text-left">
            <thead class="bg-nexo-bg/70 text-nexo-text-muted font-mono uppercase text-[10px] border-b border-nexo-border">
              <tr>
                <th class="p-3">Headline & Source</th>
                <th class="p-3">Canonical URL</th>
                <th class="p-3">Published Date</th>
                <th class="p-3">Cluster</th>
                <th class="p-3">Status</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border">
              <tr v-for="story in discoveries" :key="story.id" class="hover:bg-slate-50/50">
                <td class="p-3 font-medium text-nexo-text-primary max-w-sm">
                  <div class="font-semibold">{{ story.title }}</div>
                  <div class="text-[10px] text-amber-700 font-mono mt-0.5">
                    {{ story.source?.name || 'Feed Wire' }}
                  </div>
                </td>
                <td class="p-3 font-mono text-[10px] text-nexo-text-muted max-w-xs truncate">
                  <a :href="story.canonical_url" target="_blank" class="hover:text-amber-600 flex items-center gap-1">
                    {{ story.canonical_url }} <ExternalLink class="h-2.5 w-2.5 inline" />
                  </a>
                </td>
                <td class="p-3 font-mono text-[10px] text-nexo-text-secondary whitespace-nowrap">
                  {{ story.published_at ? new Date(story.published_at).toLocaleString() : 'N/A' }}
                </td>
                <td class="p-3 font-mono text-[10px]">
                  <span v-if="story.cluster_id" class="text-amber-600 font-semibold">
                    #{{ story.cluster_id.substring(0, 8) }}
                  </span>
                  <span v-else class="text-slate-400">Unassigned</span>
                </td>
                <td class="p-3">
                  <span
                    :class="[
                      'text-[9px] uppercase font-mono px-2 py-0.5 rounded font-semibold',
                      story.processing_status === 'processed' ? 'bg-emerald-100 text-emerald-800' :
                      story.processing_status === 'duplicate' ? 'bg-slate-100 text-slate-700' :
                      story.processing_status === 'excluded' ? 'bg-rose-100 text-rose-800' :
                      'bg-amber-100 text-amber-800'
                    ]"
                  >
                    {{ story.processing_status }}
                  </span>
                </td>
                <td class="p-3 text-right whitespace-nowrap space-x-1.5">
                  <Button
                    size="sm"
                    variant="outline"
                    @click="inspectStory = story; isInspectModalOpen = true"
                  >
                    <Eye class="h-3 w-3 inline" />
                  </Button>
                  <Button
                    v-if="story.is_duplicate"
                    size="sm"
                    variant="secondary"
                    :disabled="actionInProgress"
                    @click="handleFalseDuplicate(story)"
                    title="Mark False Duplicate"
                  >
                    Un-duplicate
                  </Button>
                  <Button
                    v-if="story.processing_status !== 'excluded'"
                    size="sm"
                    variant="danger"
                    :disabled="actionInProgress"
                    @click="handleExcludeStory(story)"
                    title="Exclude Story"
                  >
                    <X class="h-3 w-3 inline" />
                  </Button>
                </td>
              </tr>
              <tr v-if="!discoveries.length">
                <td colspan="6" class="p-6 text-center text-xs text-nexo-text-muted">
                  No discovered stories matching current criteria.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 4: STORY CLUSTERS & DEDUPLICATION -->
      <div v-if="activeTab === 'clusters'" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="text-xs text-nexo-text-secondary">
            Multi-source story clusters grouped by topic, entity similarity, and 72-hour event window.
          </div>
          <Button size="sm" variant="secondary" @click="isMergeModalOpen = true">
            <GitMerge class="h-3.5 w-3.5 mr-1" /> Merge Two Clusters
          </Button>
        </div>

        <div class="space-y-4">
          <Card
            v-for="cluster in clusters"
            :key="cluster.id"
            class="p-5 border border-nexo-border space-y-3"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <div class="flex items-center gap-2 text-xs font-mono text-nexo-text-muted">
                  <span class="font-mono text-[9px] uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 font-semibold">
                    {{ cluster.category || 'Tech & AI' }}
                  </span>
                  <span>Confidence: {{ ((cluster.confidence_score || 0.8) * 100).toFixed(0) }}%</span>
                  <span>•</span>
                  <span>{{ cluster.stories?.length || 1 }} Wire Dispatches</span>
                </div>
                <h3 class="text-base font-medium text-nexo-text-primary mt-1">
                  {{ cluster.headline }}
                </h3>
                <p class="text-xs text-nexo-text-secondary mt-1">
                  {{ cluster.summary }}
                </p>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <Button
                  size="sm"
                  variant="outline"
                  @click="inspectCluster = cluster; isClusterModalOpen = true"
                >
                  <Eye class="h-3 w-3 mr-1" /> Inspect Stories
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  :disabled="actionInProgress"
                  @click="handleSplitCluster(cluster)"
                >
                  <Split class="h-3 w-3 mr-1" /> Split Cluster
                </Button>
              </div>
            </div>

            <!-- Wire reports inside this cluster -->
            <div class="pt-2 border-t border-nexo-border/60">
              <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Clustered Wire Reports:</span>
              <div class="flex flex-wrap gap-2 mt-1.5">
                <span
                  v-for="st in cluster.stories || []"
                  :key="st.id"
                  class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-slate-100 text-[10px] font-mono text-slate-700 border border-slate-200"
                >
                  <span class="font-semibold text-amber-700">{{ st.source?.name || 'Feed' }}:</span>
                  <span class="truncate max-w-xs">{{ st.title }}</span>
                  <span v-if="st.pivot?.is_primary" class="bg-amber-200 text-amber-900 px-1 rounded text-[8px] uppercase">Primary</span>
                </span>
              </div>
            </div>
          </Card>

          <div v-if="!clusters.length" class="text-center py-10 text-xs text-nexo-text-muted border border-dashed border-nexo-border rounded-xl">
            No clusters formed yet. Trigger ingestion to discover and cluster stories.
          </div>
        </div>
      </div>

      <!-- TAB 5: FACT & EVIDENCE VERIFICATION -->
      <div v-if="activeTab === 'facts'" class="space-y-4">
        <!-- Filter Header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <select
              v-model="factFilterStatus"
              @change="fetchTabData"
              class="px-3 py-1.5 text-xs bg-white border border-nexo-border rounded-lg text-nexo-text-primary focus:outline-none"
            >
              <option value="all">All Verification Statuses</option>
              <option value="supported_multiple">Supported by Multiple Sources (Consensus)</option>
              <option value="supported_single">Supported by Single Source</option>
              <option value="conflicting">Conflicting Claims</option>
              <option value="unverified">Unverified</option>
              <option value="debunked">Debunked</option>
            </select>
          </div>

          <div class="text-xs text-nexo-text-secondary">
            Cross-publisher claims and atomic verification evidence.
          </div>
        </div>

        <div class="overflow-x-auto border border-nexo-border rounded-xl bg-white shadow-2xs">
          <table class="w-full text-xs text-left">
            <thead class="bg-nexo-bg/70 text-nexo-text-muted font-mono uppercase text-[10px] border-b border-nexo-border">
              <tr>
                <th class="p-3">Claim & Entity</th>
                <th class="p-3">Source & Credibility</th>
                <th class="p-3">Evidence Excerpt</th>
                <th class="p-3">Status</th>
                <th class="p-3 text-right">Editorial Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border">
              <tr v-for="fact in facts" :key="fact.id" class="hover:bg-slate-50/50">
                <td class="p-3 max-w-sm">
                  <div class="font-medium text-nexo-text-primary">{{ fact.claim }}</div>
                  <div class="text-[10px] font-mono text-amber-700 mt-0.5">Entity: {{ fact.entity || 'General' }}</div>
                </td>
                <td class="p-3 font-mono text-[10px]">
                  <div class="font-semibold text-nexo-text-primary">{{ fact.source?.name || 'Wire' }}</div>
                  <div class="text-nexo-text-muted">Credibility: {{ ((fact.source?.credibility_score || 0.8) * 100).toFixed(0) }}%</div>
                </td>
                <td class="p-3 text-[11px] text-nexo-text-secondary max-w-xs italic">
                  "{{ fact.evidence_excerpt || 'No specific excerpt' }}"
                </td>
                <td class="p-3">
                  <span
                    :class="[
                      'text-[9px] uppercase font-mono px-2 py-0.5 rounded font-semibold',
                      fact.verification_status === 'supported_multiple' ? 'bg-emerald-100 text-emerald-800' :
                      fact.verification_status === 'conflicting' ? 'bg-rose-100 text-rose-800' :
                      'bg-amber-100 text-amber-800'
                    ]"
                  >
                    {{ fact.verification_status }}
                  </span>
                </td>
                <td class="p-3 text-right">
                  <Button
                    size="sm"
                    variant="outline"
                    @click="openEditFact(fact)"
                    class="font-mono text-[10px]"
                  >
                    Edit Verification
                  </Button>
                </td>
              </tr>
              <tr v-if="!facts.length">
                <td colspan="5" class="p-6 text-center text-xs text-nexo-text-muted">
                  No extracted facts available. Facts are populated during the automated pipeline verification stage.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 6: 14-STAGE AUTOMATION PIPELINE -->
      <div v-if="activeTab === 'pipeline'" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="text-xs text-nexo-text-secondary">
            Continuous job orchestration queue: discovery, deduplication, clustering, fact verification, and synthesis.
          </div>
          <Button size="sm" variant="outline" @click="fetchTabData">
            <RefreshCw class="h-3.5 w-3.5 mr-1" /> Refresh Queue
          </Button>
        </div>

        <div class="overflow-x-auto border border-nexo-border rounded-xl bg-white shadow-2xs">
          <table class="w-full text-xs text-left">
            <thead class="bg-nexo-bg/70 text-nexo-text-muted font-mono uppercase text-[10px] border-b border-nexo-border">
              <tr>
                <th class="p-3">Job ID & Task</th>
                <th class="p-3">Pipeline Stage</th>
                <th class="p-3">Target Context</th>
                <th class="p-3">Duration / Retries</th>
                <th class="p-3">Status</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border">
              <tr v-for="job in pipelineJobs" :key="job.id" class="hover:bg-slate-50/50">
                <td class="p-3 font-mono text-[11px] text-nexo-text-primary">
                  <div class="font-bold">#{{ job.id.substring(0, 8) }}</div>
                  <div class="text-[10px] text-nexo-text-muted">{{ job.job_name || 'Pipeline Task' }}</div>
                </td>
                <td class="p-3 font-mono text-xs">
                  <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 border border-slate-200">
                    {{ job.stage }}
                  </span>
                </td>
                <td class="p-3 font-mono text-[10px] text-nexo-text-secondary">
                  <div>{{ job.target_type }}: {{ job.target_id ? job.target_id.substring(0, 8) : 'N/A' }}</div>
                </td>
                <td class="p-3 font-mono text-[10px] text-nexo-text-muted">
                  <div>{{ job.execution_duration_ms ? `${job.execution_duration_ms}ms` : 'In progress' }}</div>
                  <div>Retries: {{ job.retry_count || 0 }}</div>
                </td>
                <td class="p-3">
                  <span
                    :class="[
                      'text-[9px] uppercase font-mono px-2 py-0.5 rounded font-semibold',
                      job.status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' :
                      job.status === 'FAILED' ? 'bg-rose-100 text-rose-800' :
                      'bg-amber-100 text-amber-800'
                    ]"
                  >
                    {{ job.status }}
                  </span>
                </td>
                <td class="p-3 text-right space-x-1.5">
                  <Button
                    v-if="job.status === 'FAILED'"
                    size="sm"
                    variant="secondary"
                    :disabled="actionInProgress"
                    @click="retryPipelineJob(job.id)"
                  >
                    Retry
                  </Button>
                  <Button
                    v-if="job.status === 'IN_PROGRESS' || job.status === 'PENDING'"
                    size="sm"
                    variant="danger"
                    :disabled="actionInProgress"
                    @click="cancelPipelineJob(job.id)"
                  >
                    Cancel
                  </Button>
                </td>
              </tr>
              <tr v-if="!pipelineJobs.length">
                <td colspan="6" class="p-6 text-center text-xs text-nexo-text-muted">
                  Pipeline queue is idle.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 7: AUTOMATION POLICY SETTINGS -->
      <div v-if="activeTab === 'automation'" class="space-y-6">
        <Card class="p-6 border border-nexo-border space-y-6">
          <div class="border-b border-nexo-border/60 pb-3">
            <h2 class="text-base font-semibold text-nexo-text-primary">Autonomous Editorial Policy & Thresholds</h2>
            <p class="text-xs text-nexo-text-secondary">Configure intelligence gating, clustering sensitivity, and human-in-the-loop oversight rules.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Publishing Mode -->
            <div class="space-y-2">
              <label class="text-xs font-semibold text-nexo-text-primary uppercase tracking-wider">
                Editorial Publishing Mode
              </label>
              <select
                v-model="automationSettings.publishing_mode"
                class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded-lg text-nexo-text-primary focus:outline-none"
              >
                <option value="FULL_AUTO">FULL AUTO — Zero touch for high-trust sources</option>
                <option value="AUTO_REVIEW">AUTO + REVIEW — Generate drafts, require human review</option>
                <option value="MANUAL_ONLY">MANUAL ONLY — AI assistance only, no automated publishing</option>
              </select>
              <p class="text-[10px] text-nexo-text-muted">
                In AUTO + REVIEW mode, synthesized articles are created in 'REVIEW' status and await chief editor approval.
              </p>
            </div>

            <!-- Ingestion Cycle Cadence -->
            <div class="space-y-2">
              <label class="text-xs font-semibold text-nexo-text-primary uppercase tracking-wider">
                Feed Ingestion Frequency (Minutes)
              </label>
              <input
                type="number"
                v-model.number="automationSettings.feed_fetch_interval_minutes"
                min="5"
                max="1440"
                class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded-lg text-nexo-text-primary focus:outline-none"
              />
              <p class="text-[10px] text-nexo-text-muted">
                Frequency at which the scheduler surveys registered RSS and Atom feeds.
              </p>
            </div>

            <!-- Minimum Credibility -->
            <div class="space-y-2">
              <label class="text-xs font-semibold text-nexo-text-primary uppercase tracking-wider">
                Minimum Source Credibility (0.00 – 1.00)
              </label>
              <input
                type="number"
                step="0.05"
                min="0"
                max="1"
                v-model.number="automationSettings.min_source_credibility"
                class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded-lg text-nexo-text-primary focus:outline-none"
              />
              <p class="text-[10px] text-nexo-text-muted">
                Sources below this score are ingested into quarantine and require manual verification.
              </p>
            </div>

            <!-- Fact Consensus -->
            <div class="space-y-2">
              <label class="text-xs font-semibold text-nexo-text-primary uppercase tracking-wider">
                Minimum Consensus Sources for Verification
              </label>
              <input
                type="number"
                min="1"
                max="10"
                v-model.number="automationSettings.min_fact_consensus_sources"
                class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded-lg text-nexo-text-primary focus:outline-none"
              />
              <p class="text-[10px] text-nexo-text-muted">
                Number of independent publishers required to report an atomic fact before marking as consensus-verified.
              </p>
            </div>
          </div>

          <!-- Manual Lock Policy Notice -->
          <div class="p-4 bg-amber-50/60 border border-amber-200 rounded-xl space-y-2">
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-900">
              <Lock class="h-4 w-4 text-amber-700" />
              <span>Manual Override Protection Standard</span>
            </div>
            <p class="text-xs text-amber-800 leading-relaxed">
              Nexovant strictly enforces non-destructive re-synthesis. When an editor modifies or locks an article attribute (<code>lock_content</code>, <code>lock_category</code>, <code>lock_topic</code>, <code>lock_image</code>, <code>lock_tags</code>) or marks an article <code>UNPUBLISHED</code>, the intelligence pipeline is permanently barred from overwriting those fields or restoring unpublishing.
            </p>
          </div>

          <div class="flex justify-end">
            <Button
              variant="primary"
              size="sm"
              :disabled="actionInProgress"
              @click="saveAutomationSettings"
              class="bg-nexo-accent-gold hover:bg-[#8e6e18] text-white shadow-xs"
            >
              Save Automation Policy
            </Button>
          </div>
        </Card>
      </div>

      <!-- MODAL 1: Inspect Discovered Story -->
      <Modal
        :isOpen="isInspectModalOpen"
        title="Discovered Wire Story Inspector"
        @close="isInspectModalOpen = false"
      >
        <div v-if="inspectStory" class="space-y-4 text-left">
          <div>
            <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Headline:</span>
            <h4 class="text-sm font-semibold text-nexo-text-primary mt-0.5">{{ inspectStory.title }}</h4>
          </div>

          <div class="grid grid-cols-2 gap-3 text-xs font-mono">
            <div>
              <span class="text-nexo-text-muted">Source:</span>
              <div class="font-medium text-nexo-text-primary">{{ inspectStory.source?.name }}</div>
            </div>
            <div>
              <span class="text-nexo-text-muted">Published:</span>
              <div class="font-medium text-nexo-text-primary">{{ inspectStory.published_at || 'N/A' }}</div>
            </div>
          </div>

          <div>
            <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Canonical URL:</span>
            <div class="text-xs font-mono text-amber-700 break-all">{{ inspectStory.canonical_url }}</div>
          </div>

          <div>
            <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Extracted Excerpt / Body:</span>
            <div class="p-3 bg-slate-50 border border-slate-200 rounded text-xs text-slate-700 max-h-48 overflow-y-auto whitespace-pre-wrap">
              {{ inspectStory.body_text || inspectStory.summary || 'No extracted text' }}
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <Button size="sm" variant="outline" @click="isInspectModalOpen = false">Close</Button>
          </div>
        </div>
      </Modal>

      <!-- MODAL 2: Merge Clusters -->
      <Modal
        :isOpen="isMergeModalOpen"
        title="Consolidate Two Story Clusters"
        @close="isMergeModalOpen = false"
      >
        <div class="space-y-4 text-left">
          <p class="text-xs text-nexo-text-secondary">
            Merges the secondary cluster's dispatches and facts into the primary cluster. The secondary cluster is soft-deleted and an audit log is created.
          </p>

          <div class="space-y-2">
            <label class="text-xs font-semibold text-nexo-text-primary">Primary Cluster ID (Kept)</label>
            <input
              v-model="mergePrimaryId"
              placeholder="UUID of surviving cluster"
              class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded text-nexo-text-primary focus:outline-none font-mono"
            />
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold text-nexo-text-primary">Secondary Cluster ID (Merged & Absorbed)</label>
            <input
              v-model="mergeSecondaryId"
              placeholder="UUID of absorbed cluster"
              class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded text-nexo-text-primary focus:outline-none font-mono"
            />
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold text-nexo-text-primary">Audit Rationale</label>
            <textarea
              v-model="mergeReason"
              rows="2"
              placeholder="Editorial reason for cluster consolidation..."
              class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded text-nexo-text-primary focus:outline-none"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <Button size="sm" variant="outline" @click="isMergeModalOpen = false">Cancel</Button>
            <Button
              size="sm"
              variant="primary"
              :disabled="actionInProgress"
              @click="handleMergeClusters"
              class="bg-nexo-accent-gold text-white"
            >
              Confirm Merge
            </Button>
          </div>
        </div>
      </Modal>

      <!-- MODAL 3: Edit Fact Verification -->
      <Modal
        :isOpen="isFactModalOpen"
        title="Editorial Fact Verification Override"
        @close="isFactModalOpen = false"
      >
        <div v-if="editingFact" class="space-y-4 text-left">
          <div>
            <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Atomic Claim:</span>
            <p class="text-xs font-medium text-nexo-text-primary mt-1">{{ editingFact.claim }}</p>
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold text-nexo-text-primary">Verification Status</label>
            <select
              v-model="factStatusChoice"
              class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded text-nexo-text-primary focus:outline-none"
            >
              <option value="supported_multiple">Supported by Multiple Sources (Consensus)</option>
              <option value="supported_single">Supported by Single Source</option>
              <option value="conflicting">Conflicting Claims</option>
              <option value="unverified">Unverified / Inconclusive</option>
              <option value="debunked">Debunked / False Information</option>
            </select>
          </div>

          <div class="space-y-2">
            <label class="text-xs font-semibold text-nexo-text-primary">Editorial Verification Notes</label>
            <textarea
              v-model="factAuditNotes"
              rows="3"
              placeholder="Record source corroboration notes, primary evidence citations..."
              class="w-full px-3 py-2 text-xs bg-white border border-nexo-border rounded text-nexo-text-primary focus:outline-none"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <Button size="sm" variant="outline" @click="isFactModalOpen = false">Cancel</Button>
            <Button
              size="sm"
              variant="primary"
              :disabled="actionInProgress"
              @click="saveFactStatus"
              class="bg-nexo-accent-gold text-white"
            >
              Save Verification Status
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>
