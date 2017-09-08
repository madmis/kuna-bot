# kuna-bot

This is php bot with simple strategy to trade bitcoin 
on the https://kuna.io/ exchange.

## Table Of Contents

- [Installation](#installation)
- [Running the bot](#running-the-bot)
    - [Run on the local machine](#run-on-the-local-machine)
    - [Run in the Docker container](#run-in-the-docker-container)


## Installation

Download latest release [here](https://github.com/madmis/kuna-bot/releases) 
and extract sources to a project (destination) folder **or** clone project
```bash
    $ git clone https://github.com/madmis/kuna-bot.git ~/kuna-bot
```

Create configuration file:
```bash
    $ cp ~/kuna-bot/app/simple-bot-config.yaml.dist ~/kuna-bot/app/conf.btcuah.yaml
```
and change configuration parameters with your requirements.


## Running the bot

You can run bot on the local machine or in the Docker container.

### Run on the local machine
To run bot on the local machine please install: 
* [php >=7.1.3](http://php.net/manual/en/install.php)
* [php-bcmath](http://php.net/manual/en/book.bc.php)
* [Сomposer](https://getcomposer.org/doc/00-intro.md)

Then do next steps:
```bash
    $ cd ~/kuna-bot/app
    $ composer install
```
and run the bot:
```bash
    $ php ~/kuna-bot/app/bin/console simple-bot:run ~/kuna-bot/app/conf.btcuah.yaml 
```


### Run in the Docker container 
To run bot in the Docker container:
* [Install Docker](https://docs.docker.com/engine/installation/)
* [Install Docker Compose](https://docs.docker.com/compose/install/)

Then do next steps:
```bash
    $ cd ~/kuna-bot
    $ docker-compose up -d
    $ docker exec -ti kunabot_php_1 bash
```
and run the bot:
```bash
    $ php /var/www/bin/console simple-bot:run /var/www/conf.btcuah.yaml 
```
