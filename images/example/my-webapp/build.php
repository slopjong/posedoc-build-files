<?php

// this will create the docker image 'example/my-webapp'
return (new BaseImage())
    ->from('example/apache-php')
    ->maintainer('John', 'Doe', 'john.doe@example.com')

    ->requireProject('https://github.com/johndoe/myproject.git')
    ->addFromProject('myproject', array(
            'config'             => '/var/www/myproject/config',
            'data'               => '/var/www/myproject/data',
            'module/Application' => '/var/www/myproject/module/Application',
            'module/Api'         => '/var/www/myproject/module/Api',
            'public'             => '/var/www/myproject/public',
            'composer.json'      => '/var/www/myproject/composer.json',
        )
    )
    // fix the application configuration to make a module unavailable
    ->run(array(
        "sed",
        "-ie",
        "s/'AnotherModule',//g",
        "/var/www/myproject/config/application.config.php"
    ))

    ->addAsset('vhost.conf', '/etc/apache2/sites-available/default')
    ->run('rm /etc/apache2/sites-available/default-ssl')
    ->run('chown -R www-data:www-data /var/www')

    // this will be the default user inside the container
    ->user('www-data')
    ;
