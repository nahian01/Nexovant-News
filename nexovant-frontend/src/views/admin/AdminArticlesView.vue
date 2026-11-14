<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Table from '@/components/ui/Table.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import { feedService } from '@/services/api/feed.service'
import { adminService } from '@/services/api/admin.service'

const articles = ref<any[]>([])
const isLoading = ref(true)
const statusMessage = ref('')

const headers = ['Headline & Provenance', 'Category', 'Status', 'Importance', 'Publication Date', 'Actions']

const fetchArticles = async () => {
  isLoading.value = true
  try {
    const res = await feedService.getArticles()
    articles.value = res.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleReview = async (id: string, action: 'approve' | 'reject') => {
  try {
    const res = await adminService.reviewArticle(id, action)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    fetchArticles()
  } catch {
    //
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
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Editorial Article & Dispatch Management
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Review AI-synthesized dispatches, approve 10-dimension assessments, or unpublish stories.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchArticles">
          Refresh List
        </Button>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- Articles Table -->
      <Table :headers="headers" :is-empty="articles.length === 0">
        <tr v-for="a in articles" :key="a.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary max-w-md">
            <RouterLink :to="`/news/${a.slug}`" class="hover:text-nexo-accent-cyan transition-colors">
              {{ a.title }}
            </RouterLink>
            <div class="flex items-center space-x-2 text-[10px] font-mono text-nexo-text-muted mt-1">
              <Badge :variant="a.verification_status" size="sm" />
              <span>{{ a.source?.name }} ({{ a.source?.credibility_score * 100 }}%)</span>
            </div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-accent-cyan">
            {{ a.categories?.[0]?.name || 'Capital Expansion' }}
          </td>
          <td class="py-3.5 px-4 font-mono">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] uppercase font-bold',
                a.is_editorial_approved ? 'bg-nexo-status-confirmed/20 text-nexo-status-confirmed' : 'bg-nexo-accent-gold/20 text-nexo-accent-gold'
              ]"
            >
              {{ a.is_editorial_approved ? 'Published' : 'Pending Review' }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary font-bold">
            {{ a.importance_score }}/10
          </td>
          <td class="py-3.5 px-4 font-mono text-[11px] text-nexo-text-muted">
            {{ new Date(a.published_at).toLocaleDateString() }}
          </td>
          <td class="py-3.5 px-4 font-mono">
            <div class="flex items-center space-x-2">
              <RouterLink :to="`/news/${a.slug}`" class="px-2 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated text-xs text-nexo-text-primary border border-nexo-border">
                Inspect
              </RouterLink>

              <button
                v-if="!a.is_editorial_approved"
                @click="handleReview(a.id, 'approve')"
                class="px-2 py-1 rounded bg-nexo-status-confirmed/20 text-nexo-status-confirmed border border-nexo-status-confirmed/40 text-xs font-bold"
              >
                Approve
              </button>
              <button
                v-else
                @click="handleReview(a.id, 'reject')"
                class="px-2 py-1 rounded bg-nexo-status-disputed/20 text-nexo-status-disputed border border-nexo-status-disputed/40 text-xs"
              >
                Unpublish
              </button>
            </div>
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
