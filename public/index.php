<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
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
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$fileName = 'dataUsers';

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->post('/users', function ($request, $response) use ($fileName, $router) {
    $user = $request->getParsedBodyParam('user');
//    var_dump($user);
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

    $id = Uuid::uuid4();
    $dataResult = json_decode(file_get_contents($fileName), true);
//        var_dump($dataResult);        
    $dataResult["$id"] = $user;
//        var_dump($dataResult);
    file_put_contents($fileName, json_encode($dataResult));
    $this->get('flash')->addMessage('success', 'Пользователь был успешно создан.');
    return $response->withRedirect($router->urlFor('users'), 302);
});

// 21.CRUD:Обновление (вывод формы обновления)
$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($fileName, $router) {
    $id = $args['id'];
//    var_dump($id);
    $usersAll = json_decode(file_get_contents($fileName), true);

    foreach ($usersAll as $key => $user) {
        if ($key === $id) {
            $userSelected = $user;
        } 
    };    

///    var_dump($userSelected);        

    $params = [
        'id' => $id,
        'user' => $userSelected,
        'userData' => $userSelected,
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editPost');

/* этот обработчик уже не нужен (он выводит show.phtml, его заменили на edit.phtml )
$app->get('/users/{id}', function ($request, $response, $args) use ($fileName) {
    $id = $args['id'];
    $usersAll = json_decode(file_get_contents($fileName), true);
    $userSelected = array_filter($usersAll, fn($user) => $user['id'] === $id);
    $userRequired = reset($userSelected);
    $params = ['userRequired' => $userRequired];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');
*/

// 21.CRUD:Обновление (обработка формы редактирования)
$app->patch('/users/{id}', function ($request, $response, array $args) use ($fileName, $router) {
    $id = $args['id'];

    $userUpdated = $request->getParsedBodyParam('user');
//    var_dump($userUpdated); 
    $errors = validate($userUpdated);

    if (count($errors) !== 0) {
        $params = [
            'id' => $id,
            'user' => $userUpdated,
            'userData' => $userUpdated,
            'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    }

    $usersAll = json_decode(file_get_contents($fileName), true);
    foreach ($usersAll as $key => &$user) {
        if ($key === $id) {
            $user = $userUpdated;
//                var_dump($user);  
        } 
    };
//    var_dump($usersAll);

    file_put_contents($fileName, json_encode($usersAll));
    $this->get('flash')->addMessage('success', 'Пользователь был обновлен');
    $url = $router->urlFor('users');
    return $response->withRedirect($url);
});


$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
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
