<fieldset>
    <legend>Home</legend>
    <section class="page-content">
        <section class="grid">
            
            <!-- Linke Spalte: Englisch -->
            <article class="imagebox">
                <div class="container">
                    <h1><?= _("Installation Successful! ✅") ?></h1>
                    <p><?= _("The installation was successful. A crucial point to remember is the generated HASH_KEY found in the config/app.json file. You must save this key in a secure location. If the key is lost, you will no longer be able to log in to the system.") ?></p>
                    <p><?= _("To customize this page, you can copy the core_modules/home directory to /modules, but do not delete the original core_modules/home directory. You can then make your modifications. You can delete parts of the copied directory, such as Controller, Model, View, etc., where you are not making any changes. This same logic applies to other resources like JavaScript (JS) and Cascading Style Sheets (CSS) files. The /modules folder is the place for your own modules or for customized versions of core modules.") ?></p>
                    <h2><?= _("Modular Customization") ?></h2>
                    <p><?= _("Only the files you want to modify should be saved within the /modules directory, and they must be in the exact same path as they are in the core_modules directory. This \"mirroring\" path ensures your customized files seamlessly override the originals.") ?></p>
                    <h3><?= _("For example:") ?></h3>
                    <ul>
                        <li><?= _("If you only want to change the header and footer in /modules/inc, you just need to copy those files from core_modules/inc and modify them. There's no need to copy the entire main module.") ?></li>
                        <li><?= _("If you are changing a View component, it's enough to copy and modify only the View of the desired module. The same applies to Model and all other parts.") ?></li>
                        <li><?= _("Should the inc/header be changed only for this copied module, create a header file in view/inc and also copy the corresponding controller. In the controller, you must adjust the header's path, for example, from /inc/header to inc/header (note the leading slash /). The header will then be loaded from <module>/view/inc/header.") ?></li>
                        <li><?= _("If you want to use a header from another module, you must specify the module name, for example: dashboard/inc/header.") ?></li>
                    </ul>
                    <h2><?= _("Using a Logo") ?></h2>
                    <p><?= _("If a file named logo.png exists in the public/images directory, it will automatically be used as the logo without any further configuration.") ?></p>
                </div>
            </article>

            <!-- Rechte Spalte: Deutsch -->
            <article class="imagebox">
                <div class="container">
                    <h1>Installation erfolgreich! ✅</h1>
                    <p>Die Installation war erfolgreich. Ein wichtiger Punkt, den Sie beachten sollten, ist der generierte HASH_KEY, der sich in der Datei config/app.json befindet. Sie müssen diesen Schlüssel unbedingt an einem sicheren Ort sichern. Geht dieser Schlüssel verloren, ist eine Anmeldung am System nicht mehr möglich.</p>
                    <p>Um diese Seite anzupassen, können Sie beispielsweise das Verzeichnis core_modules/home nach /modules kopieren, aber lassen Sie das Original-Verzeichnis core_modules/home unverändert. Anschließend können Sie Ihre Anpassungen vornehmen. Teile wie Controller, Model, View etc., in denen keine Änderungen vorgenommen werden, können aus dem kopierten Verzeichnis gelöscht werden. Diese Logik gilt auch für andere Ressourcen wie JavaScript (JS) und Cascading Style Sheets (CSS). Der /modules-Ordner ist der vorgesehene Platz für Ihre eigenen Module oder für angepasste Versionen von Kernmodulen.</p>
                    <h2>Modulare Anpassung</h2>
                    <p>Speichern Sie im /modules-Verzeichnis nur die Dateien, die Sie tatsächlich anpassen möchten, und zwar im exakt gleichen Pfad, den sie auch im core_modules-Verzeichnis haben. Dieser "spiegelnde" Pfad stellt sicher, dass Ihre angepassten Dateien die Originale nahtlos überschreiben.</p>
                    <h3>Beispiel:</h3>
                    <ul>
                        <li>Wenn Sie nur den Header und Footer ändern möchten, die sich in /modules/inc befinden, kopieren Sie lediglich diese Dateien aus core_modules/inc und passen sie an. Das Hauptmodul muss in diesem Fall nicht komplett kopiert werden.</li>
                        <li>Wenn Sie eine Änderung an einem View-Teil vornehmen, reicht es aus, nur den View des gewünschten Moduls zu kopieren und zu ändern. Dasselbe gilt für Model und alle anderen Teile.</li>
                        <li>Soll der inc/header nur für dieses kopierte Modul geändert werden, erstellen Sie eine Header-Datei in view/inc und kopieren Sie auch den zugehörigen Controller. Im Controller müssen Sie den Pfad des Headers anpassen, zum Beispiel von /inc/header auf inc/header (beachten Sie den führenden Schrägstrich /). Der Header wird dann aus &lt;modul&gt;/view/inc/header geladen.</li>
                        <li>Wenn Sie einen Header aus einem anderen Modul verwenden möchten, muss der Modulname angegeben werden, zum Beispiel: dashboard/inc/header.</li>
                    </ul>
                    <h2>Verwendung eines Logos</h2>
                    <p>Wenn sich eine Datei namens logo.png im Verzeichnis public/images befindet, wird diese automatisch als Logo verwendet, ohne dass weitere Anpassungen oder Konfigurationen notwendig sind.</p>
                </div>
            </article>

        </section>
    </section>
</fieldset>
