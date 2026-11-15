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
  Bookmark,
  Plus,
  Trash2,
  Calendar,
  Network,
  Clock,
  Sparkles,
  ExternalLink,
  ChevronRight,
  ShieldCheck,
  Building2,
  Cpu,
  Layers,
  ArrowRight
} from 'lucide-vue-next'

interface WatchlistRecord {
  id: string
  name: string
  description?: string
  is_default: boolean
  notification_frequency: string
  items_count?: number
  items?: Array<{
    id: string
    entity_name: string
    entity_type: string
  }>
}

interface TimelineEvent {
  id: string
  title: string
  event_category: string
  summary: string
  occurred_at: string
}

interface ConnectedEntity {
  id: string
  name: string
  entity_type: string
  relationship: string
  direction: string
  confidence_score: number
}

const watchlists = ref<WatchlistRecord[]>([])
const selectedWatchlist = ref<WatchlistRecord | null>(null)
const timeline = ref<TimelineEvent[]>([])
const connectedEntities = ref<ConnectedEntity[]>([])

const isLoading = ref(true)
const isSubmitting = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

// Create Watchlist Modal
const isCreateModalOpen = ref(false)
const createForm = ref({
  name: '',
  description: '',
  notification_frequency: 'daily',
  is_default: false,
})

// Add Entity Item Modal
const isAddItemModalOpen = ref(false)
const itemForm = ref({
  entity_name: '',
  entity_type: 'company',
})

// Load All Watchlists
const loadWatchlists = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await workspaceService.getWatchlists()
    if (res.success) {
      watchlists.value = res.watchlists
      if (watchlists.value.length > 0 && !selectedWatchlist.value) {
        await selectWatchlist(watchlists.value[0])
      }
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load intelligence watchlists.'
  } finally {
    isLoading.value = false
  }
}

// Select Watchlist & Load Context
const selectWatchlist = async (wl: WatchlistRecord) => {
  selectedWatchlist.value = wl
  timeline.value = []
  connectedEntities.value = []
  try {
    const full = await workspaceService.getWatchlist(wl.id)
    if (full.success) {
      selectedWatchlist.value = full.watchlist
    }

    const tlRes = await workspaceService.getWatchlistTimeline(wl.id)
    if (tlRes.success) {
      timeline.value = tlRes.timeline
    }

    const conRes = await workspaceService.getWatchlistConnectedEntities(wl.id)
    if (conRes.success) {
      connectedEntities.value = conRes.connected_entities
    }
  } catch (err: any) {
    //
  }
}

// Create Watchlist
const handleCreateWatchlist = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  statusMessage.value = ''
  try {
    const res = await workspaceService.createWatchlist(createForm.value)
    if (res.success) {
      statusMessage.value = `Watchlist "${res.watchlist.name}" created.`
      isCreateModalOpen.value = false
      createForm.value = { name: '', description: '', notification_frequency: 'daily', is_default: false }
      await loadWatchlists()
      await selectWatchlist(res.watchlist)
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.name?.[0] || 'Failed to create watchlist.'
  } finally {
    isSubmitting.value = false
  }
}

