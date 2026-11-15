# NEXOVANT — STAGE 8 IMPLEMENTATION PLAN
## Personalized Intelligence Feed & Authenticated Navigation

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 8  
**Architecture:** Vue 3 + TypeScript (Frontend) & Laravel 12 + Eloquent + Sanctum (Backend)  
**Author:** Google Antigravity Production Engineering Lead  
**Date:** 2026-11-16  

---

## 1. Executive Summary & Objective

Stage 8 transforms Nexovant into an institutional-grade, personalized intelligence product for authenticated subscribers. Rather than acting as a standard news website, the platform delivers a structured, personalized intelligence workspace where decision-makers can discover, follow, monitor, save, and explore connected technology and business intelligence.

Core objectives:
1. **Authenticated Application Shell:** Consistent 3-part layout (Left Navigation Sidebar, Main Content Area, Right Intelligence Panel, and Mobile Navigation Drawer).
2. **Personalized Intelligence Feed (`/feed`):** Rich algorithmic feed powered by `PersonalizedFeedService`, supporting dynamic category filtering, 5-tier sorting (Relevant, Latest, Discussed, Trending, Credible), consolidated stories, and card interactions.
3. **Subscriber Overview Dashboard (`/dashboard`):** Refined executive overview backed by real backend metrics (followed entities, active watchlists, monitors, library count, unread alerts, recent research).
4. **Following Management (`/following`):** Dedicated interface to follow/unfollow companies, executives, technologies, industries, and topics, browse suggested entities, and view direct followed activity.
5. **Story Detail Experience (`/news/:slug`):** Deep editorial view distinguishing verified facts, independently supported claims, disputed statements, and AI synthesis.
6. **Trending Intelligence (`/trends`):** Time-window filtered trends (24h, 7d, 30d) with real growth velocity scores.
7. **Unified Search & Discovery (`/search`):** Multi-entity search clearly demarcating keyword search from Grounded RAG research.
8. **Saved Intelligence (`/saved`):** Personal library with collection management and private annotations.
9. **Entitlement Awareness:** Graceful upgrade prompts across free, standard, professional, ultra, and enterprise tiers.

---

## 2. Existing Codebase Audit & Reuse Strategy

### 2.1 Backend Existing Capabilities to Reuse
- **`PersonalizedFeedService` (`app/Services/Workspace/`):** Already builds structured sections (`for_you`, `following`, `breaking`, `trending`, `companies`, `technologies`, `industries`, `saved`, `recommended_research`). We will extend it to support stream pagination, category filtering, multi-factor sorting, and rich card flags.
- **`WorkspaceController` (`app/Http/Controllers/Api/V1/Subscriber/`):** Handles `/feed`, `/interact`, `/reading-history`, `/preferences`. We will add `/overview` and `/following` endpoints.
- **`CompanyController` & `ExecutiveController`:** Already handle `/companies/{id}/follow` and `/executives/{id}/follow`.
- **`WatchlistService` & `WatchlistController`:** Handles multi-entity watchlists and entity relationships.
- **`IntelligenceMonitorService` & `MonitorController`:** Handles customized entity and topic monitors.
- **`PersonalLibraryService` & `PersonalLibraryController`:** Already implements full saved article, publication, and collection management (`/library`, `/collections`).
- **`SocialTrendController` & `TrendIntelligenceService`:** Provides trend scoring and velocity records.
- **`SearchController`:** Global search across articles, companies, executives, trends.

### 2.2 Frontend Existing Capabilities to Reuse
- **Components:** `Sidebar.vue`, `AppHeader.vue`, `RightDiscoveryPanel.vue`, `Card.vue`, `Badge.vue`, `Button.vue`, `Modal.vue`, `Skeleton.vue`, `StoryClusterCard.vue`.
- **Stores:** `useAuthStore` (token, user profile, account type).
- **Services:** `workspace.service.ts`, `feed.service.ts`, `categories.service.ts`, `search.service.ts`.
- **Design Tokens:** `nexo-bg`, `nexo-card`, `nexo-border`, `nexo-text-primary`, `nexo-text-secondary`, `nexo-text-muted`, `nexo-accent-cyan`, `nexo-accent-gold`.

