<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import {
  billingService,
  type BillingPlan,
  type PaymentMethodOption
} from '@/services/api/billing.service'
import {
  CreditCard,
  CheckCircle2,
  AlertCircle,
  Clock,
  Shield,
  Zap,
  ArrowRight,
  Sparkles,
  Building2,
  Smartphone,
  RefreshCw,
  Check,
  Tag,
  Copy,
  ChevronLeft,
  ExternalLink,
  Lock
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()

// Core state
const isLoading = ref(true)
const isSubmitting = ref(false)
const plans = ref<BillingPlan[]>([])
const selectedPlanId = ref<string>('')
const selectedCycle = ref<'monthly' | 'annual'>('annual')
const selectedCountry = ref<string>('BD')
const selectedCurrency = ref<string>('BDT')
const selectedMethodKey = ref<string>('bkash')

// Supported registries from server
const supportedCountries = ref<any[]>([])
const supportedCurrencies = ref<any[]>([])
const eligibleMethods = ref<PaymentMethodOption[]>([])

// Customer billing form
const customerName = ref('')
const customerEmail = ref('')
const customerAddress = ref('')
const couponCode = ref('')
const couponDiscount = ref(0)
const couponError = ref<string | null>(null)
const couponSuccess = ref<string | null>(null)
const isValidatingCoupon = ref(false)

// Offline reference fields (for bKash, Nagad, Wire)
const transactionId = ref('')
const senderAccount = ref('')
const copySuccess = ref<string | null>(null)

// Alerts / Errors
const errorMessage = ref<string | null>(null)

// Current selected plan object
const activePlan = computed(() => {
  return plans.value.find(p => p.id === selectedPlanId.value || p.slug === selectedPlanId.value) || plans.value[0] || null
})

// Currency symbol helper
const currencySymbol = computed(() => {
  const match = supportedCurrencies.value.find(c => c.code === selectedCurrency.value)
  if (match) return match.symbol
  return selectedCurrency.value === 'BDT' ? '৳' : '$'
})

// Base subtotal calculation
const baseSubtotal = computed(() => {
  if (!activePlan.value) return 0
  const isAnnual = selectedCycle.value === 'annual'
  const curr = selectedCurrency.value

  if (curr === 'BDT') {
    return isAnnual
      ? Number(activePlan.value.price_bdt_annual)
      : Number(activePlan.value.price_bdt_monthly)
  }

  const usdBase = isAnnual
    ? Number(activePlan.value.price_usd_annual)
    : Number(activePlan.value.price_usd_monthly)

  if (curr === 'EUR') return Math.round(usdBase * 0.92 * 100) / 100
  if (curr === 'GBP') return Math.round(usdBase * 0.79 * 100) / 100
  if (curr === 'CAD') return Math.round(usdBase * 1.35 * 100) / 100
  return usdBase
})

// Final price after coupon discount
const finalTotal = computed(() => {
  const tot = baseSubtotal.value - couponDiscount.value
  return Math.max(0, Math.round(tot * 100) / 100)
})

// Fetch initial data
async function loadCheckoutData() {
  isLoading.value = true
  errorMessage.value = null

  try {
    // 1. Load available plans
    const plansRes = await billingService.getPlans()
    if (plansRes.success && plansRes.data) {
      plans.value = plansRes.data
    }

    // Determine initial plan from query or pick popular/executive
    const queryPlan = route.query.plan as string
    if (queryPlan && plans.value.some(p => p.id === queryPlan || p.slug === queryPlan)) {
      selectedPlanId.value = queryPlan
    } else {
      const exec = plans.value.find(p => p.slug === 'executive' || p.tier === 'executive')
      selectedPlanId.value = exec ? exec.id : (plans.value[0]?.id || '')
    }

    if (route.query.interval === 'monthly') {
      selectedCycle.value = 'monthly'
    } else {
      selectedCycle.value = 'annual'
    }

    // 2. Load user overview to prefill name & email
    const overviewRes = await billingService.getOverview()
    if (overviewRes.success && overviewRes.data?.user) {
      customerName.value = overviewRes.data.user.name || ''
      customerEmail.value = overviewRes.data.user.email || ''
    }

    // 3. Load dynamic checkout options from backend
    await refreshCheckoutOptions()
  } catch (err: any) {
    console.error('Failed to load checkout initialization:', err)
    errorMessage.value = err.response?.data?.message || 'Failed to initialize checkout environment. Please try again.'
  } finally {
    isLoading.value = false
  }
}

// Refresh eligible payment methods when country/currency changes
async function refreshCheckoutOptions() {
  try {
    const optsRes = await billingService.getCheckoutOptions({
      country: selectedCountry.value,
      currency: selectedCurrency.value,
      interval: selectedCycle.value
    })

    if (optsRes.success) {
      supportedCountries.value = optsRes.countries || []
      supportedCurrencies.value = optsRes.currencies || []
      eligibleMethods.value = optsRes.eligible_payment_methods || []

      // If current selected method is no longer eligible, pick first eligible
      const stillEligible = eligibleMethods.value.some(m => m.gateway_key === selectedMethodKey.value)
      if (!stillEligible && eligibleMethods.value.length > 0) {
        selectedMethodKey.value = eligibleMethods.value[0].gateway_key
      }
    }
  } catch (err) {
    console.error('Failed to load checkout options:', err)
  }
}

// Handle country change
watch(selectedCountry, (newCountry) => {
  if (newCountry === 'BD') {
    selectedCurrency.value = 'BDT'
  } else if (selectedCurrency.value === 'BDT') {
    selectedCurrency.value = 'USD'
  }
  refreshCheckoutOptions()
})

// Handle currency or cycle change
watch([selectedCurrency, selectedCycle], () => {
  couponDiscount.value = 0
  couponSuccess.value = null
  couponError.value = null
  refreshCheckoutOptions()
})

// Validate coupon code
async function applyCoupon() {
  if (!couponCode.value.trim() || !activePlan.value) return
  isValidatingCoupon.value = true
  couponError.value = null
  couponSuccess.value = null

  try {
    const res = await billingService.validateCoupon({
      code: couponCode.value.trim(),
      plan_id: activePlan.value.id,
      country: selectedCountry.value,
      subtotal: baseSubtotal.value
    })

    if (res.success && res.data) {
      couponDiscount.value = Number(res.data.discount_amount || 0)
      couponSuccess.value = `Coupon '${res.data.code}' applied: ${res.data.description || 'Discount activated'}`
    }
  } catch (err: any) {
    couponDiscount.value = 0
    couponError.value = err.response?.data?.message || 'Invalid or expired promotional code.'
  } finally {
    isValidatingCoupon.value = false
  }
}

// Copy to clipboard helper
function copyText(text: string, label: string) {
  navigator.clipboard.writeText(text)
  copySuccess.value = label
  setTimeout(() => {
    copySuccess.value = null
  }, 2500)
}

// Submit checkout
async function handleCheckout() {
  if (!activePlan.value) return
  isSubmitting.value = true
  errorMessage.value = null

  try {
    // Generate deterministic client-side idempotency token
    const idempotencyKey = 'chk_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36)

    const payload: any = {
      plan_id: activePlan.value.id,
      billing_cycle: selectedCycle.value,
      currency: selectedCurrency.value,
      country: selectedCountry.value,
      payment_method: selectedMethodKey.value,
      coupon_code: couponSuccess.value ? couponCode.value.trim() : undefined,
      idempotency_key: idempotencyKey,
      billing_name: customerName.value.trim() || undefined,
      billing_email: customerEmail.value.trim() || undefined,
      billing_address: customerAddress.value.trim() || undefined,
    }

    const res = await billingService.checkout(payload)

    if (res.success && res.data) {
      const data = res.data
      const reference = data.transaction_reference || data.payment?.provider_reference || data.invoice?.id

      // If manual proof was already filled in for MFS or Wire, submit proof
      if (['bkash', 'nagad', 'manual_bank'].includes(selectedMethodKey.value) && transactionId.value.trim() && data.invoice?.id) {
        try {
          await billingService.submitManualPayment({
            invoice_id: data.invoice.id,
            payment_method: selectedMethodKey.value,
            transaction_id: transactionId.value.trim(),
            sender_account: senderAccount.value.trim() || undefined,
            notes: `Submitted during intelligent checkout. Country: ${selectedCountry.value}`
          })
        } catch (proofErr) {
          console.warn('Manual proof auto-submission error:', proofErr)
        }
      }

      // Navigate to dedicated status tracking page
      router.push(`/checkout/status/${reference}`)
    } else {
      errorMessage.value = res.message || 'Payment initiation failed. Please verify your details.'
    }
  } catch (err: any) {
    console.error('Checkout execution error:', err)
    errorMessage.value = err.response?.data?.message || err.message || 'Payment processing error. Please try another payment method.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  loadCheckoutData()
})
</script>

<template>
  <AuthenticatedLayout>
    <div class="min-h-screen bg-nexo-bg py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-6xl mx-auto space-y-8">

        <!-- Top Navigation & Security Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-nexo-border">
          <div>
            <router-link to="/billing" class="inline-flex items-center text-xs text-nexo-muted hover:text-nexo-accent transition-colors mb-2">
              <ChevronLeft class="w-4 h-4 mr-1" />
              Return to Billing Portal
            </router-link>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-nexo-text flex items-center gap-3">
              Intelligent Checkout
              <span class="text-xs px-2 py-0.5 rounded border border-nexo-accent/40 text-nexo-accent font-sans font-medium">
                Stage 10
              </span>
            </h1>
            <p class="text-sm text-nexo-muted mt-1">
              Secure executive subscription enrollment with automated regional payment routing.
            </p>
          </div>

          <!-- Security Badge -->
          <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs">
            <Lock class="w-4 h-4" />
            <span>256-bit TLS Encrypted &bull; PCI DSS Compliant</span>
          </div>
        </div>

        <!-- Global Error Notification -->
        <Alert v-if="errorMessage" variant="danger" class="mb-4">
          <div class="flex items-center gap-2">
            <AlertCircle class="w-5 h-5 flex-shrink-0" />
            <span>{{ errorMessage }}</span>
          </div>
        </Alert>

        <!-- Loading State -->
        <div v-if="isLoading" class="p-16 flex flex-col items-center justify-center space-y-4">
          <RefreshCw class="w-10 h-10 text-nexo-accent animate-spin" />
          <p class="text-sm text-nexo-muted">Configuring regional payment gateways and verifying credentials...</p>
        </div>

        <!-- Main Checkout Layout -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

          <!-- Left Column: 6-Step Interaction Flow (7 cols) -->
          <div class="lg:col-span-7 space-y-6">

            <!-- Step 1: Subscription Plan & Cadence Selection -->
            <Card class="p-6 bg-nexo-surface border-nexo-border shadow-sm">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                  <span class="w-6 h-6 rounded-full bg-nexo-accent/20 text-nexo-accent flex items-center justify-center text-xs font-bold">1</span>
                  <h2 class="text-base font-medium text-nexo-text">Plan & Cadence</h2>
                </div>
                <!-- Cadence Toggle -->
                <div class="inline-flex p-1 rounded-lg bg-nexo-bg border border-nexo-border text-xs">
                  <button
                    type="button"
                    @click="selectedCycle = 'monthly'"
                    :class="[
                      'px-3 py-1 rounded-md transition-colors font-medium',
                      selectedCycle === 'monthly' ? 'bg-nexo-surface text-nexo-text shadow-sm' : 'text-nexo-muted hover:text-nexo-text'
                    ]"
                  >
                    Monthly
                  </button>
                  <button
                    type="button"
                    @click="selectedCycle = 'annual'"
                    :class="[
                      'px-3 py-1 rounded-md transition-colors font-medium flex items-center gap-1.5',
                      selectedCycle === 'annual' ? 'bg-nexo-surface text-nexo-accent shadow-sm' : 'text-nexo-muted hover:text-nexo-text'
                    ]"
                  >
                    Annual
                    <span class="text-[10px] px-1 py-0.2 bg-nexo-accent/20 text-nexo-accent rounded">Save 17%</span>
                  </button>
                </div>
              </div>

              <!-- Plan Cards Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div
                  v-for="plan in plans"
                  :key="plan.id"
                  @click="selectedPlanId = plan.id"
                  :class="[
                    'p-4 rounded-lg border text-left cursor-pointer transition-all relative',
                    selectedPlanId === plan.id
                      ? 'border-nexo-accent bg-nexo-accent/5 ring-1 ring-nexo-accent'
                      : 'border-nexo-border bg-nexo-bg/40 hover:border-nexo-border/80'
                  ]"
                >
                  <div class="flex items-center justify-between mb-2">
                    <span class="font-serif font-bold text-sm text-nexo-text">{{ plan.name }}</span>
                    <span v-if="selectedPlanId === plan.id" class="w-4 h-4 rounded-full bg-nexo-accent text-white flex items-center justify-center">
                      <Check class="w-3 h-3" />
                    </span>
                  </div>
                  <div class="text-xs text-nexo-muted line-clamp-2 mb-2">{{ plan.description || 'Executive intelligence access.' }}</div>
                  <div class="text-sm font-semibold text-nexo-text">
                    <span v-if="selectedCurrency === 'BDT'">
                      ৳{{ selectedCycle === 'annual' ? Number(plan.price_bdt_annual).toLocaleString() : Number(plan.price_bdt_monthly).toLocaleString() }}
                    </span>
                    <span v-else>
                      ${{ selectedCycle === 'annual' ? Number(plan.price_usd_annual).toLocaleString() : Number(plan.price_usd_monthly).toLocaleString() }}
                    </span>
                    <span class="text-[10px] font-normal text-nexo-muted">/{{ selectedCycle === 'annual' ? 'yr' : 'mo' }}</span>
                  </div>
                </div>
              </div>
            </Card>

            <!-- Step 2 & 3: Country & Currency Selection -->
            <Card class="p-6 bg-nexo-surface border-nexo-border shadow-sm">
              <div class="flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-full bg-nexo-accent/20 text-nexo-accent flex items-center justify-center text-xs font-bold">2</span>
                <h2 class="text-base font-medium text-nexo-text">Billing Country & Settlement Currency</h2>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Country Dropdown -->
                <div>
                  <label class="block text-xs font-medium text-nexo-muted mb-1.5">Country / Operating Region</label>
                  <select
                    v-model="selectedCountry"
                    class="w-full px-3 py-2 bg-nexo-bg border border-nexo-border rounded-lg text-sm text-nexo-text focus:outline-none focus:border-nexo-accent"
                  >
                    <option v-for="c in supportedCountries" :key="c.code" :value="c.code">
                      {{ c.flag }} {{ c.name }} ({{ c.code }})
                    </option>
                  </select>
                </div>

                <!-- Currency Selector -->
                <div>
                  <label class="block text-xs font-medium text-nexo-muted mb-1.5">Settlement Currency</label>
                  <div class="flex gap-2">
                    <button
                      v-for="curr in (supportedCountries.find(c => c.code === selectedCountry)?.supported_currencies || ['USD', 'BDT'])"
                      :key="curr"
                      type="button"
                      @click="selectedCurrency = curr"
                      :class="[
                        'flex-1 py-2 px-3 rounded-lg border text-xs font-semibold transition-colors flex items-center justify-center gap-1.5',
                        selectedCurrency === curr
                          ? 'border-nexo-accent bg-nexo-accent/10 text-nexo-accent'
                          : 'border-nexo-border bg-nexo-bg text-nexo-muted hover:text-nexo-text'
                      ]"
                    >
                      <span>{{ curr === 'BDT' ? '৳ BDT' : (curr === 'EUR' ? '€ EUR' : (curr === 'GBP' ? '£ GBP' : (curr === 'CAD' ? 'CA$ CAD' : '$ USD'))) }}</span>
                    </button>
                  </div>
                </div>
              </div>

              <p class="text-[11px] text-nexo-muted/80 mt-3 flex items-center gap-1">
                <Shield class="w-3.5 h-3.5 text-nexo-accent" />
                <span>Zero conversion surcharge. All amounts settle natively without intermediary markups.</span>
              </p>
            </Card>

            <!-- Step 4: Eligible Payment Methods (Server-Authoritative) -->
            <Card class="p-6 bg-nexo-surface border-nexo-border shadow-sm">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                  <span class="w-6 h-6 rounded-full bg-nexo-accent/20 text-nexo-accent flex items-center justify-center text-xs font-bold">3</span>
                  <h2 class="text-base font-medium text-nexo-text">Eligible Payment Methods</h2>
                </div>
                <span class="text-xs text-nexo-muted">{{ eligibleMethods.length }} options available</span>
              </div>

              <!-- Payment Method Cards -->
              <div class="space-y-3">
                <div
                  v-for="m in eligibleMethods"
                  :key="m.gateway_key"
                  @click="selectedMethodKey = m.gateway_key"
                  :class="[
                    'p-4 rounded-lg border cursor-pointer transition-all flex items-start justify-between gap-4',
                    selectedMethodKey === m.gateway_key
                      ? 'border-nexo-accent bg-nexo-accent/5 ring-1 ring-nexo-accent'
                      : 'border-nexo-border bg-nexo-bg/40 hover:border-nexo-border/80'
                  ]"
                >
                  <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-nexo-bg border border-nexo-border text-nexo-accent">
                      <Smartphone v-if="m.type === 'mfs'" class="w-5 h-5" />
                      <CreditCard v-else-if="m.type === 'card' || m.type === 'upi_card'" class="w-5 h-5" />
                      <Building2 v-else class="w-5 h-5" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium text-sm text-nexo-text">{{ m.name }}</span>
                        <span v-if="m.badge" class="text-[10px] py-0.5 px-1.5 rounded border border-nexo-accent/40 text-nexo-accent font-sans">
                          {{ m.badge }}
                        </span>
                      </div>
                      <p class="text-xs text-nexo-muted mt-0.5">{{ m.description }}</p>
                    </div>
                  </div>

                  <div class="w-5 h-5 rounded-full border flex items-center justify-center flex-shrink-0 mt-1"
                    :class="selectedMethodKey === m.gateway_key ? 'border-nexo-accent bg-nexo-accent text-white' : 'border-nexo-border bg-nexo-bg'">
                    <Check v-if="selectedMethodKey === m.gateway_key" class="w-3.5 h-3.5" />
                  </div>
                </div>
              </div>
            </Card>

            <!-- Step 5: Execution Details & Direct TrxID Form -->
            <Card class="p-6 bg-nexo-surface border-nexo-border shadow-sm">
              <div class="flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-full bg-nexo-accent/20 text-nexo-accent flex items-center justify-center text-xs font-bold">4</span>
                <h2 class="text-base font-medium text-nexo-text">Payment Execution Instructions</h2>
              </div>

              <!-- bKash Instructions -->
              <div v-if="selectedMethodKey === 'bkash'" class="space-y-4">
                <div class="p-4 rounded-lg bg-pink-500/10 border border-pink-500/20 text-xs space-y-2">
                  <div class="flex items-center justify-between font-semibold text-pink-400">
                    <span>bKash Merchant Account Number</span>
                    <button
                      type="button"
                      @click="copyText('01700000000', 'bKash Merchant Number')"
                      class="inline-flex items-center gap-1 text-[11px] hover:underline"
                    >
                      <Copy class="w-3 h-3" />
                      {{ copySuccess === 'bKash Merchant Number' ? 'Copied!' : 'Copy Number' }}
                    </button>
                  </div>
                  <div class="text-base font-mono font-bold text-nexo-text">01700000000</div>
                  <p class="text-nexo-muted text-[11px]">
                    Dial <code class="text-nexo-accent">*247#</code> or use bKash App &gt; Select <strong>Make Payment</strong> &gt; Enter Amount <strong>৳{{ finalTotal.toLocaleString() }}</strong> &gt; Reference: <strong>NEXOVANT</strong>.
                  </p>
                </div>

                <!-- Direct TrxID Field -->
                <div>
                  <label class="block text-xs font-medium text-nexo-muted mb-1.5">bKash Transaction ID (TrxID)</label>
                  <Input
                    v-model="transactionId"
                    placeholder="e.g. BK89234XLM"
                    class="font-mono text-sm uppercase"
                  />
                  <p class="text-[11px] text-nexo-muted mt-1">Submit your 10-character alphanumeric TrxID from the bKash confirmation SMS.</p>
                </div>
              </div>

              <!-- Nagad Instructions -->
              <div v-else-if="selectedMethodKey === 'nagad'" class="space-y-4">
                <div class="p-4 rounded-lg bg-orange-500/10 border border-orange-500/20 text-xs space-y-2">
                  <div class="flex items-center justify-between font-semibold text-orange-400">
                    <span>Nagad Merchant Account Number</span>
                    <button
                      type="button"
                      @click="copyText('01800000000', 'Nagad Merchant Number')"
                      class="inline-flex items-center gap-1 text-[11px] hover:underline"
                    >
                      <Copy class="w-3 h-3" />
                      {{ copySuccess === 'Nagad Merchant Number' ? 'Copied!' : 'Copy Number' }}
                    </button>
                  </div>
                  <div class="text-base font-mono font-bold text-nexo-text">01800000000</div>
                  <p class="text-nexo-muted text-[11px]">
                    Dial <code class="text-nexo-accent">*167#</code> or open Nagad App &gt; Select <strong>Merchant Pay</strong> &gt; Enter Amount <strong>৳{{ finalTotal.toLocaleString() }}</strong> &gt; Counter: 1.
                  </p>
                </div>

                <div>
                  <label class="block text-xs font-medium text-nexo-muted mb-1.5">Nagad Transaction ID (TrxID)</label>
                  <Input
                    v-model="transactionId"
                    placeholder="e.g. NG772189AA"
                    class="font-mono text-sm uppercase"
                  />
                  <p class="text-[11px] text-nexo-muted mt-1">Submit the 8-10 character Nagad TrxID received in your confirmation SMS.</p>
                </div>
              </div>

              <!-- Corporate Bank Transfer Instructions -->
              <div v-else-if="selectedMethodKey === 'manual_bank'" class="space-y-4">
                <div class="p-4 rounded-lg bg-nexo-bg border border-nexo-border text-xs space-y-3">
                  <div class="font-semibold text-nexo-accent flex items-center justify-between">
                    <span>{{ selectedCurrency === 'BDT' ? 'City Bank PLC — Institutional BDT Account' : 'Standard Chartered Bank — Global Corporate Account' }}</span>
                  </div>

                  <div v-if="selectedCurrency === 'BDT'" class="grid grid-cols-2 gap-2 text-nexo-text">
                    <div>
                      <div class="text-[10px] text-nexo-muted">Account Name</div>
                      <div class="font-medium">Nexovant Intelligence Ltd</div>
                    </div>
                    <div>
                      <div class="text-[10px] text-nexo-muted">Account Number</div>
                      <div class="font-mono font-bold flex items-center gap-1">
                        1102938475001
                        <button type="button" @click="copyText('1102938475001', 'City Bank Acc')" class="text-nexo-accent"><Copy class="w-3 h-3" /></button>
                      </div>
                    </div>
                    <div>
                      <div class="text-[10px] text-nexo-muted">Routing Number</div>
                      <div class="font-mono">225272648</div>
                    </div>
                    <div>
                      <div class="text-[10px] text-nexo-muted">SWIFT BIC</div>
                      <div class="font-mono">CIBLBDDH</div>
                    </div>
                  </div>

                  <div v-else class="grid grid-cols-2 gap-2 text-nexo-text">
                    <div>
                      <div class="text-[10px] text-nexo-muted">Account Name</div>
                      <div class="font-medium">Nexovant Global Corporate Accounts</div>
                    </div>
                    <div>
                      <div class="text-[10px] text-nexo-muted">Account Number</div>
                      <div class="font-mono font-bold flex items-center gap-1">
                        01-9988223-01
                        <button type="button" @click="copyText('01-9988223-01', 'SCB Acc')" class="text-nexo-accent"><Copy class="w-3 h-3" /></button>
                      </div>
                    </div>
                    <div>
                      <div class="text-[10px] text-nexo-muted">IBAN</div>
                      <div class="font-mono text-[11px]">BD28SCBL00000001998822301</div>
                    </div>
                    <div>
                      <div class="text-[10px] text-nexo-muted">SWIFT BIC</div>
                      <div class="font-mono">SCBLBDDX</div>
                    </div>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-medium text-nexo-muted mb-1.5">Bank Wire Transfer Reference / Voucher Number</label>
                  <Input
                    v-model="transactionId"
                    placeholder="e.g. WT-992834-CITY or Bank Deposit Slip #"
                    class="font-mono text-sm"
                  />
                  <p class="text-[11px] text-nexo-muted mt-1">Our compliance team verifies bank wire submissions within 2-4 business hours.</p>
                </div>
              </div>

              <!-- Stripe Card Payment -->
              <div v-else class="p-4 rounded-lg bg-nexo-bg border border-nexo-border text-xs space-y-3">
                <div class="flex items-center gap-2 text-nexo-accent">
                  <CreditCard class="w-4 h-4" />
                  <span class="font-semibold">Stripe Payment Gateway</span>
                </div>
                <p class="text-nexo-muted">
                  You will be securely routed through Stripe's hosted checkout to complete payment with Visa, Mastercard, American Express, Apple Pay, or Google Pay.
                </p>
                <div class="p-2.5 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[11px]">
                  Sandbox & Live payment session will initialize automatically with instantaneous activation.
                </div>
              </div>
            </Card>

            <!-- Customer Contact Information -->
            <Card class="p-6 bg-nexo-surface border-nexo-border shadow-sm">
              <h2 class="text-base font-medium text-nexo-text mb-4">Subscriber Information</h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-nexo-muted mb-1.5">Full Name</label>
                  <Input v-model="customerName" placeholder="Executive Name" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-nexo-muted mb-1.5">Official Email</label>
                  <Input v-model="customerEmail" type="email" placeholder="subscriber@company.com" />
                </div>
              </div>
            </Card>

          </div>

          <!-- Right Column: Sticky Summary & Checkout Execution (5 cols) -->
          <div class="lg:col-span-5 space-y-6 lg:sticky lg:top-8">

            <Card class="p-6 bg-nexo-surface border-nexo-border shadow-lg space-y-6">
              <h2 class="text-lg font-serif font-bold text-nexo-text border-b border-nexo-border pb-3 flex items-center justify-between">
                <span>Order Summary</span>
                <span class="text-xs px-2 py-0.5 rounded border border-nexo-accent/40 text-nexo-accent font-sans capitalize">
                  {{ activePlan?.tier || 'Subscription' }}
                </span>
              </h2>

              <!-- Plan Details Line -->
              <div class="flex justify-between items-start text-sm">
                <div>
                  <div class="font-semibold text-nexo-text">{{ activePlan?.name }} Intelligence</div>
                  <div class="text-xs text-nexo-muted capitalize">{{ selectedCycle }} Billing Cadence</div>
                </div>
                <div class="text-right">
                  <div class="font-bold text-nexo-text">{{ currencySymbol }}{{ baseSubtotal.toLocaleString() }}</div>
                  <div class="text-[11px] text-nexo-muted">{{ selectedCurrency }}</div>
                </div>
              </div>

              <!-- Promotional Coupon Code Input -->
              <div class="pt-3 border-t border-nexo-border/60">
                <label class="block text-xs font-medium text-nexo-muted mb-1.5">Promotional Coupon</label>
                <div class="flex gap-2">
                  <Input
                    v-model="couponCode"
                    placeholder="Enter code (e.g. LAUNCH25)"
                    class="text-xs uppercase flex-1"
                    :disabled="couponDiscount > 0"
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="applyCoupon"
                    :loading="isValidatingCoupon"
                    :disabled="!couponCode.trim() || couponDiscount > 0"
                  >
                    Apply
                  </Button>
                </div>

                <p v-if="couponSuccess" class="text-[11px] text-emerald-400 mt-1.5 flex items-center gap-1">
                  <CheckCircle2 class="w-3.5 h-3.5" />
                  <span>{{ couponSuccess }}</span>
                </p>
                <p v-if="couponError" class="text-[11px] text-rose-400 mt-1.5 flex items-center gap-1">
                  <AlertCircle class="w-3.5 h-3.5" />
                  <span>{{ couponError }}</span>
                </p>
              </div>

              <!-- Price Breakdown Ledger -->
              <div class="pt-3 border-t border-nexo-border/60 space-y-2 text-xs">
                <div class="flex justify-between text-nexo-muted">
                  <span>Subtotal</span>
                  <span>{{ currencySymbol }}{{ baseSubtotal.toLocaleString() }}</span>
                </div>

                <div v-if="couponDiscount > 0" class="flex justify-between text-emerald-400 font-medium">
                  <span>Discount</span>
                  <span>-{{ currencySymbol }}{{ couponDiscount.toLocaleString() }}</span>
                </div>

                <div class="flex justify-between text-nexo-muted">
                  <span>Taxes &amp; Digital VAT</span>
                  <span>{{ currencySymbol }}0.00</span>
                </div>

                <div class="pt-3 border-t border-nexo-border flex justify-between items-baseline text-base font-bold text-nexo-text">
                  <span>Total Due Today</span>
                  <span class="text-xl text-nexo-accent font-mono">{{ currencySymbol }}{{ finalTotal.toLocaleString() }}</span>
                </div>
              </div>

              <!-- Action Button -->
              <div class="pt-2">
                <Button
                  type="button"
                  class="w-full justify-center py-3 text-sm font-semibold shadow-md bg-nexo-accent hover:bg-nexo-accent/90 text-white"
                  :loading="isSubmitting"
                  @click="handleCheckout"
                >
                  <Lock class="w-4 h-4 mr-2" />
                  <span>Complete Secure Checkout</span>
                </Button>
              </div>

              <!-- Risk Reversal Guarantees -->
              <div class="text-[11px] text-nexo-muted space-y-2 pt-2 border-t border-nexo-border/60">
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0" />
                  <span>Instant executive publication &amp; RAG access upon payment.</span>
                </div>
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0" />
                  <span>14-day satisfaction money-back guarantee.</span>
                </div>
                <div class="flex items-center gap-2">
                  <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0" />
                  <span>Cancel anytime online with a single click.</span>
                </div>
              </div>

            </Card>

          </div>

        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
