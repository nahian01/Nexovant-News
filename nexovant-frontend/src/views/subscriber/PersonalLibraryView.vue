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
  color: '#d4af37',
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
  try {
    const res = await workspaceService.createCollection(collectionForm.value)
    if (res.success) {
      statusMessage.value = `Collection "${res.collection.name}" created.`
      isCollectionModalOpen.value = false
      collectionForm.value = { name: '', description: '', color: '#d4af37' }
      await loadData()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create collection.'
  } finally {
    isSubmitting.value = false
  }
}

const handleDeleteCollection = async (id: string) => {
  if (!confirm('Delete this collection? Saved items will be kept in your main library.')) return
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
  try {
    const tags = notesForm.value.tagsRaw.split(',').map(s => s.trim()).filter(Boolean)
    const res = await workspaceService.updateLibraryItem(selectedItem.value.id, {
      private_notes: notesForm.value.private_notes,
      tags,
      collection_id: notesForm.value.collection_id || null,
    })
    if (res.success) {
      statusMessage.value = 'Private notes and metadata updated.'
      isNotesModalOpen.value = false
      await loadItems()
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update notes.'
  } finally {
    isSubmitting.value = false
  }
}

const handleRemoveItem = async (id: string) => {
  if (!confirm('Remove this intelligence record from your personal library?')) return
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

const handleExportCollection = async (colId: string, format: string) => {
  try {
    const res = await workspaceService.exportCollection(colId, format)
    if (res.success) {
      const content = format === 'markdown' ? res.export : JSON.stringify(res.export, null, 2)
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
              <span>Personal Knowledge Library</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
              Personal Intelligence Library & Notes
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Organize saved articles, research dossiers, and executive briefings into collections with strictly private annotations.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="isCollectionModalOpen = true">
              <Folder class="w-3.5 h-3.5 mr-1" /> New Collection
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

        <!-- Collections Pills Bar -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
          <button
            @click="activeCollectionId = 'all'; loadItems()"
            class="px-3 py-1.5 rounded-full font-medium transition-colors shrink-0"
            :class="activeCollectionId === 'all' ? 'bg-slate-900 text-white' : 'bg-white border border-nexo-border text-nexo-text-secondary hover:text-nexo-text-primary'"
          >
            All Saved Records
          </button>

          <div
            v-for="c in collections"
            :key="c.id"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border transition-colors shrink-0"
            :class="activeCollectionId === c.id ? 'bg-amber-100 border-amber-300 text-amber-900 font-bold' : 'bg-white border-nexo-border text-nexo-text-secondary hover:text-nexo-text-primary'"
          >
            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: c.color }"></span>
            <button @click="activeCollectionId = c.id; loadItems()">
              {{ c.name }} ({{ c.items_count || 0 }})
            </button>
            <button
              v-if="activeCollectionId === c.id"
              @click="handleExportCollection(c.id, 'markdown')"
              title="Export Collection as Markdown"
              class="ml-1 text-slate-500 hover:text-slate-800"
            >
              <Download class="w-3 h-3" />
            </button>
            <button
              v-if="activeCollectionId === c.id"
              @click="handleDeleteCollection(c.id)"
              title="Delete Collection"
              class="text-slate-400 hover:text-rose-600"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Filter and Search Row -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-3 rounded-lg border border-nexo-border/80">
          <div class="w-full sm:w-80">
            <Input v-model="searchQuery" placeholder="Search private notes or tags..." size="sm" @input="loadItems" />
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto">
            <select
              v-model="activeItemType"
              @change="loadItems"
              class="text-xs bg-white border border-nexo-border rounded px-3 py-1.5 text-nexo-text-primary focus:outline-hidden"
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
        <div v-if="isLoading" class="text-xs text-nexo-text-muted py-12 text-center">
          Loading personal intelligence repository...
        </div>

        <div v-else-if="libraryItems.length === 0" class="p-12 bg-white rounded-lg border border-nexo-border text-center space-y-2">
          <Bookmark class="w-10 h-10 text-slate-300 mx-auto" />
          <h3 class="font-serif font-bold text-nexo-text-primary text-base">Your Library is Empty</h3>
          <p class="text-xs text-nexo-text-secondary max-w-md mx-auto">
            Save articles, publications, and deep research dossiers to annotate them with strictly private executive notes.
          </p>
        </div>

        <div v-else class="space-y-3">
          <Card
            v-for="item in libraryItems"
            :key="item.id"
            class="p-4 bg-white border border-nexo-border/80 shadow-xs hover:border-amber-300 transition-colors space-y-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-slate-100 text-slate-700 font-bold">
                    {{ item.item_type.replace('_', ' ') }}
                  </span>
                  <span v-if="item.collection" class="px-2 py-0.5 rounded text-[10px] font-mono font-medium text-amber-800 bg-amber-50 border border-amber-200">
                    Collection: {{ item.collection.name }}
                  </span>
                  <span class="text-[11px] font-mono text-nexo-text-muted">
                    Saved: {{ new Date(item.created_at).toLocaleDateString() }}
                  </span>
                </div>

                <h3 class="font-serif font-bold text-base text-nexo-text-primary leading-snug">
                  {{ item.title }}
                </h3>
                <p v-if="item.subtitle" class="text-xs text-nexo-text-secondary line-clamp-2">
                  {{ item.subtitle }}
                </p>
              </div>

              <div class="flex items-center gap-1.5 shrink-0">
                <Button variant="outline" size="sm" @click="openNotesModal(item)" title="Edit Private Notes">
                  <Edit3 class="w-3.5 h-3.5 mr-1" /> Notes
                </Button>
                <Button variant="outline" size="sm" @click="handleRemoveItem(item.id)" title="Remove from Library">
                  <Trash2 class="w-3.5 h-3.5 text-rose-500" />
                </Button>
              </div>
            </div>

            <!-- Private Notes Display Box (Owner Only) -->
            <div v-if="item.private_notes" class="p-3 bg-amber-50/60 rounded border border-amber-200/80 text-xs text-amber-900 space-y-1">
              <div class="flex items-center gap-1 text-[10px] font-mono text-amber-800 uppercase font-bold">
                <Lock class="w-3 h-3 text-amber-700" /> Private Executive Note (Owner Only)
              </div>
              <p class="font-serif italic leading-relaxed whitespace-pre-wrap">
                {{ item.private_notes }}
              </p>
            </div>

            <!-- Tags Pills -->
            <div v-if="item.tags && item.tags.length > 0" class="flex items-center gap-1.5 pt-1">
              <Tag class="w-3 h-3 text-slate-400" />
              <span
                v-for="(t, idx) in item.tags"
                :key="idx"
                class="px-2 py-0.5 rounded bg-slate-50 text-slate-600 border border-slate-200 text-[10px] font-mono"
              >
                #{{ t }}
              </span>
            </div>
          </Card>
        </div>
      </main>
    </div>

    <!-- MODAL 1: Create Collection -->
    <Modal :is-open="isCollectionModalOpen" title="Create Library Collection" @close="isCollectionModalOpen = false">
      <form @submit.prevent="handleCreateCollection" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Collection Name</label>
          <Input v-model="collectionForm.name" placeholder="e.g. European Semiconductor Mandates" required />
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Description</label>
          <textarea
            v-model="collectionForm.description"
            rows="2"
            class="w-full text-xs border border-nexo-border rounded p-2.5 text-nexo-text-primary focus:outline-hidden"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Color Stamp</label>
          <div class="flex items-center gap-2">
            <input type="color" v-model="collectionForm.color" class="w-8 h-8 rounded border border-slate-300" />
            <Input v-model="collectionForm.color" size="sm" class="w-32" />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="outline" size="sm" type="button" @click="isCollectionModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">Create Collection</Button>
        </div>
      </form>
    </Modal>

    <!-- MODAL 2: Edit Notes & Tags -->
    <Modal :is-open="isNotesModalOpen" title="Edit Private Notes & Organization" @close="isNotesModalOpen = false">
      <form @submit.prevent="handleSaveNotes" class="space-y-4 text-left">
        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Target Collection</label>
          <select
            v-model="notesForm.collection_id"
            class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
          >
            <option value="">No Collection (Unfiled)</option>
            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Private Executive Notes (Strictly Confidential)</label>
          <textarea
            v-model="notesForm.private_notes"
            rows="4"
            class="w-full text-xs border border-nexo-border rounded p-2.5 text-nexo-text-primary focus:outline-hidden font-serif leading-relaxed"
            placeholder="Record proprietary analysis, investment thesis, or board talking points..."
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-medium text-nexo-text-primary mb-1">Tags (Comma-separated)</label>
          <Input v-model="notesForm.tagsRaw" placeholder="e.g. EUV, TSMC, Dresden, Q3-Briefing" />
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="outline" size="sm" type="button" @click="isNotesModalOpen = false">Cancel</Button>
          <Button variant="primary" size="sm" type="submit" :disabled="isSubmitting">Save Notes</Button>
        </div>
      </form>
    </Modal>
  </div>
</template>
