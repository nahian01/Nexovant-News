<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import {
  Bot,
  Key,
  Cpu,
  Layers,
  BarChart3,
  CheckCircle2,
  AlertTriangle,
  Play,
  Trash2,
  Pause,
  Plus,
  RefreshCw,
} from 'lucide-vue-next'

const activeTab = ref<'providers' | 'credentials' | 'models' | 'tasks' | 'usage'>('credentials')
const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')

// Data
const stats = ref<any>({})
const providers = ref<any[]>([])
const credentials = ref<any[]>([])
const models = ref<any[]>([])
const tasks = ref<any[]>([])
const routingLogs = ref<any[]>([])
const usageData = ref<any>({})

// Modals
const isAddCredentialModalOpen = ref(false)
const isAddModelModalOpen = ref(false)
const isEditTaskModalOpen = ref(false)
const testingCredentialId = ref<string | null>(null)

// Forms
const credentialForm = ref({
  ai_provider_id: '',
  display_name: '',
  api_key: '',
  account_label: '',
  priority: 1,
  rate_limit_rpm: 60,
  rate_limit_tpm: 100000,
  daily_request_budget: 1000,
  monthly_spending_budget_usd: 100.0,
})

const modelForm = ref({
  ai_provider_id: '',
  model_id: '',
  display_name: '',
  context_window: 128000,
  input_price_per_1k_tokens: 0.0015,
  output_price_per_1k_tokens: 0.002,
  is_active: true,
})

const selectedTask = ref<any>(null)
const taskForm = ref({
  preferred_model_id: '',
  fallback_providers: [] as string[],
  temperature: 0.3,
  max_tokens: 2048,
})

const fetchAll = async () => {
  isLoading.value = true
  try {
    const pData = await adminService.getAIProviders()
    providers.value = pData.providers || []
    stats.value = pData.stats || {}
    routingLogs.value = pData.recent_logs || []

    credentials.value = await adminService.getAICredentials()
    models.value = await adminService.getAIModels()
    tasks.value = await adminService.getAITasks()
  } catch (err: any) {
    errorMessage.value = 'Failed to load AI infrastructure data.'
  } finally {
    isLoading.value = false
  }
}

const handleCreateCredential = async () => {
  try {
    const res = await adminService.createAICredential(credentialForm.value)
    statusMessage.value = res.message || 'API credential encrypted and added to routing pool.'
    isAddCredentialModalOpen.value = false
    credentialForm.value = {
      ai_provider_id: '',
      display_name: '',
      api_key: '',
      account_label: '',
      priority: 1,
      rate_limit_rpm: 60,
      rate_limit_tpm: 100000,
      daily_request_budget: 1000,
      monthly_spending_budget_usd: 100.0,
    }
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to save credential.'
  }
}

const handleTestCredential = async (id: string) => {
  testingCredentialId.value = id
  statusMessage.value = ''
  try {
    const res = await adminService.testAICredential(id)
    statusMessage.value = `${res.message} (Latency: ${res.latency_ms}ms)`
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Connection test failed.'
  } finally {
    testingCredentialId.value = null
  }
}

const handleToggleCredential = async (cred: any) => {
  try {
    const newStatus = cred.status === 'active' ? 'suspended' : 'active'
    await adminService.updateAICredential(cred.id, { status: newStatus })
    statusMessage.value = `Credential status updated to ${newStatus}.`
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = 'Failed to update credential status.'
  }
}

const handleDeleteCredential = async (id: string) => {
  if (!confirm('Permanently remove this API credential from the pool?')) return
  try {
    const res = await adminService.deleteAICredential(id)
    statusMessage.value = res.message || 'Credential deleted.'
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = 'Failed to delete credential.'
  }
}

const handleCreateModel = async () => {
  try {
    const res = await adminService.createAIModel(modelForm.value)
    statusMessage.value = res.message || 'Model registered.'
    isAddModelModalOpen.value = false
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to register model.'
  }
}

const openEditTaskModal = (task: any) => {
  selectedTask.value = task
  taskForm.value = {
    preferred_model_id: task.preferred_model_id || '',
    fallback_providers: task.fallback_providers || [],
    temperature: task.temperature || 0.3,
    max_tokens: task.max_tokens || 2048,
  }
  isEditTaskModalOpen.value = true
}

