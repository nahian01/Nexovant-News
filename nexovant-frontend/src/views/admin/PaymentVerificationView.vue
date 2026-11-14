<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import Alert from '@/components/ui/Alert.vue'
import Modal from '@/components/ui/Modal.vue'
import { adminService } from '@/services/api/admin.service'
import { CircleDollarSign, CheckCircle, XCircle, CreditCard, Smartphone, Building, RefreshCw } from 'lucide-vue-next'

const activeTab = ref<'queue' | 'methods'>('queue')
const payments = ref<any[]>([])
const paymentMethods = ref<any[]>([])
const isLoading = ref(true)
const statusMessage = ref('')
const errorMessage = ref('')

// Verification review modal
const isReviewModalOpen = ref(false)
const selectedPayment = ref<any>(null)
const reviewAction = ref<'verified' | 'rejected'>('verified')
const adminNotes = ref('')

const fetchAll = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getPayments()
    payments.value = res.data || []
    paymentMethods.value = await adminService.getPaymentMethods()
  } catch (err: any) {
    errorMessage.value = 'Failed to load payment compliance records.'
  } finally {
    isLoading.value = false
  }
}

const openReviewModal = (payment: any, action: 'verified' | 'rejected') => {
  selectedPayment.value = payment
  reviewAction.value = action
  adminNotes.value = action === 'verified'
    ? `Manual verification completed. Bank/MFS transaction confirmed on merchant terminal.`
    : `Transaction rejected: Reference number not found or mismatched amount.`
  isReviewModalOpen.value = true
}

const submitVerification = async () => {
  if (!selectedPayment.value) return
  try {
    const res = await adminService.verifyPayment(
      selectedPayment.value.id,
      reviewAction.value,
      adminNotes.value
    )
    statusMessage.value = res.message || 'Payment review completed.'
    isReviewModalOpen.value = false
    await fetchAll()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Verification update failed.'
  }
}

const toggleMethod = async (method: any) => {
  try {
    const res = await adminService.updatePaymentMethod(method.id, {
      is_active: !method.is_active,
    })
    method.is_active = !method.is_active
    statusMessage.value = res.message || 'Payment method status updated.'
  } catch (err: any) {
    errorMessage.value = 'Failed to update payment method.'
  }
}

