---
title: "Interactive Livewire Vault Portals"
description: "Publish non-E2EE Obsidian vaults or subfolders as Livewire 4 web portals with 4 layouts, 5 themes, Portal AI, and /llms.txt."
tags:
  - portals
  - publishing
  - livewire
  - themes
  - llms-txt
---

# 🌐 Interactive Livewire 4 Vault Portals

Publish any non-E2EE team vault—or a single subfolder via `root_path`—as an interactive web portal at `/p/{slug}` with zero static site generators or CI build pipelines.

---

## 🎨 4 Layouts × 5 Color Themes

Mix any structural layout with any color palette in **Portals → Create / Edit Portal**:

- **Layouts:** `docs` (Docs), `bento` (Bento), `dashboard` (Dashboard), `minimal` (Minimal)
- **Color Themes:** `obsidian-noir` (Obsidian Noir), `slate-luxe` (Slate Luxe), `midnight-emerald` (Midnight Emerald), `paper-craft` (Paper Craft), `amber-gold` (Amber Gold)

---

## 🔒 Access Control & Publishing Scope

- **Public Portals:** Accessible to anyone at `/p/{slug}`. *(Note: Custom domain mapping is planned on the roadmap.)*
- **Team-Restricted Portals:** Toggle `is_public` off to return `404` to non-members.
- **Password Protection:** Optional bcrypt password gate (min 8 characters, rate-limited to 5 attempts/min, 2-hour session unlock grant).
- **Subfolder Scoping (`root_path`):** Publish only `Public-Docs/` while keeping the rest of your vault internal.

---

## ⚡ Interactive Features, Portal AI & `/llms.txt`

- **Markdown & Navigation:** CommonMark rendering with YAML frontmatter, TOC anchors, Obsidian callouts, `[[wikilink]]` hover popovers, backlinks, tag filtering, and `Cmd+K` search.
- **Interactive Graph View:** Force-directed 2D knowledge graph directly inside the portal.
- **Ask This Portal (AI Drawer):** Optional rate-limited Q&A assistant (10 req/min per IP + configurable daily question limit, default 100/day) grounded in your published portal notes.
- **Automatic `/llms.txt`:** Every public non-E2EE portal serves `/p/{slug}/llms.txt` so AI assistants and documentation crawlers can read your published docs cleanly.

Next: Explore [[05-AI-Copilot/Vault Copilot & Vector RAG Server|Vault AI Suite, Hybrid Search & Local E2EE AI]].
