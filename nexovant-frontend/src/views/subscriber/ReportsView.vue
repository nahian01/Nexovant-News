<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import { publicationService, type PublicPublication } from '@/services/api/publication.service'
import { automationService, type AutomatedReport } from '@/services/api/automation.service'
import {
  FileText,
  Download,
  Mail,
  Sparkles,
  Calendar,
  Shield,
  Layers,
  Search,
  CheckCircle2,
  Clock,
  ExternalLink,
  BookOpen,
  FileCode,
  FileSpreadsheet,
  Check,
  ChevronRight
} from 'lucide-vue-next'

const activeTab = ref<'publications' | 'automated'>('automated')

// 1. Publications State
const publications = ref<PublicPublication[]>([])
const isDownloadingId = ref<string | null>(null)
const isEmailingId = ref<string | null>(null)
const searchQuery = ref('')
const selectedType = ref('all')

// 2. Automated Reports State
const automatedReports = ref<AutomatedReport[]>([])
const isGeneratingReport = ref(false)
const selectedReportForView = ref<AutomatedReport | null>(null)
const isReportViewModalOpen = ref(false)
const reportTypeChoice = ref<'daily_summary' | 'weekly_intelligence' | 'ad_hoc_brief'>('daily_summary')

const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')

const filteredPublications = computed(() => {
  return publications.value.filter(p => {
    const matchesSearch = !searchQuery.value ||
      p.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      p.edition_number.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesType = selectedType.value === 'all' || p.publication_type === selectedType.value
    return matchesSearch && matchesType
  })
})

const fetchAll = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const [pubRes, repRes] = await Promise.all([
      publicationService.getPublications(),
      automationService.getReports()
    ])

    if (pubRes?.success) {
      publications.value = pubRes.data || []
    }
    if (repRes?.success) {
      automatedReports.value = repRes.data || []
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Unable to retrieve executive reporting.'
  } finally {
    isLoading.value = false
  }
}

