<?php

echo "<!DOCTYPE html>";
echo "<html lang=\"de\">";
echo "<head>";
echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
echo "<script> var BASE_URI = \"" . BASE_URI . "\"</script>";

if (!empty($this->base_scripts)) {
    echo $this->base_scripts;
}

echo "<link rel=\"shortcut icon\" href=\"" . BASE_URI . "favicon.ico\" type=\"image/x-icon\">";
echo "<link rel=\"icon\" href=\"" . BASE_URI . "favicon.ico\" type=\"image/x-icon\">";

echo "<script>";
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

if (!empty($this->base_css)) {
    echo $this->base_css;
}

echo "</head>";
echo "<body>";
echo " <div class=\"fixed-header\">";
echo "  <div class=\"container\">";
echo "   <img class=\"logo\" src=\"" . BASE_URI . "public/images/logo.png\" alt=\"LOGO\" />";
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
