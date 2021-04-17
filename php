#!/usr/bin/env bash
pwd=$(dirname "$(readlink -e "$0")")
docker-compose --project-directory ${pwd} -f ${pwd}/docker-compose.yml exec -T php8.0-fpm php "$@"