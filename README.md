# About
A simple tool to create Cards from Gitlab Issues

# Requirements
- PHP
- Composer, install instructions: http://getcomposer.org/download/
- Gitlab
- Cardmaker (optional, public service available under http://cardmaker.patrickzahnd.ch/cards.pdf)

# Licence
The software and media in the main folder are distributed under the terms of the WTF Public Licence expressed in the document LICENCE

The Software and Data in the vendor folder is distributed under its respective licence.

# SETUP

```sh
# install dependencies
composer install

# create and modify config file
cp config.php.dist config.php

# Add analytics code to your installation (optional)
cp src/analytics.html.dist src/analytics.html
```