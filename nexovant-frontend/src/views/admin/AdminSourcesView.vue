<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import {
  Radio, RefreshCw, Zap, Trash2, Plus, ExternalLink, ShieldCheck,
  AlertTriangle, Globe, CheckCircle, XCircle, Search, Filter, Activity,
  Layers, FileText, Lock, Award, Compass, ShieldAlert
} from 'lucide-vue-next'

// Active Tab
const activeTab = ref<'registry' | 'onboarding' | 'health' | 'coverage' | 'diversity'>('registry')

// State
const sources = ref<any[]>([])
const onboardingQueue = ref<any[]>([])
const healthOverview = ref<any>(null)
const coverageData = ref<any>(null)
const coverageGaps = ref<any>(null)
const diversityData = ref<any>(null)
const isLoading = ref(true)

// Alerts
const statusMessage = ref('')
const errorMessage = ref('')

// Filters
const filterType = ref('')
const filterCountry = ref('')
const filterStatus = ref('')
const filterSearch = ref('')

// Modals
const isAddModalOpen = ref(false)
const isTestModalOpen = ref(false)
const isReliabilityModalOpen = ref(false)
const isApproveModalOpen = ref(false)
const isRejectModalOpen = ref(false)

// Active target for modals
const activeSource = ref<any>(null)
const testProbeResult = ref<any>(null)
const isProbing = ref(false)
const scanningSourceId = ref<string | null>(null)
const approvalNotes = ref('')
const rejectReason = ref('')
const correctionDetail = ref('')

// Form Model for New Source
const newSource = ref({
  name: '',
  publisher_name: '',
  source_type: 'rss',
  website_url: '',
  feed_url: '',
  api_endpoint: '',
  country_code: 'US',
  continent: 'North America',
  language: 'en',
  supported_languages: ['en'],
  publication_frequency: 'hourly',
  auth_type: 'none',
  credentials: {
    api_key: '',
    bearer_token: '',
    username: '',
    password: '',
  },
  attribution_requirements: '',
  terms_of_use_url: '',
  rate_limit_per_minute: 60,
  credibility_score: 0.90,
  is_active: true,
})

const registryHeaders = ['Source Name & Domain', 'Type & Tier', 'Jurisdiction', 'Credibility & Reliability', 'Health', 'Actions']
const queueHeaders = ['Source Name & Publisher', 'Type & Target', 'Jurisdiction & Language', 'Security Check', 'Notes', 'Actions']

// Data Loaders
const loadAllData = async () => {
  isLoading.value = true
  try {
    await Promise.all([
      fetchSources(),
      fetchOnboardingQueue(),
      fetchHealthOverview(),
      fetchCoverage(),
      fetchDiversity(),
    ])
  } catch (err: any) {
    errorMessage.value = 'Failed to load source network intelligence data.'
  } finally {
    isLoading.value = false
  }
}

const fetchSources = async () => {
  try {
    const params: Record<string, any> = {}
    if (filterType.value) params.source_type = filterType.value
    if (filterCountry.value) params.country_code = filterCountry.value
    if (filterStatus.value) params.editorial_status = filterStatus.value
    if (filterSearch.value) params.search = filterSearch.value
    sources.value = await adminService.getSources(params)
  } catch (e: any) {
    console.error(e)
  }
}

const fetchOnboardingQueue = async () => {
  try {
    const res = await adminService.getOnboardingQueue()
    onboardingQueue.value = res.data || []
  } catch (e: any) {
    console.error(e)
  }
}

const fetchHealthOverview = async () => {
  try {
    healthOverview.value = await adminService.getHealthOverview()
  } catch (e: any) {
    console.error(e)
  }
}

const fetchCoverage = async () => {
  try {
    const [cov, gaps] = await Promise.all([
      adminService.getCoverageAnalytics(),
      adminService.getCoverageGaps(),
    ])
    coverageData.value = cov
    coverageGaps.value = gaps
  } catch (e: any) {
    console.error(e)
  }
}

const fetchDiversity = async () => {
  try {
    diversityData.value = await adminService.getDiversityMetrics()
  } catch (e: any) {
    console.error(e)
  }
}

// Handlers
const handleProbe = async (source: any) => {
  activeSource.value = source
  isProbing.value = true
  testProbeResult.value = null
  isTestModalOpen.value = true
  try {
    const res = await adminService.testSource(source.id)
    testProbeResult.value = res
    await fetchSources()
    await fetchHealthOverview()
  } catch (err: any) {
    testProbeResult.value = {
      success: false,
      message: err.response?.data?.message || 'Connection probe failed.',
      status: 'unavailable',
      latency_ms: 0,
      sample_items: [],
    }
  } finally {
    isProbing.value = false
  }
}

const handleScan = async (id: string) => {
  scanningSourceId.value = id
  try {
    const res = await adminService.scanSource(id)
    statusMessage.value = res.message || 'Collection scan initiated.'
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to trigger scan.'
  } finally {
    scanningSourceId.value = null
  }
}

