<?php

echo "<!DOCTYPE html>";
echo "<html lang=\"de\">";
echo "<head>";
echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
echo "<script> var BASE_URI = \"" . BASE_URI . "\"</script>";
echo "<script src=\"" . BASE_URI . "public/js/menuscript.js\"></script>";
echo "<script src=\"" . BASE_URI . "public/js/x-notify.js\"></script>";
// echo "<script src=\"" . BASE_URI . "public/js/highslide/highslide-full.js\"></script>";
echo "<script src=\"" . BASE_URI . "public/js/theme-js-vanilla.js\"></script>";
// echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"" . BASE_URI . "public/js/highslide/highslide.css\">";
echo "<link rel=\"shortcut icon\" href=\"" . BASE_URI . "favicon.ico\" type=\"image/x-icon\">";
echo "<link rel=\"icon\" href=\"" . BASE_URI . "favicon.ico\" type=\"image/x-icon\">";

if ($this->mobile) {
    echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"" . BASE_URI . "public/css/mobile.css\">\n";
} else {
    echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"" . BASE_URI . "public/css/style.css\">\n";
}

if (isset($this->styles)) {
    echo $this->styles;
}

if (isset($this->script)) {
    echo $this->script;
}

echo "<script>";
echo "  function deleteRow(id) {";
echo "      console.log(\"Löschen von Zeile mit ID \" + id);";
echo "  }";

echo "  function undoRow(id) {";
echo "      console.log(\"Rückgängig machen von Zeile mit ID \" + id);";
echo "  }";

echo "  function displayMessage(type, title, message) {";
echo "    const Notify = new XNotify(\"BottomRight\");";
echo "    switch (type) {";
echo "      case 'success':";
echo "        Notify.success({";
echo "          title: title,";
echo "          description: message,";
echo "          duration: 5000";
echo "        });";
echo "      break;";
echo "      case 'error':";
echo "        Notify.error({";
echo "          title: title,";
echo "          description: message,";
echo "          duration: 5000,";
echo "        });";
echo "      break;";
echo "      default:";
echo "        Notify.info({";
echo "          title: title,";
echo "          description: message,";
echo "          duration: 5000,";
echo "        });";
echo "    }";
echo "  }";
echo "  function initChangeDetection(form) {";
echo "    Array.from(form).forEach(el => el.dataset.origValue = el.value);";
echo "  }";
echo "  function formHasChanges(form) {";
echo "    return Array.from(form).some(el => 'origValue' in el.dataset && el.dataset.origValue !== el.value);";
echo "  }";
echo "</script>";

echo "</head>";
echo "<body>";
echo " <div class=\"fixed-header\">";
echo "  <div class=\"container\">";
echo "   <img class=\"logo\" src=\"" . BASE_URI . "public/images/logo_neu.png\" alt=\"kvasny.at\" />";
echo "  </div>";
echo "  <div id=\"primary_nav_stretch\">";
echo "   <nav role=\"navigation\" id='primary_nav_wrap'>";
if ($this->mobile) {
    echo "<div class = \"hamburger-menu\">";
    echo "<div class = \"bar\"></div>";
    echo "<div class = \"bar\"></div>";
    echo "<div class = \"bar\"></div>";
    echo "</div>";
}
echo $this->menuitems;
echo "   </nav>";
echo "  </div>";
echo " </div>";
echo "<div id=\"flex-container\">";
echo " <div id=\"status\"></div>";

if ($this->mobile) {
    echo "<script>";
    echo "const hamburger = document.querySelector('.hamburger-menu');";
    echo "const menu = document.querySelector('#menu');";
    echo "hamburger.addEventListener('click', function() {";
    echo "  menu.classList.toggle('open');";
    echo " console.log(\"toogle\")";
    echo "});";
    echo "</script>";
}
