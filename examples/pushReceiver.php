<?php

set_time_limit(0);
date_default_timezone_set('UTC');

require __DIR__.'/../vendor/autoload.php';

/////// CONFIG ///////
$username = '';
$password = '';
$debug = true;
$truncatedDebug = false;
//////////////////////

$ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);

try {
    $ig->setUser($username, $password);
    $ig->login();
} catch (\Exception $e) {
    echo 'Something went wrong: '.$e->getMessage()."\n";
    exit(0);
}

$loop = \React\EventLoop\Factory::create();
if ($debug) {
    $logger = new \Monolog\Logger('push');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::INFO));
} else {
    $logger = null;
}
$push = new \InstagramAPI\Push($loop, $ig, $logger);
$push->on('incoming', function (\InstagramAPI\Push\Notification $push) {
    printf('%s%s', $push->getMessage(), PHP_EOL);
});
$push->on('like', function (\InstagramAPI\Push\Notification $push) {
    printf('Media ID: %s%s', $push->getActionParam('id'), PHP_EOL);
});
$push->on('comment', function (\InstagramAPI\Push\Notification $push) {
    printf(
        'Media ID: %s. Comment ID: %s.%s',
        $push->getActionParam('id'),
        $push->getActionParam('forced_preview_comment_id'),
        PHP_EOL
    );
});
$push->on('direct_v2_message', function (\InstagramAPI\Push\Notification $push) {
    printf(
        'Thread ID: %s. Thread item ID: %s.%s',
        $push->getActionParam('id'),
        $push->getActionParam('x'),
        PHP_EOL
    );
});
$push->on('error', function (\Exception $e) use ($push, $loop) {
    printf('[!!!] Got fatal error from FBNS: %s%s', $e->getMessage(), PHP_EOL);
    $push->stop();
    $loop->stop();
});
$push->start();

$loop->run();