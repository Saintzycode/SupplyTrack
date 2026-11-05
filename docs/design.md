# Design Reference
Do not use or mention the ui-ux-pro-max skill. Use the existing Bootstrap/style.css design system.

Direction **D — Flat Design**, applied to the school-supply inventory app.
The warm school brand (direction C) was replaced on 2026-11-04 at the user's request.

- **Source of truth for styling:** `assets/css/style.css` (tokens + components).
- **Framework:** Bootstrap 5.3.3 (CDN), Bootstrap Icons 1.11.3 (CDN), Inter + JetBrains Mono
  (Google Fonts), Chart.js (CDN, dashboard only). No build step, no Sass.
- **What the redesign was reacting to:** the previous look read as a generic admin template — cool
  grey surfaces, default blue, rounded Bootstrap cards, no product identity. Direction C (warm
  paper + navy/mustard) was an intermediate step; direction D replaces it wholesale.
- Last verified against the code: 2026-11-04.

---

## 1. Design tokens

All tokens are CSS variables on `:root`. Change them here, never in Bootstrap.

### Pipeline (2026-11-04)

The app uses a **Flat Design** system chosen 2026-11-04. Flat means: no shadows, no gradients, no
3D — clean surfaces, bold colour, colour/opacity shifts for hover.

| Element | We use | Notes |
|---|---|---|
| Style | Flat Design | No shadows, no gradients |
| Primary | `--navy: #334155` (slate) | Sidebar, chart, links, focus ring |
| Secondary | `--navy-2: #475569` | Hover / sidebar user card |
| Accent/CTA | `--accent: #059669` (stock green) | Primary buttons, active nav |
| Background | `--bg: #F8FAFC` | Cool off-white |
| Foreground | `--text: #0F172A` | Body text |
| Border | `--border: #E6E8EA` | Cards, inputs |
| Typography | Inter (body) / JetBrains Mono (data) — exactly 2 fonts, no other font loaded | JetBrains Mono for SKUs, quantities, money |
| Radius | 8px | Down from 14px |
| Effects | 150–200ms colour/opacity shift only | No lift, no shadow |
| 375 / 768 / 1024 / 1440 responsive | Already present at 1024/768/1440/390 | Verified in CSS |

### Colour

| Token | Hex | Used for |
|---|---|---|
| `--bg` | `#F8FAFC` | Page background, table header strip, filter bar |
| `--surface` | `#FFFFFF` | Cards, top bar |
| `--border` | `#E6E8EA` | Card/table/input borders |
| `--border-soft` | `#EDEFF2` | Inner dividers, table row lines |
| `--text` | `#0F172A` | Body text, headings |
| `--muted` | `#475569` | Secondary text, labels, table headers |
| `--muted-2` | `#64748B` | Placeholders, search icon |
| `--navy` | `#334155` | Sidebar, chart bars, links, focus ring, active pagination |
| `--navy-2` | `#475569` | Nav hover, sidebar user card, tooltips |
| `--navy-text` | `#CBD5E1` | Inactive nav labels |
| `--accent` | `#059669` | Primary buttons, active nav, tab underline, avatar |
| `--login-navy` | `#213A5C` | Login hero panel, logo mark, sign-in button |
| `--login-gold` | `#F4B400` | Yellow sun circle in the login hero |
| `--login-paper` | `#FBFBF8` | Warm off-white form panel on the login page |

### Chart colours (full, flat, solid — one per category)

| Token | Hex | Used for |
|---|---|---|
| `--chart-1` | `#059669` | Dashboard bar chart, 1st category |
| `--chart-2` | `#2563EB` | Dashboard bar chart, 2nd category |
| `--chart-3` | `#D97706` | Dashboard bar chart, 3rd category |
| `--chart-4` | `#0E7490` | Dashboard bar chart, 4th category |
| `--chart-5` | `#7C3AED` | Dashboard bar chart, 5th category |
| `--chart-6` | `#DB2777` | Dashboard bar chart, 6th category |
| `--chart-7` | `#475569` | Dashboard bar chart, 7th category |
| `--chart-8` | `#65A30D` | Dashboard bar chart, 8th category |

### Status colours

