#!/usr/bin/env bash

dir=`pwd`
cd ./../../../

./vendor/bin/phpunit --configuration="$dir" --colors=always "$@"
