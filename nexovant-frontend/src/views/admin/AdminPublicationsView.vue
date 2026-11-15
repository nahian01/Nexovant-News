<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Input from '@/components/ui/Input.vue'
import Badge from '@/components/ui/Badge.vue'
import { adminService } from '@/services/api/admin.service'
import { publicationService } from '@/services/api/publication.service'
import {
  FileText,
  Sparkles,
  Download,
  CheckCircle2,
  Clock,
  Archive,
  Trash2,
  Eye,
  RefreshCw,
  Plus,
  Calendar,
  Layers,
  Shield,
  Palette,
  ExternalLink,
  Sliders,
  History,
  FileCheck,
  Send,
  AlertTriangle
} from 'lucide-vue-next'

interface PublicationRecord {
  id: string
  title: string
  subtitle?: string
  publication_type: string
  edition_number: string
  reporting_period?: string
  visibility: string
  status: string
  page_count: number
  evidence_completeness_score: number
  published_at?: string
  created_at: string
  template?: {
    id: string
    name: string
    confidentiality_label: string
  }
  revisions?: Array<{
    id: string
    version: number
    change_summary?: string
    created_at: string
    editor?: { name: string }
  }>
  content_json?: any
}

interface TemplateRecord {
  id: string
  name: string
  page_size: string
  orientation: string
  confidentiality_label: string
  brand_config: {
    primary_color?: string
    accent_color?: string
    logo_text?: string
  }
  is_default: boolean
  disclosure_text?: string
}

interface ScheduleRecord {
  id: string
  publication_type: string
  template_id?: string
  title_pattern: string
  frequency: string
  schedule_time: string
  timezone: string
  require_review: boolean
  is_enabled: boolean
  last_run_at?: string
  next_run_at: string
}

interface DownloadLogRecord {
  id: string
  publication_id: string
  ip_address: string
  user_agent: string
  user_id?: string
  downloaded_at: string
  publication?: {
    title: string
    edition_number: string
  }
}

// Active Tab
const activeTab = ref<'publications' | 'templates' | 'schedules' | 'distribution'>('publications')

// Telemetry & Metrics
const metrics = ref({
  total_publications: 0,
  published_editions: 0,
  pending_reviews: 0,
  drafts: 0,
  active_schedules: 0,
  total_downloads: 0,
})

// Lists State
const publications = ref<PublicationRecord[]>([])
const templates = ref<TemplateRecord[]>([])
const schedules = ref<ScheduleRecord[]>([])
const downloadLogs = ref<DownloadLogRecord[]>([])

// Loading & Notification State
const isLoading = ref(true)
const isSubmitting = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

// Filters
const searchQuery = ref('')
const typeFilter = ref('all')
const statusFilter = ref('all')

// Generate Modal State
const isGenerateModalOpen = ref(false)
const generateForm = ref({
  publication_type: 'daily_briefing',
  title: '',
  subtitle: '',
  reporting_period: '',
  template_id: '',
  visibility: 'premium',
  force_regenerate: false,
})

// Editorial Review & Revisions Drawer/Modal
const isReviewModalOpen = ref(false)
const selectedPublication = ref<PublicationRecord | null>(null)
const editableSummaryHighlight = ref('')
const editableSummaryOverview = ref('')
const revisionChangeSummary = ref('')
const activeSectionTab = ref<'summary' | 'intel' | 'companies' | 'tech' | 'revisions'>('summary')

// Template Creation Modal
const isTemplateModalOpen = ref(false)
const templateForm = ref({
  name: '',
  page_size: 'a4',
  orientation: 'portrait',
  confidentiality_label: 'STRICTLY CONFIDENTIAL // FOR SUBSCRIBERS ONLY',
  primary_color: '#0d1527',
  accent_color: '#d4af37',
  logo_text: 'NEXOVANT INTELLIGENCE',
  disclosure_text: 'This executive dossier is produced using Nexovant grounded intelligence pipelines.',
})

// Schedule Creation Modal
const isScheduleModalOpen = ref(false)
const scheduleForm = ref({
  publication_type: 'daily_briefing',
  template_id: '',
  title_pattern: 'Nexovant Morning Intelligence Briefing — {date}',
  frequency: 'daily',
  schedule_time: '06:00',
  timezone: 'UTC',
  require_review: true,
  is_enabled: true,
})

// HMAC Token Generator State
const tokenForm = ref({
  publication_id: '',
  expires_in_hours: 24,
  max_downloads: 5,
  allowed_ip: '',
})
const generatedTokenResult = ref<{
  download_url: string
  token: string
  expires_at: string
} | null>(null)

// Computed Publications Filter
const filteredPublications = computed(() => {
  return publications.value.filter(p => {
    const matchesSearch = !searchQuery.value ||
      p.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      p.edition_number.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesType = typeFilter.value === 'all' || p.publication_type === typeFilter.value
    const matchesStatus = statusFilter.value === 'all' || p.status === statusFilter.value
    return matchesSearch && matchesType && matchesStatus
  })
})

const publicationTypes = [
  { value: 'daily_briefing', label: 'Daily Briefing (06:00 UTC)' },
  { value: 'weekly_report', label: 'Weekly Macro Intelligence Report' },
  { value: 'monthly_outlook', label: 'Monthly Strategic Industry Outlook' },
  { value: 'breaking_bulletin', label: 'Breaking Intelligence Bulletin' },
  { value: 'company_dossier', label: 'Company Deep-Dive Dossier' },
  { value: 'executive_profile', label: 'Executive Leadership Profile' },
  { value: 'product_teardown', label: 'Product Architecture Teardown' },
  { value: 'technology_report', label: 'Emerging Technology Analysis' },
  { value: 'industry_landscape', label: 'Industry Landscape Map' },
  { value: 'custom_deep_research', label: 'Custom Deep Research Publication' },
]

