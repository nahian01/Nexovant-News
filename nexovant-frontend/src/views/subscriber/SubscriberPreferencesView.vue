<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { workspaceService } from '@/services/api/workspace.service'
import {
  Sliders,
  Bell,
  History,
  Sparkles,
  ShieldAlert,
  CheckCircle2,
  Trash2,
  RotateCcw,
  Plus,
  X,
  Mail,
  Smartphone,
  MessageSquare,
  Globe2,
  Building2,
  Cpu,
  Layers,
  Clock
} from 'lucide-vue-next'

const loading = ref(true)
const saving = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

// Preferences state
const industries = ref<string[]>([])
const topics = ref<string[]>([])
const companies = ref<string[]>([])
const executives = ref<string[]>([])
const personalizationEnabled = ref(true)

// Tag inputs
const newIndustry = ref('')
const newTopic = ref('')
const newCompany = ref('')
const newExecutive = ref('')

// Notification preferences state
const channels = ref({
  in_app: true,
  email: true,
  telegram: false,
  whatsapp: false,
})
const digestFrequency = ref('daily')
const quietHoursStart = ref('22:00')
const quietHoursEnd = ref('07:00')

// Reading history stats
const readingHistoryCount = ref(0)
const clearingHistory = ref(false)
const resettingSignals = ref(false)

const loadAllPreferences = async () => {
  loading.value = true
  errorMsg.value = ''
  try {
    // 1. Domain preferences
    const resPref = await workspaceService.getPreferences()
    if (resPref.preferences) {
      industries.value = resPref.preferences.preferred_industries || ['Technology', 'Semiconductors']
      topics.value = resPref.preferences.preferred_topics || ['Foundry & Fabs', 'Lithography', 'AI Compute']
      companies.value = resPref.preferences.preferred_companies || ['ASML', 'TSMC', 'NVIDIA']
      executives.value = resPref.preferences.preferred_executives || []
      personalizationEnabled.value = resPref.preferences.personalization_enabled !== false
    }

    // 2. Notification preferences
    const resNotif = await workspaceService.getNotificationPreferences()
    if (resNotif.preferences) {
      channels.value = {
        in_app: resNotif.preferences.channels?.in_app !== false,
        email: resNotif.preferences.channels?.email !== false,
        telegram: !!resNotif.preferences.channels?.telegram,
        whatsapp: !!resNotif.preferences.channels?.whatsapp,
      }
      digestFrequency.value = resNotif.preferences.digest_frequency || 'daily'
      quietHoursStart.value = resNotif.preferences.quiet_hours_start || '22:00'
      quietHoursEnd.value = resNotif.preferences.quiet_hours_end || '07:00'
    }

    // 3. Reading history count
    const resHist = await workspaceService.getReadingHistory(1)
    readingHistoryCount.value = resHist.history?.total || (resHist.history?.data ? resHist.history.data.length : 0)
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to load preferences.'
  } finally {
    loading.value = false
  }
}

const addTag = (list: 'industries' | 'topics' | 'companies' | 'executives') => {
  if (list === 'industries' && newIndustry.value.trim()) {
    if (!industries.value.includes(newIndustry.value.trim())) {
      industries.value.push(newIndustry.value.trim())
    }
    newIndustry.value = ''
  } else if (list === 'topics' && newTopic.value.trim()) {
    if (!topics.value.includes(newTopic.value.trim())) {
      topics.value.push(newTopic.value.trim())
    }
    newTopic.value = ''
  } else if (list === 'companies' && newCompany.value.trim()) {
    if (!companies.value.includes(newCompany.value.trim())) {
      companies.value.push(newCompany.value.trim())
    }
    newCompany.value = ''
  } else if (list === 'executives' && newExecutive.value.trim()) {
    if (!executives.value.includes(newExecutive.value.trim())) {
      executives.value.push(newExecutive.value.trim())
    }
    newExecutive.value = ''
  }
}

const removeTag = (list: 'industries' | 'topics' | 'companies' | 'executives', index: number) => {
  if (list === 'industries') industries.value.splice(index, 1)
  else if (list === 'topics') topics.value.splice(index, 1)
  else if (list === 'companies') companies.value.splice(index, 1)
  else if (list === 'executives') executives.value.splice(index, 1)
}