// Delete Watchlist
const handleDeleteWatchlist = async (id: string) => {
  if (!confirm('Are you sure you want to delete this watchlist?')) return
  try {
    const res = await workspaceService.deleteWatchlist(id)
    if (res.success) {
      statusMessage.value = 'Watchlist deleted.'
      selectedWatchlist.value = null
      await loadWatchlists()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete watchlist.'
  }
}

// Add Item
const handleAddItem = async () => {
  if (!selectedWatchlist.value) return
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const res = await workspaceService.addWatchlistItem(selectedWatchlist.value.id, itemForm.value)
    if (res.success) {
      statusMessage.value = `Added ${res.item.entity_name} to watchlist.`
      isAddItemModalOpen.value = false
      itemForm.value = { entity_name: '', entity_type: 'company' }
      await selectWatchlist(selectedWatchlist.value)
      await loadWatchlists()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.entity_name?.[0] || 'Failed to add entity.'
  } finally {
    isSubmitting.value = false
  }
}

// Remove Item
const handleRemoveItem = async (itemId: string) => {
  if (!selectedWatchlist.value) return
  try {
    await workspaceService.removeWatchlistItem(selectedWatchlist.value.id, itemId)
    statusMessage.value = 'Entity removed from watchlist.'
    await selectWatchlist(selectedWatchlist.value)
    await loadWatchlists()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to remove entity.'
  }
}

onMounted(() => {
  loadWatchlists()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Personal Workspace</span>
              <span>/</span>
              <span>Entity Watchlists & Graph</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
              Custom Intelligence Watchlists
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Curate monitored companies, technologies, executives, and topics. Track historical event timelines and graph relationships.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="primary" size="sm" @click="isCreateModalOpen = true">
              <Plus class="w-3.5 h-3.5 mr-1" /> New Watchlist
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

        <!-- Main Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          <!-- Left Column: Watchlists Roster -->
          <div class="lg:col-span-4 space-y-3">
            <div class="flex items-center justify-between text-xs font-mono text-nexo-text-muted uppercase px-1">
              <span>Your Watchlists ({{ watchlists.length }})</span>
            </div>

            <div v-if="isLoading" class="text-xs text-nexo-text-muted py-6 text-center">
              Loading watchlists...
            </div>

            <div v-else-if="watchlists.length === 0" class="p-6 bg-white rounded-lg border border-nexo-border text-center text-xs">
              <p class="text-nexo-text-secondary">No watchlists created yet.</p>
              <Button variant="outline" size="sm" class="mt-3" @click="isCreateModalOpen = true">
                Create First Watchlist
              </Button>
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="wl in watchlists"
                :key="wl.id"
                @click="selectWatchlist(wl)"
                class="p-4 rounded-lg border transition-all cursor-pointer bg-white"
                :class="selectedWatchlist?.id === wl.id ? 'border-amber-400 shadow-xs ring-1 ring-amber-300' : 'border-nexo-border hover:border-slate-300'"
              >
                <div class="flex items-start justify-between">
                  <h3 class="font-serif font-bold text-nexo-text-primary text-sm flex items-center gap-1.5">
                    {{ wl.name }}
                  </h3>
                  <span v-if="wl.is_default" class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 font-bold border border-amber-200">
                    Default
                  </span>
                </div>
                <p v-if="wl.description" class="text-xs text-nexo-text-secondary mt-1 line-clamp-1">
                  {{ wl.description }}
                </p>
                <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] font-mono text-nexo-text-muted">
                  <span>{{ wl.items_count || wl.items?.length || 0 }} Entities</span>
                  <span class="capitalize">{{ wl.notification_frequency }} alerts</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Selected Watchlist Workspace -->
          <div v-if="selectedWatchlist" class="lg:col-span-8 space-y-6">
            <!-- Watchlist Header Card -->
            <Card class="p-5 bg-white border border-nexo-border shadow-xs space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                  <div class="flex items-center gap-2">
                    <h2 class="font-serif font-bold text-xl text-nexo-text-primary">{{ selectedWatchlist.name }}</h2>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-blue-50 text-blue-800 uppercase font-bold">
                      {{ selectedWatchlist.notification_frequency }} cadence
                    </span>
                  </div>
                  <p v-if="selectedWatchlist.description" class="text-xs text-nexo-text-secondary mt-1">
                    {{ selectedWatchlist.description }}
                  </p>
                </div>

                <div class="flex items-center gap-2">
                  <Button variant="primary" size="sm" @click="isAddItemModalOpen = true">
                    <Plus class="w-3.5 h-3.5 mr-1" /> Add Entity
                  </Button>
                  <Button variant="outline" size="sm" @click="handleDeleteWatchlist(selectedWatchlist.id)">
                    <Trash2 class="w-3.5 h-3.5 text-rose-500" />
                  </Button>
                </div>
              </div>

              <!-- Monitored Entities Grid -->
              <div>
                <h4 class="text-[11px] font-mono uppercase text-nexo-text-muted mb-2">Monitored Entities in Watchlist</h4>
                <div v-if="!selectedWatchlist.items || selectedWatchlist.items.length === 0" class="text-xs text-nexo-text-muted py-4 italic">
                  No entities added yet. Click "Add Entity" to monitor companies, executives, or technologies.
                </div>
                <div v-else class="flex flex-wrap gap-2">
                  <div
                    v-for="item in selectedWatchlist.items"
                    :key="item.id"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-medium text-nexo-text-primary"
                  >
                    <component :is="item.entity_type === 'technology' ? Cpu : Building2" class="w-3.5 h-3.5 text-slate-500" />
                    <span>{{ item.entity_name }}</span>
                    <span class="text-[10px] font-mono uppercase text-slate-400">({{ item.entity_type }})</span>
                    <button
                      @click="handleRemoveItem(item.id)"
                      class="text-slate-400 hover:text-rose-600 font-bold ml-1 text-xs"
                      title="Remove entity"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </div>
            </Card>

            <!-- Connected Relationship Graph Discovery -->
            <Card v-if="connectedEntities.length > 0" class="p-5 bg-white border border-nexo-border shadow-xs space-y-3">
              <div class="flex items-center justify-between">
                <h3 class="font-serif font-bold text-nexo-text-primary text-sm flex items-center gap-1.5">
                  <Network class="w-4 h-4 text-emerald-700" /> Connected Ecosystem Entities (Graph Discovery)
                </h3>
                <span class="text-[11px] font-mono text-nexo-text-muted">Discovered via verified relationships</span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                <div
                  v-for="ce in connectedEntities"
                  :key="ce.id"
                  class="p-2.5 bg-slate-50/75 rounded border border-slate-200 text-xs space-y-1"
                >
                  <div class="font-bold text-nexo-text-primary truncate">{{ ce.name }}</div>
                  <div class="text-[10px] font-mono text-emerald-800 flex items-center justify-between">
                    <span>{{ ce.relationship.replace('_', ' ') }}</span>
                    <span>{{ Math.round(ce.confidence_score * 100) }}% match</span>
                  </div>
                </div>
              </div>
            </Card>

            <!-- Historical Milestone Timeline -->
            <Card class="p-5 bg-white border border-nexo-border shadow-xs space-y-4">
              <h3 class="font-serif font-bold text-nexo-text-primary text-sm flex items-center gap-1.5">
                <Clock class="w-4 h-4 text-amber-700" /> Monitored Historical Events Timeline
              </h3>

              <div v-if="timeline.length === 0" class="text-xs text-nexo-text-muted py-4 italic">
                No recent historical events logged for these entities.
              </div>

              <div v-else class="space-y-3 border-l-2 border-slate-200 pl-4 ml-2">
                <div v-for="ev in timeline" :key="ev.id" class="relative text-xs space-y-0.5">
                  <span class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-amber-500 ring-4 ring-white"></span>
                  <div class="flex items-center gap-2">
                    <span class="font-mono text-[10px] text-nexo-text-muted">
                      {{ new Date(ev.occurred_at).toLocaleDateString() }}
                    </span>
                    <span class="font-mono text-[10px] uppercase text-emerald-800 bg-emerald-50 px-1 rounded">
                      {{ ev.event_category?.replace('_', ' ') }}
                    </span>
                  </div>
                  <div class="font-bold text-nexo-text-primary">{{ ev.title }}</div>
                  <p v-if="ev.summary" class="text-nexo-text-secondary text-[11px] leading-relaxed">
                    {{ ev.summary }}
                  </p>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </main>
    </div>

    <!-- MODAL 1: Create Watchlist -->
    <Modal :is-open="isCreateModalOpen" title="Create New Intelligence Watchlist" @close="isCreateModalOpen = false">
      <form @submit.prevent="handleCreateWatchlist" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Watchlist Name</label>
          <Input v-model="createForm.name" placeholder="e.g. Semiconductor Foundry Leaders" required />
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Description / Strategic Scope</label>
          <textarea
            v-model="createForm.description"
            rows="2"
            class="w-full text-xs border border-nexo-border rounded p-2.5 text-nexo-text-primary focus:outline-hidden"
            placeholder="Focusing on sub-2nm nodes, wafer fabs, and packaging joint ventures..."
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Notification Cadence</label>
          <select
            v-model="createForm.notification_frequency"
            class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
          >
            <option value="real_time">Real-Time (Immediate Alert)</option>
            <option value="daily">Daily Morning Digest</option>
            <option value="weekly">Weekly Macro Summary</option>
            <option value="disabled">Silent Monitoring (No Alerts)</option>
          </select>
        </div>

        <div class="flex items-center gap-2 pt-1">
          <input type="checkbox" id="is_default" v-model="createForm.is_default" class="rounded border-slate-300" />
          <label for="is_default" class="text-xs text-nexo-text-secondary">
            Set as default workspace watchlist
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="outline" size="sm" type="button" @click="isCreateModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">Create Watchlist</Button>
        </div>
      </form>
    </Modal>

    <!-- MODAL 2: Add Entity Item -->
    <Modal :is-open="isAddItemModalOpen" title="Add Monitored Entity to Watchlist" @close="isAddItemModalOpen = false">
      <form @submit.prevent="handleAddItem" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Entity Category</label>
          <select
            v-model="itemForm.entity_type"
            class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
          >
            <option value="company">Company (e.g. ASML, TSMC)</option>
            <option value="executive">Executive / Leader</option>
            <option value="technology">Technology (e.g. High-NA EUV)</option>
            <option value="product">Product Architecture</option>
            <option value="industry">Industry Sector</option>
            <option value="region">Region / Geographic Hub</option>
            <option value="topic">Topic / Macro Theme</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Entity Name / Identifier</label>
          <Input v-model="itemForm.entity_name" placeholder="e.g. Taiwan Semiconductor Manufacturing Co." required />
          <p class="text-[10px] text-nexo-text-muted mt-1">
            Aliases and common tickers are automatically resolved against our canonical entity knowledge graph.
          </p>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="outline" size="sm" type="button" @click="isAddItemModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">Add to Watchlist</Button>
        </div>
      </form>
    </Modal>
  </div>
</template>
