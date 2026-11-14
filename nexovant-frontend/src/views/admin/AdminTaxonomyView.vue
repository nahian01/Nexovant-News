<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import { FolderTree, Tag as TagIcon, Bookmark, BookOpen } from 'lucide-vue-next'

const activeTab = ref<'categories' | 'subcategories' | 'topics' | 'series' | 'tags'>('categories')
const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')

const categories = ref<any[]>([])
const subcategories = ref<any[]>([])
const topics = ref<any[]>([])
const series = ref<any[]>([])
const tags = ref<any[]>([])

// Modals
const isCategoryModalOpen = ref(false)
const isSubcategoryModalOpen = ref(false)
const isTopicModalOpen = ref(false)
const isTagModalOpen = ref(false)
const isSeriesModalOpen = ref(false)

// Form states
const categoryForm = ref({ name: '', slug: '', description: '', icon: '' })
const subcategoryForm = ref({ category_id: '', name: '', slug: '', description: '' })
const topicForm = ref({ name: '', slug: '', parent_id: '', description: '' })
const tagForm = ref({ name: '', slug: '' })
const seriesForm = ref({ title: '', slug: '', description: '', cadence: 'weekly', is_active: true })

const fetchTaxonomy = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getTaxonomy()
    categories.value = res.categories || []
    subcategories.value = res.subcategories || []
    topics.value = res.topics || []
    series.value = res.editorial_series || []
    tags.value = res.tags || []
  } catch (err: any) {
    errorMessage.value = 'Failed to load taxonomy data.'
  } finally {
    isLoading.value = false
  }
}

const handleCreateCategory = async () => {
  try {
    const res = await adminService.createCategory(categoryForm.value)
    statusMessage.value = res.message || 'Category created successfully.'
    isCategoryModalOpen.value = false
    categoryForm.value = { name: '', slug: '', description: '', icon: '' }
    await fetchTaxonomy()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create category.'
  }
}

const handleDeleteCategory = async (id: string) => {
  if (!confirm('Are you sure you want to delete this category?')) return
  try {
    const res = await adminService.deleteCategory(id)
    statusMessage.value = res.message || 'Category deleted.'
    await fetchTaxonomy()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete category.'
  }
}

const handleCreateSubcategory = async () => {
  try {
    const res = await adminService.createSubcategory(subcategoryForm.value)
    statusMessage.value = res.message || 'Subcategory created successfully.'
    isSubcategoryModalOpen.value = false
    subcategoryForm.value = { category_id: '', name: '', slug: '', description: '' }
    await fetchTaxonomy()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create subcategory.'
  }
}

const handleCreateTopic = async () => {
  try {
    const res = await adminService.createTopic(topicForm.value)
    statusMessage.value = res.message || 'Topic created successfully.'
    isTopicModalOpen.value = false
    topicForm.value = { name: '', slug: '', parent_id: '', description: '' }
    await fetchTaxonomy()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create topic.'
  }
}

const handleCreateTag = async () => {
  try {
    const res = await adminService.createTag(tagForm.value)
    statusMessage.value = res.message || 'Tag created successfully.'
    isTagModalOpen.value = false
    tagForm.value = { name: '', slug: '' }
    await fetchTaxonomy()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create tag.'
  }
}

const handleDeleteTag = async (id: string) => {
  try {
    const res = await adminService.deleteTag(id)
    statusMessage.value = res.message || 'Tag deleted.'
    await fetchTaxonomy()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to delete tag.'
  }
}

const handleCreateSeries = async () => {
  try {
    const res = await adminService.createEditorialSeries(seriesForm.value)
    statusMessage.value = res.message || 'Editorial series created.'
    isSeriesModalOpen.value = false
    seriesForm.value = { title: '', slug: '', description: '', cadence: 'weekly', is_active: true }
    await fetchTaxonomy()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create series.'
  }
}

