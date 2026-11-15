<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import {
  billingService,
  type BillingPlan,
  type SubscriberSubscription,
  type EntitlementsSnapshot,
  type BillingUsage,
  type PaymentMethodOption,
  type BillingInvoice
} from '@/services/api/billing.service'
import {
  CreditCard,
  CheckCircle2,
  AlertCircle,
  Clock,
  Download,
  Shield,
  Zap,
  ArrowRight,
  Sparkles,
  Building2,
  Smartphone,
  RefreshCw,
  Check,
  Tag,
  FileText
} from 'lucide-vue-next'

// State
const isLoading = ref(true)
const activeTab = ref<'overview' | 'plans' | 'invoices'>('overview')
const currentSub = ref<SubscriberSubscription | null>(null)
const entitlements = ref<EntitlementsSnapshot | null>(null)
const usage = ref<BillingUsage | null>(null)
const plans = ref<BillingPlan[]>([])
const invoices = ref<BillingInvoice[]>([])
const eligibleMethods = ref<PaymentMethodOption[]>([])

// Currency & Billing Cycle selection
const selectedCycle = ref<'monthly' | 'annual'>('monthly')
const selectedCurrency = ref<'USD' | 'BDT'>('USD')

// Checkout Modal State
const isCheckoutModalOpen = ref(false)
const targetPlan = ref<BillingPlan | null>(null)
const selectedMethod = ref<string>('stripe')
const couponCode = ref('')
const couponDiscount = ref<any>(null)
const couponError = ref('')
const isCheckingCoupon = ref(false)
const isCheckingOut = ref(false)
const checkoutError = ref('')
const checkoutSuccess = ref<any>(null)

// Manual Proof Modal State
const isProofModalOpen = ref(false)
const proofInvoiceId = ref('')
const proofMethod = ref('manual_bkash')
const proofTrxId = ref('')
const proofSenderAccount = ref('')
const proofNotes = ref('')
const isSubmittingProof = ref(false)
const proofSuccess = ref('')
const proofError = ref('')

// Cancel / Reactivate State
const isCancelModalOpen = ref(false)
const cancelAtPeriodEnd = ref(true)
const cancelReason = ref('')
const isCanceling = ref(false)
const cancelError = ref('')

const fetchBillingData = async () => {
  isLoading.value = true
  try {
    const [overviewRes, plansRes, invoicesRes] = await Promise.all([
      billingService.getOverview(),
      billingService.getPlans(),
      billingService.getInvoices(1)
    ])

    if (overviewRes.success) {
      currentSub.value = overviewRes.data.subscription
      entitlements.value = overviewRes.data.entitlements
      usage.value = overviewRes.data.usage
      eligibleMethods.value = overviewRes.data.eligible_payment_methods || []
      if (overviewRes.data.subscription?.currency) {
        selectedCurrency.value = overviewRes.data.subscription.currency
      }
    }

    if (plansRes.success) {
      plans.value = plansRes.data
    }

    if (invoicesRes.success) {
      invoices.value = invoicesRes.data
    }
  } catch (err) {
    console.error('Failed to load billing overview', err)
  } finally {
    isLoading.value = false
  }
}

const openCheckout = async (plan: BillingPlan) => {
  targetPlan.value = plan
  couponCode.value = ''
  couponDiscount.value = null
  couponError.value = ''
  checkoutError.value = ''
  checkoutSuccess.value = null

  // Fetch methods matching current currency
  try {
    const country = selectedCurrency.value === 'BDT' ? 'BD' : 'US'
    const res = await billingService.getPaymentMethods({
      country,
      currency: selectedCurrency.value,
      interval: selectedCycle.value
    })
    eligibleMethods.value = res.data || []
    if (eligibleMethods.value.length > 0) {
      selectedMethod.value = eligibleMethods.value[0].id
    }
  } catch {
    //
  }

  isCheckoutModalOpen.value = true
}

const handleApplyCoupon = async () => {
  if (!couponCode.value.trim() || !targetPlan.value) return

  isCheckingCoupon.value = true
  couponError.value = ''
  try {
    const subtotal = getPlanPrice(targetPlan.value, selectedCycle.value, selectedCurrency.value)
    const country = selectedCurrency.value === 'BDT' ? 'BD' : 'US'

    const res = await billingService.validateCoupon({
      code: couponCode.value.trim(),
      plan_id: targetPlan.value.id,
      country,
      subtotal
    })

    if (res.success) {
      couponDiscount.value = res.data
    }
  } catch (err: any) {
    couponDiscount.value = null
    couponError.value = err.response?.data?.message || err.response?.data?.errors?.coupon?.[0] || 'Invalid or expired coupon.'
  } finally {
    isCheckingCoupon.value = false
  }
}

