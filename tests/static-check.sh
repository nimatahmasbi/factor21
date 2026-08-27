#!/usr/bin/env sh
set -eu
find app resources -name '*.php' -print | while read -r file; do php -l "$file"; done
test -f public/assets/vendor/html2canvas.min.js
test -f public/assets/vendor/jspdf.umd.min.js
test -f public/assets/vendor/jalalidatepicker.min.js
test -f public/assets/vendor/jalalidatepicker.min.css
test -f install/install.php
test -f install/Migrator.php
test -f install/updates/1.0.0.php
test -f install/updates/1.1.0.php
test -f install/updates/1.2.0.php
test -f install/updates/1.2.1.php
test -f install/updates/1.2.2.php
test -f install/updates/1.2.3.php
test -f install/updates/1.2.4.php
test -f install/updates/1.3.0.php
test -f install/updates/1.3.1.php
test -f install/updates/1.4.0.php
test -f install/updates/1.4.1.php
test -f install/updates/1.4.2.php
test -f install/updates/1.4.3.php
test -f resources/views/public_quote.php
test -f public/assets/css/companies.css
echo "Static checks passed."
