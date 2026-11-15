<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import Modal from '@/components/ui/Modal.vue'
import { billingService } from '@/services/api/billing.service'
import {
  CheckCircle2,
  Clock,
  AlertCircle,
  RefreshCw,
  Download,
  ArrowRight,
  Shield,
  CreditCard,
  Building2,
  Smartphone,
  ChevronLeft,
  FileText
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()

const reference = ref<string>((route.params.reference as string) || '')
const isLoading = ref(true)
const isRetrying = ref(false)
const showRetryModal = ref(false)
const retryMethod = ref('manual_bank')
const errorMessage = ref<string | null>(null)
const statusData = ref<any>(null)
let pollInterval: any = null
let pollCount = 0

async function fetchStatus(silent = false) {
  if (!silent) isLoading.value = true
  errorMessage.value = null

  try {
    const res = await billingService.getPaymentStatus(reference.value)
    if (res.success && res.data) {
      statusData.value = res.data

      // Stop polling if definitive terminal state reached
      if (['paid', 'failed', 'pending_review'].includes(res.data.status)) {
        stopPolling()
      }
    } else {
      errorMessage.value = res.message || 'Payment status could not be resolved.'
    }
  } catch (err: any) {
    console.error('Failed to resolve payment status:', err)
    if (err.response?.status === 403) {
      errorMessage.value = 'Access Denied: You do not have permission to inspect this transaction.'
    } else if (err.response?.status === 404) {
      errorMessage.value = 'Transaction reference was not found on our ledger.'
    } else {
      errorMessage.value = err.response?.data?.message || 'Error communicating with billing server.'
    }
    stopPolling()
  } finally {
    if (!silent) isLoading.value = false
  }
}

function startPolling() {
  stopPolling()
  pollCount = 0
  pollInterval = setInterval(() => {
    pollCount++
    if (pollCount > 10) {
      stopPolling()
      return
    }
    fetchStatus(true)
  }, 4000)
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

async function handleRetryPayment() {
  if (!statusData.value?.invoice?.id) return
  isRetrying.value = true

  try {
    const res = await billingService.retryPayment({
      invoice_id: statusData.value.invoice.id,
      payment_method: retryMethod.value
    })

    if (res.success && res.data) {
      showRetryModal.value = false
      const newRef = res.data.transaction_reference || statusData.value.invoice.id
      reference.value = newRef
      await fetchStatus()
    } else {
      alert(res.message || 'Retry could not be initiated.')
    }
  } catch (err: any) {
    console.error('Payment retry error:', err)
    alert(err.response?.data?.message || 'Failed to retry payment.')
  } finally {
    isRetrying.value = false
  }
}

onMounted(async () => {
  await fetchStatus()
  if (statusData.value?.status === 'pending' || statusData.value?.status === 'processing') {
    startPolling()
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <AuthenticatedLayout>
    <div class="min-h-screen bg-nexo-bg py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-2xl mx-auto space-y-6">

        <!-- Back Link -->
        <router-link to="/billing" class="inline-flex items-center text-xs text-nexo-muted hover:text-nexo-accent transition-colors">
          <ChevronLeft class="w-4 h-4 mr-1" />
          Back to Billing &amp; Subscriptions
        </router-link>

        <!-- Loading State -->
        <Card v-if="isLoading" class="p-12 text-center bg-nexo-surface border-nexo-border">
          <RefreshCw class="w-10 h-10 text-nexo-accent animate-spin mx-auto mb-4" />
          <h2 class="text-base font-medium text-nexo-text">Resolving Ledger Status...</h2>
          <p class="text-xs text-nexo-muted mt-1">Verifying cryptographic reference against gateway records.</p>
        </Card>

        <!-- Error Notification -->
        <Alert v-else-if="errorMessage" variant="danger">
          <div class="flex items-center gap-2">
            <AlertCircle class="w-5 h-5 flex-shrink-0" />
            <span>{{ errorMessage }}</span>
          </div>
        </Alert>

        <!-- Resolved Status Cards -->
        <div v-else-if="statusData" class="space-y-6">

          <!-- 1. PAID & ACTIVE SUCCESS STATE -->
          <Card v-if="statusData.status === 'paid' || statusData.invoice?.status === 'paid'" class="p-8 text-center bg-nexo-surface border-emerald-500/30 shadow-lg space-y-6">
            <div class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center mx-auto">
              <CheckCircle2 class="w-9 h-9" />
            </div>

            <div>
              <span class="inline-block text-xs px-2.5 py-0.5 rounded-full border border-emerald-500/40 text-emerald-400 font-sans font-medium mb-2">
                Payment Confirmed
              </span>
              <h1 class="text-2xl font-serif font-bold text-nexo-text">Subscription Activated</h1>
              <p class="text-sm text-nexo-muted mt-1">
                Your payment for {{ statusData.subscription?.plan_name || 'Executive Intelligence' }} has been successfully recorded.
              </p>
            </div>

            <!-- Receipt Details Grid -->
            <div class="p-4 rounded-lg bg-nexo-bg border border-nexo-border text-xs text-left space-y-2">
              <div class="flex justify-between py-1 border-b border-nexo-border/50">
                <span class="text-nexo-muted">Invoice Number</span>
                <span class="font-mono font-medium text-nexo-text">{{ statusData.invoice?.invoice_number }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-nexo-border/50">
                <span class="text-nexo-muted">Amount Paid</span>
                <span class="font-bold text-nexo-text">
                  {{ statusData.invoice?.currency === 'BDT' ? '৳' : '$' }}{{ Number(statusData.invoice?.total).toLocaleString() }} {{ statusData.invoice?.currency }}
                </span>
              </div>
              <div class="flex justify-between py-1 border-b border-nexo-border/50">
                <span class="text-nexo-muted">Payment Gateway</span>
                <span class="font-medium text-nexo-text capitalize">{{ statusData.payment?.gateway_key || 'Direct Verification' }}</span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-nexo-muted">Transaction Reference</span>
                <span class="font-mono text-nexo-accent">{{ statusData.payment?.provider_reference || statusData.reference }}</span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center pt-2">
              <a
                v-if="statusData.receipt_url"
                :href="statusData.receipt_url"
                target="_blank"
                class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg border border-nexo-border bg-nexo-bg text-nexo-text text-xs font-semibold hover:border-nexo-accent transition-colors"
              >
                <Download class="w-4 h-4 mr-2" />
                Download PDF Receipt
              </a>
              <router-link
                to="/feed"
                class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg bg-nexo-accent text-white text-xs font-semibold hover:bg-nexo-accent/90 shadow-md transition-colors"
              >
                Launch Intelligence Feed
                <ArrowRight class="w-4 h-4 ml-2" />
              </router-link>
            </div>
          </Card>

          <!-- 2. PENDING REVIEW (Bank Wire / MFS Manual Proof) -->
          <Card v-else-if="statusData.status === 'pending_review'" class="p-8 text-center bg-nexo-surface border-amber-500/30 shadow-lg space-y-6">
            <div class="w-16 h-16 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center mx-auto">
              <Clock class="w-9 h-9" />
            </div>

            <div>
              <span class="inline-block text-xs px-2.5 py-0.5 rounded-full border border-amber-500/40 text-amber-400 font-sans font-medium mb-2">
                Under Compliance Review
              </span>
              <h1 class="text-2xl font-serif font-bold text-nexo-text">Verification In Progress</h1>
              <p class="text-sm text-nexo-muted mt-1 max-w-md mx-auto">
                We have received your payment reference. Our institutional compliance desk verifies incoming transfers within 2-4 business hours.
              </p>
            </div>

            <!-- Reference Metadata -->
            <div class="p-4 rounded-lg bg-nexo-bg border border-nexo-border text-xs text-left space-y-2">
              <div class="flex justify-between py-1 border-b border-nexo-border/50">
                <span class="text-nexo-muted">Submitted Reference (TrxID)</span>
                <span class="font-mono font-bold text-amber-400">{{ statusData.payment?.proof?.transaction_id || statusData.reference }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-nexo-border/50">
                <span class="text-nexo-muted">Parent Invoice</span>
                <span class="font-mono text-nexo-text">{{ statusData.invoice?.invoice_number }}</span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-nexo-muted">Due Total</span>
                <span class="font-medium text-nexo-text">{{ statusData.invoice?.currency === 'BDT' ? '৳' : '$' }}{{ Number(statusData.invoice?.total).toLocaleString() }}</span>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
              <Button variant="outline" size="sm" @click="fetchStatus()">
                <RefreshCw class="w-3.5 h-3.5 mr-1.5" />
                Refresh Status
              </Button>
              <router-link
                to="/billing"
                class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-nexo-accent text-white text-xs font-semibold hover:bg-nexo-accent/90 transition-colors"
              >
                Go to Billing Desk
              </router-link>
            </div>
          </Card>

          <!-- 3. FAILED PAYMENT STATE & SAFE RECOVERY -->
          <Card v-else-if="statusData.status === 'failed'" class="p-8 text-center bg-nexo-surface border-rose-500/30 shadow-lg space-y-6">
            <div class="w-16 h-16 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center mx-auto">
              <AlertCircle class="w-9 h-9" />
            </div>

            <div>
              <span class="inline-block text-xs px-2.5 py-0.5 rounded-full border border-rose-500/40 text-rose-400 font-sans font-medium mb-2">
                Transaction Incomplete
              </span>
              <h1 class="text-2xl font-serif font-bold text-nexo-text">Payment Not Completed</h1>
              <p class="text-sm text-nexo-muted mt-1 max-w-md mx-auto">
                The payment attempt did not complete successfully. No funds have been deducted from your account.
              </p>
            </div>

            <div class="p-4 rounded-lg bg-nexo-bg border border-nexo-border text-xs text-left">
              <div class="text-nexo-muted mb-1">Invoice Reference</div>
              <div class="font-mono text-nexo-text">{{ statusData.invoice?.invoice_number }} &bull; {{ statusData.invoice?.currency === 'BDT' ? '৳' : '$' }}{{ Number(statusData.invoice?.total).toLocaleString() }}</div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
              <Button
                variant="primary"
                size="sm"
                class="bg-nexo-accent hover:bg-nexo-accent/90 text-white"
                @click="showRetryModal = true"
              >
                <RefreshCw class="w-3.5 h-3.5 mr-1.5" />
                Retry with Alternative Method
              </Button>
              <router-link to="/checkout" class="inline-flex items-center justify-center px-4 py-2 rounded-lg border border-nexo-border text-xs text-nexo-muted hover:text-nexo-text">
                Start New Checkout
              </router-link>
            </div>
          </Card>

          <!-- 4. PENDING / PROCESSING STATE -->
          <Card v-else class="p-8 text-center bg-nexo-surface border-nexo-border shadow-lg space-y-6">
            <div class="w-16 h-16 rounded-full bg-nexo-accent/10 text-nexo-accent border border-nexo-accent/20 flex items-center justify-center mx-auto">
              <RefreshCw class="w-9 h-9 animate-spin" />
            </div>

            <div>
              <span class="inline-block text-xs px-2.5 py-0.5 rounded-full border border-nexo-accent/40 text-nexo-accent font-sans font-medium mb-2">
                Awaiting Gateway Callback
              </span>
              <h1 class="text-2xl font-serif font-bold text-nexo-text">Processing Authorization</h1>
              <p class="text-sm text-nexo-muted mt-1 max-w-md mx-auto">
                We are waiting for authorization confirmation from your payment provider. This page will update automatically.
              </p>
            </div>

            <div class="flex justify-center">
              <Button variant="outline" size="sm" @click="fetchStatus()">
                <RefreshCw class="w-3.5 h-3.5 mr-1.5" />
                Check Now
              </Button>
            </div>
          </Card>

        </div>

      </div>
    </div>

    <!-- Safe Payment Retry Modal -->
    <Modal
      :isOpen="showRetryModal"
      title="Select Payment Method to Retry"
      @close="showRetryModal = false"
    >
      <div class="space-y-4">
        <p class="text-xs text-nexo-muted">
          Re-authorize your unpaid invoice ({{ statusData?.invoice?.invoice_number }}) without generating duplicate bills.
        </p>

        <div class="space-y-2">
          <label
            class="flex items-center justify-between p-3 rounded-lg border cursor-pointer"
            :class="retryMethod === 'bkash' ? 'border-nexo-accent bg-nexo-accent/5' : 'border-nexo-border bg-nexo-bg'"
            @click="retryMethod = 'bkash'"
          >
            <div class="flex items-center gap-2">
              <Smartphone class="w-4 h-4 text-pink-400" />
              <span class="text-xs font-medium text-nexo-text">bKash Mobile Payment</span>
            </div>
            <input type="radio" v-model="retryMethod" value="bkash" class="text-nexo-accent" />
          </label>

          <label
            class="flex items-center justify-between p-3 rounded-lg border cursor-pointer"
            :class="retryMethod === 'nagad' ? 'border-nexo-accent bg-nexo-accent/5' : 'border-nexo-border bg-nexo-bg'"
            @click="retryMethod = 'nagad'"
          >
            <div class="flex items-center gap-2">
              <Smartphone class="w-4 h-4 text-orange-400" />
              <span class="text-xs font-medium text-nexo-text">Nagad Digital Payment</span>
            </div>
            <input type="radio" v-model="retryMethod" value="nagad" class="text-nexo-accent" />
          </label>

          <label
            class="flex items-center justify-between p-3 rounded-lg border cursor-pointer"
            :class="retryMethod === 'stripe' ? 'border-nexo-accent bg-nexo-accent/5' : 'border-nexo-border bg-nexo-bg'"
            @click="retryMethod = 'stripe'"
          >
            <div class="flex items-center gap-2">
              <CreditCard class="w-4 h-4 text-nexo-accent" />
              <span class="text-xs font-medium text-nexo-text">International Credit / Debit Card</span>
            </div>
            <input type="radio" v-model="retryMethod" value="stripe" class="text-nexo-accent" />
          </label>

          <label
            class="flex items-center justify-between p-3 rounded-lg border cursor-pointer"
            :class="retryMethod === 'manual_bank' ? 'border-nexo-accent bg-nexo-accent/5' : 'border-nexo-border bg-nexo-bg'"
            @click="retryMethod = 'manual_bank'"
          >
            <div class="flex items-center gap-2">
              <Building2 class="w-4 h-4 text-nexo-muted" />
              <span class="text-xs font-medium text-nexo-text">Corporate Bank Wire / Transfer</span>
            </div>
            <input type="radio" v-model="retryMethod" value="manual_bank" class="text-nexo-accent" />
          </label>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-nexo-border">
          <Button variant="ghost" size="sm" @click="showRetryModal = false">Cancel</Button>
          <Button
            variant="primary"
            size="sm"
            class="bg-nexo-accent text-white"
            :loading="isRetrying"
            @click="handleRetryPayment"
          >
            Confirm &amp; Re-Authorize
          </Button>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
