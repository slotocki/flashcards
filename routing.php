<?php
require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

class Routing {
    public static $routes = [
        'login' => ['controller' => 'SecurityController', 'action' => 'login'],
        'register' => ['controller' => 'SecurityController', 'action' => 'register'],
        'dashboard' => ['controller' => 'DashboardController', 'action' => 'dashboard']
    ];
    
    public static function run(string $path) {
        // Usuń trailing slash jeśli istnieje
        $path = trim($path, '/');
        
        // Sprawdź czy ścieżka istnieje w routach
        if (array_key_exists($path, self::$routes)) {
            $controllerName = self::$routes[$path]['controller'];
            $action = self::$routes[$path]['action'];
            
            $controller = new $controllerName();
            $controller->$action();
        } else {
            // Jeśli nie znaleziono route, pokaż 404
            include 'public/views/404.html';
        }
    }
}