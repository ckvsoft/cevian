# [0.17.0](https://github.com/ckvsoft/cevian/compare/v0.16.0...v0.17.0) (2025-11-04)


### Bug Fixes

* **bootstrap:** integrate multi-login session management and activity tracking ([5e7b5d0](https://github.com/ckvsoft/cevian/commit/5e7b5d0b580d740067f0e5da06bd3287a19abd45))
* **db:** apply utf8mb4_bin collation fix via migration 0.17.0 ([89adc11](https://github.com/ckvsoft/cevian/commit/89adc11461b693d82e87b2fe3537e48e6a6fb3fb))
* enforce utf8mb4_bin collation for gallery paths and filenames ([95afbc4](https://github.com/ckvsoft/cevian/commit/95afbc4e151da1c3ae6504c5056245aeea7b4ab9))
* **filemanager:** Implement auto-fallback on panel render error ([cf1f8aa](https://github.com/ckvsoft/cevian/commit/cf1f8aa0181d0292c848f8c3f087a61412adf810))
* **i18n:** Correct source string for slideshow toggle label ([9c4e7c1](https://github.com/ckvsoft/cevian/commit/9c4e7c1cebfc75cf752118d815c101e727e2e1f4))
* improve fetch error handling and simplify redirect logic ([7272692](https://github.com/ckvsoft/cevian/commit/72726925c2c2d86eaf25313907bd32703f8c4c07))


### Features

* add AJAX endpoint for physical image rotation ([b76f7d2](https://github.com/ckvsoft/cevian/commit/b76f7d262d44970fe9efb562fa9fbd9b559e2dd8))
* add image rotation and EXIF orientation correction ([8b61bf1](https://github.com/ckvsoft/cevian/commit/8b61bf120c88b51593648ba9b0e1137bc4d12ba4))
* add in-gallery rotation actions for admin users ([8e2cc2b](https://github.com/ckvsoft/cevian/commit/8e2cc2bcfee435dbf3675af5c009d15dfcc6df32))
* add model methods for media time and album counts ([bd5d9f0](https://github.com/ckvsoft/cevian/commit/bd5d9f04154cb5a2a8c02f738736c05a6c0f27dc))
* add rotation controls to edit_media page ([65ada93](https://github.com/ckvsoft/cevian/commit/65ada9355dfa65a7a6a6e16f5b07a0a3c4686bd7))
* expose album and media counts to gallery view ([433f7fb](https://github.com/ckvsoft/cevian/commit/433f7fbb45fda3f6774faeab0839927f9e769a0b))
* **i18n:** Introduce global translation helper functions ([fe58f2f](https://github.com/ckvsoft/cevian/commit/fe58f2f3dbefb149d8107bf048d5b1fe6e103ad7))
* implement client-side file manager logic in filemanager.js ([7a47aa5](https://github.com/ckvsoft/cevian/commit/7a47aa59bb17baa1c255fcb6d85d41a884e57810))
* implement dynamic pagination and AJAX image rotation ([fd47aac](https://github.com/ckvsoft/cevian/commit/fd47aac62d856c0fc28e12a62b868080f63fc0e6))
* implement image rotation logic and refactor thumbnail generation ([b42cc87](https://github.com/ckvsoft/cevian/commit/b42cc87b2cba3e4ff62e053f3a4ca31a4ceb64d4))
* implement server-side flash message display ([8762509](https://github.com/ckvsoft/cevian/commit/87625096657621ff6566d313c14fb32f853fa485))
* implement transactional folder merging during move operation ([b30c65a](https://github.com/ckvsoft/cevian/commit/b30c65aa6a6e268914a532179045d7459761df27))
* **session:** add activity tracking and database garbage collection ([243de9b](https://github.com/ckvsoft/cevian/commit/243de9b41a5cfad1d2f2c99962c62687c3afa38f))
* **ui:** Allow custom success title for AJAX redirects ([8e005f7](https://github.com/ckvsoft/cevian/commit/8e005f7fe7a4c5dd6951f551599981e81061ff49))
* **ui:** Enhance file upload progress with per-file status ([944bf07](https://github.com/ckvsoft/cevian/commit/944bf0735d967d1be43ea1d898c0e81ca3d1ed06))


### Performance Improvements

* **assets:** Integrate and utilize newly added minified JavaScript assets ([8424938](https://github.com/ckvsoft/cevian/commit/84249383bbd250cc90d24e879500f76c91065beb))
* **build:** Switch default JS loading to minified files ([8c630ed](https://github.com/ckvsoft/cevian/commit/8c630ed1c403cd88b0ac5b8acbeb6148ceda2651))
* **filemanager:** Load minified simple-lightbox script ([ec0851e](https://github.com/ckvsoft/cevian/commit/ec0851e7e24907f334544a6be26b7badec2b02c7))
* **gallery:** Load minified simple-lightbox script ([8212007](https://github.com/ckvsoft/cevian/commit/82120070c4b18319a00af6948c15133b047014f0))



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



