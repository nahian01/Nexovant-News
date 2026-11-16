<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Modal from '@/components/ui/Modal.vue'
import { searchService, type SavedSearchRecord } from '@/services/api/search.service'
import {
  Bookmark,
  Search,
  Bell,
  Trash2,
  Clock,
  Play,
  Pause,
  ExternalLink,
  SlidersHorizontal,
  CheckCircle2,
} from 'lucide-vue-next'

const router = useRouter()
const isLoading = ref(true)
const savedSearches = ref<SavedSearchRecord[]>([])
const statusMessage = ref('')
const errorMessage = ref('')

// Preview Matches Modal
const showMatchesModal = ref(false)
const selectedSearch = ref<SavedSearchRecord | null>(null)
const matchesList = ref<any[]>([])
const isLoadingMatches = ref(false)

const loadSavedSearches = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    savedSearches.value = await searchService.getSavedSearches()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load saved searches.'
  } finally {
    isLoading.value = false
  }
}

const handleToggle = async (item: SavedSearchRecord) => {
  try {
    const updated = await searchService.toggleSavedSearch(item.id)
    item.is_active = updated.is_active
    statusMessage.value = item.is_active ? `Monitoring resumed for "${item.name}".` : `Monitoring paused for "${item.name}".`
  } catch {
    errorMessage.value = 'Failed to toggle monitor state.'
  }
}

const handleDelete = async (id: string) => {
  if (!confirm('Are you sure you want to remove this saved search and monitor?')) return
  try {
    await searchService.deleteSavedSearch(id)
    savedSearches.value = savedSearches.value.filter((s) => s.id !== id)
    statusMessage.value = 'Saved search removed.'
  } catch {
    errorMessage.value = 'Failed to delete saved search.'
  }
}

const handleViewMatches = async (item: SavedSearchRecord) => {
  selectedSearch.value = item
  showMatchesModal.value = true
  isLoadingMatches.value = true
  try {
    matchesList.value = await searchService.getSavedSearchMatches(item.id, 15)
  } catch {
    matchesList.value = []
  } finally {
    isLoadingMatches.value = false
  }
}

const handleExecuteInSearch = (query: string) => {
  router.push({ path: '/search', query: { q: query } })
}

onMounted(() => {
  loadSavedSearches()
})
</script>

