---
title: Apollo Launch Brief
status: active
owner: Alex Rivera
target_launch: 2026-12-15
tags:
  - project/apollo
  - launch
  - strategy
---

# 🚀 Apollo Launch Brief

> [!IMPORTANT] Core Objective
> Ship **Apollo v1.0** by **December 15, 2026** with rock-solid offline reliability, clear customer documentation, and strict client/internal permission boundaries.

## Linked Documents
- Technical architecture: [[01-Projects/Apollo Launch/Architecture Overview|Architecture Overview]]
- Commercial model: [[01-Projects/Apollo Launch/Pricing Strategy|Pricing Strategy]]
- Pilot customer: [[02-Clients/Acme Corp/Acme Corp Kickoff|Acme Corp Kickoff]]
- Public help center: [[04-Public-Docs/Welcome to Northstar|Northstar Public Docs]]

## Launch Checklist
- [x] Complete core sync architecture review
- [x] Establish folder-level permissions for internal vs. client notes
- [ ] Finalize public documentation portal (`/p/northstar-docs`)
- [ ] Complete mobile field-testing with Ghost Files enabled
- [ ] Run final security review and verify backup restore drill

## Key Decisions Log
1. **Local-First Markdown:** All internal specs and customer guides live as standard `.md` files in Obsidian.
2. **Zero Silent Overwrites:** Offline field edits reconcile through Synkk's Visual Conflict Sandbox rather than last-write-wins.
3. **Human-Reviewed AI:** External AI assistants connect via MCP with `mcp_propose` scope so every edit lands in our Proposal Inbox first.

## Live Co-Editing Sandbox (Use in Video 2 & Video 3)
- **Desktop note:** Initial draft written on laptop before syncing to mobile.
- **Mobile update:** _(Edit this line from your phone during Video 2 to verify bidirectional sync!)_
- **Conflict / CRDT test line:** Target beta cohort size is 50 pilot teams.
