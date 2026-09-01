# Responsive Pass Changelog

Full-system responsive / mobile compatibility pass. Scope: UI markup + CSS only.
No functional, API, validation, route, or permission changes. No deletions.
Breakpoints follow existing Tailwind utilities (mobile-first): `sm` 640, `md` 768,
`lg` 1024.

Decision model: dense tables use a **contained horizontal scroll + sticky first
column** technique (never card-ification), so no data/columns are ever hidden.
Off-canvas navigations use a **drawer** with overlay backdrop on small screens.

## Breakpoint behavior

| Range (px)  | Behavior                                                       |
|-------------|----------------------------------------------------------------|
| 320–480     | Drawer sidebar, stacked headers/toolbars, scrollable tables    |
| 481–768     | Two-col grids start, name/roles text visible in header         |
| 769–1024    | Full admin layouts, tables keep scroll + sticky first column   |
| 1025+       | Unchanged from the original fixed desktop layout               |

## Shared assets

- `resources/css/app.css`
  - Added `.table-scroll`: `overflow-x:auto` + touch scrolling for dense tables.
  - Added `.ui-sticky-col`: sticky first `th`/`td` with solid backgrounds and a
    subtle right-edge divider (slate-200), so columns stay legible while scrolled.
  - Added off-canvas drawer rules for `#sidebar` (translate off-canvas by default,
    `sidebar-open` slides in, `@media (min-width: 64rem)` pins it visible).
- `resources/js/ui.js`
  - Pagination footer now wraps (`flex-wrap … gap-2`) so Prev/Next/page numbers
    reflow inside 320px viewports.

## Per-page

### Layout shell (`layout.js`)
- Sidebar converted from an always-fixed column to a drawer below `lg`.
- Hamburger toggle in the header (`lg:hidden`), overlay backdrop closes it,
  nav-link taps also close it. `aria-expanded` toggled.
- Main content margin `ml-64` → `lg:ml-64`; header padding `px-6` → `px-4 sm:px-6`;
  content padding `p-6` → `p-4 sm:p-6`.
- Header user name/roles block hidden on small screens (`hidden sm:block`);
  logo block and title truncate (`truncate`) to avoid overflow.

### Auth / login (`login.js`)
- Already `px-4` + `max-w-sm`; added `py-8` so the card never touches viewport
  edges on very short screens. No password-reset page exists in the SPA.

### Dashboard (`dashboard.js`)
- Already responsive: KPI `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`, analytics
  `lg:grid-cols-3`, SVGs sized via `viewBox` + `w-full`. No change required.

### Users (`users.js`)
- Toolbar: search input `w-64` → `w-full sm:w-64`; toolbar is now a stacked flex
  column on mobile.
- Table: wrapper `overflow-hidden` → `table-scroll`; table `min-w-[560px]` with
  `ui-sticky-col` (Name column stays pinned, actions stay reachable).
- Form action bar: `flex-wrap`, reduced mobile padding (`px-4 sm:px-6`) so the
  long submit label reflows instead of overflowing.

### Cadets (`cadets.js`)
- Toolbar: search `w-64` → `w-full sm:w-64`; rank select `max-w-full` guard; the
  toolbar columns are stacked on mobile.
- Table: wrapper `overflow-hidden` → `table-scroll`; table `min-w-[540px]` with
  `ui-sticky-col`.
- Form and Import Excel modal were already responsive (`grid sm:grid-cols-2`,
  `max-h-[90vh]` + internal scroll) — unchanged.

### Recruitment & Ingestion (`recruitment.js`)
- Candidates table: `min-w-full` → `ui-sticky-col` `min-w-[780px]` (Full Name
  column pinned); container already `overflow-x-auto`.
- Channel actions (Call / WhatsApp / Email / Manage): tap targets enlarged to
  `min-h-10` `px-3 py-2` (was `px-2.5 py-1.5`).
- Search input `min-w-[220px] flex-1` → `w-full sm:min-w-[220px] sm:flex-1`.
- Shared `modal()` wrapper now `max-h-[calc(100dvh-2rem)] overflow-y-auto` so
  long modal bodies scroll instead of overflowing the viewport.

### Roles & Permissions (`roles.js`)
- Page header `flex items-end justify-between` → stacked `flex-col` with
  `sm:flex-row`; "New Role" button `w-fit` on narrow screens.
- Roles list: `ui-sticky-col` `min-w-[640px]` (Role column pinned).
- Permission matrix: `ui-sticky-col` `min-w-[720px]` so the Capability column
  stays pinned; removed inner `overflow-hidden` wrapper that would have scoped
  sticky positioning to a non-scrolling box.
- Delete-role modal: `max-h-[calc(100dvh-2rem)] overflow-y-auto`.

### Email Campaigns (`email.js`)
- Recipient delivery-log table clipped in an `overflow-hidden` box → `table-scroll`
  (horizontal scroll inside the campaign modal instead of clipped cells).
- Composer/settings/campaigns-table were already responsive
  (`grid-cols-1 lg:grid-cols-12`, `grid-cols-1 md:grid-cols-2`, `overflow-x-auto`,
  `max-h-[90vh]` modal) — unchanged.

### Profile (`profile.js`)
- Already responsive (`max-w-6xl`, stacked header `flex-col sm:flex-row`,
  responsive info grid) — no change required.

## Verification

- `node --check` on every edited JS module.
- `npm run build` (Vite 8 production build) passes.
- Manual checkpoints (not automatable in this repo):
  - No horizontal scroll on `<body>` at 320/375 px per page.
  - Every action button remains tappable (enlarged where needed).
  - Desktop ≥1024 px renders identically to the pre-pass fixed layout.