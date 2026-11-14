<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Table from '@/components/ui/Table.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminService } from '@/services/api/admin.service'
import { History, Lock, Unlock, Eye, Check, X, FileEdit, Trash2, Plus } from 'lucide-vue-next'

const articles = ref<any[]>([])
const categories = ref<any[]>([])
const isLoading = ref(true)
const statusFilter = ref('all')
const statusMessage = ref('')
const errorMessage = ref('')

// Modals
const isCreateModalOpen = ref(false)
const isEditModalOpen = ref(false)
const isRevisionsModalOpen = ref(false)
const currentArticle = ref<any>(null)
const revisionsList = ref<any[]>([])

// Create form
const articleForm = ref({
  title: '',
  slug: '',
  raw_content: '',
  summary: '',
  category_id: '',
  importance_score: 8,
  verification_status: 'confirmed',
  status: 'published',
  lock_category: false,
  lock_topic: false,
  lock_content: false,
  lock_image: false,
  lock_tags: false,
})

// Edit form
const editForm = ref<any>({})

const headers = ['Headline & Locks', 'Category', 'Status', 'Verification', 'Importance', 'Actions']

const fetchArticles = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getEditorialArticles({ status: statusFilter.value })
    articles.value = res.data || []
    if (res.categories) {
      categories.value = res.categories
    }
  } catch (err: any) {
    errorMessage.value = 'Failed to load editorial articles.'
  } finally {
    isLoading.value = false
  }
}

const openCreateModal = () => {
  articleForm.value = {
    title: '',
    slug: '',
    raw_content: '',
    summary: '',
    category_id: categories.value[0]?.id || '',
    importance_score: 8,
    verification_status: 'confirmed',
    status: 'published',
    lock_category: false,
    lock_topic: false,
    lock_content: false,
    lock_image: false,
    lock_tags: false,
  }
  isCreateModalOpen.value = true
}

const handleCreateArticle = async () => {
  try {
    const res = await adminService.createArticle(articleForm.value)
    statusMessage.value = res.message || 'Article published successfully.'
    isCreateModalOpen.value = false
    await fetchArticles()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to create article.'
  }
}

const openEditModal = (article: any) => {
  currentArticle.value = article
  editForm.value = {
    title: article.title,
    raw_content: article.raw_content || article.summary,
    summary: article.summary,
    category_id: article.category_id || article.categories?.[0]?.id,
    importance_score: article.importance_score,
    verification_status: article.verification_status,
    status: article.status,
    lock_category: !!article.lock_category,
    lock_topic: !!article.lock_topic,
    lock_content: !!article.lock_content,
    lock_image: !!article.lock_image,
    lock_tags: !!article.lock_tags,
  }
  isEditModalOpen.value = true
}

const handleUpdateArticle = async () => {
  if (!currentArticle.value) return
  try {
    const res = await adminService.updateArticle(currentArticle.value.id, editForm.value)
    statusMessage.value = res.message || 'Article and override locks updated.'
    isEditModalOpen.value = false
    await fetchArticles()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update article.'
  }
}

const openRevisionsModal = async (article: any) => {
  currentArticle.value = article
  try {
    revisionsList.value = await adminService.getArticleRevisions(article.id)
    isRevisionsModalOpen.value = true
  } catch (err: any) {
    errorMessage.value = 'Failed to load revisions history.'
  }
}

const handleRestoreRevision = async (revisionId: string) => {
  if (!currentArticle.value) return
  if (!confirm('Restore article to this previous revision state?')) return
  try {
    const res = await adminService.restoreArticleRevision(currentArticle.value.id, revisionId)
    statusMessage.value = res.message || 'Revision restored successfully.'
    isRevisionsModalOpen.value = false
    await fetchArticles()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to restore revision.'
  }
}

const handleReview = async (id: string, action: 'approve' | 'reject') => {
  try {
    const res = await adminService.reviewArticle(id, { action })
    statusMessage.value = res.message
    await fetchArticles()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to review article.'
  }
}

const handleDelete = async (id: string) => {
  if (!confirm('Archive this article?')) return
  try {
    const res = await adminService.deleteArticle(id)
    statusMessage.value = res.message || 'Article archived.'
    await fetchArticles()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to archive article.'
  }
}

