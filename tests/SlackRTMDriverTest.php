<?php

namespace Tests;

use Slack\File;
use Mockery as m;
use Slack\RealTimeClient;
use React\EventLoop\Factory;
use PHPUnit_Framework_TestCase;
use React\Promise\FulfilledPromise;
use BotMan\Drivers\Slack\SlackRTMDriver;
use BotMan\Drivers\Slack\Extensions\User;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage as OutgoingMessage;

class SlackRTMDriverTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    private function getDriver($responseData = [], $htmlInterface = null)
    {
        $loop = Factory::create();
        $client = new RealTimeClient($loop);
        $driver = new SlackRTMDriver([], $client);
        $client->emit('_internal_message', ['message', $responseData]);

        return $driver;
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('SlackRTM', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([]);
        $this->assertFalse($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getDriver([
            'user' => 'U0X12345',
            'text' => 'Hi Julia',
        ]);
        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getDriver([
            'user' => 'U0X12345',
            'text' => 'Hi Julia',
        ]);
        $this->assertSame('Hi Julia', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_detects_bots()
    {
        $driver = $this->getDriver([
            'user' => 'U0X12345',
            'text' => 'Hi Julia',
        ]);
        $messages = $driver->getMessages();
        $this->assertFalse($messages[0]->isFromBot());

        $driver = $this->getDriver([
            'user' => 'U0X12345',
            'bot_id' => 'foo',
            'text' => 'Hi Julia',
        ]);
        $messages = $driver->getMessages();
        $this->assertTrue($messages[0]->isFromBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getDriver([
            'user' => 'U0X12345',
        ]);

        $this->assertSame('U0X12345', $driver->getMessages()[0]->getSender());

        $driver = $this->getDriver([
            'user' => ['id' => 'U0X12345'],
        ]);

        $this->assertSame('U0X12345', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_user_object()
    {
        $responseData = [
            'user' => [
                'id' => 'U0X12345',
                'name' => 'botman',
                'deleted' => false,
                'color' => '9f69e7',
                'profile' => [
                    'avatar_hash' => 'ge3b51ca72de',
                    'status_emoji' => ':mountain_railway:',
                    'status_text' => 'riding a train',
                    'first_name' => 'Bot',
                    'last_name' => 'Man',
                    'real_name' => 'Bot Man',
                    'tz' => 'America\/Los_Angeles',
                    'tz_label' => 'Pacific Daylight Time',
                    'tz_offset' => -25200,
                    'email' => 'botman@foo.bar',
                    'skype' => 'my-skype-name',
                    'phone' => '+1 (123) 456 7890',
                    'image_24' => 'http://via.placeholder.com/24',
                    'image_32' => 'http://via.placeholder.com/32',
                    'image_48' => 'http://via.placeholder.com/48',
                    'image_72' => 'http://via.placeholder.com/72',
                    'image_192' => 'http://via.placeholder.com/192',
                ],
                'is_admin' => true,
                'is_owner' => true,
                'is_primary_owner' => true,
                'updated' => 1490054400,
                'has_2fa' => true,
            ],
            'type' => 'message',
            'channel' => 'general',
            'text' => 'response',
        ];

        $driver = $this->getDriver([
            'user' => 'U0X12345',
        ]);

        $user = new User($driver->getClient(), $responseData['user']);

        $this->assertSame('U0X12345', $user->getId());
        $this->assertSame('Bot', $user->getFirstName());
        $this->assertSame('Man', $user->getLastName());
        $this->assertSame('botman', $user->getUsername());
        $this->assertSame('Bot Man', $user->getRealName());
        $this->assertSame('botman@foo.bar', $user->getEmail());
        $this->assertSame('+1 (123) 456 7890', $user->getPhone());
        $this->assertSame('my-skype-name', $user->getSkype());
        $this->assertSame(true, $user->isAdmin());
        $this->assertSame(true, $user->isOwner());
        $this->assertSame(true, $user->isPrimaryOwner());
        $this->assertSame(false, $user->isDeleted());
        $this->assertSame(':mountain_railway:', $user->getStatusEmoji());
        $this->assertSame('riding a train', $user->getStatusText());
        $this->assertSame('http://via.placeholder.com/24', $user->getProfileImage24());
        $this->assertSame('http://via.placeholder.com/32', $user->getProfileImage32());
        $this->assertSame('http://via.placeholder.com/48', $user->getProfileImage48());
        $this->assertSame('http://via.placeholder.com/72', $user->getProfileImage72());
        $this->assertSame('http://via.placeholder.com/192', $user->getProfileImage192());
        $this->assertSame('9f69e7', $user->getColor());
        $this->assertSame(1490054400, $user->getUpdated());
        $this->assertSame($responseData['user'], $user->getInfo());
        $this->assertSame($responseData['user'], $user->getRawUser());
    }

    /** @test */
    public function it_returns_the_channel_id()
    {
        $driver = $this->getDriver([
            'user' => 'U0X12345',
            'channel' => 'general',
        ]);

        $this->assertSame('general', $driver->getMessages()[0]->getRecipient());

        $driver = $this->getDriver([
            'user' => 'U0X12345',
            'channel' => ['id' => 'general'],
        ]);

        $this->assertSame('general', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_calls_files_upload_api()
    {
        $filePath = __FILE__;

        $channelId = uniqid();

        $loop = Factory::create();

        $client = new RealTimeClient($loop);

        $clientMock = m::mock($client);

        $clientMock->shouldReceive('fileUpload')
            ->with(m::on(function (File $file) use ($filePath) {
                return $file->getPath() === $filePath;
            }), [$channelId])
            ->once()
            ->andReturn(new FulfilledPromise([]));

        $driver = new SlackRTMDriver([], $clientMock);

        $message = OutgoingMessage::create('File')
            ->withAttachment(\BotMan\BotMan\Messages\Attachments\File::url($filePath));

        $matchingMessage = new IncomingMessage('A command', 'U0X12345', $channelId);

        $driver->sendPayload($driver->buildServicePayload($message, $matchingMessage));
    }

    /**
     * @test
     **/
    public function it_returns_false_for_check_if_conv_callbacks_are_stored_serialized()
    {
        $this->assertFalse($this->getDriver()->serializesCallbacks());
    }
}
