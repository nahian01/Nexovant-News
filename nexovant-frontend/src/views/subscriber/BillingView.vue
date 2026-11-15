<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
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
  type SubscriberOrganization,
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
  FileText,
  AlertTriangle,
  Sliders,
  Users,
  ChevronRight,
  ExternalLink
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()

// State
const isLoading = ref(true)
const activeTab = ref<'overview' | 'plans' | 'history' | 'methods' | 'organization'>('overview')
const currentSub = ref<SubscriberSubscription | null>(null)
const organization = ref<SubscriberOrganization | null>(null)
const entitlements = ref<EntitlementsSnapshot | null>(null)
const usage = ref<BillingUsage | null>(null)
const plans = ref<BillingPlan[]>([])
const invoices = ref<BillingInvoice[]>([])
const eligibleMethods = ref<PaymentMethodOption[]>([])

// Currency & Billing Cycle selection
const selectedCycle = ref<'monthly' | 'annual'>('monthly')
const selectedCurrency = ref<'USD' | 'BDT'>('USD')

// Checkout / Proration Modal State
const isProrationModalOpen = ref(false)
const targetPlan = ref<BillingPlan | null>(null)
const prorationData = ref<any>(null)
const isCalculatingProration = ref(false)
const selectedMethod = ref<string>('stripe')
const couponCode = ref('')
const couponDiscount = ref<any>(null)
const couponError = ref('')
const isCheckingCoupon = ref(false)
const isExecutingPlanChange = ref(false)
const checkoutError = ref('')
const checkoutSuccess = ref<any>(null)

// Trial Activation State
const isStartingTrial = ref(false)
const trialSuccessMessage = ref('')
const trialErrorMessage = ref('')

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
const cancelReason = ref('Pricing / budget constraints')
const isCanceling = ref(false)
const cancelError = ref('')
const isReactivating = ref(false)

// Tab switcher with query sync
const setActiveTab = (tab: 'overview' | 'plans' | 'history' | 'methods' | 'organization') => {
  activeTab.value = tab
  router.replace({ query: { ...route.query, tab } })
}

watch(selectedCurrency, async (newCurr) => {
  try {
    const country = newCurr === 'BDT' ? 'BD' : 'US'
    const res = await billingService.getPaymentMethods({
      country,
      currency: newCurr,
      interval: selectedCycle.value
    })
    if (res.success) {
      eligibleMethods.value = res.data || []
    }
  } catch (err) {
    console.warn('Failed to update regional payment methods', err)
  }
})

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
      organization.value = overviewRes.data.organization || null
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

// Plan Pricing Helpers
const getPlanPrice = (plan: BillingPlan, cycle: 'monthly' | 'annual', curr: 'USD' | 'BDT'): number => {
  if (curr === 'BDT') {
    return cycle === 'annual' ? Number(plan.price_bdt_annual) : Number(plan.price_bdt_monthly)
  }
  return cycle === 'annual' ? Number(plan.price_usd_annual) : Number(plan.price_usd_monthly)
}

const formatCurrency = (amount: number, curr: 'USD' | 'BDT'): string => {
  if (curr === 'BDT') {
    return `৳${amount.toLocaleString()}`
  }
  return `$${amount.toLocaleString()}`
}

// Open Plan Change or Upgrade Modal with Proration Preview
const openPlanChangeModal = async (plan: BillingPlan) => {
  targetPlan.value = plan
  couponCode.value = ''
  couponDiscount.value = null
  couponError.value = ''
  checkoutError.value = ''
  checkoutSuccess.value = null
  prorationData.value = null
  isProrationModalOpen.value = true

  // If user has an active paid subscription, fetch proration preview
  if (currentSub.value?.is_active && currentSub.value.plan?.slug !== 'free') {
    isCalculatingProration.value = true
    try {
      const res = await billingService.previewProration(plan.id, selectedCycle.value)
      if (res.success) {
        prorationData.value = res.data
      }
    } catch (err) {
      console.warn('Proration preview error', err)
    } finally {
      isCalculatingProration.value = false
    }
  }

  // Fetch payment methods matching current currency
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
}

const handleApplyCoupon = async () => {
  if (!couponCode.value.trim() || !targetPlan.value) return

  isCheckingCoupon.value = true
  couponError.value = ''
  try {
    const subtotal = prorationData.value?.amount_due !== undefined
      ? Number(prorationData.value.amount_due)
      : getPlanPrice(targetPlan.value, selectedCycle.value, selectedCurrency.value)

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
    couponError.value = err.response?.data?.message || err.response?.data?.errors?.coupon?.[0] || 'Invalid or expired promotional code.'
  } finally {
    isCheckingCoupon.value = false
  }
}

const computedImmediateTotal = computed(() => {
  if (!targetPlan.value) return 0
  const base = prorationData.value?.amount_due !== undefined
    ? Number(prorationData.value.amount_due)
    : getPlanPrice(targetPlan.value, selectedCycle.value, selectedCurrency.value)

  if (couponDiscount.value?.discount_amount) {
    return Math.max(0, base - Number(couponDiscount.value.discount_amount))
  }
  return base
})

const handleExecutePlanChange = async () => {
  if (!targetPlan.value) return

  isExecutingPlanChange.value = true
  checkoutError.value = ''
  try {
    // If existing active subscription, execute plan change
    if (currentSub.value?.is_active && currentSub.value.plan?.slug !== 'free') {
      const res = await billingService.changePlan(targetPlan.value.id, selectedCycle.value)
      if (res.success) {
        checkoutSuccess.value = res.data
        await fetchBillingData()
        setTimeout(() => {
          isProrationModalOpen.value = false
        }, 1500)
      }
    } else {
      // First-time or new checkout
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
        if (selectedMethod.value.includes('bank') || selectedMethod.value.includes('bkash')) {
          proofInvoiceId.value = res.data.invoice?.id || ''
          proofMethod.value = selectedMethod.value
        }
        await fetchBillingData()
        setTimeout(() => {
          isProrationModalOpen.value = false
        }, 1500)
      }
    }
  } catch (err: any) {
    checkoutError.value = err.response?.data?.message || 'Plan update failed. Please verify selected payment method.'
  } finally {
    isExecutingPlanChange.value = false
  }
}

