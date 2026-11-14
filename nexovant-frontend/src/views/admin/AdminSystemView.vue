<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { adminService } from '@/services/api/admin.service'

const settings = ref<any>({})
const isLoading = ref(true)
const statusMessage = ref('')

const fetchSettings = async () => {
  isLoading.value = true
  try {
    settings.value = await adminService.getSystemSettings()
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleSave = async () => {
  try {
    const res = await adminService.updateSystemSettings(settings.value)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
  } catch {
    //
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left max-w-4xl">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            System & Editorial Algorithmic Configuration
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Configure clustering similarity thresholds, verification credibility bars, and sequential ingestion schedules.
          </p>
        </div>

        <Button variant="primary" size="sm" @click="handleSave">
          Save Configuration
        </Button>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- Settings Cards -->
      <div class="space-y-4 text-xs">
        <Card class="p-5 space-y-4">
          <h2 class="font-serif font-semibold text-nexo-text-primary text-base border-b border-nexo-border/60 pb-2">
            Story Clustering & Editorial Thresholds
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Story Consolidation Similarity Threshold (0.0 - 1.0)
              </label>
              <Input v-model="settings.story_consolidation_similarity_threshold" type="number" step="0.05" min="0" max="1" />
              <p class="text-[10px] text-nexo-text-muted mt-1">Cosine vector similarity required to group dispatches into one event cluster.</p>
            </div>

            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Minimum Verification Credibility Bar (0.0 - 1.0)
              </label>
              <Input v-model="settings.verification_minimum_credibility" type="number" step="0.05" min="0" max="1" />
              <p class="text-[10px] text-nexo-text-muted mt-1">Lower scores classify story as Developing or Disputed.</p>
            </div>
          </div>
        </Card>

        <Card class="p-5 space-y-4">
          <h2 class="font-serif font-semibold text-nexo-text-primary text-base border-b border-nexo-border/60 pb-2">
            Ingestion & Hardware Resource Protection (Hostinger VPS)
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                RSS / Regulatory Polling Interval (Minutes)
              </label>
              <Input v-model="settings.rss_polling_interval_minutes" type="number" min="5" max="120" />
            </div>

            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Max Daily PDF Generation Concurrency
              </label>
              <Input v-model="settings.max_daily_pdf_concurrency" type="number" min="1" max="5" />
              <p class="text-[10px] text-nexo-text-muted mt-1">Keeps peak RAM below 1.2 GB during ReportLab compilation.</p>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </AdminLayout>
</template>
