<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Check, CheckCircle2 } from 'lucide-vue-next'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import { apiClient } from '@/services/api/client'

const plans = ref<any[]>([])
const currentSub = ref<any>(null)
const invoices = ref<any[]>([])
const paymentMethods = ref<any[]>([])
const currency = ref<'BDT' | 'USD'>('BDT')

// Payment Submission State
const activeInvoice = ref<any>(null)
const selectedMethod = ref('manual_bkash')
const transactionId = ref('')
const senderAccount = ref('')
const submitSuccess = ref('')
const submitError = ref('')
const isSubmitting = ref(false)

const fetchData = async () => {
  try {
    const [plansRes, subRes, invRes, methodsRes] = await Promise.all([
      apiClient.get('/subscriptions/plans'),
      apiClient.get('/subscriptions/current'),
      apiClient.get('/payments/invoices'),
      apiClient.get('/payments/methods'),
    ])

    plans.value = plansRes.data.data
    currentSub.value = subRes.data.subscription
    invoices.value = invRes.data.data
    paymentMethods.value = methodsRes.data.data
  } catch {
    //
  }
}

const handleCheckout = async (plan: any) => {
  submitError.value = ''
  submitSuccess.value = ''
  try {
    const res = await apiClient.post('/subscriptions/checkout', {
      plan_id: plan.id,
      billing_cycle: 'monthly',
      currency: currency.value,
    })
    activeInvoice.value = res.data.invoice
    await fetchData()
  } catch (err: any) {
    submitError.value = err.response?.data?.message || 'Checkout failed.'
  }
}

