<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Alert from '@/components/ui/Alert.vue'
import { adminBillingService } from '@/services/api/billing.service'
import {
  CreditCard,
  DollarSign,
  TrendingUp,
  Users,
  ShieldCheck,
  AlertTriangle,
  RefreshCw,
  Plus,
  Edit2,
  CheckCircle2,
  XCircle,
  FileText,
  RotateCcw,
  Sparkles,
  Server,
  Layers,
  Search,
  Sliders,
  Calendar,
  Building2,
  Smartphone,
  Tag
} from 'lucide-vue-next'

// State
const loading = ref(true)
const activeTab = ref<'overview' | 'plans' | 'subscriptions' | 'invoices' | 'gateways' | 'refunds' | 'coupons' | 'reconciliation'>('overview')
const statusMessage = ref('')
const errorMessage = ref('')

// Overview Telemetry
const telemetry = ref<any>(null)

// Plans State
const plans = ref<any[]>([])
const isPlanModalOpen = ref(false)
const editingPlan = ref<any>({
  id: '',
  name: '',
  slug: '',
  description: '',
  price_usd_monthly: 0,
  price_usd_annual: 0,
  price_bdt_monthly: 0,
  price_bdt_annual: 0,
  trial_period_days: 0,
  is_active: true,
  is_popular: false,
  entitlements: {}
})

// Subscriptions State
const subscriptions = ref<any[]>([])
const subPage = ref(1)
const subTotal = ref(0)
const subFilter = ref('all')
const subSearch = ref('')
const selectedSub = ref<any>(null)
const isSubModalOpen = ref(false)
const newSubStatus = ref('active')
const subStatusReason = ref('')

// Override State
const isOverrideModalOpen = ref(false)
const overrideForm = ref({
  feature_key: 'max_watchlists',
  value: '20',
  reason: '',
  expires_at: ''
})

// Invoices State
const invoices = ref<any[]>([])
const invPage = ref(1)
const invTotal = ref(0)
const invFilter = ref('all')
const invSearch = ref('')
const selectedInvoice = ref<any>(null)
const isInvModalOpen = ref(false)

// Gateways State
const gateways = ref<any[]>([])
const isGatewayModalOpen = ref(false)
const editingGateway = ref<any>(null)
const testResult = ref<any>(null)
const isTestingGateway = ref(false)

// Refunds State
const refunds = ref<any[]>([])
const isRefundModalOpen = ref(false)
const refundPaymentId = ref('')
const refundAmount = ref(0)
const refundReason = ref('')
const refundNotes = ref('')
const isProcessingRefund = ref(false)

// Coupons State
const coupons = ref<any[]>([])
const isCouponModalOpen = ref(false)
const couponForm = ref({
  code: '',
  name: '',
  discount_type: 'percentage',
  discount_value: 20,
  currency: 'USD',
  max_redemptions: 100,
  valid_until: '',
  min_order_amount: 0,
  is_active: true
})

// Reconciliation State
const isReconciling = ref(false)
const reconciliationResult = ref<any>(null)
const reconciliationHistory = ref<any[]>([])

const fetchOverview = async () => {
  loading.value = true
  try {
    const res = await adminBillingService.getOverview()
    if (res.success) {
      telemetry.value = res.data
    }
  } catch (err) {
    console.error('Failed to load billing telemetry', err)
  } finally {
    loading.value = false
  }
}

const fetchPlans = async () => {
  try {
    const res = await adminBillingService.getPlans()
    if (res.success) {
      plans.value = res.data
    }
  } catch (err) {
    console.error('Failed to load plans', err)
  }
}

const fetchSubscriptions = async (page = 1) => {
  try {
    const res = await adminBillingService.getSubscriptions({
      page,
      status: subFilter.value !== 'all' ? subFilter.value : undefined,
      search: subSearch.value.trim() || undefined
    })
    if (res.success) {
      subscriptions.value = res.data
      subPage.value = res.meta.current_page
      subTotal.value = res.meta.total
    }
  } catch (err) {
    console.error('Failed to load subscriptions', err)
  }
}

const openSubDetails = async (id: string) => {
  try {
    const res = await adminBillingService.getSubscriptionDetails(id)
    if (res.success) {
      selectedSub.value = res.data
      newSubStatus.value = res.data.status
      subStatusReason.value = ''
      isSubModalOpen.value = true
    }
  } catch (err) {
    alert('Failed to load subscription details.')
  }
}

