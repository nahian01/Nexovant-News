<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Modal from '@/components/ui/Modal.vue'
import Input from '@/components/ui/Input.vue'
import { adminService } from '@/services/api/admin.service'

const sources = ref<any[]>([])
const isLoading = ref(true)
const isAddModalOpen = ref(false)
const testResult = ref<string | null>(null)

const newSource = ref({
  name: '',
  source_type: 'regulatory',
  url: '',
  feed_url: '',
  credibility_score: 0.95,
  rate_limit_per_minute: 30,
})

const headers = ['Source Name', 'Type', 'Credibility', 'Poll Limit', 'Status', 'Actions']

const fetchSources = async () => {
  isLoading.value = true
  try {
    sources.value = await adminService.getSources()
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleTest = async (id: string) => {
  testResult.value = 'Testing connection...'
  try {
    const res = await adminService.testSource(id)
    testResult.value = res.message
    setTimeout(() => (testResult.value = null), 4000)
  } catch {
    testResult.value = 'Connection test failed.'
  }
}

const handleToggle = async (id: string) => {
  try {
    await adminService.toggleSource(id)
    fetchSources()
  } catch {
    //
  }
}

const handleCreate = async () => {
  try {
    await adminService.createSource(newSource.value)
    isAddModalOpen.value = false
    fetchSources()
  } catch {
    //
  }
}

onMounted(() => {
  fetchSources()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            News Collection & Source Registry
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Manage regulatory disclosure APIs, financial wires, RSS feeds, and collection polling rates.
          </p>
        </div>

        <Button variant="primary" size="sm" @click="isAddModalOpen = true">
          + Add Collection Source
        </Button>
      </div>

      <div
        v-if="testResult"
        class="p-3 bg-nexo-surface border border-nexo-accent-cyan text-nexo-accent-cyan text-xs font-mono rounded"
      >
        {{ testResult }}
      </div>

      <!-- Sources Table -->
      <Table :headers="headers" :is-empty="sources.length === 0">
        <tr v-for="s in sources" :key="s.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div>{{ s.name }}</div>
            <a :href="s.url" target="_blank" class="text-[10px] font-mono text-nexo-text-muted hover:underline">
              {{ s.url }}
            </a>
          </td>
          <td class="py-3.5 px-4 font-mono text-nexo-accent-cyan capitalize">
            {{ s.source_type.replace('_', ' ') }}
          </td>
          <td class="py-3.5 px-4 font-mono font-bold text-nexo-status-confirmed">
            {{ s.credibility_score * 100 }}%
          </td>
          <td class="py-3.5 px-4 font-mono text-nexo-text-secondary">
            {{ s.rate_limit_per_minute }} req/min
          </td>
          <td class="py-3.5 px-4">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase',
                s.is_active ? 'bg-nexo-status-confirmed/20 text-nexo-status-confirmed' : 'bg-nexo-status-disputed/20 text-nexo-status-disputed'
              ]"
            >
              {{ s.is_active ? 'Active' : 'Paused' }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <div class="flex items-center space-x-2">
              <button
                @click="handleTest(s.id)"
                class="px-2.5 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-xs text-nexo-text-primary"
              >
                Test API
              </button>
              <button
                @click="handleToggle(s.id)"
                :class="[
                  'px-2.5 py-1 rounded border text-xs',
                  s.is_active ? 'border-nexo-status-disputed/40 text-nexo-status-disputed hover:bg-nexo-surface' : 'border-nexo-status-confirmed/40 text-nexo-status-confirmed hover:bg-nexo-surface'
                ]"
              >
                {{ s.is_active ? 'Pause' : 'Activate' }}
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
            <Input v-model="newSource.name" placeholder="e.g. European Medicines Agency (EMA)" required />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Source Type</label>
            <select v-model="newSource.source_type" class="w-full bg-nexo-surface border border-nexo-border rounded p-2 text-nexo-text-primary">
              <option value="regulatory">Regulatory Filing Authority</option>
              <option value="wire">Financial News Wire</option>
              <option value="official_company">Official Corporate Newsroom</option>
              <option value="press_release">Press Release Distribution</option>
            </select>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Primary URL</label>
            <Input v-model="newSource.url" placeholder="https://ema.europa.eu" required />
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Feed / Endpoint URL</label>
            <Input v-model="newSource.feed_url" placeholder="https://ema.europa.eu/feed/rss" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Credibility (0.0 - 1.0)</label>
              <Input v-model="newSource.credibility_score" type="number" step="0.05" min="0" max="1" required />
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Rate Limit (req/min)</label>
              <Input v-model="newSource.rate_limit_per_minute" type="number" min="1" max="120" required />
            </div>
          </div>

          <div class="pt-3 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isAddModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit">Save Collection Source</Button>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
