# Test Drive

## Bash Session Inside a Container

Start a bash session inside the WordPress container:
```shell
docker exec -it test-drive-hello_wordpress-1 bash
```

* WordPress root folder: `/var/www/html/`
* plugin folder: `/var/www/html/wp-content/plugins/`

# Copy Out of a Container

Copy a plugin out of the WordPress directory:
```shell
docker cp test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/akismet ./
```

# Copy Into a Container

Make the target directory:
```shell
docker exec test-drive-hello_wordpress-1 mkdir -p /var/www/html/wp-content/plugins/hello-login
```

Copy a file inside the container:
```shell
docker cp ../readme.txt test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
```

Copy a folder inside the container:
```shell
docker cp ../includes test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/hello-login/
```

Change the owner such that WordPress can edit or delete:
```shell
docker exec test-drive-hello_wordpress-1 chown -R www-data:www-data /var/www/html/wp-content/plugins/hello-login
```
