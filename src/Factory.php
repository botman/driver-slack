<?php

namespace BotMan\Drivers\Slack;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Cache\ArrayCache;
use BotMan\BotMan\Interfaces\CacheInterface;
use BotMan\BotMan\Interfaces\StorageInterface;
use BotMan\BotMan\Storages\Drivers\FileStorage;
use Illuminate\Support\Collection;
use React\EventLoop\LoopInterface;
use Slack\RealTimeClient;

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

        $client->setToken(Collection::make($config)->get('slack_token'));

        $driver = new SlackRTMDriver($config, $client);
        $botman = new BotMan($cache, $driver, $config, $storageDriver);

        $client->on('_internal_message', function () use ($botman) {
            $botman->listen();
        });

        $client->connect()->then(function () use ($driver) {
            $driver->connected();
        });

        return $botman;
    }
}