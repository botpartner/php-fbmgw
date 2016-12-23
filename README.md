# BotPartner CoT - Facebook Messenger Gateway

## Requires

 * last version [FFmpeg](https://ffmpeg.org/)
 * php: >= 5.6.4
 * pimax/fb-messenger-php: dev-fixbug
 * jyyan/eliza: dev-master
 * laravel/framework: 5.3.*
 * google/cloud: ^0.11.1
 * james-heinrich/getid3: ^1.9
 * php-ffmpeg/php-ffmpeg: ^0.6.1


## Installation

* install last version [FFmpeg](https://ffmpeg.org/) for your host
* clone the repository
```sh
git clone https://github.com/botpartner/cot-fbmgw
```
 * install composer packages dependencies
```sh
composer install --no-dev
```
 * put your google cloud API *application account JSON key* from Google Developers Console into the project path
```sh
storage/google_api_json.key
```
 * make your tmp/cache folders writable for your http server , and some setup process for laravel framework:
```sh
chmod 777 -R bootstrap/cache
chmod 777 -R storage
cp .env.example .env
php artisan key:generate
```
 * edit *.env* change following line
```
APP_URL=http://localhost
COT_API_URL=http://localhost/cot/emit
GOOGLE_PID=__YOUR_GOOGLE_PROJECT_ID__
GOOGLE_API_KEY=__YOUR_GOOGLE_API_KEY_STRING__
IMG_TURN_ON=__IMAGE_NAME_TUEN_ON__
IMG_TURN_OFF=__IMAGE_NAME_TUEN_OFF__
```
as
```
APP_URL=https://__YOUR_DOMAINNAME__
COT_API_URL=http://__YOUR_COT_API_DOMAINNAME__/cot/emit
GOOGLE_PID=__YOUR_GOOGLE_PROJECT_ID__
GOOGLE_API_KEY=__YOUR_GOOGLE_API_KEY_STRING__
IMG_TURN_ON=__IMAGE_NAME_TUEN_ON__
IMG_TURN_OFF=__IMAGE_NAME_TUEN_OFF__
```
 * put your sticker images into ``publish/img/``
 * edit ``app/Http/Messenger/ApiController.php`` find the following line and replace as your facebook app/page information
```
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
```
 * at last, please looking the README of package [php-louis](https://github.com/botpartner/php-louis) for more setup options
 * enjoy


## Source Code License

(The MIT License)

Copyright (c) 2016 Jun-Yuan Yan (bot@botpartner.me) , BotPartner Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