const getPlanPrice = (plan: BillingPlan, cycle: 'monthly' | 'annual', curr: 'USD' | 'BDT'): number => {
  if (curr === 'BDT') {
    return cycle === 'annual' ? Number(plan.price_bdt_annual) : Number(plan.price_bdt_monthly)
  }
  return cycle === 'annual' ? Number(plan.price_usd_annual) : Number(plan.price_usd_monthly)
}

const computedCheckoutTotal = computed(() => {
  if (!targetPlan.value) return 0
  const base = getPlanPrice(targetPlan.value, selectedCycle.value, selectedCurrency.value)
  if (couponDiscount.value?.discount_amount) {
    return Math.max(0, base - Number(couponDiscount.value.discount_amount))
  }
  return base
})

const handleExecuteCheckout = async () => {
  if (!targetPlan.value) return

  isCheckingOut.value = true
  checkoutError.value = ''
  try {
    const country = selectedCurrency.value === 'BDT' ? 'BD' : 'US'
    const res = await billingService.checkout({
      plan_id: targetPlan.value.id,
      billing_cycle: selectedCycle.value,
      currency: selectedCurrency.value,
      country,
      payment_method: selectedMethod.value,
      coupon_code: couponDiscount.value ? couponCode.value.trim() : undefined,
    })

    if (res.success) {
      checkoutSuccess.value = res.data

      // If bank wire or manual payment, prompt for proof submission
      if (res.data.is_manual_instructions || selectedMethod.value.includes('bank') || selectedMethod.value.includes('bkash')) {
        proofInvoiceId.value = res.data.invoice?.id || ''
        proofMethod.value = selectedMethod.value
      }

      await fetchBillingData()
    }
  } catch (err: any) {
    checkoutError.value = err.response?.data?.message || 'Checkout failed. Please verify selected payment method.'
  } finally {
    isCheckingOut.value = false
  }
}

const openProofModal = (invoiceId: string, method?: string) => {
  proofInvoiceId.value = invoiceId
  proofMethod.value = method || (selectedCurrency.value === 'BDT' ? 'manual_bkash' : 'manual_bank')
  proofTrxId.value = ''
  proofSenderAccount.value = ''
  proofNotes.value = ''
  proofSuccess.value = ''
  proofError.value = ''
  isProofModalOpen.value = true
}

const handleSubmitProof = async () => {
  if (!proofTrxId.value.trim()) {
    proofError.value = 'Transaction reference ID (TrxID) is required.'
    return
  }

  isSubmittingProof.value = true
  proofError.value = ''
  proofSuccess.value = ''
  try {
    const res = await billingService.submitManualPayment({
      invoice_id: proofInvoiceId.value,
      payment_method: proofMethod.value,
      transaction_id: proofTrxId.value.trim(),
      sender_account: proofSenderAccount.value.trim() || undefined,
      notes: proofNotes.value.trim() || undefined,
    })

    if (res.success) {
      proofSuccess.value = 'Payment proof submitted. Our compliance team will review and credit your subscription.'
      setTimeout(() => {
        isProofModalOpen.value = false
        fetchBillingData()
      }, 1500)
    }
  } catch (err: any) {
    proofError.value = err.response?.data?.message || err.response?.data?.error?.message || 'Failed to submit payment proof.'
  } finally {
    isSubmittingProof.value = false
  }
}

const handleCancelSubscription = async () => {
  isCanceling.value = true
  cancelError.value = ''
  try {
    const res = await billingService.cancelSubscription(cancelAtPeriodEnd.value, cancelReason.value)
    if (res.success) {
      isCancelModalOpen.value = false
      await fetchBillingData()
    }
  } catch (err: any) {
    cancelError.value = err.response?.data?.message || 'Failed to cancel subscription.'
  } finally {
    isCanceling.value = false
  }
}

