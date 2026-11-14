<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import { apiClient } from '@/services/api/client'

const payments = ref<any[]>([])
const isLoading = ref(true)
const msgSuccess = ref('')
const msgError = ref('')

const fetchPayments = async () => {
  isLoading.value = true
  try {
    const res = await apiClient.get('/admin/payments')
    payments.value = res.data.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleVerify = async (paymentId: string, status: 'verified' | 'rejected') => {
  msgSuccess.value = ''
  msgError.value = ''
  try {
    const res = await apiClient.post(`/admin/payments/${paymentId}/verify`, {
      status,
      admin_notes: `Manual compliance check executed by admin.`,
    })
    msgSuccess.value = res.data.message
    await fetchPayments()
  } catch (err: any) {
    msgError.value = err.response?.data?.message || 'Verification update failed.'
  }
}

onMounted(() => {
  fetchPayments()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <div class="border-b border-nexo-border/60 pb-6">
          <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
            <span>Financial Operations</span>
            <span>/</span>
            <span>Payment Compliance & Verification</span>
          </div>
          <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
            Manual Payment Review Queue
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Independently verify bKash, Nagad, and Bank Wire transaction references to activate subscriber entitlements.
          </p>
        </div>

        <Alert v-if="msgSuccess" variant="success" dismissible @dismiss="msgSuccess = ''">
          {{ msgSuccess }}
        </Alert>

        <Alert v-if="msgError" variant="danger" dismissible @dismiss="msgError = ''">
          {{ msgError }}
        </Alert>

        <!-- Payments Table -->
        <Card padding="none">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-nexo-surface text-nexo-text-secondary font-mono uppercase text-[10px] border-b border-nexo-border">
                <tr>
                  <th class="p-3.5">Invoice #</th>
                  <th class="p-3.5">Subscriber</th>
                  <th class="p-3.5">Channel</th>
                  <th class="p-3.5">Transaction ID</th>
                  <th class="p-3.5">Amount</th>
                  <th class="p-3.5">Status</th>
                  <th class="p-3.5 text-right">Verification Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-nexo-border/60 bg-nexo-card">
                <tr v-for="p in payments" :key="p.id">
                  <td class="p-3.5 font-mono font-bold text-nexo-text-primary">{{ p.invoice?.invoice_number }}</td>
                  <td class="p-3.5">
                    <span class="font-bold text-nexo-text-primary block">{{ p.invoice?.subscription?.user?.name }}</span>
                    <span class="text-[10px] font-mono text-nexo-text-muted">{{ p.invoice?.subscription?.user?.email }}</span>
                  </td>
                  <td class="p-3.5 font-mono text-nexo-accent-cyan uppercase">
                    {{ p.payment_method.replace('manual_', '') }}
                  </td>
                  <td class="p-3.5 font-mono text-nexo-accent-gold font-bold">
                    {{ p.provider_reference || p.proof?.transaction_id }}
                  </td>
                  <td class="p-3.5 font-mono text-nexo-text-primary">
                    {{ p.currency === 'BDT' ? '৳' : '$' }}{{ p.amount }}
                  </td>
                  <td class="p-3.5">
                    <Badge :variant="p.status === 'verified' ? 'confirmed' : 'developing'" size="sm">
                      {{ p.status.replace('_', ' ').toUpperCase() }}
                    </Badge>
                  </td>
                  <td class="p-3.5 text-right">
                    <div v-if="p.status === 'pending_review'" class="flex items-center justify-end space-x-2">
                      <Button variant="primary" size="sm" @click="handleVerify(p.id, 'verified')">
                        Approve & Activate
                      </Button>
                      <Button variant="danger" size="sm" @click="handleVerify(p.id, 'rejected')">
                        Reject
                      </Button>
                    </div>
                    <span v-else class="text-nexo-text-muted font-mono text-[11px]">
                      Reviewed by {{ p.verified_by?.name || 'Compliance' }}
                    </span>
                  </td>
                </tr>
                <tr v-if="payments.length === 0">
                  <td colspan="7" class="p-8 text-center text-nexo-text-muted">
                    No pending transactions awaiting verification.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </main>
    </div>
  </div>
</template>
