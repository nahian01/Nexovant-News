# NEXOVANT — STAGE 8 DESIGN SYSTEM AUDIT
## Design Language, Visual Tokens, Typography & Component Standards

**Product:** Nexovant — Intelligence Beyond Headlines  
**Stage:** 8  
**Design Audit Status:** **VALIDATED & ENFORCED**  

---

## 1. Design System Philosophy

Nexovant is positioned as a premium technology and executive intelligence platform, **not a conventional consumer news website**. The design language conveys institutional authority, precision, calm focus, and deep editorial credibility.

All new components, layouts, cards, buttons, modals, and views developed in Stage 8 must strictly conform to these established design tokens and UI conventions.

---

## 2. Core Color Palette & Visual Tokens

| Token Name | Hex Code | Utility Class | Purpose / Usage |
|---|---|---|---|
| **Background Neutral** | `#f8faf9` | `bg-nexo-bg` | Main application canvas and page background. |
| **Card / Surface** | `#ffffff` | `bg-white` / `bg-nexo-card` | Content cards, data containers, and floating modals. |
| **Elevated Surface** | `#f0f4f1` | `bg-nexo-elevated` | Hover states, pill backgrounds, and secondary panels. |
| **Border Neutral** | `#e2e8e4` | `border-nexo-border` | Subtle structural division lines and card borders. |
| **Text Primary** | `#111816` | `text-nexo-text-primary` | Main titles, headlines, and primary data values. |
| **Text Secondary** | `#47554f` | `text-nexo-text-secondary` | Body paragraphs, summaries, and secondary descriptors. |
| **Text Muted** | `#788880` | `text-nexo-text-muted` | Meta timestamps, source labels, and non-active icons. |
| **Accent Emerald Cyan** | `#147a67` | `text-nexo-accent-cyan`, `bg-nexo-accent-cyan` | Brand primary color; buttons, active links, verified tags. |
| **Accent Deep Forest** | `#0d2a23` | `bg-[#0d2a23]` | Executive dark banners, premium briefings, and dark cards. |
| **Accent Gold / Amber** | `#b8860b` | `text-nexo-accent-gold` | Emerging trends, breaking milestones, and financial highlights. |
| **Status Verified** | `#059669` | `emerald-600` / `emerald-50` | Confirmed / verified intelligence signals. |
| **Status Developing** | `#d97706` | `amber-600` / `amber-50` | Developing stories with active source claims. |
| **Status Risk / Disputed**| `#dc2626` | `rose-600` / `rose-50` | Contradicting or disputed intelligence reports. |

---

## 3. Typography Hierarchy

Nexovant utilizes a distinct editorial dual-font system:

1. **Serif Font Family (`font-serif`):**
   - Headings, editorial titles, publication names, and metric callouts (`font-serif text-[21px]`, `text-2xl`, `text-3xl`, `text-4xl`).
   - Represents editorial gravitas and historical depth.
2. **Sans-Serif Font Family (`font-sans`):**
   - Body copy, summaries, navigation items, buttons, form controls, and table rows (`text-xs`, `text-sm`, `text-base`).
   - Prioritizes rapid legibility and clean data scanning.
3. **Monospace Font Family (`font-mono`):**
   - Tickers (`NVDA`, `TSMC`), timestamps, verification hashes, confidence scores, and count badges.
4. **Eyebrows (`eyebrow` class):**
   - Subtitle markers preceding headlines: uppercase, tracking wide (`text-[10px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted`).

---

## 4. Component Standards & Reuse

### 4.1 Card Components (`Card.vue`)
- Border radius: `rounded-2xl` (16px) or `rounded-[24px]` (24px) for featured hero cards.
- Border: `border border-nexo-border/80`.
- Shadows: `shadow-sm` on neutral surfaces; `shadow-executive` on dark premium cards; `hover:shadow-lg hover:border-nexo-accent-cyan/30` on interactive cards.

### 4.2 Status & Category Badges (`Badge.vue`)
- Supported variants:
  - `confirmed` / `verified`: Emerald green with optional live pulsing dot.
  - `reported_reliable`: Cyan blue tint.
  - `developing`: Amber gold tint.
  - `unverified`: Slate neutral.
- Padding: `px-2.5 py-1 text-[10px] font-medium rounded-lg`.

### 4.3 Action Buttons (`Button.vue`)
- `primary`: `bg-nexo-accent-cyan text-white hover:bg-nexo-accent-cyan-hover shadow-sm`.
- `secondary`: `bg-white border border-nexo-border text-nexo-text-primary hover:bg-nexo-bg`.
- `ghost`: Transparent with hover background `hover:bg-nexo-bg text-nexo-text-secondary`.
- `sm`: Height 32px; `md`: Height 40px; `lg`: Height 48px. Minimum touch target on mobile: 44px.

### 4.4 Form Controls & Modals
- Input fields: `rounded-xl border border-nexo-border bg-white px-3.5 py-2.5 text-xs text-nexo-text-primary focus:border-nexo-accent-cyan focus:outline-none focus:ring-2 focus:ring-nexo-accent-cyan/20`.
- Modals: Centered backdrop overlay `bg-black/50 backdrop-blur-sm`, smooth scale transition, accessible `Esc` key handling.

---

## 5. Responsive Breakpoint Standards

- **Mobile (< 768px):** Single column, bottom navigation bar or drawer, full-width touch cards, hidden right discovery panel.
- **Tablet (768px – 1023px):** Two column layout, compact collapsible sidebar or drawer.
- **Desktop (1024px – 1279px):** Three-part structure: Left navigation (252px) + Main content stream.
- **Wide Desktop (>= 1280px / 1440px):** Full three-part layout: Left navigation (252px) + Main content (max 960px) + Right intelligence panel (320px).

---

## 6. Audit Conclusion

All Stage 8 development will reuse existing components (`Card.vue`, `Badge.vue`, `Button.vue`, `Modal.vue`, `Skeleton.vue`, `Sidebar.vue`, `AppHeader.vue`) and match these exact design tokens to ensure visual harmony across the application.
