<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use Illuminate\Support\Collection;
use BotMan\Drivers\Slack\SlackDriver;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BotMan\BotMan\Middleware\MiddlewareManager;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class SlackDriverTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new SlackDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('Slack', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'event' => [
                'text' => 'bar',
            ],
        ]);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'bar',
            ],
        ]);
        $this->assertTrue($driver->matchesRequest());
    }

    private function getRequest($responseData)
    {
        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        return $request;
    }

    /** @test */
    public function it_matches_the_request_for_outgoing_webhooks()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'text' => 'hello',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_matches_the_request_for_a_slash_command()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'command' => '/botman',
            'text' => 'hello',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_object_for_outgoing_webhooks()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'text' => 'Hi Julia',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $this->assertTrue(is_array($driver->getMessages()));
        $this->assertSame('Hi Julia', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_message_object_for_slash_commands()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'command' => '/botman',
            'text' => 'Hi Julia',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $this->assertTrue(is_array($driver->getMessages()));
        $this->assertSame('/botman Hi Julia', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'Hi Julia',
            ],
        ]);
        $this->assertSame('Hi Julia', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_user_object()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ];

        $response = new Response('{"ok":true,"user":{"id": "U0X12345","name": "botman","deleted": false,"color": "9f69e7","profile": {"avatar_hash": "ge3b51ca72de","status_emoji": ":mountain_railway:","status_text": "riding a train","first_name": "Bot","last_name": "Man","real_name": "Bot Man","email": "botman@foo.bar","skype": "my-skype-name","phone": "+1 (123) 456 7890","image_24": "http:\/\/via.placeholder.com\/24","image_32": "http:\/\/via.placeholder.com\/32","image_48": "http:\/\/via.placeholder.com\/48","image_72": "http:\/\/via.placeholder.com\/72","image_192": "http:\/\/via.placeholder.com\/192","image_512": "http:\/\/via.placeholder.com\/512"},"is_admin": true, "is_owner": true,"is_primary_owner": true,"is_restricted": false,"is_ultra_restricted": false,"updated": 1490054400,"has_2fa": false,"two_factor_type": "sms"}}');

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/users.info', [], [
                'token' => 'Foo',
                'user' => 'U0X12345',
            ])
            ->andReturn($response);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = $driver->getMessages()[0];
        $user = $driver->getUser($message);
        $rawUserData = json_decode($response->getContent(), true)['user'];

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
        $this->assertSame($rawUserData, $user->getInfo());
        $this->assertSame($rawUserData, $user->getRawUser());
    }

    /** @test */
    public function it_detects_bots()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'text' => 'Hi Julia',
            ],
        ]);
        $messages = $driver->getMessages();
        $this->assertFalse($messages[0]->isFromBot());

        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'bot_id' => 'foo',
                'text' => 'Hi Julia',
            ],
        ]);
        $messages = $driver->getMessages();
        $this->assertTrue($messages[0]->isFromBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
            ],
        ]);
        $this->assertSame('U0X12345', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_user_id_for_outgoing_webhooks()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'text' => 'Hi Julia',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $this->assertTrue(is_array($driver->getMessages()));
        $this->assertSame('U1234567890', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_channel_id()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
            ],
        ]);
        $this->assertSame('general', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_the_channel_id_for_outgoing_webhooks()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'text' => 'Hi Julia',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $this->assertTrue(is_array($driver->getMessages()));
        $this->assertSame('C1234567890', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_the_message_for_conversation_answers()
    {
        $driver = $this->getDriver([
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ]);

        $message = new IncomingMessage('response', 'U0X12345', 'general');
        $this->assertSame('response', $driver->getConversationAnswer($message)->getText());
        $this->assertSame($message, $driver->getConversationAnswer($message)->getMessage());
    }

    /** @test */
    public function it_detects_users_from_interactive_messages()
    {
        $request = new \Symfony\Component\HttpFoundation\Request([], [
            'payload' => file_get_contents(__DIR__.'/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $this->assertSame('U045VRZFT', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_detects_bots_from_interactive_messages()
    {
        $request = new \Symfony\Component\HttpFoundation\Request([], [
            'payload' => file_get_contents(__DIR__.'/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $messages = $driver->getMessages();
        $this->assertFalse($messages[0]->isFromBot());
    }

    /** @test */
    public function it_detects_channels_from_interactive_messages()
    {
        $request = new \Symfony\Component\HttpFoundation\Request([], [
            'payload' => file_get_contents(__DIR__.'/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $this->assertSame('C065W1189', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_answer_from_interactive_messages()
    {
        $request = new \Symfony\Component\HttpFoundation\Request([], [
            'payload' => file_get_contents(__DIR__.'/payload.json'),
        ]);
        $driver = new SlackDriver($request, [], new Curl());

        $message = new IncomingMessage('', '', '');
        $this->assertSame('yes', $driver->getConversationAnswer($message)->getValue());
    }

    /** @test */
    public function it_can_reply_string_messages()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'as_user' => true,
                'token' => 'Foo',
                'channel' => 'general',
                'text' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = new IncomingMessage('', 'U0X12345', 'general');
        $driver->sendPayload($driver->buildServicePayload('Test', $message));
    }

    /** @test */
    public function it_can_reply_message_objects()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'as_user' => true,
                'token' => 'Foo',
                'channel' => 'general',
                'text' => 'Test',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = new IncomingMessage('', 'U0X12345', 'general');
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test'), $message));
    }

    /** @test */
    public function it_can_reply_message_objects_with_image()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'as_user' => true,
                'token' => 'Foo',
                'channel' => 'general',
                'text' => 'Test',
                'attachments' => json_encode(['image_url' => 'http://image.url/foo.png']),
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = new IncomingMessage('', 'U0X12345', 'general');
        $driver->sendPayload($driver->buildServicePayload(\BotMan\BotMan\Messages\Outgoing\OutgoingMessage::create('Test', Image::url('http://image.url/foo.png')), $message));
    }

    /** @test */
    public function it_can_reply_string_messages_for_outgoing_webhooks()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'text' => 'Hi Julia',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $driver->sendPayload($driver->buildServicePayload('test', $driver->getMessages()[0]));
    }

    /** @test */
    public function it_can_reply_string_messages_for_slash_commands()
    {
        $request = new Request([], [
            'token' => '1234567890',
            'team_id' => 'T046C3T',
            'team_domain' => 'botman',
            'service_id' => '1234567890',
            'channel_id' => 'C1234567890',
            'channel_name' => 'botman',
            'timestamp' => '1481125473.000011',
            'user_id' => 'U1234567890',
            'user_name' => 'marcel',
            'command' => '/botman',
            'text' => 'Hi Julia',
        ]);
        $driver = new SlackDriver($request, [], m::mock(Curl::class));
        $driver->sendPayload($driver->buildServicePayload('test', $driver->getMessages()[0]));
    }

    /** @test */
    public function it_can_reply_questions_with_additional_button_parameters()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ];

        $question = Question::create('How are you doing?')
            ->addButton(Button::create('Great')->additionalParameters(['style' => 'danger']))
            ->addButton(Button::create('Good'));

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'as_user' => true,
                'token' => 'Foo',
                'channel' => 'general',
                'text' => '',
                'attachments' => '[{"text":"How are you doing?","fallback":null,"callback_id":null,"actions":[{"name":"Great","text":"Great","image_url":null,"type":"button","value":null,"style":"danger"},{"name":"Good","text":"Good","image_url":null,"type":"button","value":null}]}]',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = new IncomingMessage('', '', 'general');
        $driver->sendPayload($driver->buildServicePayload($question, $message));
    }

    /** @test */
    public function it_can_reply_questions()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ];

        $question = Question::create('How are you doing?')
            ->addButton(Button::create('Great'))
            ->addButton(Button::create('Good'));

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'as_user' => true,
                'token' => 'Foo',
                'channel' => 'general',
                'text' => '',
                'attachments' => '[{"text":"How are you doing?","fallback":null,"callback_id":null,"actions":[{"name":"Great","text":"Great","image_url":null,"type":"button","value":null},{"name":"Good","text":"Good","image_url":null,"type":"button","value":null}]}]',
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = new IncomingMessage('', '', 'general');
        $driver->sendPayload($driver->buildServicePayload($question, $message));
    }

    /** @test */
    public function it_can_reply_with_additional_parameters()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'as_user' => true,
                'token' => 'Foo',
                'channel' => 'general',
                'text' => 'Test',
                'username' => 'ReplyBot',
                'icon_emoji' => ':dash:',
                'attachments' => json_encode([[
                    'image_url' => 'imageurl',
                ]]),
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = new IncomingMessage('response', '', 'general');
        $driver->sendPayload($driver->buildServicePayload('Test', $message, [
            'username' => 'ReplyBot',
            'icon_emoji' => ':dash:',
            'attachments' => json_encode([[
                'image_url' => 'imageurl',
            ]]),
        ]));
    }

    /** @test */
    public function it_can_reply_in_threads()
    {
        $responseData = [
            'event' => [
                'user' => 'U0X12345',
                'channel' => 'general',
                'text' => 'response',
                'ts' => '1234.5678',
            ],
        ];

        $html = m::mock(Curl::class);
        $html->shouldReceive('post')
            ->once()
            ->with('https://slack.com/api/chat.postMessage', [], [
                'as_user' => true,
                'token' => 'Foo',
                'channel' => 'general',
                'text' => 'Test',
                'username' => 'ReplyBot',
                'thread_ts' => '1234.5678',
                'icon_emoji' => ':dash:',
                'attachments' => json_encode([[
                    'image_url' => 'imageurl',
                ]]),
            ]);

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $html);

        $message = new IncomingMessage('response', '', 'general', Collection::make([
            'ts' => '1234.5678',
        ]));

        $botman = m::mock(BotMan::class)->makePartial();
        $botman->middleware = m::mock(MiddlewareManager::class)->makePartial();
        $botman->setDriver($driver);

        $driver->replyInThread('Test', [
            'username' => 'ReplyBot',
            'icon_emoji' => ':dash:',
            'attachments' => json_encode([[
                'image_url' => 'imageurl',
            ]]),
        ], $message, $botman);
    }

    /** @test */
    public function it_is_configured()
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn('');
        $htmlInterface = m::mock(Curl::class);

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => 'Foo',
            ],
        ], $htmlInterface);

        $this->assertTrue($driver->isConfigured());

        $driver = new SlackDriver($request, [
            'slack' => [
                'token' => null,
            ],
        ], $htmlInterface);

        $this->assertFalse($driver->isConfigured());

        $driver = new SlackDriver($request, [], $htmlInterface);

        $this->assertFalse($driver->isConfigured());
    }
}
