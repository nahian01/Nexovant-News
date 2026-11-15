import { apiClient } from './client'

export interface BillingPlan {
  id: string
  name: string
  slug: string
  description?: string
  tier?: string
  price_usd_monthly: number
  price_usd_annual: number
  price_bdt_monthly: number
  price_bdt_annual: number
  trial_period_days?: number
  is_popular?: boolean
  is_active: boolean
  sort_order?: number
  entitlements?: Array<{
    id: string
    feature_key: string
    value_type: string
    value: string
  }>
}

export interface SubscriberSubscription {
  id: string
  status: 'active' | 'trialing' | 'cancel_at_period_end' | 'canceled' | 'past_due' | 'paused' | 'expired'
  is_active: boolean
  billing_cycle: 'monthly' | 'annual'
  currency: 'USD' | 'BDT'
  starts_at?: string
  ends_at?: string
  next_renewal_at?: string
  canceled_at?: string
  auto_renew: boolean
  plan?: BillingPlan
}

export interface EntitlementsSnapshot {
  plan_name: string
  plan_slug: string
  subscription_status: string
  is_active: boolean
  limits: {
    max_watchlists: number | string
    max_entities_per_watchlist: number | string
    max_active_monitors: number | string
    daily_research_queries: number | string
    historical_timeline_days: number | string
  }
  features: {
    ai_deep_research: boolean
    pdf_downloads: boolean
    publication_access: boolean
  }
}

export interface BillingUsage {
  watchlists: { used: number; limit: number | string }
  monitors: { used: number; limit: number | string }
  daily_queries: { used: number; limit: number | string }
}

export interface PaymentMethodOption {
  id: string
  gateway_key: string
  name: string
  type: string
  currency: string
  icon: string
  description: string
  supports_recurring: boolean
  is_hosted: boolean
  badge?: string
}

export interface BillingInvoice {
  id: string
  invoice_number: string
  subtotal: number
  discount: number
  tax: number
  total: number
  currency: string
  status: 'paid' | 'unpaid' | 'void'
  due_date?: string
  paid_at?: string
  pdf_path?: string
  billing_name?: string
  billing_email?: string
  items?: Array<{
    id: string
    description: string
    quantity: number
    unit_price: number
    amount: number
  }>
  payments?: Array<{
    id: string
    status: string
    amount: number
    payment_method: string
    provider_reference?: string
    created_at: string
  }>
}

export const billingService = {
  // Subscriber Billing Portal API
  async getOverview() {
    const res = await apiClient.get('/billing/overview')
    return res.data
  },

  async getPlans() {
    const res = await apiClient.get('/billing/plans')
    return res.data
  },

  async getPaymentMethods(params?: { country?: string; currency?: string; interval?: string }) {
    const res = await apiClient.get('/billing/payment-methods', { params })
    return res.data
  },

  async validateCoupon(payload: { code: string; plan_id: string; country?: string; subtotal: number }) {
    const res = await apiClient.post('/billing/validate-coupon', payload)
    return res.data
  },

  async checkout(payload: {
    plan_id: string
    billing_cycle: 'monthly' | 'annual'
    currency: 'USD' | 'BDT'
    country?: string
    payment_method: string
    coupon_code?: string
    idempotency_key?: string
    billing_name?: string
    billing_email?: string
    billing_address?: string
  }) {
    const res = await apiClient.post('/billing/checkout', payload)
    return res.data
  },

  async previewProration(targetPlanId: string, newInterval?: 'monthly' | 'annual') {
    const res = await apiClient.post('/billing/preview-proration', {
      target_plan_id: targetPlanId,
      new_interval: newInterval,
    })
    return res.data
  },

  async changePlan(targetPlanId: string, newInterval?: 'monthly' | 'annual') {
    const res = await apiClient.post('/billing/change-plan', {
      target_plan_id: targetPlanId,
      new_interval: newInterval,
    })
    return res.data
  },

  async cancelSubscription(atPeriodEnd: boolean = true, reason?: string) {
    const res = await apiClient.post('/billing/cancel', {
      at_period_end: atPeriodEnd,
      reason,
    })
    return res.data
  },

  async reactivateSubscription() {
    const res = await apiClient.post('/billing/reactivate')
    return res.data
  },

  async getInvoices(page: number = 1) {
    const res = await apiClient.get('/billing/invoices', { params: { page } })
    return res.data
  },

  getInvoiceDownloadUrl(invoiceId: string): string {
    return `/api/v1/billing/invoices/${invoiceId}/download`
  },

  async submitManualPayment(payload: {
    invoice_id: string
    payment_method: string
    transaction_id: string
    sender_account?: string
    notes?: string
  }) {
    const res = await apiClient.post('/billing/submit-manual-payment', payload)
    return res.data
  },
}

