<?php
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;

require(__DIR__ . "/vendor/autoload.php");

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');

$container = new Container();

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuración de la base de datos
$container->set("db", function() {
     /*$con = array(
        "host" => $_ENV["DB_HOST"] ?? "localhost",
        "dbname" => $_ENV["DB_NAME"] ?? "u565673608_karttem",
        "user" => $_ENV["DB_USER"] ?? "u565673608_karttem",
        "pass" => $_ENV["DB_PASS"] ?? "9c]ZUHWlT8;#"
    );*/
        $con = array(
            "host" => $_ENV["DB_HOST"] ?? "localhost",
            "dbname" => $_ENV["DB_NAME"] ?? "u565673608_karttem_local",
            "user" => $_ENV["DB_USER"] ?? "root",
            "pass" => $_ENV["DB_PASS"] ?? ""
        );

    
    try {
        $pdo = new PDO(
            "mysql:host=" . $con["host"] . ";dbname=" . $con["dbname"], 
            $con["user"], 
            $con["pass"], 
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        return $pdo;
    } catch(PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
        exit;
    }
});

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

$app = AppFactory::create();

$app->setBasePath(preg_replace("/(.*)\/.*/", "$1", $_SERVER["SCRIPT_NAME"]));

// Middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// JWT Authentication Middleware
$app->add(new \Tuupola\Middleware\JwtAuthentication([
    "ignore" => [
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/user/login",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/user/register",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/user/password/recover",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/user/password/temp",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/user/token/validate",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/properties",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/property",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/properties/featured",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/properties/search",
        "/" . basename(dirname($_SERVER["PHP_SELF"])) . "/owner/document",

    ],
    "secret" => $_ENV["JWT_SECRET_KEY"],
    "algorithm" => $_ENV["JWT_ALGORITHM"],
    "attribute" => "jwt",
    "error" => function($response, $arguments) {
        $data["ok"] = false;
        $data["msg"] = $arguments["message"];
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        return $response->withHeader("Content-Type", "application/json");
    }
]));

// Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// CORS Pre-Flight OPTIONS Request Handler
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Ruta principal
$app->get("/", function (Request $request, Response $response, array $args) {
    $data = [
        "ok" => true,
        "msg" => "Real Estate API v1.0",
        "timestamp" => time()
    ];
    $response->getBody()->write(json_encode($data)); 
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

// Incluir rutas
require_once("routes/r_users.php");
require_once("routes/r_properties.php");
require_once("routes/r_owners.php");


// Manejo de rutas no encontradas
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    throw new HttpNotFoundException($request);
});

// Iniciar la aplicación
$app->run();