const handleReactivateSubscription = async () => {
  try {
    const res = await billingService.reactivateSubscription()
    if (res.success) {
      await fetchBillingData()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to reactivate subscription.')
  }
}

const getUsagePercentage = (used: number, limit: number | string): number => {
  if (limit === 'unlimited' || limit === 999999) return 10
  const numLimit = Number(limit)
  if (numLimit <= 0) return 0
  return Math.min(100, Math.round((used / numLimit) * 100))
}

onMounted(() => {
  fetchBillingData()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 md:p-8 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-nexo-border gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs uppercase tracking-widest font-semibold px-2 py-0.5 rounded bg-primary-950/60 text-primary-400 border border-primary-800/40">
                Phase 7D Billing Engine
              </span>
              <span class="text-xs text-nexo-muted">Dual-Currency & Compliance Gateways</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
              <CreditCard class="w-6 h-6 text-primary-400" />
              Subscriber Billing & Entitlement Management
            </h1>
            <p class="text-sm text-nexo-muted mt-1">
              Manage your subscription tier, track quota consumption, access downloadable receipts, and update payment methods.
            </p>
          </div>

          <!-- Currency Selector Toggle -->
          <div class="flex items-center bg-nexo-surface border border-nexo-border rounded-lg p-1 self-start md:self-auto">
            <button
              @click="selectedCurrency = 'USD'"
              :class="[
                'px-3 py-1.5 text-xs font-medium rounded-md transition-all',
                selectedCurrency === 'USD'
                  ? 'bg-primary-600 text-white shadow-sm'
                  : 'text-nexo-muted hover:text-white'
              ]"
            >
              USD ($) Global
            </button>
            <button
              @click="selectedCurrency = 'BDT'"
              :class="[
                'px-3 py-1.5 text-xs font-medium rounded-md transition-all',
                selectedCurrency === 'BDT'
                  ? 'bg-primary-600 text-white shadow-sm'
                  : 'text-nexo-muted hover:text-white'
              ]"
            >
              BDT (৳) Bangladesh
            </button>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-4 mt-6 border-b border-nexo-border text-sm">
          <button
            @click="activeTab = 'overview'"
            :class="[
              'pb-3 font-medium transition-colors border-b-2 flex items-center gap-2',
              activeTab === 'overview'
                ? 'border-primary-500 text-primary-400'
                : 'border-transparent text-nexo-muted hover:text-white'
            ]"
          >
            <Shield class="w-4 h-4" />
            Current Plan & Entitlements
          </button>
          <button
            @click="activeTab = 'plans'"
            :class="[
              'pb-3 font-medium transition-colors border-b-2 flex items-center gap-2',
              activeTab === 'plans'
                ? 'border-primary-500 text-primary-400'
                : 'border-transparent text-nexo-muted hover:text-white'
            ]"
          >
            <Sparkles class="w-4 h-4" />
            Available Plans & Upgrades
          </button>
          <button
            @click="activeTab = 'invoices'"
            :class="[
              'pb-3 font-medium transition-colors border-b-2 flex items-center gap-2',
              activeTab === 'invoices'
                ? 'border-primary-500 text-primary-400'
                : 'border-transparent text-nexo-muted hover:text-white'
            ]"
          >
            <FileText class="w-4 h-4" />
            Invoices & Receipts ({{ invoices.length }})
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="py-16 text-center text-nexo-muted">
          <RefreshCw class="w-8 h-8 animate-spin mx-auto mb-2 text-primary-500" />
          <p class="text-sm">Synchronizing entitlement quotas and financial telemetry...</p>
        </div>

        <!-- Content Area -->
        <div v-else class="mt-6 space-y-6">

          <!-- TAB 1: OVERVIEW & ENTITLEMENTS -->
          <div v-if="activeTab === 'overview'" class="space-y-6">
            <!-- Current Plan Card -->
            <Card class="p-6 bg-gradient-to-br from-nexo-surface to-nexo-surface/60 border border-nexo-border">
              <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-2">
                  <div class="flex items-center gap-2">
                    <span class="text-xs uppercase tracking-wider text-nexo-muted font-mono">Active Subscription</span>
                    <span
                      :class="currentSub?.status === 'active' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : (currentSub?.status === 'trialing' ? 'bg-amber-950/80 text-amber-400 border-amber-800/40' : 'bg-nexo-bg text-nexo-muted border-nexo-border')"
                      class="px-2 py-0.5 rounded text-[11px] font-mono border"
                    >
                      {{ currentSub?.status ? currentSub.status.toUpperCase().replace(/_/g, ' ') : 'FREE TIER' }}
                    </span>
                    <span v-if="currentSub?.auto_renew" class="px-2 py-0.5 rounded text-[11px] font-mono border bg-primary-950/60 text-primary-400 border-primary-800/40">
                      Auto-Renewal ON
                    </span>
                  </div>
                  <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                    {{ currentSub?.plan?.name || entitlements?.plan_name || 'Free Tier Intelligence' }}
                  </h2>
                  <p class="text-sm text-nexo-muted max-w-xl">
                    {{ currentSub?.plan?.description || 'Essential access to public briefings, basic company watchlists, and standard intelligence feeds.' }}
                  </p>
                </div>

                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                  <Button
                    v-if="currentSub?.status === 'cancel_at_period_end'"
                    @click="handleReactivateSubscription"
                    variant="outline"
                    class="border-emerald-500/50 text-emerald-400 hover:bg-emerald-950/30"
                  >
                    <RefreshCw class="w-4 h-4 mr-2" />
                    Reactivate Auto-Renewal
                  </Button>
                  <Button
                    @click="activeTab = 'plans'"
                    variant="primary"
                    class="shadow-lg shadow-primary-950/50"
                  >
                    <Sparkles class="w-4 h-4 mr-2" />
                    Change or Upgrade Plan
                  </Button>
                  <Button
                    v-if="currentSub?.is_active && currentSub?.status !== 'cancel_at_period_end' && currentSub?.plan?.slug !== 'free'"
                    @click="isCancelModalOpen = true"
                    variant="ghost"
                    class="text-rose-400 hover:bg-rose-950/20"
                  >
                    Cancel Plan
                  </Button>
                </div>
              </div>

              <!-- Renewal & Period Metadata -->
              <div class="mt-6 pt-4 border-t border-nexo-border/60 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div>
                  <span class="text-nexo-muted block">Billing Cycle:</span>
                  <span class="font-medium text-white capitalize">{{ currentSub?.billing_cycle || 'Monthly' }}</span>
                </div>
                <div>
                  <span class="text-nexo-muted block">Period Started:</span>
                  <span class="font-medium text-white">{{ currentSub?.starts_at ? new Date(currentSub.starts_at).toLocaleDateString() : 'Active' }}</span>
                </div>
                <div>
                  <span class="text-nexo-muted block">Valid Until / Renews:</span>
                  <span class="font-medium text-emerald-400">{{ currentSub?.ends_at ? new Date(currentSub.ends_at).toLocaleDateString() : 'Perpetual' }}</span>
                </div>
                <div>
                  <span class="text-nexo-muted block">Default Currency:</span>
                  <span class="font-medium text-white font-mono">{{ currentSub?.currency || selectedCurrency }}</span>
                </div>
              </div>
            </Card>

            <!-- Usage Mappings & Real-Time Entitlement Meters -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <!-- Watchlists Quota -->
              <Card class="p-5 border border-nexo-border bg-nexo-surface">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs uppercase font-medium tracking-wider text-nexo-muted">Watchlists Quota</span>
                  <span class="text-xs font-mono font-bold text-white">
                    {{ usage?.watchlists.used || 0 }} / {{ usage?.watchlists.limit }}
                  </span>
                </div>
                <div class="w-full bg-nexo-bg rounded-full h-2 overflow-hidden mb-3">
                  <div
                    class="bg-primary-500 h-2 rounded-full transition-all duration-500"
                    :style="{ width: `${getUsagePercentage(usage?.watchlists.used || 0, usage?.watchlists.limit || 3)}%` }"
                  ></div>
                </div>
                <p class="text-xs text-nexo-muted">
                  Custom monitored entity portfolios across companies, executives, and technologies.
                </p>
              </Card>

              <!-- Active Monitors Quota -->
              <Card class="p-5 border border-nexo-border bg-nexo-surface">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs uppercase font-medium tracking-wider text-nexo-muted">Active Intelligence Monitors</span>
                  <span class="text-xs font-mono font-bold text-white">
                    {{ usage?.monitors.used || 0 }} / {{ usage?.monitors.limit }}
                  </span>
                </div>
                <div class="w-full bg-nexo-bg rounded-full h-2 overflow-hidden mb-3">
                  <div
                    class="bg-indigo-500 h-2 rounded-full transition-all duration-500"
                    :style="{ width: `${getUsagePercentage(usage?.monitors.used || 0, usage?.monitors.limit || 3)}%` }"
                  ></div>
                </div>
                <p class="text-xs text-nexo-muted">
                  Automated background radars scanning for real-time market shifts and disclosures.
                </p>
              </Card>

              <!-- Daily Research Queries -->
              <Card class="p-5 border border-nexo-border bg-nexo-surface">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs uppercase font-medium tracking-wider text-nexo-muted">Daily RAG & AI Queries</span>
                  <span class="text-xs font-mono font-bold text-white">
                    {{ usage?.daily_queries.used || 0 }} / {{ usage?.daily_queries.limit }}
                  </span>
                </div>
                <div class="w-full bg-nexo-bg rounded-full h-2 overflow-hidden mb-3">
                  <div
                    class="bg-emerald-500 h-2 rounded-full transition-all duration-500"
                    :style="{ width: `${getUsagePercentage(usage?.daily_queries.used || 0, usage?.daily_queries.limit || 10)}%` }"
                  ></div>
                </div>
                <p class="text-xs text-nexo-muted">
                  Grounded research questions backed by verified facts and story clusters. Resets at midnight UTC.
                </p>
              </Card>
            </div>

            <!-- Feature Matrix Access Status -->
            <Card class="p-6 border border-nexo-border bg-nexo-surface">
              <h3 class="text-sm font-semibold uppercase tracking-wider text-white mb-4 flex items-center gap-2">
                <Zap class="w-4 h-4 text-primary-400" />
                Active Platform Capabilities
              </h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-xs">
                <div class="flex items-center gap-3 p-3 rounded-lg bg-nexo-bg/60 border border-nexo-border/40">
                  <CheckCircle2
                    :class="entitlements?.features.ai_deep_research ? 'text-emerald-400' : 'text-nexo-muted/40'"
                    class="w-5 h-5 flex-shrink-0"
                  />
                  <div>
                    <span class="font-medium text-white block">AI Deep Research Dossiers</span>
                    <span class="text-nexo-muted">Multi-step synthesis and executive movement analysis</span>
                  </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-lg bg-nexo-bg/60 border border-nexo-border/40">
                  <CheckCircle2
                    :class="entitlements?.features.pdf_downloads ? 'text-emerald-400' : 'text-nexo-muted/40'"
                    class="w-5 h-5 flex-shrink-0"
                  />
                  <div>
                    <span class="font-medium text-white block">Executive PDF Export Engine</span>
                    <span class="text-nexo-muted">Download branded intelligence briefings and dossiers</span>
                  </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-lg bg-nexo-bg/60 border border-nexo-border/40">
                  <CheckCircle2
                    :class="entitlements?.features.publication_access ? 'text-emerald-400' : 'text-nexo-muted/40'"
                    class="w-5 h-5 flex-shrink-0"
                  />
                  <div>
                    <span class="font-medium text-white block">Institutional Publications</span>
                    <span class="text-nexo-muted">Global Executive Briefing and thematic whitepapers</span>
                  </div>
                </div>
              </div>
            </Card>
          </div>

          <!-- TAB 2: AVAILABLE PLANS & PRICING -->
          <div v-if="activeTab === 'plans'" class="space-y-6">
            <!-- Billing Cycle Switcher -->
            <div class="flex items-center justify-center gap-3 py-2">
              <span :class="selectedCycle === 'monthly' ? 'text-white font-medium' : 'text-nexo-muted'" class="text-sm">
                Monthly Billing
              </span>
              <button
                @click="selectedCycle = selectedCycle === 'monthly' ? 'annual' : 'monthly'"
                class="relative inline-flex h-6 w-11 items-center rounded-full bg-nexo-surface border border-nexo-border transition-colors focus:outline-none"
              >
                <span
                  :class="selectedCycle === 'annual' ? 'translate-x-6 bg-primary-500' : 'translate-x-1 bg-nexo-muted'"
                  class="inline-block h-4 w-4 transform rounded-full transition-transform"
                />
              </button>
              <span :class="selectedCycle === 'annual' ? 'text-white font-medium' : 'text-nexo-muted'" class="text-sm flex items-center gap-1.5">
                Annual Billing
                <span class="text-[10px] font-bold text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded-full border border-emerald-800/40">
                  SAVE UP TO 20%
                </span>
              </span>
            </div>

            <!-- Plan Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <Card
                v-for="plan in plans"
                :key="plan.id"
                :class="[
                  'p-6 flex flex-col justify-between border transition-all duration-200 relative',
                  plan.is_popular
                    ? 'border-primary-500/80 bg-gradient-to-b from-primary-950/20 to-nexo-surface shadow-lg shadow-primary-950/30'
                    : 'border-nexo-border bg-nexo-surface'
                ]"
              >
                <div v-if="plan.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2">
                  <span class="bg-primary-600 text-white text-[11px] font-bold uppercase tracking-wider px-3 py-0.5 rounded-full shadow">
                    Most Popular
                  </span>
                </div>

                <div>
                  <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-bold text-white">{{ plan.name }}</h3>
                    <span v-if="currentSub?.plan?.id === plan.id" class="px-2 py-0.5 rounded text-[10px] font-mono border bg-emerald-950/80 text-emerald-400 border-emerald-800/40">
                      Current
                    </span>
                  </div>
                  <p class="text-xs text-nexo-muted min-h-[36px] mb-4">
                    {{ plan.description }}
                  </p>

                  <!-- Price Display -->
                  <div class="mb-6 pb-6 border-b border-nexo-border/60">
                    <div class="flex items-baseline gap-1">
                      <span class="text-3xl font-extrabold text-white">
                        {{ selectedCurrency === 'BDT' ? '৳' : '$' }}{{ getPlanPrice(plan, selectedCycle, selectedCurrency) }}
                      </span>
                      <span class="text-xs text-nexo-muted">
                        / {{ selectedCycle === 'annual' ? 'year' : 'month' }}
                      </span>
                    </div>
                    <span v-if="plan.trial_period_days" class="text-[11px] text-primary-400 font-medium block mt-1">
                      Includes {{ plan.trial_period_days }}-day complimentary trial
                    </span>
                  </div>

                  <!-- Entitlements Checklist -->
                  <ul class="space-y-2.5 text-xs text-nexo-muted mb-6">
                    <li v-for="ent in plan.entitlements" :key="ent.id" class="flex items-center gap-2">
                      <Check class="w-4 h-4 text-emerald-400 flex-shrink-0" />
                      <span>
                        <strong class="text-white capitalize">{{ ent.feature_key.replace(/_/g, ' ') }}:</strong>
                        {{ ent.value === 'unlimited' ? 'Unlimited' : ent.value }}
                      </span>
                    </li>
                  </ul>
                </div>

                <!-- CTA Button -->
                <div>
                  <Button
                    v-if="currentSub?.plan?.id === plan.id"
                    variant="outline"
                    class="w-full text-nexo-muted cursor-default border-nexo-border/40"
                    disabled
                  >
                    Your Current Plan
                  </Button>
                  <Button
                    v-else
                    @click="openCheckout(plan)"
                    :variant="plan.is_popular ? 'primary' : 'outline'"
                    class="w-full"
                  >
                    {{ getPlanPrice(plan, selectedCycle, selectedCurrency) === 0 ? 'Activate Free' : 'Choose ' + plan.name }}
                  </Button>
                </div>
              </Card>
            </div>
          </div>

          <!-- TAB 3: INVOICES & RECEIPTS -->
          <div v-if="activeTab === 'invoices'" class="space-y-4">
            <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
              <div class="p-4 border-b border-nexo-border flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Subscription Billing History</h3>
                <span class="text-xs text-nexo-muted">Downloadable PDF receipts with full tax and transaction details</span>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead class="bg-nexo-bg/60 text-nexo-muted uppercase tracking-wider font-mono">
                    <tr>
                      <th class="p-3.5">Invoice #</th>
                      <th class="p-3.5">Date</th>
                      <th class="p-3.5">Amount</th>
                      <th class="p-3.5">Status</th>
                      <th class="p-3.5 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-nexo-border/40 text-nexo-text">
                    <tr v-if="invoices.length === 0">
                      <td colspan="5" class="p-8 text-center text-nexo-muted">
                        No billing invoices generated yet.
                      </td>
                    </tr>
                    <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-nexo-surface/80 transition-colors">
                      <td class="p-3.5 font-mono font-medium text-white">
                        {{ inv.invoice_number }}
                      </td>
                      <td class="p-3.5 text-nexo-muted">
                        {{ new Date(inv.paid_at || inv.due_date || '').toLocaleDateString() }}
                      </td>
                      <td class="p-3.5 font-mono font-semibold text-white">
                        {{ inv.currency === 'BDT' ? '৳' : '$' }}{{ Number(inv.total).toFixed(2) }}
                      </td>
                      <td class="p-3.5">
                        <span
                          :class="inv.status === 'paid' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-amber-950/80 text-amber-400 border-amber-800/40'"
                          class="px-2 py-0.5 rounded text-[11px] font-mono border"
                        >
                          {{ inv.status.toUpperCase() }}
                        </span>
                      </td>
                      <td class="p-3.5 text-right space-x-2">
                        <a
                          :href="billingService.getInvoiceDownloadUrl(inv.id)"
                          target="_blank"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-nexo-bg hover:bg-nexo-border text-nexo-text hover:text-white transition-colors"
                        >
                          <Download class="w-3.5 h-3.5" />
                          PDF Receipt
                        </a>
                        <Button
                          v-if="inv.status === 'unpaid'"
                          @click="openProofModal(inv.id)"
                          size="sm"
                          variant="primary"
                          class="text-xs py-1"
                        >
                          Submit Proof
                        </Button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </Card>
          </div>

        </div>
      </main>
    </div>

    <!-- CHECKOUT MODAL -->
    <Modal
      :isOpen="isCheckoutModalOpen"
      :title="`Subscribe to ${targetPlan?.name || 'Plan'}`"
      maxWidth="lg"
      @close="isCheckoutModalOpen = false"
    >
      <div v-if="targetPlan" class="space-y-5 text-left">
        <!-- Order Summary Box -->
        <div class="p-4 rounded-lg bg-nexo-bg border border-nexo-border space-y-2 text-xs">
          <div class="flex justify-between text-nexo-muted">
            <span>Selected Plan:</span>
            <span class="text-white font-medium">{{ targetPlan.name }} ({{ selectedCycle }})</span>
          </div>
          <div class="flex justify-between text-nexo-muted">
            <span>Subtotal:</span>
            <span class="text-white font-mono font-medium">
              {{ selectedCurrency === 'BDT' ? '৳' : '$' }}{{ getPlanPrice(targetPlan, selectedCycle, selectedCurrency) }}
            </span>
          </div>
          <div v-if="couponDiscount" class="flex justify-between text-emerald-400">
            <span>Promotional Discount ({{ couponDiscount.name }}):</span>
            <span class="font-mono font-semibold">
              -{{ selectedCurrency === 'BDT' ? '৳' : '$' }}{{ couponDiscount.discount_amount }}
            </span>
          </div>
          <div class="pt-2 border-t border-nexo-border flex justify-between text-sm font-bold text-white">
            <span>Total Payable:</span>
            <span class="text-primary-400 font-mono">
              {{ selectedCurrency === 'BDT' ? '৳' : '$' }}{{ computedCheckoutTotal.toFixed(2) }}
            </span>
          </div>
        </div>

        <!-- Coupon Code Section -->
        <div class="space-y-1.5">
          <label class="text-xs font-medium text-nexo-muted">Promotional Coupon</label>
          <div class="flex gap-2">
            <Input
              v-model="couponCode"
              placeholder="e.g. LAUNCH20"
              class="uppercase font-mono text-xs"
            />
            <Button
              @click="handleApplyCoupon"
              :disabled="isCheckingCoupon || !couponCode.trim()"
              variant="outline"
              size="sm"
            >
              {{ isCheckingCoupon ? 'Verifying...' : 'Apply' }}
            </Button>
          </div>
          <p v-if="couponError" class="text-xs text-rose-400 mt-1">{{ couponError }}</p>
          <p v-if="couponDiscount" class="text-xs text-emerald-400 mt-1 flex items-center gap-1">
            <Check class="w-3.5 h-3.5" /> Coupon applied: {{ couponDiscount.discount_amount }} discount
          </p>
        </div>

        <!-- Regional Payment Method Selection -->
        <div class="space-y-2">
          <label class="text-xs font-medium text-nexo-muted">Select Payment Method</label>
          <div class="grid grid-cols-1 gap-2.5">
            <div
              v-for="method in eligibleMethods"
              :key="method.id"
              @click="selectedMethod = method.id"
              :class="[
                'p-3 rounded-lg border cursor-pointer transition-all flex items-center justify-between',
                selectedMethod === method.id
                  ? 'border-primary-500 bg-primary-950/20'
                  : 'border-nexo-border bg-nexo-surface hover:border-nexo-border/80'
              ]"
            >
              <div class="flex items-center gap-3">
                <Smartphone v-if="method.type === 'mfs'" class="w-5 h-5 text-primary-400" />
                <CreditCard v-else-if="method.type === 'card'" class="w-5 h-5 text-primary-400" />
                <Building2 v-else class="w-5 h-5 text-primary-400" />
                <div>
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-white">{{ method.name }}</span>
                    <span v-if="method.badge" class="text-[10px] px-1.5 py-0 rounded bg-primary-950 text-primary-400 border border-primary-800 font-mono">
                      {{ method.badge }}
                    </span>
                  </div>
                  <span class="text-[11px] text-nexo-muted block">{{ method.description }}</span>
                </div>
              </div>
              <div class="w-4 h-4 rounded-full border flex items-center justify-center" :class="selectedMethod === method.id ? 'border-primary-500 bg-primary-500' : 'border-nexo-border'">
                <div v-if="selectedMethod === method.id" class="w-1.5 h-1.5 rounded-full bg-white"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Error Feedback -->
        <Alert v-if="checkoutError" variant="danger" class="text-xs">
          {{ checkoutError }}
        </Alert>

        <!-- Success Feedback -->
        <div v-if="checkoutSuccess" class="p-3 bg-emerald-950/40 border border-emerald-800/40 rounded-lg text-xs space-y-2">
          <p class="font-bold text-emerald-400 flex items-center gap-1">
            <CheckCircle2 class="w-4 h-4" /> Checkout Initiated Successfully
          </p>
          <p class="text-nexo-muted" v-if="checkoutSuccess.redirect_url">
            Redirecting to secure gateway checkout...
          </p>
          <div v-else-if="checkoutSuccess.is_manual_instructions" class="space-y-1">
            <p class="text-white">Please submit your transfer reference to activate subscription:</p>
            <Button
              @click="openProofModal(checkoutSuccess.invoice.id, selectedMethod)"
              size="sm"
              variant="primary"
              class="w-full mt-2"
            >
              Submit Transfer Proof (TrxID)
            </Button>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-3 border-t border-nexo-border">
          <Button @click="isCheckoutModalOpen = false" variant="ghost">Cancel</Button>
          <Button
            @click="handleExecuteCheckout"
            :disabled="isCheckingOut"
            variant="primary"
          >
            {{ isCheckingOut ? 'Processing Checkout...' : 'Confirm & Proceed' }}
          </Button>
        </div>
      </div>
    </Modal>

    <!-- MANUAL PAYMENT PROOF SUBMISSION MODAL -->
    <Modal
      :isOpen="isProofModalOpen"
      title="Submit Manual Payment Proof"
      maxWidth="md"
      @close="isProofModalOpen = false"
    >
      <div class="space-y-4 text-left">
        <p class="text-xs text-nexo-muted">
          Our compliance desk verifies transaction reference IDs before unlocking full subscription access.
        </p>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-nexo-muted">Payment Method</label>
          <select
            v-model="proofMethod"
            class="w-full bg-nexo-bg border border-nexo-border rounded-md px-3 py-2 text-xs text-white focus:outline-none focus:border-primary-500"
          >
            <option value="manual_bkash">bKash Merchant / Personal Transfer</option>
            <option value="manual_nagad">Nagad Digital Transfer</option>
            <option value="manual_bank">Corporate Bank Wire (City Bank / SCB)</option>
          </select>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-nexo-muted">Transaction ID (TrxID) *</label>
          <Input
            v-model="proofTrxId"
            placeholder="e.g. BL8291X009 or WIRE-REF-9921"
            class="font-mono text-xs"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-nexo-muted">Sender Account / Mobile Number</label>
          <Input
            v-model="proofSenderAccount"
            placeholder="e.g. +8801700000000 or Account #4421"
            class="text-xs"
          />
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-nexo-muted">Additional Reference Notes</label>
          <Input
            v-model="proofNotes"
            placeholder="Optional branch or company reference"
            class="text-xs"
          />
        </div>

        <Alert v-if="proofError" variant="danger" class="text-xs">{{ proofError }}</Alert>
        <Alert v-if="proofSuccess" variant="success" class="text-xs">{{ proofSuccess }}</Alert>

        <div class="flex justify-end gap-3 pt-3 border-t border-nexo-border">
          <Button @click="isProofModalOpen = false" variant="ghost">Close</Button>
          <Button
            @click="handleSubmitProof"
            :disabled="isSubmittingProof || !proofTrxId.trim()"
            variant="primary"
          >
            {{ isSubmittingProof ? 'Submitting...' : 'Submit Reference' }}
          </Button>
        </div>
      </div>
    </Modal>

    <!-- SUBSCRIPTION CANCELLATION MODAL -->
    <Modal
      :isOpen="isCancelModalOpen"
      title="Cancel Subscription"
      maxWidth="md"
      @close="isCancelModalOpen = false"
    >
      <div class="space-y-4 text-left">
        <p class="text-xs text-nexo-muted">
          Are you sure you wish to cancel your subscription? You can choose to maintain access until the end of your billing cycle.
        </p>

        <div class="space-y-2">
          <label class="flex items-center gap-2 cursor-pointer text-xs text-white">
            <input
              type="radio"
              :value="true"
              v-model="cancelAtPeriodEnd"
              class="text-primary-500 focus:ring-0"
            />
            <span>Cancel at end of current period (Recommended — keep access until {{ currentSub?.ends_at ? new Date(currentSub.ends_at).toLocaleDateString() : 'expiry' }})</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer text-xs text-white">
            <input
              type="radio"
              :value="false"
              v-model="cancelAtPeriodEnd"
              class="text-primary-500 focus:ring-0"
            />
            <span>Cancel immediately (Revoke access now)</span>
          </label>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-medium text-nexo-muted">Cancellation Reason</label>
          <Input
            v-model="cancelReason"
            placeholder="Tell us what we can improve..."
            class="text-xs"
          />
        </div>

        <Alert v-if="cancelError" variant="danger" class="text-xs">{{ cancelError }}</Alert>

        <div class="flex justify-end gap-3 pt-3 border-t border-nexo-border">
          <Button @click="isCancelModalOpen = false" variant="ghost">Keep Subscription</Button>
          <Button
            @click="handleCancelSubscription"
            :disabled="isCanceling"
            variant="danger"
          >
            {{ isCanceling ? 'Canceling...' : 'Confirm Cancellation' }}
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
