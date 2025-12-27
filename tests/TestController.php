<?php

declare(strict_types=1);

namespace tests;

use Slendie\Controllers\Controller;

// Classe de controller de teste que expõe métodos protegidos
final class TestController extends Controller
{
    public static $calledMethod = null;
    public static $calledArgs = [];
    public static $output = '';

    public static function reset()
    {
        self::$calledMethod = null;
        self::$calledArgs = [];
        self::$output = '';
    }

    public function getRequest()
    {
        return $this->request();
    }

    public function testRedirect($url)
    {
        return $this->redirect($url);
    }

    public function testRender($view, $data = [])
    {
        return $this->render($view, $data);
    }

    public function getFormErrors()
    {
        return $this->formErrors;
    }

    public function getFormSuccess()
    {
        return $this->formSuccess;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getOldInput()
    {
        return $this->oldInput;
    }

    public function index()
    {
        self::$calledMethod = 'index';
        self::$calledArgs = func_get_args();
        return 'index output';
    }

    public function show($id)
    {
        self::$calledMethod = 'show';
        self::$calledArgs = func_get_args();
        return 'show output: ' . $id;
    }

    public function edit($id, $action)
    {
        self::$calledMethod = 'edit';
        self::$calledArgs = func_get_args();
        return 'edit output: ' . $id . ' ' . $action;
    }

    public function create()
    {
        self::$calledMethod = 'create';
        self::$calledArgs = func_get_args();
        return 'create output';
    }

    public function store()
    {
        self::$calledMethod = 'store';
        self::$calledArgs = func_get_args();
        return 'store output';
    }
}

