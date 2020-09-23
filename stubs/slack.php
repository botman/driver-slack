<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slack token
    |--------------------------------------------------------------------------
    |
    | Your Slack bot token.
    |
    */
    'token' => env('SLACK_TOKEN'),
    
    /*
    |--------------------------------------------------------------------------
    | Slack API base url
    |--------------------------------------------------------------------------
    |
    | Your Slack API base url. Useful if you're using Mattermost,
    |
    */
    'base_url' => env('SLACK_BASE_URL', 'https://slack.com/api/'),

];
