<?php

namespace BotMan\Drivers\Slack;

use BotMan\BotMan\BotMan;
use Slack\RealTimeClient;
use Illuminate\Support\Collection;
use React\EventLoop\LoopInterface;
use BotMan\BotMan\Cache\ArrayCache;
use BotMan\BotMan\Interfaces\CacheInterface;
use BotMan\BotMan\Interfaces\StorageInterface;
use BotMan\BotMan\Storages\Drivers\FileStorage;

class Factory
{
    /**
     * Create a new BotMan instance.
     *
     * @param array $config
     * @param LoopInterface $loop
     * @param CacheInterface $cache
     * @param StorageInterface $storageDriver
     * @return \BotMan\BotMan\BotMan
     */
    public function createForRTM(
        array $config,
        LoopInterface $loop,
        CacheInterface $cache = null,
        StorageInterface $storageDriver = null
    ) {
        $client = new RealTimeClient($loop);

        return $this->createUsingRTM($config, $client, $cache, $storageDriver);
    }

    /**
     * Create a new BotMan instance.
     *
     * @param array $config
     * @param RealTimeClient $client
     * @param CacheInterface $cache
     * @param StorageInterface $storageDriver
     * @return BotMan
     * @internal param LoopInterface $loop
     */
    public function createUsingRTM(
        array $config,
        RealTimeClient $client,
        CacheInterface $cache = null,
        StorageInterface $storageDriver = null
    ) {
        if (empty($cache)) {
            $cache = new ArrayCache();
        }

        if (empty($storageDriver)) {
            $storageDriver = new FileStorage(__DIR__);
        }

        $config = Collection::make(Collection::make($config)->get('slack', []));
        $client->setToken($config->get('token'));

        $driver = new SlackRTMDriver($config->toArray(), $client);
        $botman = new BotMan($cache, $driver, $config->toArray(), $storageDriver);

        $client->on('_internal_message', function () use ($botman) {
            $botman->listen();
        });

        $client->connect()->then(function () use ($driver) {
            echo "\033[32mSuccessfully connected\033[0m".PHP_EOL;
            $driver->connected();
        })->otherwise(function (\Exception $e) {
            echo "\033[31mError: ".$e->getMessage()."\033[0m".PHP_EOL;
        });

        return $botman;
    }
}
