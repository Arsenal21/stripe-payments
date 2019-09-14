#!/bin/bash
stable=$(grep "Stable tag:" ./stripe-payments/readme.txt)
version=$(echo $stable | sed 's/Stable tag://' | xargs)
rm -f ./stripe-payments_$version.zip
zip -r -J -X ./stripe-payments_$version.zip ./stripe-payments/ -x *_debug_log.txt
