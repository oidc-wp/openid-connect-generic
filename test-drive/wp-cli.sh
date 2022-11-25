#!/bin/sh

docker run -it --rm \
--volumes-from test-drive-hello_wordpress-1 \
--network container:test-drive-hello_wordpress-1 \
-e WORDPRESS_DB_USER=exampleuser \
-e WORDPRESS_DB_PASSWORD=examplepass \
-e WORDPRESS_DB_NAME=exampledb \
-e WORDPRESS_DB_HOST=hello_db \
wordpress:cli "$@"