const savePreferences = async () => {
  saving.value = true
  successMsg.value = ''
  errorMsg.value = ''
  try {
    // Save domain preferences
    await workspaceService.updatePreferences({
      industries: industries.value,
      topics: topics.value,
      companies: companies.value,
      executives: executives.value,
      personalization_enabled: personalizationEnabled.value,
      digest_frequency: digestFrequency.value,
    })

    // Save notification preferences
    await workspaceService.updateNotificationPreferences({
      channels: channels.value,
      digest_frequency: digestFrequency.value,
      quiet_hours_start: quietHoursStart.value,
      quiet_hours_end: quietHoursEnd.value,
    })

    successMsg.value = 'Your intelligence preferences and delivery channels have been successfully updated.'
    setTimeout(() => {
      successMsg.value = ''
    }, 4000)
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to update preferences.'
  } finally {
    saving.value = false
  }
}

const handleClearHistory = async () => {
  if (!confirm('Are you sure you want to clear your reading and search history? This action cannot be undone.')) {
    return
  }
  clearingHistory.value = true
  errorMsg.value = ''
  try {
    const res = await workspaceService.clearReadingHistory()
    readingHistoryCount.value = 0
    successMsg.value = res.message || 'Reading history cleared.'
    setTimeout(() => {
      successMsg.value = ''
    }, 4000)
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to clear reading history.'
  } finally {
    clearingHistory.value = false
  }
}

const handleResetSignals = async () => {
  if (!confirm('Reset all implicit personalization signals? Your "For You" feed will revert to your explicitly configured industries and watchlists.')) {
    return
  }
  resettingSignals.value = true
  errorMsg.value = ''
  try {
    const res = await workspaceService.resetPersonalizationSignals()
    successMsg.value = res.message || 'Personalization signals have been reset.'
    setTimeout(() => {
      successMsg.value = ''
    }, 4000)
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to reset personalization signals.'
  } finally {
    resettingSignals.value = false
  }
}

