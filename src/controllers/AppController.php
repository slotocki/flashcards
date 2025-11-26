<?php

class AppController 
{
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }   
    
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function render(string $template = null, array $variables = [])
    {
        $templatePath = 'public/views/'. $template.'.html';
        
        // Ustaw zmienne jako dostępne w widoku
        extract($variables);
        
        // Sprawdź czy plik istnieje
        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            $content = ob_get_clean();
            echo $content;
        } else {
            include 'public/views/404.html';
        }
    }
}