<template>
  <div class="flex h-screen bg-black text-neutral-100 overflow-hidden font-sans">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <AppHeader />

      <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-serif text-white tracking-tight flex items-center gap-2.5">
              <span>Saved Searches & Monitors</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-sans font-medium bg-primary-500/10 text-primary-400 border border-primary-500/20">
                Automated Alerts
              </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-1">
              Persistent queries monitored against incoming intelligence feeds with Stage 11 multi-channel dispatch.
            </p>
          </div>

          <Button size="sm" class="gap-1.5" @click="router.push('/search')">
            <Search class="w-3.5 h-3.5" />
            <span>New Search</span>
          </Button>
        </div>

        <Alert v-if="statusMessage" variant="success">
          {{ statusMessage }}
        </Alert>
        <Alert v-if="errorMessage" variant="danger">
          {{ errorMessage }}
        </Alert>

        <div v-if="isLoading" class="space-y-4">
          <Skeleton class="h-24 w-full rounded-xl" />
          <Skeleton class="h-24 w-full rounded-xl" />
        </div>

        <!-- Empty State -->
        <Card
          v-else-if="savedSearches.length === 0"
          class="p-12 text-center border-neutral-800 bg-neutral-900/40 space-y-3"
        >
          <Bookmark class="w-10 h-10 text-neutral-600 mx-auto" />
          <h3 class="text-base font-serif text-white">No saved searches yet</h3>
          <p class="text-xs text-neutral-400 max-w-sm mx-auto">
            Save any search query from the discovery omnibar to receive automated alerts whenever new matching developments are verified.
          </p>
          <Button size="sm" class="gap-1.5 mt-2" @click="router.push('/search')">
            <Search class="w-3.5 h-3.5" />
            <span>Explore Intelligence</span>
          </Button>
        </Card>

        <!-- Saved Searches List -->
        <div v-else class="space-y-3">
          <Card
            v-for="item in savedSearches"
            :key="item.id"
            class="p-4 border-neutral-800 bg-neutral-900/70 hover:border-neutral-700 transition-all space-y-3"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-white font-serif">{{ item.name }}</span>
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono uppercase tracking-wider font-semibold"
                    :class="item.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-neutral-800 text-neutral-500 border border-neutral-700'"
                  >
                    {{ item.is_active ? 'Monitoring Active' : 'Paused' }}
                  </span>
                  <span class="px-2 py-0.5 rounded text-[10px] uppercase font-mono bg-primary-500/10 text-primary-400 border border-primary-500/20">
                    {{ item.alert_frequency.replace('_', ' ') }}
                  </span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                  <span class="text-neutral-400">Query:</span>
                  <code class="px-1.5 py-0.5 rounded bg-neutral-950 text-primary-300 font-mono text-[11px]">
                    "{{ item.query }}"
                  </code>
                </div>
                <p v-if="item.description" class="text-xs text-neutral-400">
                  {{ item.description }}
                </p>
              </div>

              <!-- Action Controls -->
              <div class="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  class="gap-1.5 text-xs"
                  @click="handleViewMatches(item)"
                >
                  <Clock class="w-3.5 h-3.5 text-neutral-400" />
                  <span>Matches ({{ item.match_count }})</span>
                </Button>

                <Button
                  variant="outline"
                  size="sm"
                  class="gap-1.5 text-xs text-primary-400 hover:bg-primary-500/10"
                  @click="handleExecuteInSearch(item.query)"
                >
                  <Search class="w-3.5 h-3.5" />
                  <span>Run Search</span>
                </Button>

                <Button
                  variant="outline"
                  size="sm"
                  class="gap-1.5 text-xs"
                  :class="item.is_active ? 'text-amber-400 hover:bg-amber-500/10' : 'text-emerald-400 hover:bg-emerald-500/10'"
                  @click="handleToggle(item)"
                >
                  <Pause v-if="item.is_active" class="w-3.5 h-3.5" />
                  <Play v-else class="w-3.5 h-3.5" />
                  <span>{{ item.is_active ? 'Pause' : 'Resume' }}</span>
                </Button>

                <button
                  type="button"
                  class="p-1.5 rounded text-neutral-500 hover:text-rose-400 hover:bg-rose-500/10 transition-colors"
                  title="Delete monitor"
                  @click="handleDelete(item.id)"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </div>
            </div>

            <div class="pt-2 border-t border-neutral-800/60 flex items-center justify-between text-[11px] text-neutral-500">
              <div>
                Channels: {{ item.delivery_channels?.join(', ') || 'in_app' }}
              </div>
              <div>
                Created {{ new Date(item.created_at).toLocaleDateString() }}
                <span v-if="item.last_matched_at"> · Last alert {{ new Date(item.last_matched_at).toLocaleDateString() }}</span>
              </div>
            </div>
          </Card>
        </div>
      </main>
    </div>

    <!-- Preview Matches Modal -->
    <Modal
      :is-open="showMatchesModal"
      max-width="md"
      @close="showMatchesModal = false"
    >
      <template #header>
        <div>
          <h3 class="text-sm font-medium text-white font-serif">
            Matched Records for "{{ selectedSearch?.name }}"
          </h3>
          <p class="text-xs text-neutral-400">
            Recent verified intelligence developments matching query "{{ selectedSearch?.query }}".
          </p>
        </div>
      </template>

      <div v-if="isLoadingMatches" class="space-y-3 py-4">
        <Skeleton class="h-14 w-full" />
        <Skeleton class="h-14 w-full" />
      </div>

      <div v-else-if="matchesList.length === 0" class="text-center py-6 text-neutral-500 text-xs">
        No recent platform items match this search term yet.
      </div>

      <div v-else class="space-y-2.5 max-h-96 overflow-y-auto">
        <div
          v-for="match in matchesList"
          :key="match.id"
          class="p-3 rounded-lg bg-neutral-900 border border-neutral-800 flex items-center justify-between gap-3 text-xs"
        >
          <div class="space-y-0.5 min-w-0">
            <div class="text-white font-medium truncate">{{ match.title }}</div>
            <div class="text-[11px] text-neutral-500">{{ match.source }} · {{ match.published_at ? new Date(match.published_at).toLocaleDateString() : '' }}</div>
          </div>
          <RouterLink
            :to="match.url"
            class="text-primary-400 hover:text-primary-300 shrink-0 p-1"
          >
            <ExternalLink class="w-3.5 h-3.5" />
          </RouterLink>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <Button variant="outline" size="sm" @click="showMatchesModal = false">
            Close
          </Button>
        </div>
      </template>
    </Modal>
  </div>
</template>
