## Requirements

- Docker (https://docs.docker.com/get-started/get-docker/)
- Docker Desktop
- Install and Start

## Installation Steps

**Make sure Docker Desktop/Engine is Running**

From the root directory run
  
> docker image build -t php8 .

And then to start the development enviornment

> docker compose up 

To Stop
> Ctrl^C -- and then -- docker compose down


## After starting docker

- all the code is in src file
- the server will run on localhost:8080
- phpMyAdmin can be found on localhost:8081


## Making Changes
If you change the code, you need to restart the server 

> Ctrl^C & docker compose down 

And then

> docker compose up

