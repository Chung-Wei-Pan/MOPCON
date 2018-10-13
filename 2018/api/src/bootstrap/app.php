<?php
require __DIR__ . '/../../../../vendor/autoload.php';
$phinx = require __DIR__ . '/../../../../phinx.php';

// 從 hostname 判斷目前運行環境
$version = 'testing';
if ($_SERVER['HTTP_HOST'] == 'dev.mopcon.org') {
    $version = 'development';
} elseif ($_SERVER['HTTP_HOST'] == 'mopcon.org') {
    $version = 'production';
}
$dbEnvFromPhinx = $phinx['environments']['mopcon2018'];

$config = [
    'settings' => [
        'displayErrorDetails' => true,
        'db' => [
            'driver' => $dbEnvFromPhinx['adapter'],
            'host' => $dbEnvFromPhinx['host'],
            'port' => $dbEnvFromPhinx['port'],
            'database' => $dbEnvFromPhinx['name'],
            'username' => $dbEnvFromPhinx['user'],
            'password' => $dbEnvFromPhinx['pass'],
            'charset' => $dbEnvFromPhinx['charset'],
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ],
        'version' => $version,
        'cache' => [
            'path' => __DIR__ . '/../storage/cache',
        ],
    ],
];

$app = new Slim\App($config);
$container = $app->getContainer();

$container['ApiController'] = function ($container) {
    return new MopConApi2018\App\Http\ApiController($container);
};

$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($container['settings']['db']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$container['db'] = function ($container) use ($capsule) {
    return $capsule;
};

$container['isProduction'] = function () use ($config) {
    return $config['settings']['version'] == 'production';
};

$container['cache'] = function () use ($config) {
    return $cache = new \Wruczek\PhpFileCache\PhpFileCache(
        $config['settings']['cache']['path']
    );
};

$app->get('/2018/api/__info__', function () {
    try {
        $ch = curl_init();
        // 484 該更新了？ 4...
        curl_setopt($ch, CURLOPT_URL, "https://hackmd.io/s/ByvLG0oWX");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 2);
        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        echo $data;
    } catch (Exception $e) {
        echo '>_____________________<';
    }
});

$app->get('/2018/api/devQrcode/{id}', function ($request, $response, $params) {

    $booths = [];
    for ($i = 1; $i < 11; $i++) {
        $booths[$i] = [
            'token' => "mopconbooth_$i",
            'reward' => $i * 5,
        ];
    }

    $booth = $booths[$params['id']];

    $result = [
        // token 是讓 server 辨認攤位，取得對應的任務獎勵並發送
        'id' => $params['id'],
        'token' => $booth['token'],
    ];

    $result['qr'] = 'http://chart.apis.google.com/chart?cht=qr&chl=' . urlencode(json_encode($result)) . '&chs=150x150';

    echo "<img src='$result[qr]'>";
});

// 用 group 可以從 global 分離出來
$app->group('/2018/api', function () {
    $this->any('/{routes:.*}', 'ApiController');
})->add(new MopConApi2018\App\Http\ApiMiddleware($container));

$app->run();