const handleToggle = async (id: string) => {
  try {
    const res = await adminService.toggleSource(id)
    statusMessage.value = res.message || 'Source state updated.'
    await fetchSources()
    await fetchHealthOverview()
  } catch (err: any) {
    errorMessage.value = 'Failed to toggle source state.'
  }
}

const handleDelete = async (id: string) => {
  if (!confirm('Permanently remove this collection source from the intelligence network?')) return
  try {
    const res = await adminService.deleteSource(id)
    statusMessage.value = res.message || 'Source deleted.'
    await fetchSources()
    await fetchCoverage()
    await fetchDiversity()
  } catch (err: any) {
    errorMessage.value = 'Failed to delete source.'
  }
}

const openApproveModal = (source: any) => {
  activeSource.value = source
  approvalNotes.value = `Verified endpoint integrity, terms of use, and attribution notices.`
  isApproveModalOpen.value = true
}

const submitApproval = async () => {
  if (!activeSource.value) return
  try {
    const res = await adminService.approveSource(activeSource.value.id, approvalNotes.value)
    statusMessage.value = res.message
    isApproveModalOpen.value = false
    await fetchOnboardingQueue()
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Approval failed.'
  }
}

const openRejectModal = (source: any) => {
  activeSource.value = source
  rejectReason.value = 'Endpoint failed SSRF verification or violated terms of use.'
  isRejectModalOpen.value = true
}

const submitRejection = async () => {
  if (!activeSource.value) return
  try {
    const res = await adminService.rejectSource(activeSource.value.id, rejectReason.value)
    statusMessage.value = res.message
    isRejectModalOpen.value = false
    await fetchOnboardingQueue()
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Rejection failed.'
  }
}

const openReliabilityModal = async (source: any) => {
  activeSource.value = source
  try {
    const res = await adminService.getReliabilityProfile(source.id)
    activeSource.value.reliability_profile = res.reliability_profile
    isReliabilityModalOpen.value = true
  } catch (err: any) {
    errorMessage.value = 'Failed to load source reliability profile.'
  }
}

const handleCreateSource = async () => {
  try {
    const payload: Record<string, any> = {
      name: newSource.value.name,
      publisher_name: newSource.value.publisher_name || newSource.value.name,
      source_type: newSource.value.source_type,
      website_url: newSource.value.website_url || null,
      feed_url: newSource.value.feed_url || null,
      api_endpoint: newSource.value.api_endpoint || null,
      country_code: newSource.value.country_code,
      continent: newSource.value.continent,
      language: newSource.value.language,
      supported_languages: [newSource.value.language],
      publication_frequency: newSource.value.publication_frequency,
      auth_type: newSource.value.auth_type,
      attribution_requirements: newSource.value.attribution_requirements,
      terms_of_use_url: newSource.value.terms_of_use_url || null,
      rate_limit_per_minute: Number(newSource.value.rate_limit_per_minute),
      credibility_score: Number(newSource.value.credibility_score),
      is_active: newSource.value.is_active,
    }

    if (newSource.value.auth_type !== 'none') {
      payload.credentials = newSource.value.credentials
    }

    const res = await adminService.onboardSource(payload)
    statusMessage.value = res.message || 'Source successfully onboarded.'
    isAddModalOpen.value = false
    await loadAllData()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to onboard source.'
  }
}

const triggerNetworkHealthCheck = async () => {
  try {
    statusMessage.value = 'Triggering health checks across network...'
    const res = await adminService.triggerHealthCheck()
    statusMessage.value = res.message
    await fetchHealthOverview()
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = 'Failed to trigger network health check.'
  }
}

