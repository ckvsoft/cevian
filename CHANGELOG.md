# [0.16.0](https://github.com/ckvsoft/cevian/compare/v0.15.0...v0.16.0) (2025-10-28)


### Bug Fixes

* Add custom success message for inline permission creation ([d354518](https://github.com/ckvsoft/cevian/commit/d354518c2b7f975abc7aa79eaa86c6055e255c5b))
* Add custom success message for menu entry creation form ([a072459](https://github.com/ckvsoft/cevian/commit/a07245913d818e0987afa72f43e7f5a7f818dcfc))
* Add custom success message for menu entry editing ([b837001](https://github.com/ckvsoft/cevian/commit/b837001b1110357d1bd4afb75d213e1b91cb84e6))
* Add custom success message for role editing and minor cleanup ([1b8d1ea](https://github.com/ckvsoft/cevian/commit/1b8d1ea0ec99a6b021978d6880de052f2c4460b2))
* Add custom success message for user creation form ([0693bd0](https://github.com/ckvsoft/cevian/commit/0693bd086634edd07024c5cee93c32e7a90a049b))
* Add custom success message for user editing form ([09319d6](https://github.com/ckvsoft/cevian/commit/09319d648206ec13c75b72bc4800f62117a98049))
* Implement client-side logic for multi-tab logout broadcast ([3ab1c06](https://github.com/ckvsoft/cevian/commit/3ab1c06bfe10772acc17334523e1b70dabed2607))
* **media/controller:** Update caching headers to prevent browser caching of private content ([3a56684](https://github.com/ckvsoft/cevian/commit/3a56684d081ae03352e516f64890a01c19ed1ea5))


### Features

* Add persistent 'OK' button support to XNotify notifications ([63164eb](https://github.com/ckvsoft/cevian/commit/63164eb083f8505e5ef201dc7e273d8dc33ce52d))
* Add persistent automatic slideshow control to gallery view ([a8737ec](https://github.com/ckvsoft/cevian/commit/a8737ec7db83bde93d6d1da1d902ab034b50428d))
* Create core JS utilities file and implement authentication/message features ([cd907cc](https://github.com/ckvsoft/cevian/commit/cd907cc51d93f73fa5e124084eda7de75c01f04e))
* **db:** Normalize gallery media metadata into new table ([97d35b1](https://github.com/ckvsoft/cevian/commit/97d35b1e012f02d3e898c0e1e7c1bd5f27d729f4))
* Enhance media rescan with modification checks and thumbnail regeneration ([d70960b](https://github.com/ckvsoft/cevian/commit/d70960bb9b1e648e0d639b98e88fc32cabf860c3))
* Enhance permission CRUD with custom messaging and utility integration ([3e3332e](https://github.com/ckvsoft/cevian/commit/3e3332e0c143ff3e915785e10f741d2322018999))
* Enhance role CRUD with custom messaging and utility integration ([8d33e57](https://github.com/ckvsoft/cevian/commit/8d33e572cddf7d256695b8d38fb79497f1b12bf1))
* **gallery/controller:** Implement edit_media POST logic and cleanup internal comments ([619a406](https://github.com/ckvsoft/cevian/commit/619a4060e39009dc50a4ed683c607059691dcbd2))
* Internationalize media editing view and rename 'name' to 'title' ([4cba9b3](https://github.com/ckvsoft/cevian/commit/4cba9b30559826c8e8e407f941906b8e557195c1))
* Introduce dedicated FlashMessage class for server-side messaging ([bfedcd2](https://github.com/ckvsoft/cevian/commit/bfedcd222b8ed2422f20bd234124b598320ec3ac))
* Introduce Request class for robust and safe request data access ([72b3b8a](https://github.com/ckvsoft/cevian/commit/72b3b8a8c05c4f3066a4ef491b9d7487ae0fd82d))
* **js:** Overhaul menuscript.js into core UI utility, add shrinking header, notifications, and change detection ([122de6e](https://github.com/ckvsoft/cevian/commit/122de6ebf43f42ddc86d32ba02b1cc9782ca4b79)), closes [#menu_11](https://github.com/ckvsoft/cevian/issues/menu_11)



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



