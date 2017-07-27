<?php

namespace BotMan\Drivers\Slack\Extensions;

use Slack\User as SlackUser;
use BotMan\BotMan\Interfaces\UserInterface;

class User extends SlackUser implements UserInterface
{
    /**
     * @return array
     */
    public function getInfo()
    {
        return $this->getRawUser();
    }
}