| Status | Strong | Soft | Meaning |
|---|---|---|---|
| `--success` | `#059669` | `#E8F5EF` | Normal stock, stock-in |
| `--danger` | `#DC2626` | `#FDECEC` | Low stock, errors |
| `--warning` | `#D97706` | `#FEF3C7` | Stock-out, future-date warning, adjustment |
| accent | `#059669` | `#E8F5EF` | Adjustments, informational |

### Radius, spacing, elevation

| Property | Values in use |
|---|---|
| Radius | `6px` / `8px` cards and inputs · `12px` icon tiles · `999px` pills · `50%` avatar · `16px` login card |
| Padding | `11–16px` compact · `18–20px` card head/body · `22px` page head gap · `28–32px` topbar/content |
| Gaps | `3px` nav items · `10–12px` icon+text · `14–18px` grid/stack · `22px` sections |
| Elevation | **None — flat design.** Separation comes from 1px borders and `#F8FAFC` page vs `#FFFFFF` cards. Mobile drawer still uses a dark scrim + border |

### Typography

- Headings (`h1`, page titles, card headings): `Inter`.
- Body: `Inter` (Google Fonts), then `"Segoe UI", system-ui`.
- Data (SKU, quantities, money, `.stat-value`, `.table-ui .text-end`): `JetBrains Mono` with
  `font-variant-numeric: tabular-nums`.

### Dark mode

Dark mode is **toggleable in-app** (topbar button: Light / Dark / System) and also **automatic via OS preference** (`prefers-color-scheme: dark`) when set to System — choice persists in `localStorage` as `theme`, applied before paint. Tokens flip: `--bg #0B1220`, `--surface #111A2E`, `--text #E6EDF7`, sidebar/pill colors invert. The login page stays **light-only** (no toggle there, always paper-white + navy hero). The dashboard chart reads the flipped tokens too.

| Role | Size / weight |
|---|---|
| Page title (`.page-head h1`) | 1.65rem / 700, sans serif, `-.02em` |
| Page subtitle | .85rem / 400, `--muted` |
| Card heading (`.card-head h2`) | .9rem / 700 |
| Stat value (`.stat-value`) | 1.875rem / 700, JetBrains Mono, tabular numerals |
| Stat / field label | .74rem uppercase tracked / .8rem / 600 |
| Body, table cell | .82rem |
| Table header | .625rem / 700, uppercase, `.1em` tracking |
| Badge | .7rem / 600 |
| Nav item | .875rem / 500 (active 600) |

---

## 2. Layout shell

```
┌────────────┬──────────────────────────────────────┐
│  sidebar   │  topbar   70px, sticky, white        │
│  240px     ├──────────────────────────────────────┤
│  slate     │  content  padding 30px 32px          │
│  sticky    │  bg #F8FAFC                          │
└────────────┴──────────────────────────────────────┘
```

- Sidebar contents: brand block (green logo tile + name + subtitle) → `MENU` label → nav list →
  user card pinned at the bottom (avatar, name, role, logout icon).
- Topbar: menu toggle, search (340px, submits to the item list), time-only live clock, avatar.

### Menu open / close

One button drives two behaviours, chosen by viewport width (`992px`):

| Viewport | Button does | Body class | Sidebar |
|---|---|---|---|
| `≥ 992px` | Collapses the sidebar to a **72px icon rail** | `nav-collapsed` | Sticky column, width animates 240 → 72 |
| `< 992px` | Opens an **off-canvas drawer** with scrim | `nav-open` | Fixed, slides in from the left |

- In the rail, labels are hidden and reappear as tooltips on hover and on keyboard focus.
- The desktop choice persists in `localStorage` under the key `sidebar` (`"collapsed"` | `"open"`).
- Escape closes the drawer; crossing the breakpoint resets the drawer state.
- The button keeps `aria-expanded` and updates its `aria-label` ("Collapse menu" / "Expand menu").

### Breakpoints

| Width | Behaviour |
|---|---|
| `≤1100px` | Sidebar narrows to 208px, search to 240px, content padding 24px |
| `≤991.98px` | Off-canvas drawer (264px + scrim), topbar 64px, clock hidden, tabs scroll horizontally, login brand panel hidden |
| `≤575.98px` | Content padding 16px, `.page-head` stacks with full-width buttons, chart 260px, tighter card/table padding |

