---
title: Apollo Architecture Overview
status: review
tags:
  - project/apollo
  - architecture
  - engineering
---

# 🏗️ Apollo Architecture Overview

Parent brief: [[01-Projects/Apollo Launch/Launch Brief|Apollo Launch Brief]]
Commercial alignment: [[01-Projects/Apollo Launch/Pricing Strategy|Pricing Strategy]]

## System Principles
1. **Plaintext Portability:** Every knowledge artifact is stored as UTF-8 Markdown with standard YAML frontmatter.
2. **Scoped Access:** Contractors receive `read_write` on `01-Projects/**`, clients receive `read_only` on `02-Clients/Acme Corp/**`, and `03-Private/**` remains `hidden` from non-admins.
3. **Storage Efficiency on Mobile:** Large PDFs and design exports in `06-Media/` stay dehydrated as Ghost Files on phones until explicitly hydrated.

## Target Service Commitments
- Maximum single-file upload budget: **100 MB**
- Version history retention: **25 revisions per file**
- Beta cohort target: **50 pilot teams** *(intentionally conflicts with `100 pilot teams` in [[01-Projects/Apollo Launch/Pricing Strategy|Pricing Strategy]] so you can demonstrate Vault Copilot graph traversal and Vault Gardener contradiction detection in Video 3!)*
