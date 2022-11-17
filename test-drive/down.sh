#!/bin/sh

# take down docker containers
docker-compose down

# remove volumes
docker volume prune -f
