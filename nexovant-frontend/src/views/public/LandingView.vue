<script setup lang="ts">
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
} from 'lucide-vue-next'
import PublicNavbar from '@/components/layout/PublicNavbar.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'

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

const plans = [
  { name: 'Professional', monthly: '৳499', annual: '৳4,990', desc: 'For independent analysts and business operators.', features: ['Full intelligence feed', 'Follow up to 25 companies', 'Verification labels & source context', 'Daily email digest'] },
  { name: 'Executive', monthly: '৳999', annual: '৳9,990', desc: 'For leadership teams, investors and strategy desks.', popular: true, features: ['Everything in Professional', 'Ask questions across the knowledge base', 'Social trend monitoring', 'Daily & weekly PDF briefings', 'Competitor context'] },
  { name: 'Executive Pro', monthly: '৳1,999', annual: '৳19,990', desc: 'For enterprise leadership and investment teams.', features: ['Everything in Executive', 'Unlimited company & executive tracking', 'Priority risk alerts', 'Custom intelligence reports', 'Five team member seats'] },
]
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-left">
    <PublicNavbar />

    <main>
      <section class="relative overflow-hidden px-4 pb-16 pt-14 sm:px-6 sm:pb-20 sm:pt-20 lg:px-8 lg:pb-24 lg:pt-24">
        <div class="pointer-events-none absolute -right-28 -top-32 h-[520px] w-[520px] rounded-full border border-[#dce8df] opacity-60"></div>
        <div class="pointer-events-none absolute -right-12 -top-16 h-[400px] w-[400px] rounded-full border border-[#dce8df] opacity-70"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-14 lg:grid-cols-12 lg:gap-12">
          <div class="lg:col-span-6">
            <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d6e6dc] bg-white/80 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.13em] text-nexo-accent-cyan shadow-sm">
              <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-40"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600"></span></span>
              Global business intelligence
            </div>
            <h1 class="max-w-[700px] font-serif text-[clamp(3.35rem,6.5vw,5.75rem)] font-medium leading-[0.98] tracking-[-0.055em] text-nexo-text-primary">
              See the story <span class="italic text-nexo-accent-cyan">behind</span> the news.
            </h1>
            <p class="mt-7 max-w-[560px] text-base leading-7 text-nexo-text-secondary sm:text-lg sm:leading-8">
              Nexovant connects verified developments to the companies, people and decisions that shape your business. Start with what happened. Leave with the context to act.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
              <RouterLink to="/register"><Button variant="primary" size="lg">Explore Nexovant <ArrowRight class="ml-2 h-4 w-4" /></Button></RouterLink>
              <RouterLink to="/feed"><Button variant="secondary" size="lg">Browse the live feed <ArrowDownRight class="ml-2 h-4 w-4" /></Button></RouterLink>
            </div>
            <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-nexo-text-muted">
              <span class="inline-flex items-center gap-1.5"><ShieldCheck class="h-4 w-4 text-nexo-accent-cyan" /> Source aware</span>
              <span class="inline-flex items-center gap-1.5"><Layers3 class="h-4 w-4 text-nexo-accent-cyan" /> Ten dimensions of context</span>
              <span class="inline-flex items-center gap-1.5"><Globe2 class="h-4 w-4 text-nexo-accent-cyan" /> Global coverage</span>
            </div>
          </div>

          <div class="relative lg:col-span-6">
            <div class="absolute -inset-5 rounded-[34px] bg-gradient-to-br from-[#dcefe2] via-transparent to-[#ece5d2] blur-2xl"></div>
            <div class="relative rounded-[28px] border border-[#315849] bg-[#173b32] p-5 text-white shadow-[0_34px_90px_-44px_rgba(16,51,39,0.65)] sm:p-7">
              <div class="flex items-center justify-between border-b border-white/10 pb-4">
                <div class="flex items-center gap-2.5"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10"><Compass class="h-4 w-4 text-[#b8e1c5]" /></span><div><p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-[#b8d8c6]">A look inside Nexovant</p><p class="mt-0.5 text-xs font-medium text-white/80">Executive intelligence brief</p></div></div>
                <span class="rounded-full border border-emerald-200/20 bg-emerald-200/10 px-2.5 py-1 text-[10px] font-semibold text-[#b8e1c5]">SAMPLE</span>
              </div>

              <div class="pt-6">
                <div class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#b8e1c5]"><FileCheck2 class="h-3.5 w-3.5" /> Capital & expansion <span class="text-white/30">·</span> 10 min read</div>
                <h2 class="mt-3 max-w-[540px] font-serif text-[27px] font-medium leading-[1.14] tracking-tight text-white sm:text-[32px]">NVIDIA plans a new chapter for custom silicon in Europe</h2>
                <p class="mt-3 max-w-[500px] text-sm leading-6 text-[#c4d6cd]">A strategic move can travel far beyond one company. Follow the decision through its partners, policy environment and next milestones.</p>
              </div>

              <div class="mt-6 rounded-2xl border border-white/10 bg-[#20483d] p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#d5eadb] text-[#174234]"><Target class="h-3.5 w-3.5" /></span><span class="text-xs font-semibold text-white">The strategic read</span></div>
                  <span class="rounded-full border border-emerald-200/20 px-2 py-1 text-[9px] font-semibold uppercase tracking-wide text-[#b8e1c5]">Confirmed</span>
                </div>
                <p class="mt-3 text-xs leading-[1.7] text-[#c4d6cd]">Explore the likely business significance, who may be affected, related events and the next signals worth watching.</p>
                <div class="mt-4 flex flex-wrap gap-2"><span class="rounded-md bg-white/10 px-2.5 py-1.5 text-[10px] font-medium text-[#e4eee8]">NVIDIA</span><span class="rounded-md bg-white/10 px-2.5 py-1.5 text-[10px] font-medium text-[#e4eee8]">European Commission</span><span class="rounded-md bg-white/10 px-2.5 py-1.5 text-[10px] font-medium text-[#e4eee8]">Supply chain</span></div>
              </div>

              <div class="mt-5 flex items-center justify-between border-t border-white/10 pt-4 text-[10px] text-[#a9c3b5]"><span>Evidence, entities and forward triggers in one view</span><ArrowUpRight class="h-4 w-4" /></div>
            </div>
            <div class="absolute -bottom-6 -left-6 hidden items-center gap-3 rounded-2xl border border-nexo-border bg-white p-3.5 shadow-xl sm:flex">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#edf5ef] text-nexo-accent-cyan"><Sparkles class="h-5 w-5" /></span>
              <span><span class="block text-xs font-semibold text-nexo-text-primary">From headline to context</span><span class="mt-1 block text-[10px] text-nexo-text-muted">A clearer way to follow change</span></span>
            </div>
          </div>
        </div>
      </section>

      <section class="border-y border-nexo-border bg-white/75 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-5 sm:grid-cols-3 sm:gap-8">
          <div class="flex items-center gap-3"><span class="font-serif text-3xl font-medium text-nexo-accent-cyan">10</span><span class="text-xs leading-5 text-nexo-text-secondary">dimensions to understand<br />what matters next</span></div>
          <div class="flex items-center gap-3 sm:border-x sm:border-nexo-border sm:px-8"><span class="font-serif text-3xl font-medium text-nexo-accent-cyan">5</span><span class="text-xs leading-5 text-nexo-text-secondary">clear verification labels<br />for every level of certainty</span></div>
          <div class="flex items-center gap-3"><span class="font-serif text-3xl font-medium text-nexo-accent-cyan">1</span><span class="text-xs leading-5 text-nexo-text-secondary">connected workspace for<br />your team and watchlists</span></div>
        </div>
      </section>

      <section id="features" class="px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
        <div class="mx-auto max-w-7xl">
          <div class="max-w-2xl">
            <p class="eyebrow">Less noise. More signal.</p>
            <h2 class="mt-3 font-serif text-4xl font-medium tracking-tight text-nexo-text-primary sm:text-5xl">Good decisions begin with better context.</h2>
            <p class="mt-4 text-sm leading-7 text-nexo-text-secondary">A focused intelligence workspace that connects the dots between business developments and their real world consequences.</p>
          </div>
          <div class="mt-10 grid gap-4 md:grid-cols-3">
            <Card v-for="feature in [
              { icon: ShieldCheck, title: 'Know what is verified', text: 'Every development carries a clear confidence label and a traceable source trail.' },
              { icon: Layers3, title: 'See the whole picture', text: 'Understand significance, stakeholders, consequences and the next milestone to watch.' },
              { icon: Globe2, title: 'Follow what matters to you', text: 'Build a living view around the companies, executives and themes you care about.' },
            ]" :key="feature.title" padding="lg" class="min-h-[218px]">
              <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#eaf3ed] text-nexo-accent-cyan"><component :is="feature.icon" class="h-5 w-5" /></span>
              <h3 class="mt-6 font-serif text-xl font-medium text-nexo-text-primary">{{ feature.title }}</h3>
              <p class="mt-2 text-sm leading-6 text-nexo-text-secondary">{{ feature.text }}</p>
            </Card>
          </div>
        </div>
      </section>

      <section id="intelligence" class="border-y border-nexo-border bg-white px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-12 lg:gap-16">
          <div class="lg:col-span-4">
            <p class="eyebrow">The Nexovant method</p>
            <h2 class="mt-3 font-serif text-4xl font-medium tracking-tight text-nexo-text-primary sm:text-[42px]">Ten questions. A more useful picture.</h2>
            <p class="mt-5 text-sm leading-7 text-nexo-text-secondary">Headlines tell you something happened. Our structured analysis helps you see how it connects to your business.</p>
            <RouterLink to="/feed" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-nexo-accent-cyan transition hover:gap-3">Explore the intelligence feed <ArrowRight class="h-4 w-4" /></RouterLink>
          </div>
          <div class="grid gap-x-7 sm:grid-cols-2 lg:col-span-8">
            <div v-for="(dim, index) in intelligenceDimensions" :key="dim.q" class="flex gap-4 border-b border-nexo-border py-4 first:pt-0 sm:even:border-l sm:even:pl-6">
              <span class="font-mono text-[11px] font-semibold text-nexo-accent-cyan">{{ String(index + 1).padStart(2, '0') }}</span>
              <div><h3 class="text-sm font-semibold text-nexo-text-primary">{{ dim.q }}</h3><p class="mt-1 text-xs leading-5 text-nexo-text-secondary">{{ dim.desc }}</p></div>
            </div>
          </div>
        </div>
      </section>

      <section id="verification" class="px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
        <div class="mx-auto max-w-7xl">
          <div class="mx-auto max-w-2xl text-center">
            <p class="eyebrow">Trust, made visible</p>
            <h2 class="mt-3 font-serif text-4xl font-medium tracking-tight text-nexo-text-primary">Confidence should never be a guess.</h2>
            <p class="mt-4 text-sm leading-6 text-nexo-text-secondary">Keep confirmed facts, developing situations and unverified claims clearly apart.</p>
          </div>
          <div class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div v-for="status in [
              { variant: 'confirmed', copy: 'Primary filings and official disclosures.' },
              { variant: 'reported_reliable', copy: 'Corroborated by reliable independent sources.' },
              { variant: 'developing', copy: 'An active situation being closely monitored.' },
              { variant: 'disputed', copy: 'Conflicting statements or contested claims.' },
              { variant: 'unverified', copy: 'A signal that still needs confirmation.' },
            ]" :key="status.variant" class="rounded-2xl border border-nexo-border bg-white p-4 text-center shadow-sm">
              <Badge :variant="status.variant as any" size="sm" />
              <p class="mt-3 text-xs leading-5 text-nexo-text-secondary">{{ status.copy }}</p>
            </div>
          </div>
        </div>
      </section>

      <section id="pricing" class="border-y border-nexo-border bg-[#edf2ec] px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
        <div class="mx-auto max-w-7xl">
          <div class="mx-auto max-w-2xl text-center">
            <p class="eyebrow">Plans that scale with you</p>
            <h2 class="mt-3 font-serif text-4xl font-medium tracking-tight text-nexo-text-primary">Clarity for every kind of team.</h2>
            <p class="mt-4 text-sm leading-6 text-nexo-text-secondary">Choose a workspace that fits. Billed in BDT locally or USD internationally.</p>
          </div>
          <div class="mt-10 grid items-stretch gap-5 md:grid-cols-3">
            <Card v-for="plan in plans" :key="plan.name" padding="lg" :class="[plan.popular ? 'relative border-nexo-accent-cyan shadow-[0_20px_60px_-30px_rgba(20,122,103,0.38)]' : '']">
              <div v-if="plan.popular" class="absolute right-5 top-5"><Badge variant="reported_reliable" size="sm">Most popular</Badge></div>
              <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-nexo-text-muted">{{ plan.name }}</div>
              <p class="mt-3 min-h-10 text-xs leading-5 text-nexo-text-secondary">{{ plan.desc }}</p>
              <div class="mt-6 flex items-baseline gap-1"><span class="font-serif text-4xl font-medium tracking-tight text-nexo-text-primary">{{ plan.monthly }}</span><span class="text-xs text-nexo-text-muted">/ month</span></div>
              <p class="mt-1 text-[10px] text-nexo-text-muted">{{ plan.annual }} billed annually</p>
              <ul class="my-6 space-y-3 border-y border-nexo-border py-5">
                <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2.5 text-xs text-nexo-text-secondary"><Check class="mt-0.5 h-3.5 w-3.5 shrink-0 text-nexo-accent-cyan" /><span>{{ feature }}</span></li>
              </ul>
              <RouterLink to="/register"><Button :variant="plan.popular ? 'primary' : 'secondary'" block>Choose {{ plan.name }}</Button></RouterLink>
            </Card>
          </div>
        </div>
      </section>

      <section class="px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 rounded-[28px] bg-[#173b32] p-7 text-white sm:p-10 lg:flex-row lg:items-center">
          <div class="max-w-xl"><p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#b8e1c5]">A sharper view starts here</p><h2 class="mt-3 font-serif text-3xl font-medium leading-tight text-white sm:text-4xl">Bring the context closer to the decisions you make.</h2></div>
          <RouterLink to="/register"><Button variant="secondary" size="lg">Start exploring <ArrowUpRight class="ml-2 h-4 w-4" /></Button></RouterLink>
        </div>
      </section>
    </main>

    <footer class="border-t border-nexo-border bg-white px-4 py-7 sm:px-6 lg:px-8">
      <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 text-xs text-nexo-text-muted sm:flex-row">
        <span>© 2026 Nexovant · Global executive intelligence</span>
        <div class="flex gap-5"><RouterLink to="/landing#verification" class="transition hover:text-nexo-text-primary">Editorial standards</RouterLink><RouterLink to="/landing#pricing" class="transition hover:text-nexo-text-primary">Plans & pricing</RouterLink><RouterLink to="/feed" class="transition hover:text-nexo-text-primary">Global feed</RouterLink></div>
      </div>
    </footer>
  </div>
</template>
