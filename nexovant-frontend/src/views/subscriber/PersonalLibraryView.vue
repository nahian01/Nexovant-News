<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import { workspaceService } from '@/services/api/workspace.service'
import {
  Folder,
  Plus,
  Trash2,
  Edit3,
  Download,
  Search,
  Tag,
  Lock,
  FileText,
  Bookmark,
  ExternalLink,
  Layers
} from 'lucide-vue-next'

interface LibraryItem {
  id: string
  item_type: string
  item_id: string
  title: string
  subtitle?: string
  resource_slug?: string
  resource_date?: string
  private_notes?: string
  tags?: string[]
  collection_id?: string
  collection?: {
    id: string
    name: string
    color: string
  }
  created_at: string
}

interface CollectionRecord {
  id: string
  name: string
  description?: string
  color: string
  items_count?: number
}

const libraryItems = ref<LibraryItem[]>([])
const collections = ref<CollectionRecord[]>([])
const activeCollectionId = ref<string>('all')
const activeItemType = ref<string>('all')
const searchQuery = ref<string>('')

const isLoading = ref(true)
const isSubmitting = ref(false)
const statusMessage = ref('')
const errorMessage = ref('')

// Collection Modal
const isCollectionModalOpen = ref(false)
const collectionForm = ref({
  name: '',
  description: '',
  color: '#B7791F',
})

// Edit Notes Modal
const isNotesModalOpen = ref(false)
const selectedItem = ref<LibraryItem | null>(null)
const notesForm = ref({
  private_notes: '',
  tagsRaw: '',
  collection_id: '',
})

const loadData = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const colRes = await workspaceService.getCollections()
    if (colRes.success) {
      collections.value = colRes.collections
    }

    await loadItems()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load personal library.'
  } finally {
    isLoading.value = false
  }
}

const loadItems = async () => {
  try {
    const params: Record<string, any> = {}
    if (activeCollectionId.value !== 'all') {
      params.collection_id = activeCollectionId.value
    }
    if (activeItemType.value !== 'all') {
      params.item_type = activeItemType.value
    }
    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    const res = await workspaceService.getLibraryItems(params)
    if (res.success) {
      libraryItems.value = res.items.data
    }
  } catch {
    //
  }
}

const handleCreateCollection = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const res = await workspaceService.createCollection(collectionForm.value)
    if (res.success) {
      statusMessage.value = 'Collection created.'
      isCollectionModalOpen.value = false
      collectionForm.value = { name: '', description: '', color: '#B7791F' }
      await loadData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.name?.[0] || 'Failed to create collection.'
  } finally {
    isSubmitting.value = false
  }
}

