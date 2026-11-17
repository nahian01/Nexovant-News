<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowDownRight,
  ArrowRight,
  ArrowUpRight,
  Check,
  Compass,
  FileCheck2,
  Globe2,
  Layers3,
  ShieldCheck,
  Sparkles,
  Target,
  X,
  Database,
  Network,
  Cpu,
  Send,
} from 'lucide-vue-next'
import PublicNavbar from '@/components/layout/PublicNavbar.vue'

const activePipelineStep = ref(0)
const pipelineSteps = [
  {
    title: '1. Ingestion & Provenance',
    icon: Database,
    desc: 'Authorized scrapers, official regulatory disclosure feeds, XML circulars, and primary wires with automated SSRF/XXE defense and signature verification.'
  },
  {
    title: '2. Entity Resolution',
    icon: Network,
    desc: 'Automated 3-hop graph linkage across public & private companies, subsidiaries, boards of directors, and regulatory bodies.'
  },
  {
    title: '3. Strategic Assessment',
    icon: Cpu,
    desc: 'Structured 10-dimension evaluation: root causes, capital allocations, affected competitors, and forward monitoring triggers.'
  },
  {
    title: '4. Delivery & Feeds',
    icon: Send,
    desc: 'Personalized real-time feeds, customizable watchlists, automated monitor alerts, and daily PDF executive briefings.'
  }
]

const intelligenceDimensions = [
  { q: 'What happened?', desc: 'A factual, normalized account of the event.' },
  { q: 'When did it happen?', desc: 'A precise timeline and event sequence.' },
  { q: 'Who is involved?', desc: 'Companies, executives, regulators and boards.' },
  { q: 'Why did it happen?', desc: 'The drivers behind the development.' },
  { q: 'Why does it matter?', desc: 'Business, capital and market significance.' },
  { q: 'Who may be affected?', desc: 'Customers, competitors and supply chains.' },
  { q: 'What could follow?', desc: 'Potential consequences and second order effects.' },
  { q: 'How certain is it?', desc: 'A clear, auditable verification status.' },
  { q: 'What came before?', desc: 'Related events and useful historical context.' },
  { q: 'What should you watch?', desc: 'The signals and milestones coming next.' },
]

const comparisonRows = [
  {
    feature: 'Primary Document Provenance',
    nexovant: 'Audited filings, SEC 8-K, central bank circulars & wire cross-checking',
    traditional: 'Republished quotes without source verification metadata',
    aggregators: 'Raw unparsed RSS feeds with high duplication'
  },
  {
    feature: '10-Dimension Strategic Impact',
    nexovant: 'Native structured analysis of root causes, stakeholders & next triggers',
    traditional: 'Editorial opinion column with variable depth',
    aggregators: 'Headlines only, no strategic synthesis'
  },
  {
    feature: 'Entity Knowledge Graph',
    nexovant: '3-hop relationship graph between firms, subsidiaries & executives',
    traditional: 'Static tagged hyperlinks',
    aggregators: 'Simple keyword search matches'
  },
  {
    feature: 'Evidence Verification Badges',
    nexovant: '5 explicit status grades (Confirmed, Reported, Developing, Disputed, Unverified)',
    traditional: 'Unattributed speculation and anonymous rumors mixed in',
    aggregators: 'No verification layer'
  },
  {
    feature: 'Multilingual Normalization',
    nexovant: 'Native automated cross-language extraction, translation & deduplication',
    traditional: 'Language-siloed publications requiring manual translation',
    aggregators: 'Raw multilingual spam'
  }
]

const plans = [
  {
    name: 'Starter',
    monthly: '$19',
    annual: '$190',
    desc: 'For individual analysts, researchers and founders.',
    features: [
      'Full intelligence feed & real-time updates',
      'Follow up to 25 entities & companies',
      '5 verification labels & source context',
      'Daily email digest'
    ]
  },
  {
    name: 'Professional',
    monthly: '$49',
    annual: '$490',
    desc: 'For strategy desks, investment teams and corporate leaders.',
    popular: true,
    features: [
      'Everything in Starter',
      'Hybrid semantic vector search across 100k+ documents',
      'Social trend radar & cross-platform velocity tracking',
      'Custom automated early-warning monitors',
      'Daily & weekly executive PDF briefings'
    ]
  },
  {
    name: 'Enterprise',
    monthly: '$149',
    annual: '$1,490',
    desc: 'For multinational enterprises, institutions and funds.',
    features: [
      'Everything in Professional',
      'Unlimited entity, executive & topic tracking',
      'Multi-tenant workspace with role-based access & team seats',
      'Priority REST API and automated Webhook endpoints',
      'Dedicated intelligence support & custom source integrations'
    ]
  },
]
</script>

