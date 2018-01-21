<?php

namespace BotMan\Drivers\Slack\Providers;

use BotMan\Drivers\Slack\SlackDriver;
use Illuminate\Support\ServiceProvider;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Studio\Providers\StudioServiceProvider;
use BotMan\Drivers\Slack\Commands\SlackRTMListenCommand;

class SlackServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (! $this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__.'/../../stubs/slack.php' => config_path('botman/slack.php'),
            ]);

            $this->mergeConfigFrom(__DIR__.'/../../stubs/slack.php', 'botman.slack');

            $this->commands([
                SlackRTMListenCommand::class,
            ]);
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(SlackDriver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