onMounted(() => {
  loadAllPreferences()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-left lg:flex">
    <Sidebar />

    <div class="min-w-0 flex-1">
      <AppHeader />

      <main class="mx-auto w-full max-w-[1400px] space-y-7 px-4 py-6 sm:px-6 lg:px-9 lg:py-8">
        <!-- Title Banner -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center border-b border-nexo-border pb-6">
          <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-nexo-border bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-nexo-accent-cyan">
              <Sliders class="h-3.5 w-3.5" /> Workspace Settings
            </div>
            <h1 class="mt-2.5 font-serif text-3xl font-medium tracking-tight text-nexo-text-primary sm:text-4xl">
              Preferences & Signals
            </h1>
            <p class="mt-1 text-sm text-nexo-text-secondary">
              Configure your domain interests, alert delivery channels, quiet hours, and control algorithmic personalization signals.
            </p>
          </div>

          <div class="flex items-center gap-3">
            <Button variant="primary" :disabled="saving" @click="savePreferences">
              <Sparkles class="mr-2 h-4 w-4" />
              {{ saving ? 'Saving Changes...' : 'Save Preferences' }}
            </Button>
          </div>
        </div>

        <!-- Alert messages -->
        <div v-if="successMsg" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-medium text-emerald-800 flex items-center gap-2">
          <CheckCircle2 class="h-4 w-4 text-emerald-600 shrink-0" />
          <span>{{ successMsg }}</span>
        </div>
        <div v-if="errorMsg" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-medium text-rose-800 flex items-center gap-2">
          <ShieldAlert class="h-4 w-4 text-rose-600 shrink-0" />
          <span>{{ errorMsg }}</span>
        </div>

        <div v-if="loading" class="py-16 text-center text-sm text-nexo-text-muted">
          Loading your subscriber intelligence settings...
        </div>

        <div v-else class="grid gap-7 lg:grid-cols-3">
          <!-- Left 2 Cols: Domain Interests & Notifications -->
          <div class="space-y-7 lg:col-span-2">
            <!-- Domain Interests Card -->
            <Card padding="lg">
              <div class="flex items-center gap-2.5 pb-4 border-b border-nexo-border">
                <Globe2 class="h-5 w-5 text-nexo-accent-cyan" />
                <div>
                  <h2 class="font-serif text-xl font-medium text-nexo-text-primary">Domain Interests & Coverage</h2>
                  <p class="text-xs text-nexo-text-secondary">Configure the core industries, technologies, and companies prioritized in your feed.</p>
                </div>
              </div>

              <div class="mt-6 space-y-6">
                <!-- Preferred Industries -->
                <div>
                  <label class="block text-xs font-semibold text-nexo-text-primary mb-2 flex items-center gap-1.5">
                    <Layers class="h-3.5 w-3.5 text-nexo-accent-cyan" /> Monitored Industries
                  </label>
                  <div class="flex flex-wrap gap-2 mb-3">
                    <span
                      v-for="(ind, idx) in industries"
                      :key="ind"
                      class="inline-flex items-center gap-1.5 rounded-lg border border-nexo-border bg-nexo-bg px-3 py-1 text-xs font-medium text-nexo-text-primary"
                    >
                      {{ ind }}
                      <button type="button" @click="removeTag('industries', idx)" class="text-nexo-text-muted hover:text-rose-600">
                        <X class="h-3 w-3" />
                      </button>
                    </span>
                  </div>
                  <div class="flex gap-2">
                    <Input v-model="newIndustry" placeholder="e.g. Semiconductors, Clean Energy, Fintech" class="flex-1" @keydown.enter.prevent="addTag('industries')" />
                    <Button variant="secondary" size="sm" @click="addTag('industries')">
                      <Plus class="h-3.5 w-3.5 mr-1" /> Add
                    </Button>
                  </div>
                </div>

                <!-- Preferred Topics -->
                <div>
                  <label class="block text-xs font-semibold text-nexo-text-primary mb-2 flex items-center gap-1.5">
                    <Cpu class="h-3.5 w-3.5 text-nexo-accent-cyan" /> Key Technology Topics & Themes
                  </label>
                  <div class="flex flex-wrap gap-2 mb-3">
                    <span
                      v-for="(top, idx) in topics"
                      :key="top"
                      class="inline-flex items-center gap-1.5 rounded-lg border border-nexo-border bg-nexo-bg px-3 py-1 text-xs font-medium text-nexo-text-primary"
                    >
                      {{ top }}
                      <button type="button" @click="removeTag('topics', idx)" class="text-nexo-text-muted hover:text-rose-600">
                        <X class="h-3 w-3" />
                      </button>
                    </span>
                  </div>
                  <div class="flex gap-2">
                    <Input v-model="newTopic" placeholder="e.g. Lithography, High Bandwidth Memory, LLM Inference" class="flex-1" @keydown.enter.prevent="addTag('topics')" />
                    <Button variant="secondary" size="sm" @click="addTag('topics')">
                      <Plus class="h-3.5 w-3.5 mr-1" /> Add
                    </Button>
                  </div>
                </div>

                <!-- Followed Companies -->
                <div>
                  <label class="block text-xs font-semibold text-nexo-text-primary mb-2 flex items-center gap-1.5">
                    <Building2 class="h-3.5 w-3.5 text-nexo-accent-cyan" /> Monitored Companies
                  </label>
                  <div class="flex flex-wrap gap-2 mb-3">
                    <span
                      v-for="(comp, idx) in companies"
                      :key="comp"
                      class="inline-flex items-center gap-1.5 rounded-lg border border-nexo-border bg-nexo-bg px-3 py-1 text-xs font-medium text-nexo-text-primary"
                    >
                      {{ comp }}
                      <button type="button" @click="removeTag('companies', idx)" class="text-nexo-text-muted hover:text-rose-600">
                        <X class="h-3 w-3" />
                      </button>
                    </span>
                  </div>
                  <div class="flex gap-2">
                    <Input v-model="newCompany" placeholder="e.g. ASML, TSMC, NVIDIA, Microsoft" class="flex-1" @keydown.enter.prevent="addTag('companies')" />
                    <Button variant="secondary" size="sm" @click="addTag('companies')">
                      <Plus class="h-3.5 w-3.5 mr-1" /> Add
                    </Button>
                  </div>
                </div>
              </div>
            </Card>

            <!-- Alert Delivery Channels Card -->
            <Card padding="lg">
              <div class="flex items-center gap-2.5 pb-4 border-b border-nexo-border">
                <Bell class="h-5 w-5 text-nexo-accent-cyan" />
                <div>
                  <h2 class="font-serif text-xl font-medium text-nexo-text-primary">Alert Channels & Delivery Cadence</h2>
                  <p class="text-xs text-nexo-text-secondary">Choose how and when verified intelligence alerts are dispatched to you.</p>
                </div>
              </div>

              <div class="mt-6 space-y-6">
                <!-- Channels Grid -->
                <div class="grid gap-3 sm:grid-cols-2">
                  <div class="flex items-center justify-between rounded-xl border border-nexo-border bg-nexo-bg/60 p-4">
                    <div class="flex items-center gap-3">
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                        <Bell class="h-4 w-4" />
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-nexo-text-primary">In-App Notifications</div>
                        <div class="text-[10px] text-nexo-text-muted">Real-time alerts inside workspace</div>
                      </div>
                    </div>
                    <input type="checkbox" v-model="channels.in_app" class="h-4 w-4 rounded text-nexo-accent-cyan focus:ring-nexo-accent-cyan" />
                  </div>

                  <div class="flex items-center justify-between rounded-xl border border-nexo-border bg-nexo-bg/60 p-4">
                    <div class="flex items-center gap-3">
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                        <Mail class="h-4 w-4" />
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-nexo-text-primary">Executive Email Digest</div>
                        <div class="text-[10px] text-nexo-text-muted">Direct to your registered inbox</div>
                      </div>
                    </div>
                    <input type="checkbox" v-model="channels.email" class="h-4 w-4 rounded text-nexo-accent-cyan focus:ring-nexo-accent-cyan" />
                  </div>

                  <div class="flex items-center justify-between rounded-xl border border-nexo-border bg-nexo-bg/60 p-4">
                    <div class="flex items-center gap-3">
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700">
                        <MessageSquare class="h-4 w-4" />
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-nexo-text-primary">Telegram Bot Dispatch</div>
                        <div class="text-[10px] text-nexo-text-muted">Requires connected Telegram ID</div>
                      </div>
                    </div>
                    <input type="checkbox" v-model="channels.telegram" class="h-4 w-4 rounded text-nexo-accent-cyan focus:ring-nexo-accent-cyan" />
                  </div>

                  <div class="flex items-center justify-between rounded-xl border border-nexo-border bg-nexo-bg/60 p-4">
                    <div class="flex items-center gap-3">
                      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                        <Smartphone class="h-4 w-4" />
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-nexo-text-primary">WhatsApp Urgent Alerts</div>
                        <div class="text-[10px] text-nexo-text-muted">Requires phone number verification</div>
                      </div>
                    </div>
                    <input type="checkbox" v-model="channels.whatsapp" class="h-4 w-4 rounded text-nexo-accent-cyan focus:ring-nexo-accent-cyan" />
                  </div>
                </div>

                <!-- Delivery Cadence & Quiet Hours -->
                <div class="grid gap-4 sm:grid-cols-2 pt-2 border-t border-nexo-border">
                  <div>
                    <label class="block text-xs font-semibold text-nexo-text-primary mb-1.5">Digest Cadence</label>
                    <select
                      v-model="digestFrequency"
                      class="w-full rounded-xl border border-nexo-border bg-white px-3 py-2 text-xs font-medium text-nexo-text-primary focus:border-nexo-accent-cyan focus:outline-none"
                    >
                      <option value="real_time">Real-Time (Immediate alert dispatch)</option>
                      <option value="daily">Daily Morning Digest (07:00 UTC)</option>
                      <option value="weekly">Weekly Strategic Summary (Monday 08:00 UTC)</option>
                      <option value="disabled">Disabled (Manual workspace browsing only)</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-xs font-semibold text-nexo-text-primary mb-1.5 flex items-center gap-1.5">
                      <Clock class="h-3.5 w-3.5 text-nexo-text-muted" /> Quiet Hours Window (UTC)
                    </label>
                    <div class="flex items-center gap-2">
                      <input
                        type="time"
                        v-model="quietHoursStart"
                        class="rounded-xl border border-nexo-border bg-white px-3 py-1.5 text-xs font-mono text-nexo-text-primary focus:border-nexo-accent-cyan focus:outline-none"
                      />
                      <span class="text-xs text-nexo-text-muted">to</span>
                      <input
                        type="time"
                        v-model="quietHoursEnd"
                        class="rounded-xl border border-nexo-border bg-white px-3 py-1.5 text-xs font-mono text-nexo-text-primary focus:border-nexo-accent-cyan focus:outline-none"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </Card>
          </div>

          <!-- Right Col: Personalization Controls & History -->
          <div class="space-y-7">
            <Card padding="lg">
              <div class="flex items-center gap-2.5 pb-4 border-b border-nexo-border">
                <Sparkles class="h-5 w-5 text-nexo-accent-gold" />
                <div>
                  <h2 class="font-serif text-xl font-medium text-nexo-text-primary">Personalization Model</h2>
                  <p class="text-xs text-nexo-text-secondary">Control algorithmic weighting.</p>
                </div>
              </div>

              <div class="mt-5 space-y-5">
                <div class="flex items-center justify-between rounded-xl border border-nexo-border bg-nexo-bg p-4">
                  <div>
                    <div class="text-xs font-semibold text-nexo-text-primary">Enable AI Feed Tuning</div>
                    <div class="text-[10px] text-nexo-text-secondary">Uses your interaction signals to rank stories</div>
                  </div>
                  <input
                    type="checkbox"
                    v-model="personalizationEnabled"
                    class="h-4 w-4 rounded text-nexo-accent-cyan focus:ring-nexo-accent-cyan"
                  />
                </div>

                <div class="rounded-xl border border-nexo-border bg-white p-4 space-y-2">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-nexo-text-secondary">Logged Reading Signals</span>
                    <span class="font-mono font-semibold text-nexo-text-primary">{{ readingHistoryCount }} events</span>
                  </div>
                  <div class="text-[11px] text-nexo-text-muted leading-relaxed">
                    Signals include viewed articles, saved briefings, and search terms. They are strictly private and never shared.
                  </div>
                </div>

                <!-- Danger Zone / Data Management -->
                <div class="space-y-3 pt-3 border-t border-nexo-border">
                  <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-nexo-text-muted mb-2">Privacy & History Controls</h3>
                  </div>

                  <Button
                    variant="secondary"
                    size="sm"
                    class="w-full justify-start text-xs text-rose-700 hover:bg-rose-50 border-rose-200"
                    :disabled="clearingHistory || readingHistoryCount === 0"
                    @click="handleClearHistory"
                  >
                    <Trash2 class="h-3.5 w-3.5 mr-2 shrink-0 text-rose-600" />
                    {{ clearingHistory ? 'Clearing History...' : 'Clear Reading & Search History' }}
                  </Button>

                  <Button
                    variant="secondary"
                    size="sm"
                    class="w-full justify-start text-xs text-amber-800 hover:bg-amber-50 border-amber-200"
                    :disabled="resettingSignals"
                    @click="handleResetSignals"
                  >
                    <RotateCcw class="h-3.5 w-3.5 mr-2 shrink-0 text-amber-700" />
                    {{ resettingSignals ? 'Resetting Signals...' : 'Reset Personalization Signals' }}
                  </Button>
                </div>
              </div>
            </Card>

            <div class="rounded-2xl border border-nexo-border bg-white p-5 space-y-3">
              <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-nexo-accent-cyan">
                <History class="h-4 w-4" /> Transparent Ranking
              </div>
              <p class="text-xs text-nexo-text-secondary leading-relaxed">
                Every story in your <strong>For You</strong> feed includes a transparent rationale explaining why it was selected (e.g. matching your followed companies, monitor rules, or reading patterns).
              </p>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>
