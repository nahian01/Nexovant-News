<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2 } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import { apiClient } from '@/services/api/client'

const props = withDefaults(
  defineProps<{
    source?: string
    title?: string
    description?: string
    compact?: boolean
  }>(),
  {
    source: 'public_blog',
    title: 'Stay in Tune — Executive Intelligence Delivered to Your Inbox',
    description: 'Get factual, multi-source verified intelligence briefings before market open. No clickbait, pure strategic signal for leaders.',
    compact: false,
  }
)

const router = useRouter()
const email = ref('')
const frequency = ref<'daily' | 'weekly'>('daily')
const isLoading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const claimedOffer = ref<any>(null)

const handleSubscribe = async () => {
  errorMessage.value = ''
  successMessage.value = ''

  if (!email.value || !email.value.includes('@')) {
    errorMessage.value = 'Please provide a valid corporate or professional email address.'
    return
  }

  isLoading.value = true
  try {
    const res = await apiClient.post('/newsletter/subscribe', {
      email: email.value,
      frequency: frequency.value,
      source: props.source,
      offer_code: 'NEXO-EXECUTIVE-30',
    })

    successMessage.value = res.data.message || 'Subscribed successfully!'
    claimedOffer.value = res.data.offer
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message ||
      'Subscription could not be processed at this time. Please retry.'
  } finally {
    isLoading.value = false
  }
}

const goToRegisterWithOffer = () => {
  if (claimedOffer.value?.claim_url) {
    router.push(claimedOffer.value.claim_url)
  } else {
    router.push(`/register?offer=NEXO-EXECUTIVE-30&email=${encodeURIComponent(email.value)}`)
  }
}
</script>

<template>
  <div
    :class="[
      'bg-gradient-to-br from-white via-white to-[#f2f6f2] border border-nexo-border rounded-2xl p-6 lg:p-8 text-left transition-all relative overflow-hidden shadow-sm',
      compact ? 'p-5' : ''
    ]"
  >
    <!-- Background Glow Accent -->
    <div class="absolute -right-16 -top-16 w-48 h-48 bg-nexo-accent-cyan/5 rounded-full blur-3xl pointer-events-none"></div>

    <div v-if="!claimedOffer" class="space-y-4 relative z-10">
      <div class="flex items-center space-x-2">
        <span class="w-2 h-2 rounded-full bg-nexo-accent-cyan animate-pulse"></span>
        <span class="text-[10px] font-mono tracking-widest text-nexo-accent-cyan uppercase font-bold">
          EXECUTIVE DISPATCH & SPECIAL OFFER
        </span>
      </div>

      <div>
        <h3 :class="['editorial-title font-semibold text-nexo-text-primary tracking-tight', compact ? 'text-lg' : 'text-xl sm:text-2xl']">
          {{ title }}
        </h3>
        <p class="mt-1 text-xs text-nexo-text-secondary leading-relaxed max-w-2xl">
          {{ description }}
        </p>
      </div>

      <Alert v-if="errorMessage" variant="danger">{{ errorMessage }}</Alert>

      <form @submit.prevent="handleSubscribe" class="space-y-3 pt-1">
        <div class="flex flex-col sm:flex-row gap-3">
          <div class="flex-1">
            <Input
              v-model="email"
              type="email"
              placeholder="Enter your corporate email (e.g. name@company.com)"
              required
            />
          </div>
          <Button
            type="submit"
            variant="primary"
            :loading="isLoading"
            class="whitespace-nowrap font-medium"
          >
            Stay in Tune & Get 30% Off
          </Button>
        </div>

        <div class="flex items-center space-x-6 text-[11px] font-mono text-nexo-text-muted">
          <label class="flex items-center space-x-1.5 cursor-pointer hover:text-nexo-text-primary transition-colors">
            <input
              type="radio"
              v-model="frequency"
              value="daily"
              class="text-nexo-accent-cyan focus:ring-0 bg-nexo-surface border-nexo-border"
            />
            <span>Daily 7:00 AM Morning Executive Digest</span>
          </label>
          <label class="flex items-center space-x-1.5 cursor-pointer hover:text-nexo-text-primary transition-colors">
            <input
              type="radio"
              v-model="frequency"
              value="weekly"
              class="text-nexo-accent-cyan focus:ring-0 bg-nexo-surface border-nexo-border"
            />
            <span>Weekly Strategic Briefing Wrap</span>
          </label>
        </div>
      </form>
    </div>

    <!-- State when subscribed and offer claimed -->
    <div v-else class="space-y-4 relative z-10">
      <div class="flex items-center space-x-2 text-emerald-700">
        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
        <span class="text-xs font-mono font-bold tracking-wider uppercase">
          SUBSCRIPTION CONFIRMED
        </span>
      </div>

      <h3 class="editorial-title text-xl sm:text-2xl font-semibold text-nexo-text-primary">
        You're In! Welcome to Executive Intelligence.
      </h3>

      <p class="text-xs text-nexo-text-secondary leading-relaxed">
        {{ successMessage }} Your first briefing will arrive tomorrow morning. In the meantime, your exclusive welcome offer has been unlocked:
      </p>

      <div class="bg-nexo-surface border border-nexo-accent-gold/40 rounded p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center space-x-2">
            <span class="text-xs font-mono font-bold text-nexo-accent-gold uppercase tracking-wider">
              {{ claimedOffer.title }}
            </span>
            <span class="bg-nexo-accent-gold/20 text-nexo-accent-gold px-2 py-0.5 rounded text-[10px] font-mono font-bold">
              30% OFF
            </span>
          </div>
          <p class="text-xs text-nexo-text-primary font-medium mt-0.5">
            {{ claimedOffer.description }} (Coupon Code: <span class="font-mono text-nexo-accent-cyan font-bold">{{ claimedOffer.code }}</span>)
          </p>
        </div>

        <Button variant="secondary" size="md" @click="goToRegisterWithOffer">
          Take Offer & Create Account →
        </Button>
      </div>
    </div>
  </div>
</template>
