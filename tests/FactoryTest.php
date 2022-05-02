<?php

namespace Tests;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Slack\SlackRTMDriver;
use Mockery as m;
use PHPUnit_Framework_TestCase;
use React\EventLoop\Factory;

class FactoryTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     *@runInSeparateProcess
     */
    public function it_registers_factory_methods()
    {
        DriverManager::loadDriver(SlackRTMDriver::class);
        $bot = BotManFactory::createForRTM([], Factory::create());
        $this->assertInstanceOf(BotMan::class, $bot);
    }
}
