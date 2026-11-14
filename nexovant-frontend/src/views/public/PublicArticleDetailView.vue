<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowLeft, ExternalLink } from 'lucide-vue-next'
import PublicNavbar from '@/components/layout/PublicNavbar.vue'
import NewsletterSubscribe from '@/components/common/NewsletterSubscribe.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
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
  <div class="min-h-screen bg-nexo-bg flex flex-col text-left">
    <PublicNavbar />

    <main class="flex-1 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full space-y-10">
      <!-- Back Link -->
      <RouterLink
        to="/blog"
        class="inline-flex items-center gap-1.5 text-xs font-medium text-nexo-accent-cyan hover:underline"
      >
        <ArrowLeft class="h-3.5 w-3.5" />
        <span>Back to Executive Blog & News</span>
      </RouterLink>

      <div v-if="isLoading" class="space-y-6">
        <Skeleton class="h-6 w-1/4" />
        <Skeleton class="h-10 w-3/4" />
        <Skeleton class="h-40 w-full" />
      </div>

      <div v-else-if="!article" class="text-center py-16 bg-nexo-card border border-nexo-border rounded-lg p-8">
        <h3 class="text-base font-semibold text-nexo-text-primary">Intelligence Article Not Found</h3>
        <p class="text-xs text-nexo-text-secondary mt-1">The requested publication may have been archived or updated.</p>
        <RouterLink to="/blog" class="mt-4 inline-block">
          <Button variant="secondary" size="sm">Browse Public Feed</Button>
        </RouterLink>
      </div>

      <article v-else class="space-y-10">
        <!-- Article Header -->
        <header class="space-y-4 border-b border-nexo-border/80 pb-8">
          <div class="flex items-center space-x-3 flex-wrap gap-2">
            <Badge :variant="article.verification_status" size="md" />
            <span class="text-xs font-mono text-nexo-text-muted">
              Source: {{ article.source?.name }} (Provenance: {{ article.source?.credibility_score * 100 }}%)
            </span>
          </div>

          <h1 class="editorial-title text-3xl sm:text-4xl lg:text-5xl font-medium text-nexo-text-primary leading-tight">
            {{ article.title }}
          </h1>

          <div class="flex items-center space-x-4 text-xs font-mono text-nexo-text-secondary pt-1 flex-wrap gap-2">
            <span>Published: {{ new Date(article.published_at).toLocaleString() }}</span>
            <span>•</span>
            <span class="text-nexo-accent-cyan">Importance Index: {{ article.importance_score }}/10</span>
            <span v-if="article.canonical_url">•</span>
            <a
              v-if="article.canonical_url"
              :href="article.canonical_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1 text-nexo-accent-gold hover:underline"
            >
              <span>Official Disclosure Filing</span>
              <ExternalLink class="h-3 w-3" />
            </a>
          </div>
        </header>

        <!-- Executive Summary & Key Takeaways Card -->
        <Card v-if="article.summary" class="p-6 bg-gradient-to-r from-nexo-card to-nexo-surface border-l-4 border-l-nexo-accent-cyan">
          <div class="space-y-4">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-mono font-bold text-nexo-accent-cyan uppercase tracking-wider">
                EXECUTIVE SYNTHESIS & CORE TAKEAWAYS
              </span>
            </div>

            <p class="text-sm sm:text-base text-nexo-text-primary leading-relaxed font-serif">
              {{ article.summary.executive_summary }}
            </p>

            <div v-if="article.summary.key_takeaways" class="pt-2 border-t border-nexo-border/60">
              <ul class="space-y-2 text-xs text-nexo-text-secondary">
                <li
                  v-for="(t, idx) in article.summary.key_takeaways"
                  :key="idx"
                  class="flex items-start gap-2"
                >
                  <span class="h-1.5 w-1.5 rounded-full bg-nexo-accent-cyan mt-1.5 shrink-0"></span>
                  <span>{{ t }}</span>
                </li>
              </ul>
            </div>
          </div>
        </Card>

        <!-- Normalized Core Story Content -->
        <div class="editorial-body text-base text-nexo-text-secondary leading-relaxed space-y-4">
          <p>{{ article.content_normalized || article.content_raw }}</p>
        </div>

        <!-- The 10 Dimensions of Executive Assessment -->
        <section v-if="article.assessment" class="space-y-6 pt-6 border-t border-nexo-border/80">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
              <h2 class="editorial-title text-2xl font-medium text-nexo-text-primary">
                10-Dimension Executive Intelligence Breakdown
              </h2>
              <p class="text-xs text-nexo-text-secondary mt-1">
                Audited multi-vector evaluation for corporate strategy and investment committees.
              </p>
            </div>
            <div class="bg-nexo-surface border border-nexo-border rounded px-3 py-1 text-xs font-mono text-nexo-accent-cyan">
              Confidence Rating: {{ article.assessment.confidence_score * 100 }}%
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- 1. What Happened -->
            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-cyan uppercase mb-1">1. What Happened?</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.what_happened }}</p>
            </Card>

            <!-- 2. When Happened -->
            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-cyan uppercase mb-1">2. When Did It Happen?</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ new Date(article.assessment.when_happened).toUTCString() }}</p>
            </Card>

            <!-- 3. Why Happened -->
            <Card class="p-5 border border-nexo-border md:col-span-2">
              <div class="text-[11px] font-mono text-nexo-accent-cyan uppercase mb-1">3. Why Did It Happen? (Root Cause)</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.why_happened }}</p>
            </Card>

            <!-- 4. Business Significance -->
            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-gold uppercase mb-1">4. Business & Financial Significance</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.business_significance }}</p>
            </Card>

            <!-- 5. Who Affected -->
            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-gold uppercase mb-1">5. Who Is Affected? (Supply Chain & Competitors)</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.who_affected }}</p>
            </Card>

            <!-- 6. Potential Consequences -->
            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-status-disputed uppercase mb-1">6. Potential Consequences & Second-Order Risks</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.potential_consequences }}</p>
            </Card>

            <!-- 7. What to Monitor Next -->
            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-status-confirmed uppercase mb-1">7. Forward Monitoring Triggers</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.what_to_monitor_next }}</p>
            </Card>
          </div>
        </section>

        <!-- Entities Involved Dossier -->
        <section v-if="article.companies && article.companies.length > 0" class="space-y-4 pt-4 border-t border-nexo-border/60">
          <h3 class="editorial-title text-xl font-medium text-nexo-text-primary">Entities Involved</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Card
              v-for="c in article.companies"
              :key="c.id"
              class="p-5 bg-nexo-surface border border-nexo-border"
            >
              <div class="flex items-center justify-between mb-2">
                <span class="font-semibold text-nexo-text-primary text-sm">{{ c.name }}</span>
                <span class="text-xs font-mono text-nexo-accent-cyan bg-nexo-card px-2 py-0.5 rounded border border-nexo-border">
                  {{ c.ticker }}
                </span>
              </div>
              <p class="text-xs text-nexo-text-secondary leading-relaxed mb-3">{{ c.summary }}</p>
              <div v-if="c.executives && c.executives.length > 0" class="pt-2 border-t border-nexo-border/40 text-xs">
                <span class="text-nexo-text-muted font-mono">Leadership: </span>
                <span class="text-nexo-text-primary font-medium">{{ c.executives.map((e: any) => e.full_name + ' (' + e.current_title + ')').join(', ') }}</span>
              </div>
            </Card>
          </div>
        </section>

        <!-- Newsletter Subscription & Special Offer Callout -->
        <NewsletterSubscribe
          source="article_bottom"
          :title="`Stay in Tune — Updates on ${article.companies?.[0]?.name || 'Global Business Intelligence'}`"
          description="Enter your email to receive morning executive digests and stay informed on subsequent developments. Claim an instant 30% discount on annual executive access."
        />

        <!-- Exclusive Offer Card -->
        <div class="bg-gradient-to-r from-nexo-surface via-nexo-card to-nexo-surface border border-nexo-accent-gold/40 rounded-lg p-6 lg:p-8 flex flex-col sm:flex-row items-center justify-between gap-6">
          <div class="space-y-1">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-mono font-bold text-nexo-accent-gold uppercase tracking-wider">
                EXECUTIVE INTELLIGENCE SPECIAL OFFER
              </span>
              <span class="bg-nexo-accent-gold/20 text-nexo-accent-gold px-2 py-0.5 rounded text-[10px] font-mono font-bold">
                CODE: NEXO-EXECUTIVE-30
              </span>
            </div>
            <h4 class="editorial-title text-xl font-semibold text-nexo-text-primary">
              Unlock Full Cognitive Q&A, PDF Briefings & Competitor Radar
            </h4>
            <p class="text-xs text-nexo-text-secondary max-w-xl">
              Equip your leadership desk with AI-routed executive briefings, automated daily PDF publications, and custom portfolio monitoring.
            </p>
          </div>

          <RouterLink to="/register?offer=NEXO-EXECUTIVE-30">
            <Button variant="primary" size="lg" class="whitespace-nowrap">
              Take Offer & Sign Up
            </Button>
          </RouterLink>
        </div>
      </article>
    </main>

    <!-- Public Footer -->
    <footer class="border-t border-nexo-border/60 bg-nexo-card py-10 px-4 sm:px-6 lg:px-8 text-center sm:text-left">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-mono text-nexo-text-muted">
        <div>
          <span class="text-nexo-text-primary font-serif font-bold">NEXOVANT</span> — Global Executive Intelligence SaaS.
        </div>
        <div class="flex items-center space-x-6">
          <RouterLink to="/blog" class="hover:text-nexo-accent-cyan transition-colors">Public Blog & News</RouterLink>
          <RouterLink to="/#pricing" class="hover:text-nexo-accent-cyan transition-colors">Pricing</RouterLink>
          <RouterLink to="/login" class="hover:text-nexo-accent-cyan transition-colors">Sign In</RouterLink>
          <RouterLink to="/register" class="hover:text-nexo-accent-cyan transition-colors">Sign Up</RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>