const handleUpdateTask = async () => {
  if (!selectedTask.value) return
  try {
    const res = await adminService.updateAITask(selectedTask.value.id, taskForm.value)
    statusMessage.value = res.message || 'Task configuration updated.'
    isEditTaskModalOpen.value = false
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update task configuration.'
  }
}

const toggleProvider = async (provider: any) => {
  try {
    await adminService.updateAIProvider(provider.id, {
      is_active: !provider.is_active,
    })
    provider.is_active = !provider.is_active
    statusMessage.value = `Updated connection status for ${provider.name}.`
  } catch {
    errorMessage.value = 'Failed to update provider status.'
  }
}

const resetCircuit = async (provider: any) => {
  try {
    await adminService.updateAIProvider(provider.id, {
      circuit_state: 'closed',
    })
    provider.circuit_state = 'closed'
    statusMessage.value = `Circuit breaker reset for ${provider.name}.`
  } catch {
    errorMessage.value = 'Failed to reset circuit breaker.'
  }
}

onMounted(() => {
  fetchAll()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
            Central AI Provider Management & Routing Gateway
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Dynamic load-balancing across 20+ API credentials per provider, circuit breaker automation, 17-task registry, and token cost tracking.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button
            v-if="activeTab === 'credentials'"
            variant="primary"
            size="sm"
            @click="isAddCredentialModalOpen = true"
            class="bg-nexo-accent-gold hover:bg-[#8e6e18]"
          >
            <Plus class="h-4 w-4 mr-1" /> Add API Credential
          </Button>

          <Button
            v-if="activeTab === 'models'"
            variant="primary"
            size="sm"
            @click="isAddModelModalOpen = true"
            class="bg-nexo-accent-gold hover:bg-[#8e6e18]"
          >
            <Plus class="h-4 w-4 mr-1" /> Register Model
          </Button>

          <Button variant="secondary" size="sm" @click="fetchAll">
            Refresh
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

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-mono">
        <Card class="p-3.5 bg-white border border-nexo-border">
          <span class="text-nexo-text-muted uppercase text-[10px] block">Active Providers</span>
          <span class="text-xl font-bold text-nexo-text-primary block mt-1">
            {{ providers.filter(p => p.is_active).length }} / {{ providers.length }}
          </span>
          <span class="text-[10px] text-emerald-700">Online & Routed</span>
        </Card>

        <Card class="p-3.5 bg-white border border-nexo-border">
          <span class="text-nexo-text-muted uppercase text-[10px] block">Pooled API Credentials</span>
          <span class="text-xl font-bold text-nexo-accent-gold block mt-1">
            {{ credentials.length }} Keys
          </span>
          <span class="text-[10px] text-nexo-text-muted">AES-256-GCM Encrypted</span>
        </Card>

        <Card class="p-3.5 bg-white border border-nexo-border">
          <span class="text-nexo-text-muted uppercase text-[10px] block">Cognitive Tasks</span>
          <span class="text-xl font-bold text-nexo-accent-cyan block mt-1">
            {{ tasks.length }} Tasks
          </span>
          <span class="text-[10px] text-emerald-700">Configured & Regulated</span>
        </Card>

        <Card class="p-3.5 bg-white border border-nexo-border">
          <span class="text-nexo-text-muted uppercase text-[10px] block">24h Token Spend</span>
          <span class="text-xl font-bold text-nexo-text-primary block mt-1">
            ${{ Number(stats.total_cost_24h_usd || 0).toFixed(4) }}
          </span>
          <span class="text-[10px] text-nexo-text-muted">{{ stats.total_routing_events_24h || 0 }} Invocations</span>
        </Card>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-nexo-border space-x-6 text-xs font-mono">
        <button
          @click="activeTab = 'credentials'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'credentials' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          API Credentials Pool ({{ credentials.length }})
        </button>
        <button
          @click="activeTab = 'providers'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'providers' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          AI Providers ({{ providers.length }})
        </button>
        <button
          @click="activeTab = 'models'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'models' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Model Registry ({{ models.length }})
        </button>
        <button
          @click="activeTab = 'tasks'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'tasks' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Task Routing Registry ({{ tasks.length }})
        </button>
        <button
          @click="activeTab = 'usage'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'usage' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Routing Logs & Spend
        </button>
      </div>

      <!-- TAB 1: API CREDENTIALS POOL -->
      <div v-if="activeTab === 'credentials'" class="space-y-4">
        <div class="flex items-center justify-between text-xs text-nexo-text-secondary">
          <p>
            Multiple API credentials registered per AI company. Keys are encrypted at rest with AES-256-GCM, hashed for duplicate detection, and masked in all responses.
          </p>
        </div>

        <Table :headers="['Provider & Label', 'Masked Key & Fingerprint', 'Status', 'Priority', 'Limits (RPM / Daily)', 'Monthly Budget', 'Actions']" :is-empty="credentials.length === 0">
          <tr v-for="c in credentials" :key="c.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4">
              <div class="font-bold text-nexo-text-primary">{{ c.display_name }}</div>
              <div class="text-[10px] font-mono text-nexo-accent-cyan uppercase">
                {{ c.provider?.name || 'AI Provider' }}
                <span v-if="c.account_label" class="text-nexo-text-muted font-normal">({{ c.account_label }})</span>
              </div>
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <div class="text-nexo-text-primary font-bold">{{ c.masked_key }}</div>
              <div class="text-[9px] text-nexo-text-muted">fp: {{ c.key_fingerprint?.substring(0, 16) }}...</div>
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <span
                :class="[
                  'px-2 py-0.5 rounded text-[9px] uppercase font-bold',
                  c.status === 'active' ? 'bg-emerald-100 text-emerald-800' :
                  c.status === 'suspended' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800'
                ]"
              >
                {{ c.status }}
              </span>
              <span v-if="c.error_count > 0" class="block text-[9px] text-red-600 mt-0.5">{{ c.error_count }} errors</span>
            </td>
            <td class="py-3 px-4 font-mono text-xs font-bold text-nexo-accent-gold">
              #{{ c.priority }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <div>{{ c.rate_limit_rpm }} RPM</div>
              <div class="text-[10px] text-nexo-text-muted">{{ c.daily_request_budget }} req/day</div>
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              ${{ c.monthly_spending_budget_usd || 100 }}/mo
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <div class="flex items-center space-x-2">
                <Button
                  variant="secondary"
                  size="sm"
                  :disabled="testingCredentialId === c.id"
                  @click="handleTestCredential(c.id)"
                  title="Test API Key Connectivity"
                >
                  <span v-if="testingCredentialId === c.id" class="flex items-center gap-1">
                    <RefreshCw class="h-3 w-3 animate-spin" />
                  </span>
                  <span v-else>Test ⚡</span>
                </Button>

                <button
                  @click="handleToggleCredential(c)"
                  class="px-2 py-1 rounded border text-xs"
                  :class="c.status === 'active' ? 'border-amber-200 text-amber-800 hover:bg-amber-50' : 'border-emerald-200 text-emerald-800 hover:bg-emerald-50'"
                >
                  {{ c.status === 'active' ? 'Suspend' : 'Activate' }}
                </button>

                <button
                  @click="handleDeleteCredential(c.id)"
                  class="p-1 rounded text-red-600 hover:bg-red-50"
                  title="Delete Credential"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 2: AI PROVIDERS -->
      <div v-if="activeTab === 'providers'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <Card
          v-for="prov in providers"
          :key="prov.id"
          class="p-5 border border-nexo-border bg-white space-y-4 hover:border-nexo-accent-gold/40 transition"
        >
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-bold text-base text-nexo-text-primary">{{ prov.name }}</h3>
              <span class="text-[10px] font-mono uppercase text-nexo-text-muted">{{ prov.slug }}</span>
            </div>
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase',
                prov.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'
              ]"
            >
              {{ prov.is_active ? 'Active' : 'Disabled' }}
            </span>
          </div>

          <!-- Circuit Breaker State -->
          <div class="p-2.5 rounded-xl bg-nexo-surface/50 border border-nexo-border/60 text-xs flex items-center justify-between">
            <div>
              <span class="text-[9px] font-mono uppercase text-nexo-text-muted block">Circuit Breaker</span>
              <span
                :class="[
                  'font-mono font-bold uppercase text-[11px]',
                  prov.circuit_state === 'closed' ? 'text-emerald-700' : 'text-red-600'
                ]"
              >
                {{ prov.circuit_state }} ({{ prov.consecutive_failures }} failures)
              </span>
            </div>

            <button
              v-if="prov.circuit_state !== 'closed'"
              @click="resetCircuit(prov)"
              class="text-xs font-mono text-nexo-accent-cyan hover:underline font-bold"
            >
              Reset ↺
            </button>
          </div>

          <!-- Provider details -->
          <div class="text-xs font-mono space-y-1 text-nexo-text-secondary">
            <div class="flex justify-between">
              <span>Default Model:</span>
              <span class="text-nexo-text-primary font-bold">{{ prov.default_model || 'Auto-select' }}</span>
            </div>
            <div class="flex justify-between">
              <span>Routing Strategy:</span>
              <span class="text-nexo-accent-gold font-bold uppercase text-[10px]">{{ prov.routing_strategy || 'priority' }}</span>
            </div>
            <div class="flex justify-between">
              <span>Active Credentials:</span>
              <span class="text-nexo-text-primary font-bold">{{ prov.active_credentials_count ?? prov.credentials?.length ?? 1 }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-nexo-border flex items-center justify-between">
            <Button
              variant="secondary"
              size="sm"
              @click="toggleProvider(prov)"
            >
              {{ prov.is_active ? 'Disable Provider' : 'Enable Provider' }}
            </Button>
          </div>
        </Card>
      </div>

      <!-- TAB 3: MODEL REGISTRY -->
      <div v-if="activeTab === 'models'">
        <Table :headers="['Model ID & Name', 'Provider', 'Context Window', 'Pricing (Input/Output per 1k)', 'Status']" :is-empty="models.length === 0">
          <tr v-for="m in models" :key="m.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4">
              <div class="font-bold text-nexo-text-primary">{{ m.display_name }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted">{{ m.model_id }}</div>
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-accent-cyan font-bold uppercase">
              {{ m.provider?.name || 'Provider' }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              {{ Number(m.context_window).toLocaleString() }} tokens
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <span class="text-nexo-text-primary">${{ m.input_price_per_1k_tokens }} in</span> /
              <span class="text-nexo-accent-gold font-bold">${{ m.output_price_per_1k_tokens }} out</span>
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <span class="px-2 py-0.5 rounded text-[9px] uppercase font-bold bg-emerald-100 text-emerald-800">
                Active
              </span>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 4: TASK CONFIGURATIONS (17 TASKS) -->
      <div v-if="activeTab === 'tasks'">
        <Table :headers="['Cognitive Task', 'Preferred Model', 'Fallback Strategy', 'Temperature', 'Max Tokens', 'Actions']" :is-empty="tasks.length === 0">
          <tr v-for="t in tasks" :key="t.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4">
              <div class="font-bold text-nexo-text-primary">{{ t.task_name }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted">{{ t.task_code }}</div>
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-accent-gold font-bold">
              {{ t.preferred_model?.display_name || t.preferred_model_id || 'System Dynamic' }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-text-secondary">
              {{ t.fallback_providers?.join(', ') || 'Anthropic → OpenAI → Gemini' }}
            </td>
            <td class="py-3 px-4 font-mono text-xs font-bold">
              {{ t.temperature ?? 0.3 }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              {{ t.max_tokens ?? 2048 }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <Button variant="secondary" size="sm" @click="openEditTaskModal(t)">
                Edit Task
              </Button>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 5: USAGE & ROUTING LOGS -->
      <div v-if="activeTab === 'usage'" class="space-y-4">
        <Table :headers="['Task & Status', 'Provider & Model', 'Credential Key', 'Tokens (Prompt/Comp)', 'Cost (USD)', 'Latency', 'Time']" :is-empty="routingLogs.length === 0">
          <tr v-for="log in routingLogs" :key="log.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4 font-mono text-xs">
              <div class="font-bold text-nexo-text-primary">{{ log.task }}</div>
              <span
                :class="[
                  'px-1.5 py-0.2 rounded text-[9px] uppercase font-bold',
                  log.status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                ]"
              >
                {{ log.status }}
              </span>
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <div class="text-nexo-text-primary font-bold">{{ log.provider }}</div>
              <div class="text-[10px] text-nexo-text-muted">{{ log.model }}</div>
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-accent-cyan">
              {{ log.credential_fingerprint ? 'fp:' + log.credential_fingerprint.substring(0, 10) + '...' : 'Default Key' }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              {{ log.prompt_tokens ?? 0 }} / {{ log.completion_tokens ?? 0 }}
            </td>
            <td class="py-3 px-4 font-mono text-xs font-bold text-nexo-accent-gold">
              ${{ Number(log.cost_usd || 0).toFixed(5) }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-text-muted">
              {{ log.latency_ms ?? 0 }}ms
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-text-muted">
              {{ new Date(log.created_at).toLocaleTimeString() }}
            </td>
          </tr>
        </Table>
      </div>

      <!-- Add Credential Modal -->
      <Modal :is-open="isAddCredentialModalOpen" title="Register New AI API Credential (AES-256-GCM Encrypted)" max-width="md" @close="isAddCredentialModalOpen = false">
        <form @submit.prevent="handleCreateCredential" class="space-y-4 text-xs text-left">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">AI Provider Company</label>
              <select v-model="credentialForm.ai_provider_id" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="" disabled>Select Provider...</option>
                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Display Label</label>
              <input v-model="credentialForm.display_name" required placeholder="e.g. Gemini 2.5 Flash Production" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">API Key / Secret Token</label>
            <input v-model="credentialForm.api_key" type="password" required placeholder="Paste full plaintext key (encrypted immediately at rest)..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono" />
            <p class="text-[9px] text-nexo-text-muted mt-1">Key will be hashed for duplicate fingerprinting and encrypted with authenticated AES-256-GCM.</p>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Priority Weight</label>
              <input v-model="credentialForm.priority" type="number" min="1" max="100" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Rate Limit (RPM)</label>
              <input v-model="credentialForm.rate_limit_rpm" type="number" min="1" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Monthly Budget ($)</label>
              <input v-model="credentialForm.monthly_spending_budget_usd" type="number" step="10" min="0" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isAddCredentialModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Encrypt & Save Key</Button>
          </div>
        </form>
      </Modal>

      <!-- Edit Task Modal -->
      <Modal :is-open="isEditTaskModalOpen" title="Configure Cognitive Task Routing" max-width="md" @close="isEditTaskModalOpen = false">
        <form @submit.prevent="handleUpdateTask" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Preferred Model</label>
            <select v-model="taskForm.preferred_model_id" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
              <option value="">System Default / Dynamic Least Cost</option>
              <option v-for="m in models" :key="m.id" :value="m.id">{{ m.display_name }} ({{ m.provider?.name }})</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Temperature (0.0 - 1.0)</label>
              <input v-model="taskForm.temperature" type="number" step="0.05" min="0" max="1" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Max Tokens</label>
              <input v-model="taskForm.max_tokens" type="number" min="256" max="16384" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isEditTaskModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Update Task Config</Button>
          </div>
        </form>
      </Modal>

      <!-- Register Model Modal -->
      <Modal :is-open="isAddModelModalOpen" title="Register New AI Model" max-width="md" @close="isAddModelModalOpen = false">
        <form @submit.prevent="handleCreateModel" class="space-y-4 text-xs text-left">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Provider</label>
              <select v-model="modelForm.ai_provider_id" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="" disabled>Select Provider...</option>
                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Model ID (API String)</label>
              <input v-model="modelForm.model_id" required placeholder="e.g. gemini-2.5-pro" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Display Name</label>
            <input v-model="modelForm.display_name" required placeholder="e.g. Google Gemini 2.5 Pro" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Context Window</label>
              <input v-model="modelForm.context_window" type="number" min="1000" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Input $ / 1k</label>
              <input v-model="modelForm.input_price_per_1k_tokens" type="number" step="0.0001" min="0" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Output $ / 1k</label>
              <input v-model="modelForm.output_price_per_1k_tokens" type="number" step="0.0001" min="0" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isAddModelModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Register Model</Button>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
