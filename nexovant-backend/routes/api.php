<?php

use App\Http\Controllers\Api\V1\Admin\AIRouterAdminController;
use App\Http\Controllers\Api\V1\Admin\EditorialAdminController;
use App\Http\Controllers\Api\V1\Admin\PaymentAdminController;
use App\Http\Controllers\Api\V1\Admin\DashboardAdminController;
use App\Http\Controllers\Api\V1\Admin\ClusterAdminController;
use App\Http\Controllers\Api\V1\Admin\SourceAdminController;
use App\Http\Controllers\Api\V1\Admin\AutomationAdminController;
use App\Http\Controllers\Api\V1\Admin\RagAdminController;
use App\Http\Controllers\Api\V1\Admin\UserAdminController;
use App\Http\Controllers\Api\V1\Admin\AdAdminController;
use App\Http\Controllers\Api\V1\Admin\SystemAdminController;
use App\Http\Controllers\Api\V1\Admin\TaxonomyAdminController;
use App\Http\Controllers\Api\V1\Admin\CompanyAdminController;
use App\Http\Controllers\Api\V1\Admin\NotificationAdminController;
use App\Http\Controllers\Api\V1\Admin\IntelligenceAdminController;
use App\Http\Controllers\Api\V1\Admin\ResearchAdminController;
use App\Http\Controllers\Api\V1\Admin\PublicationAdminController;
use App\Http\Controllers\Api\V1\Admin\SubscriberAdminController;
use App\Http\Controllers\Api\V1\Admin\BillingAdminController;
use App\Http\Controllers\Api\V1\Admin\OrganizationAdminController;
use App\Http\Controllers\Api\V1\Subscriber\OrganizationWorkspaceController;
use App\Http\Controllers\Api\V1\Subscriber\WorkspaceController;
use App\Http\Controllers\Api\V1\Subscriber\WatchlistController;
use App\Http\Controllers\Api\V1\Subscriber\MonitorController;
use App\Http\Controllers\Api\V1\Subscriber\PersonalLibraryController;
use App\Http\Controllers\Api\V1\Subscriber\NotificationController;
use App\Http\Controllers\Api\V1\Subscriber\SubscriberResearchController;
use App\Http\Controllers\Api\V1\Subscriber\BillingPortalController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\PublicIntelligenceController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\UserInteractionController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\ExecutiveController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PublicationController;
use App\Http\Controllers\Api\V1\RagSearchController;
use App\Http\Controllers\Api\V1\SocialTrendController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\EntityDiscoveryController;
use App\Http\Controllers\Api\V1\SourceComparisonController;
use App\Http\Controllers\Api\V1\Subscriber\SavedSearchController;
use App\Http\Controllers\Api\V1\Subscriber\ResearchBriefController;
use App\Http\Controllers\Api\V1\Admin\SearchAnalyticsAdminController;
use App\Http\Controllers\Api\V1\Automation\SignalController;
use App\Http\Controllers\Api\V1\Automation\EarlyWarningController;
use App\Http\Controllers\Api\V1\Automation\TrendForecastController;
use App\Http\Controllers\Api\V1\Automation\AutomatedReportController;
use App\Http\Controllers\Api\V1\Admin\AnalystReviewAdminController;
use App\Http\Controllers\Api\V1\Admin\IntelligenceAutomationAdminController;
use App\Http\Controllers\Api\V1\Executive\ExecutiveBriefingController;
use App\Http\Controllers\Api\V1\Executive\StrategicImpactController;
use App\Http\Controllers\Api\V1\Executive\OpportunityRiskController;
use App\Http\Controllers\Api\V1\Executive\ScenarioAnalysisController;
use App\Http\Controllers\Api\V1\Executive\DecisionSupportController;
use App\Http\Controllers\Api\V1\Executive\ExecutiveAssistantController;
use App\Http\Controllers\Api\V1\Executive\ExecutiveDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nexovant Executive Intelligence API v1
|--------------------------------------------------------------------------
*/

// Health Check
Route::get('/health', HealthController::class)->name('api.health');

// Public Intelligence Feeds & Newsletter
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('api.newsletter.subscribe');

Route::prefix('articles')->group(function () {
    Route::get('/', [ArticleController::class, 'index'])->name('api.articles.index');
    Route::get('/breaking', [ArticleController::class, 'breaking'])->name('api.articles.breaking');
    Route::get('/{slug}', [ArticleController::class, 'show'])->name('api.articles.show');
});

Route::prefix('companies')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('api.companies.index');
    Route::get('/{slug}', [CompanyController::class, 'show'])->name('api.companies.show');
});

Route::prefix('executives')->group(function () {
    Route::get('/', [ExecutiveController::class, 'index'])->name('api.executives.index');
    Route::get('/{slug}', [ExecutiveController::class, 'show'])->name('api.executives.show');
});

Route::prefix('trends')->group(function () {
    Route::get('/', [SocialTrendController::class, 'index'])->name('api.trends.index');
    Route::get('/{id}', [SocialTrendController::class, 'show'])->name('api.trends.show');
});

Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans'])->name('api.subscriptions.plans');
Route::get('/payments/methods', [PaymentController::class, 'methods'])->name('api.payments.methods');
Route::get('/billing/plans', [BillingPortalController::class, 'plans'])->name('api.billing.plans');
Route::get('/billing/payment-methods', [BillingPortalController::class, 'paymentMethods'])->name('api.billing.payment-methods');
Route::get('/billing/checkout-options', [BillingPortalController::class, 'checkoutOptions'])->name('api.billing.checkout-options.public');
Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle'])->name('api.webhooks.handle');
Route::get('/publications', [PublicationController::class, 'index'])->name('api.publications.index');
Route::get('/publications/{id}', [PublicationController::class, 'show'])->name('api.publications.show');
Route::get('/publications/{id}/stream', [PublicationController::class, 'stream'])->name('api.publications.stream');
Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/search', [SearchController::class, 'search'])->name('api.search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('api.search.suggestions');
Route::post('/search/rag', [RagSearchController::class, 'query'])->name('api.search.rag');
Route::post('/search/feedback', [SearchController::class, 'feedback'])->name('api.search.feedback');
Route::post('/search/click', [SearchController::class, 'recordClick'])->name('api.search.click');

// Stage 12 Discovery & Source Transparency Endpoints
Route::prefix('discovery')->group(function () {
    Route::get('/entities/{slugOrId}', [EntityDiscoveryController::class, 'profile'])->name('api.discovery.entities.profile');
    Route::get('/entities/{entityId}/graph', [EntityDiscoveryController::class, 'graph'])->name('api.discovery.entities.graph');
    Route::get('/entities/{entityId}/timeline', [EntityDiscoveryController::class, 'timeline'])->name('api.discovery.entities.timeline');
    Route::get('/corroboration/{id}', [SourceComparisonController::class, 'corroboration'])->name('api.discovery.corroboration');
    Route::post('/source-comparison', [SourceComparisonController::class, 'compare'])->name('api.discovery.source-comparison');
});

// Public Intelligence Profiles & Reports
Route::prefix('intelligence')->group(function () {
    Route::get('/companies/{slug}', [PublicIntelligenceController::class, 'showCompany'])->name('api.public.intelligence.company');
    Route::get('/executives/{slug}', [PublicIntelligenceController::class, 'showExecutive'])->name('api.public.intelligence.executive');
    Route::get('/technologies/{slug}', [PublicIntelligenceController::class, 'showTechnology'])->name('api.public.intelligence.technology');
    Route::get('/timeline', [PublicIntelligenceController::class, 'listTimeline'])->name('api.public.intelligence.timeline');
    Route::get('/reports', [PublicIntelligenceController::class, 'listReports'])->name('api.public.intelligence.reports');
    Route::get('/reports/{slug}', [PublicIntelligenceController::class, 'showReport'])->name('api.public.intelligence.report');
});

