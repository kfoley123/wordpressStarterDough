# wordpressStarterDough


## Get Started 
docker compose up 

## Purpose 
To create a local Wordpress environment using Docker. 

Creates two containers, one for database (mySQL version 8 container) and one for the WordPress installation. It runs on localhost port 666 but this can be changed in the docker compose file. 
The plugins and themes folders are created as seperate volumes and the gitignore ignores the database and wordpress folders.

