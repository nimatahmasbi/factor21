# Design System - Pishfactor (21f-2) - UI/UX Pro Max
**Generated via `ui-ux-pro-max-skill` for Invoice & Billing Tool (Fintech)**
**Pattern: Data-Dense + Trust | Style: Minimal Professional + Soft UI Evolution**

## Target
Pishfactor - سامانه پیش‌فاکتور چندشرکتی - Fintech/Invoice SaaS requiring trust, data-density, and premium feel.

## Reasoning (192 Rules)
- **Product Type:** Invoice & Billing Tool / Fintech
- **Recommended Pattern:** Data-Dense Table + Trust Elements (seller/buyer verification, OTP approval badge, audit log)
- **Style Priority:** Minimal Professional (rank 1), Soft UI Evolution (rank 2), Fluent 2 (supplemental)
- **Conversion Focus:** Trust-driven - clear totals, amount-in-words, bank info, daily quote for human touch
- **Anti-Patterns Avoided:** Neon gradients, harsh animations, dark mode for invoices, AI purple/pink gradients

## Colors
- Primary: #1e3a8a (Trust Navy) - headings, buttons, invoice header
- Primary Hover: #1e40af
- Primary Soft: #eff6ff / #dbeafe (backgrounds, badges)
- Secondary: #0f766e (Success/Teal for approved states)
- Accent: #06b6d4 (Cyan for gradients)
- Background: #f8fafc (app bg)
- Panel: #ffffff
- Text: #0f172a (Slate 900) - 4.5:1 contrast
- Muted: #64748b
- Line: #e2e8f0
- Danger: #dc2626, Success: #059669

## Typography
- **Primary:** Vazirmatn (Persian) + Tahoma fallback - authoritative, readable for financial data
- **Display:** Vazirmatn 800 for page heads
- **Per-Section Config (NEW):** header/body/table/notes each have font + size (8-18px) stored in `output_templates.typography_json`
- **Sizes:** header 17px, body 10px, table 8.5px, notes 9.5px (A4 landscape) - responsive scales for A5/A6
- **Google Fonts:** https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap

## Spacing & Radius
- Radius: 16px (base), 10px (small), 20px (large) - consistent cards/dialogs
- Shadows: sm (1px), base (4px), lg (12px) - soft depth, not harsh
- Grid: 4-col KPI on desktop, 2-col on 768px, 1-col on 520px - resilient

## Components
- **Sidebar:** Slate 900 gradient, sticky, 280px, active state white with primary text, hover rgba(255,255,255,.06)
- **KPI Cards:** White, border, top gradient bar on hover, -1px lift
- **Tables:** Faint header #f8fafc, hover #f8fafc, overflow auto with border
- **Invoice Sheet (.a4-sheet):** CSS variables --font-size-* and --font-family-* per section, formal/modern/minimal variants, print-optimized
- **Mobile Handling:** No `zoom` (non-standard, breaks html2canvas); use `transform:scale(.42)` for preview, temporarily `transform:none` during capture
- **Dialogs:** Rounded 16px, backdrop blur, sticky preview-tools

## Key Effects
- Soft shadows + 150ms transitions + -1px hover lift
- No harsh animations; `prefers-reduced-motion` respected
- Focus-visible 2px primary outline

## Pre-Delivery Checklist (from skill)
- [x] No emojis as icons (use text/SVG)
- [x] cursor-pointer on all buttons
- [x] 4.5:1 contrast for text
- [x] Focus states visible
- [x] prefers-reduced-motion respected
- [x] Text reflow without clipping at 375px
- [x] Responsive: 375, 768, 1024, 1440 tested (CSS media queries)
- [x] Chips/badges wrap, not clip (designer-checks)

## Stack
HTML + Tailwind-like CSS (no framework) + Vanilla JS + html2canvas + jspdf

## Persistence
MASTER.md is global source; page overrides would be in `design-system/pages/*.md` (not needed for single-page invoice app).

## Changes Applied (v1.4.7)
- Modernized app.css to this system (was 14px radius, #2563eb primary - now trust navy)
- Added per-user template editing (`user-output-*` APIs)
- Added typography_json to output_templates
- Added short_code to quote_shares + /s/{code} route
- Fixed Jalali dates in quotes list, dashboard, public_quote
- Fixed invoice title dynamic (پیش‌فاکتور vs فاکتور)
- Fixed mobile PDF/print via transform handling
- Added typography controls UI for both admin and user
