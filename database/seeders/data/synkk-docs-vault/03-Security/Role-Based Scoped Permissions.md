---
title: "Role-Based Scoped Permissions"
description: "Granular folder-level and path-level access controls for multi-user teams."
tags:
  - permissions
  - security
  - rbac
  - teams
---

# 🛡️ Role-Based Scoped Path Permissions

Obsidian Sync and cloud folders traditionally apply permissions on an "all-or-nothing" basis—either a team member has access to the entire vault, or they have nothing.

Synkk introduces **Path-Aware Access Control Rules**.

---

## 🔑 Permission Levels

Every path in a vault can be assigned one of three granular states:

| Permission | Behavior on Client Devices | Use Case |
| :--- | :--- | :--- |
| **`read_write`** | Two-way synchronization. Edits made locally push to server; remote edits pull automatically. | Active collaborators, project contributors. |
| **`read_only`** | One-way download synchronization. Client cannot push edits or deletes. | Company handbooks, legal policies, template folders. |
| **`hidden`** | Completely invisible. Server manifest excludes path from the device. | Contractor onboarding, executive salaries, HR notes. |

---

## 🎯 How Path Rules Cascade

1. **Default Vault Permission**: Establishes the baseline policy for all notes in the vault (e.g., `read_write`).
2. **User Root Permission**: Overrides the vault baseline for a specific member.
3. **Path-Specific Overrides**: Takes highest precedence for folder trees (e.g. assigning `admin/` as `hidden` for contractors, while granting `read_write` to `projects/client-a/`).

Next: Explore publishing your vault notes as public websites with [[04-Portals/Interactive Livewire Vault Portals|Interactive Livewire Vault Portals]].
