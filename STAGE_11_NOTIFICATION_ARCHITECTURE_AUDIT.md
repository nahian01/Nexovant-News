# NEXOVANT — STAGE 11 NOTIFICATION ARCHITECTURE AUDIT
## Comprehensive Pre-Implementation Technical Audit of Existing Notification Infrastructure

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 11  
**Status:** Audit Completed  
**Environment:** Google Antigravity  
**Audited Repositories:**
- `nexovant-backend` (Laravel 12, PHP 8.4)
- `nexovant-frontend` (Vue 3, TypeScript, Vite)

---

## 1. Executive Summary

This audit assesses all existing notification, communication, messaging, and delivery tracking infrastructure across the Nexovant platform. The goal is to establish an exhaustive technical baseline, identify architectural gaps, and define a centralized consolidation strategy without duplicating database models, routes, or services.

---

## 2. Inventory of Existing Backend Infrastructure

### 2.1 Database Schema & Migrations
1. **`notifications` Table** ([0001_01_01_000096_create_notifications_table.php](file:///g:/Own/New%20folder/Newstech/nexovant-backend/database/migrations/0001_01_01_000096_create_notifications_table.php)):
   - Columns: `id` (UUID pk), `user_id` (foreignUuid), `category` (`breaking`, `trend_alert`, `publication`, `system`, `billing`, `editorial`), `title` (string), `message` (text), `link` (string nullable), `delivery_channel` (`in_app`, `email`, `whatsapp`, `telegram`, `pdf_digest`), `delivery_status` (`pending`, `sent`, `failed`, `delivered`), `retry_count` (int), `error_message` (text nullable), `is_read` (bool), `read_at` (timestamp nullable), `deleted_at` (soft deletes), `timestamps`.
   - Indexes: `['user_id', 'is_read', 'created_at']`, `['delivery_channel', 'delivery_status']`.
   - **Audit Finding**: Schema is solid for in-app persistence, but lacks `priority` (`low`, `medium`, `high`, `urgent`), `source_module` (`billing`, `research`, `pipeline`, `org`), `organization_id` (for tenant isolation), and `idempotency_key` (to prevent duplicate event dispatches across pipeline retries).

2. **`subscriber_notification_preferences` Table** ([0001_01_01_000101_create_subscriber_workspace_tables.php](file:///g:/Own/New%20folder/Newstech/nexovant-backend/database/migrations/0001_01_01_000101_create_subscriber_workspace_tables.php)):
   - Columns: `id` (UUID), `user_id` (unique foreignUuid), `channels` (JSON), `category_settings` (JSON), `digest_frequency` (string default `daily`), `quiet_hours_start` (string nullable), `quiet_hours_end` (string nullable), `timestamps`.
   - **Audit Finding**: Preference structure exists and stores JSON channel maps, but needs telegram chat ID / linking status, push subscription flags, and timezone offset support.

3. **`notification_delivery_logs` Table** ([0001_01_01_000101_create_subscriber_workspace_tables.php](file:///g:/Own/New%20folder/Newstech/nexovant-backend/database/migrations/0001_01_01_000101_create_subscriber_workspace_tables.php)):
   - Columns: `id` (UUID), `notification_id` (foreignUuid), `channel` (`in_app`, `email`, `whatsapp`, `telegram`, `webhook`), `status` (`delivered`, `sent`, `failed`, `unconfigured_channel`), `attempt_count` (int), `recipient` (string nullable), `error_message` (text nullable), `sent_at` (timestamp nullable), `timestamps`.
   - **Audit Finding**: Fully available and tracks delivery per-channel.

4. **Missing Tables for Stage 11**:
   - `web_push_subscriptions`: Required for browser push endpoint and keys storage (`endpoint`, `public_key`, `auth_token`, `user_agent`).
   - `telegram_link_tokens`: For cryptographic, expiring one-time bot linking tokens.
   - `notification_rules`: Administrator-configurable notification routing rules.

---

### 2.2 Models
- [App\Models\Notification](file:///g:/Own/New%20folder/Newstech/nexovant-backend/app/Models/Notification.php): Uses UUID, soft deletes, unread scope. Missing relationship to `deliveryLogs` in `fillable` or tenant scoping.
- [App\Models\NotificationDeliveryLog](file:///g:/Own/New%20folder/Newstech/nexovant-backend/app/Models/NotificationDeliveryLog.php): Records delivery attempt, channel, status, and error logs.
- [App\Models\SubscriberNotificationPreference](file:///g:/Own/New%20folder/Newstech/nexovant-backend/app/Models/SubscriberNotificationPreference.php): Casts channels and category settings as array.

---

### 2.3 Services & Controllers
- [SubscriberNotificationService.php](file:///g:/Own/New%20folder/Newstech/nexovant-backend/app/Services/Workspace/SubscriberNotificationService.php):
  - Provides `sendNotification()`, `dispatchChannels()`, `deliverEmail()`, `deliverTelegram()`, `deliverWhatsApp()`, `listNotifications()`, `markAsRead()`, `markAllAsRead()`, `archiveNotification()`, `deleteNotification()`, `getUnreadCount()`, `getPreferences()`, `updatePreferences()`.
  - **Audit Finding**: Contains rudimentary stubs for email/telegram/whatsapp that check configuration but don't dispatch real queued Mailable jobs, Web Push VAPID payloads, or Telegram bot webhooks.
- [NotificationController.php](file:///g:/Own/New%20folder/Newstech/nexovant-backend/app/Http/Controllers/Api/V1/Subscriber/NotificationController.php):
  - Handles `/workspace/notifications` CRUD and preferences.
- [NotificationAdminController.php](file:///g:/Own/New%20folder/Newstech/nexovant-backend/app/Http/Controllers/Api/V1/Admin/NotificationAdminController.php):
  - Handles `/admin/notifications` listing, manual dispatch, retry, and deletion with audit logging.

---

### 2.4 Queue, Mail & Real-Time Infrastructure
1. **Queues**: Laravel database queue driver configured with `jobs` table ready. Sync queue in local environment.
2. **Mail**: Mail transport configured in Laravel; currently defaults to `log`/`smtp` in `.env`.
3. **Broadcasting**: Real-time broadcasting needs unified event `NotificationDispatched` broadcast on private channel `private-user.{userId}` with polling fallback on frontend.
4. **Web Push**: VAPID keys needed; standard Web Push protocol via `web-push-libs` or pure PHP curve25519/AES-GCM encryption.

---

## 3. Inventory of Existing Frontend Infrastructure

1. **[NotificationsView.vue](file:///g:/Own/New%20folder/Newstech/nexovant-frontend/src/views/subscriber/NotificationsView.vue)**:
   - Full-page subscriber notifications view at `/workspace/notifications`.
   - Includes category filters, read/unread filters, mark as read, archive, and preferences modal.
   - **Audit Finding**: Does not support Web Push registration, Telegram bot linking UI, or quiet hours timezone handling.
2. **[NotificationDrawer.vue](file:///g:/Own/New%20folder/Newstech/nexovant-frontend/src/components/layout/NotificationDrawer.vue)**:
   - Dropdown component for header.
   - **Audit Finding**: Currently calls legacy `userService.getNotifications()` and mutates client-side read state without calling backend API.
3. **[AppHeader.vue](file:///g:/Own/New%20folder/Newstech/nexovant-frontend/src/components/layout/AppHeader.vue)**:
   - Displays unread notification count badge in header.
   - Links directly to `/workspace/notifications` rather than toggling the dropdown drawer.
4. **[AdminNotificationsView.vue](file:///g:/Own/New%20folder/Newstech/nexovant-frontend/src/views/admin/AdminNotificationsView.vue)**:
   - Admin view at `/admin/notifications` for dispatching announcements and viewing delivery logs.

---

## 4. Architectural Gap Analysis

| Capability | Current State | Required Stage 11 State | Gap Severity |
| :--- | :--- | :--- | :--- |
| **Centralized Event Dispatcher** | Modules call disparate methods or write directly | Single `NotificationEngine::dispatch(NotificationEventDTO $event)` | **Critical** |
| **Notification Prioritization & Tenancy** | No priority or tenant fields on `notifications` table | `priority` (`low`, `medium`, `high`, `urgent`) & `organization_id` on schema | **High** |
| **Web Push (VAPID)** | Completely missing | `web_push_subscriptions` table, VAPID generation, Service Worker handling | **High** |
| **Telegram Bot Integration** | Stubs in service | Secure 1-time expiring token linking flow, test notification, link/unlink | **High** |
| **WhatsApp Integration** | Unimplemented stub | Official Meta WhatsApp Business API evaluation; marked pending onboarding | **Medium** |
| **Email Templates** | Generic text or unconfigured | Responsive, branded HTML email template with primary action & unsubscribe | **High** |
| **Scheduled Digests** | Frequency saved in preferences only | Artisan command `notifications:send-digests` grouping unread updates | **High** |
| **Header Notification Dropdown** | Disconnected from backend | Real-time dropdown updating backend read states with deep link to full center | **Medium** |
| **Full Center Route** | Only `/workspace/notifications` | Dedicated `/notifications` and `/settings/notifications` routes | **Medium** |

---

## 5. Architectural Recommendations

1. **Extend Existing Schema Conservatively**:
   - Add migration `expand_notifications_and_create_communication_tables.php` to add `priority`, `source_module`, `organization_id`, `idempotency_key`, `metadata` to `notifications`.
   - Create `web_push_subscriptions` table.
   - Create `telegram_link_tokens` table.
   - Create `notification_rules` table.
2. **Build Unified `NotificationEngine` Service**:
   - Resides in `app/Services/Notification/NotificationEngine.php`.
   - Replaces manual module calls with deterministic 8-stage pipeline:
     `Event -> Rule Evaluation -> Recipient Resolver -> Preference & Quiet Hours -> Channel Router -> Queue/Dispatcher -> Adapter -> Delivery Tracker`.
3. **Integrate All Upstream Product Modules**:
   - **Billing & Subscriptions**: Trial start/expiring, renewal success/fail, payment pending/verified.
   - **Personalized Workspace**: Watchlist alerts, monitor updates, followed entity signals.
   - **Intelligence & Deep Research**: Breaking news verification, research completion.
   - **Organization Workspaces**: Member invitations, role changes, seat alerts.
   - **Admin Announcements**: Broadcast messaging with preview and audit trail.
4. **Polish In-App Notification Center & Header Dropdown**:
   - Upgrade `AppHeader.vue` to toggle `NotificationDrawer.vue` on click.
   - Provide direct API-backed mark as read, category filters, and route to `/notifications`.
   - Enhance `NotificationsView.vue` with Web Push permission banner and Telegram bot linking card.