// Data Fetching
const loadAllData = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const overviewRes = await adminService.getPublicationsOverview()
    if (overviewRes.success) {
      metrics.value = overviewRes.metrics
    }

    const pubsRes = await adminService.getPublications()
    if (pubsRes.success) {
      publications.value = pubsRes.data
    }

    const tmplRes = await adminService.getPublicationTemplates()
    if (tmplRes.success) {
      templates.value = tmplRes.templates
      if (templates.value.length > 0 && !generateForm.value.template_id) {
        generateForm.value.template_id = templates.value[0].id
        scheduleForm.value.template_id = templates.value[0].id
      }
    }

    const schedRes = await adminService.getPublicationSchedules()
    if (schedRes.success) {
      schedules.value = schedRes.schedules
    }

    const logsRes = await adminService.getPublicationDownloadLogs()
    if (logsRes.success) {
      downloadLogs.value = logsRes.logs
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load publication infrastructure data.'
  } finally {
    isLoading.value = false
  }
}

// Generate Publication
const handleGeneratePublication = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  statusMessage.value = ''
  try {
    const res = await adminService.createPublication(generateForm.value)
    if (res.success) {
      statusMessage.value = `Publication "${res.publication.title}" synthesized and queued for review (Completeness: ${res.publication.evidence_completeness_score}%).`
      isGenerateModalOpen.value = false
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to generate publication.'
  } finally {
    isSubmitting.value = false
  }
}

// Open Editorial Review Drawer
const openReviewModal = async (pub: PublicationRecord) => {
  selectedPublication.value = pub
  isReviewModalOpen.value = true
  activeSectionTab.value = 'summary'
  revisionChangeSummary.value = ''

  try {
    const full = await adminService.getPublication(pub.id)
    if (full.success) {
      selectedPublication.value = full.publication
      editableSummaryHighlight.value = full.publication.content_json?.executive_summary?.highlight || ''
      editableSummaryOverview.value = full.publication.content_json?.executive_summary?.overview || ''
    }
  } catch {
    editableSummaryHighlight.value = pub.content_json?.executive_summary?.highlight || ''
    editableSummaryOverview.value = pub.content_json?.executive_summary?.overview || ''
  }
}

