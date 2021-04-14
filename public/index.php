<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

//$app = AppFactory::create();

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];
$fileName = 'dataUsers';

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => '', 'id' => '']
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->post('/users', function ($request, $response) use ($fileName, $router) {
    $user = $request->getParsedBodyParam('user');
    $dataResult = json_decode(file_get_contents($fileName), true);
    $dataResult[count($dataResult) + 1] = $user;
    file_put_contents($fileName, json_encode($dataResult));
    return $response->withRedirect($router->urlFor('users'), 302);
});

$app->get('/users', function ($request, $response) use ($fileName) {
    $usersAll = json_decode(file_get_contents($fileName), true);
    $params = [
        'users' => $usersAll
    ];
    return $this->get('renderer')->render($response, 'users/users.phtml', $params);
})->setName('users');

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
})->setName('/');

/*$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term');
    $selectedUsers = $users;
    if (!is_null($term)) {
        $selectedUsers = array_filter($users, fn($user) =>
        strpos($user, $term) !== false);
    }

    $params = ['users' => $selectedUsers, 'term' => $term];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
}); */

//$app->post('/users', function ($request, $response) {
//    return $response->withStatus(302);
//});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});



$app->run();
