# wordpressStarterDough

## Purpose
The purpose of this project is to create a ready-to-use local Wordpress environment using Docker. It simplifies the setup process by creating two containers: one for the database (MySQL version 8 container) and one for the Wordpress installation. By default, it runs on localhost port 666, but this can be changed in the docker-compose file to fit your needs. Additionally, the project creates separate volumes for plugins and themes folders, and the .gitignore file is configured to ignore the database and Wordpress folders.

## Author
- Author: Kortney Foley

## Usage/Get Started 
This project is designed to provide an easy-to-clone version of Wordpress in Docker, allowing you to quickly spin up a Wordpress instance for a new project. Simply run the following command to start the environment: docker compose up 
