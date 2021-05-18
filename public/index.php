<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Ramsey\Uuid\Uuid;

session_start();

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

//$app = AppFactory::create();

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];
$fileName = 'dataUsers';

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => '', 'id' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->post('/users', function ($request, $response) use ($fileName, $router) {
    $this->get('flash')->addMessage('success', 'Пользователь был успешно создан.');
    $user = $request->getParsedBodyParam('user');

    $errors = validate($user);
    if (count($errors) === 0) {
        $user['id'] = Uuid::uuid4();
        $dataResult = json_decode(file_get_contents($fileName), true);
        $dataResult[count($dataResult) + 1] = $user;
        file_put_contents($fileName, json_encode($dataResult));
        return $response->withRedirect($router->urlFor('users'), 302);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

$app->get('/users/{id}', function ($request, $response, $args) use ($fileName) {
    $id = $args['id'];
    $usersAll = json_decode(file_get_contents($fileName), true);
    $userSelected = array_filter($usersAll, fn($user) => $user['id'] === $id);
    $userRequired = reset($userSelected);
    $params = ['userRequired' => $userRequired];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
})->setName('/');

$app->get('/users', function ($request, $response) use ($fileName) {
    $message = $this->get('flash')->getMessages();
    $usersAll = json_decode(file_get_contents($fileName), true);
    $params = [
        'users' => $usersAll,
        'flash' => $message
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

function validate(array $user)
{
    $errors = [];
    if ($user['nickname'] === '') {
        $errors['nickname'] = "Надо заполнить!";
    }

    if ($user['email'] === '') {
        $errors['email'] = "Заполнить!!!";
    }

    return $errors;
}

$app->run();
