#!/bin/bash
#combine css
cat ./stripe-payments/public/views/templates/default/pure.css ./stripe-payments/public/views/templates/default/pp-style.css | cleancss -o ./stripe-payments/public/views/templates/default/pp-combined.min.css --s0

stable=$(grep "Stable tag:" ./stripe-payments/readme.txt)
version=$(echo $stable | sed 's/Stable tag://' | xargs)
rm -f ./stripe-payments_$version.zip
zip -r -J -X ./stripe-payments_$version.zip ./stripe-payments/ -x *_debug_log.txt