Test at 1440px, 1024px, 768px and 390px.

---

## 3. Component inventory

| Class | What it is | Used in |
|---|---|---|
| `.card-ui` | White panel, 1px border, 8px radius, no shadow; optional `.card-head` / `.card-body` | every list/form page |
| `.page-head` | Inter title + subtitle left, action buttons right (wraps, stacks on phone) | every page |
| `.stat-card` | KPI tile: tinted `.stat-icon`, uppercase `.stat-label`, big JetBrains Mono `.stat-value` | dashboard |
| `.table-ui` | Grey uppercase header, .82rem cells, row hover, no outer margin | all lists/reports |
| `.badge-soft` + `.badge-low` `.badge-normal` `.badge-in` `.badge-out` `.badge-adj` `.badge-inactive` | Full-round status pills | lists, reports, item detail |
| `.row-low` `.row-normal` `.row-in` `.row-out` `.row-adj` | Row wash + 3px inset stripe | reports, inventory |
| `.report-summary` (+ `-title`, `-item`) | Totals strip under a report table, light slate background | reports |
| `.nav-tabs` (customised) | Underline tabs, active = 2px green underline | reports, tabs elsewhere |
| `.login-wrap` `.login-form-panel` `.login-hero` `.stock-card` `.stock-row` `.login-note` | Split login: warm paper form panel (logo + form + lockout note + PHP/black footer) on the left, navy hero with gold sun + headline + sample stock-status card on the right | `login.php` |
| `.future-date-input` / `.future-date-warn` | Amber border + note on a future-dated transaction | stock-in / out / adjustment / distribution |
| `.avatar` | 32px green circle with an initial | sidebar, topbar |
| Bootstrap used as-is | `btn`, `btn-primary` (green), `btn-outline-*`, `form-control`, `form-select`, `form-label`, `alert`, `modal`, `pagination`, `is-invalid`/`invalid-feedback` | everywhere |

Animated on hover: nav items (background + 2px slide + green icon), table rows (cream wash),
buttons and inputs (colour shift only), sidebar width and tooltip. All transitions are disabled under
`prefers-reduced-motion: reduce`.

### Page skeletons in use

1. **List page** — `.page-head` (title + "Add" button) → filter card (search + selects) → `.card-ui`
   + `.table-ui` (10–25 rows) → pagination → empty state inside the card.
2. **Form page** — `.page-head` with a back link → main card with a two-column grid, inline
   `is-invalid` + `invalid-feedback`, action buttons in a side card.
3. **Transaction page** — item picker, quantity, date, remarks in one card, with a read-only
   "current stock" hint.
4. **Dashboard** — 4 `.stat-card` KPIs (each with its own top-border colour via `:has()`) →
   "Stock by category" chart (320px) → "Items at or below reorder point" + "Recent transactions".
   Everything except the chart is server-rendered.
5. **Reports** — tabs → filter row (period preset, date range, item/school/type) → totals strip →
   table → pagination → Export PDF / Export Excel in the `.page-head`.
6. **Login** — paper-white form panel (SchoolSupply logo, Welcome back, username/password, sign-in, lockout note) on the left, navy hero with gold sun, headline and sample stock-status card on the right.

---

## 4. What must not break

These hooks are consumed by JavaScript or PHP. Renaming them breaks behaviour, not just looks.

| Hook | Consumed by | Effect if removed |
|---|---|---|
| `.live-clock` + `data-timezone` | `assets/js/clock.js` | Top-bar time-only clock disappears |
| `[data-sidebar-toggle]` | `assets/js/sidebar-toggle.js` | No collapse, no drawer |
| `[data-sidebar-close]` | `sidebar-toggle.js` | Backdrop stops closing the drawer |
| `body.nav-open` | CSS + JS | Drawer cannot open |
| `body.nav-collapsed` | CSS + JS | Icon rail cannot collapse |
| `localStorage.sidebar` | `sidebar-toggle.js` | Collapse state not remembered |
| `.sidebar-nav .nav-item` | `sidebar-toggle.js` | Nav links stop closing the mobile drawer |
| `#stockByCategory` + `#stockByCategoryData` | `assets/js/dashboard-chart.js` | Dashboard chart disappears |
| CSS vars `--navy` `--accent` `--border` `--muted` `--text` | `dashboard-chart.js` (reads them) | Chart falls back to hardcoded hex |
| `input[type=date][data-future-warn]`, `.future-date-input` | `assets/js/future-date-warn.js` | Future-date warning stops |
| `[data-toggle-password="<input id>"]` + inner `<i>` | `assets/js/password-toggle.js` | Show/hide password breaks |
| `active` on `.nav-item` / `.nav-link` | `includes/header.php` (`$activePage`) | Wrong menu item highlighted |
| `is-invalid` + `invalid-feedback` | server-side validation | Inline field errors stop showing |
| `.badge-low` / `.badge-normal` | stock status rendering | Status pills lose meaning |
| `.table-ui` row classes | report row tinting | Status at a glance lost |
| Bootstrap utilities (`d-flex`, `mb-3`, `col-*`, …) | every page | Layout collapses |

