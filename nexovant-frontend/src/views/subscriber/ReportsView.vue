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
  ExternalLink
} from 'lucide-vue-next'

const publications = ref<PublicPublication[]>([])
const isLoading = ref(true)
const isDownloadingId = ref<string | null>(null)
const statusMessage = ref('')
const errorMessage = ref('')

const searchQuery = ref('')
const selectedType = ref('all')

// Custom On-Demand Report Modal
const isGenerateModalOpen = ref(false)
const isSubmitting = ref(false)
const generateForm = ref({
  publication_type: 'custom_deep_research',
  title: '',
  subtitle: '',
  reporting_period: '',
})

const filteredPublications = computed(() => {
  return publications.value.filter(p => {
    const matchesSearch = !searchQuery.value ||
      p.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      p.edition_number.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesType = selectedType.value === 'all' || p.publication_type === selectedType.value
    return matchesSearch && matchesType
  })
})

const fetchPublications = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await publicationService.getPublications()
    if (res.success) {
      publications.value = res.data
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Unable to retrieve executive publications.'
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

onMounted(() => {
  fetchPublications()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
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
              <span>Vector PDF Publications</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
              Executive Publications & Dossier Library
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Grounded executive briefings and dossiers synthesized directly from verified corporate disclosures and RAG knowledge graphs.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="fetchPublications">
              Refresh Feed
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

        <!-- Publications Grid -->
        <div v-if="isLoading" class="text-center py-16 text-xs text-nexo-text-muted">
          Loading grounded publication dossiers...
        </div>

        <div v-else-if="filteredPublications.length === 0" class="text-center py-16 bg-white rounded-lg border border-nexo-border/80">
          <FileText class="w-10 h-10 text-slate-300 mx-auto mb-2" />
          <h3 class="font-serif font-bold text-nexo-text-primary text-sm">No Published Editions Available</h3>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Executive publications are generated on daily 06:00 UTC schedules.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <Card
            v-for="pub in filteredPublications"
            :key="pub.id"
            class="p-5 bg-white border border-nexo-border/80 hover:border-amber-300 transition-all flex flex-col justify-between shadow-xs"
          >
            <div class="space-y-3">
              <!-- Header Badges -->
              <div class="flex items-center justify-between text-[11px]">
                <span class="font-mono text-amber-800 font-bold bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">
                  {{ pub.edition_number }}
                </span>
                <span
                  class="font-mono uppercase text-[10px] px-2 py-0.5 rounded font-bold"
                  :class="{
                    'bg-slate-100 text-slate-700': pub.visibility === 'public',
                    'bg-amber-50 text-amber-800 border border-amber-200': pub.visibility === 'premium',
                    'bg-purple-50 text-purple-800 border border-purple-200': pub.visibility === 'enterprise',
                  }"
                >
                  {{ pub.visibility }}
                </span>
              </div>

              <!-- Title & Subtitle -->
              <div>
                <h3 class="font-serif font-bold text-nexo-text-primary text-base leading-snug">
                  {{ pub.title }}
                </h3>
                <p v-if="pub.subtitle" class="text-xs text-nexo-text-secondary mt-1">
                  {{ pub.subtitle }}
                </p>
              </div>

              <!-- Metadata Metrics -->
              <div class="pt-2 border-t border-slate-100 grid grid-cols-2 gap-2 text-xs">
                <div>
                  <span class="text-[10px] font-mono text-nexo-text-muted uppercase block">Evidence Score</span>
                  <span class="font-mono font-bold text-emerald-700">{{ pub.evidence_completeness_score }}% Corroborated</span>
                </div>
                <div>
                  <span class="text-[10px] font-mono text-nexo-text-muted uppercase block">Document Length</span>
                  <span class="font-mono text-nexo-text-secondary">{{ pub.page_count }} Pages (PDF)</span>
                </div>
              </div>
            </div>

            <!-- Footer Action -->
            <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[11px] font-mono text-nexo-text-muted">
                {{ pub.published_at ? new Date(pub.published_at).toLocaleDateString() : 'Active Edition' }}
              </span>

              <Button
                variant="primary"
                size="sm"
                :loading="isDownloadingId === pub.id"
                @click="handleDownload(pub)"
              >
                <Download class="w-3.5 h-3.5 mr-1" /> Download PDF
              </Button>
            </div>
          </Card>
        </div>
      </main>
    </div>
  </div>
</template>
