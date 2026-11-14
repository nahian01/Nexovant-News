<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import { Radio, RefreshCw, Zap, Trash2, Plus, ExternalLink } from 'lucide-vue-next'

const sources = ref<any[]>([])
const isLoading = ref(true)
const isAddModalOpen = ref(false)
const testResult = ref<string | null>(null)
const statusMessage = ref('')
const errorMessage = ref('')
const testingSourceId = ref<string | null>(null)
const scanningSourceId = ref<string | null>(null)

const newSource = ref({
  name: '',
  source_type: 'regulatory',
  url: '',
  feed_url: '',
  credibility_score: 0.95,
  rate_limit_per_minute: 30,
})

const headers = ['Source Name & Endpoint', 'Type', 'Credibility', 'Poll Limit', 'Status', 'Actions']

const fetchSources = async () => {
  isLoading.value = true
  try {
    sources.value = await adminService.getSources()
  } catch (err: any) {
    errorMessage.value = 'Failed to load news sources.'
  } finally {
    isLoading.value = false
  }
}

const handleTest = async (id: string) => {
  testingSourceId.value = id
  testResult.value = null
  try {
    const res = await adminService.testSource(id)
    statusMessage.value = `${res.message} (HTTP Latency: ${res.latency_ms}ms)`
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Connection test failed.'
  } finally {
    testingSourceId.value = null
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
    statusMessage.value = res.message || 'Source status updated.'
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = 'Failed to toggle source status.'
  }
}

const handleDelete = async (id: string) => {
  if (!confirm('Permanently remove this collection source?')) return
  try {
    const res = await adminService.deleteSource(id)
    statusMessage.value = res.message || 'Source deleted.'
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = 'Failed to delete source.'
  }
}

const handleCreate = async () => {
  try {
    const res = await adminService.createSource(newSource.value)
    statusMessage.value = res.message || 'Collection source registered.'
    isAddModalOpen.value = false
    newSource.value = {
      name: '',
      source_type: 'regulatory',
      url: '',
      feed_url: '',
      credibility_score: 0.95,
      rate_limit_per_minute: 30,
    }
    await fetchSources()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create source.'
  }
}

onMounted(() => {
  fetchSources()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            News Collection & Source Registry
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Manage regulatory disclosure APIs, financial wires, RSS/Atom feeds, and collection polling rates with SSRF protection.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button variant="primary" size="sm" @click="isAddModalOpen = true" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">
            <Plus class="h-4 w-4 mr-1" /> Add Collection Source
          </Button>
          <Button variant="secondary" size="sm" @click="fetchSources">
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

      <!-- Sources Table -->
      <Table :headers="headers" :is-empty="sources.length === 0">
        <tr v-for="s in sources" :key="s.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div class="flex items-center gap-1.5">
              <span>{{ s.name }}</span>
              <a :href="s.url" target="_blank" class="text-nexo-text-muted hover:text-nexo-text-primary" title="Visit website">
                <ExternalLink class="h-3 w-3" />
              </a>
            </div>
            <div class="text-[10px] font-mono text-nexo-text-muted mt-0.5 truncate max-w-sm">
              {{ s.feed_url || s.url }}
            </div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-cyan capitalize font-bold">
            {{ s.source_type?.replace('_', ' ') }}
          </td>
          <td class="py-3.5 px-4 font-mono font-bold text-emerald-700">
            {{ Math.round((s.credibility_score || 0.9) * 100) }}%
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-secondary">
            {{ s.rate_limit_per_minute }} req/min
          </td>
          <td class="py-3.5 px-4">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase',
                s.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
              ]"
            >
              {{ s.is_active ? 'Active' : 'Paused' }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <div class="flex items-center space-x-1.5">
              <button
                :disabled="testingSourceId === s.id"
                @click="handleTest(s.id)"
                class="px-2.5 py-1 rounded bg-white hover:bg-nexo-bg border border-nexo-border text-xs text-nexo-text-primary font-bold shadow-xs"
                title="Perform live HTTP latency probe"
              >
                <span v-if="testingSourceId === s.id" class="flex items-center gap-1">
                  <RefreshCw class="h-3 w-3 animate-spin" /> Probing...
                </span>
                <span v-else>Test ⚡</span>
              </button>

              <button
                :disabled="scanningSourceId === s.id"
                @click="handleScan(s.id)"
                class="px-2 py-1 rounded bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold hover:bg-amber-100"
                title="Trigger immediate feed scan"
              >
                <span v-if="scanningSourceId === s.id" class="flex items-center gap-1">
                  <RefreshCw class="h-3 w-3 animate-spin" /> Ingesting...
                </span>
                <span v-else>Scan Now</span>
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

      <!-- Add Source Modal -->
      <Modal :is-open="isAddModalOpen" title="Register New News Collection Source" max-width="md" @close="isAddModalOpen = false">
        <form @submit.prevent="handleCreate" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Source Name</label>
            <input v-model="newSource.name" placeholder="e.g. European Medicines Agency (EMA)" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Source Type</label>
            <select v-model="newSource.source_type" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
              <option value="regulatory">Regulatory Filing Authority</option>
              <option value="wire">Financial News Wire</option>
              <option value="official_company">Official Corporate Newsroom</option>
              <option value="press_release">Press Release Distribution</option>
            </select>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Primary URL</label>
            <input v-model="newSource.url" placeholder="https://ema.europa.eu" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono" />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Feed / Endpoint URL</label>
            <input v-model="newSource.feed_url" placeholder="https://ema.europa.eu/feed/rss" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Credibility (0.0 - 1.0)</label>
              <input v-model="newSource.credibility_score" type="number" step="0.05" min="0" max="1" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Rate Limit (req/min)</label>
              <input v-model="newSource.rate_limit_per_minute" type="number" min="1" max="120" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div class="pt-3 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isAddModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Save Collection Source</Button>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
