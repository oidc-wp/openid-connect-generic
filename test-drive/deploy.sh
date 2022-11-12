#!/usr/bin/env bash

DOCKER_VOLUME=./plugin

cp -v -u ../CHANGELOG.md ../hello-login.php ../readme.txt $DOCKER_VOLUME
cp -r -v -u ../css ../includes ../languages $DOCKER_VOLUME
