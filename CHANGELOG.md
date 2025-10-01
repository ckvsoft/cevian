## [0.8.0](https://github.com/ckvsoft/cevian/compare/v0.0.0...v0.8.0) (2025-10-01)

### ✨ Features
* **Schema**: Full implementation of Nested Set RBAC model (v0.8.0)
* **Migration**: Apply 0.8.0 database update for full RBAC
* **RbacViews**: Add roles_table_snippet.php for AJAX role listing
* **RbacViews**: Add permissions_manage.php for permission definitions
* **RbacViews**: Add permissions.php partial for role assignment
* **RbacViews**: Add editrole.php view for role details
* **RbacIndexView**: Introduce Role Creation Form and AJAX List
* **View/User**: Create user list view snippet
* Add username field to user creation form
* **MenuModel**: Implement custom database error handling on creation
* Refactor AJAX form handling for reusability and add sequential save logic
* **Input**: Extend Input class for full JSON payload handling with _handle_input($all)
* Handle string error responses from User_Model in Controller
* **ACL**: Centralize permission definition management in ACL class
* **DB**: Add DbExpr class for raw SQL expressions
* **Input**: Add JSON POST support to Input class
* Add modulmanager and updater

### 🐞 Bug Fixes
* **UserModel**: Include username in userList query
* **View/User**: Wrap user form in generic AJAX container
* **View/Menu**: Wrap menu form in generic AJAX container
* **ACL**: Enforce superuser security with session hash and auto-create missing permissions
* Enhance database error handling across all query methods
* Handling for json post
* Unify array syntax to short [] notation
* Send full directory path correctly for image backups
* **Framework updater**: Ensure migrations run transaction-safe with JSON config and version tracking
* **Backup_Model**: Refactor to fully use Database class and prepared statements
* Fix: .gitattributes

### 🧹 Code Refactoring
* **RbacModel**: Delegate core RBAC logic to ACL component
* **RbacController**: Implement full CRUD for Roles and Permissions
* **ACL**: Massive cleanup, feature additions, and type-hinting
* **MenuController**: Delegate rendering and apply action styling
* **UserController**: Delegate user list rendering to view snippet
* **UserModel**: Ensure returned ID is explicitly cast to integer
* **ACL**: Modernize, standardize, and introduce effective role permission resolution
* Use 'data-list' attribute for menu list container
* **Input**: Enhance input handling to support JSON payloads and clarify docblocks
* **Rbac**: Delegate permission definition management to ACL
* Standardize database error handling in User_Model

### 📖 Documentation
* **RBAC**: Add documentation for Role Inheritance Logic

### ⚙️ Chore
* chore(release): v0.7.0 [skip ci]
* **RbacViews**: Refactor editpermission.php to use $this->perm
* chore: update README.md
* chore: add .gitattributes
* ignore updater config.json

### 🎨 Style
* **View/Menu**: Wrap menu list in fieldset with legend
* **View/Menu**: Cleanup menu table header alignment
* **View/User**: Add legend to user list fieldset
* **View/User**: Wrap user list in fieldset for visual separation
* Add dedicated CSS for small action buttons and table alignment