const handleUpdateSubStatus = async () => {
  if (!selectedSub.value || !subStatusReason.value.trim()) {
    alert('Please provide a mandatory audit reason for status change.')
    return
  }
  try {
    const res = await adminBillingService.updateSubscriptionStatus(
      selectedSub.value.id,
      newSubStatus.value,
      subStatusReason.value.trim()
    )
    if (res.success) {
      statusMessage.value = res.message
      await openSubDetails(selectedSub.value.id)
      await fetchSubscriptions(subPage.value)
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Status change failed.'
  }
}

const handleGrantOverride = async () => {
  if (!selectedSub.value || !overrideForm.value.reason.trim()) {
    alert('Reason is required for entitlement override.')
    return
  }
  try {
    const res = await adminBillingService.grantEntitlementOverride(selectedSub.value.id, {
      feature_key: overrideForm.value.feature_key,
      value: overrideForm.value.value,
      reason: overrideForm.value.reason.trim(),
      expires_at: overrideForm.value.expires_at || undefined
    })
    if (res.success) {
      isOverrideModalOpen.value = false
      await openSubDetails(selectedSub.value.id)
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to grant override.')
  }
}

const handleRevokeOverride = async (overrideId: string) => {
  if (!confirm('Are you sure you want to revoke this override?')) return
  try {
    await adminBillingService.revokeEntitlementOverride(overrideId)
    if (selectedSub.value) {
      await openSubDetails(selectedSub.value.id)
    }
  } catch (err) {
    alert('Failed to revoke override.')
  }
}

const fetchInvoices = async (page = 1) => {
  try {
    const res = await adminBillingService.getInvoices({
      page,
      status: invFilter.value !== 'all' ? invFilter.value : undefined,
      search: invSearch.value.trim() || undefined
    })
    if (res.success) {
      invoices.value = res.data
      invPage.value = res.meta.current_page
      invTotal.value = res.meta.total
    }
  } catch (err) {
    console.error('Failed to load invoices', err)
  }
}

const openInvoiceDetails = async (id: string) => {
  try {
    const res = await adminBillingService.getInvoiceDetails(id)
    if (res.success) {
      selectedInvoice.value = res.data
      isInvModalOpen.value = true
    }
  } catch (err) {
    alert('Failed to load invoice details.')
  }
}

const fetchGateways = async () => {
  try {
    const res = await adminBillingService.getGateways()
    if (res.success) {
      gateways.value = res.data
    }
  } catch (err) {
    console.error('Failed to load gateways', err)
  }
}

const handleTestGateway = async (key: string) => {
  isTestingGateway.value = true
  testResult.value = null
  try {
    const res = await adminBillingService.testGateway(key)
    testResult.value = res
  } catch (err: any) {
    testResult.value = { success: false, message: err.response?.data?.message || 'Test failed' }
  } finally {
    isTestingGateway.value = false
  }
}

const fetchRefunds = async (page = 1) => {
  try {
    const res = await adminBillingService.getRefunds(page)
    if (res.success) {
      refunds.value = res.data
    }
  } catch (err) {
    console.error('Failed to load refunds', err)
  }
}

const handleProcessRefund = async () => {
  if (!refundPaymentId.value || refundAmount.value <= 0 || !refundReason.value.trim()) {
    alert('Payment ID, positive amount, and reason are required.')
    return
  }
  isProcessingRefund.value = true
  try {
    const res = await adminBillingService.processRefund({
      payment_id: refundPaymentId.value,
      amount: refundAmount.value,
      reason: refundReason.value.trim(),
      notes: refundNotes.value.trim() || undefined
    })
    if (res.success) {
      isRefundModalOpen.value = false
      alert(res.message)
      await fetchRefunds()
      await fetchOverview()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || err.response?.data?.errors?.amount?.[0] || 'Refund failed.')
  } finally {
    isProcessingRefund.value = false
  }
}

const fetchCoupons = async (page = 1) => {
  try {
    const res = await adminBillingService.getCoupons(page)
    if (res.success) {
      coupons.value = res.data
    }
  } catch (err) {
    console.error('Failed to load coupons', err)
  }
}

const handleToggleCoupon = async (id: string) => {
  try {
    const res = await adminBillingService.toggleCoupon(id)
    if (res.success) {
      await fetchCoupons()
    }
  } catch (err) {
    alert('Failed to toggle coupon.')
  }
}

const handleCreateCoupon = async () => {
  if (!couponForm.value.code.trim() || !couponForm.value.name.trim()) {
    alert('Coupon code and name are required.')
    return
  }
  try {
    const res = await adminBillingService.storeCoupon({
      ...couponForm.value,
      code: couponForm.value.code.trim().toUpperCase()
    })
    if (res.success) {
      isCouponModalOpen.value = false
      await fetchCoupons()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to create coupon.')
  }
}

const handleExecuteReconciliation = async () => {
  isReconciling.value = true
  try {
    const res = await adminBillingService.reconcile()
    if (res.success) {
      reconciliationResult.value = res.data
      await fetchReconciliationHistory()
    }
  } catch (err) {
    alert('Reconciliation failed.')
  } finally {
    isReconciling.value = false
  }
}

const fetchReconciliationHistory = async () => {
  try {
    const res = await adminBillingService.getReconciliationHistory()
    if (res.success) {
      reconciliationHistory.value = res.data
    }
  } catch (err) {
    console.error('Failed to load reconciliation history', err)
  }
}

onMounted(() => {
  fetchOverview()
  fetchPlans()
  fetchSubscriptions()
  fetchInvoices()
  fetchGateways()
  fetchRefunds()
  fetchCoupons()
  fetchReconciliationHistory()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Context -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-nexo-border">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs uppercase tracking-widest font-semibold px-2 py-0.5 rounded bg-primary-950/60 text-primary-400 border border-primary-800/40">
              Phase 7D Admin Control Center
            </span>
            <span class="text-xs text-nexo-muted font-mono">Financial Operations & Gateway Registry</span>
          </div>
          <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
            <CreditCard class="w-6 h-6 text-primary-400" />
            Billing & Subscriptions Control Center
          </h1>
          <p class="text-xs text-nexo-muted mt-1">
            Institutional revenue tracking, plan matrix configuration, lifecycle state machines, gateway capabilities, and automated reconciliation.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Button @click="handleExecuteReconciliation" :disabled="isReconciling" variant="outline" size="sm">
            <RefreshCw :class="isReconciling ? 'animate-spin' : ''" class="w-4 h-4 mr-1.5 text-primary-400" />
            {{ isReconciling ? 'Auditing...' : 'Run Reconciliation' }}
          </Button>
          <Button @click="fetchOverview" variant="outline" size="sm">
            <RotateCcw class="w-4 h-4 mr-1 text-nexo-muted" />
            Refresh
          </Button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-2 border-b border-nexo-border text-xs overflow-x-auto pb-1">
        <button
          @click="activeTab = 'overview'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'overview' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Telemetry & MRR
        </button>
        <button
          @click="activeTab = 'plans'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'plans' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Plans & Entitlements
        </button>
        <button
          @click="activeTab = 'subscriptions'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'subscriptions' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Subscriptions Queue
        </button>
        <button
          @click="activeTab = 'invoices'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'invoices' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Invoices & Ledger
        </button>
        <button
          @click="activeTab = 'gateways'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'gateways' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Gateway Registry
        </button>
        <button
          @click="activeTab = 'refunds'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'refunds' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Refunds & Disputes
        </button>
        <button
          @click="activeTab = 'coupons'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'coupons' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Promotional Coupons
        </button>
        <button
          @click="activeTab = 'reconciliation'"
          :class="['px-3 py-2 rounded-t font-medium transition-colors border-b-2', activeTab === 'reconciliation' ? 'border-primary-500 text-primary-400 bg-nexo-surface' : 'border-transparent text-nexo-muted hover:text-white']"
        >
          Financial Reconciliation
        </button>
      </div>

      <!-- TAB 1: TELEMETRY & OVERVIEW -->
      <div v-if="activeTab === 'overview'" class="space-y-6">
        <!-- Metric Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Card class="p-4 border border-nexo-border bg-nexo-surface">
            <span class="text-xs text-nexo-muted font-medium uppercase tracking-wider">Normalized MRR</span>
            <div class="text-2xl font-bold text-white mt-1 font-mono">
              ${{ telemetry?.metrics.mrr_usd?.toFixed(2) || '0.00' }}
            </div>
            <span class="text-[11px] text-primary-400 font-mono block mt-1">
              + ৳{{ telemetry?.metrics.mrr_bdt?.toFixed(0) || '0' }} BDT / mo
            </span>
          </Card>

          <Card class="p-4 border border-nexo-border bg-nexo-surface">
            <span class="text-xs text-nexo-muted font-medium uppercase tracking-wider">Annual Run Rate (ARR)</span>
            <div class="text-2xl font-bold text-white mt-1 font-mono">
              ${{ telemetry?.metrics.arr_usd?.toFixed(2) || '0.00' }}
            </div>
            <span class="text-[11px] text-emerald-400 font-mono block mt-1">
              + ৳{{ telemetry?.metrics.arr_bdt?.toFixed(0) || '0' }} BDT / yr
            </span>
          </Card>

          <Card class="p-4 border border-nexo-border bg-nexo-surface">
            <span class="text-xs text-nexo-muted font-medium uppercase tracking-wider">Active Subscribers</span>
            <div class="text-2xl font-bold text-white mt-1">
              {{ telemetry?.metrics.subscribers.active || 0 }}
            </div>
            <span class="text-[11px] text-nexo-muted block mt-1">
              {{ telemetry?.metrics.subscribers.trialing || 0 }} trialing · {{ telemetry?.metrics.subscribers.past_due || 0 }} past due
            </span>
          </Card>

          <Card class="p-4 border border-nexo-border bg-nexo-surface">
            <span class="text-xs text-nexo-muted font-medium uppercase tracking-wider">30-Day Churn Rate</span>
            <div class="text-2xl font-bold text-white mt-1 font-mono">
              {{ telemetry?.metrics.churn_rate_pct || 0 }}%
            </div>
            <span class="text-[11px] text-nexo-muted block mt-1">
              {{ telemetry?.metrics.subscribers.canceled || 0 }} cancellations logged
            </span>
          </Card>
        </div>

        <!-- Volume Breakdown by Gateway -->
        <Card class="p-5 border border-nexo-border bg-nexo-surface">
          <h3 class="text-sm font-semibold text-white mb-3">Collected Revenue Volume by Gateway</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div
              v-for="gw in telemetry?.gateway_volume"
              :key="gw.gateway_key"
              class="p-3 bg-nexo-bg/60 border border-nexo-border/40 rounded-lg"
            >
              <span class="text-nexo-muted uppercase tracking-wider block font-mono">{{ gw.gateway_key || 'Direct Wire' }}</span>
              <span class="text-lg font-bold text-white font-mono mt-1 block">${{ Number(gw.total).toFixed(2) }}</span>
              <span class="text-[11px] text-nexo-muted">{{ gw.count }} settled transactions</span>
            </div>
          </div>
        </Card>

        <!-- Recent Transactions -->
        <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
          <div class="p-4 border-b border-nexo-border flex justify-between items-center">
            <h3 class="text-sm font-semibold text-white">Recent Settled Transactions</h3>
            <span class="text-xs text-nexo-muted font-mono">Real-time ledger events</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-bg/60 text-nexo-muted font-mono uppercase">
                <tr>
                  <th class="p-3">Reference / TrxID</th>
                  <th class="p-3">Subscriber</th>
                  <th class="p-3">Gateway</th>
                  <th class="p-3">Amount</th>
                  <th class="p-3">Status</th>
                  <th class="p-3">Timestamp</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/40">
                <tr v-for="p in telemetry?.recent_payments" :key="p.id" class="hover:bg-nexo-bg/30">
                  <td class="p-3 font-mono font-medium text-white">{{ p.provider_reference || p.id.slice(0, 8) }}</td>
                  <td class="p-3 text-nexo-muted">{{ p.invoice?.subscription?.user?.name || 'Subscriber' }}</td>
                  <td class="p-3 uppercase font-mono">{{ p.gateway_key || p.payment_method }}</td>
                  <td class="p-3 font-mono font-bold text-white">{{ p.currency }} {{ Number(p.amount).toFixed(2) }}</td>
                  <td class="p-3">
                    <span
                      :class="p.status === 'verified' || p.status === 'paid' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-amber-950/80 text-amber-400 border-amber-800/40'"
                      class="px-2 py-0.5 rounded text-[11px] font-mono border"
                    >
                      {{ p.status }}
                    </span>
                  </td>
                  <td class="p-3 text-nexo-muted">{{ new Date(p.created_at).toLocaleString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <!-- TAB 2: PLANS & ENTITLEMENTS -->
      <div v-if="activeTab === 'plans'" class="space-y-4">
        <div class="flex justify-between items-center">
          <span class="text-xs text-nexo-muted">Platform subscription plans and feature entitlement limits.</span>
          <Button @click="isPlanModalOpen = true" size="sm" variant="primary">
            <Plus class="w-4 h-4 mr-1" /> Create Plan
          </Button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card
            v-for="plan in plans"
            :key="plan.id"
            class="p-5 border border-nexo-border bg-nexo-surface flex flex-col justify-between"
          >
            <div>
              <div class="flex justify-between items-center mb-2">
                <h3 class="text-base font-bold text-white">{{ plan.name }}</h3>
                <span
                  :class="plan.is_active ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-nexo-bg text-nexo-muted border-nexo-border'"
                  class="px-2 py-0.5 rounded text-[10px] font-mono border"
                >
                  {{ plan.is_active ? 'ACTIVE' : 'INACTIVE' }}
                </span>
              </div>
              <p class="text-xs text-nexo-muted mb-4">{{ plan.description }}</p>

              <div class="p-3 rounded bg-nexo-bg/60 border border-nexo-border/40 space-y-1 mb-4 text-xs font-mono">
                <div class="flex justify-between">
                  <span class="text-nexo-muted">Monthly USD:</span>
                  <span class="text-white">${{ plan.price_usd_monthly }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-nexo-muted">Annual USD:</span>
                  <span class="text-white">${{ plan.price_usd_annual }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-nexo-muted">Monthly BDT:</span>
                  <span class="text-white">৳{{ plan.price_bdt_monthly }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-nexo-muted">Annual BDT:</span>
                  <span class="text-white">৳{{ plan.price_bdt_annual }}</span>
                </div>
              </div>

              <!-- Entitlements list -->
              <div class="space-y-1 text-xs">
                <span class="text-nexo-muted font-medium uppercase tracking-wider text-[10px]">Entitlements:</span>
                <ul class="space-y-1 text-nexo-text">
                  <li v-for="ent in plan.entitlements" :key="ent.id" class="flex justify-between text-[11px]">
                    <span class="capitalize">{{ ent.feature_key.replace(/_/g, ' ') }}</span>
                    <span class="font-mono text-white font-medium">{{ ent.value }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <div class="mt-4 pt-3 border-t border-nexo-border/60">
              <span class="text-[11px] text-nexo-muted">Sort Order: {{ plan.sort_order || 0 }}</span>
            </div>
          </Card>
        </div>
      </div>

      <!-- TAB 3: SUBSCRIPTIONS QUEUE -->
      <div v-if="activeTab === 'subscriptions'" class="space-y-4">
        <!-- Filter Controls -->
        <div class="flex flex-col sm:flex-row gap-3 justify-between items-center bg-nexo-surface p-3 rounded-lg border border-nexo-border">
          <div class="flex gap-2">
            <select
              v-model="subFilter"
              @change="fetchSubscriptions(1)"
              class="bg-nexo-bg border border-nexo-border rounded px-3 py-1.5 text-xs text-white"
            >
              <option value="all">All Statuses</option>
              <option value="active">Active</option>
              <option value="trialing">Trialing</option>
              <option value="past_due">Past Due</option>
              <option value="cancel_at_period_end">Scheduled to Cancel</option>
              <option value="canceled">Canceled</option>
            </select>
          </div>

          <div class="flex gap-2 w-full sm:w-auto">
            <Input
              v-model="subSearch"
              placeholder="Search user name or email..."
              class="text-xs w-64"
              @keyup.enter="fetchSubscriptions(1)"
            />
            <Button @click="fetchSubscriptions(1)" size="sm" variant="outline">
              <Search class="w-4 h-4" />
            </Button>
          </div>
        </div>

        <!-- Subscriptions Table -->
        <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-bg/60 text-nexo-muted font-mono uppercase">
                <tr>
                  <th class="p-3.5">Subscriber</th>
                  <th class="p-3.5">Plan</th>
                  <th class="p-3.5">Cycle</th>
                  <th class="p-3.5">Status</th>
                  <th class="p-3.5">Valid Until</th>
                  <th class="p-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/40">
                <tr v-for="sub in subscriptions" :key="sub.id" class="hover:bg-nexo-bg/30">
                  <td class="p-3.5">
                    <span class="font-medium text-white block">{{ sub.user?.name || 'User' }}</span>
                    <span class="text-nexo-muted text-[11px]">{{ sub.user?.email }}</span>
                  </td>
                  <td class="p-3.5 font-medium text-white">{{ sub.plan?.name }}</td>
                  <td class="p-3.5 capitalize font-mono">{{ sub.billing_cycle }}</td>
                  <td class="p-3.5">
                    <span
                      :class="sub.status === 'active' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : (sub.status === 'trialing' ? 'bg-amber-950/80 text-amber-400 border-amber-800/40' : 'bg-nexo-bg text-nexo-muted border-nexo-border')"
                      class="px-2 py-0.5 rounded text-[11px] font-mono border"
                    >
                      {{ sub.status }}
                    </span>
                  </td>
                  <td class="p-3.5 text-nexo-muted">
                    {{ sub.ends_at ? new Date(sub.ends_at).toLocaleDateString() : 'Perpetual' }}
                  </td>
                  <td class="p-3.5 text-right">
                    <Button @click="openSubDetails(sub.id)" size="sm" variant="outline" class="text-xs">
                      Inspect & Override
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <!-- TAB 4: INVOICES & LEDGER -->
      <div v-if="activeTab === 'invoices'" class="space-y-4">
        <!-- Filter Controls -->
        <div class="flex flex-col sm:flex-row gap-3 justify-between items-center bg-nexo-surface p-3 rounded-lg border border-nexo-border">
          <div class="flex gap-2">
            <select
              v-model="invFilter"
              @change="fetchInvoices(1)"
              class="bg-nexo-bg border border-nexo-border rounded px-3 py-1.5 text-xs text-white"
            >
              <option value="all">All Invoices</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid</option>
              <option value="void">Void</option>
            </select>
          </div>

          <div class="flex gap-2 w-full sm:w-auto">
            <Input
              v-model="invSearch"
              placeholder="Search invoice # or billing name..."
              class="text-xs w-64"
              @keyup.enter="fetchInvoices(1)"
            />
            <Button @click="fetchInvoices(1)" size="sm" variant="outline">
              <Search class="w-4 h-4" />
            </Button>
          </div>
        </div>

        <!-- Invoices Table -->
        <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-bg/60 text-nexo-muted font-mono uppercase">
                <tr>
                  <th class="p-3.5">Invoice #</th>
                  <th class="p-3.5">Subscriber</th>
                  <th class="p-3.5">Subtotal</th>
                  <th class="p-3.5">Discount</th>
                  <th class="p-3.5">Total</th>
                  <th class="p-3.5">Status</th>
                  <th class="p-3.5">Due / Paid</th>
                  <th class="p-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/40">
                <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-nexo-bg/30">
                  <td class="p-3.5 font-mono font-medium text-white">{{ inv.invoice_number }}</td>
                  <td class="p-3.5">
                    <span class="text-white block">{{ inv.billing_name || inv.subscription?.user?.name }}</span>
                    <span class="text-nexo-muted text-[11px]">{{ inv.billing_email || inv.subscription?.user?.email }}</span>
                  </td>
                  <td class="p-3.5 font-mono">{{ inv.currency }} {{ Number(inv.subtotal).toFixed(2) }}</td>
                  <td class="p-3.5 font-mono text-emerald-400">-{{ inv.currency }} {{ Number(inv.discount).toFixed(2) }}</td>
                  <td class="p-3.5 font-mono font-bold text-white">{{ inv.currency }} {{ Number(inv.total).toFixed(2) }}</td>
                  <td class="p-3.5">
                    <span
                      :class="inv.status === 'paid' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-amber-950/80 text-amber-400 border-amber-800/40'"
                      class="px-2 py-0.5 rounded text-[11px] font-mono border"
                    >
                      {{ inv.status }}
                    </span>
                  </td>
                  <td class="p-3.5 text-nexo-muted">
                    {{ new Date(inv.paid_at || inv.due_date).toLocaleDateString() }}
                  </td>
                  <td class="p-3.5 text-right">
                    <Button @click="openInvoiceDetails(inv.id)" size="sm" variant="outline" class="text-xs">
                      View Details
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <!-- TAB 5: GATEWAY REGISTRY -->
      <div v-if="activeTab === 'gateways'" class="space-y-4">
        <div class="flex justify-between items-center">
          <span class="text-xs text-nexo-muted">
            Independent gateway adapters, live credential verification, and regional eligibility matrix.
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Card
            v-for="gw in gateways"
            :key="gw.gateway_key"
            class="p-5 border border-nexo-border bg-nexo-surface flex flex-col justify-between"
          >
            <div>
              <div class="flex justify-between items-center mb-2">
                <div class="flex items-center gap-2">
                  <Smartphone v-if="gw.gateway_key === 'bkash' || gw.gateway_key === 'nagad'" class="w-5 h-5 text-primary-400" />
                  <CreditCard v-else-if="gw.gateway_key === 'stripe' || gw.gateway_key === 'razorpay'" class="w-5 h-5 text-primary-400" />
                  <Building2 v-else class="w-5 h-5 text-primary-400" />
                  <h3 class="text-base font-bold text-white">{{ gw.name }}</h3>
                </div>
                <span
                  :class="gw.is_active ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-nexo-bg text-nexo-muted border-nexo-border'"
                  class="px-2 py-0.5 rounded text-[10px] font-mono border"
                >
                  {{ gw.is_active ? 'ENABLED' : 'DISABLED' }}
                </span>
              </div>

              <!-- Operational Health Tags -->
              <div class="flex flex-wrap gap-1.5 my-3">
                <span
                  :class="gw.is_configured ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-rose-950/80 text-rose-400 border-rose-800/40'"
                  class="text-[10px] px-2 py-0.5 rounded border font-mono font-medium"
                >
                  {{ gw.is_configured ? 'CREDENTIALS VALID' : 'UNCONFIGURED' }}
                </span>
                <span
                  v-if="gw.test_mode"
                  class="text-[10px] px-2 py-0.5 rounded border font-mono font-medium bg-amber-950/80 text-amber-400 border-amber-800/40"
                >
                  SANDBOX MODE
                </span>
                <span
                  class="text-[10px] px-2 py-0.5 rounded border font-mono font-medium bg-indigo-950/80 text-indigo-400 border-indigo-800/40"
                >
                  {{ gw.supported_currencies.join(', ') }}
                </span>
              </div>

              <p v-if="gw.unavailability_reason" class="text-xs text-rose-400/90 bg-rose-950/30 p-2.5 rounded border border-rose-900/30 mb-3">
                {{ gw.unavailability_reason }}
              </p>

              <!-- Capability Matrix -->
              <div class="space-y-1 text-xs border-t border-nexo-border/60 pt-3">
                <div class="flex justify-between text-nexo-muted">
                  <span>Recurring Auto-Renew:</span>
                  <span :class="gw.supports_recurring ? 'text-emerald-400' : 'text-nexo-muted'">{{ gw.supports_recurring ? 'Supported' : 'No' }}</span>
                </div>
                <div class="flex justify-between text-nexo-muted">
                  <span>Automated Refunds:</span>
                  <span :class="gw.supports_refunds ? 'text-emerald-400' : 'text-nexo-muted'">{{ gw.supports_refunds ? 'Supported' : 'No' }}</span>
                </div>
                <div class="flex justify-between text-nexo-muted">
                  <span>Webhooks HMAC:</span>
                  <span :class="gw.supports_webhooks ? 'text-emerald-400' : 'text-nexo-muted'">{{ gw.supports_webhooks ? 'Active' : 'No' }}</span>
                </div>
              </div>
            </div>

            <div class="mt-4 pt-3 border-t border-nexo-border/60 flex gap-2">
              <Button @click="handleTestGateway(gw.gateway_key)" size="sm" variant="outline" class="w-full text-xs">
                Ping Sandbox
              </Button>
            </div>
          </Card>
        </div>

        <!-- Ping Result Alert -->
        <Alert v-if="testResult" :variant="testResult.success ? 'success' : 'danger'" class="text-xs">
          {{ testResult.message }}
        </Alert>
      </div>

      <!-- TAB 6: REFUNDS & DISPUTES -->
      <div v-if="activeTab === 'refunds'" class="space-y-4">
        <div class="flex justify-between items-center">
          <span class="text-xs text-nexo-muted">Processed refunds, credit notes, and customer dispute adjustments.</span>
          <Button @click="isRefundModalOpen = true" size="sm" variant="primary">
            <Plus class="w-4 h-4 mr-1" /> Issue Refund
          </Button>
        </div>

        <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-bg/60 text-nexo-muted font-mono uppercase">
                <tr>
                  <th class="p-3.5">Refund ID</th>
                  <th class="p-3.5">Subscriber</th>
                  <th class="p-3.5">Amount</th>
                  <th class="p-3.5">Reason</th>
                  <th class="p-3.5">Processed By</th>
                  <th class="p-3.5">Status</th>
                  <th class="p-3.5">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/40">
                <tr v-if="refunds.length === 0">
                  <td colspan="7" class="p-8 text-center text-nexo-muted">No refund records found.</td>
                </tr>
                <tr v-for="rf in refunds" :key="rf.id" class="hover:bg-nexo-bg/30">
                  <td class="p-3.5 font-mono text-white">{{ rf.id.slice(0, 8) }}</td>
                  <td class="p-3.5 text-white">{{ rf.payment?.invoice?.subscription?.user?.name || 'Subscriber' }}</td>
                  <td class="p-3.5 font-mono font-bold text-rose-400">-{{ rf.currency }} {{ Number(rf.amount).toFixed(2) }}</td>
                  <td class="p-3.5 text-nexo-muted max-w-xs truncate">{{ rf.reason }}</td>
                  <td class="p-3.5 text-nexo-muted">{{ rf.processed_by?.name || 'Admin' }}</td>
                  <td class="p-3.5">
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono border bg-primary-950 text-primary-400 border-primary-800">
                      {{ rf.status }}
                    </span>
                  </td>
                  <td class="p-3.5 text-nexo-muted">{{ new Date(rf.created_at).toLocaleDateString() }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <!-- TAB 7: COUPONS & PROMOTIONS -->
      <div v-if="activeTab === 'coupons'" class="space-y-4">
        <div class="flex justify-between items-center">
          <span class="text-xs text-nexo-muted">Promotional discount codes with regional and plan eligibility constraints.</span>
          <Button @click="isCouponModalOpen = true" size="sm" variant="primary">
            <Plus class="w-4 h-4 mr-1" /> Add Coupon
          </Button>
        </div>

        <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-bg/60 text-nexo-muted font-mono uppercase">
                <tr>
                  <th class="p-3.5">Code</th>
                  <th class="p-3.5">Name</th>
                  <th class="p-3.5">Discount</th>
                  <th class="p-3.5">Redemptions</th>
                  <th class="p-3.5">Valid Until</th>
                  <th class="p-3.5">Status</th>
                  <th class="p-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/40">
                <tr v-for="cp in coupons" :key="cp.id" class="hover:bg-nexo-bg/30">
                  <td class="p-3.5 font-mono font-bold text-white tracking-wider">{{ cp.code }}</td>
                  <td class="p-3.5 text-white">{{ cp.name }}</td>
                  <td class="p-3.5 font-mono font-medium text-emerald-400">
                    {{ cp.discount_type === 'percentage' ? `${cp.discount_value}%` : `$${cp.discount_value}` }}
                  </td>
                  <td class="p-3.5 font-mono text-nexo-muted">{{ cp.times_redeemed || 0 }} / {{ cp.max_redemptions || '∞' }}</td>
                  <td class="p-3.5 text-nexo-muted">
                    {{ cp.valid_until ? new Date(cp.valid_until).toLocaleDateString() : 'Perpetual' }}
                  </td>
                  <td class="p-3.5">
                    <span
                      :class="cp.is_active ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-nexo-bg text-nexo-muted border-nexo-border'"
                      class="px-2 py-0.5 rounded text-[10px] font-mono border"
                    >
                      {{ cp.is_active ? 'ACTIVE' : 'INACTIVE' }}
                    </span>
                  </td>
                  <td class="p-3.5 text-right">
                    <Button @click="handleToggleCoupon(cp.id)" size="sm" variant="outline" class="text-xs">
                      {{ cp.is_active ? 'Deactivate' : 'Activate' }}
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <!-- TAB 8: FINANCIAL RECONCILIATION -->
      <div v-if="activeTab === 'reconciliation'" class="space-y-6">
        <Card class="p-5 border border-nexo-border bg-nexo-surface">
          <div class="flex justify-between items-center mb-3">
            <div>
              <h3 class="text-sm font-semibold text-white">Automated Cross-System Reconciliation</h3>
              <p class="text-xs text-nexo-muted">
                Audits transactions, unbacked paid invoices, active subscriptions with overdue invoices, and uncredited payments.
              </p>
            </div>
            <Button @click="handleExecuteReconciliation" :disabled="isReconciling" variant="primary" size="sm">
              <RefreshCw :class="isReconciling ? 'animate-spin' : ''" class="w-4 h-4 mr-1.5" />
              Run Audit Now
            </Button>
          </div>

          <!-- Latest Run Result -->
          <div v-if="reconciliationResult" class="p-4 rounded-lg bg-nexo-bg border border-nexo-border space-y-3 text-xs">
            <div class="flex justify-between items-center">
              <span class="font-bold text-white flex items-center gap-1.5">
                <CheckCircle2 v-if="reconciliationResult.discrepancies_found === 0" class="w-4 h-4 text-emerald-400" />
                <AlertTriangle v-else class="w-4 h-4 text-amber-400" />
                Audit Status: {{ reconciliationResult.status.toUpperCase().replace(/_/g, ' ') }}
              </span>
              <span class="text-nexo-muted font-mono">Date: {{ reconciliationResult.run_date }}</span>
            </div>

            <div class="grid grid-cols-3 gap-3 font-mono">
              <div class="p-2.5 rounded bg-nexo-surface border border-nexo-border">
                <span class="text-nexo-muted block text-[10px]">Total Checked:</span>
                <span class="text-white font-bold text-sm">{{ reconciliationResult.total_transactions }}</span>
              </div>
              <div class="p-2.5 rounded bg-nexo-surface border border-nexo-border">
                <span class="text-nexo-muted block text-[10px]">Verified:</span>
                <span class="text-emerald-400 font-bold text-sm">{{ reconciliationResult.verified_transactions }}</span>
              </div>
              <div class="p-2.5 rounded bg-nexo-surface border border-nexo-border">
                <span class="text-nexo-muted block text-[10px]">Discrepancies:</span>
                <span :class="reconciliationResult.discrepancies_found > 0 ? 'text-rose-400' : 'text-emerald-400'" class="font-bold text-sm">
                  {{ reconciliationResult.discrepancies_found }}
                </span>
              </div>
            </div>

            <!-- Discrepancy Items -->
            <div v-if="reconciliationResult.discrepancies?.length > 0" class="space-y-2 pt-2 border-t border-nexo-border">
              <span class="text-rose-400 font-bold block">Detected Discrepancies:</span>
              <div
                v-for="(disc, idx) in reconciliationResult.discrepancies"
                :key="idx"
                class="p-2 rounded bg-rose-950/20 border border-rose-900/30 flex justify-between items-center text-[11px]"
              >
                <div>
                  <span class="font-bold text-rose-300 uppercase block font-mono">{{ disc.type }}</span>
                  <span class="text-nexo-muted">{{ disc.message }}</span>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono border bg-rose-950 text-rose-400 border-rose-800">{{ disc.severity }}</span>
              </div>
            </div>
          </div>
        </Card>

        <!-- Historical Runs -->
        <Card class="border border-nexo-border bg-nexo-surface overflow-hidden">
          <div class="p-4 border-b border-nexo-border">
            <h3 class="text-sm font-semibold text-white">Previous Reconciliation Runs</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-bg/60 text-nexo-muted font-mono uppercase">
                <tr>
                  <th class="p-3">Run Date</th>
                  <th class="p-3">Gateway</th>
                  <th class="p-3">Transactions</th>
                  <th class="p-3">Discrepancies</th>
                  <th class="p-3">Auditor</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/40">
                <tr v-for="run in reconciliationHistory" :key="run.id" class="hover:bg-nexo-bg/30">
                  <td class="p-3 font-mono text-white">{{ run.run_date }}</td>
                  <td class="p-3 uppercase font-mono">{{ run.gateway_key }}</td>
                  <td class="p-3 font-mono text-white">{{ run.total_internal_transactions }}</td>
                  <td class="p-3 font-mono" :class="run.discrepancy_count > 0 ? 'text-rose-400 font-bold' : 'text-emerald-400'">
                    {{ run.discrepancy_count }}
                  </td>
                  <td class="p-3 text-nexo-muted">{{ run.executed_by?.name || 'Automated' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

    </div>

    <!-- SUBSCRIPTION INSPECT & OVERRIDE MODAL -->
    <Modal
      :isOpen="isSubModalOpen"
      :title="`Subscription Control — ${selectedSub?.user?.name || 'Subscriber'}`"
      maxWidth="2xl"
      @close="isSubModalOpen = false"
    >
      <div v-if="selectedSub" class="space-y-5 text-left text-xs">
        <div class="grid grid-cols-2 gap-3 p-3 bg-nexo-bg rounded border border-nexo-border font-mono">
          <div><span class="text-nexo-muted">ID:</span> <span class="text-white">{{ selectedSub.id }}</span></div>
          <div><span class="text-nexo-muted">Current Status:</span> <span :class="selectedSub.status === 'active' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-nexo-bg text-nexo-muted border-nexo-border'" class="px-2 py-0.5 rounded text-[10px] font-mono border">{{ selectedSub.status }}</span></div>
          <div><span class="text-nexo-muted">Plan:</span> <span class="text-white">{{ selectedSub.plan?.name }}</span></div>
          <div><span class="text-nexo-muted">Billing Cycle:</span> <span class="text-white capitalize">{{ selectedSub.billing_cycle }}</span></div>
        </div>

        <!-- Status Change Control -->
        <div class="p-3 rounded bg-nexo-surface border border-nexo-border space-y-2">
          <span class="font-bold text-white block">Manual Status Override:</span>
          <div class="flex gap-2">
            <select v-model="newSubStatus" class="bg-nexo-bg border border-nexo-border rounded px-2 py-1 text-white">
              <option value="active">Active</option>
              <option value="paused">Paused</option>
              <option value="past_due">Past Due</option>
              <option value="canceled">Canceled</option>
              <option value="expired">Expired</option>
            </select>
            <Input v-model="subStatusReason" placeholder="Mandatory audit reason..." class="flex-1" />
            <Button @click="handleUpdateSubStatus" size="sm" variant="primary">Update Status</Button>
          </div>
        </div>

        <!-- Active Entitlement Overrides -->
        <div class="p-3 rounded bg-nexo-surface border border-nexo-border space-y-2">
          <div class="flex justify-between items-center">
            <span class="font-bold text-white">Custom Entitlement Overrides:</span>
            <Button @click="isOverrideModalOpen = true" size="sm" variant="outline">
              <Plus class="w-3.5 h-3.5 mr-1" /> Grant Override
            </Button>
          </div>
          <div v-if="selectedSub.entitlement_overrides?.length === 0" class="text-nexo-muted py-2">
            No active overrides granted to this subscriber.
          </div>
          <div
            v-for="ov in selectedSub.entitlement_overrides"
            :key="ov.id"
            class="flex justify-between items-center p-2 rounded bg-nexo-bg border border-nexo-border/60"
          >
            <div>
              <span class="text-white font-mono font-medium block">{{ ov.feature_key }} = {{ ov.value }}</span>
              <span class="text-nexo-muted text-[11px]">{{ ov.reason }}</span>
            </div>
            <Button @click="handleRevokeOverride(ov.id)" size="sm" variant="ghost" class="text-rose-400">
              Revoke
            </Button>
          </div>
        </div>

        <!-- Lifecycle Audit Trail -->
        <div class="space-y-1">
          <span class="font-bold text-white block">Lifecycle State Transitions:</span>
          <div class="max-h-40 overflow-y-auto space-y-1 pr-1">
            <div
              v-for="h in selectedSub.history"
              :key="h.id"
              class="p-2 rounded bg-nexo-bg/60 border border-nexo-border/40 text-[11px] flex justify-between"
            >
              <div>
                <span class="text-primary-400 font-mono font-medium">{{ h.previous_status }} → {{ h.new_status }}</span>
                <span class="text-nexo-muted block">{{ h.reason }}</span>
              </div>
              <span class="text-nexo-muted text-[10px]">{{ new Date(h.created_at).toLocaleString() }}</span>
            </div>
          </div>
        </div>
      </div>
    </Modal>

    <!-- GRANT OVERRIDE MODAL -->
    <Modal
      :isOpen="isOverrideModalOpen"
      title="Grant Custom Entitlement Override"
      maxWidth="md"
      @close="isOverrideModalOpen = false"
    >
      <div class="space-y-3 text-left text-xs">
        <div>
          <label class="text-nexo-muted block mb-1">Feature Key</label>
          <select v-model="overrideForm.feature_key" class="w-full bg-nexo-bg border border-nexo-border rounded px-3 py-1.5 text-white">
            <option value="max_watchlists">max_watchlists</option>
            <option value="max_entities_per_watchlist">max_entities_per_watchlist</option>
            <option value="max_active_monitors">max_active_monitors</option>
            <option value="daily_research_queries">daily_research_queries</option>
            <option value="historical_timeline_days">historical_timeline_days</option>
            <option value="ai_deep_research">ai_deep_research (true/false)</option>
            <option value="publication.pdf_download">publication.pdf_download (true/false)</option>
          </select>
        </div>

        <div>
          <label class="text-nexo-muted block mb-1">Override Value</label>
          <Input v-model="overrideForm.value" placeholder="e.g. 50 or unlimited or true" />
        </div>

        <div>
          <label class="text-nexo-muted block mb-1">Reason for Override *</label>
          <Input v-model="overrideForm.reason" placeholder="e.g. Enterprise VIP pilot request" />
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button @click="isOverrideModalOpen = false" variant="ghost">Cancel</Button>
          <Button @click="handleGrantOverride" variant="primary">Grant Override</Button>
        </div>
      </div>
    </Modal>

    <!-- ISSUE REFUND MODAL -->
    <Modal
      :isOpen="isRefundModalOpen"
      title="Issue Refund / Credit Note"
      maxWidth="md"
      @close="isRefundModalOpen = false"
    >
      <div class="space-y-3 text-left text-xs">
        <div>
          <label class="text-nexo-muted block mb-1">Payment ID *</label>
          <Input v-model="refundPaymentId" placeholder="UUID of the settled payment" />
        </div>

        <div>
          <label class="text-nexo-muted block mb-1">Refund Amount *</label>
          <Input v-model.number="refundAmount" type="number" step="0.01" placeholder="Amount to refund" />
        </div>

        <div>
          <label class="text-nexo-muted block mb-1">Reason *</label>
          <Input v-model="refundReason" placeholder="e.g. Dissatisfied with service / Courtesy credit" />
        </div>

        <div>
          <label class="text-nexo-muted block mb-1">Internal Notes</label>
          <Input v-model="refundNotes" placeholder="Optional audit memo" />
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button @click="isRefundModalOpen = false" variant="ghost">Cancel</Button>
          <Button @click="handleProcessRefund" :disabled="isProcessingRefund" variant="danger">
            {{ isProcessingRefund ? 'Processing...' : 'Confirm Refund' }}
          </Button>
        </div>
      </div>
    </Modal>

    <!-- CREATE COUPON MODAL -->
    <Modal
      :isOpen="isCouponModalOpen"
      title="Create Promotional Coupon"
      maxWidth="md"
      @close="isCouponModalOpen = false"
    >
      <div class="space-y-3 text-left text-xs">
        <div>
          <label class="text-nexo-muted block mb-1">Coupon Code *</label>
          <Input v-model="couponForm.code" placeholder="e.g. LAUNCH30" class="uppercase font-mono" />
        </div>

        <div>
          <label class="text-nexo-muted block mb-1">Campaign Name *</label>
          <Input v-model="couponForm.name" placeholder="e.g. Q4 Launch Special" />
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="text-nexo-muted block mb-1">Discount Type</label>
            <select v-model="couponForm.discount_type" class="w-full bg-nexo-bg border border-nexo-border rounded px-3 py-1.5 text-white">
              <option value="percentage">Percentage (%)</option>
              <option value="fixed">Fixed Amount ($)</option>
            </select>
          </div>
          <div>
            <label class="text-nexo-muted block mb-1">Value</label>
            <Input v-model.number="couponForm.discount_value" type="number" step="0.01" />
          </div>
        </div>

        <div>
          <label class="text-nexo-muted block mb-1">Max Redemptions</label>
          <Input v-model.number="couponForm.max_redemptions" type="number" />
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button @click="isCouponModalOpen = false" variant="ghost">Cancel</Button>
          <Button @click="handleCreateCoupon" variant="primary">Create Coupon</Button>
        </div>
      </div>
    </Modal>

    <!-- INVOICE DETAILS MODAL -->
    <Modal
      :isOpen="isInvModalOpen"
      :title="`Invoice ${selectedInvoice?.invoice_number || ''}`"
      maxWidth="lg"
      @close="isInvModalOpen = false"
    >
      <div v-if="selectedInvoice" class="space-y-4 text-left text-xs">
        <div class="p-3 bg-nexo-bg rounded border border-nexo-border space-y-1 font-mono">
          <div class="flex justify-between">
            <span class="text-nexo-muted">Customer:</span>
            <span class="text-white">{{ selectedInvoice.billing_name || selectedInvoice.subscription?.user?.name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-nexo-muted">Status:</span>
            <span :class="selectedInvoice.status === 'paid' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-amber-950/80 text-amber-400 border-amber-800/40'" class="px-2 py-0.5 rounded text-[10px] font-mono border">{{ selectedInvoice.status }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-nexo-muted">Total:</span>
            <span class="text-white font-bold">{{ selectedInvoice.currency }} {{ Number(selectedInvoice.total).toFixed(2) }}</span>
          </div>
        </div>

        <div>
          <span class="font-bold text-white block mb-1">Line Items:</span>
          <div
            v-for="item in selectedInvoice.items"
            :key="item.id"
            class="flex justify-between p-2 rounded bg-nexo-surface border border-nexo-border/60"
          >
            <span>{{ item.description }}</span>
            <span class="font-mono text-white">{{ selectedInvoice.currency }} {{ Number(item.amount).toFixed(2) }}</span>
          </div>
        </div>

        <div>
          <span class="font-bold text-white block mb-1">Payment Transactions:</span>
          <div v-if="selectedInvoice.payments?.length === 0" class="text-nexo-muted">
            No payments recorded yet.
          </div>
          <div
            v-for="p in selectedInvoice.payments"
            :key="p.id"
            class="p-2 rounded bg-nexo-surface border border-nexo-border/60 flex justify-between font-mono"
          >
            <div>
              <span class="text-white block">{{ p.gateway_key || p.payment_method }} ({{ p.provider_reference || 'Pending' }})</span>
              <span class="text-nexo-muted text-[10px]">{{ new Date(p.created_at).toLocaleString() }}</span>
            </div>
            <div class="text-right">
              <span class="text-white font-bold block">{{ p.currency }} {{ Number(p.amount).toFixed(2) }}</span>
              <span :class="p.status === 'verified' || p.status === 'paid' ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800/40' : 'bg-amber-950/80 text-amber-400 border-amber-800/40'" class="px-2 py-0.5 rounded text-[10px] font-mono border">{{ p.status }}</span>
            </div>
          </div>
        </div>
      </div>
    </Modal>
  </AdminLayout>
</template>
