# wp-migrate
Wordpress content and database migration script.

This script uses PHP FTP and MySQLi modules on both servers, make sure they are installed and enabled via CLI (on the source server) and web (on the target server).

# How to use
- Download the script on source Wordpress server
- Make a fresh Wordpress installation on the target server
- Make sure you have a FTP user on the target (new) server
- Run the script ```php wp-migrate.php``` and follow the instructions

# What this script does?
- Copies all Wordpress content files (wp-content folder) via FTP
- Dump and restore the entire database

# What this script don't do?
- Install Wordpress on the target server (this is a TODO, pull requests are welcome)
- Backup your Wordpress installation
- Copies Wordpress core files
