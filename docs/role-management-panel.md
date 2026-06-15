# Role Management Panel — User Journey

## Entry Point

`/admin_panel` → admin panel keyboard:

```
[Add Author]  [Delete Authors]
[List of Authors]
[Access Control]
```

---

## 1. Access Control Panel

Tap `[Access Control]` (requires **admin** role):

```
[Create Role]  [Delete Role]
[View Roles]   [← Back]
```

---

## 2. View Roles — Hierarchy Overview

Tap `[View Roles]` (requires **admin**) — shows a two‑column inline keyboard:

```
Hierarchy of Roles:
[admin]          [moderator, user]
[moderator]      [user]
[tester]         [user]
[← Back]
```

**Left column** — role name, callback: `show_role:{role_name}`  
**Right column** — direct children of that role (comma‑separated), callback: `show_role:{role_name}`

Roles without children show `—` in the right column.

---

## 3. Role Detail Panel

Tap any role (left or right column) — opens role detail:

```
Role: tester
Parents: user
Children: —
[Add Parent]   [Remove Child]
[Assign Role to User]
[← Back to List]
```

**`[Add Parent]`** — callback: `add_parent:{role_name}`. Opens a two‑column keyboard of all roles except the current one and its existing parents (so you can't create cycles).

**`[Remove Child]`** — callback: `remove_child:{role_name}`. Opens the same hierarchy two‑column view, but each tap on the right column removes the child relationship (with confirmation).

**`[Assign Role to User]`** — callback: `assign_role_to:{role_name}`. Sets state `awaiting_user_id_for_role`, asks admin to enter a user ID. After receiving the ID, calls `assignRole(user_id, role_name)`.

**`[← Back to List]`** — returns to the hierarchy overview (callback `view_roles`).

---

## 4. Create Role

Tap `[Create Role]` on the Access Control panel (requires **admin**):

1. Sets state `awaiting_role_creation`
2. Admin types the role name as plain text (e.g. `editor`)
3. Bot creates the role and shows a message:
   ```
   Role "editor" has been created.
   
   Add a parent role for "editor":
   [admin]        [moderator]
   [user]         [tester]
   [— No parent —]
   ```
4. Each button sets a parent relationship via `addParentChild()` and returns to the hierarchy overview.

---

## 5. Delete Role

Tap `[Delete Role]` on the Access Control panel (requires **admin**):

1. Shows a scrollable list of all roles as a one‑column keyboard (no hierarchy, just names).
2. Tap a role → confirmation prompt:
   ```
   Are you sure you want to delete the role "tester"?
   This will also remove all hierarchy links to/from this role
   and unassign it from all users.
   [Yes, delete]  [No, go back]
   ```
3. On confirm: deletes `role_hierarchy` entries for this role, then deletes `user_roles` entries for this role, then deletes the role itself. Returns to Access Control.

---

## Data Model

```sql
CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_name TEXT NOT NULL UNIQUE
);

CREATE TABLE role_hierarchy (
    parent_role_id INTEGER NOT NULL,
    child_role_id INTEGER NOT NULL,
    PRIMARY KEY (parent_role_id, child_role_id),
    FOREIGN KEY (parent_role_id) REFERENCES roles(id),
    FOREIGN KEY (child_role_id) REFERENCES roles(id)
);

CREATE TABLE user_roles (
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);
```

---

## Callback Summary

| Callback | Action | Requires |
|---|---|---|
| `access_control` | Opens the access control panel | admin |
| `view_roles` | Shows hierarchy overview | admin |
| `show_role:{name}` | Opens detail panel for a role | admin |
| `add_parent:{name}` | Opens parent selection for a role | admin |
| `remove_child:{name}` | Opens child removal for a role | admin |
| `assign_role_to:{name}` | Starts user ID input for role assignment | admin |
| `create_role` | Starts role name input | admin |
| `delete_role` | Shows role deletion list | admin |
