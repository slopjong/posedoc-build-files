Posedoc
=======

```
.
|-- builds
`-- images
    `-- example
        |-- apache-php
        |   `-- build.php
        `-- my-webapp
            |-- build.php
            `-- vhost.conf
```

The build files that *bundle* a web application should never install software via the package manager. If you need to install packages, create another docker image with all the system dependencies. In our case `example/my-webapp` bundles a web application and is using `example/apache-php` as its base.

By doing so you can package different web applications with the same base image or package the same web application for different environments (apache, nginx, ubuntu, debian, ...). Before your application is packages a container of the base image will be created and run to download and cache all the dependencies defined in your composer file. Every new build makes use of the composer cache then and avoids unnecessary requests to github in order to speed the build up.

```
<?php

return (new DebianImage())
    ->from('debian', '7.6')
    ->maintainer('John', 'Doe', 'john.doe@example.com')
    ->apt('vim wget git-core curl apache2-mpm-prefork php5 php5-intl php5-cli php5-curl php5-gd')
    ;
```

```
<?php

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
    ->run('chown -R www-data:www-data /var/www')
    ;
```