---

## 3. Detailed Work Breakdown & Implementation Steps

### Phase 8.1 — Backend API Enhancements & New Endpoints
1. **Extend `PersonalizedFeedService`:**
   - Add `getFeedStream(User $user, array $filters = []): array` supporting:
     - `category`: Matches dynamic taxonomy slug or article topic.
     - `sort`: `relevance` (default score), `latest` (published_at desc), `trending` (trend velocity / breaking), `credible` (source credibility desc), `discussed` (interactions count desc).
     - `q`: Search keyword filter.
     - `page` & `per_page`: Pagination metadata (`current_page`, `last_page`, `total`).
     - Card attributes: `is_saved` (boolean), `is_following` (boolean), `reading_time_minutes` (calculated), `source_count`, `entities`.
   - Preserve 100% backward compatibility for existing `getPersonalizedFeed` callers.
2. **Add Subscriber Overview Endpoint (`GET /api/v1/workspace/overview`):**
   - Returns real statistics: `followed_entities_count`, `active_watchlists_count`, `active_monitors_count`, `saved_intelligence_count`, `unread_notifications_count`, `recent_research_count`.
   - Returns personalized briefing greeting, followed company updates, top trending signals, and recent briefings.
3. **Add Following Management Endpoints (`/api/v1/workspace/following`):**
   - `GET /api/v1/workspace/following`: Returns all entities followed by the user (companies, executives, technologies, topics).
   - `POST /api/v1/workspace/following/toggle`: Unified toggle follow endpoint accepting `entity_type` and `entity_id` / `entity_slug`.
   - `GET /api/v1/workspace/following/suggestions`: Recommended entities to follow.
   - `GET /api/v1/workspace/following/feed`: Dedicated feed of developments mentioning followed entities.
4. **Enhance Search Controller (`GET /api/v1/search`):**
   - Include matching `technologies` and executive `reports` (publications) in search results.
5. **Enhance Trends Controller (`GET /api/v1/trends`):**
   - Support `time_window` query parameter (`24h`, `7d`, `30d`).

### Phase 8.2 — Backend Automated Test Suite
- Create `tests/Feature/PersonalizedFeedAndNavigationTest.php`:
  1. `test_authenticated_user_can_fetch_personalized_feed_stream`: Verifies authenticated feed returns items with relevance explanation and card attributes.
  2. `test_unauthenticated_user_cannot_access_subscriber_feed`: Assert HTTP 401.
  3. `test_feed_filters_by_category`: Querying `?category=semiconductors` returns matching articles.
  4. `test_feed_sorts_by_latest_and_credibility`: Tests sort orders affect returned results.
  5. `test_subscriber_overview_returns_real_metrics`: Confirms accurate metrics counts from database.
  6. `test_following_management_endpoints`: Follow, unfollow, suggestions, and followed feed.
  7. `test_feed_card_interaction_recording`: Recording save/view/follow updates interactions table.
  8. `test_search_endpoint_returns_multi_entity_results`: Verifies articles, companies, executives, technologies, reports.
  9. `test_trends_endpoint_supports_time_window_filtering`: Tests `24h` vs `7d` vs `30d`.

