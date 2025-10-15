# [0.11.0](https://github.com/ckvsoft/cevian/compare/v0.10.0...v0.11.0) (2025-10-15)


### Bug Fixes

* **gallery/view/inc:** Add missing opening PHP tag to menu snippet ([752fbcc](https://github.com/ckvsoft/cevian/commit/752fbcc6c337ef1ba1f62c66172bc06ba6240827))


### Features

* **gallery/ui:** Display album title in manager overview table ([34983f0](https://github.com/ckvsoft/cevian/commit/34983f09f76030c4a89002670ae3a079d75000b0))
* **gallery:** Ensure root album tracking and include it in path list ([4ed2dbe](https://github.com/ckvsoft/cevian/commit/4ed2dbe7102a8086d6572df129a9b717cf312866))



# [0.10.0](https://github.com/ckvsoft/cevian/compare/v0.9.0...v0.10.0) (2025-10-14)


### Bug Fixes

* **backup:** Correct data parsing and remove debug logs ([4cd82f6](https://github.com/ckvsoft/cevian/commit/4cd82f62451eea45b549a54adb19773f7a0a9910))
* **mobile:** Update menu ID selector in header.php ([7ff26a9](https://github.com/ckvsoft/cevian/commit/7ff26a979fdd5d06c11ec6dceec67d6cf5a91935)), closes [#menu_11](https://github.com/ckvsoft/cevian/issues/menu_11)
* **module:** Remove redundant updated_at assignment in module registration ([60092e9](https://github.com/ckvsoft/cevian/commit/60092e9ec9a3f4dc4d297475545c1662d3cb9903))


### Features

* **ajax:** Introduce progress polling and unify client-side pagination ([223e12a](https://github.com/ckvsoft/cevian/commit/223e12a438e39b40f7fa2d4d72c644d45ccbd0ea))
* **auth:** Add static method to retrieve simplified permission level ([610698c](https://github.com/ckvsoft/cevian/commit/610698cc4ab19430d4aaf5f804483b48cf0f2613))
* **controller:** Pass mobile flag to menu helper for responsive generation ([f5df782](https://github.com/ckvsoft/cevian/commit/f5df78283a62e661b064b6064cafe5da42ec9bde))
* **database:** Add selectOne() method for single-row queries ([edfe17b](https://github.com/ckvsoft/cevian/commit/edfe17b300f1ff9336b05b3799c24c842c884210))
* **gallery/assets:** Add placeholder images and deny icons ([40eccf4](https://github.com/ckvsoft/cevian/commit/40eccf43343bfa4b7247cfa2fe8edab2a0070e31))
* **gallery/view/manager:** Add media item management view ([1431195](https://github.com/ckvsoft/cevian/commit/14311958125c7ee7d2665f3f3e9792096a87bc70))
* **gallery/view:** Add dedicated view for editing individual media item metadata ([2b6a034](https://github.com/ckvsoft/cevian/commit/2b6a0349b0674162d6700f46f1112c6ab7d1135a))
* **gallery:** Add dedicated Manager controller for gallery administration ([64c0605](https://github.com/ckvsoft/cevian/commit/64c0605ea5577d9c99de60d0a3f4818228553edd))
* **gallerymanager:** Introduce GalleryManager_Model for administrative tasks ([19b9208](https://github.com/ckvsoft/cevian/commit/19b9208c769dffdfcf3b67cb86f87e1e8c441a74))
* **image:** Add GIF support and improve resource cleanup in Image class ([d08aba2](https://github.com/ckvsoft/cevian/commit/d08aba2cb4565eaf725c9b8d13824406df4c3eaf))
* **media:** Introduce dedicated Media controller for secure file serving and fallbacks ([fdbb9b1](https://github.com/ckvsoft/cevian/commit/fdbb9b169d490097ff2caac4fd6fabe85fe48e84))


### Performance Improvements

* **backup:** Drastically reduce usleep duration for faster backups ([6f8641b](https://github.com/ckvsoft/cevian/commit/6f8641b8ba15cd0d73cba313b19499234c882dd6))



# [0.9.0](https://github.com/ckvsoft/cevian/compare/v0.8.3...v0.9.0) (2025-10-08)


### Bug Fixes

* **bootstrap:** Expand asset logging and clarify error message ([e25537c](https://github.com/ckvsoft/cevian/commit/e25537cd28d50c2b3579fbf66fac5ddaa0885ced))
* **gallery:** Ensure correct parameter passing for recursive and random media fetching ([87dfd88](https://github.com/ckvsoft/cevian/commit/87dfd888bfd9dc676c73988c6b2e3ed063cf3903))


### Features

* **database:** Add gallery tables for album and media statistics ([efd8bef](https://github.com/ckvsoft/cevian/commit/efd8beff58cea528e588c6f4354f4f6d110ba14c))
* **gallery:** Add simple-lightbox CSS styling for media viewer ([9ea125f](https://github.com/ckvsoft/cevian/commit/9ea125f769641632f003f4c75d17f1a6bacb9955))
* **gallery:** Add simple-lightbox JavaScript for interactive media viewing ([16a7b0c](https://github.com/ckvsoft/cevian/commit/16a7b0cd76bd4300ee2ec62ac737ebda69022d33))
* **gallery:** Implement media view counter and dynamic album tracking ([32b61ec](https://github.com/ckvsoft/cevian/commit/32b61ec6c03f92451848892771e4a201ecf63ca2))
* **schema:** Finalize database schema with gallery and core updates ([37c4863](https://github.com/ckvsoft/cevian/commit/37c4863c8dbcdaf241967089b06cc6e215b50cdc))



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



