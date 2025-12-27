<?php

declare(strict_types=1);

namespace Slendie\Controllers;

use Slendie\Controllers\Middlewares\WebMiddleware;
use Slendie\Framework\Blade;

abstract class Controller
{
    protected $request;
    protected $formErrors;
    protected $formSuccess;
    protected $errors;
    protected $oldInput;

    public function __construct()
    {
        $this->request = WebMiddleware::getRequest();

        // Get form errors and success message from session
        $this->formErrors = $_SESSION['form_errors'] ?? [];
        $this->formSuccess = $_SESSION['form_success'] ?? null;

        // Clear session messages after retrieving them
        unset($_SESSION['form_errors']);
        unset($_SESSION['form_success']);
    }

    public function request()
    {
        return $this->request;
    }

    public function redirect($url)
    {
        // Em ambiente de teste, não executa exit para permitir que os testes continuem
        if (defined('PHPUNIT_TEST') || getenv('PHPUNIT_TEST')) {
            return;
        }
        header('Location: ' . $url);
        exit;
    }

    public function render($view, $data = [])
    {
        $blade = new Blade();

        if (!array_key_exists('form_errors', $data)) {
            $data['form_errors'] = $this->formErrors;
        }
        if (!array_key_exists('errors', $data)) {
            $data['errors'] = $this->errors;
        }
        if (!array_key_exists('form_success', $data)) {
            $data['form_success'] = $this->formSuccess;
        }

        // Debug temporário - remover depois
        if ($view === 'auth/login' && !empty($data['form_errors'])) {
            echo "<!-- DEBUG: form_errors = " . json_encode($data['form_errors']) . " -->\n";
        }

        $html = $blade->render($view, $data);
        echo $html;
    }
}
