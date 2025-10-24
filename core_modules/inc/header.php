<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <?php if (!empty($this->base_scripts)): ?>
            <?= $this->base_scripts ?>
        <?php endif; ?>

        <link rel="icon" href="<?= BASE_URI ?>favicon.ico" type="image/x-icon">

        <?php if (!empty($this->base_css)): ?>
            <?= $this->base_css ?>
        <?php endif; ?>

    </head>
    <body>

        <header class="fixed-header" id="mainHeader">
            <div class="container">
                <img class="logo" id="headerLogo"
                     src="<?= BASE_URI ?>public/images/logo.png"
                     alt="LOGO">
            </div>

            <div id="primary_nav_stretch">
                <nav id="primary_nav_wrap" role="navigation">

                    <?php if ($this->mobile): ?>
                        <div class="hamburger-menu">
                            <div class="bar"></div>
                            <div class="bar"></div>
                            <div class="bar"></div>
                        </div>
                    <?php endif; ?>

                    <?= $this->menuitems ?? '' ?>

                </nav>
            </div>
        </header>

        <main id="flex-container">
            <div id="status"></div>
