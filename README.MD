Fall mvc
=
Fall mvc contains all classes needed to handle http requests and response.

Installation
=
    composer install fall\mvc --save
Then add require in your root php script

    require_once vendor/autoload.php


Usage
=
Create http server

    use fall\mvc\web\server\HttpServer;

    $server = new HttpServer(80); // 80 is the listening port
