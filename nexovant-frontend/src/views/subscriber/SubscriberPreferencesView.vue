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
  Clock,
  ExternalLink,
  Copy,
  Check
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

// Channel Integrations
const telegramChatId = ref('')
const isTelegramModalOpen = ref(false)
const telegramConnecting = ref(false)
const telegramTesting = ref(false)
const telegramInputChatId = ref('')
const telegramTokenData = ref<{ token: string; bot_username: string; deep_link: string } | null>(null)
const isTelegramLoadingToken = ref(false)
const telegramCopied = ref(false)

const whatsappPhone = ref('')
const isWhatsAppModalOpen = ref(false)
const whatsappConnecting = ref(false)
const whatsappTesting = ref(false)
const whatsappInputPhone = ref('')
const whatsappStatusData = ref<any>(null)

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
      telegramChatId.value = resNotif.preferences.telegram_chat_id || ''
      whatsappPhone.value = resNotif.preferences.whatsapp_phone || ''
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

// Telegram Channel Actions
const openTelegramModal = async () => {
  isTelegramModalOpen.value = true
  telegramInputChatId.value = telegramChatId.value
  isTelegramLoadingToken.value = true
  try {
    const res = await workspaceService.getTelegramLinkToken()
    if (res.success) {
      telegramTokenData.value = res
    }
  } catch {
    //
  } finally {
    isTelegramLoadingToken.value = false
  }
}

const copyTelegramCommand = async () => {
  if (!telegramTokenData.value?.token) return
  try {
    await navigator.clipboard.writeText(`/start ${telegramTokenData.value.token}`)
    telegramCopied.value = true
    setTimeout(() => { telegramCopied.value = false }, 2500)
  } catch {
    //
  }
}

const handleConnectTelegram = async () => {
  if (!telegramInputChatId.value.trim()) return
  telegramConnecting.value = true
  errorMsg.value = ''
  try {
    const res = await workspaceService.connectTelegram(telegramInputChatId.value.trim())
    if (res.success) {
      telegramChatId.value = res.chat_id
      channels.value.telegram = true
      isTelegramModalOpen.value = false
      successMsg.value = `Telegram bot alerts successfully connected to @${res.chat_id}!`
      setTimeout(() => { successMsg.value = '' }, 4000)
    }
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to link Telegram account.'
  } finally {
    telegramConnecting.value = false
  }
}

const handleDisconnectTelegram = async () => {
  if (!confirm('Disconnect Telegram alert notifications?')) return
  try {
    const res = await workspaceService.disconnectTelegram()
    if (res.success) {
      telegramChatId.value = ''
      channels.value.telegram = false
      successMsg.value = 'Telegram channel unlinked.'
      setTimeout(() => { successMsg.value = '' }, 4000)
    }
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to disconnect Telegram.'
  }
}

const handleTestTelegram = async () => {
  telegramTesting.value = true
  errorMsg.value = ''
  try {
    const res = await workspaceService.testTelegram()
    if (res.success) {
      successMsg.value = 'Test alert successfully delivered to your Telegram chat!'
    } else {
      successMsg.value = res.details?.error || 'Test notification dispatched to Telegram.'
    }
    setTimeout(() => { successMsg.value = '' }, 4000)
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to dispatch Telegram test alert.'
  } finally {
    telegramTesting.value = false
  }
}

// WhatsApp Channel Actions
const openWhatsAppModal = async () => {
  isWhatsAppModalOpen.value = true
  whatsappInputPhone.value = whatsappPhone.value
  try {
    const res = await workspaceService.getWhatsAppStatus()
    if (res.success) {
      whatsappStatusData.value = res.data
    }
  } catch {
    //
  }
}

const handleConnectWhatsApp = async () => {
  if (!whatsappInputPhone.value.trim()) return
  whatsappConnecting.value = true
  errorMsg.value = ''
  try {
    const res = await workspaceService.connectWhatsApp(whatsappInputPhone.value.trim())
    if (res.success) {
      whatsappPhone.value = res.phone_number
      channels.value.whatsapp = true
      isWhatsAppModalOpen.value = false
      successMsg.value = `WhatsApp alerts connected for ${res.phone_number}!`
      setTimeout(() => { successMsg.value = '' }, 4000)
    }
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to connect WhatsApp phone number.'
  } finally {
    whatsappConnecting.value = false
  }
}

