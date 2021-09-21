#!/bin/bash

#regenerate .pot file
sh ./genpot.sh

#minify and combine css
cat ./stripe-payments/public/views/templates/default/pure.css ./stripe-payments/public/views/templates/default/pp-style.css | cleancss -o ./stripe-payments/public/views/templates/default/pp-combined.min.css --s0

#minify and combine js
cat ./stripe-payments/public/assets/js/add-ons/tax-variations.js ./stripe-payments/public/assets/js/md5.min.js ./stripe-payments/public/assets/js/pp-handler.js | uglifyjs - -c -m -o ./stripe-payments/public/assets/js/pp-handler.min.js

stable=$(grep "Stable tag:" ./stripe-payments/readme.txt)
version=$(echo $stable | sed 's/Stable tag://' | xargs)
rm -f ./stripe-payments_$version.zip
zip -r -q -J -X ./stripe-payments_$version.zip ./stripe-payments/ -x *_debug_log.txt
