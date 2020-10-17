<?php

namespace BotMan\Drivers\Slack\Commands;

use React\EventLoop\Factory;
use Illuminate\Console\Command;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Cache\ArrayCache;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Slack\SlackRTMDriver;

class SlackRTMListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'botman:listen-on-slack
                    {--heartbeat-interval=120 : Seconds to wait before checking the connection. Set to 0 to disable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tell BotMan to listen with the Slack RTM API.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var \Illuminate\Foundation\Application $app */
        $app = app('app');
        $loop = Factory::create();

        $app->singleton('botman', function ($app) use ($loop) {
            DriverManager::loadDriver(SlackRTMDriver::class);

            return BotManFactory::createForRTM(config('botman', []), $loop, new ArrayCache());
        });

        if (file_exists(base_path('routes/botman.php'))) {
            require base_path('routes/botman.php');
        }

        //If a heartbeat is set, add a periodic timer to check the connection to the server
        if ($this->option('heartbeat-interval') > 0) {
            $checkConnection = function () {
                $botman = resolve('botman');
                $botman->getDriver()->getClient()->checkConnection();
            };
            $loop->addPeriodicTimer(
                $this->option('heartbeat-interval'),
                $checkConnection
            );
        }

        $loop->run();
    }
}