const handleDisconnectWhatsApp = async () => {
  if (!confirm('Disconnect WhatsApp alert notifications?')) return
  try {
    const res = await workspaceService.disconnectWhatsApp()
    if (res.success) {
      whatsappPhone.value = ''
      channels.value.whatsapp = false
      successMsg.value = 'WhatsApp channel disconnected.'
      setTimeout(() => { successMsg.value = '' }, 4000)
    }
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to disconnect WhatsApp.'
  }
}

const handleTestWhatsApp = async () => {
  whatsappTesting.value = true
  errorMsg.value = ''
  try {
    const res = await workspaceService.testWhatsApp()
    if (res.success) {
      successMsg.value = res.message || 'Test alert successfully sent to WhatsApp!'
    } else {
      successMsg.value = res.message || 'WhatsApp test alert dispatched (channel evaluation status reported).'
    }
    setTimeout(() => { successMsg.value = '' }, 5000)
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Failed to dispatch WhatsApp test alert.'
  } finally {
    whatsappTesting.value = false
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

                  <!-- Telegram Bot Dispatch Card -->
                  <div class="rounded-xl border border-nexo-border bg-nexo-bg/60 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700">
                          <MessageSquare class="h-4 w-4" />
                        </div>
                        <div>
                          <div class="text-xs font-semibold text-nexo-text-primary">Telegram Bot Dispatch</div>
                          <div class="flex items-center gap-1.5 mt-0.5">
                            <span v-if="telegramChatId" class="inline-flex items-center gap-1 text-[10px] text-emerald-700 font-medium">
                              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                              Connected: @{{ telegramChatId }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-[10px] text-nexo-text-muted">
                              <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                              Not connected
                            </span>
                          </div>
                        </div>
                      </div>
                      <input
                        type="checkbox"
                        v-model="channels.telegram"
                        :disabled="!telegramChatId"
                        class="h-4 w-4 rounded text-nexo-accent-cyan focus:ring-nexo-accent-cyan disabled:opacity-40"
                        title="Toggle alert delivery to Telegram"
                      />
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-nexo-border/60">
                      <button
                        v-if="!telegramChatId"
                        type="button"
                        @click="openTelegramModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 transition-colors shadow-xs"
                      >
                        <Plus class="w-3.5 h-3.5" /> Connect Telegram
                      </button>
                      <template v-else>
                        <button
                          type="button"
                          @click="openTelegramModal"
                          class="px-2.5 py-1 rounded-lg border border-nexo-border bg-white text-xs text-nexo-text-primary hover:bg-nexo-bg font-medium"
                        >
                          Configure
                        </button>
                        <button
                          type="button"
                          @click="handleTestTelegram"
                          :disabled="telegramTesting"
                          class="px-2.5 py-1 rounded-lg border border-indigo-200 bg-indigo-50/50 text-xs text-indigo-700 hover:bg-indigo-100 font-medium"
                        >
                          {{ telegramTesting ? 'Sending...' : 'Test Alert' }}
                        </button>
                        <button
                          type="button"
                          @click="handleDisconnectTelegram"
                          class="px-2.5 py-1 rounded-lg text-xs text-rose-600 hover:bg-rose-50 font-medium"
                        >
                          Disconnect
                        </button>
                      </template>
                    </div>
                  </div>

                  <!-- WhatsApp Urgent Alerts Card -->
                  <div class="rounded-xl border border-nexo-border bg-nexo-bg/60 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                          <Smartphone class="h-4 w-4" />
                        </div>
                        <div>
                          <div class="text-xs font-semibold text-nexo-text-primary">WhatsApp Urgent Alerts</div>
                          <div class="flex items-center gap-1.5 mt-0.5">
                            <span v-if="whatsappPhone" class="inline-flex items-center gap-1 text-[10px] text-emerald-700 font-medium">
                              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                              Connected: {{ whatsappPhone }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 text-[10px] text-nexo-text-muted">
                              <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                              Not connected
                            </span>
                          </div>
                        </div>
                      </div>
                      <input
                        type="checkbox"
                        v-model="channels.whatsapp"
                        :disabled="!whatsappPhone"
                        class="h-4 w-4 rounded text-nexo-accent-cyan focus:ring-nexo-accent-cyan disabled:opacity-40"
                        title="Toggle alert delivery to WhatsApp"
                      />
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-nexo-border/60">
                      <button
                        v-if="!whatsappPhone"
                        type="button"
                        @click="openWhatsAppModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700 transition-colors shadow-xs"
                      >
                        <Plus class="w-3.5 h-3.5" /> Connect WhatsApp
                      </button>
                      <template v-else>
                        <button
                          type="button"
                          @click="openWhatsAppModal"
                          class="px-2.5 py-1 rounded-lg border border-nexo-border bg-white text-xs text-nexo-text-primary hover:bg-nexo-bg font-medium"
                        >
                          Change Number
                        </button>
                        <button
                          type="button"
                          @click="handleTestWhatsApp"
                          :disabled="whatsappTesting"
                          class="px-2.5 py-1 rounded-lg border border-emerald-200 bg-emerald-50/50 text-xs text-emerald-700 hover:bg-emerald-100 font-medium"
                        >
                          {{ whatsappTesting ? 'Sending...' : 'Test Alert' }}
                        </button>
                        <button
                          type="button"
                          @click="handleDisconnectWhatsApp"
                          class="px-2.5 py-1 rounded-lg text-xs text-rose-600 hover:bg-rose-50 font-medium"
                        >
                          Disconnect
                        </button>
                      </template>
                    </div>
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

    <!-- Telegram Connection Modal -->
    <Modal :is-open="isTelegramModalOpen" title="Connect Telegram Intelligence Alerts" @close="isTelegramModalOpen = false">
      <div class="space-y-5 text-left text-xs p-1">
        <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl text-indigo-900 space-y-1">
          <p class="font-bold flex items-center gap-1.5">
            <MessageSquare class="w-4 h-4 text-indigo-700" /> Nexovant Telegram Intelligence Bot
          </p>
          <p class="text-[11px] text-indigo-700 leading-relaxed">
            Receive verified breaking technology alerts and early warnings directly in your private Telegram chat or executive channel.
          </p>
        </div>

        <!-- Option 1: Deep Link with Linking Token -->
        <div class="space-y-3 p-3.5 border border-nexo-border rounded-xl bg-nexo-bg/40">
          <div class="flex items-center justify-between">
            <span class="font-semibold text-nexo-text-primary text-xs">Method 1: Instant Bot Deep Link (Recommended)</span>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">1-Click</span>
          </div>

          <div v-if="isTelegramLoadingToken" class="py-4 text-center text-nexo-text-muted">
            Generating secure cryptographically signed linking token...
          </div>
          <div v-else-if="telegramTokenData" class="space-y-2.5">
            <p class="text-[11px] text-nexo-text-secondary">
              Open the bot and click <strong>Start</strong>, or copy the verification command:
            </p>
            <div class="flex items-center gap-2">
              <input
                type="text"
                readonly
                :value="`/start ${telegramTokenData.token}`"
                class="flex-1 font-mono text-xs p-2 bg-white border border-nexo-border rounded-lg text-nexo-text-primary"
              />
              <button
                type="button"
                @click="copyTelegramCommand"
                class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-nexo-border bg-white hover:bg-nexo-bg text-nexo-text-primary text-xs font-medium cursor-pointer"
              >
                <Check v-if="telegramCopied" class="w-3.5 h-3.5 text-emerald-600" />
                <Copy v-else class="w-3.5 h-3.5" />
                <span>{{ telegramCopied ? 'Copied' : 'Copy' }}</span>
              </button>
            </div>
            <a
              :href="telegramTokenData.deep_link"
              target="_blank"
              class="inline-flex items-center justify-center gap-2 w-full p-2.5 bg-indigo-600 text-white font-semibold rounded-lg text-xs hover:bg-indigo-700 transition-colors shadow-xs"
            >
              <span>Open Telegram & Connect @{{ telegramTokenData.bot_username }}</span>
              <ExternalLink class="w-3.5 h-3.5" />
            </a>
          </div>
        </div>

        <!-- Option 2: Direct Chat ID or Username -->
        <div class="space-y-3 p-3.5 border border-nexo-border rounded-xl bg-nexo-bg/40">
          <div class="flex items-center justify-between">
            <span class="font-semibold text-nexo-text-primary text-xs">Method 2: Direct Chat ID or Username</span>
            <span class="text-[10px] text-nexo-text-muted">Manual</span>
          </div>
          <p class="text-[11px] text-nexo-text-secondary">
            Enter your Telegram Chat ID (numeric) or personal Telegram @username:
          </p>
          <div class="flex gap-2">
            <Input
              v-model="telegramInputChatId"
              placeholder="e.g. 123456789 or @executive_intel"
              class="flex-1"
              @keydown.enter.prevent="handleConnectTelegram"
            />
            <Button
              variant="primary"
              size="sm"
              :disabled="telegramConnecting || !telegramInputChatId.trim()"
              @click="handleConnectTelegram"
            >
              {{ telegramConnecting ? 'Connecting...' : 'Save & Link' }}
            </Button>
          </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-nexo-border">
          <Button variant="secondary" size="sm" @click="isTelegramModalOpen = false">
            Close
          </Button>
        </div>
      </div>
    </Modal>

    <!-- WhatsApp Connection Modal -->
    <Modal :is-open="isWhatsAppModalOpen" title="Connect WhatsApp Urgent Intelligence" @close="isWhatsAppModalOpen = false">
      <div class="space-y-5 text-left text-xs p-1">
        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 space-y-1">
          <div class="flex items-center justify-between">
            <p class="font-bold flex items-center gap-1.5">
              <Smartphone class="w-4 h-4 text-emerald-700" /> Meta WhatsApp Business Cloud API
            </p>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-200/70 text-emerald-900 font-bold">Official Channel</span>
          </div>
          <p class="text-[11px] text-emerald-700 leading-relaxed">
            Direct, encrypted delivery of time-critical breaking stories, sovereign policy shifts, and morning intelligence digests.
          </p>
        </div>

        <div class="space-y-3 p-3.5 border border-nexo-border rounded-xl bg-nexo-bg/40">
          <label class="block font-semibold text-nexo-text-primary text-xs">
            Verified Mobile Phone Number (E.164 International Format)
          </label>
          <p class="text-[11px] text-nexo-text-secondary">
            Enter your mobile number with the country code (e.g. +1 for United States, +44 for United Kingdom, +49 for Germany, +65 for Singapore, +81 for Japan):
          </p>
          <div class="space-y-2">
            <Input
              v-model="whatsappInputPhone"
              placeholder="+14155552671"
              class="w-full font-mono text-sm"
              @keydown.enter.prevent="handleConnectWhatsApp"
            />
            <span class="text-[10px] text-nexo-text-muted block">
              Format: <code>+[CountryCode][AreaCode][Number]</code> with no spaces or hyphens.
            </span>
          </div>

          <div class="pt-2 flex items-center justify-between">
            <span class="text-[11px] text-nexo-text-muted">
              {{ whatsappPhone ? `Currently configured: ${whatsappPhone}` : 'No phone linked yet' }}
            </span>
            <Button
              variant="primary"
              size="sm"
              :disabled="whatsappConnecting || !whatsappInputPhone.trim()"
              @click="handleConnectWhatsApp"
              class="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {{ whatsappConnecting ? 'Connecting...' : (whatsappPhone ? 'Update Number' : 'Connect & Enable') }}
            </Button>
          </div>
        </div>

        <div v-if="whatsappPhone" class="p-3 bg-white border border-nexo-border rounded-xl space-y-2">
          <div class="flex items-center justify-between">
            <span class="font-semibold text-xs text-nexo-text-primary">Dispatch Channel Verification</span>
            <span class="text-[10px] text-emerald-600 font-medium">Ready</span>
          </div>
          <p class="text-[11px] text-nexo-text-secondary">
            Send an instant test alert to verify delivery to <strong>{{ whatsappPhone }}</strong>:
          </p>
          <Button
            variant="secondary"
            size="sm"
            :disabled="whatsappTesting"
            @click="handleTestWhatsApp"
            class="w-full border-emerald-200 text-emerald-800 hover:bg-emerald-50"
          >
            {{ whatsappTesting ? 'Dispatching Test Message...' : 'Send Test Alert via WhatsApp' }}
          </Button>
        </div>

        <div class="flex justify-end pt-2 border-t border-nexo-border">
          <Button variant="secondary" size="sm" @click="isWhatsAppModalOpen = false">
            Close
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
