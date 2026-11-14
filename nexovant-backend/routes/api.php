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
use App\Http\Controllers\Api\V1\Admin\NotificationAdminController;
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
Route::get('/publications', [PublicationController::class, 'index'])->name('api.publications.index');
Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/search', [SearchController::class, 'search'])->name('api.search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('api.search.suggestions');

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('api.auth.admin-login');
    Route::post('/admin-login', [AuthController::class, 'adminLogin']);

    // Protected Auth
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('api.auth.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

Route::post('/admin/login', [AuthController::class, 'adminLogin']);

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

    // Organization Workspace
    Route::prefix('organization')->group(function () {
        Route::get('/current', [OrganizationController::class, 'current'])->name('api.organization.current');
        Route::post('/', [OrganizationController::class, 'store'])->name('api.organization.store');
        Route::post('/invite', [OrganizationController::class, 'invite'])->name('api.organization.invite');
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

    // Administrative Console Endpoints (Guarded by admin middleware)
    Route::prefix('admin')->middleware('admin')->group(function () {
        // 1. Dashboard & Operational Controls
        Route::get('/dashboard', [DashboardAdminController::class, 'metrics'])->name('api.admin.dashboard.metrics');
        Route::post('/dashboard/scan', [DashboardAdminController::class, 'triggerScan'])->name('api.admin.dashboard.scan');
        Route::post('/dashboard/retry-failed', [DashboardAdminController::class, 'retryFailedJobs'])->name('api.admin.dashboard.retry-failed');

        // 2. AI Management & Multi-Credential Gateway
        Route::get('/ai-router', [AIRouterAdminController::class, 'index'])->name('api.admin.ai-router.index');
        Route::put('/ai-router/{id}', [AIRouterAdminController::class, 'update'])->name('api.admin.ai-router.update');
        Route::get('/ai-credentials', [AIRouterAdminController::class, 'credentials'])->name('api.admin.ai-credentials.index');
        Route::post('/ai-credentials', [AIRouterAdminController::class, 'storeCredential'])->name('api.admin.ai-credentials.store');
        Route::put('/ai-credentials/{id}', [AIRouterAdminController::class, 'updateCredential'])->name('api.admin.ai-credentials.update');
        Route::post('/ai-credentials/{id}/test', [AIRouterAdminController::class, 'testCredential'])->name('api.admin.ai-credentials.test');
        Route::delete('/ai-credentials/{id}', [AIRouterAdminController::class, 'destroyCredential'])->name('api.admin.ai-credentials.destroy');

        Route::get('/ai-models', [AIRouterAdminController::class, 'models'])->name('api.admin.ai-models.index');
        Route::post('/ai-models', [AIRouterAdminController::class, 'storeModel'])->name('api.admin.ai-models.store');
        Route::put('/ai-models/{id}', [AIRouterAdminController::class, 'updateModel'])->name('api.admin.ai-models.update');

        Route::get('/ai-tasks', [AIRouterAdminController::class, 'tasks'])->name('api.admin.ai-tasks.index');
        Route::put('/ai-tasks/{id}', [AIRouterAdminController::class, 'updateTask'])->name('api.admin.ai-tasks.update');
        Route::get('/ai-usage', [AIRouterAdminController::class, 'usage'])->name('api.admin.ai-usage.index');

        // 3. Articles & Editorial Lifecycle Management
        Route::get('/articles', [EditorialAdminController::class, 'index'])->name('api.admin.articles.index');
        Route::post('/articles', [EditorialAdminController::class, 'store'])->name('api.admin.articles.store');
        Route::get('/articles/{id}', [EditorialAdminController::class, 'show'])->name('api.admin.articles.show');
        Route::put('/articles/{id}', [EditorialAdminController::class, 'update'])->name('api.admin.articles.update');
        Route::delete('/articles/{id}', [EditorialAdminController::class, 'destroy'])->name('api.admin.articles.destroy');
        Route::get('/articles/{id}/revisions', [EditorialAdminController::class, 'revisions'])->name('api.admin.articles.revisions');
        Route::post('/articles/{id}/revisions/{version}/restore', [EditorialAdminController::class, 'restoreRevision'])->name('api.admin.articles.revisions.restore');

        Route::get('/editorial', [EditorialAdminController::class, 'index'])->name('api.admin.editorial.index');
        Route::post('/editorial/{id}/review', [EditorialAdminController::class, 'review'])->name('api.admin.editorial.review');

        // 4. Clusters & Events
        Route::get('/clusters', [ClusterAdminController::class, 'index'])->name('api.admin.clusters.index');
        Route::post('/clusters/merge', [ClusterAdminController::class, 'merge'])->name('api.admin.clusters.merge');
        Route::post('/clusters/split', [ClusterAdminController::class, 'split'])->name('api.admin.clusters.split');

        // 5. Sources Management
        Route::get('/sources', [SourceAdminController::class, 'index'])->name('api.admin.sources.index');
        Route::post('/sources', [SourceAdminController::class, 'store'])->name('api.admin.sources.store');
        Route::put('/sources/{id}', [SourceAdminController::class, 'update'])->name('api.admin.sources.update');
        Route::post('/sources/{id}/test', [SourceAdminController::class, 'testConnection'])->name('api.admin.sources.test');
        Route::post('/sources/{id}/scan', [SourceAdminController::class, 'scan'])->name('api.admin.sources.scan');
        Route::put('/sources/{id}/toggle', [SourceAdminController::class, 'toggle'])->name('api.admin.sources.toggle');
        Route::delete('/sources/{id}', [SourceAdminController::class, 'destroy'])->name('api.admin.sources.destroy');

        // 6. Automation & Ingestion Pipeline
        Route::get('/automation/jobs', [AutomationAdminController::class, 'index'])->name('api.admin.automation.index');
        Route::post('/automation/jobs/{id}/retry', [AutomationAdminController::class, 'retry'])->name('api.admin.automation.retry');
        Route::post('/automation/jobs/{id}/cancel', [AutomationAdminController::class, 'cancel'])->name('api.admin.automation.cancel');
        Route::post('/automation/scan', [AutomationAdminController::class, 'triggerScan'])->name('api.admin.automation.scan');

        // 7. Taxonomy & Entities
        Route::get('/taxonomy', [TaxonomyAdminController::class, 'index'])->name('api.admin.taxonomy.index');
        Route::post('/taxonomy/categories', [TaxonomyAdminController::class, 'storeCategory'])->name('api.admin.taxonomy.categories.store');
        Route::put('/taxonomy/categories/{id}', [TaxonomyAdminController::class, 'updateCategory'])->name('api.admin.taxonomy.categories.update');
        Route::post('/taxonomy/subcategories', [TaxonomyAdminController::class, 'storeSubcategory'])->name('api.admin.taxonomy.subcategories.store');
        Route::post('/taxonomy/topics', [TaxonomyAdminController::class, 'storeTopic'])->name('api.admin.taxonomy.topics.store');
        Route::post('/taxonomy/tags', [TaxonomyAdminController::class, 'storeTag'])->name('api.admin.taxonomy.tags.store');
        Route::post('/taxonomy/series', [TaxonomyAdminController::class, 'storeSeries'])->name('api.admin.taxonomy.series.store');

        // 8. RAG & Knowledge
        Route::get('/rag-knowledge', [RagAdminController::class, 'index'])->name('api.admin.rag.index');
        Route::post('/rag-knowledge/{id}/reindex', [RagAdminController::class, 'reindex'])->name('api.admin.rag.reindex');
        Route::post('/rag-knowledge/clear-cache', [RagAdminController::class, 'clearCache'])->name('api.admin.rag.clear-cache');

        // 9. Users & Access Control
        Route::get('/users', [UserAdminController::class, 'index'])->name('api.admin.users.index');
        Route::get('/users/{id}', [UserAdminController::class, 'show'])->name('api.admin.users.show');
        Route::put('/users/{id}/status', [UserAdminController::class, 'toggleStatus'])->name('api.admin.users.status');
        Route::put('/users/{id}/role', [UserAdminController::class, 'updateRole'])->name('api.admin.users.role');
        Route::post('/users/{id}/reset-password', [UserAdminController::class, 'resetPassword'])->name('api.admin.users.reset-password');
        Route::get('/access/roles', [UserAdminController::class, 'roles'])->name('api.admin.access.roles');

        // 10. Payments & Subscription Review
        Route::get('/payments', [PaymentAdminController::class, 'index'])->name('api.admin.payments.index');
        Route::post('/payments/{id}/verify', [PaymentAdminController::class, 'verify'])->name('api.admin.payments.verify');
        Route::get('/payments/methods', [PaymentAdminController::class, 'methods'])->name('api.admin.payments.methods');
        Route::put('/payments/methods/{id}', [PaymentAdminController::class, 'updateMethod'])->name('api.admin.payments.methods.update');

        // 11. Notifications & Delivery
        Route::get('/notifications', [NotificationAdminController::class, 'index'])->name('api.admin.notifications.index');
        Route::post('/notifications', [NotificationAdminController::class, 'store'])->name('api.admin.notifications.store');
        Route::post('/notifications/{id}/retry', [NotificationAdminController::class, 'retry'])->name('api.admin.notifications.retry');
        Route::delete('/notifications/{id}', [NotificationAdminController::class, 'destroy'])->name('api.admin.notifications.destroy');

        // 12. Advertising & System Configuration
        Route::get('/advertising', [AdAdminController::class, 'index'])->name('api.admin.ads.index');
        Route::put('/advertising/{id}/status', [AdAdminController::class, 'updateStatus'])->name('api.admin.ads.status');

        Route::get('/system/settings', [SystemAdminController::class, 'settings'])->name('api.admin.system.settings');
        Route::put('/system/settings', [SystemAdminController::class, 'updateSettings'])->name('api.admin.system.update');
        Route::get('/audit-logs', [SystemAdminController::class, 'auditLogs'])->name('api.admin.audit-logs');
        Route::get('/logs/ai', [SystemAdminController::class, 'aiLogs'])->name('api.admin.logs.ai');

        Route::get('/newsletter/subscribers', [NewsletterController::class, 'index'])->name('api.admin.newsletter.subscribers');
    });
});
