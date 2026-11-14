<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Modal from '@/components/ui/Modal.vue'
import Input from '@/components/ui/Input.vue'
import { searchService } from '@/services/api/search.service'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const router = useRouter()
const query = ref('')
const suggestions = ref<any[]>([])
const results = ref<any>({ articles: [], companies: [], executives: [], trends: [] })
const isLoading = ref(false)
let debounceTimeout: any = null

const handleInput = () => {
  clearTimeout(debounceTimeout)
  if (query.value.length < 2) {
    suggestions.value = []
    results.value = { articles: [], companies: [], executives: [], trends: [] }
    return
  }

  isLoading.value = true
  debounceTimeout = setTimeout(async () => {
    try {
      const [sugRes, searchRes] = await Promise.all([
        searchService.getSuggestions(query.value),
        searchService.search(query.value),
      ])
      suggestions.value = sugRes || []
      results.value = searchRes || { articles: [], companies: [], executives: [], trends: [] }
    } catch {
      //
    } finally {
      isLoading.value = false
    }
  }, 250)
}

const navigateTo = (path: string) => {
  emit('close')
  query.value = ''
  router.push(path)
}

const submitFullSearch = () => {
  if (query.value.trim()) {
    const q = query.value.trim()
    emit('close')
    router.push(`/search?q=${encodeURIComponent(q)}`)
  }
}
</script>

<template>
  <Modal :is-open="isOpen" title="Global Executive Intelligence Search" max-width="2xl" @close="emit('close')">
    <div class="space-y-4">
      <form @submit.prevent="submitFullSearch" class="relative">
        <Input
          v-model="query"
          placeholder="Search global companies, executives, tickers, regulatory filings..."
          @input="handleInput"
          autofocus
        />
        <span class="absolute right-3 top-2.5 text-[10px] font-mono text-nexo-text-muted bg-nexo-surface px-1.5 py-0.5 rounded border border-nexo-border">
          ↵ Enter for all results
        </span>
      </form>

      <!-- Instant Suggestions -->
      <div v-if="suggestions.length > 0" class="space-y-1 pt-1">
        <div class="text-[10px] font-mono uppercase text-nexo-text-muted tracking-wider">
          Suggested Entities & Topics
        </div>
        <div class="space-y-1">
          <div
            v-for="(s, i) in suggestions"
            :key="i"
            @click="navigateTo(s.type === 'article' ? `/news/${s.slug}` : (s.type === 'company' ? `/companies` : `/trends`))"
            class="flex items-center justify-between p-2 rounded hover:bg-nexo-surface cursor-pointer text-xs transition-colors group"
          >
            <span class="text-nexo-text-primary group-hover:text-nexo-accent-cyan font-medium">{{ s.text }}</span>
            <span class="text-[10px] font-mono uppercase text-nexo-text-muted bg-nexo-surface px-2 py-0.5 rounded border border-nexo-border">
              {{ s.type }}
            </span>
          </div>
        </div>
      </div>

      <!-- Quick Articles Results Preview -->
      <div v-if="results.articles.length > 0" class="space-y-2 pt-2 border-t border-nexo-border/60">
        <div class="text-[10px] font-mono uppercase text-nexo-text-muted tracking-wider">
          Matching Intelligence Articles
        </div>
        <div class="space-y-1.5">
          <div
            v-for="a in results.articles.slice(0, 3)"
            :key="a.id"
            @click="navigateTo(`/news/${a.slug}`)"
            class="p-2.5 rounded bg-nexo-surface/40 hover:bg-nexo-surface border border-nexo-border/60 cursor-pointer text-xs transition-colors"
          >
            <div class="font-medium text-nexo-text-primary hover:text-nexo-accent-cyan">{{ a.title }}</div>
            <div class="text-[10px] font-mono text-nexo-text-secondary mt-1 flex items-center space-x-2">
              <span class="text-nexo-status-confirmed">● {{ a.verification_status }}</span>
              <span>•</span>
              <span>{{ a.source?.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="query.length >= 2 && !isLoading && suggestions.length === 0 && results.articles.length === 0" class="text-center py-6 text-xs text-nexo-text-muted font-mono">
        No direct entity matches found for "{{ query }}". Press Enter to perform an exhaustive search.
      </div>
    </div>
  </Modal>
</template>
