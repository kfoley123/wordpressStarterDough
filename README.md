# WordPress Starter Dough

<<<<<<< Updated upstream
## Purpose
The purpose of this project is to create a ready-to-use local Wordpress environment using Docker. It simplifies the setup process by creating two containers: one for the database (MySQL version 8 container) and one for the Wordpress installation. By default, it runs on localhost port 666, but this can be changed in the docker-compose file to fit your needs. Additionally, the project creates separate volumes for plugins and themes folders, and the .gitignore file is configured to ignore the database and Wordpress folders.

## Author
- Author: Kortney Foley
=======
## Author 
Kortney Foley 

## 🚀 Get Started
Run the following command to spin up your local WordPress environment:  
```sh
docker compose up -d
```
Once running, access WordPress at:  
**http://localhost:666**

## 🎯 Purpose  
This project provides a quick and easy way to set up a local WordPress environment using Docker.  

It creates two containers:  
- **Database**: MySQL 8 container  
- **WordPress**: WordPress installation  
The setup runs on `localhost:666` (port can be changed in `docker-compose.yml`).  

The plugins and themes folders are mounted as separate volumes, making it easier to manage them via Git.  
The `.gitignore` file ensures the database and WordPress files are excluded from version control.  

---

## 🔄 Recent Updates
- Switched to using environment variables (`.env` file) for better security.
- Added a health check for MySQL to ensure WordPress waits until the database is ready.
- Used named volumes for MySQL data storage instead of binding to a local directory.
- Explicitly set a stable WordPress version (`wordpress:6.4`) instead of `latest`.
- Added a restart policy (`restart: always`) to ensure containers automatically restart if they fail.

---

## 📌 Notes
- If you want to change the database credentials, update the `.env` file before running `docker compose up`.
- MySQL port `3306` is exposed, allowing external tools (e.g., MySQL Workbench) to connect for debugging.

---

## 🔗 Connect with Me  
[![LinkedIn](https://img.shields.io/badge/LinkedIn-Connect-blue?style=flat&logo=linkedin)](https://www.linkedin.com/in/kortney-foley/)
>>>>>>> Stashed changes

## Usage/Get Started 
This project is designed to provide an easy-to-clone version of Wordpress in Docker, allowing you to quickly spin up a Wordpress instance for a new project. Simply run the following command to start the environment: docker compose up 