// Stage 13: Intelligence Automation, Predictive Monitoring & Early Warnings
Route::prefix('automation')->group(function () {
    Route::get('/signals', [SignalController::class, 'index'])->name('api.automation.signals.index');
    Route::get('/signals/{slugOrId}', [SignalController::class, 'show'])->name('api.automation.signals.show');
    Route::get('/early-warnings', [EarlyWarningController::class, 'index'])->name('api.automation.early-warnings.index');
    Route::get('/early-warnings/{slugOrId}', [EarlyWarningController::class, 'show'])->name('api.automation.early-warnings.show');
    Route::match(['patch', 'post'], '/early-warnings/{id}/status', [EarlyWarningController::class, 'updateStatus'])->middleware('auth:sanctum')->name('api.automation.early-warnings.status');
    Route::get('/forecasts', [TrendForecastController::class, 'index'])->name('api.automation.forecasts.index');
    Route::get('/forecasts/{id}', [TrendForecastController::class, 'show'])->name('api.automation.forecasts.show');
    Route::post('/forecasts', [TrendForecastController::class, 'generate'])->middleware('auth:sanctum')->name('api.automation.forecasts.create');
    Route::post('/forecasts/generate', [TrendForecastController::class, 'generate'])->middleware('auth:sanctum')->name('api.automation.forecasts.generate');
    Route::get('/reports', [AutomatedReportController::class, 'index'])->name('api.automation.reports.index');
    Route::get('/reports/{slugOrId}', [AutomatedReportController::class, 'show'])->name('api.automation.reports.show');
    Route::post('/reports', [AutomatedReportController::class, 'generate'])->middleware('auth:sanctum')->name('api.automation.reports.create');
    Route::post('/reports/generate', [AutomatedReportController::class, 'generate'])->middleware('auth:sanctum')->name('api.automation.reports.generate');
    Route::get('/reports/{id}/export', [AutomatedReportController::class, 'export'])->name('api.automation.reports.export');
});

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('api.auth.register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('api.auth.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:6,1')->name('api.auth.admin-login');
    Route::post('/admin-login', [AuthController::class, 'adminLogin'])->middleware('throttle:6,1');

    // Protected Auth
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('api.auth.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

// Authenticated Subscriber Endpoints
Route::middleware('auth:sanctum')->group(function () {
    // Watchlist Management
    Route::post('/companies/{id}/follow', [CompanyController::class, 'toggleFollow'])->name('api.companies.follow');
    Route::post('/executives/{id}/follow', [ExecutiveController::class, 'toggleFollow'])->name('api.executives.follow');

    // Subscription & Billing
    Route::prefix('subscriptions')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'current'])->name('api.subscriptions.current');
        Route::post('/checkout', [SubscriptionController::class, 'checkout'])->name('api.subscriptions.checkout');
    });

    Route::prefix('payments')->group(function () {
        Route::post('/submit', [PaymentController::class, 'submitProof'])->name('api.payments.submit');
        Route::get('/invoices', [PaymentController::class, 'invoices'])->name('api.payments.invoices');
    });

    // Billing & Subscription Management Platform (Phase 7D & Stage 9)
    Route::prefix('billing')->group(function () {
        Route::get('/overview', [BillingPortalController::class, 'overview'])->name('api.billing.overview');
        Route::post('/start-trial', [BillingPortalController::class, 'startTrial'])->name('api.billing.start-trial');
        Route::post('/validate-coupon', [BillingPortalController::class, 'validateCoupon'])->name('api.billing.validate-coupon');
        Route::post('/checkout', [BillingPortalController::class, 'checkout'])->name('api.billing.checkout');
        Route::get('/checkout-options', [BillingPortalController::class, 'checkoutOptions'])->name('api.billing.checkout-options');
        Route::get('/checkout/status/{reference}', [BillingPortalController::class, 'paymentStatus'])->name('api.billing.checkout.status');
        Route::post('/checkout/retry', [BillingPortalController::class, 'retryPayment'])->name('api.billing.checkout.retry');
        Route::post('/preview-proration', [BillingPortalController::class, 'previewProration'])->name('api.billing.preview-proration');
        Route::post('/change-plan', [BillingPortalController::class, 'changePlan'])->name('api.billing.change-plan');
        Route::post('/cancel', [BillingPortalController::class, 'cancel'])->name('api.billing.cancel');
        Route::post('/reactivate', [BillingPortalController::class, 'reactivate'])->name('api.billing.reactivate');
        Route::get('/invoices', [BillingPortalController::class, 'invoices'])->name('api.billing.invoices');
        Route::get('/invoices/{id}/download', [BillingPortalController::class, 'downloadInvoice'])->name('api.billing.invoices.download');
        Route::post('/submit-manual-payment', [BillingPortalController::class, 'submitManualProof'])->name('api.billing.submit-manual-payment');
    });

    // Organization Workspace & Multi-Tenant Management (Phase 7E)
    Route::prefix('organization')->group(function () {
        Route::get('/current', [OrganizationWorkspaceController::class, 'current'])->name('api.organization.current');
        Route::post('/', [OrganizationWorkspaceController::class, 'store'])->name('api.organization.store');
        Route::put('/settings', [OrganizationWorkspaceController::class, 'update'])->name('api.organization.update');
        Route::post('/invite', [OrganizationWorkspaceController::class, 'invite'])->name('api.organization.invite');
        Route::get('/invitations', [OrganizationWorkspaceController::class, 'invitations'])->name('api.organization.invitations');
        Route::delete('/invitations/{id}', [OrganizationWorkspaceController::class, 'revokeInvitation'])->name('api.organization.invitations.revoke');
        Route::post('/invitations/{token}/accept', [OrganizationWorkspaceController::class, 'acceptInvitation'])->name('api.organization.invitations.accept');
        Route::put('/members/{id}/role', [OrganizationWorkspaceController::class, 'updateMemberRole'])->name('api.organization.members.role');
        Route::delete('/members/{id}', [OrganizationWorkspaceController::class, 'removeMember'])->name('api.organization.members.remove');
        Route::post('/domain/initiate', [OrganizationWorkspaceController::class, 'initiateDomain'])->name('api.organization.domain.initiate');
        Route::post('/domain/confirm', [OrganizationWorkspaceController::class, 'confirmDomain'])->name('api.organization.domain.confirm');
        Route::get('/assets', [OrganizationWorkspaceController::class, 'assets'])->name('api.organization.assets');
        Route::post('/assets/watchlists/{id}/share', [OrganizationWorkspaceController::class, 'shareWatchlist'])->name('api.organization.assets.watchlists.share');
        Route::post('/assets/monitors/{id}/share', [OrganizationWorkspaceController::class, 'shareMonitor'])->name('api.organization.assets.monitors.share');
        Route::post('/documents', [OrganizationWorkspaceController::class, 'uploadDocument'])->name('api.organization.documents.upload');
        Route::get('/audit-logs', [OrganizationWorkspaceController::class, 'auditLogs'])->name('api.organization.audit_logs');
        Route::delete('/leave', [OrganizationWorkspaceController::class, 'leave'])->name('api.organization.leave');
    });

    // RAG Search Interface
    Route::post('/rag/query', [RagSearchController::class, 'query'])->name('api.rag.query');

    // Publication Downloads
    Route::get('/publications/{id}/download', [PublicationController::class, 'download'])->name('api.publications.download');

    // User Feed & Interaction Signals
    Route::post('/user/interact', [UserInteractionController::class, 'interact'])->name('api.user.interact');
    Route::get('/user/saved-articles', [UserInteractionController::class, 'savedArticles'])->name('api.user.saved-articles');
    Route::match(['get', 'post', 'put'], '/user/preferences', [UserInteractionController::class, 'preferences'])->name('api.user.preferences');
    Route::get('/user/notifications', [UserInteractionController::class, 'notifications'])->name('api.user.notifications');

    // Centralized Subscriber Notifications & Communication Engine (Stage 11)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('api.notifications.read');
        Route::patch('/{id}/read', [NotificationController::class, 'markRead'])->name('api.notifications.patch-read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('api.notifications.mark-all-read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');
        Route::match(['get', 'post', 'put'], '/preferences', [NotificationController::class, 'preferences'])->name('api.notifications.preferences');
        Route::get('/push/vapid-key', [NotificationController::class, 'getVapidKey'])->name('api.notifications.vapid-key');
        Route::post('/push/subscribe', [NotificationController::class, 'subscribePush'])->name('api.notifications.push.subscribe');
        Route::post('/push/unsubscribe', [NotificationController::class, 'unsubscribePush'])->name('api.notifications.push.unsubscribe');
        Route::post('/telegram/link-token', [NotificationController::class, 'generateTelegramToken'])->name('api.notifications.telegram.token');
        Route::post('/telegram/verify', [NotificationController::class, 'verifyTelegram'])->name('api.notifications.telegram.verify');
        Route::post('/telegram/connect', [NotificationController::class, 'connectTelegram'])->name('api.notifications.telegram.connect');
        Route::post('/telegram/disconnect', [NotificationController::class, 'disconnectTelegram'])->name('api.notifications.telegram.disconnect');
        Route::post('/telegram/test', [NotificationController::class, 'testTelegram'])->name('api.notifications.telegram.test');
        Route::get('/whatsapp/status', [NotificationController::class, 'whatsappStatus'])->name('api.notifications.whatsapp.status');
        Route::post('/whatsapp/connect', [NotificationController::class, 'connectWhatsapp'])->name('api.notifications.whatsapp.connect');
        Route::post('/whatsapp/disconnect', [NotificationController::class, 'disconnectWhatsapp'])->name('api.notifications.whatsapp.disconnect');
        Route::post('/whatsapp/test', [NotificationController::class, 'testWhatsapp'])->name('api.notifications.whatsapp.test');
    });

    // Subscriber Workspace & Personalized Intelligence (Phase 7C)
    Route::prefix('workspace')->group(function () {
        Route::get('/overview', [WorkspaceController::class, 'overview'])->name('api.workspace.overview');
        Route::get('/feed', [WorkspaceController::class, 'feed'])->name('api.workspace.feed');
        Route::get('/following', [WorkspaceController::class, 'getFollowing'])->name('api.workspace.following.index');
        Route::post('/following/toggle', [WorkspaceController::class, 'toggleFollow'])->name('api.workspace.following.toggle');
        Route::get('/following/suggestions', [WorkspaceController::class, 'getFollowingSuggestions'])->name('api.workspace.following.suggestions');
        Route::get('/following/feed', [WorkspaceController::class, 'getFollowingFeed'])->name('api.workspace.following.feed');
        Route::post('/interact', [WorkspaceController::class, 'interact'])->name('api.workspace.interact');
        Route::get('/reading-history', [WorkspaceController::class, 'readingHistory'])->name('api.workspace.history');
        Route::delete('/reading-history', [WorkspaceController::class, 'clearHistory'])->name('api.workspace.history.clear');
        Route::post('/reset-signals', [WorkspaceController::class, 'resetSignals'])->name('api.workspace.signals.reset');
        Route::match(['get', 'post', 'put'], '/preferences', [WorkspaceController::class, 'preferences'])->name('api.workspace.preferences');

        // Watchlists
        Route::get('/watchlists', [WatchlistController::class, 'index'])->name('api.workspace.watchlists.index');
        Route::post('/watchlists', [WatchlistController::class, 'store'])->name('api.workspace.watchlists.store');
        Route::get('/watchlists/{id}', [WatchlistController::class, 'show'])->name('api.workspace.watchlists.show');
        Route::put('/watchlists/{id}', [WatchlistController::class, 'update'])->name('api.workspace.watchlists.update');
        Route::delete('/watchlists/{id}', [WatchlistController::class, 'destroy'])->name('api.workspace.watchlists.destroy');
        Route::post('/watchlists/{id}/items', [WatchlistController::class, 'addItem'])->name('api.workspace.watchlists.items.store');
        Route::delete('/watchlists/{id}/items/{itemId}', [WatchlistController::class, 'removeItem'])->name('api.workspace.watchlists.items.destroy');
        Route::get('/watchlists/{id}/timeline', [WatchlistController::class, 'timeline'])->name('api.workspace.watchlists.timeline');
        Route::get('/watchlists/{id}/connected', [WatchlistController::class, 'connectedEntities'])->name('api.workspace.watchlists.connected');

        // Custom Monitors
        Route::get('/monitors', [MonitorController::class, 'index'])->name('api.workspace.monitors.index');
        Route::post('/monitors', [MonitorController::class, 'store'])->name('api.workspace.monitors.store');
        Route::get('/monitors/{id}', [MonitorController::class, 'show'])->name('api.workspace.monitors.show');
        Route::put('/monitors/{id}', [MonitorController::class, 'update'])->name('api.workspace.monitors.update');
        Route::post('/monitors/{id}/toggle', [MonitorController::class, 'toggle'])->name('api.workspace.monitors.toggle');
        Route::delete('/monitors/{id}', [MonitorController::class, 'destroy'])->name('api.workspace.monitors.destroy');
        Route::post('/monitors/preview', [MonitorController::class, 'preview'])->name('api.workspace.monitors.preview');

        // Personal Knowledge Library & Collections
        Route::get('/library', [PersonalLibraryController::class, 'index'])->name('api.workspace.library.index');
        Route::post('/library', [PersonalLibraryController::class, 'store'])->name('api.workspace.library.store');
        Route::put('/library/{id}', [PersonalLibraryController::class, 'update'])->name('api.workspace.library.update');
        Route::delete('/library/{id}', [PersonalLibraryController::class, 'destroy'])->name('api.workspace.library.destroy');
        Route::get('/collections', [PersonalLibraryController::class, 'collections'])->name('api.workspace.collections.index');
        Route::post('/collections', [PersonalLibraryController::class, 'storeCollection'])->name('api.workspace.collections.store');
        Route::put('/collections/{id}', [PersonalLibraryController::class, 'updateCollection'])->name('api.workspace.collections.update');
        Route::delete('/collections/{id}', [PersonalLibraryController::class, 'destroyCollection'])->name('api.workspace.collections.destroy');
        Route::get('/collections/{id}/export', [PersonalLibraryController::class, 'export'])->name('api.workspace.collections.export');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.workspace.notifications.index');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.workspace.notifications.read');
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('api.workspace.notifications.patch-read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('api.workspace.notifications.mark-all-read');
        Route::post('/notifications/{id}/archive', [NotificationController::class, 'archive'])->name('api.workspace.notifications.archive');
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('api.workspace.notifications.destroy');
        Route::match(['get', 'post', 'put'], '/notifications/preferences', [NotificationController::class, 'preferences'])->name('api.workspace.notifications.preferences');

        // Stage 11 Multi-Channel Integration Endpoints
        Route::get('/notifications/push/vapid-key', [NotificationController::class, 'getVapidKey'])->name('api.workspace.notifications.vapid-key');
        Route::post('/notifications/push/subscribe', [NotificationController::class, 'subscribePush'])->name('api.workspace.notifications.push.subscribe');
        Route::post('/notifications/push/unsubscribe', [NotificationController::class, 'unsubscribePush'])->name('api.workspace.notifications.push.unsubscribe');
        Route::post('/notifications/telegram/link-token', [NotificationController::class, 'generateTelegramToken'])->name('api.workspace.notifications.telegram.token');
        Route::post('/notifications/telegram/verify', [NotificationController::class, 'verifyTelegram'])->name('api.workspace.notifications.telegram.verify');
        Route::post('/notifications/telegram/connect', [NotificationController::class, 'connectTelegram'])->name('api.workspace.notifications.telegram.connect');
        Route::post('/notifications/telegram/disconnect', [NotificationController::class, 'disconnectTelegram'])->name('api.workspace.notifications.telegram.disconnect');
        Route::post('/notifications/telegram/test', [NotificationController::class, 'testTelegram'])->name('api.workspace.notifications.telegram.test');
        Route::get('/notifications/whatsapp/status', [NotificationController::class, 'whatsappStatus'])->name('api.workspace.notifications.whatsapp.status');
        Route::post('/notifications/whatsapp/connect', [NotificationController::class, 'connectWhatsapp'])->name('api.workspace.notifications.whatsapp.connect');
        Route::post('/notifications/whatsapp/disconnect', [NotificationController::class, 'disconnectWhatsapp'])->name('api.workspace.notifications.whatsapp.disconnect');
        Route::post('/notifications/whatsapp/test', [NotificationController::class, 'testWhatsapp'])->name('api.workspace.notifications.whatsapp.test');

        // Personalized Research
        Route::post('/research/query', [SubscriberResearchController::class, 'query'])->name('api.workspace.research.query');
        Route::post('/research/watchlist-brief/{watchlistId}', [SubscriberResearchController::class, 'brief'])->name('api.workspace.research.brief');

        // Stage 12 Saved Searches & Automated Monitoring
        Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('api.workspace.saved-searches.index');
        Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('api.workspace.saved-searches.store');
        Route::get('/saved-searches/{id}', [SavedSearchController::class, 'show'])->name('api.workspace.saved-searches.show');
        Route::put('/saved-searches/{id}', [SavedSearchController::class, 'update'])->name('api.workspace.saved-searches.update');
        Route::post('/saved-searches/{id}/toggle', [SavedSearchController::class, 'toggle'])->name('api.workspace.saved-searches.toggle');
        Route::delete('/saved-searches/{id}', [SavedSearchController::class, 'destroy'])->name('api.workspace.saved-searches.destroy');
        Route::get('/saved-searches/{id}/matches', [SavedSearchController::class, 'matches'])->name('api.workspace.saved-searches.matches');

        // Stage 12 Grounded Research Briefs
        Route::get('/research-briefs', [ResearchBriefController::class, 'index'])->name('api.workspace.research-briefs.index');
        Route::post('/research-briefs/generate', [ResearchBriefController::class, 'generate'])->name('api.workspace.research-briefs.generate');
        Route::get('/research-briefs/{id}', [ResearchBriefController::class, 'show'])->name('api.workspace.research-briefs.show');
        Route::get('/research-briefs/{id}/export', [ResearchBriefController::class, 'export'])->name('api.workspace.research-briefs.export');

        // Stage 13 Early Warning Status Updates
        Route::post('/early-warnings/{id}/status', [EarlyWarningController::class, 'updateStatus'])->name('api.workspace.early-warnings.status');
    });

    // =========================================================================
    // Stage 15: Advanced Executive Intelligence & Decision Support Suite
    // =========================================================================
    Route::prefix('executive')->group(function () {
        // Executive Cockpit Overview
        Route::get('/cockpit', [ExecutiveDashboardController::class, 'cockpit'])->name('api.executive.cockpit');

        // Executive Intelligence Briefings
        Route::get('/briefings', [ExecutiveBriefingController::class, 'index'])->name('api.executive.briefings.index');
        Route::post('/briefings', [ExecutiveBriefingController::class, 'store'])->name('api.executive.briefings.store');
        Route::get('/briefings/{id}', [ExecutiveBriefingController::class, 'show'])->name('api.executive.briefings.show');
        Route::put('/briefings/{id}', [ExecutiveBriefingController::class, 'update'])->name('api.executive.briefings.update');
        Route::post('/briefings/{id}/review', [ExecutiveBriefingController::class, 'review'])->name('api.executive.briefings.review');
        Route::get('/briefings/{id}/export', [ExecutiveBriefingController::class, 'export'])->name('api.executive.briefings.export');

        // Strategic Impact Analysis
        Route::get('/strategic-impacts', [StrategicImpactController::class, 'index'])->name('api.executive.strategic-impacts.index');
        Route::post('/strategic-impacts', [StrategicImpactController::class, 'store'])->name('api.executive.strategic-impacts.store');
        Route::get('/strategic-impacts/{id}', [StrategicImpactController::class, 'show'])->name('api.executive.strategic-impacts.show');

        // Opportunity & Risk Intelligence
        Route::get('/opportunities', [OpportunityRiskController::class, 'getOpportunities'])->name('api.executive.opportunities.index');
        Route::post('/opportunities', [OpportunityRiskController::class, 'storeOpportunity'])->name('api.executive.opportunities.store');
        Route::patch('/opportunities/{id}/status', [OpportunityRiskController::class, 'updateOpportunityStatus'])->name('api.executive.opportunities.status');
        Route::get('/risks', [OpportunityRiskController::class, 'getRisks'])->name('api.executive.risks.index');
        Route::post('/risks', [OpportunityRiskController::class, 'storeRisk'])->name('api.executive.risks.store');
        Route::patch('/risks/{id}/status', [OpportunityRiskController::class, 'updateRiskStatus'])->name('api.executive.risks.status');

        // Scenario Analysis Engine
        Route::get('/scenarios', [ScenarioAnalysisController::class, 'index'])->name('api.executive.scenarios.index');
        Route::post('/scenarios', [ScenarioAnalysisController::class, 'store'])->name('api.executive.scenarios.store');
        Route::post('/scenarios/generate-suite', [ScenarioAnalysisController::class, 'generateSuite'])->name('api.executive.scenarios.generate-suite');
        Route::post('/scenarios/compare', [ScenarioAnalysisController::class, 'compare'])->name('api.executive.scenarios.compare');
        Route::post('/scenarios/{id}/duplicate', [ScenarioAnalysisController::class, 'duplicate'])->name('api.executive.scenarios.duplicate');
        Route::put('/scenarios/{id}', [ScenarioAnalysisController::class, 'update'])->name('api.executive.scenarios.update');
        Route::post('/scenarios/{id}/archive', [ScenarioAnalysisController::class, 'archive'])->name('api.executive.scenarios.archive');

        // Structured Decision Support Engine
        Route::get('/decisions', [DecisionSupportController::class, 'index'])->name('api.executive.decisions.index');
        Route::post('/decisions', [DecisionSupportController::class, 'store'])->name('api.executive.decisions.store');
        Route::get('/decisions/{id}', [DecisionSupportController::class, 'show'])->name('api.executive.decisions.show');
        Route::post('/decisions/{id}/options', [DecisionSupportController::class, 'addOption'])->name('api.executive.decisions.options.store');
        Route::post('/decisions/{id}/milestones', [DecisionSupportController::class, 'addMilestone'])->name('api.executive.decisions.milestones.store');
        Route::patch('/decisions/{id}/milestones/{milestoneId}', [DecisionSupportController::class, 'updateMilestone'])->name('api.executive.decisions.milestones.update');
        Route::post('/decisions/{id}/approve', [DecisionSupportController::class, 'approve'])->name('api.executive.decisions.approve');
        Route::post('/decisions/{id}/post-review', [DecisionSupportController::class, 'postReview'])->name('api.executive.decisions.post-review');

        // Executive Intelligence Assistant (Grounded Q&A)
        Route::post('/assistant/ask', [ExecutiveAssistantController::class, 'ask'])->name('api.executive.assistant.ask');
    });

    // Administrative Console Endpoints (Guarded by admin middleware)
    Route::prefix('admin')->middleware('admin')->group(function () {
        // 1. Dashboard & Operational Controls
        Route::get('/dashboard', [DashboardAdminController::class, 'metrics'])->name('api.admin.dashboard.metrics');
        Route::post('/dashboard/scan', [DashboardAdminController::class, 'triggerScan'])->name('api.admin.dashboard.scan');
        Route::post('/dashboard/trigger-scan', [DashboardAdminController::class, 'triggerScan']);
        Route::post('/dashboard/retry-failed', [DashboardAdminController::class, 'retryFailedJobs'])->name('api.admin.dashboard.retry-failed');

        // 2. AI Management & Multi-Credential Gateway
        Route::get('/ai-router', [AIRouterAdminController::class, 'index'])->name('api.admin.ai-router.index');
        Route::get('/ai-credentials', [AIRouterAdminController::class, 'credentials'])->name('api.admin.ai-credentials.index');
        Route::post('/ai-credentials', [AIRouterAdminController::class, 'storeCredential'])->name('api.admin.ai-credentials.store');
        Route::put('/ai-credentials/{id}', [AIRouterAdminController::class, 'updateCredential'])->name('api.admin.ai-credentials.update');
        Route::post('/ai-credentials/{id}/test', [AIRouterAdminController::class, 'testCredential'])->name('api.admin.ai-credentials.test');
        Route::delete('/ai-credentials/{id}', [AIRouterAdminController::class, 'destroyCredential'])->name('api.admin.ai-credentials.destroy');

        Route::get('/ai-router/credentials', [AIRouterAdminController::class, 'credentials']);
        Route::post('/ai-router/credentials', [AIRouterAdminController::class, 'storeCredential']);
        Route::put('/ai-router/credentials/{id}', [AIRouterAdminController::class, 'updateCredential']);
        Route::post('/ai-router/credentials/{id}/test', [AIRouterAdminController::class, 'testCredential']);
        Route::delete('/ai-router/credentials/{id}', [AIRouterAdminController::class, 'destroyCredential']);
        Route::post('/ai-router/execute-test', [AIRouterAdminController::class, 'executeTest'])->name('api.admin.ai-router.execute-test');

        Route::get('/ai-models', [AIRouterAdminController::class, 'models'])->name('api.admin.ai-models.index');
        Route::post('/ai-models', [AIRouterAdminController::class, 'storeModel'])->name('api.admin.ai-models.store');
        Route::put('/ai-models/{id}', [AIRouterAdminController::class, 'updateModel'])->name('api.admin.ai-models.update');
        Route::get('/ai-router/models', [AIRouterAdminController::class, 'models']);
        Route::post('/ai-router/models', [AIRouterAdminController::class, 'storeModel']);
        Route::put('/ai-router/models/{id}', [AIRouterAdminController::class, 'updateModel']);
        Route::get('/ai-router/providers/{id}/fetch-models', [AIRouterAdminController::class, 'fetchProviderModels'])->name('api.admin.ai-router.fetch-provider-models');

        Route::get('/ai-tasks', [AIRouterAdminController::class, 'tasks'])->name('api.admin.ai-tasks.index');
        Route::put('/ai-tasks/{id}', [AIRouterAdminController::class, 'updateTask'])->name('api.admin.ai-tasks.update');
        Route::get('/ai-router/tasks', [AIRouterAdminController::class, 'tasks']);
        Route::put('/ai-router/tasks/{id}', [AIRouterAdminController::class, 'updateTask']);

        Route::get('/ai-usage', [AIRouterAdminController::class, 'usage'])->name('api.admin.ai-usage.index');
        Route::get('/ai-router/usage', [AIRouterAdminController::class, 'usage']);
        Route::put('/ai-router/{id}', [AIRouterAdminController::class, 'update'])->name('api.admin.ai-router.update');

        // 3. Articles & Editorial Lifecycle Management
        Route::get('/articles', [EditorialAdminController::class, 'index'])->name('api.admin.articles.index');
        Route::post('/articles', [EditorialAdminController::class, 'store'])->name('api.admin.articles.store');
        Route::get('/articles/{id}', [EditorialAdminController::class, 'show'])->name('api.admin.articles.show');
        Route::put('/articles/{id}', [EditorialAdminController::class, 'update'])->name('api.admin.articles.update');
        Route::delete('/articles/{id}', [EditorialAdminController::class, 'destroy'])->name('api.admin.articles.destroy');
        Route::get('/articles/{id}/revisions', [EditorialAdminController::class, 'revisions'])->name('api.admin.articles.revisions');
        Route::post('/articles/{id}/revisions/{version}/restore', [EditorialAdminController::class, 'restoreRevision'])->name('api.admin.articles.revisions.restore');

        Route::get('/editorial', [EditorialAdminController::class, 'index'])->name('api.admin.editorial.index');
        Route::get('/editorial/{id}', [EditorialAdminController::class, 'show']);
        Route::post('/editorial', [EditorialAdminController::class, 'store']);
        Route::put('/editorial/{id}', [EditorialAdminController::class, 'update']);
        Route::delete('/editorial/{id}', [EditorialAdminController::class, 'destroy']);
        Route::post('/editorial/{id}/review', [EditorialAdminController::class, 'review'])->name('api.admin.editorial.review');
        Route::get('/editorial/{id}/revisions', [EditorialAdminController::class, 'revisions']);
        Route::post('/editorial/{id}/revisions/{version}/restore', [EditorialAdminController::class, 'restoreRevision']);

        // 4. Clusters & Events
        Route::get('/clusters', [ClusterAdminController::class, 'index'])->name('api.admin.clusters.index');
        Route::post('/clusters/merge', [ClusterAdminController::class, 'merge'])->name('api.admin.clusters.merge');
        Route::post('/clusters/split', [ClusterAdminController::class, 'split'])->name('api.admin.clusters.split');

        // 5. Sources Management & Stage 14 Global Source Network
        Route::get('/sources', [SourceAdminController::class, 'index'])->name('api.admin.sources.index');
        Route::post('/sources', [SourceAdminController::class, 'store'])->name('api.admin.sources.store');
        Route::get('/sources/onboarding-queue', [SourceAdminController::class, 'onboardingQueue'])->name('api.admin.sources.onboarding_queue');
        Route::post('/sources/onboard', [SourceAdminController::class, 'onboard'])->name('api.admin.sources.onboard');
        Route::get('/sources/health-overview', [SourceAdminController::class, 'healthOverview'])->name('api.admin.sources.health_overview');
        Route::post('/sources/health-check', [SourceAdminController::class, 'triggerHealthCheck'])->name('api.admin.sources.health_check');
        Route::get('/sources/coverage-analytics', [SourceAdminController::class, 'coverageAnalytics'])->name('api.admin.sources.coverage_analytics');
        Route::get('/sources/coverage-gaps', [SourceAdminController::class, 'coverageGaps'])->name('api.admin.sources.coverage_gaps');
        Route::get('/sources/diversity-metrics', [SourceAdminController::class, 'diversityMetrics'])->name('api.admin.sources.diversity_metrics');
        Route::put('/sources/{id}', [SourceAdminController::class, 'update'])->name('api.admin.sources.update');
        Route::post('/sources/{id}/approve', [SourceAdminController::class, 'approve'])->name('api.admin.sources.approve');
        Route::post('/sources/{id}/reject', [SourceAdminController::class, 'reject'])->name('api.admin.sources.reject');
        Route::post('/sources/{id}/suspend', [SourceAdminController::class, 'suspend'])->name('api.admin.sources.suspend');
        Route::get('/sources/{id}/reliability-profile', [SourceAdminController::class, 'reliabilityProfile'])->name('api.admin.sources.reliability_profile');
        Route::post('/sources/{id}/test', [SourceAdminController::class, 'testConnection'])->name('api.admin.sources.test');
        Route::post('/sources/{id}/scan', [SourceAdminController::class, 'scan'])->name('api.admin.sources.scan');
        Route::put('/sources/{id}/toggle', [SourceAdminController::class, 'toggle'])->name('api.admin.sources.toggle');
        Route::delete('/sources/{id}', [SourceAdminController::class, 'destroy'])->name('api.admin.sources.destroy');

        // 6. Automation & Ingestion Pipeline
        Route::get('/automation/jobs', [AutomationAdminController::class, 'index'])->name('api.admin.automation.index');
        Route::post('/automation/jobs/{id}/retry', [AutomationAdminController::class, 'retry'])->name('api.admin.automation.retry');
        Route::post('/automation/jobs/{id}/cancel', [AutomationAdminController::class, 'cancel'])->name('api.admin.automation.cancel');
        Route::post('/automation/scan', [AutomationAdminController::class, 'triggerScan'])->name('api.admin.automation.scan');

        // Intelligence Ingestion & Cognitive Pipeline (Phase 5)
        Route::get('/intelligence', [IntelligenceAdminController::class, 'dashboard'])->name('api.admin.intelligence.dashboard');
        Route::get('/intelligence/ingestion', [IntelligenceAdminController::class, 'ingestion'])->name('api.admin.intelligence.ingestion');
        Route::post('/intelligence/ingestion/trigger', [IntelligenceAdminController::class, 'triggerIngestion'])->name('api.admin.intelligence.ingestion.trigger');
        Route::post('/intelligence/ingestion/toggle-pause', [IntelligenceAdminController::class, 'toggleAutomation'])->name('api.admin.intelligence.ingestion.toggle');
        Route::get('/intelligence/discoveries', [IntelligenceAdminController::class, 'discoveries'])->name('api.admin.intelligence.discoveries');
        Route::get('/intelligence/discoveries/{id}', [IntelligenceAdminController::class, 'showDiscovery'])->name('api.admin.intelligence.discoveries.show');
        Route::post('/intelligence/discoveries/{id}/assign-cluster', [IntelligenceAdminController::class, 'assignStoryToCluster'])->name('api.admin.intelligence.discoveries.assign-cluster');
        Route::post('/intelligence/discoveries/{id}/false-duplicate', [IntelligenceAdminController::class, 'markFalseDuplicate'])->name('api.admin.intelligence.discoveries.false-duplicate');
        Route::post('/intelligence/discoveries/{id}/exclude', [IntelligenceAdminController::class, 'excludeStory'])->name('api.admin.intelligence.discoveries.exclude');
        Route::get('/intelligence/clusters', [IntelligenceAdminController::class, 'clusters'])->name('api.admin.intelligence.clusters');
        Route::get('/intelligence/clusters/{id}', [IntelligenceAdminController::class, 'showCluster'])->name('api.admin.intelligence.clusters.show');
        Route::post('/intelligence/clusters/merge', [IntelligenceAdminController::class, 'mergeClusters'])->name('api.admin.intelligence.clusters.merge');
        Route::post('/intelligence/clusters/split', [IntelligenceAdminController::class, 'splitCluster'])->name('api.admin.intelligence.clusters.split');
        Route::get('/intelligence/facts', [IntelligenceAdminController::class, 'facts'])->name('api.admin.intelligence.facts');
        Route::put('/intelligence/facts/{id}', [IntelligenceAdminController::class, 'updateFactStatus'])->name('api.admin.intelligence.facts.update');
        Route::get('/intelligence/pipeline', [IntelligenceAdminController::class, 'pipeline'])->name('api.admin.intelligence.pipeline');
        Route::post('/intelligence/pipeline/{id}/retry', [IntelligenceAdminController::class, 'retryPipelineJob'])->name('api.admin.intelligence.pipeline.retry');
        Route::post('/intelligence/pipeline/{id}/cancel', [IntelligenceAdminController::class, 'cancelPipelineJob'])->name('api.admin.intelligence.pipeline.cancel');
        Route::get('/intelligence/automation', [IntelligenceAdminController::class, 'automationSettings'])->name('api.admin.intelligence.automation.get');
        Route::put('/intelligence/automation', [IntelligenceAdminController::class, 'automationSettings'])->name('api.admin.intelligence.automation.update');

        // Phase 6: Intelligence Quality, Verification & Deep Research
        Route::get('/research', [ResearchAdminController::class, 'overview'])->name('api.admin.research.overview');
        Route::get('/research/entities', [ResearchAdminController::class, 'listEntities'])->name('api.admin.research.entities.index');
        Route::post('/research/entities', [ResearchAdminController::class, 'storeEntity'])->name('api.admin.research.entities.store');
        Route::get('/research/entities/reviews/queue', [ResearchAdminController::class, 'listReviewQueue'])->name('api.admin.research.entities.reviews');
        Route::post('/research/entities/reviews/{reviewId}/decision', [ResearchAdminController::class, 'reviewDecision'])->name('api.admin.research.entities.review-decision');
        Route::get('/research/entities/{id}', [ResearchAdminController::class, 'showEntity'])->name('api.admin.research.entities.show');
        Route::put('/research/entities/{id}', [ResearchAdminController::class, 'updateEntity'])->name('api.admin.research.entities.update');
        Route::post('/research/entities/{id}/verify', [ResearchAdminController::class, 'verifyEntity'])->name('api.admin.research.entities.verify');
        Route::post('/research/entities/{id}/dispute', [ResearchAdminController::class, 'disputeEntity'])->name('api.admin.research.entities.dispute');
        Route::post('/research/entities/{id}/merge', [ResearchAdminController::class, 'mergeEntities'])->name('api.admin.research.entities.merge');
        Route::post('/research/entities/{id}/split', [ResearchAdminController::class, 'splitEntity'])->name('api.admin.research.entities.split');

        Route::get('/research/companies', [ResearchAdminController::class, 'listCompanies'])->name('api.admin.research.companies');
        Route::get('/research/executives', [ResearchAdminController::class, 'listExecutives'])->name('api.admin.research.executives');
        Route::get('/research/technology', [ResearchAdminController::class, 'listTechnology'])->name('api.admin.research.technology');

        Route::get('/research/graph', [ResearchAdminController::class, 'getGraph'])->name('api.admin.research.graph');
        Route::post('/research/relationships', [ResearchAdminController::class, 'storeRelationship'])->name('api.admin.research.relationships.store');
        Route::post('/research/relationships/{id}/verify', [ResearchAdminController::class, 'verifyRelationship'])->name('api.admin.research.relationships.verify');

        Route::get('/research/verification', [ResearchAdminController::class, 'listFacts'])->name('api.admin.research.facts');
        Route::post('/research/verification/{factId}/evidence', [ResearchAdminController::class, 'attachFactEvidence'])->name('api.admin.research.facts.evidence');
        Route::post('/research/verification/{factId}/verdict', [ResearchAdminController::class, 'recordFactVerdict'])->name('api.admin.research.facts.verdict');

        Route::get('/research/timeline', [ResearchAdminController::class, 'listTimeline'])->name('api.admin.research.timeline');
        Route::post('/research/timeline', [ResearchAdminController::class, 'storeEvent'])->name('api.admin.research.timeline.store');
        Route::put('/research/timeline/{id}/date-correction', [ResearchAdminController::class, 'correctEventDate'])->name('api.admin.research.timeline.correct-date');

        Route::get('/research/trends', [ResearchAdminController::class, 'listTrends'])->name('api.admin.research.trends');
        Route::post('/research/trends/scan', [ResearchAdminController::class, 'triggerTrendScan'])->name('api.admin.research.trends.scan');

        Route::get('/research/workspace/projects', [ResearchAdminController::class, 'listResearchProjects'])->name('api.admin.research.projects');
        Route::post('/research/workspace/projects', [ResearchAdminController::class, 'initiateResearch'])->name('api.admin.research.projects.store');
        Route::get('/research/reports', [ResearchAdminController::class, 'listReports'])->name('api.admin.research.reports');
        Route::get('/research/reports/{id}', [ResearchAdminController::class, 'showReport'])->name('api.admin.research.reports.show');
        Route::put('/research/reports/{id}/status', [ResearchAdminController::class, 'updateReportStatus'])->name('api.admin.research.reports.status');

        // 7. Taxonomy & Entities
        Route::get('/taxonomy', [TaxonomyAdminController::class, 'index'])->name('api.admin.taxonomy.index');
        Route::get('/taxonomy/categories', [TaxonomyAdminController::class, 'categories']);
        Route::post('/taxonomy/categories', [TaxonomyAdminController::class, 'storeCategory'])->name('api.admin.taxonomy.categories.store');
        Route::put('/taxonomy/categories/{id}', [TaxonomyAdminController::class, 'updateCategory'])->name('api.admin.taxonomy.categories.update');
        Route::delete('/taxonomy/categories/{id}', [TaxonomyAdminController::class, 'destroyCategory']);
        Route::get('/taxonomy/subcategories', [TaxonomyAdminController::class, 'subcategories']);
        Route::post('/taxonomy/subcategories', [TaxonomyAdminController::class, 'storeSubcategory'])->name('api.admin.taxonomy.subcategories.store');
        Route::get('/taxonomy/topics', [TaxonomyAdminController::class, 'topics']);
        Route::post('/taxonomy/topics', [TaxonomyAdminController::class, 'storeTopic'])->name('api.admin.taxonomy.topics.store');
        Route::get('/taxonomy/tags', [TaxonomyAdminController::class, 'tags']);
        Route::post('/taxonomy/tags', [TaxonomyAdminController::class, 'storeTag'])->name('api.admin.taxonomy.tags.store');
        Route::delete('/taxonomy/tags/{id}', [TaxonomyAdminController::class, 'destroyTag']);
        Route::get('/taxonomy/series', [TaxonomyAdminController::class, 'series']);
        Route::post('/taxonomy/series', [TaxonomyAdminController::class, 'storeSeries'])->name('api.admin.taxonomy.series.store');

        // 7.1 Company Directory & Geo-Hierarchy
        Route::get('/companies/geo-options', [CompanyAdminController::class, 'geoHierarchyOptions'])->name('api.admin.companies.geo-options');
        Route::get('/companies', [CompanyAdminController::class, 'index'])->name('api.admin.companies.index');
        Route::post('/companies', [CompanyAdminController::class, 'store'])->name('api.admin.companies.store');
        Route::get('/companies/{id}', [CompanyAdminController::class, 'show'])->name('api.admin.companies.show');
        Route::put('/companies/{id}', [CompanyAdminController::class, 'update'])->name('api.admin.companies.update');
        Route::delete('/companies/{id}', [CompanyAdminController::class, 'destroy'])->name('api.admin.companies.destroy');

        // 8. RAG & Knowledge
        Route::get('/rag-knowledge', [RagAdminController::class, 'index'])->name('api.admin.rag.index');
        Route::post('/rag-knowledge/ingest', [RagAdminController::class, 'ingest'])->name('api.admin.rag.ingest');
        Route::get('/rag-knowledge/documents/{id}/chunks', [RagAdminController::class, 'documentChunks'])->name('api.admin.rag.chunks');
        Route::post('/rag-knowledge/{id}/reindex', [RagAdminController::class, 'reindex'])->name('api.admin.rag.reindex');
        Route::delete('/rag-knowledge/{id}', [RagAdminController::class, 'destroy'])->name('api.admin.rag.destroy');
        Route::post('/rag-knowledge/clear-cache', [RagAdminController::class, 'clearCache'])->name('api.admin.rag.clear-cache');

        // Phase 7B: Executive Publications & PDF Engine
        Route::prefix('publications')->group(function () {
            Route::get('/overview', [PublicationAdminController::class, 'overview'])->name('api.admin.publications.overview');
            Route::get('/', [PublicationAdminController::class, 'index'])->name('api.admin.publications.index');
            Route::post('/', [PublicationAdminController::class, 'store'])->name('api.admin.publications.store');
            Route::get('/templates', [PublicationAdminController::class, 'templates'])->name('api.admin.publications.templates.index');
            Route::post('/templates', [PublicationAdminController::class, 'storeTemplate'])->name('api.admin.publications.templates.store');
            Route::get('/schedules', [PublicationAdminController::class, 'schedules'])->name('api.admin.publications.schedules.index');
            Route::post('/schedules', [PublicationAdminController::class, 'storeSchedule'])->name('api.admin.publications.schedules.store');
            Route::post('/schedules/{id}/trigger', [PublicationAdminController::class, 'triggerSchedule'])->name('api.admin.publications.schedules.trigger');
            Route::get('/{id}', [PublicationAdminController::class, 'show'])->name('api.admin.publications.show');
            Route::put('/{id}', [PublicationAdminController::class, 'update'])->name('api.admin.publications.update');
            Route::post('/{id}/approve', [PublicationAdminController::class, 'approve'])->name('api.admin.publications.approve');
            Route::post('/{id}/publish', [PublicationAdminController::class, 'publish'])->name('api.admin.publications.publish');
            Route::post('/{id}/archive', [PublicationAdminController::class, 'archive'])->name('api.admin.publications.archive');
            Route::delete('/{id}', [PublicationAdminController::class, 'destroy'])->name('api.admin.publications.destroy');
            Route::post('/{id}/tokens', [PublicationAdminController::class, 'createToken'])->name('api.admin.publications.tokens.store');
            Route::post('/tokens/{tokenId}/revoke', [PublicationAdminController::class, 'revokeToken'])->name('api.admin.publications.tokens.revoke');
        });

        // 9. Users & Access Control
        Route::get('/users', [UserAdminController::class, 'index'])->name('api.admin.users.index');
        Route::get('/users/roles', [UserAdminController::class, 'roles'])->name('api.admin.users.roles');
        Route::get('/access/roles', [UserAdminController::class, 'roles'])->name('api.admin.access.roles');
        Route::get('/users/{id}', [UserAdminController::class, 'show'])->name('api.admin.users.show');
        Route::put('/users/{id}/status', [UserAdminController::class, 'toggleStatus'])->name('api.admin.users.status');
        Route::put('/users/{id}/role', [UserAdminController::class, 'updateRole'])->name('api.admin.users.role');
        Route::post('/users/{id}/reset-password', [UserAdminController::class, 'resetPassword'])->name('api.admin.users.reset-password');

        // 10. Payments & Compliance
        Route::get('/payments', [PaymentAdminController::class, 'index'])->name('api.admin.payments.index');
        Route::post('/payments/{id}/verify', [PaymentAdminController::class, 'verify'])->name('api.admin.payments.verify');
        Route::get('/payments/methods', [PaymentAdminController::class, 'methods'])->name('api.admin.payments.methods');
        Route::put('/payments/methods/{id}', [PaymentAdminController::class, 'updateMethod'])->name('api.admin.payments.methods.update');

        // 11. Notifications & Central Dispatch (Stage 11)
        Route::get('/notifications/metrics', [NotificationAdminController::class, 'metrics'])->name('api.admin.notifications.metrics');
        Route::get('/notifications/logs', [NotificationAdminController::class, 'logs'])->name('api.admin.notifications.logs');
        Route::get('/notifications/rules', [NotificationAdminController::class, 'rules'])->name('api.admin.notifications.rules');
        Route::put('/notifications/rules/{id}', [NotificationAdminController::class, 'updateRule'])->name('api.admin.notifications.rules.update');
        Route::post('/notifications/broadcast', [NotificationAdminController::class, 'store'])->name('api.admin.notifications.broadcast');
        Route::get('/notifications', [NotificationAdminController::class, 'index'])->name('api.admin.notifications.index');
        Route::post('/notifications', [NotificationAdminController::class, 'store'])->name('api.admin.notifications.store');
        Route::post('/notifications/{id}/retry', [NotificationAdminController::class, 'retry'])->name('api.admin.notifications.retry');
        Route::delete('/notifications/{id}', [NotificationAdminController::class, 'destroy'])->name('api.admin.notifications.destroy');

        // 12. Advertising & System Settings
        Route::get('/advertising', [AdAdminController::class, 'index'])->name('api.admin.ads.index');
        Route::put('/advertising/{id}/status', [AdAdminController::class, 'updateStatus'])->name('api.admin.ads.status');

        Route::get('/system/settings', [SystemAdminController::class, 'settings'])->name('api.admin.system.settings');
        Route::put('/system/settings', [SystemAdminController::class, 'updateSettings'])->name('api.admin.system.update');
        Route::get('/system/ai-logs', [SystemAdminController::class, 'aiLogs']);
        Route::get('/logs/ai', [SystemAdminController::class, 'aiLogs'])->name('api.admin.logs.ai');
        Route::get('/audit-logs', [SystemAdminController::class, 'auditLogs'])->name('api.admin.audit-logs');

        Route::get('/newsletter/subscribers', [NewsletterController::class, 'index'])->name('api.admin.newsletter.subscribers');

        // 13. Subscriber Intelligence Administration (Phase 7C)
        Route::prefix('subscriber-intelligence')->group(function () {
            Route::get('/overview', [SubscriberAdminController::class, 'overview'])->name('api.admin.subscriber-intelligence.overview');
            Route::get('/delivery-logs', [SubscriberAdminController::class, 'notificationLogs'])->name('api.admin.subscriber-intelligence.delivery-logs');
            Route::match(['get', 'post', 'put'], '/policies', [SubscriberAdminController::class, 'policies'])->name('api.admin.subscriber-intelligence.policies');
        });

        // 14. Billing, Subscriptions & Gateways Control Center (Phase 7D)
        Route::prefix('billing')->group(function () {
            Route::get('/overview', [BillingAdminController::class, 'overview'])->name('api.admin.billing.overview');
            Route::get('/plans', [BillingAdminController::class, 'plans'])->name('api.admin.billing.plans');
            Route::post('/plans', [BillingAdminController::class, 'storePlan'])->name('api.admin.billing.plans.store');
            Route::put('/plans/{id}', [BillingAdminController::class, 'updatePlan'])->name('api.admin.billing.plans.update');
            Route::get('/subscriptions', [BillingAdminController::class, 'subscriptions'])->name('api.admin.billing.subscriptions');
            Route::get('/subscriptions/{id}', [BillingAdminController::class, 'subscriptionDetails'])->name('api.admin.billing.subscriptions.details');
            Route::put('/subscriptions/{id}/status', [BillingAdminController::class, 'updateSubscriptionStatus'])->name('api.admin.billing.subscriptions.status');
            Route::post('/subscriptions/{id}/override', [BillingAdminController::class, 'grantOverride'])->name('api.admin.billing.subscriptions.override');
            Route::delete('/overrides/{id}', [BillingAdminController::class, 'revokeOverride'])->name('api.admin.billing.overrides.revoke');
            Route::get('/invoices', [BillingAdminController::class, 'invoices'])->name('api.admin.billing.invoices');
            Route::get('/invoices/{id}', [BillingAdminController::class, 'invoiceDetails'])->name('api.admin.billing.invoices.details');
            Route::get('/gateways', [BillingAdminController::class, 'gateways'])->name('api.admin.billing.gateways');
            Route::put('/gateways/{key}', [BillingAdminController::class, 'updateGateway'])->name('api.admin.billing.gateways.update');
            Route::post('/gateways/{key}/test', [BillingAdminController::class, 'testGateway'])->name('api.admin.billing.gateways.test');
            Route::get('/refunds', [BillingAdminController::class, 'refunds'])->name('api.admin.billing.refunds');
            Route::post('/refunds', [BillingAdminController::class, 'processRefund'])->name('api.admin.billing.refunds.process');
            Route::get('/coupons', [BillingAdminController::class, 'coupons'])->name('api.admin.billing.coupons');
            Route::post('/coupons', [BillingAdminController::class, 'storeCoupon'])->name('api.admin.billing.coupons.store');
            Route::put('/coupons/{id}/toggle', [BillingAdminController::class, 'toggleCoupon'])->name('api.admin.billing.coupons.toggle');
            Route::post('/reconcile', [BillingAdminController::class, 'reconcile'])->name('api.admin.billing.reconcile');
            Route::get('/reconcile/history', [BillingAdminController::class, 'reconciliationHistory'])->name('api.admin.billing.reconcile.history');
        });

        // 15. Organization & Multi-Tenant Control Center (Phase 7E)
        Route::prefix('organizations')->group(function () {
            Route::get('/', [OrganizationAdminController::class, 'index'])->name('api.admin.organizations.index');
            Route::get('/metrics', [OrganizationAdminController::class, 'metrics'])->name('api.admin.organizations.metrics');
            Route::post('/', [OrganizationAdminController::class, 'store'])->name('api.admin.organizations.store');
            Route::get('/{id}', [OrganizationAdminController::class, 'show'])->name('api.admin.organizations.show');
            Route::put('/{id}', [OrganizationAdminController::class, 'update'])->name('api.admin.organizations.update');
            Route::put('/{id}/seats', [OrganizationAdminController::class, 'updateSeats'])->name('api.admin.organizations.seats');
            Route::put('/{id}/status', [OrganizationAdminController::class, 'toggleStatus'])->name('api.admin.organizations.status');
            Route::delete('/{id}', [OrganizationAdminController::class, 'destroy'])->name('api.admin.organizations.destroy');
        });

        // 16. Search Analytics & Query Telemetry (Stage 12)
        Route::prefix('search-analytics')->group(function () {
            Route::get('/overview', [SearchAnalyticsAdminController::class, 'overview'])->name('api.admin.search-analytics.overview');
            Route::get('/logs', [SearchAnalyticsAdminController::class, 'logs'])->name('api.admin.search-analytics.logs');
            Route::get('/unanswered', [SearchAnalyticsAdminController::class, 'unanswered'])->name('api.admin.search-analytics.unanswered');
            Route::get('/feedback', [SearchAnalyticsAdminController::class, 'feedback'])->name('api.admin.search-analytics.feedback');
        });

        // 17. Intelligence Automation & Early Warning Administration (Stage 13)
        Route::prefix('automation-control')->group(function () {
            Route::get('/overview', [IntelligenceAutomationAdminController::class, 'overview'])->name('api.admin.automation-control.overview');
            Route::get('/telemetry', [IntelligenceAutomationAdminController::class, 'telemetry'])->name('api.admin.automation-control.telemetry');
            Route::get('/rules', [IntelligenceAutomationAdminController::class, 'rules'])->name('api.admin.automation-control.rules');
            Route::post('/rules', [IntelligenceAutomationAdminController::class, 'storeRule'])->name('api.admin.automation-control.rules.store');
            Route::match(['put', 'patch'], '/rules/{id}', [IntelligenceAutomationAdminController::class, 'updateRule'])->name('api.admin.automation-control.rules.update');
            Route::post('/rules/{id}/toggle', [IntelligenceAutomationAdminController::class, 'toggleRule'])->name('api.admin.automation-control.rules.toggle');
            Route::post('/trigger-scan', [IntelligenceAutomationAdminController::class, 'triggerScan'])->name('api.admin.automation-control.trigger-scan');
            Route::post('/scan', [IntelligenceAutomationAdminController::class, 'triggerScan']);
            Route::get('/anomalies', [IntelligenceAutomationAdminController::class, 'overview']);
            Route::get('/reviews', [AnalystReviewAdminController::class, 'index'])->name('api.admin.automation-control.reviews.index');
            Route::post('/reviews/{signalId}', [AnalystReviewAdminController::class, 'submitReview'])->name('api.admin.automation-control.reviews.submit');
            Route::post('/reviews', [AnalystReviewAdminController::class, 'submitReview']);
            Route::get('/reviews/stats', [AnalystReviewAdminController::class, 'stats'])->name('api.admin.automation-control.reviews.stats');
        });
    });
});