onMounted(() => {
  fetchAll()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <!-- Title & Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
            Manual Payment Review & Entitlement Gateway
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Independently verify bKash, Nagad, Rocket, and Bank Wire transaction references to activate paid subscriber access.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchAll">
          Refresh Transactions
        </Button>
      </div>

      <!-- Alerts -->
      <Alert v-if="statusMessage" variant="success" dismissible @dismiss="statusMessage = ''">
        {{ statusMessage }}
      </Alert>
      <Alert v-if="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''">
        {{ errorMessage }}
      </Alert>

      <!-- Tabs -->
      <div class="flex border-b border-nexo-border space-x-6 text-xs font-mono">
        <button
          @click="activeTab = 'queue'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'queue' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Transaction Review Queue ({{ payments.length }})
        </button>
        <button
          @click="activeTab = 'methods'"
          :class="['pb-2.5 font-semibold transition border-b-2', activeTab === 'methods' ? 'border-nexo-accent-gold text-nexo-accent-gold' : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary']"
        >
          Payment Gateways & Accounts ({{ paymentMethods.length }})
        </button>
      </div>

      <!-- TAB 1: TRANSACTION VERIFICATION QUEUE -->
      <div v-if="activeTab === 'queue'">
        <Table :headers="['Invoice # & Subscriber', 'Payment Method', 'Transaction ID / Proof', 'Amount', 'Status', 'Actions']" :is-empty="payments.length === 0">
          <tr v-for="p in payments" :key="p.id" class="hover:bg-nexo-surface/40 transition">
            <td class="py-3.5 px-4">
              <div class="font-bold text-nexo-text-primary">{{ p.invoice?.subscription?.user?.name || 'Subscriber' }}</div>
              <div class="text-[10px] font-mono text-nexo-text-muted">
                {{ p.invoice?.subscription?.user?.email }} • Inv #{{ p.invoice?.invoice_number || p.id.substring(0, 8) }}
              </div>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs uppercase text-nexo-accent-cyan font-bold">
              {{ p.payment_method?.replace('manual_', '') }}
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <div class="text-nexo-accent-gold font-bold">
                {{ p.provider_reference || p.proof?.transaction_id || 'REF-N/A' }}
              </div>
              <div v-if="p.proof?.sender_number" class="text-[9px] text-nexo-text-muted">
                Sender: {{ p.proof.sender_number }}
              </div>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs font-bold text-nexo-text-primary">
              {{ p.currency === 'BDT' ? '৳' : '$' }}{{ p.amount }}
            </td>
            <td class="py-3.5 px-4">
              <Badge :variant="p.status === 'verified' ? 'confirmed' : 'developing'" size="sm">
                {{ p.status?.replace('_', ' ').toUpperCase() }}
              </Badge>
            </td>
            <td class="py-3.5 px-4 font-mono text-xs">
              <div v-if="p.status === 'pending_review'" class="flex items-center space-x-2">
                <Button
                  variant="primary"
                  size="sm"
                  @click="openReviewModal(p, 'verified')"
                  class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold"
                >
                  Approve & Activate
                </Button>
                <Button
                  variant="danger"
                  size="sm"
                  @click="openReviewModal(p, 'rejected')"
                >
                  Reject
                </Button>
              </div>
              <span v-else class="text-nexo-text-muted text-[11px]">
                Reviewed by {{ p.verified_by?.name || 'Compliance' }}
              </span>
            </td>
          </tr>
        </Table>
      </div>

      <!-- TAB 2: PAYMENT METHODS CONFIGURATION -->
      <div v-if="activeTab === 'methods'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <Card
          v-for="m in paymentMethods"
          :key="m.id"
          class="p-5 border border-nexo-border bg-white space-y-3"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Smartphone v-if="m.type === 'mobile_banking'" class="h-4 w-4 text-pink-600" />
              <CreditCard v-else-if="m.type === 'card'" class="h-4 w-4 text-indigo-600" />
              <Building v-else class="h-4 w-4 text-emerald-600" />
              <h3 class="font-bold text-sm text-nexo-text-primary">{{ m.name }}</h3>
            </div>
            <span
              :class="[
                'px-2 py-0.5 rounded text-[9px] font-mono font-bold uppercase',
                m.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'
              ]"
            >
              {{ m.is_active ? 'Active' : 'Disabled' }}
            </span>
          </div>

          <div class="text-xs font-mono space-y-1 text-nexo-text-secondary bg-nexo-surface/40 p-2.5 rounded-lg border border-nexo-border/60">
            <div class="flex justify-between">
              <span>Account Number:</span>
              <span class="text-nexo-text-primary font-bold">{{ m.account_number }}</span>
            </div>
            <div class="flex justify-between">
              <span>Account Type:</span>
              <span class="capitalize">{{ m.account_type }}</span>
            </div>
            <div class="flex justify-between">
              <span>Supported Currency:</span>
              <span class="text-nexo-accent-gold font-bold">{{ m.currency }}</span>
            </div>
          </div>

          <p class="text-[11px] text-nexo-text-secondary">{{ m.instructions }}</p>

          <div class="pt-2 border-t border-nexo-border flex justify-end">
            <Button
              variant="secondary"
              size="sm"
              @click="toggleMethod(m)"
            >
              {{ m.is_active ? 'Disable Channel' : 'Enable Channel' }}
            </Button>
          </div>
        </Card>
      </div>

      <!-- Verification Review Modal -->
      <Modal :is-open="isReviewModalOpen" :title="reviewAction === 'verified' ? 'Approve Payment & Activate Entitlement' : 'Reject Payment Transaction'" max-width="md" @close="isReviewModalOpen = false">
        <div class="space-y-4 text-xs text-left">
          <div class="p-3 bg-nexo-surface rounded border border-nexo-border font-mono space-y-1">
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">Subscriber:</span>
              <span class="text-nexo-text-primary font-bold">{{ selectedPayment?.invoice?.subscription?.user?.name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">Channel / Reference:</span>
              <span class="text-nexo-accent-gold font-bold">{{ selectedPayment?.provider_reference || selectedPayment?.proof?.transaction_id }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-nexo-text-muted">Amount:</span>
              <span class="text-emerald-700 font-bold">{{ selectedPayment?.currency }} {{ selectedPayment?.amount }}</span>
            </div>
          </div>

          <div>
            <label class="font-mono text-[10px] uppercase text-nexo-text-muted block mb-1">Compliance Audit Note</label>
            <textarea v-model="adminNotes" rows="3" class="w-full p-2 border border-nexo-border rounded text-xs bg-white text-nexo-text-primary"></textarea>
          </div>

          <div class="pt-2 flex justify-end space-x-2">
            <Button variant="ghost" size="sm" type="button" @click="isReviewModalOpen = false">Cancel</Button>
            <Button
              :variant="reviewAction === 'verified' ? 'primary' : 'danger'"
              size="sm"
              @click="submitVerification"
              :class="reviewAction === 'verified' ? 'bg-emerald-600 hover:bg-emerald-700' : ''"
            >
              Confirm {{ reviewAction === 'verified' ? 'Approval' : 'Rejection' }}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>
