<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  ArrowLeft,
  Bookmark,
  BookOpen,
  CheckCircle2,
  ExternalLink,
  Share2,
  ShieldCheck,
} from 'lucide-vue-next'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import NewsletterSubscribe from '@/components/common/NewsletterSubscribe.vue'
import MoreCoverageModal from '@/components/common/MoreCoverageModal.vue'
import { feedService } from '@/services/api/feed.service'

const route = useRoute()
const article = ref<any>(null)
const isLoading = ref(true)
const isCoverageOpen = ref(false)
const isSaved = ref(false)
const toastMessage = ref('')

const fetchDetail = async () => {
  isLoading.value = true
  try {
    const slug = route.params.slug as string
    const res = await feedService.getArticleBySlug(slug)
    article.value = res.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const showToast = (msg: string) => {
  toastMessage.value = msg
  setTimeout(() => (toastMessage.value = ''), 3000)
}

const toggleSave = async () => {
  if (!article.value) return
  try {
    const action = isSaved.value ? 'unsave' : 'save'
    const res = await feedService.recordInteraction(article.value.id, action)
    isSaved.value = res.is_saved
    showToast(res.message)
  } catch {
    showToast('Please sign in to save stories.')
  }
}

const handleShare = () => {
  navigator.clipboard.writeText(window.location.href)
  showToast('Article URL copied to clipboard.')
}

const supportingSources = [
  {
    publisher: 'SEC EDGAR Disclosures',
    original_headline: 'Form 8-K Regulatory Filing for Capital Allocation',
    published_at: '2h ago',
    url: 'https://sec.gov',
    coverage_type: 'Regulatory',
    adds_new_info: true,
    description: 'Primary authorized filing with audited balance sheet allocation and state aid disclosures.',
  },
  {
    publisher: 'Reuters Technology Wire',
    original_headline: 'Nvidia approves long-term silicon packaging plan across Germany and France',
    published_at: '1h ago',
    url: 'https://reuters.com',
    coverage_type: 'Wire',
    adds_new_info: false,
    description: 'Independent cross-confirmation citing diplomatic and supply chain procurement sources.',
  },
]

const storyTimeline = [
  { time: '07:30 UTC', event: 'Board Approval', desc: 'Board of Directors authorizes $15B multi-year capital commitment.', source: 'SEC Filing' },
  { time: '08:12 UTC', event: 'Official SEC 8-K Lodged', desc: 'Normalized filing published to EDGAR corporate repository.', source: 'SEC EDGAR' },
  { time: '08:45 UTC', event: 'Diplomatic Briefing', desc: 'European Commission DG Internal Market acknowledges compliance with Chips Act.', source: 'EU Briefing' },
  { time: '10:00 UTC', event: 'Supply Chain Assessment', desc: 'ASML and TSMC project integration timeline online Q1 FY28.', source: 'Industry Intelligence' },
]

onMounted(() => {
  fetchDetail()
})
</script>

<template>
  <ThreeColumnLayout>
    <div class="max-w-4xl mx-auto space-y-8 text-left relative">
      <!-- Toast Alert -->
      <div
        v-if="toastMessage"
        class="fixed bottom-6 right-6 bg-nexo-surface border border-nexo-accent-cyan text-nexo-accent-cyan px-4 py-2 rounded shadow-2xl text-xs font-mono z-50 animate-fade-in"
      >
        {{ toastMessage }}
      </div>

      <!-- Navigation & Action Bar -->
      <div class="flex items-center justify-between pb-4 border-b border-nexo-border/80 text-xs">
        <RouterLink to="/feed" class="inline-flex items-center gap-1.5 font-medium text-nexo-accent-cyan hover:text-nexo-accent-cyan-hover transition-colors">
          <ArrowLeft class="h-4 w-4" />
          <span>Back to Personalized Feed</span>
        </RouterLink>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="toggleSave"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-nexo-elevated border border-nexo-border text-nexo-text-primary transition-all text-xs font-medium shadow-sm"
          >
            <Bookmark :class="['h-3.5 w-3.5', isSaved ? 'fill-nexo-accent-cyan text-nexo-accent-cyan' : 'text-nexo-text-muted']" />
            <span>{{ isSaved ? 'Saved' : 'Save Story' }}</span>
          </button>
          <button
            type="button"
            @click="handleShare"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-nexo-elevated border border-nexo-border text-nexo-text-primary transition-all text-xs font-medium shadow-sm"
          >
            <Share2 class="h-3.5 w-3.5 text-nexo-text-muted" />
            <span>Share</span>
          </button>
          <button
            type="button"
            @click="isCoverageOpen = true"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100/70 border border-emerald-200 text-nexo-accent-cyan transition-all text-xs font-semibold shadow-sm"
          >
            <BookOpen class="h-3.5 w-3.5 text-nexo-accent-cyan" />
            <span>More Coverage (2 Sources)</span>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="space-y-6">
        <Skeleton class="h-6 w-1/4" />
        <Skeleton class="h-10 w-3/4" />
        <Skeleton class="h-32 w-full" />
      </div>

      <div v-else-if="!article" class="text-center py-16 bg-nexo-card border border-nexo-border rounded-lg p-8">
        <h3 class="text-base font-semibold text-nexo-text-primary">Story Not Found</h3>
        <p class="text-xs text-nexo-text-secondary mt-1">This publication may have been updated or clustered into another event.</p>
      </div>

      <!-- Article Presentation -->
      <article v-else class="space-y-8">
        <!-- Header -->
        <header class="space-y-3">
          <div class="flex items-center space-x-3 flex-wrap gap-2 text-xs font-mono">
            <Badge :variant="article.verification_status" size="md" />
            <span class="text-nexo-text-muted">
              Source: {{ article.source?.name }} (Provenance Score: {{ article.source?.credibility_score * 100 }}%)
            </span>
          </div>

          <h1 class="editorial-title text-3xl sm:text-4xl lg:text-5xl font-medium text-nexo-text-primary leading-tight">
            {{ article.title }}
          </h1>

          <div class="flex items-center space-x-4 text-xs font-mono text-nexo-text-secondary pt-1 flex-wrap gap-2">
            <span>Published: {{ new Date(article.published_at).toLocaleString() }}</span>
            <span>•</span>
            <span class="text-nexo-accent-cyan">Importance Rating: {{ article.importance_score }}/10</span>
            <span v-if="article.canonical_url">•</span>
            <a
              v-if="article.canonical_url"
              :href="article.canonical_url"
              target="_blank"
              rel="noopener noreferrer"
              class="text-nexo-accent-gold hover:underline"
            >
              Official Disclosure Filing ↗
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

            <div v-if="article.summary.key_takeaways" class="pt-3 border-t border-nexo-border/60">
              <ul class="space-y-2.5 text-xs text-nexo-text-secondary">
                <li
                  v-for="(t, idx) in article.summary.key_takeaways"
                  :key="idx"
                  class="flex items-start gap-2.5"
                >
                  <span class="mt-1 h-1.5 w-1.5 rounded-full bg-nexo-accent-cyan shrink-0"></span>
                  <span class="leading-relaxed">{{ t }}</span>
                </li>
              </ul>
            </div>
          </div>
        </Card>

        <!-- Normalized Core Story -->
        <div class="editorial-body text-base text-nexo-text-secondary leading-relaxed space-y-4">
          <p>{{ article.content_normalized || article.content_raw }}</p>
        </div>

        <!-- The 10 Dimensions of Executive Assessment (PART 5.1) -->
        <section v-if="article.assessment" class="space-y-6 pt-6 border-t border-nexo-border/80">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
              <h2 class="editorial-title text-2xl font-medium text-nexo-text-primary">
                10-Dimension Strategic Evaluation
              </h2>
              <p class="text-xs text-nexo-text-secondary mt-1">
                Audited multi-vector executive analysis for corporate boards and strategy committees.
              </p>
            </div>
            <div class="bg-nexo-surface border border-nexo-border rounded px-3 py-1 text-xs font-mono text-nexo-accent-cyan">
              Confidence Rating: {{ article.assessment.confidence_score * 100 }}%
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-cyan uppercase mb-1">1. What Happened?</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.what_happened }}</p>
            </Card>

            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-cyan uppercase mb-1">2. When Did It Happen?</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ new Date(article.assessment.when_happened).toUTCString() }}</p>
            </Card>

            <Card class="p-5 border border-nexo-border md:col-span-2">
              <div class="text-[11px] font-mono text-nexo-accent-cyan uppercase mb-1">3. Why Did It Happen? (Root Cause)</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.why_happened }}</p>
            </Card>

            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-gold uppercase mb-1">4. Business & Financial Significance</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.business_significance }}</p>
            </Card>

            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-accent-gold uppercase mb-1">5. Who Is Affected? (Supply Chain & Competitors)</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.who_affected }}</p>
            </Card>

            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-status-disputed uppercase mb-1">6. Potential Consequences & Second-Order Risks</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.potential_consequences }}</p>
            </Card>

            <Card class="p-5 border border-nexo-border">
              <div class="text-[11px] font-mono text-nexo-status-confirmed uppercase mb-1">7. Forward Monitoring Triggers</div>
              <p class="text-xs text-nexo-text-primary leading-relaxed">{{ article.assessment.what_to_monitor_next }}</p>
            </Card>
          </div>
        </section>

        <!-- Chronological Story Timeline (PART 5.4) -->
        <section class="space-y-4 pt-6 border-t border-nexo-border/80">
          <div class="flex items-center justify-between">
            <h3 class="editorial-title text-xl font-medium text-nexo-text-primary">Event Chronology & Timeline</h3>
            <span class="text-xs font-mono text-nexo-text-muted">Chronological Sequencing</span>
          </div>

          <div class="relative pl-6 space-y-6 border-l-2 border-nexo-border/60 ml-2">
            <div
              v-for="(ev, idx) in storyTimeline"
              :key="idx"
              class="relative space-y-1 text-xs"
            >
              <!-- Timeline node dot -->
              <span class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full bg-nexo-card border-2 border-nexo-accent-cyan"></span>

              <div class="flex items-center space-x-2 text-[10px] font-mono">
                <span class="text-nexo-accent-cyan font-bold">{{ ev.time }}</span>
                <span class="text-nexo-text-muted">•</span>
                <span class="text-nexo-text-muted">{{ ev.source }}</span>
              </div>

              <div class="font-serif font-semibold text-nexo-text-primary text-sm">{{ ev.event }}</div>
              <p class="text-nexo-text-secondary leading-relaxed">{{ ev.desc }}</p>
            </div>
          </div>
        </section>

        <!-- Entities Involved -->
        <section v-if="article.companies && article.companies.length > 0" class="space-y-4 pt-6 border-t border-nexo-border/80">
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
                <span class="text-nexo-text-muted font-mono">Executive Leadership: </span>
                <span class="text-nexo-text-primary font-medium">{{ c.executives.map((e: any) => e.full_name + ' (' + e.current_title + ')').join(', ') }}</span>
              </div>
            </Card>
          </div>
        </section>

        <!-- Newsletter & Offer Box -->
        <NewsletterSubscribe
          source="story_detail_bottom"
          :title="`Stay in Tune — Developments on ${article.companies?.[0]?.name || 'Global Companies'}`"
          description="Never miss second-order developments, municipal site selections, or subsidy grant approvals. Claim an instant 30% discount on annual executive access."
        />
      </article>

      <!-- Expandable More Coverage Modal -->
      <MoreCoverageModal
        :is-open="isCoverageOpen"
        :story-title="article?.title || ''"
        :sources="supportingSources"
        @close="isCoverageOpen = false"
      />
    </div>
  </ThreeColumnLayout>
</template>