onMounted(() => {
  loadAllData()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
              Global Source Network & Coverage Intelligence
            </h1>
            <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-emerald-100 text-emerald-800 font-bold">
              Stage 14
            </span>
          </div>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Centrally govern RSS/Atom, REST APIs, Regulatory Disclosures, and Academic Repositories with automated SSRF protection, health monitoring, and coverage gap detection.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button variant="primary" size="sm" @click="isAddModalOpen = true" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">
            <Plus class="h-4 w-4 mr-1" /> Onboard New Source
          </Button>
          <Button variant="secondary" size="sm" @click="loadAllData">
            <RefreshCw class="h-3.5 w-3.5 mr-1" /> Refresh
          </Button>
        </div>
      </div>

      <!-- Alerts -->
      <Alert v-if="statusMessage" variant="success" dismissible @dismiss="statusMessage = ''">
        {{ statusMessage }}
      </Alert>
      <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
        {{ errorMessage }}
      </Alert>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-nexo-border text-xs font-mono font-medium gap-6">
        <button
          @click="activeTab = 'registry'"
          :class="['pb-2.5 flex items-center gap-1.5 transition-colors border-b-2', activeTab === 'registry' ? 'border-nexo-accent-gold text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary']"
        >
          <Layers class="h-3.5 w-3.5" /> Source Registry ({{ sources.length }})
        </button>
        <button
          @click="activeTab = 'onboarding'"
          :class="['pb-2.5 flex items-center gap-1.5 transition-colors border-b-2', activeTab === 'onboarding' ? 'border-nexo-accent-gold text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary']"
        >
          <ShieldCheck class="h-3.5 w-3.5" /> Onboarding Queue
          <span v-if="onboardingQueue.length > 0" class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-200 text-amber-900 font-bold">
            {{ onboardingQueue.length }}
          </span>
        </button>
        <button
          @click="activeTab = 'health'"
          :class="['pb-2.5 flex items-center gap-1.5 transition-colors border-b-2', activeTab === 'health' ? 'border-nexo-accent-gold text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary']"
        >
          <Activity class="h-3.5 w-3.5" /> Health & Diagnostics
        </button>
        <button
          @click="activeTab = 'coverage'"
          :class="['pb-2.5 flex items-center gap-1.5 transition-colors border-b-2', activeTab === 'coverage' ? 'border-nexo-accent-gold text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary']"
        >
          <Globe class="h-3.5 w-3.5" /> Coverage & Gaps
          <span v-if="coverageGaps?.total_gaps" class="px-1.5 py-0.2 rounded-full text-[10px] bg-red-100 text-red-800 font-bold">
            {{ coverageGaps.total_gaps }} Gaps
          </span>
        </button>
        <button
          @click="activeTab = 'diversity'"
          :class="['pb-2.5 flex items-center gap-1.5 transition-colors border-b-2', activeTab === 'diversity' ? 'border-nexo-accent-gold text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-muted hover:text-nexo-text-primary']"
        >
          <Compass class="h-3.5 w-3.5" /> Source Diversity & Independence
        </button>
      </div>

      <!-- TAB 1: SOURCE REGISTRY -->
      <div v-if="activeTab === 'registry'" class="space-y-4">
        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center gap-3 bg-white p-3 rounded-lg border border-nexo-border">
          <div class="flex items-center gap-1.5 flex-1 min-w-[200px]">
            <Search class="h-4 w-4 text-nexo-text-muted" />
            <input
              v-model="filterSearch"
              @keyup.enter="fetchSources"
              placeholder="Search by source name, domain, publisher..."
              class="w-full text-xs bg-transparent border-none focus:outline-hidden text-nexo-text-primary"
            />
          </div>

          <select v-model="filterType" @change="fetchSources" class="text-xs p-1.5 border border-nexo-border rounded bg-white text-nexo-text-primary">
            <option value="">All Source Types</option>
            <option value="rss">RSS / Atom</option>
            <option value="rest_api">REST API</option>
            <option value="regulatory">Regulatory / Official</option>
            <option value="academic">Academic / Preprints</option>
            <option value="wire">News Wire</option>
          </select>

          <select v-model="filterStatus" @change="fetchSources" class="text-xs p-1.5 border border-nexo-border rounded bg-white text-nexo-text-primary">
            <option value="">All Editorial Statuses</option>
            <option value="active">Active</option>
            <option value="approved">Approved</option>
            <option value="pending_review">Pending Review</option>
            <option value="temporarily_suspended">Suspended</option>
          </select>

          <Button variant="ghost" size="sm" @click="fetchSources">
            Filter
          </Button>
        </div>

        <!-- Registry Table -->
        <Table :headers="registryHeaders" :is-empty="sources.length === 0">
          <tr v-for="s in sources" :key="s.id" class="hover:bg-nexo-surface/40 transition-colors">
            <td class="py-3 px-4 font-semibold text-nexo-text-primary">
              <div class="flex items-center gap-1.5">
                <span>{{ s.name }}</span>
                <span v-if="s.organization_id" class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-purple-100 text-purple-800 font-bold" title="Private Organization Feed">
                  Private Org #{{ s.organization_id }}
                </span>
                <a :href="s.website_url || s.url" target="_blank" class="text-nexo-text-muted hover:text-nexo-text-primary" title="Open Website">
                  <ExternalLink class="h-3 w-3" />
                </a>
              </div>
              <div class="text-[10px] font-mono text-nexo-text-muted mt-0.5 truncate max-w-xs">
                {{ s.canonical_domain || s.api_endpoint || s.feed_url || s.url }}
              </div>
              <div v-if="s.has_credentials" class="flex items-center gap-1 text-[9px] text-amber-700 font-mono mt-0.5">
                <Lock class="h-2.5 w-2.5" /> Auth: {{ s.auth_type }} (Encrypted)
              </div>
            </td>

            <td class="py-3 px-4">
              <div class="font-mono text-xs text-nexo-accent-cyan capitalize font-bold">
                {{ s.source_type?.replace('_', ' ') }}
              </div>
              <div class="text-[10px] font-mono text-nexo-text-muted">
                {{ s.reliability_profile?.tier || 'Tier 2' }}
              </div>
            </td>

            <td class="py-3 px-4 font-mono text-xs">
              <span class="font-bold text-nexo-text-primary">{{ s.country_code || 'GLOBAL' }}</span>
              <span class="text-nexo-text-muted"> ({{ s.continent || 'Global' }})</span>
              <div class="text-[10px] text-nexo-text-muted">Lang: {{ s.language || 'en' }}</div>
            </td>

            <td class="py-3 px-4 font-mono text-xs">
              <div class="flex items-center gap-2">
                <div>
                  <div class="font-bold text-emerald-700">
                    {{ Math.round((s.credibility_score || 0.9) * 100) }}% Cred
                  </div>
                  <div class="text-[10px] text-nexo-text-muted">
                    {{ s.reliability_profile?.overall_score || 85 }}% Reliab
                  </div>
                </div>
                <button
                  @click="openReliabilityModal(s)"
                  class="p-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700"
                  title="View detailed reliability profile"
                >
                  <Award class="h-3 w-3" />
                </button>
              </div>
            </td>

            <td class="py-3 px-4">
              <span
                :class="[
                  'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase',
                  s.health_status === 'healthy' ? 'bg-emerald-100 text-emerald-800' :
                  s.health_status === 'degraded' ? 'bg-amber-100 text-amber-800' :
                  s.health_status === 'suspended' ? 'bg-gray-200 text-gray-800' :
                  'bg-red-100 text-red-800'
                ]"
              >
                {{ s.health_status || 'unknown' }}
              </span>
              <div v-if="s.last_response_time_ms" class="text-[10px] font-mono text-nexo-text-muted mt-0.5">
                {{ s.last_response_time_ms }}ms
              </div>
            </td>

            <td class="py-3 px-4 font-mono text-xs">
              <div class="flex items-center space-x-1.5">
                <button
                  @click="handleProbe(s)"
                  class="px-2 py-1 rounded bg-white hover:bg-nexo-bg border border-nexo-border text-xs text-nexo-text-primary font-bold shadow-xs"
                  title="Test connector and inspect sample payload"
                >
                  Probe ⚡
                </button>

                <button
                  :disabled="scanningSourceId === s.id"
                  @click="handleScan(s.id)"
                  class="px-2 py-1 rounded bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold hover:bg-amber-100"
                  title="Trigger immediate feed scan"
                >
                  Scan
                </button>

                <button
                  @click="handleToggle(s.id)"
                  :class="[
                    'px-2 py-1 rounded border text-xs',
                    s.is_active ? 'border-amber-200 text-amber-800 hover:bg-amber-50' : 'border-emerald-200 text-emerald-800 hover:bg-emerald-50'
                  ]"
                >
                  {{ s.is_active ? 'Pause' : 'Activate' }}
                </button>

                <button
                  @click="handleDelete(s.id)"
                  class="p-1 rounded text-red-600 hover:bg-red-50"
                  title="Delete source"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 2: ONBOARDING & EDITORIAL QUEUE -->
      <div v-if="activeTab === 'onboarding'" class="space-y-4">
        <div class="p-3 bg-amber-50 border border-amber-200 rounded text-xs text-amber-900 flex items-start gap-2">
          <ShieldAlert class="h-4 w-4 mt-0.5 text-amber-700 shrink-0" />
          <div>
            <span class="font-bold">Editorial Governance Queue:</span>
            New sources must pass automated endpoint SSRF verification, robots compliance, and schema extraction before being promoted to active intelligence feeds.
          </div>
        </div>

        <Table :headers="queueHeaders" :is-empty="onboardingQueue.length === 0">
          <tr v-for="q in onboardingQueue" :key="q.id" class="hover:bg-nexo-surface/40 transition-colors">
            <td class="py-3 px-4 font-semibold text-nexo-text-primary">
              <div>{{ q.name }}</div>
              <div class="text-[10px] text-nexo-text-muted font-normal">Publisher: {{ q.publisher_name }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted">{{ q.canonical_domain }}</div>
            </td>

            <td class="py-3 px-4 font-mono text-xs">
              <span class="text-nexo-accent-cyan font-bold capitalize">{{ q.source_type }}</span>
              <div class="text-[10px] text-nexo-text-muted truncate max-w-xs">{{ q.feed_url || q.api_endpoint }}</div>
            </td>

            <td class="py-3 px-4 font-mono text-xs">
              <div>{{ q.country_code }} - {{ q.continent }}</div>
              <div class="text-[10px] text-nexo-text-muted">Lang: {{ q.language }}</div>
            </td>

            <td class="py-3 px-4 font-mono text-xs">
              <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                SSRF Passed
              </span>
            </td>

            <td class="py-3 px-4 text-xs text-nexo-text-muted max-w-xs truncate">
              {{ q.onboarding_notes || 'Pending review' }}
            </td>

            <td class="py-3 px-4 font-mono text-xs">
              <div class="flex items-center gap-1.5">
                <button
                  @click="handleProbe(q)"
                  class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-xs font-bold"
                  title="Probe endpoint"
                >
                  Inspect Sample
                </button>
                <button
                  @click="openApproveModal(q)"
                  class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs"
                >
                  Approve
                </button>
                <button
                  @click="openRejectModal(q)"
                  class="px-2 py-1 rounded bg-red-100 hover:bg-red-200 text-red-800 font-bold text-xs"
                >
                  Reject
                </button>
              </div>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 3: HEALTH & DIAGNOSTICS -->
      <div v-if="activeTab === 'health'" class="space-y-6">
        <!-- Health Overview Cards -->
        <div v-if="healthOverview" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Availability Rate</div>
            <div class="editorial-title text-2xl font-bold text-emerald-700 mt-1">
              {{ healthOverview.availability_rate }}%
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">
              {{ healthOverview.active_sources }} active sources
            </div>
          </Card>

          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Average Latency</div>
            <div class="editorial-title text-2xl font-bold text-nexo-accent-gold mt-1">
              {{ healthOverview.average_latency_ms }} ms
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">
              Across live connectors
            </div>
          </Card>

          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Degraded / Slow Feeds</div>
            <div class="editorial-title text-2xl font-bold text-amber-700 mt-1">
              {{ healthOverview.status_breakdown.degraded || 0 }}
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">
              Latency > 3500ms
            </div>
          </Card>

          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Unavailable / Errors</div>
            <div class="editorial-title text-2xl font-bold text-red-700 mt-1">
              {{ (healthOverview.status_breakdown.unavailable || 0) + (healthOverview.status_breakdown.auth_required || 0) }}
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">
              Requires attention
            </div>
          </Card>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
          <h2 class="editorial-title text-lg font-medium text-nexo-text-primary">
            Recent Health Diagnostics & Incidents
          </h2>
          <Button variant="secondary" size="sm" @click="triggerNetworkHealthCheck">
            <RefreshCw class="h-3.5 w-3.5 mr-1" /> Run Network-Wide Health Probe
          </Button>
        </div>

        <!-- Status Breakdown Pill Badges -->
        <div v-if="healthOverview" class="flex flex-wrap gap-2 p-3 bg-white rounded border border-nexo-border text-xs font-mono">
          <span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 font-bold">
            Healthy: {{ healthOverview.status_breakdown.healthy }}
          </span>
          <span class="px-2 py-1 rounded bg-amber-100 text-amber-800 font-bold">
            Degraded: {{ healthOverview.status_breakdown.degraded }}
          </span>
          <span class="px-2 py-1 rounded bg-red-100 text-red-800 font-bold">
            Unavailable: {{ healthOverview.status_breakdown.unavailable }}
          </span>
          <span class="px-2 py-1 rounded bg-orange-100 text-orange-800 font-bold">
            Auth Required: {{ healthOverview.status_breakdown.auth_required }}
          </span>
          <span class="px-2 py-1 rounded bg-purple-100 text-purple-800 font-bold">
            Rate Limited: {{ healthOverview.status_breakdown.rate_limited }}
          </span>
          <span class="px-2 py-1 rounded bg-blue-100 text-blue-800 font-bold">
            Format Changed: {{ healthOverview.status_breakdown.format_changed }}
          </span>
        </div>

        <!-- Incidents Table -->
        <div v-if="healthOverview?.recent_incidents?.length" class="space-y-2">
          <div
            v-for="inc in healthOverview.recent_incidents"
            :key="inc.id"
            class="p-3 bg-white border border-nexo-border rounded flex items-center justify-between text-xs font-mono"
          >
            <div>
              <span class="font-bold text-nexo-text-primary">{{ inc.source?.name }}</span>
              <span class="text-nexo-text-muted ml-2">({{ inc.status }})</span>
              <div class="text-[10px] text-red-700 mt-0.5">{{ inc.error_message }}</div>
            </div>
            <div class="text-right text-[10px] text-nexo-text-muted">
              <div>HTTP {{ inc.http_status || 'ERR' }} &middot; {{ inc.response_time_ms }}ms</div>
              <div>{{ inc.checked_at }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 4: COVERAGE & GAPS -->
      <div v-if="activeTab === 'coverage'" class="space-y-6">
        <!-- Coverage Metrics -->
        <div v-if="coverageData" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Continents Represented</div>
            <div class="editorial-title text-2xl font-bold text-nexo-text-primary mt-1">
              {{ coverageData.total_continents }} / 6
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">Global geographic distribution</div>
          </Card>

          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Countries Covered</div>
            <div class="editorial-title text-2xl font-bold text-emerald-700 mt-1">
              {{ coverageData.total_countries_covered }}
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">Active sovereign jurisdictions</div>
          </Card>

          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Total Indexed Articles</div>
            <div class="editorial-title text-2xl font-bold text-nexo-accent-gold mt-1">
              {{ coverageData.global_summary.total_articles }}
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">Across all regions and languages</div>
          </Card>
        </div>

        <!-- Detected Gaps Section -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="editorial-title text-lg font-medium text-nexo-text-primary">
              Automated Coverage Gap Detection ({{ coverageGaps?.total_gaps || 0 }} Identified)
            </h2>
          </div>

          <div v-if="coverageGaps?.gaps?.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div
              v-for="gap in coverageGaps.gaps"
              :key="gap.title"
              class="p-4 bg-white border border-nexo-border rounded-lg space-y-2 text-xs"
            >
              <div class="flex items-center justify-between">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase',
                    gap.severity === 'critical' ? 'bg-red-100 text-red-800' :
                    gap.severity === 'high' ? 'bg-amber-100 text-amber-800' :
                    'bg-blue-100 text-blue-800'
                  ]"
                >
                  {{ gap.severity }} Severity
                </span>
                <span class="text-[10px] font-mono text-nexo-text-muted">{{ gap.category }}</span>
              </div>

              <div class="font-bold text-nexo-text-primary text-sm">{{ gap.title }}</div>
              <div class="text-nexo-text-secondary text-xs">{{ gap.description }}</div>

              <div class="pt-2 border-t border-nexo-border text-[11px] text-emerald-800 font-mono flex items-start gap-1.5">
                <Compass class="h-3.5 w-3.5 mt-0.5 shrink-0 text-emerald-700" />
                <span>Action: {{ gap.recommended_action }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Continental Breakdown -->
        <div v-if="coverageData?.continents" class="space-y-3">
          <h2 class="editorial-title text-lg font-medium text-nexo-text-primary">
            Continental Source Distribution
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            <div v-for="c in coverageData.continents" :key="c.continent" class="p-3 bg-white border border-nexo-border rounded text-center">
              <div class="text-xs font-bold text-nexo-text-primary">{{ c.continent }}</div>
              <div class="text-lg font-mono font-bold text-nexo-accent-gold mt-1">{{ c.active_sources }}</div>
              <div class="text-[10px] text-nexo-text-muted font-mono">active sources</div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 5: SOURCE DIVERSITY & INDEPENDENCE -->
      <div v-if="activeTab === 'diversity'" class="space-y-6">
        <div v-if="diversityData" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Unique Canonical Publishers</div>
            <div class="editorial-title text-2xl font-bold text-nexo-text-primary mt-1">
              {{ diversityData.unique_canonical_domains }}
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">Independent parent publishing entities</div>
          </Card>

          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Primary Regulatory Ratio</div>
            <div class="editorial-title text-2xl font-bold text-emerald-700 mt-1">
              {{ diversityData.primary_source_ratio }}%
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">{{ diversityData.primary_sources_count }} primary official bodies</div>
          </Card>

          <Card class="p-4">
            <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Recent Diversity Index</div>
            <div class="editorial-title text-2xl font-bold text-nexo-accent-gold mt-1">
              {{ diversityData.sample_recent_diversity?.diversity_index }}
            </div>
            <div class="text-[10px] text-nexo-text-muted mt-1">Non-inflated independent ratio (0 - 1.0)</div>
          </Card>
        </div>

        <div class="p-4 bg-white border border-nexo-border rounded-lg space-y-3 text-xs">
          <h3 class="font-bold text-sm text-nexo-text-primary">Syndication vs Independent Reporting Safeguards</h3>
          <p class="text-nexo-text-secondary">
            Nexovant isolates wire reprints and syndications (e.g. identical content hash or attribution to Reuters, AP, BSS, AFP) from independent corroborations. Corroboration rates are calculated exclusively from unique parent publishers to prevent echo-chamber score inflation.
          </p>

          <div v-if="diversityData?.sample_recent_diversity?.active_wire_networks?.length" class="pt-2">
            <div class="font-mono text-[11px] font-bold text-nexo-text-primary mb-1">Active Wire Networks Detected:</div>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="wire in diversityData.sample_recent_diversity.active_wire_networks"
                :key="wire"
                class="px-2 py-0.5 rounded text-[10px] font-mono bg-slate-100 text-slate-800 border border-slate-200"
              >
                {{ wire }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL: ADD / ONBOARD SOURCE -->
      <Modal :is-open="isAddModalOpen" title="Onboard Source to Intelligence Network" max-width="lg" @close="isAddModalOpen = false">
        <form @submit.prevent="handleCreateSource" class="space-y-4 text-xs text-left">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Source Name</label>
              <input v-model="newSource.name" placeholder="e.g. Bangladesh Bank Regulatory Portal" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Publisher / Organization</label>
              <input v-model="newSource.publisher_name" placeholder="e.g. Bangladesh Bank" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Source Type</label>
              <select v-model="newSource.source_type" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="rss">RSS 2.0 / Atom 1.0 Feed</option>
                <option value="rest_api">REST API (JSON)</option>
                <option value="regulatory">Government / Regulatory Disclosure</option>
                <option value="academic">Academic / Preprint Archive</option>
                <option value="wire">News Wire</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Publication Frequency</label>
              <select v-model="newSource.publication_frequency" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="realtime">Real-time / Instant</option>
                <option value="hourly">Hourly</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Country (ISO 2)</label>
              <input v-model="newSource.country_code" placeholder="BD" maxlength="2" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono uppercase" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Continent</label>
              <select v-model="newSource.continent" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="Asia">Asia</option>
                <option value="Europe">Europe</option>
                <option value="North America">North America</option>
                <option value="South America">South America</option>
                <option value="Africa">Africa</option>
                <option value="Oceania">Oceania</option>
                <option value="Global">Global</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Native Language</label>
              <select v-model="newSource.language" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="en">English (en)</option>
                <option value="bn">Bengali (bn)</option>
                <option value="fr">French (fr)</option>
                <option value="de">German (de)</option>
                <option value="es">Spanish (es)</option>
                <option value="ar">Arabic (ar)</option>
              </select>
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Primary Feed / API Endpoint URL (SSRF Protected)</label>
            <input v-model="newSource.feed_url" placeholder="https://bb.org.bd/en/index.php/mediaroom/press_release_rss" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono" />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Authentication Method</label>
            <select v-model="newSource.auth_type" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
              <option value="none">Public (No Authentication)</option>
              <option value="bearer">Bearer Token (Header: Authorization Bearer ...)</option>
              <option value="api_key">API Key (Header / Query)</option>
              <option value="basic">Basic HTTP Authentication</option>
            </select>
          </div>

          <div v-if="newSource.auth_type === 'bearer'" class="p-2 bg-slate-50 border border-slate-200 rounded">
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Bearer Token (Encrypted At Rest)</label>
            <input v-model="newSource.credentials.bearer_token" type="password" placeholder="sec_live_..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white font-mono" />
          </div>

          <div v-if="newSource.auth_type === 'api_key'" class="p-2 bg-slate-50 border border-slate-200 rounded">
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">API Key (Encrypted At Rest)</label>
            <input v-model="newSource.credentials.api_key" type="password" placeholder="api_key_..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white font-mono" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Attribution Requirements</label>
              <input v-model="newSource.attribution_requirements" placeholder="Reporting by Bangladesh Bank" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Terms of Use / Robots Policy URL</label>
              <input v-model="newSource.terms_of_use_url" placeholder="https://bb.org.bd/terms" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono" />
            </div>
          </div>

          <div class="pt-3 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isAddModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Submit for Review</Button>
          </div>
        </form>
      </Modal>

      <!-- MODAL: CONNECTOR TESTING & SAMPLE PAYLOAD -->
      <Modal :is-open="isTestModalOpen" :title="`Connector Probe: ${activeSource?.name}`" max-width="lg" @close="isTestModalOpen = false">
        <div class="space-y-4 text-xs text-left">
          <div v-if="isProbing" class="py-8 text-center text-nexo-text-muted space-y-2">
            <RefreshCw class="h-6 w-6 animate-spin mx-auto text-nexo-accent-gold" />
            <div>Executing live SSRF verification and HTTP probe...</div>
          </div>

          <div v-else-if="testProbeResult" class="space-y-3">
            <div
              :class="[
                'p-3 rounded border text-xs flex items-center justify-between',
                testProbeResult.success ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900'
              ]"
            >
              <div class="flex items-center gap-2">
                <CheckCircle v-if="testProbeResult.success" class="h-4 w-4 text-emerald-700" />
                <XCircle v-else class="h-4 w-4 text-red-700" />
                <span class="font-bold">{{ testProbeResult.message }}</span>
              </div>
              <div class="font-mono text-[11px]">
                Latency: {{ testProbeResult.latency_ms }}ms &middot; HTTP {{ testProbeResult.http_status || 'ERR' }}
              </div>
            </div>

            <div v-if="testProbeResult.attribution" class="p-2 bg-slate-50 border border-slate-200 rounded text-slate-700 font-mono text-[11px]">
              <span class="font-bold">Attribution Notice:</span> {{ testProbeResult.attribution }}
            </div>

            <div v-if="testProbeResult.sample_items?.length" class="space-y-2">
              <div class="font-mono text-[11px] font-bold text-nexo-text-primary">
                Sample Extracted Items ({{ testProbeResult.sample_items.length }}):
              </div>
              <div
                v-for="(item, idx) in testProbeResult.sample_items"
                :key="idx"
                class="p-2.5 bg-white border border-nexo-border rounded space-y-1"
              >
                <div class="font-bold text-nexo-text-primary">{{ item.title }}</div>
                <div class="text-[10px] font-mono text-nexo-text-muted flex items-center justify-between">
                  <a :href="item.url" target="_blank" class="text-nexo-accent-cyan hover:underline truncate max-w-sm">{{ item.url }}</a>
                  <span>{{ item.published_at || 'Recent' }}</span>
                </div>
                <div v-if="item.abstract || item.summary" class="text-nexo-text-secondary text-[11px]">
                  {{ item.abstract || item.summary }}
                </div>
              </div>
            </div>
          </div>

          <div class="pt-3 flex justify-end">
            <Button variant="secondary" size="sm" @click="isTestModalOpen = false">Close</Button>
          </div>
        </div>
      </Modal>

      <!-- MODAL: APPROVE SOURCE -->
      <Modal :is-open="isApproveModalOpen" title="Approve Source for Ingestion" max-width="md" @close="isApproveModalOpen = false">
        <div class="space-y-4 text-xs text-left">
          <p>
            Approve <span class="font-bold text-nexo-text-primary">{{ activeSource?.name }}</span> and promote to active collection schedule?
          </p>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Editorial Approval Notes</label>
            <textarea v-model="approvalNotes" rows="3" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div class="flex justify-end space-x-2">
            <Button variant="ghost" size="sm" @click="isApproveModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white" @click="submitApproval">Confirm Approval</Button>
          </div>
        </div>
      </Modal>

      <!-- MODAL: REJECT SOURCE -->
      <Modal :is-open="isRejectModalOpen" title="Reject Onboarding Source" max-width="md" @close="isRejectModalOpen = false">
        <div class="space-y-4 text-xs text-left">
          <p>
            Reject <span class="font-bold text-nexo-text-primary">{{ activeSource?.name }}</span> and archive from review queue?
          </p>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Rejection Reason</label>
            <textarea v-model="rejectReason" rows="3" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div class="flex justify-end space-x-2">
            <Button variant="ghost" size="sm" @click="isRejectModalOpen = false">Cancel</Button>
            <Button variant="danger" size="sm" @click="submitRejection">Confirm Rejection</Button>
          </div>
        </div>
      </Modal>

      <!-- MODAL: RELIABILITY PROFILE -->
      <Modal :is-open="isReliabilityModalOpen" :title="`Reliability Profile: ${activeSource?.name}`" max-width="md" @close="isReliabilityModalOpen = false">
        <div v-if="activeSource?.reliability_profile" class="space-y-4 text-xs text-left">
          <div class="grid grid-cols-2 gap-3">
            <div class="p-3 bg-slate-50 border border-slate-200 rounded">
              <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Overall Score</div>
              <div class="text-xl font-bold text-nexo-accent-gold mt-0.5">
                {{ activeSource.reliability_profile.overall_score }}%
              </div>
              <div class="text-[10px] font-mono text-emerald-800 font-bold mt-0.5">
                {{ activeSource.reliability_profile.tier }}
              </div>
            </div>

            <div class="p-3 bg-slate-50 border border-slate-200 rounded">
              <div class="text-[10px] font-mono uppercase text-nexo-text-muted">Primary Source Ratio</div>
              <div class="text-xl font-bold text-nexo-text-primary mt-0.5">
                {{ Math.round((activeSource.reliability_profile.primary_source_ratio || 0.6) * 100) }}%
              </div>
              <div class="text-[10px] text-nexo-text-muted mt-0.5">Original reporting / disclosures</div>
            </div>
          </div>

          <div class="space-y-2 p-3 bg-white border border-nexo-border rounded font-mono text-[11px]">
            <div class="flex justify-between py-1 border-b border-nexo-border">
              <span>Factual Accuracy Score:</span>
              <span class="font-bold text-emerald-700">{{ activeSource.reliability_profile.factual_accuracy_score }}%</span>
            </div>
            <div class="flex justify-between py-1 border-b border-nexo-border">
              <span>Transparency & Attribution:</span>
              <span class="font-bold text-nexo-text-primary">{{ activeSource.reliability_profile.transparency_score }}%</span>
            </div>
            <div class="flex justify-between py-1 border-b border-nexo-border">
              <span>Peer Corroboration Rate:</span>
              <span class="font-bold text-nexo-accent-cyan">{{ activeSource.reliability_profile.corroboration_rate }}%</span>
            </div>
            <div class="flex justify-between py-1">
              <span>Corrections Issued:</span>
              <span class="font-bold text-amber-700">{{ activeSource.reliability_profile.corrections_count || 0 }}</span>
            </div>
          </div>

          <div class="text-[10px] text-nexo-text-muted">
            {{ activeSource.reliability_profile.notes }}
          </div>

          <div class="pt-2 flex justify-end">
            <Button variant="secondary" size="sm" @click="isReliabilityModalOpen = false">Close</Button>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>
