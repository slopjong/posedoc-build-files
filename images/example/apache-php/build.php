<?php

// this will create the docker image 'example/apache-php'
return (new DebianImage())
    ->from('debian', '7.6')
    ->maintainer('John', 'Doe', 'john.doe@example.com')
    ->apt('vim wget git-core curl apache2-mpm-prefork php5 php5-intl php5-cli php5-curl php5-gd')
    ;