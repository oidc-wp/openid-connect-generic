#!/bin/zsh

# start with an empty plugin subfolder
rm -rf plugin
mkdir -p plugin

# start the docker containers, plugin subfolder is bound inside WordPress container
docker-compose up  -d

# wait for the WordPress service to start
sleep 10

# open WordPress in browser
open http://localhost:8080/
