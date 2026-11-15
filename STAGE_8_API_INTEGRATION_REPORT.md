# NEXOVANT — STAGE 8 API INTEGRATION REPORT
## Personalized Intelligence Feed, Following System & Authenticated Navigation APIs

**Project:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 8  
**Repository:** `nexovant-backend` and `nexovant-frontend`  
**Date:** November 16, 2026  
**Environment:** Local Development / Antigravity Engineering Rig  
**Validation Suite:** PHPUnit / Pest Feature Tests (`Tests\Feature\PersonalizedFeedAndNavigationTest`)

---

## 1. Executive Summary

This report documents the architectural design, endpoint specifications, payload schemas, and automated test coverage for the **Personalized Intelligence Feed, Authenticated Workspace Navigation, Following System, and Discovery APIs** implemented during Stage 8.

All endpoints adhere strictly to:
- Sanctum token authentication (`auth:sanctum`).
- Tenant and subscriber isolation.
- RESTful standards with consistent JSON responses (`success: true`, `data`, `meta`).
- Real database records with zero hardcoded mocks or fabricated metrics.
- Complete backward compatibility with Phase 1–7F endpoints.

---

## 2. API Endpoint Matrix

| Method | Endpoint | Description | Auth Required | Controller & Action |
|---|---|---|---|---|
| `GET` | `/api/v1/workspace/overview` | Returns executive subscriber briefing metrics, followed updates, and shortcuts | Yes | `WorkspaceController@overview` |
| `GET` | `/api/v1/workspace/feed` | Returns paginated intelligence stream with 5-criteria sorting and category filtering | Yes | `WorkspaceController@feed` |
| `GET` | `/api/v1/workspace/following` | Returns list of all followed entities (companies, executives, technologies, topics) | Yes | `WorkspaceController@getFollowing` |
| `POST` | `/api/v1/workspace/following/toggle` | Toggles follow/unfollow for any entity type | Yes | `WorkspaceController@toggleFollow` |
| `GET` | `/api/v1/workspace/following/suggestions` | Returns suggested companies, executives, technologies, and topics | Yes | `WorkspaceController@getFollowingSuggestions` |
| `GET` | `/api/v1/workspace/following/feed` | Returns dedicated activity stream for followed entities only | Yes | `WorkspaceController@getFollowingFeed` |
| `GET` | `/api/v1/search` | Multi-entity search across articles, companies, executives, technologies, reports | Optional | `SearchController@search` |
| `GET` | `/api/v1/trends` | Social trends with time-window filtering (`24h`, `7d`, `30d`) | Optional | `SocialTrendController@index` |

---

## 3. Detailed Endpoint Specifications

### 3.1 Subscriber Workspace Overview (`GET /api/v1/workspace/overview`)

#### Request
```http
GET /api/v1/workspace/overview HTTP/1.1
Host: 127.0.0.1:8000
Authorization: Bearer <token>
Accept: application/json
```

#### Response (`200 OK`)
```json
{
  "success": true,
  "overview": {
    "greeting": "Morning Intelligence Briefing",
    "user_profile": {
      "id": "01a1e098-1f80-706d-a624-6d58cbf858d5",
      "name": "Dr. Elena Rostova",
      "email": "subscriber@nexovant.test",
      "account_type": "individual",
      "plan": "Executive Tier"
    },
    "metrics": {
      "followed_entities": 8,
      "active_watchlists": 2,
      "active_monitors": 3,
      "saved_intelligence": 12,
      "unread_notifications": 1,
      "recent_research": 5
    },
    "followed_company_updates": [
      {
        "id": "art-001",
        "headline": "ASML Ships Second High-NA EUV Machine to Leading Foundry",
        "slug": "asml-ships-second-high-na-euv",
        "category": "Semiconductors",
        "verification_status": "verified",
        "published_at": "2026-11-15T18:00:00Z"
      }
    ],
    "trending_stories": [
      {
        "id": "tr-001",
        "title": "High-NA Lithography Supply Constraints",
        "velocity_score": 94,
        "cross_platform_count": 3
      }
    ],
    "recent_publications": [],
    "saved_shortcuts": []
  }
}
```

---

### 3.2 Personalized Intelligence Feed Stream (`GET /api/v1/workspace/feed`)

#### Query Parameters
- `page` (integer, default: 1): Page number.
- `per_page` (integer, default: 10): Items per page.
- `sort` (string, enum: `relevance`, `latest`, `trending`, `credible`, `discussed`, default: `relevance`): Sort criterion.
- `category` (string, optional): Category slug filter (e.g. `semiconductors`, `enterprise-ai`).
- `search` (string, optional): Keyword search within feed.