onMounted(() => {
  fetchArticles()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & CTA -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Editorial Article & Dispatch Management
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Publish verified intelligence, manage 5 manual override locks, inspect full revision history, or unpublish stories.
          </p>
        </div>

        <div class="flex items-center space-x-2">
          <Button variant="primary" size="sm" @click="openCreateModal" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">
            <Plus class="h-4 w-4 mr-1" /> Create Article
          </Button>
          <Button variant="secondary" size="sm" @click="fetchArticles">
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

      <!-- Status Filter Tabs -->
      <div class="flex border-b border-nexo-border space-x-6 text-xs font-mono">
        <button
          @click="statusFilter = 'all'; fetchArticles()"
          :class="['pb-2.5 font-semibold transition border-b-2', statusFilter === 'all' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          All Articles
        </button>
        <button
          @click="statusFilter = 'review_queue'; fetchArticles()"
          :class="['pb-2.5 font-semibold transition border-b-2', statusFilter === 'review_queue' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Review Queue
        </button>
        <button
          @click="statusFilter = 'published'; fetchArticles()"
          :class="['pb-2.5 font-semibold transition border-b-2', statusFilter === 'published' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Published
        </button>
        <button
          @click="statusFilter = 'draft'; fetchArticles()"
          :class="['pb-2.5 font-semibold transition border-b-2', statusFilter === 'draft' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Drafts
        </button>
        <button
          @click="statusFilter = 'archived'; fetchArticles()"
          :class="['pb-2.5 font-semibold transition border-b-2', statusFilter === 'archived' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Archived
        </button>
      </div>

      <!-- Articles Table -->
      <Table :headers="headers" :is-empty="articles.length === 0">
        <tr v-for="a in articles" :key="a.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary max-w-md">
            <RouterLink :to="`/news/${a.slug}`" class="hover:text-nexo-accent-cyan transition-colors">
              {{ a.title }}
            </RouterLink>
            <!-- 5 Override Lock Badges -->
            <div class="flex items-center gap-1.5 text-[9px] font-mono mt-1.5 flex-wrap">
              <span
                v-if="a.lock_category || a.lock_topic || a.lock_content || a.lock_image || a.lock_tags"
                class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 font-bold border border-amber-200"
              >
                <Lock class="h-2.5 w-2.5" /> Locks:
                <span v-if="a.lock_category">Cat </span>
                <span v-if="a.lock_topic">Top </span>
                <span v-if="a.lock_content">Txt </span>
                <span v-if="a.lock_image">Img </span>
                <span v-if="a.lock_tags">Tag</span>
              </span>
              <span v-else class="text-nexo-text-muted flex items-center gap-1">
                <Unlock class="h-2.5 w-2.5" /> AI Unlocked
              </span>
            </div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-cyan">
            {{ a.categories?.[0]?.name || 'Capital Expansion' }}
          </td>
          <td class="py-3.5 px-4 font-mono">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] uppercase font-bold',
                a.status === 'published' ? 'bg-emerald-100 text-emerald-800' :
                a.status === 'archived' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800'
              ]"
            >
              {{ a.status || (a.is_editorial_approved ? 'published' : 'pending') }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <Badge :variant="a.verification_status" size="sm" />
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary font-bold">
            {{ a.importance_score }}/10
          </td>
          <td class="py-3.5 px-4 font-mono">
            <div class="flex items-center space-x-1.5">
              <button
                @click="openEditModal(a)"
                class="px-2 py-1 rounded bg-white hover:bg-nexo-bg border border-nexo-border text-xs text-nexo-text-primary"
                title="Edit Article & Locks"
              >
                <FileEdit class="h-3.5 w-3.5" />
              </button>

              <button
                @click="openRevisionsModal(a)"
                class="px-2 py-1 rounded bg-white hover:bg-nexo-bg border border-nexo-border text-xs text-nexo-text-muted hover:text-nexo-text-primary"
                title="View Revisions History"
              >
                <History class="h-3.5 w-3.5" />
              </button>

              <button
                v-if="!a.is_editorial_approved || a.status !== 'published'"
                @click="handleReview(a.id, 'approve')"
                class="px-2 py-1 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold hover:bg-emerald-100"
                title="Approve & Publish"
              >
                Approve
              </button>
              <button
                v-else
                @click="handleReview(a.id, 'reject')"
                class="px-2 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 text-xs hover:bg-amber-100"
                title="Unpublish"
              >
                Unpublish
              </button>

              <button
                @click="handleDelete(a.id)"
                class="px-1.5 py-1 rounded text-red-600 hover:bg-red-50 text-xs"
                title="Archive"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>
          </td>
        </tr>
      </Table>

      <!-- Create Modal -->
      <Modal :is-open="isCreateModalOpen" title="Publish New Intelligence Dispatch" max-width="lg" @close="isCreateModalOpen = false">
        <form @submit.prevent="handleCreateArticle" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Headline Title</label>
            <input v-model="articleForm.title" required placeholder="Headline of intelligence dispatch..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Primary Category</label>
              <select v-model="articleForm.category_id" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Importance Score (1-10)</label>
              <input v-model="articleForm.importance_score" type="number" min="1" max="10" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Executive Summary</label>
            <textarea v-model="articleForm.summary" rows="2" placeholder="Brief 2-3 sentence executive synopsis..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"></textarea>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Full Content / Dispatch Body</label>
            <textarea v-model="articleForm.raw_content" rows="5" required placeholder="Full technical assessment body..." class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"></textarea>
          </div>

          <!-- 5 Manual Override Locks -->
          <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-200/80 space-y-2">
            <span class="font-mono text-[10px] uppercase text-amber-900 font-bold block">
              🛡️ Manual Editorial Override Locks (Prevent AI pipeline from overwriting)
            </span>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="articleForm.lock_category" class="rounded text-nexo-accent-gold" />
                <span>Lock Category</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="articleForm.lock_topic" class="rounded text-nexo-accent-gold" />
                <span>Lock Topic</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="articleForm.lock_content" class="rounded text-nexo-accent-gold" />
                <span>Lock Content</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="articleForm.lock_image" class="rounded text-nexo-accent-gold" />
                <span>Lock Image</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="articleForm.lock_tags" class="rounded text-nexo-accent-gold" />
                <span>Lock Tags</span>
              </label>
            </div>
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isCreateModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Create & Publish</Button>
          </div>
        </form>
      </Modal>

      <!-- Edit Modal -->
      <Modal :is-open="isEditModalOpen" title="Edit Article & Override Locks" max-width="lg" @close="isEditModalOpen = false">
        <form @submit.prevent="handleUpdateArticle" class="space-y-4 text-xs text-left">
          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Headline Title</label>
            <input v-model="editForm.title" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Status</label>
              <select v-model="editForm.status" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="pending_review">Pending Review</option>
                <option value="archived">Archived</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Verification</label>
              <select v-model="editForm.verification_status" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary">
                <option value="confirmed">Confirmed</option>
                <option value="developing">Developing</option>
                <option value="disputed">Disputed</option>
                <option value="unverified">Unverified</option>
              </select>
            </div>
            <div>
              <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Importance (1-10)</label>
              <input v-model="editForm.importance_score" type="number" min="1" max="10" required class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary" />
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Content Body</label>
            <textarea v-model="editForm.raw_content" rows="6" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary font-mono"></textarea>
          </div>

          <!-- 5 Override Locks -->
          <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-200/80 space-y-2">
            <span class="font-mono text-[10px] uppercase text-amber-900 font-bold block">
              🛡️ Manual Editorial Override Locks
            </span>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="editForm.lock_category" class="rounded text-nexo-accent-gold" />
                <span>Lock Category</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="editForm.lock_topic" class="rounded text-nexo-accent-gold" />
                <span>Lock Topic</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="editForm.lock_content" class="rounded text-nexo-accent-gold" />
                <span>Lock Content</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="editForm.lock_image" class="rounded text-nexo-accent-gold" />
                <span>Lock Image</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="editForm.lock_tags" class="rounded text-nexo-accent-gold" />
                <span>Lock Tags</span>
              </label>
            </div>
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isEditModalOpen = false">Cancel</Button>
            <Button variant="primary" size="sm" type="submit" class="bg-nexo-accent-gold hover:bg-[#8e6e18]">Save Changes & Record Revision</Button>
          </div>
        </form>
      </Modal>

      <!-- Revisions History Modal -->
      <Modal :is-open="isRevisionsModalOpen" title="Article Revisions History" max-width="md" @close="isRevisionsModalOpen = false">
        <div class="space-y-4 text-xs text-left">
          <p class="text-nexo-text-secondary">
            Each editorial change automatically creates an immutable revision record. You can restore any revision with 1 click.
          </p>

          <div class="divide-y divide-nexo-border space-y-2 max-h-96 overflow-y-auto">
            <div v-for="rev in revisionsList" :key="rev.id" class="pt-3 pb-2 flex items-center justify-between">
              <div>
                <div class="font-semibold text-nexo-text-primary">Revision #{{ rev.revision_number }}</div>
                <div class="text-[10px] text-nexo-text-secondary mt-0.5">{{ rev.change_summary || 'Editorial update' }}</div>
                <div class="text-[9px] font-mono text-nexo-text-muted mt-0.5">
                  Edited by {{ rev.editor?.name || 'Administrator' }} on {{ new Date(rev.created_at).toLocaleString() }}
                </div>
              </div>

              <Button variant="secondary" size="sm" @click="handleRestoreRevision(rev.id)">
                Restore ↺
              </Button>
            </div>

            <div v-if="revisionsList.length === 0" class="py-6 text-center text-nexo-text-muted font-mono">
              No previous revisions recorded for this article.
            </div>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>
