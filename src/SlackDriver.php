<?php

namespace BotMan\Drivers\Slack;

use BotMan\BotMan\BotMan;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\Drivers\Slack\Extensions\User;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\Drivers\Slack\Extensions\Dialog;
use BotMan\BotMan\Interfaces\VerifiesService;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Conversations\Conversation;

class SlackDriver extends HttpDriver implements VerifiesService
{
    const DRIVER_NAME = 'Slack';

    const RESULT_TOKEN = 'token';

    const RESULT_JSON = 'json';

    const RESULT_DIALOG = 'dialog';

    const TYPE_DIALOG_SUBMISSION = 'dialog_submission';

    protected $resultType = self::RESULT_JSON;

    protected $botID;

    protected $botUserID;

    protected $messages = [];

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->extendConversation();

        $this->config = Collection::make($this->config->get('slack', []));

        /*
         * If the request has a POST parameter called 'payload'
         * we're dealing with an interactive button response.
         */
        if (! is_null($request->get('payload'))) {
            $payloadData = json_decode($request->get('payload'), true);

            $this->payload = Collection::make($payloadData);
            $this->event = Collection::make([
                'channel' => $payloadData['channel']['id'],
                'user' => $payloadData['user']['id'],
            ]);
        } elseif (! is_null($request->get('team_domain'))) {
            $this->payload = $request->request;
            $this->event = Collection::make($request->request->all());
        } else {
            $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
            $this->event = Collection::make($this->payload->get('event'));
            if (! empty($this->config['token']) && empty($this->botID)) {
                $this->getBotUserId();
            }
        }
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return ! is_null($this->event->get('user')) || ! is_null($this->event->get('team_domain')) || ! is_null($this->event->get('bot_id'));
    }

    /**
     * @param  \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        if ($this->payload instanceof Collection) {
            if ($this->payload->get('type') === self::TYPE_DIALOG_SUBMISSION) {
                $name = self::TYPE_DIALOG_SUBMISSION;
                $value = $this->payload->get('submission');
            } else {
                $action = Collection::make($this->payload['actions'][0]);
                $name = $action->get('name');
                if ($action->get('type') === 'select') {
                    $value = $action->get('selected_options');
                } else {
                    $value = $action->get('value');
                }
            }

            return Answer::create($name)
                ->setInteractiveReply(true)
                ->setValue($value)
                ->setMessage($message)
                ->setCallbackId($this->payload->get('callback_id'));
        }

        return Answer::create($this->event->get('text'))->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $this->loadMessages();
        }

        return $this->messages;
    }

    /**
     * Load Slack messages.
     */
    protected function loadMessages()
    {
        $messageText = '';
        if (! $this->payload instanceof Collection) {
            $messageText = $this->event->get('text');
            if ($this->isSlashCommand()) {
                $messageText = $this->event->get('command').' '.$messageText;
            }
        }

        $user_id = $this->event->get('user');
        if ($this->event->has('user_id')) {
            $user_id = $this->event->get('user_id');
        }

        $channel_id = $this->event->get('channel');
        if ($this->event->has('channel_id')) {
            $channel_id = $this->event->get('channel_id');
        }

        $message = new IncomingMessage($messageText, $user_id, $channel_id, $this->event);
        $message->setIsFromBot($this->isBot());

        $this->messages = [$message];
    }

    /**
     * @return bool
     */
    protected function isBot()
    {
        return $this->event->has('bot_id') && $this->event->get('bot_id') == $this->botID;
    }

    /**
     * @return bool
     */
    protected function isSlashCommand()
    {
        return $this->event->has('command');
    }

    /**
     * Convert a Question object into a valid Slack response.
     *
     * @param \BotMan\BotMan\Messages\Outgoing\Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $questionData = $question->toArray();

        $buttons = Collection::make($question->getButtons())->map(function ($button) {
            if ($button['type'] === 'select') {
                return $button;
            }

            return array_merge([
                'name' => $button['name'],
                'text' => $button['text'],
                'image_url' => $button['image_url'],
                'type' => $button['type'],
                'value' => $button['value'],
            ], $button['additional']);
        })->toArray();
        $questionData['actions'] = $buttons;

        return $questionData;
    }

    /**
     * @param string|\BotMan\BotMan\Messages\Outgoing\Question $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        if (! Collection::make($matchingMessage->getPayload())->has('team_domain')) {
            $this->resultType = self::RESULT_TOKEN;
            $payload = $this->replyWithToken($message, $matchingMessage, $additionalParameters);
        } else {
            $this->resultType = self::RESULT_JSON;
            $payload = $this->respondJSON($message, $matchingMessage, $additionalParameters);
        }

        return $payload;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        if ($this->resultType == self::RESULT_TOKEN) {
            return $this->http->post('https://slack.com/api/chat.postMessage', [], $payload);
        } elseif ($this->resultType == self::RESULT_DIALOG) {
            return $this->http->post('https://slack.com/api/dialog.open', [], $payload);
        }

        return JsonResponse::create($payload)->send();
    }

    /**
     * @param $message
     * @param array $additionalParameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return array
     */
    public function replyInThread($message, $additionalParameters, $matchingMessage, BotMan $bot)
    {
        $additionalParameters['thread_ts'] = ! empty($matchingMessage->getPayload()->get('thread_ts'))
            ? $matchingMessage->getPayload()->get('thread_ts')
            : $matchingMessage->getPayload()->get('ts');

        $payload = $this->buildServicePayload($message, $matchingMessage, $additionalParameters);

        return $bot->sendPayload($payload);
    }

    /**
     * @param $message
     * @param array $additionalParameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return array
     */
    public function replyDialog(Dialog $dialog, $additionalParameters, $matchingMessage, BotMan $bot)
    {
        $this->resultType = self::RESULT_DIALOG;
        $payload = [
            'trigger_id' => $this->payload->get('trigger_id'),
            'channel' => $matchingMessage->getRecipient() === '' ? $matchingMessage->getSender() : $matchingMessage->getRecipient(),
            'token' => $this->config->get('token'),
            'dialog' => json_encode($dialog->toArray()),
        ];

        return $bot->sendPayload($payload);
    }

    /**
     * @param string|Question $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @param array $parameters
     * @return array
     */
    protected function respondJSON($message, $matchingMessage, $parameters = [])
    {
        /*
         * If we send a Question with buttons, ignore
         * the text and append the question.
         */
        if ($message instanceof Question) {
            $parameters['text'] = $message->getText();
            $parameters['attachments'] = json_encode([$this->convertQuestion($message)]);
        } elseif ($message instanceof OutgoingMessage) {
            $parameters['text'] = $message->getText();
            $attachment = $message->getAttachment();
            if (! is_null($attachment)) {
                if ($attachment instanceof Image) {
                    $parameters['attachments'] = json_encode([
                        [
                            'title' => $attachment->getTitle(),
                            'image_url' => $attachment->getUrl(),
                        ],
                    ]);
                }
            }
        } else {
            $parameters['text'] = $message;
        }

        return $parameters;
    }

    /**
     * @param string|\BotMan\BotMan\Messages\Outgoing\Question|IncomingMessage $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     */
    protected function replyWithToken($message, $matchingMessage, $additionalParameters = [])
    {
        $parameters = array_replace_recursive([
            'as_user' => true,
            'token' => $this->payload->get('token'),
            'channel' => $matchingMessage->getRecipient() === '' ? $matchingMessage->getSender() : $matchingMessage->getRecipient(),
        ], $additionalParameters);

        /*
         * If we send a Question with buttons, ignore
         * the text and append the question.
         */
        if ($message instanceof Question) {
            if (! isset($parameters['text'])) {
                $parameters['text'] = '';
            }
            if (! isset($parameters['attachments'])) {
                $parameters['attachments'] = json_encode([$this->convertQuestion($message)]);
            }
        } elseif ($message instanceof OutgoingMessage) {
            $parameters['text'] = $message->getText();
            $attachment = $message->getAttachment();
            if (! is_null($attachment)) {
                if ($attachment instanceof Image) {
                    $parameters['attachments'] = json_encode(['image_url' => $attachment->getUrl()]);
                }
            }
        } else {
            $parameters['text'] = $message;
        }

        $parameters['token'] = $this->config->get('token');

        return $parameters;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->config->get('token'));
    }

    /**
     * Retrieve User information.
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $response = $this->sendRequest('users.info', [
            'user' => $matchingMessage->getSender(),
        ], $matchingMessage);
        try {
            $content = json_decode($response->getContent(), true);

            return new User(null, $content['user']);
        } catch (\Exception $e) {
            return new User(null, ['id' => $matchingMessage->getSender()]);
        }
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $parameters = array_replace_recursive([
            'token' => $this->config->get('token'),
        ], $parameters);

        return $this->http->post('https://slack.com/api/'.$endpoint, [], $parameters);
    }

    /**
     * @param Request $request
     * @return null|Response
     */
    public function verifyRequest(Request $request)
    {
        $payload = Collection::make(json_decode($request->getContent(), true));
        if ($payload->get('type') === 'url_verification') {
            return Response::create($payload->get('challenge'))->send();
        }
    }

    /**
     * Get bot userID.
     */
    public function getBotUserId()
    {
        $botUserIdRequest = $this->http->post('https://slack.com/api/auth.test', [], [
            'token' => $this->config->get('token'),
        ]);
        $botUserIdPayload = new ParameterBag((array) json_decode($botUserIdRequest->getContent(), true));

        if ($botUserIdPayload->get('user_id')) {
            $this->botUserID = $botUserIdPayload->get('user_id');
            $this->getBotId();
        }
    }

    /**
     * Get bot ID.
     */
    private function getBotId()
    {
        $botUserRequest = $this->http->post('https://slack.com/api/users.info', [], [
            'user' => $this->botUserID,
            'token' => $this->config->get('token'),
        ]);
        $botUserPayload = (array) json_decode($botUserRequest->getContent(), true);

        if ($botUserPayload['user']['is_bot']) {
            $this->botID = $botUserPayload['user']['profile']['bot_id'];
        }
    }

    /**
     * Extend BotMan conversation class.
     */
    public function extendConversation()
    {
        Conversation::macro('sendDialog', function (Dialog $dialog, $next, $additionalParameters = []) {
            $response = $this->bot->replyDialog($dialog, $additionalParameters);

            $validation = function ($answer) use ($dialog, $next, $additionalParameters) {
                $errors = $dialog->errors(Collection::make($answer->getValue()));
                if (count($errors)) {
                    $this->bot->touchCurrentConversation();

                    return Response::create(json_encode(['errors' => $errors]), 200, ['ContentType' => 'application/json'])->send();
                } else {
                    if ($next instanceof \Closure) {
                        $next = $next->bindTo($this, $this);
                    }
                    $next($answer);
                }
            };
            $this->bot->storeConversation($this, $validation, $dialog, $additionalParameters);

            return $response;
        });
    }
}