const handleDownload = async (pub: PublicPublication) => {
  isDownloadingId.value = pub.id
  statusMessage.value = ''
  errorMessage.value = ''
  try {
    const res = await publicationService.getDownloadToken(pub.id)
    if (res.success && res.download_url) {
      window.open(res.download_url, '_blank')
      statusMessage.value = `Secure stream initiated for ${pub.edition_number}.`
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Subscription entitlement check failed. Upgrade to access premium dossiers.'
  } finally {
    isDownloadingId.value = null
  }
}

const handleEmailReport = async (pub: PublicPublication) => {
  isEmailingId.value = pub.id
  statusMessage.value = ''
  errorMessage.value = ''
  try {
    const res = await publicationService.emailPublication(pub.id)
    if (res.success) {
      statusMessage.value = res.message || `Executive intelligence dossier dispatched to your email.`
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to dispatch publication via email. Please check your account profile.'
  } finally {
    isEmailingId.value = null
  }
}

const handleGenerateAutomatedReport = async () => {
  isGeneratingReport.value = true
  errorMessage.value = ''
  statusMessage.value = ''
  try {
    const res = await automationService.generateReport({
      report_type: reportTypeChoice.value,
      coverage_start: new Date(Date.now() - 7 * 86400000).toISOString(),
      coverage_end: new Date().toISOString()
    })
    if (res.success) {
      statusMessage.value = `12-section summary generated: "${res.report.title}".`
      await fetchAll()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to generate automated intelligence report.'
  } finally {
    isGeneratingReport.value = false
  }
}

const handleExportMarkdown = async (report: AutomatedReport) => {
  try {
    const res = await automationService.exportReport(report.id, 'markdown')
    if (res.success && res.content) {
      const blob = new Blob([res.content], { type: 'text/markdown' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${report.title.toLowerCase().replace(/[^a-z0-9]/g, '-')}.md`
      a.click()
      URL.revokeObjectURL(url)
    }
  } catch (err: any) {
    errorMessage.value = 'Failed to export markdown report.'
  }
}

const handleExportJson = async (report: AutomatedReport) => {
  try {
    const res = await automationService.exportReport(report.id, 'json')
    if (res.success && res.content) {
      const blob = new Blob([JSON.stringify(res.content, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${report.title.toLowerCase().replace(/[^a-z0-9]/g, '-')}.json`
      a.click()
      URL.revokeObjectURL(url)
    }
  } catch (err: any) {
    errorMessage.value = 'Failed to export JSON schema report.'
  }
}

const openReportViewer = (report: AutomatedReport) => {
  selectedReportForView.value = report
  isReportViewModalOpen.value = true
}

onMounted(() => {
  fetchAll()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Header (per Mockup P11 Research library.png & P24 Reports and briefings.png) -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-[11px] font-mono text-[#8A5A08] uppercase">
            <span>Executive Publications</span>
            <span>/</span>
            <span>PDF Dossiers & 12-Section Syntheses</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Reports & Executive Briefings
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875]">
            Download vector PDF briefings or generate on-demand multi-vector intelligence syntheses with verified source citations.
          </p>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="flex border-b border-[#D0D9CF] space-x-6 text-xs sm:text-sm font-medium">
        <button
          @click="activeTab = 'automated'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'automated' ? 'border-[#0E1A2B] text-[#0E1A2B] font-semibold' : 'border-transparent text-[#5F6875] hover:text-[#0E1A2B]'"
        >
          <Sparkles class="w-4 h-4 text-[#8A5A08]" />
          <span>Automated 12-Section Summaries</span>
          <span class="ml-1 px-1.5 py-0.5 rounded-[2px] text-[10px] bg-[#F6E7C8] text-[#8A5A08] font-mono font-bold">
            {{ automatedReports.length }}
          </span>
        </button>

        <button
          @click="activeTab = 'publications'"
          class="pb-3 border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === 'publications' ? 'border-[#0E1A2B] text-[#0E1A2B] font-semibold' : 'border-transparent text-[#5F6875] hover:text-[#0E1A2B]'"
        >
          <FileText class="w-4 h-4 text-[#274888]" />
          <span>Vector PDF Publications ({{ publications.length }})</span>
        </button>
      </div>

      <!-- Alerts -->
      <div v-if="statusMessage" class="p-3 bg-[#E3F1EA] border border-[#17794D]/30 text-[#17794D] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✓ {{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="text-[#17794D] font-bold ml-2">✕</button>
      </div>

      <div v-if="errorMessage" class="p-3 bg-[#FBE4E1] border border-[#B3261E]/30 text-[#B3261E] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✕ {{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="text-[#B3261E] font-bold ml-2">✕</button>
      </div>

      <!-- TAB 1: 12-SECTION AUTOMATED SUMMARIES (STAGE 13) -->
      <div v-if="activeTab === 'automated'" class="space-y-6">
        <!-- Generation Panel -->
        <div class="card-editorial p-5 sm:p-6 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#D0D9CF]/60 pb-3">
            <div>
              <h3 class="editorial-headline text-lg font-medium text-[#0E1A2B]">
                Synthesize Multi-Section Intelligence Summary
              </h3>
              <p class="text-xs text-[#5F6875]">
                Compiles verified developments, emerging signals, early warnings, trend analyses, and traceable source citations.
              </p>
            </div>

            <div class="flex items-center gap-2">
              <select
                v-model="reportTypeChoice"
                class="text-xs p-2 border border-[#D0D9CF] rounded-[4px] bg-white text-[#0E1A2B] font-medium"
              >
                <option value="daily_summary">Daily Intelligence Summary</option>
                <option value="weekly_intelligence">Weekly Executive Brief</option>
                <option value="ad_hoc_brief">Ad-Hoc Surveillance Dossier</option>
              </select>

              <button
                type="button"
                class="btn-ink text-xs px-4 py-2 flex items-center gap-1.5"
                @click="handleGenerateAutomatedReport"
                :disabled="isGeneratingReport"
              >
                <Sparkles class="w-3.5 h-3.5 text-[#B7791F]" />
                <span>{{ isGeneratingReport ? 'Synthesizing 12 Sections...' : 'Generate Summary' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Reports List -->
        <div v-if="isLoading" class="text-center py-16 text-xs text-[#5F6875]">
          Loading automated intelligence syntheses...
        </div>

        <div v-else-if="automatedReports.length === 0" class="card-editorial p-12 text-center space-y-2">
          <Sparkles class="w-8 h-8 text-[#5F6875] mx-auto opacity-50" />
          <h3 class="font-serif font-medium text-[#0E1A2B] text-base">No Automated Summaries Generated</h3>
          <p class="text-xs text-[#5F6875] max-w-sm mx-auto">
            Select a summary cadence above to generate a structured 12-section report with verified citations.
          </p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="rep in automatedReports"
            :key="rep.id"
            class="card-editorial p-5 sm:p-6 space-y-4 hover:border-[#274888]/40 transition-colors"
          >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#D0D9CF]/60 pb-3">
              <div class="flex items-center gap-2">
                <span class="badge-developing">
                  {{ rep.report_type.replace(/_/g, ' ') }}
                </span>
                <span class="text-xs font-mono text-[#5F6875]">
                  {{ rep.sections_count || 12 }} Discrete Sections
                </span>
              </div>
              <div class="text-[11px] font-mono text-[#5F6875]">
                Synthesized: {{ new Date(rep.created_at).toLocaleString() }}
              </div>
            </div>

            <div>
              <h4 class="editorial-headline text-xl font-medium text-[#0E1A2B]">
                {{ rep.title }}
              </h4>
              <p class="text-xs text-[#3F4A5A] mt-1.5 line-clamp-3 leading-relaxed">
                {{ rep.executive_summary }}
              </p>
            </div>

            <!-- 12-Section Checklist Pills -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-1.5 text-[10px] font-mono text-[#3F4A5A] bg-[#F8FAF7] p-2.5 rounded-[2px] border border-[#D0D9CF]">
              <span class="truncate">1. Exec Summary</span>
              <span class="truncate">2. Period Window</span>
              <span class="truncate">3. Developments</span>
              <span class="truncate">4. Emerging Signals</span>
              <span class="truncate">5. Early Warnings</span>
              <span class="truncate">6. Supporting Proof</span>
              <span class="truncate">7. Contradictions</span>
              <span class="truncate">8. Trend Analysis</span>
              <span class="truncate">9. OLS Forecasts</span>
              <span class="truncate">10. Prior Changes</span>
              <span class="truncate">11. Unresolved</span>
              <span class="truncate">12. Citations</span>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[#D0D9CF]/60">
              <button
                type="button"
                class="px-3 py-1.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] font-semibold flex items-center gap-1.5"
                @click="openReportViewer(rep)"
              >
                <BookOpen class="w-3.5 h-3.5 text-[#274888]" />
                <span>Read Structured Report</span>
              </button>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="px-2.5 py-1 text-xs text-[#5F6875] hover:text-[#0E1A2B] hover:bg-[#EEEBE4] rounded-[4px] flex items-center gap-1"
                  @click="handleExportMarkdown(rep)"
                >
                  <FileCode class="w-3.5 h-3.5" />
                  <span>Export Markdown (.md)</span>
                </button>
                <button
                  type="button"
                  class="px-2.5 py-1 text-xs text-[#5F6875] hover:text-[#0E1A2B] hover:bg-[#EEEBE4] rounded-[4px] flex items-center gap-1"
                  @click="handleExportJson(rep)"
                >
                  <FileSpreadsheet class="w-3.5 h-3.5" />
                  <span>Export JSON Schema</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: VECTOR PDF PUBLICATIONS -->
      <div v-else class="space-y-4">
        <!-- Filter & Search Bar -->
        <div class="card-editorial p-3 flex flex-col sm:flex-row items-center justify-between gap-3">
          <div class="w-full sm:w-80 relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#5F6875]" />
            <input
              v-model="searchQuery"
              placeholder="Search publication library..."
              class="w-full pl-9 pr-3 py-1.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888]"
            />
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto">
            <select
              v-model="selectedType"
              class="text-xs bg-white border border-[#D0D9CF] rounded-[4px] px-3 py-1.5 text-[#0E1A2B] focus:outline-none"
            >
              <option value="all">All Publication Types</option>
              <option value="daily_briefing">Daily Executive Briefings</option>
              <option value="weekly_report">Weekly Macro Reports</option>
              <option value="monthly_outlook">Monthly Strategic Outlooks</option>
              <option value="company_dossier">Company Deep-Dives</option>
              <option value="technology_report">Technology Intelligence</option>
              <option value="custom_deep_research">Deep Research Dossiers</option>
            </select>
          </div>
        </div>

        <div v-if="filteredPublications.length === 0" class="card-editorial p-12 text-center space-y-2">
          <FileText class="w-8 h-8 text-[#5F6875] mx-auto opacity-50" />
          <h3 class="font-serif font-medium text-[#0E1A2B] text-base">No Publications Match Filter</h3>
          <p class="text-xs text-[#5F6875] max-w-sm mx-auto">
            Refine your keyword search or select all publication types.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="pub in filteredPublications"
            :key="pub.id"
            class="card-editorial p-5 flex flex-col justify-between space-y-4 hover:border-[#274888]/40 transition-colors"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono uppercase bg-[#EEEBE4] text-[#3F4A5A] font-medium">
                  {{ pub.publication_type.replace(/_/g, ' ') }}
                </span>
                <span class="text-[10px] font-mono text-[#5F6875]">
                  {{ pub.edition_number }}
                </span>
              </div>

              <h3 class="editorial-headline text-lg font-medium text-[#0E1A2B] leading-snug">
                {{ pub.title }}
              </h3>

              <p v-if="pub.subtitle" class="text-xs text-[#5F6875] line-clamp-2 leading-relaxed">
                {{ pub.subtitle }}
              </p>
            </div>

            <div class="pt-3 border-t border-[#D0D9CF]/60 flex items-center justify-between">
              <span class="text-[10px] font-mono text-[#5F6875]">
                {{ pub.page_count }} pages
              </span>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="text-xs px-2.5 py-1.5 flex items-center gap-1.5 border border-[#D0D9CF] rounded-[2px] bg-white text-[#0E1A2B] hover:bg-[#F0EFEA] transition-colors"
                  @click="handleEmailReport(pub)"
                  :disabled="isEmailingId === pub.id"
                  title="Dispatch executive brief dossier to your verified email"
                >
                  <Mail class="w-3.5 h-3.5 text-[#274888]" />
                  <span>{{ isEmailingId === pub.id ? 'Sending...' : 'Email' }}</span>
                </button>
                <button
                  type="button"
                  class="btn-ink text-xs px-3.5 py-1.5 flex items-center gap-1.5"
                  @click="handleDownload(pub)"
                  :disabled="isDownloadingId === pub.id"
                >
                  <Download class="w-3.5 h-3.5" />
                  <span>{{ isDownloadingId === pub.id ? 'Verifying...' : 'Download PDF' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- REPORT VIEW MODAL -->
      <Modal
        :isOpen="isReportViewModalOpen"
        :title="selectedReportForView?.title || 'Intelligence Summary'"
        @close="isReportViewModalOpen = false"
      >
        <div v-if="selectedReportForView" class="space-y-4 max-h-[70vh] overflow-y-auto pr-2 text-xs text-left">
          <div class="p-3 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] text-[#0E1A2B]">
            <div class="font-bold text-sm text-[#0E1A2B] mb-1">1. Executive Summary</div>
            <p class="leading-relaxed">{{ selectedReportForView.structured_content['1_executive_summary'] || selectedReportForView.executive_summary }}</p>
          </div>

          <div class="p-3 bg-white rounded-[2px] border border-[#D0D9CF] text-[#3F4A5A] space-y-2">
            <div class="font-bold text-sm text-[#0E1A2B]">3. Verified Developments</div>
            <ul class="list-disc pl-4 space-y-1">
              <li v-for="(dev, idx) in (selectedReportForView.structured_content['3_important_developments'] || [])" :key="idx">
                <strong class="text-[#0E1A2B]">{{ dev.title }}</strong>: {{ dev.verification_status }} ({{ dev.source }})
              </li>
            </ul>
          </div>

          <div class="p-3 bg-white rounded-[2px] border border-[#D0D9CF] text-[#3F4A5A] space-y-2">
            <div class="font-bold text-sm text-[#0E1A2B]">5. Early Warnings Active</div>
            <ul class="list-disc pl-4 space-y-1">
              <li v-for="(ew, idx) in (selectedReportForView.structured_content['5_early_warnings'] || [])" :key="idx">
                <span class="font-bold uppercase text-[#8A5A08]">[{{ ew.level }}]</span> {{ ew.title }}
              </li>
            </ul>
          </div>

          <div class="p-3 bg-white rounded-[2px] border border-[#D0D9CF] text-[#3F4A5A] space-y-2">
            <div class="font-bold text-sm text-[#0E1A2B]">12. Traceable Sources & References</div>
            <ul class="list-disc pl-4 space-y-1">
              <li v-for="(src, idx) in (selectedReportForView.structured_content['12_sources_and_references'] || [])" :key="idx">
                <strong class="text-[#0E1A2B]">{{ src.source_name }}</strong>: {{ src.title }} ({{ src.verification_status }})
              </li>
            </ul>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D0D9CF]">
            <button
              type="button"
              class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
              @click="handleExportMarkdown(selectedReportForView)"
            >
              Export Markdown
            </button>
            <button
              type="button"
              class="btn-ink text-xs px-4 py-1.5"
              @click="isReportViewModalOpen = false"
            >
              Close
            </button>
          </div>
        </div>
      </Modal>
    </div>
  </AuthenticatedLayout>
</template>
