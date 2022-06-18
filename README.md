# wp-migrate
Wordpress content and database migration script.

[![Maintenance](https://img.shields.io/badge/Maintained%3F-no-red.svg)](https://bitbucket.org/lbesson/ansi-colors)

This script uses PHP FTP and MySQLi modules on both servers, make sure they are installed and enabled via CLI (on the source server) and web (on the target server).

Make sure you have a FTP user on the target (new) server.

# How to use
1. Make a fresh Wordpress installation on the target server
2. Download the script on source Wordpress server
You can do it using `wget https://raw.githubusercontent.com/theprometeus/wp-migrate/master/wp-migrate.php`
3. Run the script ```php wp-migrate.php``` and follow the instructions

# What this script does?
- Copies all Wordpress content files (wp-content folder) via FTP
- Dump and restore the entire database

# What this script don't do?
- Install Wordpress on the target server (this is a TODO, pull requests are welcome)
- Backup your Wordpress installation
- Copies Wordpress core files

# TODO
- Make the target script install Wordpress if not installed
- Zip subfolders (upload months, plugins and themes) separately, and upload them separately
- Add more skip options (plugins, uploads, and themes)

# Documentation
The methods marked as ❗ can only be changed via console arguments.

### `--target.ftp.host="ftp.awesomesite.com"`
Set the target FTP server host

### `--target.ftp.user="flufflyftp"`
Set the target FTP server user

### `--target.ftp.password="notafurry"`
Set the target FTP server password

### `--target.ftp.dir="public_html/not_wordpress"`
Set the target FTP server directory

### `--source.dir="/var/www/definitely_not_wordpress/"`
Set the source Wordpress directory

### ❗ `--skip-database=true`
Skips database migration

### ❗ `--skip-content=true`
Skips entire wp-content folder
