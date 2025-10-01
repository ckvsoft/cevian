# 🇬🇧 Role Management for Administrators (PHP Framework)

This framework uses a logical top-down inheritance system. You define roles from the weakest to the strongest permission level. Each new role accumulates the rights of the previous stage.

## 1. The Logic: Cumulative Rights (Bottom-Up Creation)

The role that inherits from another automatically becomes more powerful since it retains the inherited rights and receives additional exclusive rights. You create roles from the base to the top.

| Logical Status         | Role          | UI Action (Select Parent Role)              | Cumulative Rights |
|------------------------|---------------|---------------------------------------------|-------------------|
| Base (0% rights)       | Guest         | -- Create as Main Role (Root) --            | Read Access (Public) |
| Standard (20% rights)  | Member        | Select: Guest                               | Read Access (Public) + Read Access (Private) |
| Medium (70% rights)    | Editor        | Select: Member                              | All rights from Member + Write & Edit rights |
| Top-Level (100% rights)| Administrator | Select: Editor                              | ALL rights from Editor/Member/Guest + System Management |

## 2. Step-by-Step Role Creation with SVGs

| Step | Action Description | Visual Hint |
|------|---------------------|-------------|
| 1. Base Role (Guest) | Create the lowest role with the fewest rights. In the 'Parent Role' field, choose -- ROOT -- because this role should not inherit anything. | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="8" y1="14" x2="16" y2="14"/></svg> |
| 2. Next Role (Member) | Create the Member role. In the 'Parent Role' field, select Guest. Assignment: Give the Member role additional rights (e.g., commenting). | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="12" x2="16" y2="12"/><polyline points="12 8 8 12 12 16 16 12 12 8"/><polyline points="8 12 12 8 16 12"/></svg> |
| 3. Strongest Role (Administrator) | Create the Administrator role. In the 'Parent Role' field, select Editor. Assignment: Give the Administrator role only the final exclusive rights (e.g., user creation/deletion). | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><path d="M16.48 10c.36-.6.52-1.3.52-2 0-1.85-1.1-3.41-2.6-4.09l-2.4 1.14"/><path d="M7.52 10c-.36-.6-.52-1.3-.52-2 0-1.85-1.1-3.41-2.6-4.09l2.4 1.14"/><path d="M12 5L12 19"/><path d="M5 20h14"/><path d="M5 13l4.5 4.5 5-5 4.5 4.5"/><line x1="12" y1="9" x2="12" y2="15"/><line x1="9" y1="12" x2="15" y2="12"/></svg> |
| Test | Assign a test user the Administrator role. The system should automatically resolve all rights (Administrator + Editor + Member + Guest) for this user. | <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="48" height="48"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> |