### Phase 8.3 — Frontend Authenticated Shell & Navigation
1. **Refine `Sidebar.vue`:**
   - Update left navigation according to Section 4 specifications:
     - Brand: Nexovant — Intelligence Beyond Headlines.
     - Primary navigation: Overview (`/dashboard`), For You (`/feed`), Latest Intelligence (`/feed?tab=latest`), Trending (`/trends`), Following (`/following`), Watchlists (`/workspace/watchlists`), Saved Intelligence (`/saved`).
     - Research: Deep Research (`/workspace/research`), Research Projects (`/admin/research/projects` or workspace research), Intelligence Reports (`/reports`).
     - Workspace: Notifications (`/workspace/notifications`), Organization Workspace (`/organization`).
     - Account: Subscription (`/billing`), Settings (`/workspace/preferences`), Help & Support, Sign Out.
   - Refine mobile bottom navigation and drawer to ensure clean access on narrow viewports without horizontal scroll.
2. **Enhance `AppHeader.vue`:**
   - Add notification bell with real unread count badge.
   - Add account profile dropdown menu displaying user avatar/initials, full name, email, subscription plan, organization badge, profile links, and sign out action.
   - Global search trigger modal.
3. **Build / Refine Right Intelligence Panel (`RightIntelligencePanel.vue`):**
   - Contextual panel on desktop screens displaying:
     - Trending Now (top 3 signals with velocity tags).
     - Most Followed Companies.
     - Emerging Technologies.
     - Recently Updated Stories.
     - Suggested Topics.

### Phase 8.4 — Core Subscriber Views
1. **`PersonalizedFeedView.vue` (`/feed`):**
   - Header: "For You" — "Intelligence shaped around the companies, technologies and developments you follow."
   - Dynamic category pills fetched from `categoryService` (All, Artificial Intelligence, Semiconductors, Cybersecurity, Cloud, Software, Startups, Telecom, Consumer Tech, Emerging Tech, etc.).
   - Sort dropdown: Most Relevant, Latest, Trending, Most Credible, Most Discussed.
   - Consolidated story cards with verification status badges, source count, relevance indicator, reading time, and direct interactive buttons (Save, Follow, Share, Dismiss).
   - Empty, loading skeleton, and error recovery states.
2. **`FollowingView.vue` (`/following`):**
   - Search bar for entities.
   - Filter tabs: All, Companies, Executives, Technologies, Topics.
   - Grid of followed entities with unfollow button and recent stories count.
   - Suggested entities section with one-click follow.
   - Tab to view "Followed Activity Stream".
3. **Refine `DashboardView.vue` (`/dashboard`):**
   - Connect to `workspaceService.getOverview()` for real metrics (no static numbers).
   - Display personalized feed preview, trending signals, followed company updates, and quick saved shortcuts.
4. **Refine `StoryDetailView.vue` (`/news/:slug`):**
   - Clear visual structure: What happened, Independently supported facts, Disputed / developing claims, Source attribution table, Related companies, Related executives, Timeline.
   - Integrated action bar: Save to library, Follow related entity, Share link, Open primary source, Start research.
5. **Refine `TrendsView.vue` (`/trends`):**
   - Time window filter pills (`24h`, `7d`, `30d`).
   - Cards showing growth velocity, category, source diversity score, and related entities.
6. **Refine `SearchResultsView.vue` (`/search`):**
   - Multi-tab results: All, Stories, Companies, Executives, Technologies, Reports.
   - Distinct callout for Grounded RAG AI Research query option.
7. **Ensure `SavedIntelligenceView.vue` / `/saved` Route:**
   - Seamless routing to personal library with collection tabs and private notes.

### Phase 8.5 — Verification & Reports
1. Run backend tests: `php artisan test tests/Feature/PersonalizedFeedAndNavigationTest.php`.
2. Run full regression test suite: `php artisan test` (116+ tests).
3. Validate frontend build: `npm run build` (0 TypeScript errors).
4. Run real browser E2E test scenarios A through F via browser subagent.
5. Compile deliverables:
   - `STAGE_8_DESIGN_SYSTEM_AUDIT.md`
   - `STAGE_8_API_INTEGRATION_REPORT.md`
   - `STAGE_8_BROWSER_E2E_REPORT.md`
   - `STAGE_8_COMPLETION_REPORT.md`
