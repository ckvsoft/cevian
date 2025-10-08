## [0.8.3](https://github.com/ckvsoft/cevian/compare/v0.8.2...v0.8.3) (2025-10-07)


### Bug Fixes

* **multilogin:** Correct column name in MultiLoginManager insert ([397e543](https://github.com/ckvsoft/cevian/commit/397e543bd3c264c76b667c9e69254daec7b2ce02))
* **schema:** Enhance SQL structure by adding primary and unique keys inline ([ff03351](https://github.com/ckvsoft/cevian/commit/ff0335187787a2c6d8dd0caaa0def1e22666936a))
* **sql:** Add InnoDB engine and explicit charset/collation to migration 0.7.0 ([6e54f64](https://github.com/ckvsoft/cevian/commit/6e54f641dba49ecaafaf96508837a04ab490e9a6))



## [0.8.2](https://github.com/ckvsoft/cevian/compare/v0.8.0...v0.8.2) (2025-10-06)


### Bug Fixes

* add missing framework name ([807b25f](https://github.com/ckvsoft/cevian/commit/807b25faf3cae329e4a3ff7b3fa0bf6753a17c38))
* cleanup ([219a1ec](https://github.com/ckvsoft/cevian/commit/219a1ec88140f833e91d74884c489e05a0694c94))
* Decode URI segments and refine routing setup ([7fca986](https://github.com/ckvsoft/cevian/commit/7fca986d924f9762a9e641d718929679876906cb))
* missing username ([355f99f](https://github.com/ckvsoft/cevian/commit/355f99f0f5e0c7830e6294d1fdb691a44aa75297))
* next one. hope it works now ([256a855](https://github.com/ckvsoft/cevian/commit/256a855a40678bde8ab9d3f2b768ef69bf5f06c3))
* typo ([aae8368](https://github.com/ckvsoft/cevian/commit/aae83683437bb536511538b31c7faa9afba398cb))
* typo from automatic create ([7414e1c](https://github.com/ckvsoft/cevian/commit/7414e1c52d6fca3a676b01dfcc8f78c27b605eca))
* user_roles ([8a0f12b](https://github.com/ckvsoft/cevian/commit/8a0f12bf6545eb4054827cd6acf749a37581f7c3))


### Features

* **config:** Add default path for public albums ([dbd9a7b](https://github.com/ckvsoft/cevian/commit/dbd9a7b94778be4f29b37e353d2faa5fe982477a))
* **controller:** Implement model file fallback to core modules ([d6a4bae](https://github.com/ckvsoft/cevian/commit/d6a4baeeb8acbe3719423575fe09ccace4527b5a))
* Create Gallery_Model for file system album management ([20102fa](https://github.com/ckvsoft/cevian/commit/20102fa26128c1506f94ada5ad46ed7a4d20b3c0))
* **css:** Add gallery.css for grid layout and media styling ([3e85f8e](https://github.com/ckvsoft/cevian/commit/3e85f8ebb43727eaef47f72e76c7d3494e29b496))
* **css:** Allow explicit module name for loading CSS ([fca0d17](https://github.com/ckvsoft/cevian/commit/fca0d172ccef458d100277a816de56257d8c270c))
* **gallery:** Add view partials for album and media items ([6b37f2b](https://github.com/ckvsoft/cevian/commit/6b37f2bba961b2d1a04d52d24245b812b5ccc7f1))
* **gallery:** Implement gallery index view with pagination and breadcrumbs ([7b7a9ec](https://github.com/ckvsoft/cevian/commit/7b7a9ecc73f9868db6e9ab963102e4f634bbb5f7))
* Implement dot-notation getter for configuration values ([f0395ee](https://github.com/ckvsoft/cevian/commit/f0395ee4af44aba15ba1fae8e35a9abff3884068))
* Introduce Gallery controller for public album viewing ([852fbab](https://github.com/ckvsoft/cevian/commit/852fbabf76c5f69f494033e7cabb45c2aff30a7f))
* Introduce Gallery_Helper to centralize album logic ([ec446c2](https://github.com/ckvsoft/cevian/commit/ec446c2766cf1f334c3d7cbda8c34233948b104a))
* Unify pagination logic for tables and image grids ([c066809](https://github.com/ckvsoft/cevian/commit/c06680932921911629090e5b0950811cc0666a6d))



# [0.8.0](https://github.com/ckvsoft/cevian/compare/v0.6.6...v0.8.0) (2025-10-01)


### Bug Fixes

* .gitattributes ([80bbc41](https://github.com/ckvsoft/cevian/commit/80bbc412b4937d574b535ea15cd4ae88cb91fa39))
* **ACL:** enforce superuser security with session hash and auto-create missing permissions ([d8b7b98](https://github.com/ckvsoft/cevian/commit/d8b7b98f5d8d56cd6ca4892d1882e5a7d03d1663))
* Enhance database error handling across all query methods ([b415955](https://github.com/ckvsoft/cevian/commit/b4159552dd748931426b1c1df42601f1b3123201))
* Framework updater to ensure migrations run transaction-safe with JSON config and version tracking ([aa694be](https://github.com/ckvsoft/cevian/commit/aa694be03346d3910f1d59edf19aa31bbb4eeb94))
* handling for json post ([c83225f](https://github.com/ckvsoft/cevian/commit/c83225fedfddedcbf20e2c0b3b94ba4fb528c496))
* refactor Backup_Model to fully use Database class and prepared statements ([bfbeaa1](https://github.com/ckvsoft/cevian/commit/bfbeaa199fdfdd529f4948d96cb95f15c02c533d))
* send full directory path correctly for image backups ([d987c1f](https://github.com/ckvsoft/cevian/commit/d987c1fd2f7070653449a74b7bd351d5547d28dd))
* unify array syntax to short [] notation ([0795c84](https://github.com/ckvsoft/cevian/commit/0795c84ce7b157abd65933910d48200b81614701))
* **UserModel:** Include username in userList query ([20f40c1](https://github.com/ckvsoft/cevian/commit/20f40c153644fdfd8aead9c9ca0e8985f3593683))
* **View/Menu:** Wrap menu form in generic AJAX container ([937a353](https://github.com/ckvsoft/cevian/commit/937a3538d1768528fb564b63e7190033bc6d3c14))
* **View/User:** Wrap user form in generic AJAX container ([690b603](https://github.com/ckvsoft/cevian/commit/690b6037824ae3b47ec254e758fcd7d0295d8afe))


### Features

* **acl:** Centralize permission definition management in ACL class ([b3f71f5](https://github.com/ckvsoft/cevian/commit/b3f71f5d54d73ff4f32e4f11790cf21e169b0d6c))
* add JSON POST support to Input class ([c075dff](https://github.com/ckvsoft/cevian/commit/c075dffde0d4442a4c3cd2e6aac9db68623f30be))
* add modulmanager and updater ([5367946](https://github.com/ckvsoft/cevian/commit/536794669a5b8bc19245ce2222ecf59a9bda7e93))
* Add username field to user creation form ([e6d242c](https://github.com/ckvsoft/cevian/commit/e6d242cfb858813a5f630b690957e6f09c117725))
* **db:** Add DbExpr class for raw SQL expressions ([49e5e6a](https://github.com/ckvsoft/cevian/commit/49e5e6af281c181a9b47f6ac3577a628668c0979))
* Extend Input class for full JSON payload handling with _handle_input($all) ([0da4323](https://github.com/ckvsoft/cevian/commit/0da432399f798b5b1e7778cb0aed615a8f3bfb4c))
* Handle string error responses from User_Model in Controller ([ad35457](https://github.com/ckvsoft/cevian/commit/ad35457d8f3a420c4c1a912119459ec973bedb27))
* **MenuModel:** Implement custom database error handling on creation ([f6655c3](https://github.com/ckvsoft/cevian/commit/f6655c35acadc34ab535b26232870f55b52c551e))
* **Migration:** Apply 0.8.0 database update for full RBAC ([7125aa3](https://github.com/ckvsoft/cevian/commit/7125aa3ade54206246622846a7a2bb8747c06abb))
* **RbacIndexView:** Introduce Role Creation Form and AJAX List ([3b9c746](https://github.com/ckvsoft/cevian/commit/3b9c7463b8e52587c808565a800f5ffe23c5198f))
* **RbacViews:** Add editrole.php view for role details ([a17c642](https://github.com/ckvsoft/cevian/commit/a17c642c7fc96e0553e6dedd7f48d31464d4a20f))
* **RbacViews:** Add permissions_manage.php for permission definitions ([cd11741](https://github.com/ckvsoft/cevian/commit/cd11741a1cb1e3b9070cfa97f9fb0d198ab39e1a))
* **RbacViews:** Add permissions.php partial for role assignment ([8512946](https://github.com/ckvsoft/cevian/commit/8512946895b88f81a59fa1b19f7676f283f862d2))
* **RbacViews:** Add roles_table_snippet.php for AJAX role listing ([8c27874](https://github.com/ckvsoft/cevian/commit/8c2787472a7fb1927ed4b2a18b6427cc9397cac8))
* Refactor AJAX form handling for reusability and add sequential save logic ([cfcaf4b](https://github.com/ckvsoft/cevian/commit/cfcaf4b5d72e0ab55acca903efae92e3d2229525))
* **Schema:** Full implementation of Nested Set RBAC model (v0.8.0) ([fb9c76b](https://github.com/ckvsoft/cevian/commit/fb9c76b4ce55fa235d880cc92b3c374d9d7bebcb))
* **View/User:** Create user list view snippet ([8dd194d](https://github.com/ckvsoft/cevian/commit/8dd194d515a3ec845ff4a8e2f2a19cd915b2848c))



