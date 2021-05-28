<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use Ramsey\Uuid\Uuid;

//const FILE_NAME = "__DIR__ . '/../' . dataUsers";
const FILE_NAME = '__DIR__ . /../dataUsers';
$fileName = FILE_NAME;

session_start();

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

// создание нового пользователя (вывод формы)
$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

// обработка формы создания пользователя
$app->post('/users', function ($request, $response) use ($fileName, $router) {
    $user = $request->getParsedBodyParam('user');
    $errors = validate($user);
    if (count($errors) !== 0) {
        $params = [
            'user' => $user,
            'userData' => $user,
            'errors' => $errors
        ];
        $response = $response->withStatus(422);
        return $this->get('renderer')->render($response, 'users/new.phtml', $params);
    }

    $id = (string)Uuid::uuid4();
    $dataResult = readUsersFile(FILE_NAME);
    $dataResult[$id] = $user;
    writeUsersFile(FILE_NAME, $dataResult);
    $this->get('flash')->addMessage('success', 'Пользователь был успешно создан.');
    return $response->withRedirect($router->urlFor('users'), 302);
});

// 21.CRUD:Обновление (вывод формы обновления)
$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($fileName, $router) {
    $id = $args['id'];
    $usersAll = readUsersFile(FILE_NAME);
    $userSelected = $usersAll[$id];
    $params = [
        'id' => $id,
        'userData' => $userSelected,
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editPost');

// 21.CRUD:Обновление (обработка формы редактирования)
$app->patch('/users/{id}', function ($request, $response, array $args) use ($fileName, $router) {
    $id = $args['id'];

    $userUpdated = $request->getParsedBodyParam('user');
    $errors = validate($userUpdated);

    if (count($errors) !== 0) {
        $params = [
            'id' => $id,
            'userData' => $userUpdated,
            'errors' => $errors
        ];

        $response = $response->withStatus(422);
        return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    }

    $usersAll = readUsersFile(FILE_NAME);
    $usersAll[$id] = $userUpdated;
    writeUsersFile(FILE_NAME, $usersAll);
    $this->get('flash')->addMessage('success', 'Пользователь был обновлен');
    $url = $router->urlFor('users');
    return $response->withRedirect($url);
});

// 22.CRUD:Удаление (обработка кнопки формы удаления[в шаблоне edit.phtml])
$app->delete('/users/{id}', function ($request, $response, array $args) use ($fileName, $router) {
    $id = $args['id'];
    $usersAll = readUsersFile(FILE_NAME);
    unset($usersAll[$id]);
    writeUsersFile(FILE_NAME, $usersAll);
    $this->get('flash')->addMessage('success', 'Пользователь был удален');
    $url = $router->urlFor('users');
    return $response->withRedirect($url);
});

// 22.Сессия (аутентификация)
$app->get('/', function ($request, $response) {

    $messages = $this->get('flash')->getMessages();
         $params = [
            'correctUser' => $_SESSION['user'] ?? null,
            'flash' => $messages
            ];
         return $this->get('renderer')->render($response, 'users/enter.phtml', $params);
})->setName('/');

$app->post('/session', function ($request, $response) use ($fileName) {
    $inputEmail = $request->getParsedBodyParam('email');
    $usersAll = readUsersFile(FILE_NAME);
//    print_r($usersAll);
    $testedUser = array_filter($usersAll, function ($user) use ($inputEmail) {
        if ($user['email'] == $inputEmail) {
            return $user;
        }
    });
    $correctUser = reset($testedUser);

    if ($correctUser) {
        $_SESSION['user'] = $correctUser;
    } else {
        $this->get('flash')->addMessage('error', 'Введен неверный E-mail');
    }

    return $response->withRedirect('/');
});

$app->delete('/session', function ($request, $response) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect('/users');
});

// все пользователи
$app->get('/users', function ($request, $response) use ($fileName) {
    $message = $this->get('flash')->getMessages();
    $usersAll = readUsersFile(FILE_NAME);
    $params = [
        'users' => $usersAll,
        'flash' => $message
    ];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

function validate(array $user)
{
    $errors = [];
    if ($user['nickname'] === '') {
        $errors['nickname'] = "Необходимо заполнить!";
    }

    if ($user['email'] === '') {
        $errors['email'] = "Необходимо заполнить!";
    }

    return $errors;
}

function readUsersFile(string $fileName): array
{
    return json_decode(file_get_contents($fileName), true);
}

function writeUsersFile(string $fileName, array $data)
{
    file_put_contents($fileName, json_encode($data));
}

$app->run();