// Start Free Trial
const handleStartTrial = async (plan: BillingPlan) => {
  isStartingTrial.value = true
  trialSuccessMessage.value = ''
  trialErrorMessage.value = ''
  try {
    const res = await billingService.startTrial(plan.id)
    if (res.success) {
      trialSuccessMessage.value = res.message || `Your 14-day evaluation trial of ${plan.name} has started.`
      await fetchBillingData()
      setActiveTab('overview')
    }
  } catch (err: any) {
    trialErrorMessage.value = err.response?.data?.message || 'Unable to start trial. Account may already have used trial eligibility.'
  } finally {
    isStartingTrial.value = false
  }
}

// Manual Payment Proof
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

// Cancel & Reactivate
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
  isReactivating.value = true
  try {
    const res = await billingService.reactivateSubscription()
    if (res.success) {
      await fetchBillingData()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to reactivate subscription.')
  } finally {
    isReactivating.value = false
  }
}

// Authorized Invoice PDF Download
const handleDownloadInvoice = async (invoiceId: string, invoiceNumber: string) => {
  try {
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token')
    const response = await fetch(`/api/v1/billing/invoices/${invoiceId}/download`, {
      headers: {
        'Accept': 'application/pdf',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {})
      }
    })
    if (!response.ok) {
      throw new Error(`Failed to download invoice (HTTP ${response.status})`)
    }
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${invoiceNumber}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (err: any) {
    alert(err.message || 'Failed to download invoice receipt.')
  }
}

// Usage Metric Calculations
const getUsagePercentage = (used: number, limit: number | string): number => {
  if (limit === 'unlimited' || limit === 999999) return 15
  const numLimit = Number(limit)
  if (numLimit <= 0) return 0
  return Math.min(100, Math.round((used / numLimit) * 100))
}

const getMeterBarColor = (pct: number): string => {
  if (pct >= 80) return 'bg-rose-500'
  if (pct >= 60) return 'bg-amber-500'
  return 'bg-emerald-500'
}