<template>
  <div class="min-h-screen bg-[#F6F4EF] text-left text-[#0E1A2B]">
    <PublicNavbar />

    <main>
      <!-- HERO SECTION (per Mockup P01 Landing page.png) -->
      <section class="relative px-4 pb-16 pt-12 sm:px-6 sm:pb-20 sm:pt-16 lg:px-8">
        <div class="mx-auto max-w-7xl">
          <div class="grid items-center gap-12 lg:grid-cols-12">
            <!-- Left Hero Content -->
            <div class="lg:col-span-6 space-y-6">
              <div class="inline-flex items-center gap-2 rounded-full border border-[#D0D9CF] bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-[#0E1A2B] shadow-xs">
                <span class="h-2 w-2 rounded-full bg-[#17794D] animate-pulse"></span>
                <span>Intelligence Beyond Headlines</span>
              </div>

              <h1 class="font-serif text-[42px] sm:text-[54px] lg:text-[62px] font-medium leading-[1.05] tracking-tight text-[#0E1A2B]">
                See the story <span class="italic text-[#274888]">behind</span> the news.
              </h1>

              <p class="text-base sm:text-lg leading-relaxed text-[#3F4A5A] max-w-xl">
                Nexovant connects verified developments to the companies, people, and decisions that shape your industry. Start with what happened. Leave with the context to act.
              </p>

              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
                <RouterLink to="/register">
                  <button type="button" class="btn-ink text-sm px-6 py-3 flex items-center justify-center gap-2 w-full sm:w-auto">
                    <span>Explore Nexovant free</span>
                    <ArrowRight class="h-4 w-4" />
                  </button>
                </RouterLink>
                <RouterLink to="/">
                  <button type="button" class="px-5 py-3 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-sm font-semibold transition-colors flex items-center justify-center gap-2 w-full sm:w-auto">
                    <span>Browse live intelligence feed</span>
                    <ArrowDownRight class="h-4 w-4 text-[#5F6875]" />
                  </button>
                </RouterLink>
              </div>

              <div class="flex flex-wrap items-center gap-x-6 gap-y-2 pt-4 text-xs font-medium text-[#5F6875]">
                <span class="inline-flex items-center gap-1.5">
                  <ShieldCheck class="h-4 w-4 text-[#17794D]" />
                  <span>Audited source provenance</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <Layers3 class="h-4 w-4 text-[#274888]" />
                  <span>10 strategic dimensions</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <Globe2 class="h-4 w-4 text-[#8A5A08]" />
                  <span>Cross-language coverage</span>
                </span>
              </div>
            </div>

            <!-- Right Hero Card: Executive Intelligence Brief Sample -->
            <div class="lg:col-span-6">
              <div class="card-editorial p-6 sm:p-7 space-y-4 bg-white shadow-sm border border-[#D0D9CF]">
                <div class="flex items-center justify-between border-b border-[#D0D9CF]/60 pb-3">
                  <div class="flex items-center gap-2">
                    <span class="h-7 w-7 rounded-[4px] bg-[#0E1A2B] text-white flex items-center justify-center font-serif text-xs font-bold">N</span>
                    <div>
                      <p class="text-[10px] font-mono uppercase tracking-wider text-[#5F6875]">EXECUTIVE BRIEFING SAMPLE</p>
                      <p class="text-xs font-semibold text-[#0E1A2B]">Cross-Border Capital Infrastructure</p>
                    </div>
                  </div>
                  <span class="badge-verified">CONFIRMED</span>
                </div>

                <div class="space-y-2">
                  <span class="text-[10px] font-mono text-[#8A5A08] uppercase tracking-wider">Capital & Expansion · 5 min read</span>
                  <h3 class="editorial-headline text-2xl font-medium text-[#0E1A2B] leading-snug">
                    Halvorsen Energy secures €420M to build modular offshore interconnector hubs
                  </h3>
                  <p class="text-xs text-[#3F4A5A] leading-relaxed">
                    European Commission energy framework approvals confirmed under Article 4a. Deployment connects Scandinavian wind corridors directly into high-demand industrial centers.
                  </p>
                </div>

                <!-- Structured Dimensions Inside Sample -->
                <div class="p-4 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] space-y-2.5">
                  <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-[#0E1A2B] flex items-center gap-1.5">
                      <Target class="h-3.5 w-3.5 text-[#274888]" />
                      Strategic Read & First-Order Impact
                    </span>
                    <span class="text-[10px] font-mono text-[#17794D] font-bold">96% Provable</span>
                  </div>
                  <p class="text-xs text-[#5F6875] leading-relaxed">
                    Subsea cable suppliers see 28-month forward capacity booked. Regional wholesale electricity spreads projected to narrow by 14% post-commissioning.
                  </p>
                  <div class="flex flex-wrap gap-1.5 pt-1">
                    <span class="text-[10px] px-2 py-0.5 rounded-[2px] bg-white border border-[#D0D9CF] text-[#0E1A2B] font-mono">Halvorsen Energy</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-[2px] bg-white border border-[#D0D9CF] text-[#0E1A2B] font-mono">Tennet TSO</span>
                    <span class="text-[10px] px-2 py-0.5 rounded-[2px] bg-white border border-[#D0D9CF] text-[#0E1A2B] font-mono">HVDC Subsea</span>
                  </div>
                </div>

                <div class="flex items-center justify-between text-xs text-[#5F6875] pt-2 border-t border-[#D0D9CF]/60">
                  <span>Evidence & forward triggers verified</span>
                  <RouterLink to="/" class="text-[#274888] font-semibold hover:underline flex items-center gap-1">
                    <span>Read full story</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                  </RouterLink>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- STATS STRIP -->
      <section class="border-y border-[#D0D9CF] bg-white px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-6 sm:grid-cols-3">
          <div class="flex items-center gap-3">
            <span class="font-serif text-3xl sm:text-4xl font-medium text-[#274888]">10</span>
            <span class="text-xs leading-5 text-[#3F4A5A]">strategic dimensions to understand<br />what matters next</span>
          </div>
          <div class="flex items-center gap-3 sm:border-x sm:border-[#D0D9CF] sm:px-8">
            <span class="font-serif text-3xl sm:text-4xl font-medium text-[#17794D]">5</span>
            <span class="text-xs leading-5 text-[#3F4A5A]">clear verification labels<br />for transparent certainty</span>
          </div>
          <div class="flex items-center gap-3">
            <span class="font-serif text-3xl sm:text-4xl font-medium text-[#8A5A08]">1</span>
            <span class="text-xs leading-5 text-[#3F4A5A]">connected intelligence workspace<br />for your strategy team</span>
          </div>
        </div>
      </section>

      <!-- PROCESS PIPELINE TABS (per Mockup P01 Landing page.png) -->
      <section class="px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-10">
          <div class="max-w-2xl">
            <h3 class="section-label">HOW NEXOVANT WORKS</h3>
            <h2 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B] mt-2">
              From global noise to high-conviction decisions.
            </h2>
            <p class="text-sm text-[#5F6875] mt-2">
              Every incoming article, filing, or market disclosure moves through four rigorous processing gates before reaching your workspace.
            </p>
          </div>

          <!-- Step Switcher Tabs -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <button
              v-for="(step, idx) in pipelineSteps"
              :key="idx"
              type="button"
              @click="activePipelineStep = idx"
              :class="[
                'p-4 rounded-[2px] border text-left transition-colors',
                activePipelineStep === idx
                  ? 'bg-white border-[#0E1A2B] shadow-xs'
                  : 'bg-[#EEEBE4]/60 border-[#D0D9CF] hover:bg-white'
              ]"
            >
              <component :is="step.icon" class="h-5 w-5 mb-2" :class="activePipelineStep === idx ? 'text-[#274888]' : 'text-[#5F6875]'" />
              <div class="font-semibold text-xs text-[#0E1A2B]">{{ step.title }}</div>
            </button>
          </div>

          <!-- Active Step Detail Box -->
          <div class="card-editorial p-6 sm:p-8 bg-white border border-[#D0D9CF] space-y-3">
            <h4 class="editorial-headline text-xl font-medium text-[#0E1A2B]">
              {{ pipelineSteps[activePipelineStep].title }}
            </h4>
            <p class="text-sm text-[#3F4A5A] leading-relaxed max-w-3xl">
              {{ pipelineSteps[activePipelineStep].desc }}
            </p>
          </div>
        </div>
      </section>

      <!-- COMPARISON TABLE: Why Nexovant (per Mockup P01 Landing page.png) -->
      <section class="border-t border-[#D0D9CF] bg-white px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
          <div class="max-w-2xl">
            <h3 class="section-label">THE INTELLIGENCE ADVANTAGE</h3>
            <h2 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B] mt-2">
              Why business leaders choose Nexovant.
            </h2>
            <p class="text-sm text-[#5F6875] mt-2">
              Traditional news outlets optimize for pageviews and sensationalism. Raw RSS aggregators inundate you with noise. Nexovant delivers evidence-backed executive clarity.
            </p>
          </div>

          <div class="card-editorial overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[700px]">
              <thead class="bg-[#F8FAF7] border-b border-[#D0D9CF] text-[#5F6875] uppercase font-mono text-[11px]">
                <tr>
                  <th class="py-3.5 px-5 w-1/4">Capability / Dimension</th>
                  <th class="py-3.5 px-5 w-1/3 bg-[#E4E9F7]/40 text-[#274888] font-bold">Nexovant</th>
                  <th class="py-3.5 px-5 w-1/5">Traditional News</th>
                  <th class="py-3.5 px-5 w-1/5">Raw Aggregators</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#D0D9CF]/70 text-[#3F4A5A]">
                <tr v-for="(row, idx) in comparisonRows" :key="idx" class="hover:bg-[#F8FAF7]/50">
                  <td class="py-4 px-5 font-semibold text-[#0E1A2B] align-top">
                    {{ row.feature }}
                  </td>
                  <td class="py-4 px-5 bg-[#E4E9F7]/20 font-medium text-[#0E1A2B] align-top">
                    <div class="flex items-start gap-2">
                      <Check class="h-4 w-4 text-[#17794D] shrink-0 mt-0.5" />
                      <span>{{ row.nexovant }}</span>
                    </div>
                  </td>
                  <td class="py-4 px-5 text-[#5F6875] align-top">
                    <div class="flex items-start gap-2">
                      <X class="h-4 w-4 text-[#B3261E] shrink-0 mt-0.5" />
                      <span>{{ row.traditional }}</span>
                    </div>
                  </td>
                  <td class="py-4 px-5 text-[#5F6875] align-top">
                    <div class="flex items-start gap-2">
                      <X class="h-4 w-4 text-[#B3261E] shrink-0 mt-0.5" />
                      <span>{{ row.aggregators }}</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- 10 QUESTIONS METHOD -->
      <section class="border-t border-[#D0D9CF] px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-12 lg:gap-16">
          <div class="lg:col-span-4 space-y-4">
            <h3 class="section-label">THE 10 DIMENSIONS</h3>
            <h2 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
              Ten questions. Complete situational awareness.
            </h2>
            <p class="text-sm text-[#5F6875] leading-relaxed">
              Headlines notify you that an event occurred. Nexovant's structured 10-dimension assessment connects the event directly to capital, regulation, and second-order industry repercussions.
            </p>
            <RouterLink to="/" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#274888] hover:underline pt-2">
              <span>Explore live stories</span>
              <ArrowRight class="h-3.5 w-3.5" />
            </RouterLink>
          </div>

          <div class="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:col-span-8">
            <div
              v-for="(dim, index) in intelligenceDimensions"
              :key="dim.q"
              class="flex gap-3 border-b border-[#D0D9CF] py-3.5"
            >
              <span class="font-mono text-xs font-bold text-[#274888]">{{ String(index + 1).padStart(2, '0') }}</span>
              <div>
                <h4 class="text-xs font-semibold text-[#0E1A2B]">{{ dim.q }}</h4>
                <p class="mt-0.5 text-xs text-[#5F6875]">{{ dim.desc }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- PLANS & PRICING TEASER (per Mockup P14 Pricing and plans.png) -->
      <section id="pricing" class="border-t border-[#D0D9CF] bg-white px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-10 text-center">
          <div class="max-w-2xl mx-auto space-y-2">
            <h3 class="section-label">TRANSPARENT PRICING</h3>
            <h2 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
              Clarity for every scale of intelligence operation.
            </h2>
            <p class="text-sm text-[#5F6875]">
              Choose the tier that matches your monitoring needs. Available in USD ($) globally or BDT (৳) in Bangladesh.
            </p>
          </div>

          <div class="grid items-stretch gap-6 md:grid-cols-3 text-left">
            <div
              v-for="plan in plans"
              :key="plan.name"
              :class="[
                'card-editorial p-6 sm:p-7 flex flex-col justify-between space-y-6',
                plan.popular ? 'border-[#0E1A2B] shadow-md ring-1 ring-[#0E1A2B]' : ''
              ]"
            >
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold uppercase tracking-wider text-[#0E1A2B] font-mono">{{ plan.name }}</span>
                  <span v-if="plan.popular" class="badge-verified">MOST POPULAR</span>
                </div>

                <p class="text-xs text-[#5F6875] min-h-[36px]">{{ plan.desc }}</p>

                <div class="pt-2">
                  <div class="flex items-baseline gap-1">
                    <span class="font-serif text-4xl font-medium text-[#0E1A2B]">{{ plan.monthly }}</span>
                    <span class="text-xs text-[#5F6875]">/ month</span>
                  </div>
                  <p class="text-[11px] text-[#5F6875] mt-1">{{ plan.annual }} billed annually (save 20%)</p>
                </div>

                <ul class="space-y-2.5 pt-4 border-t border-[#D0D9CF] text-xs text-[#3F4A5A]">
                  <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2">
                    <Check class="h-3.5 w-3.5 text-[#17794D] shrink-0 mt-0.5" />
                    <span>{{ feature }}</span>
                  </li>
                </ul>
              </div>

              <RouterLink :to="`/register?plan=${plan.name.toLowerCase()}`">
                <button
                  type="button"
                  :class="[
                    'w-full py-2.5 text-xs font-semibold rounded-[4px] transition-colors',
                    plan.popular ? 'btn-ink' : 'border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B]'
                  ]"
                >
                  Choose {{ plan.name }}
                </button>
              </RouterLink>
            </div>
          </div>
        </div>
      </section>

      <!-- BOTTOM CALLOUT -->
      <section class="px-4 py-16 sm:px-6 lg:px-8 border-t border-[#D0D9CF] bg-[#F6F4EF]">
        <div class="mx-auto max-w-7xl card-editorial bg-[#0E1A2B] text-white p-8 sm:p-12 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
          <div class="max-w-xl space-y-2">
            <p class="text-[11px] font-mono uppercase tracking-widest text-[#B7791F]">A SHARPER VIEW STARTS TODAY</p>
            <h2 class="editorial-headline text-2xl sm:text-3xl font-medium text-white">
              Bring verified intelligence directly into your team's workflow.
            </h2>
          </div>
          <RouterLink to="/register">
            <button type="button" class="btn-brass-outline bg-[#B7791F] text-white hover:bg-[#8A5A08] border-none px-6 py-3 text-xs font-semibold flex items-center gap-2">
              <span>Start exploring now</span>
              <ArrowUpRight class="h-4 w-4" />
            </button>
          </RouterLink>
        </div>
      </section>
    </main>

    <!-- FOOTER -->
    <footer class="border-t border-[#D0D9CF] bg-white px-4 py-6 sm:px-6 lg:px-8 text-xs text-[#5F6875]">
      <div class="mx-auto flex max-w-7xl flex-col sm:flex-row items-center justify-between gap-4">
        <span>© 2026 Nexovant · Intelligence Beyond Headlines. All rights reserved.</span>
        <div class="flex gap-6">
          <RouterLink to="/billing" class="hover:text-[#0E1A2B]">Plans & Pricing</RouterLink>
          <RouterLink to="/trends" class="hover:text-[#0E1A2B]">Trending Signals</RouterLink>
          <RouterLink to="/" class="hover:text-[#0E1A2B]">Live Intelligence Feed</RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>
