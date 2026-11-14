<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import { Settings, Save, Shield, Cpu, RefreshCw } from 'lucide-vue-next'

const settings = ref<any>({})
const isLoading = ref(true)
const isSaving = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

const fetchSettings = async () => {
  isLoading.value = true
  try {
    settings.value = await adminService.getSystemSettings()
  } catch (err: any) {
    errorMessage.value = 'Failed to load system settings.'
  } finally {
    isLoading.value = false
  }
}

const handleSave = async () => {
  isSaving.value = true
  statusMessage.value = ''
  errorMessage.value = ''
  try {
    const res = await adminService.updateSystemSettings(settings.value)
    statusMessage.value = res.message || 'System configuration persisted successfully.'
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to persist configuration.'
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left max-w-5xl">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            System & Algorithmic Parameters Configuration
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Global threshold calibration across cosine clustering, 10-dimension credibility gating, sequential VPS concurrency, and security policies.
          </p>
        </div>

        <Button
          variant="primary"
          size="sm"
          :disabled="isSaving"
          @click="handleSave"
          class="bg-nexo-accent-gold hover:bg-[#8e6e18]"
        >
          <span v-if="isSaving" class="flex items-center gap-1.5">
            <RefreshCw class="h-3.5 w-3.5 animate-spin" /> Persisting...
          </span>
          <span v-else class="flex items-center gap-1.5">
            <Save class="h-3.5 w-3.5" /> Save Configuration
          </span>
        </Button>
      </div>

      <!-- Alerts -->
      <Alert v-if="statusMessage" variant="success" dismissible @dismiss="statusMessage = ''">
        {{ statusMessage }}
      </Alert>
      <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
        {{ errorMessage }}
      </Alert>

      <!-- Settings Cards -->
      <div class="space-y-5 text-xs">
        <!-- Story Clustering & Evaluation -->
        <Card class="p-5 space-y-4 bg-white border border-nexo-border">
          <h2 class="font-serif font-semibold text-nexo-text-primary text-base border-b border-nexo-border/60 pb-2">
            Story Clustering & 10-Dimension Credibility Thresholds
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Story Consolidation Similarity Threshold (0.0 - 1.0)
              </label>
              <input
                v-model="settings.story_consolidation_similarity_threshold"
                type="number"
                step="0.05"
                min="0"
                max="1"
                class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"
              />
              <p class="text-[10px] text-nexo-text-muted mt-1">Cosine vector similarity required to group dispatches into one event cluster.</p>
            </div>

            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Minimum Verification Credibility Bar (0.0 - 1.0)
              </label>
              <input
                v-model="settings.verification_minimum_credibility"
                type="number"
                step="0.05"
                min="0"
                max="1"
                class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"
              />
              <p class="text-[10px] text-nexo-text-muted mt-1">Scores below this threshold automatically mark dispatches as Developing or Disputed.</p>
            </div>
          </div>
        </Card>

        <!-- VPS Safe Sequential Limits -->
        <Card class="p-5 space-y-4 bg-white border border-nexo-border">
          <h2 class="font-serif font-semibold text-nexo-text-primary text-base border-b border-nexo-border/60 pb-2">
            Hardware Resource Protection (Hostinger 1 vCPU / 4 GB Profile)
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                RSS / Regulatory Polling Interval (Minutes)
              </label>
              <input
                v-model="settings.rss_polling_interval_minutes"
                type="number"
                min="5"
                max="180"
                class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"
              />
              <p class="text-[10px] text-nexo-text-muted mt-1">Scheduled frequency for background ingestion collectors.</p>
            </div>

            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Max Daily PDF Generation Concurrency
              </label>
              <input
                v-model="settings.max_daily_pdf_concurrency"
                type="number"
                min="1"
                max="5"
                class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"
              />
              <p class="text-[10px] text-nexo-text-muted mt-1">Keeps peak RAM consumption below 1.2 GB during ReportLab compilation.</p>
            </div>
          </div>
        </Card>

        <!-- Operational Security & Rate Limits -->
        <Card class="p-5 space-y-4 bg-white border border-nexo-border">
          <h2 class="font-serif font-semibold text-nexo-text-primary text-base border-b border-nexo-border/60 pb-2">
            Security & Authentication Policies
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Max Failed Login Attempts Before Lockout
              </label>
              <input
                v-model="settings.max_failed_logins_before_lockout"
                type="number"
                min="3"
                max="20"
                class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"
              />
            </div>

            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Session Inactivity Timeout (Minutes)
              </label>
              <input
                v-model="settings.session_timeout_minutes"
                type="number"
                min="15"
                max="1440"
                class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"
              />
            </div>

            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">
                Enforce 2FA for Staff Accounts
              </label>
              <select
                v-model="settings.enforce_admin_2fa"
                class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"
              >
                <option :value="true">Enabled (TOTP Required)</option>
                <option :value="false">Disabled (Optional)</option>
              </select>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </AdminLayout>
</template>