const handleDeleteCollection = async (id: string) => {
  if (!confirm('Are you sure you want to delete this collection?')) return
  try {
    const res = await workspaceService.deleteCollection(id)
    if (res.success) {
      statusMessage.value = 'Collection deleted.'
      if (activeCollectionId.value === id) activeCollectionId.value = 'all'
      await loadData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete collection.'
  }
}

const openNotesModal = (item: LibraryItem) => {
  selectedItem.value = item
  notesForm.value = {
    private_notes: item.private_notes || '',
    tagsRaw: item.tags?.join(', ') || '',
    collection_id: item.collection_id || '',
  }
  isNotesModalOpen.value = true
}

const handleSaveNotes = async () => {
  if (!selectedItem.value) return
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const tags = notesForm.value.tagsRaw
      ? notesForm.value.tagsRaw.split(',').map((t) => t.trim()).filter(Boolean)
      : []

    const res = await workspaceService.updateLibraryItem(selectedItem.value.id, {
      private_notes: notesForm.value.private_notes,
      tags,
      collection_id: notesForm.value.collection_id || null,
    })

    if (res.success) {
      statusMessage.value = 'Notes updated.'
      isNotesModalOpen.value = false
      await loadItems()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to save notes.'
  } finally {
    isSubmitting.value = false
  }
}

const handleRemoveItem = async (id: string) => {
  if (!confirm('Remove this saved item from your library?')) return
  try {
    const res = await workspaceService.deleteLibraryItem(id)
    if (res.success) {
      statusMessage.value = 'Item removed from library.'
      await loadItems()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to remove item.'
  }
}

const handleExportCollection = async (colId: string, format: 'markdown' | 'json') => {
  try {
    const res = await workspaceService.exportCollection(colId, format)
    if (res.success) {
      const content = format === 'markdown' ? res.markdown : JSON.stringify(res.data, null, 2)
      const blob = new Blob([content], { type: format === 'markdown' ? 'text/markdown' : 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `nexovant-collection-${colId}.${format === 'markdown' ? 'md' : 'json'}`
      a.click()
      URL.revokeObjectURL(url)
      statusMessage.value = `Collection exported as ${format.toUpperCase()}.`
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Export failed.'
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Header (per Mockup P20 Saved.png) -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center space-x-2 text-[11px] font-mono text-[#58488A] uppercase">
            <span>Personal Workspace</span>
            <span>/</span>
            <span>Personal Knowledge Library</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Saved Intelligence & Annotations
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875]">
            Organize saved articles, research dossiers, and executive briefings into collections with strictly private annotations.
          </p>
        </div>

        <button
          type="button"
          class="btn-ink text-xs px-4 py-2 flex items-center gap-1.5"
          @click="isCollectionModalOpen = true"
        >
          <Folder class="w-3.5 h-3.5" />
          <span>New Collection</span>
        </button>
      </div>

      <!-- Alerts -->
      <div v-if="statusMessage" class="p-3 bg-[#E3F1EA] border border-[#17794D]/30 text-[#17794D] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✓ {{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="text-[#17794D] font-bold ml-2">✕</button>
      </div>

      <div v-if="errorMessage" class="p-3 bg-[#FBE4E1] border border-[#B3261E]/30 text-[#B3261E] text-xs font-mono rounded-[2px] flex items-center justify-between">
        <span>✕ {{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="text-[#B3261E] font-bold ml-2">✕</button>
      </div>

      <!-- Collections Pills Bar -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
        <button
          @click="activeCollectionId = 'all'; loadItems()"
          class="px-3.5 py-1.5 rounded-full font-medium transition-colors shrink-0"
          :class="activeCollectionId === 'all' ? 'bg-[#0E1A2B] text-white shadow-xs' : 'bg-white border border-[#D0D9CF] text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'"
        >
          All Saved Records
        </button>

        <div
          v-for="c in collections"
          :key="c.id"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border transition-colors shrink-0 text-xs"
          :class="activeCollectionId === c.id ? 'bg-[#F6E7C8] border-[#B7791F] text-[#8A5A08] font-bold' : 'bg-white border-[#D0D9CF] text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'"
        >
          <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: c.color }"></span>
          <button @click="activeCollectionId = c.id; loadItems()">
            {{ c.name }} ({{ c.items_count || 0 }})
          </button>
          <button
            v-if="activeCollectionId === c.id"
            @click="handleExportCollection(c.id, 'markdown')"
            title="Export Collection as Markdown"
            class="ml-1 text-[#8A5A08] hover:text-[#0E1A2B]"
          >
            <Download class="w-3 h-3" />
          </button>
          <button
            v-if="activeCollectionId === c.id"
            @click="handleDeleteCollection(c.id)"
            title="Delete Collection"
            class="text-[#8A5A08] hover:text-[#B3261E]"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Filter and Search Row -->
      <div class="card-editorial p-3 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#5F6875]" />
          <input
            v-model="searchQuery"
            placeholder="Search private notes or tags..."
            @input="loadItems"
            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888]"
          />
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <select
            v-model="activeItemType"
            @change="loadItems"
            class="text-xs bg-white border border-[#D0D9CF] rounded-[4px] px-3 py-1.5 text-[#0E1A2B] focus:outline-none"
          >
            <option value="all">All Resource Formats</option>
            <option value="article">Intelligence Articles</option>
            <option value="publication">Executive Briefing PDFs</option>
            <option value="research_report">Deep Research Dossiers</option>
            <option value="intelligence_event">Historical Milestones</option>
          </select>
        </div>
      </div>

      <!-- Library Items Listing -->
      <div v-if="isLoading" class="text-xs text-[#5F6875] py-12 text-center">
        Loading personal intelligence repository...
      </div>

      <div v-else-if="libraryItems.length === 0" class="card-editorial p-12 text-center space-y-2">
        <Bookmark class="w-8 h-8 text-[#5F6875] mx-auto" />
        <h3 class="font-serif font-medium text-[#0E1A2B] text-base">Your Library is Empty</h3>
        <p class="text-xs text-[#5F6875] max-w-md mx-auto">
          Save articles, publications, and deep research dossiers to annotate them with strictly private executive notes.
        </p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="item in libraryItems"
          :key="item.id"
          class="card-editorial p-5 space-y-3 hover:border-[#274888]/40 transition-colors"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono uppercase bg-[#EEEBE4] text-[#0E1A2B] font-bold">
                  {{ item.item_type.replace('_', ' ') }}
                </span>
                <span v-if="item.collection" class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-medium text-[#8A5A08] bg-[#F6E7C8]">
                  Collection: {{ item.collection.name }}
                </span>
                <span class="text-[11px] font-mono text-[#5F6875]">
                  Saved: {{ new Date(item.created_at).toLocaleDateString() }}
                </span>
              </div>

              <h3 class="editorial-headline text-lg font-medium text-[#0E1A2B] leading-snug">
                {{ item.title }}
              </h3>
              <p v-if="item.subtitle" class="text-xs text-[#5F6875] line-clamp-2">
                {{ item.subtitle }}
              </p>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
              <button
                type="button"
                class="px-2.5 py-1 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold flex items-center gap-1"
                @click="openNotesModal(item)"
                title="Edit Private Notes"
              >
                <Edit3 class="w-3.5 h-3.5" />
                <span>Notes</span>
              </button>
              <button
                type="button"
                class="p-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#FBE4E1] hover:border-[#B3261E]/30 text-[#5F6875] hover:text-[#B3261E] transition-colors"
                @click="handleRemoveItem(item.id)"
                title="Remove from Library"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Private Notes Display Box (Owner Only) -->
          <div v-if="item.private_notes" class="p-3 bg-[#F8FAF7] rounded-[2px] border border-[#D0D9CF] text-xs space-y-1">
            <div class="flex items-center gap-1 text-[10px] font-mono text-[#8A5A08] uppercase font-bold">
              <Lock class="w-3 h-3 text-[#8A5A08]" />
              <span>Private Executive Note (Strictly Confidential)</span>
            </div>
            <p class="font-serif italic leading-relaxed whitespace-pre-wrap text-[#0E1A2B]">
              {{ item.private_notes }}
            </p>
          </div>

          <!-- Tags Pills -->
          <div v-if="item.tags && item.tags.length > 0" class="flex items-center gap-1.5 pt-1">
            <Tag class="w-3 h-3 text-[#5F6875]" />
            <span
              v-for="(t, idx) in item.tags"
              :key="idx"
              class="px-2 py-0.5 rounded-[2px] bg-[#EEEBE4] text-[#3F4A5A] text-[10px] font-mono"
            >
              #{{ t }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: Create Collection -->
    <Modal :is-open="isCollectionModalOpen" title="Create Library Collection" @close="isCollectionModalOpen = false">
      <form @submit.prevent="handleCreateCollection" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Collection Name</label>
          <input
            v-model="collectionForm.name"
            placeholder="e.g. European Semiconductor Mandates"
            required
            class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Description</label>
          <textarea
            v-model="collectionForm.description"
            rows="2"
            class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Color Stamp</label>
          <div class="flex items-center gap-2">
            <input type="color" v-model="collectionForm.color" class="w-8 h-8 rounded-[2px] border border-[#D0D9CF]" />
            <input
              v-model="collectionForm.color"
              class="w-32 text-xs border border-[#D0D9CF] rounded-[4px] p-2 bg-white text-[#0E1A2B]"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-[#D0D9CF]">
          <button
            type="button"
            class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
            @click="isCollectionModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-ink text-xs px-4 py-1.5"
            :disabled="isSubmitting"
          >
            Create Collection
          </button>
        </div>
      </form>
    </Modal>

    <!-- MODAL 2: Edit Notes & Tags -->
    <Modal :is-open="isNotesModalOpen" title="Edit Private Notes & Organization" @close="isNotesModalOpen = false">
      <form @submit.prevent="handleSaveNotes" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Target Collection</label>
          <select
            v-model="notesForm.collection_id"
            class="w-full text-xs bg-white border border-[#D0D9CF] rounded-[4px] p-2.5 text-[#0E1A2B] focus:outline-none"
          >
            <option value="">No Collection (Unfiled)</option>
            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Private Executive Notes (Strictly Confidential)</label>
          <textarea
            v-model="notesForm.private_notes"
            rows="4"
            class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none font-serif leading-relaxed"
            placeholder="Record proprietary analysis, investment thesis, or board talking points..."
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-[#5F6875] mb-1">Tags (Comma-separated)</label>
          <input
            v-model="notesForm.tagsRaw"
            placeholder="e.g. EUV, TSMC, Dresden, Q3-Briefing"
            class="w-full text-xs border border-[#D0D9CF] rounded-[4px] p-2.5 bg-white text-[#0E1A2B] focus:border-[#274888] focus:outline-none"
          />
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-[#D0D9CF]">
          <button
            type="button"
            class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
            @click="isNotesModalOpen = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-ink text-xs px-4 py-1.5"
            :disabled="isSubmitting"
          >
            Save Notes
          </button>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>
