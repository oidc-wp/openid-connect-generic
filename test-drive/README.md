# Test Drive

## Local Testing

Use `docker compose` (or the older `docker-compose`) to run the containers required for local testing. The `up.sh` and
`down.sh` are simple wrappers around `docker compose` commands that also open a browser and prune volumes.

Once the containers are running use the following URLs:
* http://localhost:8080/ - WordPress instance
* http://localhost:8025/ - MailHog web interface

Once the WordPress instance has basic configuration and an admin account you can install the latest released Hellō Login
plugin from the WordPress plugin repo.

You can run the `install.sh` script to copy local plugin files into the docker container.

## WordPress CLI

Use the wp-cli.sh script to run [WordPress CLI](https://wp-cli.org/) commands. Command reference at
https://developer.wordpress.org/cli/commands/

### Examples ###
List users:
```shell
./wp-cli.sh user list
```

Unlink user 1 from Hellō:
```shell
./wp-cli.sh user meta delete 1 hello-login-subject-identity
```

## Docker Cheat Sheet

### Bash Session Inside a Container

Start a bash session inside the WordPress container:
```shell
docker exec -it test-drive-hello_wordpress-1 bash
```

* WordPress root folder: `/var/www/html/`
* plugin folder: `/var/www/html/wp-content/plugins/`

### Copy Out of a Container

Copy a plugin out of the WordPress directory:
```shell
docker cp test-drive-hello_wordpress-1:/var/www/html/wp-content/plugins/akismet ./
```

### Copy Into a Container

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