Every page pulls Bootstrap, Bootstrap Icons and Inter / JetBrains Mono from CDN. **Bump `ASSET_VER` in
`config/config.php` after any `style.css` or `*.js` change**, or you will debug a cached file.

Report exports have their own palette in `rep_colors()` (`includes/reports_lib.php`) plus hardcoded
spots in `reports/report_export.php` and the PDF footer default in `includes/pdf.php`.
They do **not** read `style.css` — update them by hand when a token changes.

---

## 5. Accessibility status

Present: `<html lang="en">`, viewport meta, `:focus-visible` ring on every interactive element,
`aria-label` + `title` on each nav item (so the icon rail still has names), `aria-hidden` on decorative
nav icons, `aria-expanded`/`aria-label` maintained on the menu toggle, real `<th>` headers, inline
validation errors paired with fields, Escape closes the drawer, tooltips also appear on keyboard focus,
all motion disabled under `prefers-reduced-motion`.

Known gaps for the next pass:

1. Status is still conveyed by colour in some pills (`Low` vs `Normal`) — text does the work,
   but add an icon.
2. No "skip to content" link.
3. Tables have no `<caption>`.
4. The logout link has only `title`, no `aria-label`.
5. Re-check contrast of `--muted` on `--bg` (`#475569` on `#F8FAFC` ≈ 7.1:1 — passes AA and AAA).

---

## 6. Removed in the redesign

| Gone | Why |
|---|---|
| `Georgia` serif headings | Replaced by Fira Sans (Flat Design) |
| `Inter` | Replaced by Fira Sans / Fira Code |
| All shadows / `:hover` transform lift | Flat Design — no 3D, no complexity |
| Warm paper background `#FBF7F0` | Replaced by cool slate-tinted `#F8FAFC` |
| Mustard accent `#E0A100` | Replaced by stock green `#059669` |
| `--shadow` / `--shadow-lift` | Flat design has no elevation |
| `admin/setup_admin.php` | Deleted for security (one-time page) |

---

## 7. Change checklist

Before changing a screen:

- [ ] Change tokens in §1 first, in `style.css` only.
- [ ] Keep the Bootstrap grid and utility classes — they carry the responsive behaviour.
- [ ] Re-check the hooks in §4 after any markup change.
- [ ] Test at 1440px, 1024px, 768px, 390px, and with the sidebar both open and collapsed.
- [ ] Test as **admin** and as **staff** (staff sees fewer nav items and no admin actions).
- [ ] Run one PDF and one Excel export.
- [ ] Bump `ASSET_VER` in `config/config.php`.
- [ ] Log the change in §8.

## 8. Change log

| Date | Change | By |
|---|---|---|
| 2026-11-04 | Document written from the build that preceded the redesign | — |
| 2026-11-04 | Full restyle to direction C (warm paper, navy + mustard, serif titles) | redesign pass |
| 2026-11-04 | **Direction D — Flat Design** applied: slate `#334155` + stock green `#059669`, Inter / JetBrains Mono, 8px radius, no shadows/gradients, cool `#F8FAFC` background, data tables in JetBrains Mono. | redesign pass |
| 2026-11-04 | **Login redesign** (reference-matched): paper-white form panel + navy hero (`#213A5C`, gold `#F4B400` sun, stat-card example). No "Keep me signed in" checkbox (session-only). | — |
