<?php
require_once('vendor/autoload.php');

$slackToken = "";

use GuzzleHttp\Client;

$json = json_decode($_POST['payload'],true);
$responseUrl = $json['response_url'];
$email = $json['actions'][0]['value'];

// Original message variables
$channelPosted = $json['channel']['id'];
$threadTs = $json['message']['ts'];

$slack = new Client([
    'base_uri' => 'https://slack.com/api/'
]);
$slackHook = new Client([
    'headers' => [ 'Content-Type' => 'application/json' ]
]);

// Get Slack user ID
$query = array(
    'token' => $slackToken,
    'email' => $email
);
$query = http_build_query($query);
$userInfo = $slack->get('users.lookupByEmail?'.$query);
$userInfo = json_decode($userInfo->getBody(), true);
if (array_key_exists('error', $userInfo)) {
    // Return error message to Slack
    $query = array(
        'token' => $slackToken,
        'channel' => $channelPosted,
        'icon_emoji' => ':everyaction:',
        'link_names' => 'true',
        'username' => 'EveryAction',
        'text' => 'There was an error finding the user. Try again and ensure the user has been invited to Slack with the correct email.',
        'thread_ts' => $threadTs
    );
    $query = http_build_query($query);
    $response = $slack->get('chat.postMessage?'.$query);
    $response = json_decode($response->getBody(), true);
    exit();
}
if ($userInfo['user']['deleted']) {
    // Return error message to Slack
    $query = array(
        'token' => $slackToken,
        'channel' => $channelPosted,
        'icon_emoji' => ':everyaction:',
        'link_names' => 'true',
        'username' => 'EveryAction',
        'text' => 'The user has not been reactivated. Try again and ensure the user has been reactivated on Slack with the correct email.',
        'thread_ts' => $threadTs
    );
    $query = http_build_query($query);
    $response = $slack->get('chat.postMessage?'.$query);
    $response = json_decode($response->getBody(), true);
    exit();
}
$userId = $userInfo['user']['id'];
$userName = $userInfo['user']['real_name'];

// Return success message to Slack
$response = $slackHook->post($responseUrl,
    ['body' => json_encode(
        [
            'delete_original' => 'true',
            'response_type' => 'ephemeral',
            'text' => $userName.' was added to the workspace successfully!',
            'mrkdwn' => 'true'
        ]
    )]
);
$response = json_decode($response->getBody(), true);
