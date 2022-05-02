<?php

namespace BotMan\Drivers\Slack\Extensions;

use BotMan\BotMan\Interfaces\UserInterface;
use Slack\User as SlackUser;

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