#### Response (`200 OK`)
```json
{
  "success": true,
  "data": [
    {
      "id": "01a1e07b-89ab-7c01-8319-2cb83e371301",
      "headline": "NVIDIA Board Approves $15B Custom Silicon Foundry Expansion in EMEA",
      "summary": "NVIDIA has committed $15 billion toward long-term semiconductor packaging agreements across Germany and France.",
      "primary_image": null,
      "category": "Enterprise AI",
      "published_at": "2026-11-15T19:00:00Z",
      "source": {
        "id": "src-reuters",
        "name": "Reuters Technology Wire",
        "credibility_rating": 95
      },
      "source_count": 3,
      "entities": [
        "NVIDIA Corporation",
        "ASML Holding NV",
        "Jensen Huang"
      ],
      "verification_status": "verified",
      "is_breaking": false,
      "is_trending": true,
      "relevance_score": 96,
      "reading_time_minutes": 4,
      "personalization_explanation": "Recommended because you track NVIDIA Corporation and Semiconductors.",
      "is_saved": false,
      "is_following": true
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 28
  }
}
```

---

### 3.3 Following Management (`GET` & `POST /api/v1/workspace/following`)

#### Following List (`GET /api/v1/workspace/following`)
Returns all companies, executives, technologies, and topics the subscriber follows:
```json
{
  "success": true,
  "data": [
    {
      "id": "01a1d9b5-424d-7169-a042-caf3d84c8d58",
      "type": "company",
      "name": "NVIDIA Corporation",
      "ticker": "NVDA",
      "category": "Semiconductors",
      "stories_count": 14,
      "followed_at": "2026-11-14T10:00:00Z"
    },
    {
      "id": "high-na-euv",
      "type": "technology",
      "name": "High-NA EUV Lithography",
      "category": "Semiconductors",
      "stories_count": 6,
      "followed_at": "2026-11-15T12:00:00Z"
    }
  ]
}
```

#### Toggle Follow (`POST /api/v1/workspace/following/toggle`)
Request Body:
```json
{
  "entity_type": "company",
  "entity_id": "asml-holding",
  "name": "ASML Holding NV"
}
```
Response:
```json
{
  "success": true,
  "message": "Entity followed successfully.",
  "is_following": true,
  "entity_type": "company",
  "entity_id": "asml-holding"
}
```

---

### 3.4 Multi-Entity Search (`GET /api/v1/search`)

Includes newly added `technologies` and `reports` (from `Publication` model):
```json
{
  "articles": [...],
  "companies": [...],
  "executives": [...],
  "technologies": [
    {
      "id": "01a1e0...",
      "name": "High-NA EUV Lithography",
      "slug": "high-na-euv",
      "category": "Semiconductors",
      "description": "Extreme ultraviolet photolithography using numerical aperture of 0.55."
    }
  ],
  "reports": [
    {
      "id": "01a1e0...",
      "title": "Executive Dossier: High-NA EUV Commercialization",
      "publication_type": "executive_dossier",
      "executive_summary": "Comprehensive briefing on supply chain bottlenecks and yield optimization."
    }
  ],
  "trends": [...]
}
```

---

### 3.5 Trending Signals with Time Windows (`GET /api/v1/trends`)

#### Query Parameter
- `time_window` (enum: `24h`, `7d`, `30d`, default: `24h`)

```json
{
  "data": [
    {
      "id": "01a1e0...",
      "title": "High-NA EUV Commercial Packaging Challenges",
      "composite_trend_score": 95,
      "status": "rapidly_accelerating",
      "cross_platform_count": 3,
      "time_window": "24h"
    }
  ]
}
```

---

## 4. Automated Test Verification Results

All 9 feature tests in `Tests\Feature\PersonalizedFeedAndNavigationTest` executed cleanly against SQLite in-memory database:

```
PASS  Tests\Feature\PersonalizedFeedAndNavigationTest
✓ unauthenticated request to feed stream is rejected (0.12s)
✓ authenticated subscriber can retrieve feed stream with pagination (0.42s)
✓ feed stream category filtering works correctly (0.38s)
✓ feed stream sorting works across relevance latest and credible (0.44s)
✓ subscriber can retrieve overview dashboard metrics and updates (0.41s)
✓ subscriber can list followed entities (0.39s)
✓ subscriber can toggle follow unfollow on company and technology (0.52s)
✓ subscriber can retrieve following suggestions and dedicated activity feed (0.48s)
✓ search endpoint returns multi entity results including technologies and reports (0.46s)

Tests:    9 passed (72 assertions)
Duration: 4.03s
```

Full platform regression suite across 16 test files: **125 passed (903 assertions)** in 126.36s with zero regressions.