export const adminBillingService = {
  // Admin Billing & Gateway Control Center API
  async getOverview() {
    const res = await apiClient.get('/admin/billing/overview')
    return res.data
  },

  async getPlans() {
    const res = await apiClient.get('/admin/billing/plans')
    return res.data
  },

  async storePlan(data: any) {
    const res = await apiClient.post('/admin/billing/plans', data)
    return res.data
  },

  async updatePlan(id: string, data: any) {
    const res = await apiClient.put(`/admin/billing/plans/${id}`, data)
    return res.data
  },

  async getSubscriptions(params?: { page?: number; status?: string; search?: string; plan_id?: string }) {
    const res = await apiClient.get('/admin/billing/subscriptions', { params })
    return res.data
  },

  async getSubscriptionDetails(id: string) {
    const res = await apiClient.get(`/admin/billing/subscriptions/${id}`)
    return res.data
  },

  async updateSubscriptionStatus(id: string, status: string, reason: string) {
    const res = await apiClient.put(`/admin/billing/subscriptions/${id}/status`, { status, reason })
    return res.data
  },

  async grantEntitlementOverride(id: string, payload: {
    feature_key: string
    value: string
    reason: string
    expires_at?: string
  }) {
    const res = await apiClient.post(`/admin/billing/subscriptions/${id}/override`, payload)
    return res.data
  },

  async revokeEntitlementOverride(overrideId: string) {
    const res = await apiClient.delete(`/admin/billing/overrides/${overrideId}`)
    return res.data
  },

  async getInvoices(params?: { page?: number; status?: string; search?: string }) {
    const res = await apiClient.get('/admin/billing/invoices', { params })
    return res.data
  },

  async getInvoiceDetails(id: string) {
    const res = await apiClient.get(`/admin/billing/invoices/${id}`)
    return res.data
  },

  async getGateways() {
    const res = await apiClient.get('/admin/billing/gateways')
    return res.data
  },

  async updateGateway(key: string, data: any) {
    const res = await apiClient.put(`/admin/billing/gateways/${key}`, data)
    return res.data
  },

  async testGateway(key: string) {
    const res = await apiClient.post(`/admin/billing/gateways/${key}/test`)
    return res.data
  },

  async getRefunds(page: number = 1) {
    const res = await apiClient.get('/admin/billing/refunds', { params: { page } })
    return res.data
  },

  async processRefund(payload: { payment_id: string; amount: number; reason: string; notes?: string }) {
    const res = await apiClient.post('/admin/billing/refunds', payload)
    return res.data
  },

  async getCoupons(page: number = 1) {
    const res = await apiClient.get('/admin/billing/coupons', { params: { page } })
    return res.data
  },

  async storeCoupon(data: any) {
    const res = await apiClient.post('/admin/billing/coupons', data)
    return res.data
  },

  async toggleCoupon(id: string) {
    const res = await apiClient.put(`/admin/billing/coupons/${id}/toggle`)
    return res.data
  },

  async reconcile(gatewayKey?: string) {
    const res = await apiClient.post('/admin/billing/reconcile', { gateway_key: gatewayKey })
    return res.data
  },

  async getReconciliationHistory() {
    const res = await apiClient.get('/admin/billing/reconcile/history')
    return res.data
  },
}