onMounted(() => {
  fetchTaxonomy()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Taxonomy & Knowledge Hierarchy
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Manage global categorization, topic DAG graphs, editorial series, and metadata tagging taxonomies.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button
            v-if="activeTab === 'categories'"
            variant="primary"
            size="sm"
            @click="isCategoryModalOpen = true"
          >
            + New Category
          </Button>
          <Button
            v-if="activeTab === 'subcategories'"
            variant="primary"
            size="sm"
            @click="isSubcategoryModalOpen = true"
          >
            + New Subcategory
          </Button>
          <Button
            v-if="activeTab === 'topics'"
            variant="primary"
            size="sm"
            @click="isTopicModalOpen = true"
          >
            + New Topic
          </Button>
          <Button
            v-if="activeTab === 'series'"
            variant="primary"
            size="sm"
            @click="isSeriesModalOpen = true"
          >
            + New Series
          </Button>
          <Button
            v-if="activeTab === 'tags'"
            variant="primary"
            size="sm"
            @click="isTagModalOpen = true"
          >
            + New Tag
          </Button>
          <Button variant="secondary" size="sm" @click="fetchTaxonomy">
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

      <!-- Navigation Tabs -->
      <div class="flex border-b border-nexo-border space-x-6 text-xs font-mono">
        <button
          @click="activeTab = 'categories'"
          :class="[
            'pb-3 font-semibold transition border-b-2',
            activeTab === 'categories'
              ? 'border-nexo-accent-gold text-nexo-accent-gold'
              : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          Primary Categories ({{ categories.length }})
        </button>
        <button
          @click="activeTab = 'subcategories'"
          :class="[
            'pb-3 font-semibold transition border-b-2',
            activeTab === 'subcategories'
              ? 'border-nexo-accent-gold text-nexo-accent-gold'
              : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          Subcategories ({{ subcategories.length }})
        </button>
        <button
          @click="activeTab = 'topics'"
          :class="[
            'pb-3 font-semibold transition border-b-2',
            activeTab === 'topics'
              ? 'border-nexo-accent-gold text-nexo-accent-gold'
              : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          Topic Hierarchy ({{ topics.length }})
        </button>
        <button
          @click="activeTab = 'series'"
          :class="[
            'pb-3 font-semibold transition border-b-2',
            activeTab === 'series'
              ? 'border-nexo-accent-gold text-nexo-accent-gold'
              : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          Editorial Series ({{ series.length }})
        </button>
        <button
          @click="activeTab = 'tags'"
          :class="[
            'pb-3 font-semibold transition border-b-2',
            activeTab === 'tags'
              ? 'border-nexo-accent-gold text-nexo-accent-gold'
              : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
          ]"
        >
          Entity Tags ({{ tags.length }})
        </button>
      </div>

      <!-- Categories Table -->
      <div v-if="activeTab === 'categories'">
        <Table :headers="['Name & Slug', 'Description', 'Icon', 'Subcategories', 'Articles', 'Actions']" :is-empty="categories.length === 0">
          <tr v-for="c in categories" :key="c.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4">
              <div class="font-semibold text-nexo-text-primary">{{ c.name }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted">{{ c.slug }}</div>
            </td>
            <td class="py-3 px-4 text-xs text-nexo-text-secondary max-w-sm">
              {{ c.description || '—' }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-accent-cyan">
              {{ c.icon || '—' }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              {{ c.subcategories?.length || 0 }} subcategories
            </td>
            <td class="py-3 px-4 font-mono text-xs font-bold text-nexo-accent-gold">
              {{ c.articles_count ?? '—' }}
            </td>
            <td class="py-3 px-4">
              <button
                @click="handleDeleteCategory(c.id)"
                class="px-2 py-1 rounded border border-red-200 text-red-600 hover:bg-red-50 text-xs font-mono"
              >
                Delete
              </button>
            </td>
          </tr>
        </Table>
      </div>

      <!-- Subcategories Table -->
      <div v-if="activeTab === 'subcategories'">
        <Table :headers="['Subcategory Name', 'Slug', 'Parent Category', 'Description', 'Actions']" :is-empty="subcategories.length === 0">
          <tr v-for="sc in subcategories" :key="sc.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4 font-semibold text-nexo-text-primary">
              {{ sc.name }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-text-muted">
              {{ sc.slug }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-accent-cyan font-bold">
              {{ sc.category?.name || 'Unassigned' }}
            </td>
            <td class="py-3 px-4 text-xs text-nexo-text-secondary">
              {{ sc.description || '—' }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-text-muted">
              Active
            </td>
          </tr>
        </Table>
      </div>

      <!-- Topics Table -->
      <div v-if="activeTab === 'topics'">
        <Table :headers="['Topic Name', 'Slug', 'Parent Topic (Hierarchy)', 'Description']" :is-empty="topics.length === 0">
          <tr v-for="t in topics" :key="t.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4 font-semibold text-nexo-text-primary">
              {{ t.name }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-text-muted">
              {{ t.slug }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <span v-if="t.parent" class="px-2 py-0.5 rounded bg-amber-50 text-nexo-accent-gold font-semibold">
                ↳ {{ t.parent.name }}
              </span>
              <span v-else class="text-nexo-text-muted">Root Topic</span>
            </td>
            <td class="py-3 px-4 text-xs text-nexo-text-secondary">
              {{ t.description || '—' }}
            </td>
          </tr>
        </Table>
      </div>

      <!-- Editorial Series Table -->
      <div v-if="activeTab === 'series'">
        <Table :headers="['Series Title', 'Slug', 'Cadence', 'Status', 'Description']" :is-empty="series.length === 0">
          <tr v-for="s in series" :key="s.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3 px-4 font-semibold text-nexo-text-primary">
              {{ s.title }}
            </td>
            <td class="py-3 px-4 font-mono text-xs text-nexo-text-muted">
              {{ s.slug }}
            </td>
            <td class="py-3 px-4 font-mono text-xs uppercase text-nexo-accent-cyan font-bold">
              {{ s.cadence }}
            </td>
            <td class="py-3 px-4 font-mono text-xs">
              <span :class="s.is_active ? 'text-emerald-700 font-bold' : 'text-gray-500'">
                {{ s.is_active ? 'Active Publication' : 'Paused' }}
              </span>
            </td>
            <td class="py-3 px-4 text-xs text-nexo-text-secondary">
              {{ s.description || '—' }}
            </td>
          </tr>
        </Table>
      </div>

      <!-- Tags Table -->
      <div v-if="activeTab === 'tags'">
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
          <div
            v-for="tag in tags"
            :key="tag.id"
            class="flex items-center justify-between p-2.5 rounded-xl border border-nexo-border bg-white text-xs"
          >
            <div>
              <span class="font-semibold text-nexo-text-primary block">#{{ tag.name }}</span>
              <span class="text-[10px] font-mono text-nexo-text-muted">{{ tag.slug }}</span>
            </div>
            <button
              @click="handleDeleteTag(tag.id)"
              class="text-red-500 hover:text-red-700 text-xs ml-2"
              title="Delete Tag"
            >
              ✕
            </button>
          </div>
        </div>
      </div>

      <!-- Modals -->
      <Modal :is-open="isCategoryModalOpen" title="Create Primary Category" max-width="md" @close="isCategoryModalOpen = false">
        <form @submit.prevent="handleCreateCategory" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Category Name</label>
            <input v-model="categoryForm.name" required placeholder="e.g. Autonomous Systems" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Slug (Optional)</label>
            <input v-model="categoryForm.slug" placeholder="autonomous-systems" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Description</label>
            <textarea v-model="categoryForm.description" rows="2" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"></textarea>
          </div>
          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isCategoryModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit">Create Category</Button>
          </div>
        </form>
      </Modal>

      <Modal :is-open="isSubcategoryModalOpen" title="Create Subcategory" max-width="md" @close="isSubcategoryModalOpen = false">
        <form @submit.prevent="handleCreateSubcategory" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Parent Category</label>
            <select v-model="subcategoryForm.category_id" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
              <option value="" disabled>Select parent category...</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Subcategory Name</label>
            <input v-model="subcategoryForm.name" required placeholder="e.g. Foundation Models" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Description</label>
            <textarea v-model="subcategoryForm.description" rows="2" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"></textarea>
          </div>
          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isSubcategoryModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit">Create Subcategory</Button>
          </div>
        </form>
      </Modal>

      <Modal :is-open="isTopicModalOpen" title="Create Knowledge Topic" max-width="md" @close="isTopicModalOpen = false">
        <form @submit.prevent="handleCreateTopic" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Parent Topic (Optional for Root)</label>
            <select v-model="topicForm.parent_id" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
              <option value="">None (Top-Level Root Topic)</option>
              <option v-for="t in topics" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Topic Name</label>
            <input v-model="topicForm.name" required placeholder="e.g. Retrieval-Augmented Generation" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Description</label>
            <textarea v-model="topicForm.description" rows="2" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"></textarea>
          </div>
          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isTopicModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit">Create Topic</Button>
          </div>
        </form>
      </Modal>

      <Modal :is-open="isTagModalOpen" title="Register Entity Tag" max-width="sm" @close="isTagModalOpen = false">
        <form @submit.prevent="handleCreateTag" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Tag Name</label>
            <input v-model="tagForm.name" required placeholder="e.g. quantum-annealing" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isTagModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit">Add Tag</Button>
          </div>
        </form>
      </Modal>

      <Modal :is-open="isSeriesModalOpen" title="Create Editorial Series" max-width="md" @close="isSeriesModalOpen = false">
        <form @submit.prevent="handleCreateSeries" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Series Title</label>
            <input v-model="seriesForm.title" required placeholder="e.g. Sovereign AI Weekly Dispatch" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Cadence</label>
            <select v-model="seriesForm.cadence" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="biweekly">Bi-Weekly</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
            </select>
          </div>
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Description</label>
            <textarea v-model="seriesForm.description" rows="2" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"></textarea>
          </div>
          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isSeriesModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit">Create Series</Button>
          </div>
        </form>
      </Modal>
    </div>
  </AdminLayout>
</template>
