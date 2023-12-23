CREATE DATABASE IF NOT EXISTS wordpress_test;
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'localhost' IDENTIFIED BY 'wordpress';
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%' IDENTIFIED BY 'wordpress';
FLUSH PRIVILEGES;
