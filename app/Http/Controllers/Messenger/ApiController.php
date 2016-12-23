<?php

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log as Log;
use Illuminate\Http\Request;

use pimax\FbBotApp;
use pimax\UserProfile;
use pimax\Messages\Message;
use pimax\Messages\ImageMessage;
use pimax\Messages\MessageButton;
use pimax\Messages\StructuredMessage;
use pimax\Messages\MessageElement;
use pimax\Messages\MessageReceiptElement;
use pimax\Messages\Address;
use pimax\Messages\Summary;
use pimax\Messages\Adjustment;
use pimax\Messages\SenderAction;
use pimax\Messages\QuickReply;
use JYYAN\ElizaBot;
use Google\Cloud\Speech\SpeechClient;
use Google\Cloud\ServiceBuilder;
use Google\Cloud\Translate\TranslateClient;
use GuzzleHttp\Client;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Flac;

class ApiController extends Controller
{

    public $enable_bot_response = true ;

    public $elizabot;

    public $fb_token=array(
        'verify_token' => 'YOUR_FACEBOOK_APP_TOKEN',
        'key_house'    => array(
            'YOUR_FACEBOOK_PAGE_MESSENGER_ID' => array(
                'elizabot'=>true,
                'name'=>'YOUR_FACEBOOK_PAGE_NAME',
                'key'=> 'YOUR_FACEBOOK_PAGE_TOKEN',
                'lang'=>'cmn-Hant-TW',
            ),
        )
    );

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function checkCotCmd($command)
    {
        $command = strtolower($command);

        // you can add your language keyword here :
        // CoT command
        $cmd_on = array(
            "turn on",
        );
        $cmd_off = array(
            "turn off",
        );
        foreach ($cmd_on as $cmd) {
            if (strrpos($command, $cmd) !== false) {
                $this->emitCotCmd(true);
                return config('app.ly.imgTurnOn');
            }
        }

        foreach ($cmd_off as $cmd) {
            if (strrpos($command, $cmd) !== false) {
                $this->emitCotCmd(false);
                return config('app.ly.imgTurnOff');
            }
        }

        return false;
    }

