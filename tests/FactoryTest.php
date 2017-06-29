<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\BotMan;
use React\EventLoop\Factory;
use PHPUnit_Framework_TestCase;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Slack\SlackRTMDriver;

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