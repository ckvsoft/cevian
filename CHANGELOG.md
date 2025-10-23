# [0.15.0](https://github.com/ckvsoft/cevian/compare/v0.14.0...v0.15.0) (2025-10-23)


### Bug Fixes

* **clitool:** Include JavaScript files in Gettext extraction ([667dc7d](https://github.com/ckvsoft/cevian/commit/667dc7d3964ef28bf7663787ff5d45b466d7dd1e))


### Features

* **filemanager/js:** Implement panel path persistence using localStorage ([9a426ca](https://github.com/ckvsoft/cevian/commit/9a426cad56d1f1929d98ab971c72e41bab9676ac))
* **js:** Implement pagination state persistence using localStorage ([3b9143c](https://github.com/ckvsoft/cevian/commit/3b9143c6334ee47fca3ca973982e787d2c0bd22e))



# [0.14.0](https://github.com/ckvsoft/cevian/compare/v0.13.0...v0.14.0) (2025-10-23)


### Features

* **auth/view:** Enhance login form security, UX, and internationalization ([87f2e02](https://github.com/ckvsoft/cevian/commit/87f2e02e401b09a5769ed9584a05182fe9e5259d))
* **cli:** Add Command Line Interface (CLI) tool for i18n management ([9b1e679](https://github.com/ckvsoft/cevian/commit/9b1e6793ff013e8d3b7b7ac85379ead48a4538ec))
* **config:** Add default locale setting ([b025e87](https://github.com/ckvsoft/cevian/commit/b025e875df9a743e29ff3232a4f6daf1b01d04d8))
* **core/tools:** Implement CliTool class for I18n automation ([0ca2116](https://github.com/ckvsoft/cevian/commit/0ca2116f78026f2595c36137e0e232a1f840b001))
* **core/util:** Add SizeConverter class for byte/human-readable conversions ([cd5c0ed](https://github.com/ckvsoft/cevian/commit/cd5c0ed320ed4a27fae111cff0e053c6e7554850))
* **core:** Introduce I18n class for centralized Gettext and locale management ([939ee37](https://github.com/ckvsoft/cevian/commit/939ee374670cda2c09045b69c6f8658b61984406))
* **filemanager/model:** Implement Filemanager Model with ownership checks ([f0d0e92](https://github.com/ckvsoft/cevian/commit/f0d0e92a9fef50d34333a3d2bb9a664ef0a5bd6e))
* **filemanager:** Initial implementation of the Filemanager Controller ([7cdb3e8](https://github.com/ckvsoft/cevian/commit/7cdb3e845f2714dc929e933f54bf3cd130444947))
* **gallery/filemanager:** Implement Two-Panel UI with Drag & Drop Move/Upload and Selection Logic ([e748000](https://github.com/ckvsoft/cevian/commit/e7480008925ec653f05a155fb6c226642b3188de))
* **gallery/view:** Display extended media metadata in detail view ([d78db9c](https://github.com/ckvsoft/cevian/commit/d78db9c330ae52369bfc89bddf913581a1a1ae69))


### Reverts

* **gallery/helper:** Remove URL encoding for album paths in grid links ([4fbc887](https://github.com/ckvsoft/cevian/commit/4fbc8873beabc3808e1272f3b79dc5c4b50b11ba))
* **gallery/helper:** Remove URL encoding for album paths in grid links ([2b5ea26](https://github.com/ckvsoft/cevian/commit/2b5ea265f3266414f0eadf0b8f89028159cf1513))



# [0.13.0](https://github.com/ckvsoft/cevian/compare/v0.12.0...v0.13.0) (2025-10-20)


### Features

* **backup/view:** Implement i18n support and enhance image backup robustness ([3b812b3](https://github.com/ckvsoft/cevian/commit/3b812b3d7f0d13306f5e25c14ebd860972e7d6ab))
* **gallery/helper:** Implement breadcrumb data helper and use DB album titles ([24fbc75](https://github.com/ckvsoft/cevian/commit/24fbc759c69fdee23aafc14baac0f993308e46d5))
* **gallerymanager/model:** Add initial title generation during sync and enhance recursive updates ([120f5a8](https://github.com/ckvsoft/cevian/commit/120f5a8717c3b54ece263e1ff4b5b862751aeab6))



# [0.12.0](https://github.com/ckvsoft/cevian/compare/v0.11.0...v0.12.0) (2025-10-16)


### Bug Fixes

* **auth:** Correct and elevate admin permission level ([55e573e](https://github.com/ckvsoft/cevian/commit/55e573e6edd7b9958e8f8a6f68acee6dfa41a553))
* **user:** Include username in userSingleList query ([249d4ea](https://github.com/ckvsoft/cevian/commit/249d4ea8d3350a8776f4c9d17c0e9be19835324c))


### Features

* **gallery/manager:** Implement internationalization for permissions and add subfolder inheritance flags ([269968f](https://github.com/ckvsoft/cevian/commit/269968ffcd4296c8c0213158582c076de46e4051))
* **gallery/manager:** Implement recursive album permission/owner update and refine media fetching ([090aac5](https://github.com/ckvsoft/cevian/commit/090aac5374c61d830004ea66b0818629cfb8b454))
* **gallery/view:** Enhance album edit view with title field, i18n, and subfolder inheritance options ([992af15](https://github.com/ckvsoft/cevian/commit/992af15722ebea37511bdae8cca39a8bd8456b29))
* **i18n:** Introduce gettext fallback functions for internationalization ([69c9ba2](https://github.com/ckvsoft/cevian/commit/69c9ba22f757166a7fb1872d43a0f4ba63fb05c6))
* **user/view:** Add 'Clear' button to user creation form ([a2603bb](https://github.com/ckvsoft/cevian/commit/a2603bb4019b525c7d1628efa8f53bfc18ce7c70))
* **validation:** Implement 'matches' rule for cross-field comparison ([5420897](https://github.com/ckvsoft/cevian/commit/5420897365e43430bf5d414ed4d85c423dee8b8e))



# [0.11.0](https://github.com/ckvsoft/cevian/compare/v0.10.0...v0.11.0) (2025-10-15)


### Bug Fixes

* **gallery/view/inc:** Add missing opening PHP tag to menu snippet ([752fbcc](https://github.com/ckvsoft/cevian/commit/752fbcc6c337ef1ba1f62c66172bc06ba6240827))


### Features

* **gallery/ui:** Display album title in manager overview table ([34983f0](https://github.com/ckvsoft/cevian/commit/34983f09f76030c4a89002670ae3a079d75000b0))
* **gallery:** Ensure root album tracking and include it in path list ([4ed2dbe](https://github.com/ckvsoft/cevian/commit/4ed2dbe7102a8086d6572df129a9b717cf312866))



