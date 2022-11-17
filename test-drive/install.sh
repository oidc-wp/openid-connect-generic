#!/bin/sh

# copy the hello-login plugin files into WordPress container, to the plugin folder
docker exec test-drive-hello_wordpress-1 mkdir -p /var/www/html/wp-content/plugins/hello-login
docker cp ../readme.txt test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
docker cp ../hello-login.php test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
docker cp ../css test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
docker cp ../js test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
docker cp ../includes test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
docker cp ../languages test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
docker exec test-drive-hello_wordpress-1 chown -R www-data:www-data /var/www/html/wp-content/plugins/hello-login
