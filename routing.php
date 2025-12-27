<?php
require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';
require_once 'src/controllers/AuthApiController.php';
require_once 'src/controllers/ClassApiController.php';
require_once 'src/controllers/DeckApiController.php';
require_once 'src/controllers/StudyApiController.php';
require_once 'src/controllers/AdminApiController.php';
require_once 'src/controllers/CommunityApiController.php';

class Routing {
    // Tradycyjne routy zwracające HTML
    public static $routes = [
        '' => ['controller' => 'DashboardController', 'action' => 'index'],
        'login' => ['controller' => 'SecurityController', 'action' => 'login'],
        'register' => ['controller' => 'SecurityController', 'action' => 'register'],
        'logout' => ['controller' => 'SecurityController', 'action' => 'logout'],
        'dashboard' => ['controller' => 'DashboardController', 'action' => 'index'],
        'study' => ['controller' => 'DashboardController', 'action' => 'study'],
        'progress' => ['controller' => 'DashboardController', 'action' => 'progress'],
        'teacher' => ['controller' => 'DashboardController', 'action' => 'teacher'],
        'join' => ['controller' => 'DashboardController', 'action' => 'joinClass'],
        'account' => ['controller' => 'DashboardController', 'action' => 'account'],
        'class' => ['controller' => 'DashboardController', 'action' => 'classView'],
        'admin' => ['controller' => 'DashboardController', 'action' => 'admin'],
        'community' => ['controller' => 'DashboardController', 'action' => 'community']
    ];
    
    // API routy zwracające JSON
    public static $apiRoutes = [
        // Auth API
        'api/auth/register' => ['controller' => 'AuthApiController', 'action' => 'register'],
        'api/auth/login' => ['controller' => 'AuthApiController', 'action' => 'login'],
        'api/auth/logout' => ['controller' => 'AuthApiController', 'action' => 'logout'],
        'api/auth/me' => ['controller' => 'AuthApiController', 'action' => 'me'],
        'api/auth/password' => ['controller' => 'AuthApiController', 'action' => 'changePassword'],
        'api/auth/profile' => ['controller' => 'AuthApiController', 'action' => 'updateProfile'],
        
        // Classes API
        'api/classes' => ['controller' => 'ClassApiController', 'action' => 'index'],
        'api/classes/join' => ['controller' => 'ClassApiController', 'action' => 'join'],
        
        // Community API
        'api/community/decks' => ['controller' => 'CommunityApiController', 'action' => 'getPublicDecks'],
        'api/community/subscribed' => ['controller' => 'CommunityApiController', 'action' => 'getSubscribedDecks'],
        
        // Admin API
        'api/admin/users' => ['controller' => 'AdminApiController', 'action' => 'users'],
        'api/admin/classes' => ['controller' => 'AdminApiController', 'action' => 'allClasses'],
    ];

    // Wzorce dynamicznych routów API (z parametrami)
    public static $dynamicApiRoutes = [
        // Classes
        '#^api/classes/(\d+)$#' => ['controller' => 'ClassApiController', 'action' => 'show'],
        '#^api/classes/(\d+)/members$#' => ['controller' => 'ClassApiController', 'action' => 'members'],
        '#^api/classes/(\d+)/members/(\d+)$#' => ['controller' => 'ClassApiController', 'action' => 'removeMember'],
        
        // Decks
        '#^api/classes/(\d+)/decks$#' => ['controller' => 'DeckApiController', 'action' => 'index'],
        '#^api/decks/(\d+)$#' => ['controller' => 'DeckApiController', 'action' => 'show'],
        '#^api/decks/(\d+)/cards$#' => ['controller' => 'DeckApiController', 'action' => 'cards'],
        
        // Study
        '#^api/study/next$#' => ['controller' => 'StudyApiController', 'action' => 'next'],
        '#^api/progress/answer$#' => ['controller' => 'StudyApiController', 'action' => 'answer'],
        '#^api/progress/stats$#' => ['controller' => 'StudyApiController', 'action' => 'stats'],
        '#^api/progress/deck/(\d+)$#' => ['controller' => 'StudyApiController', 'action' => 'deckProgress'],
        
        // Tasks
        '#^api/classes/(\d+)/tasks$#' => ['controller' => 'ClassApiController', 'action' => 'tasks'],
        
        // Community
        '#^api/community/deck/(\d+)$#' => ['controller' => 'CommunityApiController', 'action' => 'getDeckDetails'],
        '#^api/community/share/([a-zA-Z0-9]+)$#' => ['controller' => 'CommunityApiController', 'action' => 'getDeckByShareToken'],
        '#^api/community/deck/(\d+)/subscribe$#' => ['controller' => 'CommunityApiController', 'action' => 'subscribeToDeck'],
        '#^api/community/deck/(\d+)/unsubscribe$#' => ['controller' => 'CommunityApiController', 'action' => 'unsubscribeFromDeck'],
        '#^api/community/deck/(\d+)/rate$#' => ['controller' => 'CommunityApiController', 'action' => 'rateDeck'],
        
        // Admin
        '#^api/admin/users/(\d+)/role$#' => ['controller' => 'AdminApiController', 'action' => 'updateRole'],
        '#^api/admin/users/(\d+)/status$#' => ['controller' => 'AdminApiController', 'action' => 'updateStatus'],
    ];
    
    public static function run(string $path) {
        // Usuń trailing slash jeśli istnieje
        $path = trim($path, '/');
        
        // Sprawdź czy to endpoint API
        if (strpos($path, 'api/') === 0) {
            return self::handleApiRoute($path);
        }
        
        // Sprawdź czy ścieżka istnieje w tradycyjnych routach
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

    private static function handleApiRoute(string $path) {
        // Sprawdź statyczne routy API
        if (array_key_exists($path, self::$apiRoutes)) {
            $controllerName = self::$apiRoutes[$path]['controller'];
            $action = self::$apiRoutes[$path]['action'];
            
            $controller = new $controllerName();
            $controller->$action();
            return;
        }
        
        // Sprawdź dynamiczne routy API (z parametrami)
        foreach (self::$dynamicApiRoutes as $pattern => $route) {
            if (preg_match($pattern, $path, $matches)) {
                $controllerName = $route['controller'];
                $action = $route['action'];
                
                $controller = new $controllerName();
                
                // Usuń pierwszy element (pełne dopasowanie) i przekaż pozostałe jako parametry
                array_shift($matches);
                
                // Wywołaj akcję z parametrami
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }
        
        // Nie znaleziono routy API - zwróć 404 JSON
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Endpoint nie istnieje'
            ]
        ]);
        exit();
    }
}