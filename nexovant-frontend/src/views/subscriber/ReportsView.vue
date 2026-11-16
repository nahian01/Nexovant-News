<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { publicationService, type PublicPublication } from '@/services/api/publication.service'
import { automationService, type AutomatedReport } from '@/services/api/automation.service'
import {
  FileText,
  Download,
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
    const md = await automationService.exportReport(report.id, 'markdown')
    const blob = new Blob([md], { type: 'text/markdown;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${report.slug}.md`
    a.click()
    URL.revokeObjectURL(url)
    statusMessage.value = `Exported markdown for "${report.title}".`
  } catch (err: any) {
    errorMessage.value = 'Failed to export report markdown.'
  }
}

const handleExportJson = (report: AutomatedReport) => {
  const jsonStr = JSON.stringify(report.structured_content, null, 2)
  const blob = new Blob([jsonStr], { type: 'application/json;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${report.slug}.json`
  a.click()
  URL.revokeObjectURL(url)
  statusMessage.value = `Exported JSON schema for "${report.title}".`
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
  <div class="min-h-screen bg-nexo-bg flex text-left font-sans text-nexo-text-primary">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Header Banner -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Executive Reporting</span>
              <span>/</span>
              <span>Automated Intelligence Syntheses</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
              Intelligence Dossiers & Automated Summaries
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Synthesized 12-section executive summaries with traceable citations, early warnings, and vector publication dossiers.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="fetchAll" :disabled="isLoading">
              Refresh Feed
            </Button>
          </div>
        </div>

        <!-- Tab Selection -->
        <div class="flex border-b border-nexo-border space-x-6 text-sm font-medium">
          <button
            @click="activeTab = 'automated'"
            class="pb-3 border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'automated' ? 'border-amber-600 text-amber-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
          >
            <Sparkles class="w-4 h-4" />
            12-Section Automated Summaries ({{ automatedReports.length }})
          </button>
          <button
            @click="activeTab = 'publications'"
            class="pb-3 border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'publications' ? 'border-cyan-600 text-cyan-600 font-semibold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'"
          >
            <FileText class="w-4 h-4" />
            Vector PDF Publications ({{ publications.length }})
          </button>
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

        <!-- TAB 1: 12-SECTION AUTOMATED SUMMARIES (STAGE 13) -->
        <div v-if="activeTab === 'automated'" class="space-y-6">
          <!-- Generation Panel -->
          <Card padding="lg" class="bg-white border-nexo-border space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-3">
              <div>
                <h3 class="font-serif font-bold text-base text-nexo-text-primary">
                  Synthesize Multi-Section Intelligence Summary
                </h3>
                <p class="text-xs text-nexo-text-secondary">
                  Compiles verified developments, emerging signals, early warnings, trend analyses, and traceable source citations.
                </p>
              </div>

              <div class="flex items-center gap-2">
                <select
                  v-model="reportTypeChoice"
                  class="text-xs p-2 border rounded border-slate-300 bg-white font-medium"
                >
                  <option value="daily_summary">Daily Intelligence Summary</option>
                  <option value="weekly_intelligence">Weekly Executive Brief</option>
                  <option value="ad_hoc_brief">Ad-Hoc Surveillance Dossier</option>
                </select>

                <Button
                  variant="primary"
                  size="sm"
                  @click="handleGenerateAutomatedReport"
                  :disabled="isGeneratingReport"
                >
                  <Sparkles class="w-3.5 h-3.5 mr-1" />
                  {{ isGeneratingReport ? 'Synthesizing 12 Sections...' : 'Generate Summary' }}
                </Button>
              </div>
            </div>
          </Card>

          <!-- Reports List -->
          <div v-if="isLoading" class="text-center py-16 text-xs text-nexo-text-muted">
            Loading automated intelligence syntheses...
          </div>

          <div v-else-if="automatedReports.length === 0" class="text-center py-16 bg-white rounded-lg border border-nexo-border/80">
            <Sparkles class="w-10 h-10 text-slate-300 mx-auto mb-2" />
            <h3 class="font-serif font-bold text-base text-nexo-text-primary">No Automated Summaries Generated</h3>
            <p class="text-xs text-nexo-text-secondary max-w-sm mx-auto mt-1">
              Select a summary cadence above to generate a structured 12-section report with verified citations.
            </p>
          </div>

          <div v-else class="space-y-4">
            <Card
              v-for="rep in automatedReports"
              :key="rep.id"
              padding="lg"
              class="bg-white border-nexo-border space-y-4 hover:shadow-xs transition"
            >
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b pb-3">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-amber-100 text-amber-800">
                    {{ rep.report_type.replace(/_/g, ' ') }}
                  </span>
                  <span class="text-xs font-mono text-slate-500">
                    {{ rep.sections_count || 12 }} Discrete Sections
                  </span>
                </div>
                <div class="text-[11px] font-mono text-slate-500">
                  Synthesized: {{ new Date(rep.created_at).toLocaleString() }}
                </div>
              </div>

              <div>
                <h4 class="font-serif font-bold text-lg text-nexo-text-primary">
                  {{ rep.title }}
                </h4>
                <p class="text-xs text-nexo-text-secondary mt-1 line-clamp-3">
                  {{ rep.executive_summary }}
                </p>
              </div>

              <!-- 12-Section Checklist Pills -->
              <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-1.5 text-[10px] font-mono text-slate-600 bg-slate-50 p-2.5 rounded border">
                <span class="truncate">1. Executive Summary</span>
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
              <div class="flex items-center justify-between pt-2 border-t">
                <Button variant="outline" size="xs" @click="openReportViewer(rep)">
                  <BookOpen class="w-3.5 h-3.5 mr-1" /> Read Structured Report
                </Button>

                <div class="flex items-center gap-2">
                  <Button variant="ghost" size="xs" @click="handleExportMarkdown(rep)">
                    <FileCode class="w-3.5 h-3.5 mr-1" /> Export Markdown (.md)
                  </Button>
                  <Button variant="ghost" size="xs" @click="handleExportJson(rep)">
                    <FileSpreadsheet class="w-3.5 h-3.5 mr-1" /> Export JSON Schema
                  </Button>
                </div>
              </div>
            </Card>
          </div>
        </div>

        <!-- TAB 2: VECTOR PDF PUBLICATIONS -->
        <div v-else class="space-y-4">
          <!-- Filter & Search Bar -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-3 rounded-lg border border-nexo-border/80">
            <div class="w-full sm:w-80 relative">
              <Input v-model="searchQuery" placeholder="Search publication library..." size="sm" />
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
              <select
                v-model="selectedType"
                class="text-xs bg-white border border-nexo-border rounded px-3 py-1.5 text-nexo-text-primary focus:outline-hidden"
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

          <div v-if="filteredPublications.length === 0" class="text-center py-16 bg-white rounded-lg border border-nexo-border/80">
            <FileText class="w-10 h-10 text-slate-300 mx-auto mb-2" />
            <h3 class="font-serif font-bold text-base text-nexo-text-primary">No Publications Match Filter</h3>
            <p class="text-xs text-nexo-text-secondary max-w-sm mx-auto mt-1">
              Refine your keyword search or select all publication types.
            </p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <Card
              v-for="pub in filteredPublications"
              :key="pub.id"
              class="p-5 bg-white border border-nexo-border shadow-xs flex flex-col justify-between"
            >
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-slate-100 text-slate-600">
                    {{ pub.publication_type.replace(/_/g, ' ') }}
                  </span>
                  <span class="text-[10px] font-mono text-slate-400">
                    {{ pub.edition_number }}
                  </span>
                </div>

                <h3 class="font-serif font-bold text-base text-nexo-text-primary">
                  {{ pub.title }}
                </h3>

                <p v-if="pub.subtitle" class="text-xs text-nexo-text-secondary">
                  {{ pub.subtitle }}
                </p>
              </div>

              <div class="pt-4 border-t mt-4 flex items-center justify-between">
                <span class="text-[10px] font-mono text-slate-500">
                  {{ pub.page_count }} pages
                </span>
                <Button
                  size="xs"
                  variant="primary"
                  @click="handleDownload(pub)"
                  :disabled="isDownloadingId === pub.id"
                >
                  <Download class="w-3.5 h-3.5 mr-1" />
                  {{ isDownloadingId === pub.id ? 'Verifying...' : 'Download PDF' }}
                </Button>
              </div>
            </Card>
          </div>
        </div>

        <!-- REPORT VIEW MODAL -->
        <Modal
          :isOpen="isReportViewModalOpen"
          :title="selectedReportForView?.title || 'Intelligence Summary'"
          @close="isReportViewModalOpen = false"
        >
          <div v-if="selectedReportForView" class="space-y-4 max-h-[70vh] overflow-y-auto pr-2 text-xs">
            <div class="p-3 bg-slate-50 rounded border text-slate-700">
              <div class="font-bold text-sm text-slate-900 mb-1">1. Executive Summary</div>
              <p>{{ selectedReportForView.structured_content['1_executive_summary'] || selectedReportForView.executive_summary }}</p>
            </div>

            <div class="p-3 bg-white rounded border text-slate-700 space-y-2">
              <div class="font-bold text-sm text-slate-900">3. Verified Developments</div>
              <ul class="list-disc pl-4 space-y-1">
                <li v-for="(dev, idx) in (selectedReportForView.structured_content['3_important_developments'] || [])" :key="idx">
                  <strong>{{ dev.title }}</strong>: {{ dev.verification_status }} ({{ dev.source }})
                </li>
              </ul>
            </div>

            <div class="p-3 bg-white rounded border text-slate-700 space-y-2">
              <div class="font-bold text-sm text-slate-900">5. Early Warnings Active</div>
              <ul class="list-disc pl-4 space-y-1">
                <li v-for="(ew, idx) in (selectedReportForView.structured_content['5_early_warnings'] || [])" :key="idx">
                  <span class="font-bold uppercase text-amber-700">[{{ ew.level }}]</span> {{ ew.title }}
                </li>
              </ul>
            </div>

            <div class="p-3 bg-white rounded border text-slate-700 space-y-2">
              <div class="font-bold text-sm text-slate-900">12. Traceable Sources & References</div>
              <ul class="list-disc pl-4 space-y-1">
                <li v-for="(src, idx) in (selectedReportForView.structured_content['12_sources_and_references'] || [])" :key="idx">
                  <strong>{{ src.source_name }}</strong>: {{ src.title }} ({{ src.verification_status }})
                </li>
              </ul>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t">
              <Button variant="secondary" size="sm" @click="handleExportMarkdown(selectedReportForView)">
                Export Markdown
              </Button>
              <Button variant="primary" size="sm" @click="isReportViewModalOpen = false">
                Close
              </Button>
            </div>
          </div>
        </Modal>
      </main>
    </div>
  </div>
</template>
