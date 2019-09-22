#!/bin/bash
#minify and combine css
cat ./stripe-payments/public/views/templates/default/pure.css ./stripe-payments/public/views/templates/default/pp-style.css | cleancss -o ./stripe-payments/public/views/templates/default/pp-combined.min.css --s0

#minify js
uglifyjs -c -m -o ./stripe-payments/public/assets/js/pp-handler.min.js ./stripe-payments/public/assets/js/pp-handler.js

stable=$(grep "Stable tag:" ./stripe-payments/readme.txt)
version=$(echo $stable | sed 's/Stable tag://' | xargs)
rm -f ./stripe-payments_$version.zip
zip -r -q -J -X ./stripe-payments_$version.zip ./stripe-payments/ -x *_debug_log.txt