const handleSubmitProof = async () => {
  if (!transactionId.value) {
    submitError.value = 'Please provide the transaction ID (TrxID) from your payment receipt.'
    return
  }

  isSubmitting.value = true
  submitError.value = ''
  submitSuccess.value = ''

  try {
    const res = await apiClient.post('/payments/submit', {
      invoice_id: activeInvoice.value.id,
      payment_method: selectedMethod.value,
      transaction_id: transactionId.value,
      sender_account: senderAccount.value,
    })
    submitSuccess.value = res.data.message
    transactionId.value = ''
    senderAccount.value = ''
    activeInvoice.value = null
    await fetchData()
  } catch (err: any) {
    submitError.value = err.response?.data?.error?.message || err.response?.data?.message || 'Payment submission failed.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-8 overflow-y-auto max-w-7xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>SaaS Entitlements</span>
              <span>/</span>
              <span>Subscription & Invoicing</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
              Subscription Management & Payments
            </h1>
          </div>

          <!-- Currency Switcher -->
          <div class="flex items-center bg-nexo-surface border border-nexo-border rounded-lg p-1 text-xs font-mono">
            <button
              :class="[
                'px-3 py-1 rounded transition-colors',
                currency === 'BDT' ? 'bg-nexo-accent-cyan text-nexo-bg font-bold' : 'text-nexo-text-secondary hover:text-nexo-text-primary'
              ]"
              @click="currency = 'BDT'"
            >
              BDT (৳ Local)
            </button>
            <button
              :class="[
                'px-3 py-1 rounded transition-colors',
                currency === 'USD' ? 'bg-nexo-accent-cyan text-nexo-bg font-bold' : 'text-nexo-text-secondary hover:text-nexo-text-primary'
              ]"
              @click="currency = 'USD'"
            >
              USD ($ Global)
            </button>
          </div>
        </div>

        <Alert v-if="submitSuccess" variant="success" dismissible @dismiss="submitSuccess = ''">
          {{ submitSuccess }}
        </Alert>

        <Alert v-if="submitError" variant="danger" dismissible @dismiss="submitError = ''">
          {{ submitError }}
        </Alert>

        <!-- Current Subscription Status Banner -->
        <Card v-if="currentSub" padding="md" class="border-nexo-accent-gold/40">
          <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="space-y-1">
              <div class="flex items-center space-x-2">
                <Badge variant="gold" size="sm">Active Plan</Badge>
                <span class="font-serif text-xl font-bold text-nexo-text-primary">{{ currentSub.plan?.name }} Tier</span>
              </div>
              <p class="text-xs text-nexo-text-secondary">
                Status: <span class="font-mono text-emerald-400 uppercase font-semibold">{{ currentSub.status }}</span>
                • Billing Cycle: {{ currentSub.billing_cycle }}
              </p>
            </div>

            <div class="text-right">
              <span class="text-xs font-mono text-nexo-text-muted block">Expires / Renews</span>
              <span class="font-mono text-sm text-nexo-text-primary font-semibold">
                {{ currentSub.ends_at ? new Date(currentSub.ends_at).toLocaleDateString() : 'Active Free Tier' }}
              </span>
            </div>
          </div>
        </Card>

        <!-- Active Payment Modal / Instructions Panel -->
        <div v-if="activeInvoice" class="p-6 bg-nexo-surface border border-nexo-accent-cyan/60 rounded-lg space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border/60 pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-text-primary">
              Invoice Payment Instructions ({{ activeInvoice.invoice_number }})
            </h3>
            <span class="font-mono text-base font-bold text-nexo-accent-cyan">
              {{ activeInvoice.currency === 'BDT' ? '৳' : '$' }}{{ activeInvoice.total }}
            </span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div class="bg-nexo-elevated/40 p-4 rounded border border-nexo-border space-y-2">
              <span class="font-mono font-bold text-nexo-text-primary uppercase text-[11px] block">
                Approved Payment Methods:
              </span>
              <div class="space-y-2">
                <div v-for="meth in paymentMethods" :key="meth.provider_key" class="p-2 bg-nexo-surface rounded border border-nexo-border/60">
                  <span class="font-bold text-nexo-accent-cyan block">{{ meth.display_name }}</span>
                  <pre class="whitespace-pre-wrap font-sans text-nexo-text-secondary text-[11px] mt-1">{{ meth.instructions }}</pre>
                </div>
              </div>
            </div>

            <!-- Proof Submission Form -->
            <form class="space-y-3 bg-nexo-elevated/40 p-4 rounded border border-nexo-border" @submit.prevent="handleSubmitProof">
              <span class="font-mono font-bold text-nexo-text-primary uppercase text-[11px] block">
                Submit Payment Reference for Activation:
              </span>

              <div class="space-y-1">
                <label class="text-[10px] font-mono text-nexo-text-muted uppercase">Payment Channel</label>
                <select
                  v-model="selectedMethod"
                  class="w-full bg-nexo-surface text-nexo-text-primary text-xs px-3 py-2 rounded border border-nexo-border"
                >
                  <option value="manual_bkash">bKash Merchant Payment</option>
                  <option value="manual_nagad">Nagad Merchant Payment</option>
                  <option value="manual_bank">Bank Transfer / Wire</option>
                </select>
              </div>

              <Input
                v-model="transactionId"
                label="Transaction ID (TrxID / Wire Reference)"
                placeholder="e.g. TRX998822A1"
                required
              />

              <Input
                v-model="senderAccount"
                label="Sender Mobile / Account Number"
                placeholder="e.g. 017XXXXXXXX"
              />

              <div class="pt-2 flex items-center space-x-2">
                <Button type="submit" variant="primary" size="md" block :loading="isSubmitting">
                  Submit for Compliance Review
                </Button>
                <Button type="button" variant="secondary" size="md" @click="activeInvoice = null">
                  Cancel
                </Button>
              </div>
            </form>
          </div>
        </div>

        <!-- Plan Selection Grid -->
        <div>
          <h3 class="editorial-title text-xl font-medium text-nexo-text-primary mb-4">
            Available Executive Intelligence Plans
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card
              v-for="plan in plans"
              :key="plan.id"
              :class="[
                plan.is_popular ? 'border-nexo-accent-cyan shadow-cyan-glow' : '',
              ]"
            >
              <div class="flex flex-col space-y-4">
                <div>
                  <h4 class="font-serif text-xl font-bold text-nexo-text-primary">{{ plan.name }}</h4>
                  <p class="text-xs text-nexo-text-secondary mt-1">{{ plan.description }}</p>
                </div>

                <div class="flex items-baseline space-x-1">
                  <span class="text-3xl font-mono font-bold text-nexo-text-primary">
                    {{ currency === 'BDT' ? '৳' + plan.price_bdt_monthly : '$' + plan.price_usd_monthly }}
                  </span>
                  <span class="text-xs text-nexo-text-muted">/ month</span>
                </div>

                <ul class="space-y-2 py-3 border-y border-nexo-border/60 text-xs text-nexo-text-secondary">
                  <li v-for="ent in plan.entitlements" :key="ent.id" class="flex items-center space-x-2">
                    <Check class="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                    <span>{{ ent.feature_key.replace('.', ' ') }}: <b>{{ ent.value }}</b></span>
                  </li>
                </ul>

                <Button
                  :variant="plan.is_popular ? 'primary' : 'secondary'"
                  block
                  @click="handleCheckout(plan)"
                >
                  Select {{ plan.name }}
                </Button>
              </div>
            </Card>
          </div>
        </div>

        <!-- Invoices History -->
        <div>
          <h3 class="editorial-title text-xl font-medium text-nexo-text-primary mb-4">
            Invoice & Billing History
          </h3>

          <div class="overflow-x-auto border border-nexo-border rounded-lg">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-surface text-nexo-text-secondary font-mono uppercase text-[10px]">
                <tr>
                  <th class="p-3">Invoice Number</th>
                  <th class="p-3">Date</th>
                  <th class="p-3">Total Amount</th>
                  <th class="p-3">Status</th>
                  <th class="p-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/60 bg-nexo-card">
                <tr v-for="inv in invoices" :key="inv.id">
                  <td class="p-3 font-mono font-bold text-nexo-text-primary">{{ inv.invoice_number }}</td>
                  <td class="p-3 text-nexo-text-secondary">{{ new Date(inv.created_at).toLocaleDateString() }}</td>
                  <td class="p-3 font-mono text-nexo-text-primary">{{ inv.currency === 'BDT' ? '৳' : '$' }}{{ inv.total }}</td>
                  <td class="p-3">
                    <Badge :variant="inv.status === 'paid' ? 'confirmed' : 'developing'" size="sm">
                      {{ inv.status.toUpperCase() }}
                    </Badge>
                  </td>
                  <td class="p-3 text-right">
                    <button
                      v-if="inv.status === 'unpaid'"
                      class="text-nexo-accent-cyan font-mono hover:underline"
                      @click="activeInvoice = inv"
                    >
                      Pay Now →
                    </button>
                    <span v-else class="inline-flex items-center gap-1 text-emerald-700 text-xs font-medium"><Check class="h-3 w-3" /> Paid</span>
                  </td>
                </tr>
                <tr v-if="invoices.length === 0">
                  <td colspan="5" class="p-6 text-center text-nexo-text-muted">
                    No billing invoices generated yet.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>
