<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import { apiClient } from '@/services/api/client'

const route = useRoute()
const article = ref<any>(null)
const isLoading = ref(true)

const fetchArticleDetail = async () => {
  isLoading.value = true
  try {
    const slug = route.params.slug as string
    const res = await apiClient.get(`/articles/${slug}`)
    article.value = res.data.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchArticleDetail()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-8 overflow-y-auto max-w-5xl">
        <!-- Back Navigation -->
        <RouterLink to="/feed" class="inline-flex items-center gap-1.5 text-xs font-medium text-nexo-accent-cyan hover:underline">
          <ArrowLeft class="h-3.5 w-3.5" />
          <span>Back to Intelligence Feed</span>
        </RouterLink>

        <div v-if="article" class="space-y-6">
          <!-- Article Header -->
          <div class="space-y-3 border-b border-nexo-border/60 pb-6">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
              <Badge :variant="article.verification_status" size="md" />
              <span class="text-xs font-mono text-nexo-text-muted">
                Audited Provenance Score: {{ article.source?.credibility_score * 100 }}%
              </span>
            </div>

            <h1 class="editorial-title text-3xl sm:text-4xl font-medium text-nexo-text-primary leading-tight">
              {{ article.title }}
            </h1>

            <div class="flex items-center space-x-4 text-xs font-mono text-nexo-text-secondary pt-1">
              <span>SOURCE: {{ article.source?.name }}</span>
              <span>•</span>
              <span>PUBLISHED: {{ new Date(article.published_at).toUTCString() }}</span>
            </div>
          </div>

          <!-- Remote Featured Image & Source Attribution (Zero local storage) -->
          <div v-if="article.featured_image || article.primary_image || article.hero_image" class="overflow-hidden rounded-[4px] border border-nexo-border/60 bg-nexo-surface">
            <img
              :src="article.featured_image || article.primary_image || article.hero_image"
              :alt="article.title"
              class="w-full max-h-[460px] object-cover bg-black/20"
              loading="lazy"
              referrerpolicy="no-referrer"
              @error="(e: any) => e.target.parentElement.style.display = 'none'"
            />
            <div class="px-3.5 py-2 bg-nexo-surface/90 border-t border-nexo-border/40 text-[11px] font-mono text-nexo-text-muted flex justify-between items-center">
              <span>{{ article.featured_image_caption || (article.source?.name ? `Source: ${article.source.name}` : 'Source: Wire Service') }}</span>
              <span class="text-[10px] text-nexo-accent-cyan/80">Remote Attribution</span>
            </div>
          </div>

          <!-- Executive Summary Callout -->
          <Card padding="md" class="border-nexo-accent-cyan/40 bg-nexo-surface/60">
            <h4 class="font-mono text-xs text-nexo-accent-cyan uppercase font-semibold mb-2">
              Executive Briefing Summary
            </h4>
            <p class="text-sm text-nexo-text-primary leading-relaxed">
              {{ article.summary?.executive_summary }}
            </p>

            <div v-if="article.summary?.key_takeaways" class="mt-4 pt-3 border-t border-nexo-border/60">
              <span class="text-xs font-mono text-nexo-text-muted uppercase block mb-1.5">
                Strategic Takeaways:
              </span>
              <ul class="space-y-1.5 text-xs text-nexo-text-secondary">
                <li v-for="(point, idx) in article.summary.key_takeaways" :key="idx" class="flex items-start space-x-2">
                  <span class="text-nexo-accent-cyan font-bold">•</span>
                  <span>{{ point }}</span>
                </li>
              </ul>
            </div>
          </Card>

          <!-- 10 Core Intelligence Dimensions -->
          <div class="space-y-4">
            <h3 class="editorial-title text-2xl font-medium text-nexo-text-primary">
              The 10 Strategic Intelligence Dimensions
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- 1. What Happened -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-accent-cyan uppercase font-semibold">1. What Happened</span>
                <p class="text-xs text-nexo-text-primary mt-1 leading-relaxed">
                  {{ article.assessment?.what_happened }}
                </p>
              </Card>

              <!-- 2. When Did It Happen -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-accent-cyan uppercase font-semibold">2. Chronology & Timestamp</span>
                <p class="text-xs text-nexo-text-primary mt-1 leading-relaxed">
                  {{ article.assessment?.when_happened }}
                </p>
              </Card>

              <!-- 3. Entities Involved -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-accent-cyan uppercase font-semibold">3. Entities Involved</span>
                <div class="flex flex-wrap gap-1 mt-1.5">
                  <span
                    v-for="co in article.companies"
                    :key="co.id"
                    class="px-2 py-0.5 rounded bg-nexo-elevated text-xs text-nexo-text-primary border border-nexo-border"
                  >
                    {{ co.name }} ({{ co.ticker }})
                  </span>
                </div>
              </Card>

              <!-- 4. Why Did It Happen -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-accent-cyan uppercase font-semibold">4. Root Cause Established</span>
                <p class="text-xs text-nexo-text-primary mt-1 leading-relaxed">
                  {{ article.assessment?.why_happened }}
                </p>
              </Card>

              <!-- 5. Business Significance -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-accent-gold uppercase font-semibold">5. Business Significance</span>
                <p class="text-xs text-nexo-text-primary mt-1 leading-relaxed">
                  {{ article.assessment?.business_significance }}
                </p>
              </Card>

              <!-- 6. Who May Be Affected -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-accent-gold uppercase font-semibold">6. Affected Ecosystem</span>
                <p class="text-xs text-nexo-text-primary mt-1 leading-relaxed">
                  {{ article.assessment?.who_affected }}
                </p>
              </Card>

              <!-- 7. Potential Consequences -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-status-warning uppercase font-semibold">7. Potential Consequences</span>
                <p class="text-xs text-nexo-text-primary mt-1 leading-relaxed">
                  {{ article.assessment?.potential_consequences }}
                </p>
              </Card>

              <!-- 8. Verification Status -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-emerald-400 uppercase font-semibold">8. Audited Verification</span>
                <div class="mt-1 flex items-center space-x-2">
                  <Badge :variant="article.verification_status" size="sm" />
                  <span class="text-xs text-nexo-text-secondary">Corroborated by primary disclosures</span>
                </div>
              </Card>

              <!-- 9. Related Previous Events -->
              <Card padding="sm">
                <span class="font-mono text-[10px] text-nexo-text-secondary uppercase font-semibold">9. Prior Historical Context</span>
                <p class="text-xs text-nexo-text-secondary mt-1 leading-relaxed">
                  Prior strategic filings in Q2 indicated preparatory capital reallocation towards sovereign regional clusters.
                </p>
              </Card>

              <!-- 10. What To Monitor Next -->
              <Card padding="sm" class="border-nexo-accent-cyan/50">
                <span class="font-mono text-[10px] text-nexo-accent-cyan uppercase font-semibold">10. What to Monitor Next</span>
                <p class="text-xs text-nexo-text-primary font-medium mt-1 leading-relaxed">
                  {{ article.assessment?.what_to_monitor_next }}
                </p>
              </Card>
            </div>
          </div>
        </div>

        <div v-else-if="!isLoading" class="p-12 text-center text-nexo-text-secondary">
          Article could not be retrieved.
        </div>
      </main>
    </div>
  </div>
</template>