// Save Content Edit / Revision
const handleSaveRevision = async () => {
  if (!selectedPublication.value) return
  isSubmitting.value = true
  try {
    const updatedContent = JSON.parse(JSON.stringify(selectedPublication.value.content_json || {}))
    if (!updatedContent.executive_summary) {
      updatedContent.executive_summary = {}
    }
    updatedContent.executive_summary.highlight = editableSummaryHighlight.value
    updatedContent.executive_summary.overview = editableSummaryOverview.value

    const res = await adminService.updatePublication(selectedPublication.value.id, {
      content_json: updatedContent,
      change_summary: revisionChangeSummary.value || 'Editorial adjustment of executive overview.',
    })

    if (res.success) {
      selectedPublication.value = res.publication
      statusMessage.value = 'New publication revision stored and vector PDF re-rendered.'
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to save revision.'
  } finally {
    isSubmitting.value = false
  }
}

// Workflow Actions
const handleApprove = async (id: string) => {
  try {
    const res = await adminService.approvePublication(id)
    if (res.success) {
      statusMessage.value = 'Publication edition approved by Chief Analyst.'
      if (selectedPublication.value && selectedPublication.value.id === id) {
        selectedPublication.value.status = 'approved'
      }
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to approve publication.'
  }
}

const handlePublish = async (id: string) => {
  try {
    const res = await adminService.publishPublication(id)
    if (res.success) {
      statusMessage.value = 'Publication live and broadcast to subscriber feeds.'
      if (selectedPublication.value && selectedPublication.value.id === id) {
        selectedPublication.value.status = 'published'
      }
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to publish edition.'
  }
}

const handleArchive = async (id: string) => {
  try {
    const res = await adminService.archivePublication(id)
    if (res.success) {
      statusMessage.value = 'Publication archived.'
      if (selectedPublication.value && selectedPublication.value.id === id) {
        selectedPublication.value.status = 'archived'
      }
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to archive edition.'
  }
}

const handleDelete = async (id: string) => {
  if (!confirm('Are you sure you want to permanently delete this publication edition and its rendered PDF binary?')) return
  try {
    const res = await adminService.deletePublication(id)
    if (res.success) {
      statusMessage.value = 'Publication and storage assets removed.'
      isReviewModalOpen.value = false
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete publication.'
  }
}

// Download PDF (Stream with HMAC Token)
const handleDownloadPdf = async (pub: PublicationRecord) => {
  try {
    const tokenRes = await publicationService.getDownloadToken(pub.id)
    if (tokenRes.success && tokenRes.download_url) {
      window.open(tokenRes.download_url, '_blank')
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Unauthorized or expired download entitlement.'
  }
}

// Template Creation
const handleCreateTemplate = async () => {
  isSubmitting.value = true
  try {
    const res = await adminService.createPublicationTemplate({
      name: templateForm.value.name,
      page_size: templateForm.value.page_size,
      orientation: templateForm.value.orientation,
      confidentiality_label: templateForm.value.confidentiality_label,
      disclosure_text: templateForm.value.disclosure_text,
      brand_config: {
        primary_color: templateForm.value.primary_color,
        accent_color: templateForm.value.accent_color,
        logo_text: templateForm.value.logo_text,
      },
    })
    if (res.success) {
      statusMessage.value = `Custom template "${res.template.name}" created.`
      isTemplateModalOpen.value = false
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create template.'
  } finally {
    isSubmitting.value = false
  }
}

// Schedule Actions
const handleCreateSchedule = async () => {
  isSubmitting.value = true
  try {
    const res = await adminService.createPublicationSchedule(scheduleForm.value)
    if (res.success) {
      statusMessage.value = `Cadence schedule created (Next run: ${res.schedule.next_run_at}).`
      isScheduleModalOpen.value = false
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create schedule.'
  } finally {
    isSubmitting.value = false
  }
}

const handleTriggerSchedule = async (id: string) => {
  try {
    const res = await adminService.triggerPublicationSchedule(id)
    if (res.success) {
      statusMessage.value = `Schedule triggered! Generated edition: "${res.publication.title}"`
      await loadAllData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to trigger schedule execution.'
  }
}

// Generate Custom HMAC Link
const handleGenerateCustomToken = async () => {
  if (!tokenForm.value.publication_id) return
  isSubmitting.value = true
  try {
    const res = await adminService.generatePublicationToken(tokenForm.value.publication_id, {
      expires_in_hours: tokenForm.value.expires_in_hours,
      max_downloads: tokenForm.value.max_downloads,
      allowed_ip: tokenForm.value.allowed_ip || null,
    })
    if (res.success) {
      generatedTokenResult.value = {
        download_url: res.download_url,
        token: res.token,
        expires_at: res.expires_at,
      }
      statusMessage.value = 'HMAC SHA-256 signed download token generated.'
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to generate signed download token.'
  } finally {
    isSubmitting.value = false
  }
}

const handleRevokeTokens = async (publicationId: string) => {
  if (!confirm('Revoke all outstanding HMAC download tokens for this publication? Existing download links will immediately cease functioning.')) return
  try {
    const res = await adminService.revokePublicationTokens(publicationId)
    if (res.success) {
      statusMessage.value = `Revoked ${res.revoked_count} download tokens.`
      generatedTokenResult.value = null
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to revoke tokens.'
  }
}

onMounted(() => {
  loadAllData()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Header Banner -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-nexo-border/80 pb-5">
        <div>
          <div class="flex items-center gap-2.5">
            <h1 class="editorial-title text-2xl font-serif font-bold text-nexo-text-primary">
              Executive Publications & PDF Engine
            </h1>
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-mono font-medium bg-amber-50 text-amber-800 border border-amber-200">
              <Sparkles class="w-3 h-3 text-amber-600" /> Grounded Dossiers
            </span>
          </div>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Produce vector-crisp executive PDF intelligence publications grounded in verified facts, corporate developments, and RAG knowledge.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" @click="loadAllData">
            <RefreshCw class="w-3.5 h-3.5 mr-1" :class="{ 'animate-spin': isLoading }" /> Refresh
          </Button>
          <Button variant="primary" size="sm" @click="isGenerateModalOpen = true">
            <Plus class="w-3.5 h-3.5 mr-1" /> Generate Publication
          </Button>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="statusMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded flex items-center justify-between">
        <span>✓ {{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="text-emerald-600 hover:text-emerald-900 font-bold ml-2">✕</button>
      </div>

      <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded flex items-center justify-between">
        <span>✕ {{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="text-rose-600 hover:text-rose-900 font-bold ml-2">✕</button>
      </div>

      <!-- Telemetry Metric Cards -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <Card class="p-3.5 border-l-4 border-l-blue-600 bg-white shadow-xs">
          <div class="text-[11px] font-mono text-nexo-text-muted uppercase">Total Publications</div>
          <div class="text-2xl font-serif font-bold text-nexo-text-primary mt-1">{{ metrics.total_publications }}</div>
          <div class="text-[10px] text-nexo-text-secondary mt-0.5">All formats & tiers</div>
        </Card>

        <Card class="p-3.5 border-l-4 border-l-emerald-600 bg-white shadow-xs">
          <div class="text-[11px] font-mono text-nexo-text-muted uppercase">Published Editions</div>
          <div class="text-2xl font-serif font-bold text-emerald-700 mt-1">{{ metrics.published_editions }}</div>
          <div class="text-[10px] text-nexo-text-secondary mt-0.5">Live on subscriber feeds</div>
        </Card>

        <Card class="p-3.5 border-l-4 border-l-amber-500 bg-white shadow-xs">
          <div class="text-[11px] font-mono text-nexo-text-muted uppercase">Pending Review</div>
          <div class="text-2xl font-serif font-bold text-amber-700 mt-1">{{ metrics.pending_reviews }}</div>
          <div class="text-[10px] text-nexo-text-secondary mt-0.5">Awaiting editorial sign-off</div>
        </Card>

        <Card class="p-3.5 border-l-4 border-l-slate-400 bg-white shadow-xs">
          <div class="text-[11px] font-mono text-nexo-text-muted uppercase">Drafts & Staging</div>
          <div class="text-2xl font-serif font-bold text-slate-700 mt-1">{{ metrics.drafts }}</div>
          <div class="text-[10px] text-nexo-text-secondary mt-0.5">In-progress syntheses</div>
        </Card>

        <Card class="p-3.5 border-l-4 border-l-indigo-600 bg-white shadow-xs">
          <div class="text-[11px] font-mono text-nexo-text-muted uppercase">Active Schedules</div>
          <div class="text-2xl font-serif font-bold text-indigo-700 mt-1">{{ metrics.active_schedules }}</div>
          <div class="text-[10px] text-nexo-text-secondary mt-0.5">Automated cron daemons</div>
        </Card>

        <Card class="p-3.5 border-l-4 border-l-purple-600 bg-white shadow-xs">
          <div class="text-[11px] font-mono text-nexo-text-muted uppercase">Secure Downloads</div>
          <div class="text-2xl font-serif font-bold text-purple-700 mt-1">{{ metrics.total_downloads }}</div>
          <div class="text-[10px] text-nexo-text-secondary mt-0.5">HMAC verified streams</div>
        </Card>
      </div>

      <!-- Navigation Tabs -->
      <div class="border-b border-nexo-border flex gap-4 text-xs font-medium">
        <button
          @click="activeTab = 'publications'"
          class="pb-2.5 px-1 border-b-2 transition-colors flex items-center gap-1.5"
          :class="activeTab === 'publications' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <FileText class="w-4 h-4" /> Publications Archive ({{ publications.length }})
        </button>

        <button
          @click="activeTab = 'templates'"
          class="pb-2.5 px-1 border-b-2 transition-colors flex items-center gap-1.5"
          :class="activeTab === 'templates' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <Palette class="w-4 h-4" /> Layout Templates ({{ templates.length }})
        </button>

        <button
          @click="activeTab = 'schedules'"
          class="pb-2.5 px-1 border-b-2 transition-colors flex items-center gap-1.5"
          :class="activeTab === 'schedules' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <Calendar class="w-4 h-4" /> Cadence Schedules ({{ schedules.length }})
        </button>

        <button
          @click="activeTab = 'distribution'"
          class="pb-2.5 px-1 border-b-2 transition-colors flex items-center gap-1.5"
          :class="activeTab === 'distribution' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
        >
          <Shield class="w-4 h-4" /> HMAC Distribution & Logs
        </button>
      </div>

      <!-- TAB 1: Publications Archive -->
      <div v-if="activeTab === 'publications'" class="space-y-4">
        <!-- Search and Filters Bar -->
        <div class="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white p-3 rounded-lg border border-nexo-border/80">
          <div class="w-full sm:w-72">
            <Input v-model="searchQuery" placeholder="Search by title or edition #..." size="sm" />
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto">
            <select
              v-model="typeFilter"
              class="text-xs bg-white border border-nexo-border rounded px-2.5 py-1.5 text-nexo-text-primary focus:outline-hidden"
            >
              <option value="all">All Formats (10)</option>
              <option v-for="t in publicationTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>

            <select
              v-model="statusFilter"
              class="text-xs bg-white border border-nexo-border rounded px-2.5 py-1.5 text-nexo-text-primary focus:outline-hidden"
            >
              <option value="all">All Statuses</option>
              <option value="draft">Draft</option>
              <option value="pending_review">Pending Review</option>
              <option value="approved">Approved</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        </div>

        <!-- Publications Table -->
        <div class="bg-white rounded-lg border border-nexo-border/80 overflow-hidden shadow-xs">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/75 border-b border-nexo-border text-[11px] font-mono text-nexo-text-muted uppercase">
                <th class="py-3 px-4">Edition / Title</th>
                <th class="py-3 px-3">Type & Cadence</th>
                <th class="py-3 px-3">Reporting Period</th>
                <th class="py-3 px-3">Evidence Score</th>
                <th class="py-3 px-3">Access Tier</th>
                <th class="py-3 px-3">Status</th>
                <th class="py-3 px-3">Pages</th>
                <th class="py-3 px-4 text-right">Operational Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border/60">
              <tr v-if="filteredPublications.length === 0">
                <td colspan="8" class="text-center py-10 text-nexo-text-muted text-xs">
                  No executive publication editions match your filters. Click "Generate Publication" to synthesize an edition.
                </td>
              </tr>
              <tr v-for="pub in filteredPublications" :key="pub.id" class="hover:bg-amber-50/20 transition-colors">
                <td class="py-3 px-4">
                  <div class="font-semibold text-nexo-text-primary text-sm flex items-center gap-1.5">
                    {{ pub.title }}
                  </div>
                  <div class="text-[11px] font-mono text-nexo-text-muted mt-0.5 flex items-center gap-2">
                    <span class="text-amber-800 font-bold bg-amber-50 px-1 rounded">{{ pub.edition_number }}</span>
                    <span>• Template: {{ pub.template?.name || 'Default' }}</span>
                  </div>
                </td>
                <td class="py-3 px-3">
                  <span class="font-mono text-[11px] text-nexo-accent-cyan uppercase bg-sky-50 px-1.5 py-0.5 rounded border border-sky-100">
                    {{ pub.publication_type.replace('_', ' ') }}
                  </span>
                </td>
                <td class="py-3 px-3 text-nexo-text-secondary font-mono text-[11px]">
                  {{ pub.reporting_period || 'Standard Interval' }}
                </td>
                <td class="py-3 px-3">
                  <div class="flex items-center gap-2">
                    <div class="w-12 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                      <div
                        class="h-full rounded-full"
                        :class="pub.evidence_completeness_score >= 80 ? 'bg-emerald-600' : 'bg-amber-500'"
                        :style="{ width: `${pub.evidence_completeness_score}%` }"
                      ></div>
                    </div>
                    <span class="font-mono font-bold text-[11px]" :class="pub.evidence_completeness_score >= 80 ? 'text-emerald-700' : 'text-amber-700'">
                      {{ pub.evidence_completeness_score }}%
                    </span>
                  </div>
                </td>
                <td class="py-3 px-3">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono uppercase font-bold"
                    :class="{
                      'bg-slate-100 text-slate-700': pub.visibility === 'public',
                      'bg-blue-50 text-blue-700 border border-blue-200': pub.visibility === 'registered',
                      'bg-amber-50 text-amber-800 border border-amber-200': pub.visibility === 'premium',
                      'bg-purple-50 text-purple-800 border border-purple-200': pub.visibility === 'enterprise',
                      'bg-rose-50 text-rose-800 border border-rose-200': pub.visibility === 'internal',
                    }"
                  >
                    {{ pub.visibility }}
                  </span>
                </td>
                <td class="py-3 px-3">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-mono font-medium"
                    :class="{
                      'bg-slate-100 text-slate-600': pub.status === 'draft',
                      'bg-amber-100 text-amber-800': pub.status === 'pending_review',
                      'bg-blue-100 text-blue-800': pub.status === 'approved',
                      'bg-emerald-100 text-emerald-800': pub.status === 'published',
                      'bg-gray-100 text-gray-500': pub.status === 'archived',
                    }"
                  >
                    {{ pub.status.replace('_', ' ') }}
                  </span>
                </td>
                <td class="py-3 px-3 font-mono text-[11px] text-nexo-text-secondary">
                  {{ pub.page_count }} pg
                </td>
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="openReviewModal(pub)"
                      title="Inspect & Edit Editorial Content"
                      class="p-1 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded"
                    >
                      <Eye class="w-4 h-4" />
                    </button>

                    <button
                      @click="handleDownloadPdf(pub)"
                      title="Stream HMAC PDF"
                      class="p-1 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded"
                    >
                      <Download class="w-4 h-4" />
                    </button>

                    <button
                      v-if="pub.status === 'pending_review'"
                      @click="handleApprove(pub.id)"
                      title="Approve Edition"
                      class="p-1 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded"
                    >
                      <CheckCircle2 class="w-4 h-4" />
                    </button>

                    <button
                      v-if="pub.status === 'approved' || pub.status === 'pending_review'"
                      @click="handlePublish(pub.id)"
                      title="Publish Live"
                      class="p-1 text-emerald-600 hover:text-emerald-900 hover:bg-emerald-50 rounded"
                    >
                      <Send class="w-4 h-4" />
                    </button>

                    <button
                      v-if="pub.status !== 'archived'"
                      @click="handleArchive(pub.id)"
                      title="Archive Edition"
                      class="p-1 text-amber-600 hover:text-amber-900 hover:bg-amber-50 rounded"
                    >
                      <Archive class="w-4 h-4" />
                    </button>

                    <button
                      @click="handleDelete(pub.id)"
                      title="Delete Edition"
                      class="p-1 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: Templates & Branding -->
      <div v-if="activeTab === 'templates'" class="space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-xs text-nexo-text-secondary">
            Configure layout geometry, confidentiality stamps, headers, and color branding for generated PDF documents.
          </p>
          <Button variant="primary" size="sm" @click="isTemplateModalOpen = true">
            <Plus class="w-3.5 h-3.5 mr-1" /> New Template
          </Button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <Card v-for="t in templates" :key="t.id" class="p-4 bg-white border border-nexo-border shadow-xs space-y-3">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="font-serif font-bold text-nexo-text-primary text-sm">{{ t.name }}</h3>
                <span class="text-[10px] font-mono text-nexo-text-muted uppercase">
                  {{ t.page_size }} • {{ t.orientation }}
                </span>
              </div>
              <span v-if="t.is_default" class="text-[10px] font-mono px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-bold border border-amber-200">
                Default
              </span>
            </div>

            <div class="p-2.5 bg-slate-50 rounded border border-slate-100 space-y-1.5 text-xs">
              <div class="flex items-center justify-between text-[11px]">
                <span class="text-nexo-text-muted">Confidentiality:</span>
                <span class="font-mono text-rose-700 font-semibold truncate max-w-[180px]">{{ t.confidentiality_label }}</span>
              </div>
              <div class="flex items-center justify-between text-[11px]">
                <span class="text-nexo-text-muted">Primary Brand:</span>
                <div class="flex items-center gap-1.5">
                  <span class="w-3.5 h-3.5 rounded border border-slate-300" :style="{ backgroundColor: t.brand_config?.primary_color || '#0d1527' }"></span>
                  <span class="font-mono">{{ t.brand_config?.primary_color || '#0d1527' }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between text-[11px]">
                <span class="text-nexo-text-muted">Accent Gold:</span>
                <div class="flex items-center gap-1.5">
                  <span class="w-3.5 h-3.5 rounded border border-slate-300" :style="{ backgroundColor: t.brand_config?.accent_color || '#d4af37' }"></span>
                  <span class="font-mono">{{ t.brand_config?.accent_color || '#d4af37' }}</span>
                </div>
              </div>
            </div>

            <div class="text-[11px] text-nexo-text-secondary italic">
              {{ t.disclosure_text || 'Standard editorial disclosure.' }}
            </div>
          </Card>
        </div>
      </div>

      <!-- TAB 3: Cadence Schedules -->
      <div v-if="activeTab === 'schedules'" class="space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-xs text-nexo-text-secondary">
            Automate publication synthesis via background cron. Configure daily briefings (06:00 UTC) and weekly macro reports.
          </p>
          <Button variant="primary" size="sm" @click="isScheduleModalOpen = true">
            <Plus class="w-3.5 h-3.5 mr-1" /> New Schedule
          </Button>
        </div>

        <div class="bg-white rounded-lg border border-nexo-border/80 overflow-hidden shadow-xs">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/75 border-b border-nexo-border text-[11px] font-mono text-nexo-text-muted uppercase">
                <th class="py-3 px-4">Publication Format</th>
                <th class="py-3 px-3">Title Expression</th>
                <th class="py-3 px-3">Cadence</th>
                <th class="py-3 px-3">Scheduled Time</th>
                <th class="py-3 px-3">Review Gate</th>
                <th class="py-3 px-3">Next Run</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border/60">
              <tr v-if="schedules.length === 0">
                <td colspan="7" class="text-center py-8 text-nexo-text-muted text-xs">
                  No automated publication schedules configured. Click "New Schedule" to create a cron trigger.
                </td>
              </tr>
              <tr v-for="s in schedules" :key="s.id" class="hover:bg-slate-50/50">
                <td class="py-3 px-4 font-semibold text-nexo-text-primary">
                  {{ s.publication_type.replace('_', ' ').toUpperCase() }}
                </td>
                <td class="py-3 px-3 font-mono text-[11px] text-nexo-text-secondary">
                  {{ s.title_pattern }}
                </td>
                <td class="py-3 px-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-blue-50 text-blue-700 font-bold uppercase">
                    {{ s.frequency }}
                  </span>
                </td>
                <td class="py-3 px-3 font-mono text-[11px]">
                  {{ s.schedule_time }} ({{ s.timezone }})
                </td>
                <td class="py-3 px-3">
                  <span v-if="s.require_review" class="text-amber-700 text-[11px] font-medium flex items-center gap-1">
                    <Clock class="w-3.5 h-3.5" /> Requires Approval
                  </span>
                  <span v-else class="text-emerald-700 text-[11px] font-medium flex items-center gap-1">
                    <CheckCircle2 class="w-3.5 h-3.5" /> Auto-Publish
                  </span>
                </td>
                <td class="py-3 px-3 font-mono text-[11px] text-nexo-text-secondary">
                  {{ new Date(s.next_run_at).toLocaleString() }}
                </td>
                <td class="py-3 px-4 text-right">
                  <Button variant="outline" size="sm" @click="handleTriggerSchedule(s.id)">
                    <RefreshCw class="w-3 h-3 mr-1" /> Run Now
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 4: HMAC Distribution & Logs -->
      <div v-if="activeTab === 'distribution'" class="space-y-6">
        <!-- Token Generator Widget -->
        <Card class="p-4 bg-white border border-nexo-border shadow-xs space-y-4">
          <div>
            <h3 class="font-serif font-bold text-nexo-text-primary text-sm flex items-center gap-1.5">
              <Shield class="w-4 h-4 text-emerald-700" /> HMAC-SHA256 Token Generator & Distribution
            </h3>
            <p class="text-xs text-nexo-text-secondary mt-0.5">
              Generate tamper-proof signed streaming URLs with cryptographically enforced expiration and download counters.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
              <label class="block text-[11px] font-mono text-nexo-text-muted mb-1">Target Publication</label>
              <select
                v-model="tokenForm.publication_id"
                class="w-full text-xs bg-white border border-nexo-border rounded px-2.5 py-1.5 text-nexo-text-primary focus:outline-hidden"
              >
                <option value="">Select an edition...</option>
                <option v-for="p in publications" :key="p.id" :value="p.id">
                  {{ p.edition_number }} — {{ p.title }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-[11px] font-mono text-nexo-text-muted mb-1">Validity (Hours)</label>
              <Input v-model="tokenForm.expires_in_hours" type="number" size="sm" />
            </div>

            <div>
              <label class="block text-[11px] font-mono text-nexo-text-muted mb-1">Max Downloads Allowed</label>
              <Input v-model="tokenForm.max_downloads" type="number" size="sm" />
            </div>

            <div class="flex gap-2">
              <Button
                variant="primary"
                size="sm"
                class="w-full"
                :disabled="!tokenForm.publication_id || isSubmitting"
                @click="handleGenerateCustomToken"
              >
                Sign Token Link
              </Button>
              <Button
                v-if="tokenForm.publication_id"
                variant="outline"
                size="sm"
                title="Revoke all active links for this publication"
                @click="handleRevokeTokens(tokenForm.publication_id)"
              >
                Revoke All
              </Button>
            </div>
          </div>

          <!-- Token Link Output Box -->
          <div v-if="generatedTokenResult" class="p-3 bg-slate-900 text-slate-100 rounded-lg space-y-2 text-xs font-mono">
            <div class="flex items-center justify-between text-[11px] text-amber-400">
              <span>HMAC-SHA256 Signed Link (Expires: {{ new Date(generatedTokenResult.expires_at).toLocaleString() }})</span>
              <a :href="generatedTokenResult.download_url" target="_blank" class="text-cyan-400 hover:underline flex items-center gap-1">
                Open Stream <ExternalLink class="w-3 h-3" />
              </a>
            </div>
            <div class="p-2 bg-slate-950 rounded border border-slate-800 break-all select-all text-[11px]">
              {{ generatedTokenResult.download_url }}
            </div>
          </div>
        </Card>

        <!-- Download Logs Table -->
        <Card class="bg-white border border-nexo-border/80 shadow-xs overflow-hidden">
          <div class="p-3.5 border-b border-nexo-border bg-slate-50/50 flex items-center justify-between">
            <h4 class="font-serif font-bold text-nexo-text-primary text-xs uppercase tracking-wide">
              HMAC Download Verification Audit Logs
            </h4>
            <span class="text-[10px] font-mono text-nexo-text-muted">Showing recent 50 streams</span>
          </div>

          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50/75 border-b border-nexo-border text-[10px] font-mono text-nexo-text-muted uppercase">
                <th class="py-2.5 px-4">Publication</th>
                <th class="py-2.5 px-3">IP Address</th>
                <th class="py-2.5 px-3">User Agent</th>
                <th class="py-2.5 px-4 text-right">Timestamp</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border/60">
              <tr v-if="downloadLogs.length === 0">
                <td colspan="4" class="text-center py-6 text-nexo-text-muted text-xs">
                  No verified PDF downloads logged yet.
                </td>
              </tr>
              <tr v-for="log in downloadLogs" :key="log.id" class="hover:bg-slate-50/50">
                <td class="py-2.5 px-4 font-semibold text-nexo-text-primary">
                  {{ log.publication?.title || 'Executive Dossier' }}
                  <span class="text-[10px] font-mono text-nexo-text-muted ml-1">({{ log.publication?.edition_number || log.publication_id.substring(0, 8) }})</span>
                </td>
                <td class="py-2.5 px-3 font-mono text-[11px] text-nexo-text-secondary">
                  {{ log.ip_address }}
                </td>
                <td class="py-2.5 px-3 text-[11px] text-nexo-text-muted truncate max-w-xs font-mono">
                  {{ log.user_agent }}
                </td>
                <td class="py-2.5 px-4 text-right font-mono text-[11px] text-nexo-text-secondary">
                  {{ new Date(log.downloaded_at).toLocaleString() }}
                </td>
              </tr>
            </tbody>
          </table>
        </Card>
      </div>

      <!-- MODAL 1: Generate Publication -->
      <Modal :is-open="isGenerateModalOpen" title="Generate Executive Intelligence Publication" @close="isGenerateModalOpen = false">
        <form @submit.prevent="handleGeneratePublication" class="space-y-4 text-left">
          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Publication Format & Cadence</label>
            <select
              v-model="generateForm.publication_type"
              class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
            >
              <option v-for="t in publicationTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Dossier Headline / Title</label>
            <Input v-model="generateForm.title" placeholder="e.g. European Foundry & Advanced Packaging Ecosystem" required />
          </div>

          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Subtitle / Strategic Focus</label>
            <Input v-model="generateForm.subtitle" placeholder="e.g. High-NA EUV Tooling Deployment & Cross-Border Sovereign Subsidies" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Reporting Period</label>
              <Input v-model="generateForm.reporting_period" placeholder="e.g. Nov 08, 2026 – Nov 15, 2026" />
            </div>

            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Access Tier</label>
              <select
                v-model="generateForm.visibility"
                class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
              >
                <option value="public">Public (All Visitors)</option>
                <option value="registered">Registered Members</option>
                <option value="premium">Premium Subscribers</option>
                <option value="enterprise">Enterprise Tier Only</option>
                <option value="internal">Internal Editorial Review</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Styling Template</label>
            <select
              v-model="generateForm.template_id"
              class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
            >
              <option v-for="tmpl in templates" :key="tmpl.id" :value="tmpl.id">
                {{ tmpl.name }} ({{ tmpl.page_size.toUpperCase() }} {{ tmpl.orientation }})
              </option>
            </select>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input type="checkbox" id="force_regen" v-model="generateForm.force_regenerate" class="rounded border-slate-300" />
            <label for="force_regen" class="text-xs text-nexo-text-secondary">
              Force regenerate (bypass idempotency cache key)
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
            <Button variant="outline" size="sm" type="button" @click="isGenerateModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">
              <Sparkles class="w-3.5 h-3.5 mr-1" />
              {{ isSubmitting ? 'Synthesizing with AI Router...' : 'Execute Grounded Synthesis' }}
            </Button>
          </div>
        </form>
      </Modal>

      <!-- MODAL 2: Editorial Review & Revisions Drawer -->
      <Modal
        :is-open="isReviewModalOpen"
        :title="`Editorial Review & Version History — ${selectedPublication?.edition_number || ''}`"
        @close="isReviewModalOpen = false"
      >
        <div v-if="selectedPublication" class="space-y-4 text-left max-h-[75vh] overflow-y-auto pr-1">
          <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
            <div>
              <div class="font-serif font-bold text-nexo-text-primary text-base">{{ selectedPublication.title }}</div>
              <div class="text-xs text-nexo-text-secondary mt-0.5">{{ selectedPublication.subtitle }}</div>
            </div>
            <div class="flex items-center gap-2">
              <Button variant="outline" size="sm" @click="handleDownloadPdf(selectedPublication)">
                <Download class="w-3.5 h-3.5 mr-1" /> Stream PDF
              </Button>
              <Button
                v-if="selectedPublication.status === 'pending_review'"
                variant="primary"
                size="sm"
                @click="handleApprove(selectedPublication.id)"
              >
                Approve
              </Button>
              <Button
                v-if="selectedPublication.status === 'approved'"
                variant="primary"
                size="sm"
                @click="handlePublish(selectedPublication.id)"
              >
                Publish Live
              </Button>
            </div>
          </div>

          <!-- Section Switcher Tabs -->
          <div class="flex border-b border-nexo-border text-xs gap-3">
            <button
              @click="activeSectionTab = 'summary'"
              class="pb-2 px-1 border-b-2 font-medium"
              :class="activeSectionTab === 'summary' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary'"
            >
              Executive Summary
            </button>
            <button
              @click="activeSectionTab = 'intel'"
              class="pb-2 px-1 border-b-2 font-medium"
              :class="activeSectionTab === 'intel' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary'"
            >
              Corroborated Intelligence
            </button>
            <button
              @click="activeSectionTab = 'companies'"
              class="pb-2 px-1 border-b-2 font-medium"
              :class="activeSectionTab === 'companies' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary'"
            >
              Corporate & Tech
            </button>
            <button
              @click="activeSectionTab = 'revisions'"
              class="pb-2 px-1 border-b-2 font-medium"
              :class="activeSectionTab === 'revisions' ? 'border-nexo-text-primary text-nexo-text-primary font-bold' : 'border-transparent text-nexo-text-secondary'"
            >
              Revision History ({{ selectedPublication.revisions?.length || 1 }})
            </button>
          </div>

          <!-- SECTION: Executive Summary Edit -->
          <div v-if="activeSectionTab === 'summary'" class="space-y-3">
            <div>
              <label class="block text-xs font-semibold text-nexo-text-primary mb-1">Key Strategic Highlight (Callout)</label>
              <Input v-model="editableSummaryHighlight" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-nexo-text-primary mb-1">Synthesized Executive Narrative</label>
              <textarea
                v-model="editableSummaryOverview"
                rows="6"
                class="w-full text-xs font-serif leading-relaxed border border-nexo-border rounded p-3 text-nexo-text-primary focus:outline-hidden"
              ></textarea>
            </div>

            <div>
              <label class="block text-xs font-semibold text-nexo-text-primary mb-1">Change Summary / Editorial Reason</label>
              <Input v-model="revisionChangeSummary" placeholder="e.g. Clarified IMEC pilot schedule per Reuters confirmation." />
            </div>

            <div class="flex justify-end">
              <Button variant="primary" size="sm" :disabled="isSubmitting" @click="handleSaveRevision">
                <FileCheck class="w-3.5 h-3.5 mr-1" /> Save Revision & Re-Render PDF
              </Button>
            </div>
          </div>

          <!-- SECTION: Corroborated Intelligence Preview -->
          <div v-if="activeSectionTab === 'intel'" class="space-y-3">
            <div
              v-for="(item, idx) in selectedPublication.content_json?.key_intelligence || []"
              :key="idx"
              class="p-3 bg-white border border-slate-200 rounded text-xs space-y-1"
            >
              <div class="font-bold text-nexo-text-primary">{{ item.title }}</div>
              <p class="text-nexo-text-secondary">{{ item.summary }}</p>
              <div class="flex items-center gap-3 text-[11px] font-mono text-emerald-700 pt-1">
                <span>Confidence: {{ item.confidence }}</span>
                <span>Corroborating Sources: {{ item.source_count }}</span>
              </div>
            </div>
          </div>

          <!-- SECTION: Corporate & Tech Preview -->
          <div v-if="activeSectionTab === 'companies'" class="space-y-3">
            <h4 class="text-xs font-mono uppercase text-nexo-text-muted">Monitored Corporate Movements</h4>
            <div
              v-for="(c, idx) in selectedPublication.content_json?.company_intelligence || []"
              :key="'c-' + idx"
              class="p-2.5 bg-slate-50 border border-slate-200 rounded text-xs"
            >
              <div class="font-bold text-nexo-text-primary">{{ c.company_name }} ({{ c.ticker }})</div>
              <p class="text-nexo-text-secondary mt-0.5">{{ c.announcement }}</p>
            </div>

            <h4 class="text-xs font-mono uppercase text-nexo-text-muted pt-2">Emerging Technology Architecture</h4>
            <div
              v-for="(t, idx) in selectedPublication.content_json?.technology_intelligence || []"
              :key="'t-' + idx"
              class="p-2.5 bg-slate-50 border border-slate-200 rounded text-xs"
            >
              <div class="font-bold text-nexo-text-primary">{{ t.name }}</div>
              <p class="text-nexo-text-secondary mt-0.5">{{ t.description }}</p>
            </div>
          </div>

          <!-- SECTION: Revision History -->
          <div v-if="activeSectionTab === 'revisions'" class="space-y-2">
            <div
              v-for="rev in selectedPublication.revisions || []"
              :key="rev.id"
              class="p-3 bg-white border border-slate-200 rounded flex items-center justify-between text-xs"
            >
              <div>
                <div class="font-mono font-bold text-nexo-text-primary">
                  Version {{ rev.version }}
                </div>
                <div class="text-nexo-text-secondary mt-0.5">
                  {{ rev.change_summary || 'Initial grounded synthesis.' }}
                </div>
              </div>
              <div class="text-right text-[11px] font-mono text-nexo-text-muted">
                <div>{{ rev.editor?.name || 'Chief Analyst' }}</div>
                <div>{{ new Date(rev.created_at).toLocaleString() }}</div>
              </div>
            </div>
          </div>
        </div>
      </Modal>

      <!-- MODAL 3: New Template Modal -->
      <Modal :is-open="isTemplateModalOpen" title="Create Vector PDF Layout Template" @close="isTemplateModalOpen = false">
        <form @submit.prevent="handleCreateTemplate" class="space-y-4 text-left">
          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Template Name</label>
            <Input v-model="templateForm.name" placeholder="e.g. Board of Directors Landscape Dossier" required />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Page Geometry</label>
              <select
                v-model="templateForm.page_size"
                class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
              >
                <option value="a4">A4 (Standard Global)</option>
                <option value="letter">US Letter</option>
                <option value="legal">US Legal</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Orientation</label>
              <select
                v-model="templateForm.orientation"
                class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
              >
                <option value="portrait">Portrait</option>
                <option value="landscape">Landscape</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Confidentiality Header Stamp</label>
            <Input v-model="templateForm.confidentiality_label" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Primary Color</label>
              <Input v-model="templateForm.primary_color" placeholder="#0d1527" />
            </div>
            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Accent Gold Color</label>
              <Input v-model="templateForm.accent_color" placeholder="#d4af37" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Disclosure & Attribution Text</label>
            <textarea
              v-model="templateForm.disclosure_text"
              rows="2"
              class="w-full text-xs border border-nexo-border rounded p-2.5 text-nexo-text-primary focus:outline-hidden"
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
            <Button variant="outline" size="sm" type="button" @click="isTemplateModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">Save Template</Button>
          </div>
        </form>
      </Modal>

      <!-- MODAL 4: New Cadence Schedule Modal -->
      <Modal :is-open="isScheduleModalOpen" title="Schedule Automated Publication Synthesis" @close="isScheduleModalOpen = false">
        <form @submit.prevent="handleCreateSchedule" class="space-y-4 text-left">
          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Publication Format</label>
            <select
              v-model="scheduleForm.publication_type"
              class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
            >
              <option v-for="t in publicationTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-nexo-text-primary mb-1">Title Pattern (Tokens: {date})</label>
            <Input v-model="scheduleForm.title_pattern" required />
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Cadence</label>
              <select
                v-model="scheduleForm.frequency"
                class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
              >
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Time (UTC)</label>
              <Input v-model="scheduleForm.schedule_time" placeholder="06:00" required />
            </div>

            <div>
              <label class="block text-xs font-medium text-nexo-text-primary mb-1">Timezone</label>
              <Input v-model="scheduleForm.timezone" />
            </div>
          </div>

          <div class="flex items-center gap-2 pt-1">
            <input type="checkbox" id="require_review" v-model="scheduleForm.require_review" class="rounded border-slate-300" />
            <label for="require_review" class="text-xs text-nexo-text-secondary">
              Require editorial review before public broadcast (recommended)
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
            <Button variant="outline" size="sm" type="button" @click="isScheduleModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">Create Schedule</Button>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
