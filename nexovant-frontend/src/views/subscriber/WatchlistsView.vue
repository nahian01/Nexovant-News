<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
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

// Select Watchlist and load timeline & graph
const selectWatchlist = async (wl: WatchlistRecord) => {
  selectedWatchlist.value = wl
  errorMessage.value = ''
  try {
    const res = await workspaceService.getWatchlist(wl.id)
    if (res.success) {
      selectedWatchlist.value = res.watchlist
      timeline.value = res.timeline || []
      connectedEntities.value = res.connected_entities || []
    }
  } catch (err: any) {
    console.error('Failed to load watchlist details', err)
  }
}

// Create Watchlist
const handleCreateWatchlist = async () => {
  if (!createForm.value.name.trim()) return
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const res = await workspaceService.createWatchlist(createForm.value)
    if (res.success) {
      statusMessage.value = 'Watchlist created successfully.'
      isCreateModalOpen.value = false
      createForm.value = { name: '', description: '', notification_frequency: 'daily', is_default: false }
      await loadWatchlists()
      if (res.watchlist) {
        await selectWatchlist(res.watchlist)
      }
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
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-[11px] font-mono text-[#274888] uppercase">
            <span>Personal Workspace</span>
            <span>/</span>
            <span>Entity Watchlists & Graph</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Custom Intelligence Watchlists
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875]">
            Curate monitored companies, technologies, executives, and topics. Track historical event timelines and graph relationships.
          </p>
        </div>

        <button
          type="button"
          class="btn-ink text-xs px-4 py-2 flex items-center gap-1.5"
          @click="isCreateModalOpen = true"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>New Watchlist</span>
        </button>
      </div>

      <!-- Feedback Alerts -->
      <div v-if="statusMessage" class="p-3 bg-[#E3F1EA] border border-[#17794D]/30 text-[#17794D] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✓ {{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="text-[#17794D] font-bold ml-2">✕</button>
      </div>

      <div v-if="errorMessage" class="p-3 bg-[#FBE4E1] border border-[#B3261E]/30 text-[#B3261E] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✕ {{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="text-[#B3261E] font-bold ml-2">✕</button>
      </div>

      <!-- Main Grid Layout (Roster on left, details on right) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left Column: Watchlists Roster -->
        <div class="lg:col-span-4 space-y-3">
          <div class="flex items-center justify-between text-xs font-mono text-[#5F6875] uppercase px-1">
            <span>Your Watchlists ({{ watchlists.length }})</span>
          </div>

          <div v-if="isLoading" class="text-xs text-[#5F6875] py-6 text-center">
            Loading watchlists...
          </div>

          <div v-else-if="watchlists.length === 0" class="card-editorial p-6 text-center text-xs space-y-2">
            <p class="text-[#5F6875]">No watchlists created yet.</p>
            <button
              type="button"
              class="px-3.5 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
              @click="isCreateModalOpen = true"
            >
              Create First Watchlist
            </button>
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="wl in watchlists"
              :key="wl.id"
              @click="selectWatchlist(wl)"
              class="card-editorial p-4 cursor-pointer transition-colors"
              :class="selectedWatchlist?.id === wl.id ? 'border-[#0E1A2B] shadow-xs' : 'hover:border-[#274888]/40'"
            >
              <div class="flex items-start justify-between">
                <h3 class="editorial-headline font-medium text-[#0E1A2B] text-base flex items-center gap-1.5">
                  {{ wl.name }}
                </h3>
                <span v-if="wl.is_default" class="text-[9px] font-mono px-1.5 py-0.5 rounded-[2px] bg-[#F6E7C8] text-[#8A5A08] font-bold">
                  Default
                </span>
              </div>
              <p v-if="wl.description" class="text-xs text-[#5F6875] mt-1 line-clamp-1">
                {{ wl.description }}
              </p>
              <div class="mt-3 pt-2.5 border-t border-[#D0D9CF]/60 flex items-center justify-between text-[11px] font-mono text-[#5F6875]">
                <span>{{ wl.items_count || wl.items?.length || 0 }} Entities</span>
                <span class="capitalize">{{ wl.notification_frequency }} alerts</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Selected Watchlist Workspace -->
        <div v-if="selectedWatchlist" class="lg:col-span-8 space-y-6">
          <!-- Watchlist Header Card -->
          <div class="card-editorial p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="editorial-headline text-xl sm:text-2xl font-medium text-[#0E1A2B]">{{ selectedWatchlist.name }}</h2>
                  <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono bg-[#E4E9F7] text-[#274888] uppercase font-bold">
                    {{ selectedWatchlist.notification_frequency }} cadence
                  </span>
                </div>
                <p v-if="selectedWatchlist.description" class="text-xs text-[#5F6875] mt-1">
                  {{ selectedWatchlist.description }}
                </p>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="btn-ink text-xs px-3.5 py-1.5 flex items-center gap-1"
                  @click="isAddItemModalOpen = true"
                >
                  <Plus class="w-3.5 h-3.5" />
                  <span>Add Entity</span>
                </button>
                <button
                  type="button"
                  class="p-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#FBE4E1] hover:border-[#B3261E]/40 text-[#5F6875] hover:text-[#B3261E] transition-colors"
                  @click="handleDeleteWatchlist(selectedWatchlist.id)"
                  title="Delete watchlist"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Monitored Entities Grid -->
            <div class="pt-2 border-t border-[#D0D9CF]/60">
              <h4 class="text-[11px] font-mono uppercase text-[#5F6875] mb-2">Monitored Entities in Watchlist</h4>
              <div v-if="!selectedWatchlist.items || selectedWatchlist.items.length === 0" class="text-xs text-[#5F6875] py-4 italic">
                No entities added yet. Click "Add Entity" to monitor companies, executives, or technologies.
              </div>
              <div v-else class="flex flex-wrap gap-2">
                <div
                  v-for="item in selectedWatchlist.items"
                  :key="item.id"
                  class="inline-flex items-center gap-2 px-3 py-1.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] text-xs font-medium text-[#0E1A2B]"
                >
                  <component :is="item.entity_type === 'technology' ? Cpu : Building2" class="w-3.5 h-3.5 text-[#274888]" />
                  <span>{{ item.entity_name }}</span>
                  <span class="text-[10px] font-mono uppercase text-[#5F6875]">({{ item.entity_type }})</span>
                  <button
                    @click="handleRemoveItem(item.id)"
                    class="text-[#5F6875] hover:text-[#B3261E] font-bold ml-1 text-xs"
                    title="Remove entity"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Connected Relationship Graph Discovery -->
          <div v-if="connectedEntities.length > 0" class="card-editorial p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-[#D0D9CF]/60 pb-2.5">
              <h3 class="editorial-headline text-base font-medium text-[#0E1A2B] flex items-center gap-1.5">
                <Network class="w-4 h-4 text-[#17794D]" />
                <span>Connected Ecosystem Entities (Graph Discovery)</span>
              </h3>
              <span class="text-[11px] font-mono text-[#5F6875]">Verified multi-hop graph</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
              <div
                v-for="ce in connectedEntities"
                :key="ce.id"
                class="p-2.5 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] text-xs space-y-1"
              >
                <div class="font-semibold text-[#0E1A2B] truncate">{{ ce.name }}</div>
                <div class="text-[10px] font-mono text-[#17794D] flex items-center justify-between">
                  <span>{{ ce.relationship.replace('_', ' ') }}</span>
                  <span>{{ Math.round(ce.confidence_score * 100) }}% match</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Historical Milestone Timeline -->
          <div class="card-editorial p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-[#D0D9CF]/60 pb-2.5">
              <h3 class="editorial-headline text-base font-medium text-[#0E1A2B] flex items-center gap-1.5">
                <Clock class="w-4 h-4 text-[#8A5A08]" />
                <span>Monitored Historical Events Timeline</span>
              </h3>
              <span class="text-[11px] font-mono text-[#5F6875]">Chronological Sequencing</span>
            </div>

            <div v-if="timeline.length === 0" class="text-xs text-[#5F6875] py-4 italic">
              No recent historical events logged for these entities.
            </div>

            <div v-else class="space-y-4 border-l border-[#D0D9CF] pl-4 ml-2">
              <div v-for="ev in timeline" :key="ev.id" class="relative text-xs space-y-1">
                <span class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-[#274888] ring-4 ring-white"></span>
                <div class="flex items-center gap-2">
                  <span class="font-mono text-[10px] text-[#5F6875]">
                    {{ new Date(ev.occurred_at).toLocaleDateString() }}
                  </span>
                  <span class="font-mono text-[10px] uppercase text-[#17794D] bg-[#E3F1EA] px-1 rounded-[2px]">
                    {{ ev.event_category?.replace('_', ' ') }}
                  </span>
                </div>
                <div class="font-semibold text-[#0E1A2B] text-sm">{{ ev.title }}</div>
                <p v-if="ev.summary" class="text-[#3F4A5A] text-xs leading-relaxed">
                  {{ ev.summary }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: Create Watchlist -->
    <Modal :is-open="isCreateModalOpen" title="Create New Intelligence Watchlist" @close="isCreateModalOpen = false">
      <form @submit.prevent="handleCreateWatchlist" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Watchlist Name</label>
          <input
            v-model="createForm.name"
            placeholder="e.g. Semiconductor Foundry Leaders"
            required
            class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Description / Strategic Scope</label>
          <textarea
            v-model="createForm.description"
            rows="2"
            class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
            placeholder="Focusing on sub-2nm nodes, wafer fabs, and packaging joint ventures..."
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Notification Cadence</label>
          <select
            v-model="createForm.notification_frequency"
            class="w-full text-xs bg-white border border-[#D0D9CF] rounded-[4px] p-2.5 text-[#0E1A2B] focus:outline-none"
          >
            <option value="real_time">Real-Time (Immediate Alert)</option>
            <option value="daily">Daily Morning Digest</option>
            <option value="weekly">Weekly Macro Summary</option>
            <option value="disabled">Silent Monitoring (No Alerts)</option>
          </select>
        </div>

        <div class="flex items-center gap-2 pt-1">
          <input type="checkbox" id="is_default" v-model="createForm.is_default" class="rounded-[2px] border-[#D0D9CF]" />
          <label for="is_default" class="text-xs text-[#5F6875]">
            Set as default workspace watchlist
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-[#D0D9CF]">
          <button
            type="button"
            class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
            @click="isCreateModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-ink text-xs px-4 py-1.5"
            :disabled="isSubmitting"
          >
            Create Watchlist
          </button>
        </div>
      </form>
    </Modal>

    <!-- MODAL 2: Add Entity Item -->
    <Modal :is-open="isAddItemModalOpen" title="Add Monitored Entity to Watchlist" @close="isAddItemModalOpen = false">
      <form @submit.prevent="handleAddItem" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Entity Category</label>
          <select
            v-model="itemForm.entity_type"
            class="w-full text-xs bg-white border border-[#D0D9CF] rounded-[4px] p-2.5 text-[#0E1A2B] focus:outline-none"
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
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Entity Name / Identifier</label>
          <input
            v-model="itemForm.entity_name"
            placeholder="e.g. Taiwan Semiconductor Manufacturing Co."
            required
            class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
          />
          <p class="text-[10px] text-[#5F6875] mt-1">
            Aliases and common tickers are automatically resolved against our canonical entity knowledge graph.
          </p>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-[#D0D9CF]">
          <button
            type="button"
            class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
            @click="isAddItemModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-ink text-xs px-4 py-1.5"
            :disabled="isSubmitting"
          >
            Add to Watchlist
          </button>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>