    public function emitCotCmd($device_power)
    {
        try {
            $apiUrl = config('app.cot_api_url');
            $client = new \GuzzleHttp\Client();
            $res = $client->post($apiUrl, [
                'body'=> [
                    "to_owner" => "botpartner",
                    "to_group" => "demo",
                    "action"   => ( $device_power ? "on" : "off" )
                ],
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }


    public function copyRemote($fromUrl, $toFile)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $resource = fopen($toFile, 'w');
            $client->get($fromUrl, ['save_to' => $resource]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

  /*
  $opts = array(
    'tmp_path'='',
    'voice_url'=>'',
    'language'='',
    'resource_type' =>'facebook'
  )
   */
    public function talkwith($opts)
    {
        if (empty($opts['tmp_path']) || empty($opts['voice_url'])
            || empty($opts['language']) || empty($opts['resource_type'])) {
            return false ;
        }

        $tmpPath = $opts['tmp_path'];
        $tmpVoiceAccPath = $tmpPath .'/tmp-'. time() .'.acc';
        $tmpVoiceFlacPath = $tmpPath .'/tmp-'. time() .'.flac';



        switch ($opts['resource_type']) {
            case 'facebook':
                if ($this->copyRemote($opts['voice_url'], $tmpVoiceAccPath)) {
                    $ffmpeg = FFMpeg::create(array(
                    'ffprobe.binaries' => '/usr/bin/ffprobe',
                    'ffmpeg.binaries' => '/usr/bin/ffmpeg',
                    ));
                    $audio = $ffmpeg->open($tmpVoiceAccPath);

                    $format = new Flac();
                    $format->on('progress', function ($audio, $format, $percentage) {
                            echo "$percentage % transcoded";
                    });

                    $format
                        -> setAudioChannels(1)
                        -> setAudioKiloBitrate(256);

                    $audio->save($format, $tmpVoiceFlacPath);

                    $gcloud = new ServiceBuilder([
                        'keyFilePath' =>  config('app.ly.keyPath') ,
                        'projectId' => config('app.ly.projectId')
                        ]);

                    // Fetch an instance of the Storage Client
                    $storage = $gcloud->storage();

                    $speech = $gcloud->speech();
                    $operation = $speech->beginRecognizeOperation(
                        fopen($tmpVoiceFlacPath, 'r'),
                        array(
                        'encoding'=>'flac',
                        'languageCode'=> $opts['language'] ,
                        )
                    );

                    $isComplete = $operation->isComplete();

                    while (!$isComplete) {
                            usleep(100); // let's wait for a moment...
                            $operation->reload();
                            $isComplete = $operation->isComplete();
                    }
                    // remove the tmp audio files
                    unlink($tmpVoiceAccPath);
                    unlink($tmpVoiceFlacPath);
                    return $operation->results() ;
                } else {
                    return false;
                };
                break;
            default:
                return false;
            break;
        }
    }

    public function index(Request $request)
    {
        $enable_bot_response= $this->enable_bot_response;

        $config=array();
        $config['fb_token']= $this->fb_token;

        $verify_token = $config['fb_token']['verify_token']; // Verify token

        // Receive something
        if (!empty($_REQUEST['hub_mode']) && $_REQUEST['hub_mode'] == 'subscribe'
            && $_REQUEST['hub_verify_token'] == $verify_token) {
            // Webhook setup request
            return response($_REQUEST['hub_challenge'], 200);
        } else {
            // Other event
            $data = json_decode(file_get_contents("php://input"), true, 512, JSON_BIGINT_AS_STRING);

            if (!empty($data['entry'][0]['messaging'])) {
                foreach ($data['entry'][0]['messaging'] as $message) {
                    $recipient_id = $message['recipient']['id'];
                    $sender_id = $message['sender']['id'];

                    $try_fanpage_id = $recipient_id;
                    // Skipping delivery messages
                    if (!empty($message['delivery'])) {
                        continue;
                    }

                    $command = "";

                    // When bot receive message from user
                    if (!empty($message['message']) && isset($message['message']['text'])) {
                        $command = $message['message']['text'];

                        // When bot receive button click from user
                    } elseif (!empty($message['postback'])) {
                        $command = $message['postback']['payload'];
                    }


                    // init bot
                    if (isset($config['fb_token']['key_house'][$try_fanpage_id]) && strlen($command) > 0) {
                        $bot_config = $config['fb_token']['key_house'][$try_fanpage_id];
                        // Handle command
                        $bot = new FbBotApp($bot_config['key']);

                        // send the typing action
                        if ($enable_bot_response) {
                            $bot->send(new SenderAction($message['sender']['id'], SenderAction::ACTION_TYPING_ON));
                        }

                        if ($bot_config['elizabot']) {
                            $opts = array(
                                'elizaKnowlegeBase'=>$this->getCharKnowlege(),
                                'humanKnowlegeBase'=> $this->getCharKnowlege(),
                                'snlpKnowlegeBase'=> $this->getSnlpKnowlege()
                            );
                            $elizabot = new ElizaBot($opts);
                            // setup the google Cloud api key
                            $apiKey = config('app.ly.projectApiKey');
                            $elizabot->bot->set('translate', new TranslateClient([
                                'key' => $apiKey
                            ]));


                            // CHECK the CoT command :
                            if ($enable_bot_response
                                && $imageID = $this->checkCotCmd($command)) {
                                $bot->send(new ImageMessage($message['sender']['id'], 'http://api.botpartner.me/img/'.$imageID));
                                $bot->send(new Message($message['sender']['id'], 'CoT command received!'));
                            } else {
                                $msgBox = $elizabot->receive($command)->think() ;
                                // send the image sticker reply
                                if (!empty($msgBox->imageID) && $enable_bot_response) {
                                    $bot->send(new ImageMessage($message['sender']['id'], 'http://api.botpartner.me/img/'.$msgBox->imageID));
                                }
                            }
                        }
                    } elseif (isset($config['fb_token']['key_house'][$sender_id]) && strlen($command) >0) {
                        $bot_config = $config['fb_token']['key_house'][$sender_id];
                    } elseif (isset($config['fb_token']['key_house'][$try_fanpage_id]) && isset($message['message']) && isset($message['message']['attachments'])) {
                        $bot_config = $config['fb_token']['key_house'][$try_fanpage_id];
                        if ($bot_config['elizabot']) {
                            // Handle command
                            $bot = new FbBotApp($bot_config['key']);
                            // when recive a Attachment image
                            foreach ($message['message']['attachments'] as $attachment) {
                                switch ($attachment['type']) {
                                    case 'image':
                                        $url = $attachment['payload']['url'];
                                        $image = new ImageMessage($message['sender']['id'], $url);
                                        if ($enable_bot_response) {
                                            $bot->send($image);
                                        }
                                        break;
                                    case 'audio':
                                        if (!empty($bot_config['lang'])) {
                                            $elizabot = new ElizaBot();
                                            $tmpPath = config('app.ly.tmpPath');

                                            // setup
                                            // audio/speech recognition options
                                            $opts = array(
                                            'tmp_path' => $tmpPath ,
                                            'voice_url' => $attachment['payload']['url'],
                                            'language' => $bot_config['lang'],
                                            'resource_type' => 'facebook',
                                            );

                                            $res = null ;

                                            try {
                                                if ($enable_bot_response) {
                                                    $res = $this->talkwith($opts) ;
                                                }
                                            } catch (\Exception $e) {
                                                    return response('audio recognition error, but thats ok', 200);
                                            }

                                            if ($res) {
                                                    //transcript
                                                    $res_text =       $res[0]['transcript'];
                                                    $res_confidence = $res[0]['confidence'];

                                                    // CHECK the CoT command :
                                                if ($enable_bot_response && $imageID = $this->checkCotCmd($res_text)) {
                                                    $bot->send(new ImageMessage($message['sender']['id'], config('app.url').'/img/'.$imageID));
                                                    $bot->send(new Message($message['sender']['id'], $res_text));
                                                    $bot->send(new Message($message['sender']['id'], 'CoT command received!'));
                                                };
                                            }
                                        }
                                        break;
                                }
                            }
                        } // ElizaBot end
                    } else {
                        return response('', 200);
                    }
                }

                // send to setup typing_off action
                if (isset($bot) && $enable_bot_response) {
                    // end Messages command check
                    $bot->send(new SenderAction($message['sender']['id'], SenderAction::ACTION_TYPING_OFF));
                }
            }
        }
    }

    public function getSnlpKnowlege()
    {
        $result = array();

        $rule = new Knowlege();
        $rule->key = "happy";
        $rule->key = "great";
        $rule->happiness = 100;
        $result[]=$rule;

        $rule = new Knowlege();
        $rule->key = "wtf";
        $rule->anger = 100;
        $result[]=$rule;

        $rule = new Knowlege();
        $rule->key = "hate";
        $rule->disqust= 100;
        $result[]=$rule;


        $rule = new Knowlege();
        $rule->key = "sad";
        $rule->sadness= 100;
        $result[]=$rule;


        $rule = new Knowlege();
        $rule->key = "surprise";
        $rule->surprise= 100;
        $result[]=$rule;

        $rule = new Knowlege();
        $rule->key = "why";
        $rule->key = "?";
        $rule->command = 100;
        $result[]=$rule;

        return $result;
    }

    public function getCharKnowlege()
    {
        $result = array();

        $rule = new Rule();
        $rule->key = "happy";
        $rule->key = "great";
        $rule->img = "001.PNG"; // pic id , pic name
        $rule->happiness = 100;
        $rule->happiness->setRule("gt");
        $result[]=$rule;

        $rule = new Rule();
        $rule->key = "wtf";
        $rule->img = "001.PNG"; // pic id , pic name
        $rule->anger = 100;
        $rule->anger->setRule('gt');
        $result[]=$rule;

        $rule = new Rule();
        $rule->key = "hate";
        $rule->img = "001.PNG"; // pic id , pic name
        $rule->disqust= 100;
        $rule->disqust->setRule('gt');
        $result[]=$rule;


        $rule = new Rule();
        $rule->key = "sad";
        $rule->img = "001.PNG"; // pic id , pic name
        $rule->sadness= 100;
        $rule->sadness->setRule('gt');
        $result[]=$rule;


        $rule = new Rule();
        $rule->key = "surprise";
        $rule->img = "001.PNG"; // pic id , pic name
        $rule->surprise= 100;
        $rule->surprise->setRule('gt');
        $result[]=$rule;


        $rule = new Rule();
        $rule->key = "why";
        $rule->key = "?";
        $rule->img = "001.PNG"; // pic id , pic name
        $rule->command = 100;
        $rule->command->setRule('gt');
        $result[]=$rule;

        $rule = new Rule();
        $rule->img = "001.PNG"; // pic id , pic name
        $rule->unknow = 90 ;
        $rule->unknow->setRule("gt");
        $result[]=$rule;

        return $result;
    }
}
