<?php
declare(strict_types = 1);

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

ini_set("session.cookie_httponly", true);
ini_set("session.use_only_cookies", true);
ini_set("session.use_trans_sid", false);
session_name("PIGDIN");
//session_start();

require '../pigdinapi/vendor/autoload.php';
require 'init.php';

$configs = ['settings' => [
        'displayErrorDetails' => true,
        'determineRouteBeforeAppMiddleware' => true,
        'addContentLengthHeader' => false
    ]
];

$container = new \Slim\Container($configs);

$isDevMode = true;

/**
 * Diretório de Entidades e Metadata do Doctrine
 */
$config = Setup::createAnnotationMetadataConfiguration(array(__DIR__ . "/Entity"), $isDevMode);

/**
 * Array de configurações da nossa conexão com o banco
 */
$conn = array(
    'dbname' => MYSQL_DBNAME,
    'user' => MYSQL_USER,
    'password' => MYSQL_PASS,
    'host' => MYSQL_HOST,
    'driver' => 'pdo_mysql'
);

/**
 * Instância do Entity Manager
 */
$entityManager = EntityManager::create($conn, $config);

/**
 * Coloca o Entity manager dentro do container com o nome de em (Entity Manager)
 */
$container['em'] = $entityManager;

$container['jwt'] = function ($container) {
    return function ($token) use ($container) {
        return $container['token'];
    };
};

$container['errorHandler'] = function ($container) {
    return function ($request, $response, $exception) use ($container) {
        $statusCode = $exception->getCode() >= 200 ? $exception->getCode() : 500;

        try {
            $error = $exception->getData();
        } catch (\Error $ex) {
            $error = array(
                "error" => true, 
                "message" => $exception->getMessage(),
                "line" => $exception->getLine(), 
                "error" => $exception->getFile(),
                "trace" => $exception->getTrace()
            );
        }
        //$getError = !is_null($exception->getData()) ? $exception->getData() : $exception->getError();

        return $container['response']->withStatus($statusCode)
                ->withHeader('Access-Control-Allow-Origin', HOST_CORS)
                ->withHeader('Content-Type', 'Application/json')
                ->withJson($error);
    };
};
$container['notAllowedHandler'] = function ($container) {
    return function ($request, $response, $methods) use ($container) {
        return $container['response']
                ->withStatus(405)
                ->withHeader('Access-Control-Allow-Origin', HOST_CORS)
                ->withHeader('Allow', implode(', ', $methods))
                ->withHeader('Content-Type', 'Application/json')
                ->withHeader("Access-Control-Allow-Methods", implode(",", $methods))
                ->withJson(["message" => "Method not Allowed; Method must be one of: " . implode(', ', $methods)], 405);
    };
};
$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        return $container['response']
                ->withStatus(404)
                ->withHeader('Access-Control-Allow-Origin', HOST_CORS)
                ->withHeader('Content-Type', 'Application/json')
                ->withJson(['message' => 'Page not found']);
    };
};

$app = new \Slim\App($container);

use App\Middleware\AuthMiddleware;
use App\Middleware\Headers;

$app->add(new Headers);
$app->add(new AuthMiddleware($container));

//$app->add(function($request, $response, $next) {
//    $route = $request->getAttribute("route");
//
//    $methods = [];
//
//    if (!empty($route)) {
//        $pattern = $route->getPattern();
//
//        foreach ($this->router->getRoutes() as $route) {
//            if ($pattern === $route->getPattern()) {
//                $methods = array_merge_recursive($methods, $route->getMethods());
//            }
//        }
//        //Methods holds all of the HTTP Verbs that a particular route handles.
//    } else {
//        $methods[] = $request->getMethod();
//    }
//
//    $response = $next($request, $response);
//
//
//    return $response->withHeader("Access-Control-Allow-Methods", implode(",", $methods));
//});
//Chama a index
//new App\Controllers\SiteController($app);

require 'routes.php';

$app->run();
