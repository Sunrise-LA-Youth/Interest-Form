<?php
require_once '/home/sunrisey/data.sunriseyouth.la/vendor/autoload.php';
require_once '/home/sunrisey/data.sunriseyouth.la/init.php';

use ZBateson\MailMimeParser\Message;
use GuzzleHttp\Psr7;
use PHPHtmlParser\Dom;
use JamesGordo\CSV\Parser;
use GuzzleHttp\Client;

$emails = glob(__DIR__."/*.txt"); // Get piped emails saved as .txt
foreach ($emails as $email) { // foreach email
    $time = time();
    
    // Parse the email
    $resource = fopen($email, "r");
    $parser = new \ZBateson\MailMimeParser\MailMimeParser();
    $message = $parser->parse($resource);
    $text = $message->getHtmlContent();
    fclose($resource);
    
    // Check if email is an EveryAction report (based on from address and text contents)
    if( strpos($text,"Click here</a> to download your file.") !== false) {
        // Start HTML parsing
        $html = $message->getHtmlContent();
        $dom = new Dom;
        $dom->loadStr($html);
        
        // Download EveryAction CSV link
        $csvDownloadLink = $dom->getElementsbyTag('a')[0]->href;
        $source = file_get_contents($csvDownloadLink);
        $filename = __DIR__.'/'.$time.'-report.csv';
        file_put_contents($filename, $source);
        
        // Parse CSV
        $hubMembers = new Parser($filename);
        foreach($hubMembers->all() as $member) {

        	// Check if user is young enough to be part of SLAY
        	if($time > strtotime($member->DOB . ' +20 years')) {
        	    continue;
        	}
        	
        	// Check if user email is in Slack already
        	$slack = new Client([
                'base_uri' => 'https://slack.com/api/'
            ]);
            $slackToken = "xoxb-1045757465095-1258361627588-UuGaYZaYP5NhsXgd22FPRLQU";
            $users = $slack->get('users.lookupByEmail?token='.$slackToken.'&email='.$member->{'Personal Email'});
            $users = json_decode($users->getBody(), true);
            if (!array_key_exists('error', $users)) {
                continue;
            }
            
            // Post message to #team_data channel with invited user payload
            $hubMemberName = explode(', ',$member->{'Contact Name'});
            $tz  = new DateTimeZone('America/Los_Angeles');
            $hubMemberAge = DateTime::createFromFormat('d/m/Y', $member->{'DOB'}, $tz)
                 ->diff(new DateTime('now', $tz))
                 ->y;
            if (array_key_exists('user',$users) && !$users['user']['deleted']) {
                $payload = <<<EOF
    [
		{
			"type": "section",
			"text": {
				"text": "A new hub member is ready to be added to Slack. Ensure the below information matches SLAY's membership requirements, invite them to the workspace, and then click the button to delete this messagel.",
				"type": "mrkdwn"
			},
			"fields": [
				{
					"type": "mrkdwn",
					"text": "*Name*"
				},
				{
					"type": "plain_text",
					"text": "$hubMemberName[1] $hubMemberName[0]"
				},
				{
					"type": "mrkdwn",
					"text": "*Email*"
				},
				{
					"type": "plain_text",
					"text": "{$member->{'Personal Email'}}"
				},
				{
					"type": "mrkdwn",
					"text": "*Date of birth (age)*"
				},
				{
					"type": "plain_text",
					"text": "{$member->{'DOB'}} ($hubMemberAge)"
				}
			]
		},
		{
			"type": "context",
			"elements": [
				{
					"type": "plain_text",
					"text": "Once $hubMemberName[1] has been invited to the workspace, click the below button to remove this message.",
					"emoji": true
				}
			]
		},
		{
			"type": "actions",
			"elements": [
				{
					"type": "button",
					"text": {
						"type": "plain_text",
						"text": "User added",
						"emoji": true,
					},
					"action_id": "user_added",
					"value": "{$member->{'Personal Email'}}",
					"confirm": {
                        "title": {
                            "type": "plain_text",
                            "text": "Are you sure?"
                        },
                        "text": {
                            "type": "mrkdwn",
                            "text": "Before continuing, please be sure you've invited $hubMemberName[1] to the workspace."
                        },
                        "confirm": {
                            "type": "plain_text",
                            "text": "I invited this member"
                        },
                        "deny": {
                            "type": "plain_text",
                            "text": "Cancel"
                        }
                    }
				}
			]
		}
	]
EOF;
            }
            else {
                $payload = <<<EOF
    [
		{
			"type": "section",
			"text": {
				"text": "A new hub member is ready to be added to Slack. *This user is already on Slack and needs to be reactivated.* Ensure the below information matches SLAY's membership requirements, reactivate their Slack account, and then click the button to delete this messagel.",
				"type": "mrkdwn"
			},
			"fields": [
				{
					"type": "mrkdwn",
					"text": "*Name*"
				},
				{
					"type": "plain_text",
					"text": "$hubMemberName[1] $hubMemberName[0]"
				},
				{
					"type": "mrkdwn",
					"text": "*Email*"
				},
				{
					"type": "plain_text",
					"text": "{$member->{'Personal Email'}}"
				},
				{
					"type": "mrkdwn",
					"text": "*Date of birth (age)*"
				},
				{
					"type": "plain_text",
					"text": "{$member->{'DOB'}} ($hubMemberAge)"
				}
			]
		},
		{
			"type": "context",
			"elements": [
				{
					"type": "plain_text",
					"text": "Once $hubMemberName[1] has been reactivated on this workspace, click the below button to remove this message.",
					"emoji": true
				}
			]
		},
		{
			"type": "actions",
			"elements": [
				{
					"type": "button",
					"text": {
						"type": "plain_text",
						"text": "User added",
						"emoji": true,
					},
					"action_id": "user_added",
					"value": "{$member->{'Personal Email'}}",
					"confirm": {
                        "title": {
                            "type": "plain_text",
                            "text": "Are you sure?"
                        },
                        "text": {
                            "type": "mrkdwn",
                            "text": "Before continuing, please be sure you've reactivated $hubMemberName[1]."
                        },
                        "confirm": {
                            "type": "plain_text",
                            "text": "I reactivated this member"
                        },
                        "deny": {
                            "type": "plain_text",
                            "text": "Cancel"
                        }
                    }
				}
			]
		}
	]
EOF;
            }
            $query = array(
                'token' => $slackToken,
                'channel' => 'C015ZB1ASJH',
                'icon_emoji' => ':everyaction:',
                'link_names' => 'true',
                'username' => 'EveryAction',
                'text' => 'A new hub member is ready to be added to Slack',
                'blocks' => $payload
            );
            $query = http_build_query($query);
            $invite = $slack->get('chat.postMessage?'.$query);
            $invite = json_decode($invite->getBody(), true);
            print_r($invite);
        	
        }
        unlink($filename); // Remove CSV download
    }
    unlink($email); // Remove .txt file so that future runs of the script don't try to re-invite people
}