onMounted(() => {
  if (route.query.tab && ['overview', 'plans', 'history', 'methods', 'organization'].includes(route.query.tab as string)) {
    activeTab.value = route.query.tab as any
  } else if (route.path === '/billing/history') {
    activeTab.value = 'history'
  }
  fetchBillingData()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="false">
    <div class="max-w-7xl mx-auto space-y-6 text-left">

      <!-- Header & Currency Switcher -->
      <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-nexo-border gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-[10px] font-mono uppercase tracking-widest font-semibold px-2 py-0.5 rounded bg-primary-950/60 text-primary-400 border border-primary-800/40">
              Stage 9 Intelligence Telemetry
            </span>
            <span class="text-xs text-nexo-muted font-sans">Dual-Currency & Multi-Tenant Entitlements</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-serif font-bold tracking-tight text-white flex items-center gap-2.5">
            <CreditCard class="w-7 h-7 text-primary-400" />
            Subscription & Quota Management
          </h1>
          <p class="text-xs md:text-sm text-nexo-muted mt-1 max-w-2xl">
            Monitor real-time intelligence telemetry, evaluate plan tiers, manage trial periods, access PDF receipts, and update multi-regional payment methods.
          </p>
        </div>

        <!-- Currency Selector Toggle & Intelligent Checkout Shortcut -->
        <div class="flex items-center gap-2 self-start md:self-auto">
          <router-link
            to="/checkout"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-xl bg-primary-600/20 text-primary-400 border border-primary-500/30 hover:bg-primary-600/30 transition-colors shadow-sm"
          >
            <Sparkles class="w-3.5 h-3.5" />
            Intelligent Checkout
          </router-link>
          <div class="flex items-center bg-nexo-surface border border-nexo-border rounded-xl p-1 shadow-sm">
            <button
              @click="selectedCurrency = 'USD'"
              :class="[
                'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all',
                selectedCurrency === 'USD'
                  ? 'bg-primary-600 text-white shadow-sm font-semibold'
                  : 'text-nexo-muted hover:text-white'
              ]"
            >
              USD ($) Global
            </button>
            <button
              @click="selectedCurrency = 'BDT'"
              :class="[
                'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all',
                selectedCurrency === 'BDT'
                  ? 'bg-primary-600 text-white shadow-sm font-semibold'
                  : 'text-nexo-muted hover:text-white'
              ]"
            >
              BDT (৳) Bangladesh
            </button>
          </div>
        </div>
      </div>

      <!-- Lifecycle Alert Banners -->
      <!-- 1. Trial Expiration Banner -->
      <div
        v-if="currentSub?.status === 'trialing' || currentSub?.is_trialing"
        class="p-4 rounded-xl border border-amber-500/30 bg-amber-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      >
        <div class="flex items-start sm:items-center gap-3">
          <Clock class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5 sm:mt-0" />
          <div>
            <span class="text-xs font-semibold text-amber-300 uppercase tracking-wide block">Complimentary Evaluation Period Active</span>
            <p class="text-xs text-amber-200/80 mt-0.5">
              You have <strong class="text-amber-300 font-mono">{{ currentSub?.days_left_in_trial ?? 14 }} days remaining</strong> in your complimentary evaluation of Nexovant {{ currentSub?.plan?.name }}. Upgrade now to retain continuous intelligence alerts.
            </p>
          </div>
        </div>
        <Button
          @click="setActiveTab('plans')"
          size="sm"
          class="bg-amber-600 hover:bg-amber-500 text-white border-none flex-shrink-0"
        >
          <Sparkles class="w-3.5 h-3.5 mr-1.5" />
          Convert Trial to Permanent Tier
        </Button>
      </div>

      <!-- 2. Past Due Warning Banner -->
      <div
        v-if="currentSub?.status === 'past_due'"
        class="p-4 rounded-xl border border-rose-500/30 bg-rose-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      >
        <div class="flex items-start sm:items-center gap-3">
          <AlertCircle class="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5 sm:mt-0" />
          <div>
            <span class="text-xs font-semibold text-rose-300 uppercase tracking-wide block">Payment Renewal Unsuccessful</span>
            <p class="text-xs text-rose-200/80 mt-0.5">
              Your subscription renewal payment was declined. You are currently within the 3-day grace period. Settle the balance to prevent workspace suspension.
            </p>
          </div>
        </div>
        <Button
          @click="setActiveTab('history')"
          size="sm"
          class="bg-rose-600 hover:bg-rose-500 text-white border-none flex-shrink-0"
        >
          Settle Unpaid Balance
        </Button>
      </div>

      <!-- 3. Cancellation Scheduled Notice -->
      <div
        v-if="currentSub?.status === 'cancel_at_period_end'"
        class="p-4 rounded-xl border border-indigo-500/30 bg-indigo-950/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      >
        <div class="flex items-start sm:items-center gap-3">
          <Clock class="w-5 h-5 text-indigo-400 flex-shrink-0 mt-0.5 sm:mt-0" />
          <div>
            <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wide block">Cancellation Scheduled at Period End</span>
            <p class="text-xs text-indigo-200/80 mt-0.5">
              Auto-renewal has been stopped. You will retain unrestricted intelligence access until <strong class="text-white">{{ currentSub?.ends_at ? new Date(currentSub.ends_at).toLocaleDateString() : 'period end' }}</strong>.
            </p>
          </div>
        </div>
        <Button
          @click="handleReactivateSubscription"
          :disabled="isReactivating"
          size="sm"
          class="bg-emerald-600 hover:bg-emerald-500 text-white border-none flex-shrink-0"
        >
          <RefreshCw :class="{ 'animate-spin': isReactivating }" class="w-3.5 h-3.5 mr-1.5" />
          Reactivate Auto-Renewal
        </Button>
      </div>

      <!-- Success / Error Global Feedback -->
      <Alert v-if="trialSuccessMessage" type="success" class="mb-4" @close="trialSuccessMessage = ''">
        {{ trialSuccessMessage }}
      </Alert>
      <Alert v-if="trialErrorMessage" type="error" class="mb-4" @close="trialErrorMessage = ''">
        {{ trialErrorMessage }}
      </Alert>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-2 border-b border-nexo-border overflow-x-auto no-scrollbar text-xs md:text-sm">
        <button
          @click="setActiveTab('overview')"
          :class="[
            'pb-3 pt-1 px-3 font-medium transition-colors border-b-2 flex items-center gap-2 flex-shrink-0',
            activeTab === 'overview'
              ? 'border-primary-500 text-primary-400 font-semibold'
              : 'border-transparent text-nexo-muted hover:text-white'
          ]"
        >
          <Shield class="w-4 h-4" />
          Overview & Telemetry
        </button>
        <button
          @click="setActiveTab('plans')"
          :class="[
            'pb-3 pt-1 px-3 font-medium transition-colors border-b-2 flex items-center gap-2 flex-shrink-0',
            activeTab === 'plans'
              ? 'border-primary-500 text-primary-400 font-semibold'
              : 'border-transparent text-nexo-muted hover:text-white'
          ]"
        >
          <Sparkles class="w-4 h-4" />
          Compare Plans & Pricing
        </button>
        <button
          @click="setActiveTab('history')"
          :class="[
            'pb-3 pt-1 px-3 font-medium transition-colors border-b-2 flex items-center gap-2 flex-shrink-0',
            activeTab === 'history'
              ? 'border-primary-500 text-primary-400 font-semibold'
              : 'border-transparent text-nexo-muted hover:text-white'
          ]"
        >
          <FileText class="w-4 h-4" />
          Billing History & Invoices ({{ invoices.length }})
        </button>
        <button
          @click="setActiveTab('methods')"
          :class="[
            'pb-3 pt-1 px-3 font-medium transition-colors border-b-2 flex items-center gap-2 flex-shrink-0',
            activeTab === 'methods'
              ? 'border-primary-500 text-primary-400 font-semibold'
              : 'border-transparent text-nexo-muted hover:text-white'
          ]"
        >
          <CreditCard class="w-4 h-4" />
          Payment Gateways
        </button>
        <button
          @click="setActiveTab('organization')"
          :class="[
            'pb-3 pt-1 px-3 font-medium transition-colors border-b-2 flex items-center gap-2 flex-shrink-0',
            activeTab === 'organization'
              ? 'border-primary-500 text-primary-400 font-semibold'
              : 'border-transparent text-nexo-muted hover:text-white'
          ]"
        >
          <Building2 class="w-4 h-4" />
          Organization Billing
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="py-20 text-center text-nexo-muted">
        <RefreshCw class="w-8 h-8 animate-spin mx-auto mb-3 text-primary-500" />
        <p class="text-sm font-medium">Synchronizing entitlement quotas and financial telemetry...</p>
      </div>

      <!-- TAB CONTENTS -->
      <div v-else class="space-y-6">

        <!-- TAB 1: OVERVIEW & TELEMETRY -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
          <!-- Active Subscription Cockpit Card -->
          <Card class="p-6 md:p-8 bg-gradient-to-br from-nexo-surface via-nexo-surface to-nexo-surface/80 border border-nexo-border relative overflow-hidden">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
              <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-[10px] font-mono uppercase tracking-wider text-nexo-muted">Active Tier</span>
                  <span
                    :class="[
                      'px-2.5 py-0.5 rounded text-[11px] font-mono border font-semibold',
                      currentSub?.status === 'active' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' :
                      currentSub?.status === 'trialing' ? 'bg-amber-950/80 text-amber-400 border-amber-800/40' :
                      currentSub?.status === 'cancel_at_period_end' ? 'bg-indigo-950/80 text-indigo-400 border-indigo-800/40' :
                      currentSub?.status === 'past_due' ? 'bg-rose-950/80 text-rose-400 border-rose-800/40' :
                      'bg-nexo-bg text-nexo-muted border-nexo-border'
                    ]"
                  >
                    {{ currentSub?.status ? currentSub.status.toUpperCase().replace(/_/g, ' ') : 'FREE TIER' }}
                  </span>
                  <span v-if="currentSub?.auto_renew" class="px-2 py-0.5 rounded text-[10px] font-mono border bg-primary-950/60 text-primary-400 border-primary-800/40">
                    Auto-Renew ON
                  </span>
                  <span v-if="organization?.is_pooled" class="px-2 py-0.5 rounded text-[10px] font-mono border bg-cyan-950/60 text-cyan-400 border-cyan-800/40">
                    Organization Pooled ({{ organization.name }})
                  </span>
                </div>

                <h2 class="text-3xl font-serif font-bold text-white tracking-tight">
                  {{ currentSub?.plan?.name || entitlements?.plan_name || 'Free Tier Public Intelligence' }}
                </h2>

                <p class="text-xs md:text-sm text-nexo-muted max-w-2xl leading-relaxed">
                  {{ currentSub?.plan?.description || 'Essential baseline access to public news briefings, basic company watchlists, and standard entity timelines.' }}
                </p>

                <div class="pt-2 flex flex-wrap items-center gap-4 text-xs font-mono text-nexo-muted">
                  <div v-if="currentSub?.billing_cycle" class="flex items-center gap-1.5">
                    <span class="text-white capitalize">{{ currentSub.billing_cycle }} Cycle</span>
                  </div>
                  <div v-if="currentSub?.next_renewal_at" class="flex items-center gap-1.5">
                    <span>Next Renewal:</span>
                    <span class="text-white font-semibold">{{ new Date(currentSub.next_renewal_at).toLocaleDateString() }}</span>
                  </div>
                  <div v-if="currentSub?.starts_at" class="flex items-center gap-1.5">
                    <span>Member Since:</span>
                    <span class="text-white">{{ new Date(currentSub.starts_at).toLocaleDateString() }}</span>
                  </div>
                </div>
              </div>

              <!-- Quick Action Buttons -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <Button
                  @click="setActiveTab('plans')"
                  variant="primary"
                  class="shadow-lg shadow-primary-950/50 justify-center"
                >
                  <Sparkles class="w-4 h-4 mr-2" />
                  Upgrade or Change Tier
                </Button>
                <Button
                  v-if="!currentSub?.is_active || currentSub?.plan?.slug === 'free'"
                  @click="handleStartTrial(plans.find(p => p.slug === 'pro') || plans[1])"
                  :disabled="isStartingTrial"
                  variant="outline"
                  class="border-amber-500/50 text-amber-400 hover:bg-amber-950/30 justify-center"
                >
                  <Clock class="w-4 h-4 mr-2" />
                  Start 14-Day Free Trial
                </Button>
                <Button
                  v-if="currentSub?.status === 'cancel_at_period_end'"
                  @click="handleReactivateSubscription"
                  variant="outline"
                  class="border-emerald-500/50 text-emerald-400 hover:bg-emerald-950/30 justify-center"
                >
                  <RefreshCw class="w-4 h-4 mr-2" />
                  Reactivate Auto-Renewal
                </Button>
                <Button
                  v-if="currentSub?.is_active && currentSub?.status !== 'cancel_at_period_end' && currentSub?.plan?.slug !== 'free'"
                  @click="isCancelModalOpen = true"
                  variant="ghost"
                  class="text-rose-400 hover:bg-rose-950/20 hover:text-rose-300 justify-center text-xs"
                >
                  Cancel Plan
                </Button>
              </div>
            </div>
          </Card>

          <!-- Real-Time Entitlement Quotas & Usage Grid -->
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-semibold uppercase tracking-wider text-white flex items-center gap-2">
                <Sliders class="w-4 h-4 text-primary-400" />
                Live Entitlement & Quota Telemetry
              </h3>
              <span class="text-xs text-nexo-muted">Server-Authoritative Enforcement via EntitlementService</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Meter 1: Monitored Watchlists -->
              <Card class="p-5 bg-nexo-surface border border-nexo-border space-y-3">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-medium text-white flex items-center gap-1.5">
                    <Shield class="w-4 h-4 text-primary-400" />
                    Custom Watchlists
                  </span>
                  <span class="font-mono font-semibold text-white">
                    {{ usage?.watchlists.used ?? 0 }} / {{ usage?.watchlists.limit ?? 3 }}
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="getMeterBarColor(getUsagePercentage(usage?.watchlists.used ?? 0, usage?.watchlists.limit ?? 3))"
                    :style="{ width: `${getUsagePercentage(usage?.watchlists.used ?? 0, usage?.watchlists.limit ?? 3)}%` }"
                  />
                </div>
                <div class="flex items-center justify-between text-[11px] text-nexo-muted">
                  <span>{{ getUsagePercentage(usage?.watchlists.used ?? 0, usage?.watchlists.limit ?? 3) }}% Quota Utilized</span>
                  <button
                    v-if="getUsagePercentage(usage?.watchlists.used ?? 0, usage?.watchlists.limit ?? 3) >= 80"
                    @click="setActiveTab('plans')"
                    class="text-primary-400 hover:underline font-semibold"
                  >
                    Upgrade Limit &rarr;
                  </button>
                </div>
              </Card>

              <!-- Meter 2: Active Monitors -->
              <Card class="p-5 bg-nexo-surface border border-nexo-border space-y-3">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-medium text-white flex items-center gap-1.5">
                    <Zap class="w-4 h-4 text-amber-400" />
                    Active Intelligence Monitors
                  </span>
                  <span class="font-mono font-semibold text-white">
                    {{ usage?.monitors.used ?? 0 }} / {{ usage?.monitors.limit ?? 3 }}
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="getMeterBarColor(getUsagePercentage(usage?.monitors.used ?? 0, usage?.monitors.limit ?? 3))"
                    :style="{ width: `${getUsagePercentage(usage?.monitors.used ?? 0, usage?.monitors.limit ?? 3)}%` }"
                  />
                </div>
                <div class="flex items-center justify-between text-[11px] text-nexo-muted">
                  <span>{{ getUsagePercentage(usage?.monitors.used ?? 0, usage?.monitors.limit ?? 3) }}% Quota Utilized</span>
                  <button
                    v-if="getUsagePercentage(usage?.monitors.used ?? 0, usage?.monitors.limit ?? 3) >= 80"
                    @click="setActiveTab('plans')"
                    class="text-primary-400 hover:underline font-semibold"
                  >
                    Upgrade Limit &rarr;
                  </button>
                </div>
              </Card>

              <!-- Meter 3: Daily Research Queries -->
              <Card class="p-5 bg-nexo-surface border border-nexo-border space-y-3">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-medium text-white flex items-center gap-1.5">
                    <Sparkles class="w-4 h-4 text-cyan-400" />
                    Daily Research & RAG Queries
                  </span>
                  <span class="font-mono font-semibold text-white">
                    {{ usage?.daily_queries.used ?? 0 }} / {{ usage?.daily_queries.limit ?? 10 }}
                  </span>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="getMeterBarColor(getUsagePercentage(usage?.daily_queries.used ?? 0, usage?.daily_queries.limit ?? 10))"
                    :style="{ width: `${getUsagePercentage(usage?.daily_queries.used ?? 0, usage?.daily_queries.limit ?? 10)}%` }"
                  />
                </div>
                <div class="flex items-center justify-between text-[11px] text-nexo-muted">
                  <span>{{ getUsagePercentage(usage?.daily_queries.used ?? 0, usage?.daily_queries.limit ?? 10) }}% Used Today</span>
                  <span class="text-nexo-muted">Resets midnight UTC</span>
                </div>
              </Card>
            </div>
          </div>

          <!-- Feature Entitlement Capabilities Checklist -->
          <Card class="p-6 bg-nexo-surface border border-nexo-border space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-white">
              Entitlement Access Privileges
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
              <div class="flex items-center gap-2.5 p-3 rounded-lg bg-nexo-bg/60 border border-nexo-border/40">
                <CheckCircle2
                  :class="entitlements?.features.ai_deep_research ? 'text-emerald-400' : 'text-nexo-muted/40'"
                  class="w-4 h-4 flex-shrink-0"
                />
                <div>
                  <span class="font-medium text-white block">AI Deep Research Engine</span>
                  <span class="text-[11px] text-nexo-muted">Autonomous entity synthesis</span>
                </div>
              </div>
              <div class="flex items-center gap-2.5 p-3 rounded-lg bg-nexo-bg/60 border border-nexo-border/40">
                <CheckCircle2
                  :class="entitlements?.features.pdf_downloads ? 'text-emerald-400' : 'text-nexo-muted/40'"
                  class="w-4 h-4 flex-shrink-0"
                />
                <div>
                  <span class="font-medium text-white block">Executive PDF Dossiers</span>
                  <span class="text-[11px] text-nexo-muted">Download publication briefs</span>
                </div>
              </div>
              <div class="flex items-center gap-2.5 p-3 rounded-lg bg-nexo-bg/60 border border-nexo-border/40">
                <CheckCircle2
                  :class="entitlements?.features.publication_access ? 'text-emerald-400' : 'text-nexo-muted/40'"
                  class="w-4 h-4 flex-shrink-0"
                />
                <div>
                  <span class="font-medium text-white block">Institutional Publications</span>
                  <span class="text-[11px] text-nexo-muted">Subscriber-only research</span>
                </div>
              </div>
              <div class="flex items-center gap-2.5 p-3 rounded-lg bg-nexo-bg/60 border border-nexo-border/40">
                <CheckCircle2
                  class="w-4 h-4 flex-shrink-0 text-emerald-400"
                />
                <div>
                  <span class="font-medium text-white block">Timeline Intelligence</span>
                  <span class="text-[11px] text-nexo-muted">{{ entitlements?.limits.historical_timeline_days ?? 90 }} days historical depth</span>
                </div>
              </div>
            </div>
          </Card>
        </div>

        <!-- TAB 2: PLANS COMPARISON & UPGRADES -->
        <div v-if="activeTab === 'plans'" class="space-y-6">
          <!-- Interval Toggle -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-xl bg-nexo-surface border border-nexo-border">
            <div>
              <h3 class="text-sm font-semibold text-white">Select Billing Cadence</h3>
              <p class="text-xs text-nexo-muted mt-0.5">Annual subscriptions include an automatic ~20% institutional savings discount.</p>
            </div>

            <div class="flex items-center bg-nexo-bg border border-nexo-border rounded-xl p-1">
              <button
                @click="selectedCycle = 'monthly'"
                :class="[
                  'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all',
                  selectedCycle === 'monthly'
                    ? 'bg-primary-600 text-white font-semibold'
                    : 'text-nexo-muted hover:text-white'
                ]"
              >
                Monthly Cadence
              </button>
              <button
                @click="selectedCycle = 'annual'"
                :class="[
                  'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all flex items-center gap-1.5',
                  selectedCycle === 'annual'
                    ? 'bg-primary-600 text-white font-semibold'
                    : 'text-nexo-muted hover:text-white'
                ]"
              >
                Annual Cadence
                <span class="px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 text-[10px] font-bold">
                  SAVE 20%
                </span>
              </button>
            </div>
          </div>

          <!-- Plans Grid -->
          <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <Card
              v-for="plan in plans"
              :key="plan.id"
              :class="[
                'p-5 flex flex-col justify-between transition-all border',
                currentSub?.plan?.id === plan.id
                  ? 'border-primary-500 bg-primary-950/20 shadow-lg'
                  : plan.is_popular
                    ? 'border-amber-500/40 bg-nexo-surface shadow-md'
                    : 'border-nexo-border bg-nexo-surface'
              ]"
            >
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-xs uppercase font-mono font-bold text-nexo-muted">
                    {{ plan.name }}
                  </span>
                  <span
                    v-if="currentSub?.plan?.id === plan.id"
                    class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-950/80 text-emerald-400 border border-emerald-800/40 font-bold"
                  >
                    CURRENT TIER
                  </span>
                  <span
                    v-else-if="plan.is_popular"
                    class="text-[10px] font-mono px-2 py-0.5 rounded bg-amber-950/80 text-amber-400 border border-amber-800/40 font-bold"
                  >
                    POPULAR
                  </span>
                </div>

                <div>
                  <div class="text-2xl font-serif font-bold text-white tracking-tight">
                    {{ formatCurrency(getPlanPrice(plan, selectedCycle, selectedCurrency), selectedCurrency) }}
                  </div>
                  <div class="text-[11px] text-nexo-muted mt-0.5">
                    per {{ selectedCycle === 'annual' ? 'year' : 'month' }}
                  </div>
                </div>

                <p class="text-xs text-nexo-muted leading-relaxed">
                  {{ plan.description || 'Institutional-grade intelligence access.' }}
                </p>

                <!-- Features list -->
                <div class="pt-3 border-t border-nexo-border/60 space-y-2 text-xs">
                  <div
                    v-for="(ent, idx) in plan.entitlements?.slice(0, 5)"
                    :key="idx"
                    class="flex items-start gap-2 text-nexo-muted"
                  >
                    <Check class="w-3.5 h-3.5 text-primary-400 flex-shrink-0 mt-0.5" />
                    <span class="text-[11px]">
                      {{ ent.feature_key.replace(/_/g, ' ') }}: <strong class="text-white">{{ ent.value }}</strong>
                    </span>
                  </div>
                </div>
              </div>

              <!-- Action Button -->
              <div class="pt-6">
                <Button
                  v-if="currentSub?.plan?.id === plan.id"
                  disabled
                  variant="outline"
                  class="w-full text-xs justify-center opacity-60 cursor-not-allowed"
                >
                  Active Plan
                </Button>
                <Button
                  v-else-if="plan.slug === 'free'"
                  disabled
                  variant="ghost"
                  class="w-full text-xs justify-center opacity-60 cursor-not-allowed"
                >
                  Baseline Included
                </Button>
                <Button
                  v-else
                  @click="openPlanChangeModal(plan)"
                  :variant="plan.is_popular ? 'primary' : 'outline'"
                  class="w-full text-xs justify-center font-semibold"
                >
                  {{ (currentSub?.is_active && currentSub?.plan?.slug !== 'free') ? 'Select Tier' : 'Upgrade Now' }}
                </Button>
              </div>
            </Card>
          </div>
        </div>

        <!-- TAB 3: BILLING HISTORY & INVOICES -->
        <div v-if="activeTab === 'history'" class="space-y-4">
          <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
            <div class="p-5 border-b border-nexo-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <h3 class="text-sm font-semibold text-white">Invoice Telemetry & Receipts</h3>
                <span class="text-xs text-nexo-muted">Downloadable PDF receipts with full financial audits and tax breakdowns</span>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="border-b border-nexo-border bg-nexo-bg/40 text-nexo-muted font-mono uppercase text-[10px]">
                    <th class="p-3.5">Invoice #</th>
                    <th class="p-3.5">Date</th>
                    <th class="p-3.5">Plan / Description</th>
                    <th class="p-3.5">Amount</th>
                    <th class="p-3.5">Status</th>
                    <th class="p-3.5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-nexo-border/60">
                  <tr v-if="invoices.length === 0">
                    <td colspan="6" class="p-8 text-center text-nexo-muted">
                      No invoices recorded yet. Active billing records will appear here upon completion.
                    </td>
                  </tr>
                  <tr
                    v-for="inv in invoices"
                    :key="inv.id"
                    class="hover:bg-nexo-bg/30 transition-colors"
                  >
                    <td class="p-3.5 font-mono font-semibold text-white">
                      {{ inv.invoice_number }}
                    </td>
                    <td class="p-3.5 text-nexo-muted">
                      {{ inv.paid_at ? new Date(inv.paid_at).toLocaleDateString() : (inv.due_date ? new Date(inv.due_date).toLocaleDateString() : 'N/A') }}
                    </td>
                    <td class="p-3.5 text-white max-w-xs truncate">
                      {{ inv.items?.[0]?.description || 'Nexovant Intelligence Subscription' }}
                    </td>
                    <td class="p-3.5 font-mono font-semibold text-white">
                      {{ formatCurrency(Number(inv.total), inv.currency === 'BDT' ? 'BDT' : 'USD') }}
                    </td>
                    <td class="p-3.5">
                      <span
                        :class="[
                          'px-2 py-0.5 rounded text-[10px] font-mono border font-semibold',
                          inv.status === 'paid' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' :
                          inv.status === 'unpaid' ? 'bg-amber-950/80 text-amber-400 border-amber-800/40' :
                          'bg-slate-900 text-slate-400 border-slate-700'
                        ]"
                      >
                        {{ inv.status.toUpperCase() }}
                      </span>
                    </td>
                    <td class="p-3.5 text-right space-x-2">
                      <Button
                        @click="handleDownloadInvoice(inv.id, inv.invoice_number)"
                        size="sm"
                        variant="ghost"
                        class="text-xs"
                      >
                        <Download class="w-3.5 h-3.5 mr-1" />
                        PDF Receipt
                      </Button>
                      <Button
                        v-if="inv.status === 'unpaid'"
                        @click="openProofModal(inv.id)"
                        size="sm"
                        variant="outline"
                        class="border-amber-500/50 text-amber-400 text-xs"
                      >
                        Submit TrxID
                      </Button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </Card>
        </div>

        <!-- TAB 4: PAYMENT GATEWAYS & SETTINGS -->
        <div v-if="activeTab === 'methods'" class="space-y-4">
          <Card class="p-6 bg-nexo-surface border border-nexo-border space-y-4">
            <div>
              <h3 class="text-sm font-semibold text-white">Regional Payment Gateways</h3>
              <p class="text-xs text-nexo-muted mt-0.5">Compliant transaction options available in your configured currency ({{ selectedCurrency }}).</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div
                v-for="method in eligibleMethods"
                :key="method.id"
                class="p-4 rounded-xl bg-nexo-bg/60 border border-nexo-border/60 flex items-start gap-3.5"
              >
                <CreditCard class="w-5 h-5 text-primary-400 flex-shrink-0 mt-0.5" />
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-white">{{ method.name }}</span>
                    <span v-if="method.badge" class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-primary-950/80 text-primary-400 border border-primary-800/40">
                      {{ method.badge }}
                    </span>
                  </div>
                  <p class="text-xs text-nexo-muted leading-relaxed">
                    {{ method.description }}
                  </p>
                  <span class="text-[10px] font-mono text-nexo-muted block pt-1">
                    Settlement Currency: {{ method.currency }} &bull; Recurring: {{ method.supports_recurring ? 'Supported' : 'Manual Renewal' }}
                  </span>
                </div>
              </div>
            </div>
          </Card>
        </div>

        <!-- TAB 5: ORGANIZATION BILLING -->
        <div v-if="activeTab === 'organization'" class="space-y-4">
          <Card v-if="organization" class="p-6 bg-nexo-surface border border-nexo-border space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-nexo-border gap-4">
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-mono uppercase text-nexo-muted">Multi-Tenant Workspace</span>
                  <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-cyan-950/80 text-cyan-400 border border-cyan-800/40 uppercase">
                    {{ organization.role }}
                  </span>
                </div>
                <h3 class="text-xl font-bold text-white mt-1">{{ organization.name }}</h3>
                <span class="text-xs text-nexo-muted">Workspace Slug: {{ organization.slug }}</span>
              </div>
              <router-link to="/organization">
                <Button variant="outline" size="sm" class="text-xs">
                  <ExternalLink class="w-3.5 h-3.5 mr-1.5" />
                  Open Organization Hub
                </Button>
              </router-link>
            </div>

            <!-- Seat Utilization Meter -->
            <div class="space-y-3">
              <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-white flex items-center gap-1.5">
                  <Users class="w-4 h-4 text-primary-400" />
                  Assigned Team Seats
                </span>
                <span class="font-mono font-semibold text-white">
                  {{ organization.seats_count }} / {{ organization.max_seats }} Seats
                </span>
              </div>
              <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                <div
                  class="h-full rounded-full bg-primary-500 transition-all duration-500"
                  :style="{ width: `${getUsagePercentage(organization.seats_count, organization.max_seats)}%` }"
                />
              </div>
            </div>

            <div class="p-4 rounded-xl bg-nexo-bg/60 border border-nexo-border/60 text-xs space-y-2">
              <span class="font-semibold text-white block">Pooled Entitlement Policy</span>
              <p class="text-nexo-muted leading-relaxed">
                As a member of <strong class="text-white">{{ organization.name }}</strong>, your subscriber intelligence quotas, publications, and deep research privileges are centrally provisioned and billed under the organization's enterprise agreement.
              </p>
              <div v-if="!organization.is_owner" class="text-amber-400 text-[11px] pt-1">
                Notice: Workspace subscription modifications and seat purchasing require Workspace Owner permissions.
              </div>
            </div>
          </Card>

          <Card v-else class="p-8 text-center bg-nexo-surface border border-nexo-border space-y-3">
            <Building2 class="w-10 h-10 text-nexo-muted mx-auto" />
            <h3 class="text-base font-semibold text-white">Independent Subscriber Account</h3>
            <p class="text-xs text-nexo-muted max-w-md mx-auto">
              You are currently operating on an individual subscription. Create an organization workspace to pool entitlements, invite team members, and centralize corporate billing.
            </p>
            <div class="pt-2">
              <router-link to="/organization">
                <Button variant="primary" size="sm">
                  Create Organization Workspace
                </Button>
              </router-link>
            </div>
          </Card>
        </div>

      </div>

      <!-- MODAL 1: PLAN CHANGE & PRORATION PREVIEW -->
      <Modal
        :is-open="isProrationModalOpen"
        :title="`Subscription Upgrade — ${targetPlan?.name || 'Selected Tier'}`"
        max-width="lg"
        @close="isProrationModalOpen = false"
      >
        <div class="space-y-4 text-left">
          <div v-if="isCalculatingProration" class="py-6 text-center text-nexo-muted text-xs">
            <RefreshCw class="w-6 h-6 animate-spin mx-auto mb-2 text-primary-500" />
            Calculating real-time proration and unused subscription credits...
          </div>

          <div v-else class="space-y-4">
            <!-- Proration Breakdown Ledger -->
            <div v-if="prorationData" class="p-4 rounded-xl bg-nexo-bg border border-nexo-border space-y-2.5 text-xs font-mono">
              <div class="flex justify-between text-nexo-muted">
                <span>Current Plan:</span>
                <span class="text-white font-semibold">{{ prorationData.current_plan }}</span>
              </div>
              <div class="flex justify-between text-nexo-muted">
                <span>Target Plan:</span>
                <span class="text-white font-semibold">{{ prorationData.target_plan }}</span>
              </div>
              <div class="flex justify-between text-emerald-400">
                <span>Unused Time Credit:</span>
                <span>-{{ formatCurrency(Number(prorationData.unused_credit), selectedCurrency) }}</span>
              </div>
              <div class="flex justify-between text-nexo-muted">
                <span>New Tier Baseline:</span>
                <span>+{{ formatCurrency(Number(prorationData.target_price), selectedCurrency) }}</span>
              </div>
              <div class="pt-2 border-t border-nexo-border/60 flex justify-between text-sm font-bold text-white">
                <span>Immediate Amount Due:</span>
                <span class="text-primary-400">{{ formatCurrency(computedImmediateTotal, selectedCurrency) }}</span>
              </div>
            </div>

            <!-- Coupon Input -->
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-white flex items-center gap-1.5">
                <Tag class="w-3.5 h-3.5 text-primary-400" />
                Promotional Coupon Code
              </label>
              <div class="flex gap-2">
                <Input
                  v-model="couponCode"
                  placeholder="e.g. NEXO25"
                  class="flex-1 font-mono text-xs uppercase"
                />
                <Button
                  @click="handleApplyCoupon"
                  :disabled="isCheckingCoupon || !couponCode.trim()"
                  size="sm"
                  variant="outline"
                  class="text-xs"
                >
                  <RefreshCw v-if="isCheckingCoupon" class="w-3 h-3 animate-spin mr-1" />
                  Apply
                </Button>
              </div>
              <p v-if="couponDiscount" class="text-[11px] text-emerald-400 font-mono">
                Applied: {{ couponDiscount.code }} (-{{ formatCurrency(Number(couponDiscount.discount_amount), selectedCurrency) }})
              </p>
              <p v-if="couponError" class="text-[11px] text-rose-400">
                {{ couponError }}
              </p>
            </div>

            <!-- Payment Method Selection -->
            <div v-if="computedImmediateTotal > 0 && (!currentSub?.is_active || currentSub?.plan?.slug === 'free')" class="space-y-2">
              <label class="text-xs font-medium text-white block">Select Payment Gateway</label>
              <div class="space-y-1.5 max-h-40 overflow-y-auto">
                <label
                  v-for="method in eligibleMethods"
                  :key="method.id"
                  class="flex items-center gap-3 p-2.5 rounded-lg border border-nexo-border bg-nexo-bg/60 cursor-pointer hover:border-primary-500 text-xs transition"
                >
                  <input
                    type="radio"
                    :value="method.id"
                    v-model="selectedMethod"
                    class="text-primary-600 focus:ring-primary-500"
                  />
                  <span class="font-medium text-white flex-1">{{ method.name }}</span>
                  <span class="text-[10px] text-nexo-muted font-mono">{{ method.currency }}</span>
                </label>
              </div>
            </div>

            <!-- Feedback -->
            <Alert v-if="checkoutSuccess" type="success">
              Plan change completed successfully. Your workspace is upgraded.
            </Alert>
            <Alert v-if="checkoutError" type="error">
              {{ checkoutError }}
            </Alert>
          </div>

          <div class="pt-3 border-t border-nexo-border flex justify-end gap-2">
            <Button
              @click="isProrationModalOpen = false"
              variant="ghost"
              size="sm"
              class="text-xs"
            >
              Cancel
            </Button>
            <Button
              @click="handleExecutePlanChange"
              :disabled="isExecutingPlanChange || isCalculatingProration"
              variant="primary"
              size="sm"
              class="text-xs font-semibold"
            >
              <RefreshCw v-if="isExecutingPlanChange" class="w-3.5 h-3.5 animate-spin mr-1.5" />
              Confirm Plan Update ({{ formatCurrency(computedImmediateTotal, selectedCurrency) }})
            </Button>
          </div>
        </div>
      </Modal>

      <!-- MODAL 2: SUBSCRIPTION CANCELLATION -->
      <Modal
        :is-open="isCancelModalOpen"
        title="Subscription Cancellation Notice"
        max-width="md"
        @close="isCancelModalOpen = false"
      >
        <div class="space-y-4 text-left text-xs">
          <div class="p-3.5 rounded-xl bg-rose-950/20 border border-rose-500/30 text-rose-200 space-y-1.5">
            <span class="font-semibold block text-rose-300">Feature Access Impact</span>
            <p class="leading-relaxed">
              Upon cancellation, your custom intelligence monitors, AI deep research engine access, and executive publication downloads will be retired.
            </p>
          </div>

          <div class="space-y-2">
            <label class="font-medium text-white block">Cancellation Mode</label>
            <div class="space-y-1.5">
              <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-nexo-border bg-nexo-bg cursor-pointer">
                <input
                  type="radio"
                  :value="true"
                  v-model="cancelAtPeriodEnd"
                  class="mt-0.5 text-primary-600 focus:ring-primary-500"
                />
                <div>
                  <span class="font-medium text-white block">Cancel at Period End (Recommended)</span>
                  <span class="text-nexo-muted text-[11px]">Maintain full access through {{ currentSub?.ends_at ? new Date(currentSub.ends_at).toLocaleDateString() : 'end of current cycle' }} without further charges.</span>
                </div>
              </label>
              <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-nexo-border bg-nexo-bg cursor-pointer">
                <input
                  type="radio"
                  :value="false"
                  v-model="cancelAtPeriodEnd"
                  class="mt-0.5 text-primary-600 focus:ring-primary-500"
                />
                <div>
                  <span class="font-medium text-white block">Cancel Immediately</span>
                  <span class="text-nexo-muted text-[11px]">Terminate subscription right now and revert immediately to the Free tier.</span>
                </div>
              </label>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="font-medium text-white block">Reason for Departure</label>
            <select
              v-model="cancelReason"
              class="w-full rounded-xl border border-nexo-border bg-nexo-bg px-3 py-2 text-xs text-white focus:border-primary-500 focus:outline-none"
            >
              <option value="Pricing / budget constraints">Pricing / budget constraints</option>
              <option value="Missing required data sources">Missing required data sources</option>
              <option value="Temporary project concluded">Temporary project concluded</option>
              <option value="Migrating to enterprise organizational plan">Migrating to enterprise organizational plan</option>
              <option value="Other">Other reason</option>
            </select>
          </div>

          <Alert v-if="cancelError" type="error">
            {{ cancelError }}
          </Alert>

          <div class="pt-3 border-t border-nexo-border flex justify-end gap-2">
            <Button
              @click="isCancelModalOpen = false"
              variant="ghost"
              size="sm"
            >
              Keep My Plan
            </Button>
            <Button
              @click="handleCancelSubscription"
              :disabled="isCanceling"
              variant="outline"
              size="sm"
              class="border-rose-500/50 text-rose-400 hover:bg-rose-950/30"
            >
              <RefreshCw v-if="isCanceling" class="w-3.5 h-3.5 animate-spin mr-1.5" />
              Confirm Cancellation
            </Button>
          </div>
        </div>
      </Modal>

      <!-- MODAL 3: MANUAL PAYMENT PROOF SUBMISSION -->
      <Modal
        :is-open="isProofModalOpen"
        title="Submit Payment Reference (MFS / Wire)"
        max-width="md"
        @close="isProofModalOpen = false"
      >
        <div class="space-y-3.5 text-left text-xs">
          <p class="text-nexo-muted leading-relaxed">
            Submit your transaction reference number (TrxID) for bKash, Nagad, or corporate bank wire transfer. Our compliance team verifies entries within standard desk hours.
          </p>

          <div class="space-y-1">
            <label class="font-medium text-white block">Payment Gateway Channel</label>
            <select
              v-model="proofMethod"
              class="w-full rounded-xl border border-nexo-border bg-nexo-bg px-3 py-2 text-xs text-white focus:border-primary-500 focus:outline-none font-mono"
            >
              <option value="manual_bkash">bKash Merchant / Personal Transfer</option>
              <option value="manual_nagad">Nagad Transfer</option>
              <option value="manual_bank">Direct Corporate Bank Wire</option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="font-medium text-white block">Transaction Reference ID (TrxID) *</label>
            <Input
              v-model="proofTrxId"
              placeholder="e.g. 9J28A7Z11M"
              class="font-mono text-xs uppercase"
            />
          </div>

          <div class="space-y-1">
            <label class="font-medium text-white block">Sender Account / Mobile Number</label>
            <Input
              v-model="proofSenderAccount"
              placeholder="e.g. 017XXXXXXXX or IBAN"
              class="font-mono text-xs"
            />
          </div>

          <div class="space-y-1">
            <label class="font-medium text-white block">Additional Notes</label>
            <Input
              v-model="proofNotes"
              placeholder="e.g. Invoice settlement from Q4 budget"
              class="text-xs"
            />
          </div>

          <Alert v-if="proofSuccess" type="success">
            {{ proofSuccess }}
          </Alert>
          <Alert v-if="proofError" type="error">
            {{ proofError }}
          </Alert>

          <div class="pt-3 border-t border-nexo-border flex justify-end gap-2">
            <Button
              @click="isProofModalOpen = false"
              variant="ghost"
              size="sm"
            >
              Cancel
            </Button>
            <Button
              @click="handleSubmitProof"
              :disabled="isSubmittingProof || !proofTrxId.trim()"
              variant="primary"
              size="sm"
              class="font-semibold"
            >
              <RefreshCw v-if="isSubmittingProof" class="w-3.5 h-3.5 animate-spin mr-1.5" />
              Submit Proof for Verification
            </Button>
          </div>
        </div>
      </Modal>

    </div>
  </AuthenticatedLayout>
</template>
