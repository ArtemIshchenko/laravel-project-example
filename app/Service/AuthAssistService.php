<?php

namespace App\Service;

use App\Models\User;
use App\Models\User2faRecavery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class AuthAssistService
{
    const GOOGLE_API_AUTH = 'https://www.authenticatorapi.com/';

    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function twoFaCode($user, $signCount = 6, $hasLatter = false) {
        $code = '';
        if ($hasLatter) {
            $signs = ['0','1','2','3','4','5','6','7','8','9','A','a','B','b','C','c','D','d','E','e','F','f','G','g','H','h','I','i','J','j','K','k','L','l',
                'M','m','N','n','O','o','P','p','Q','q','R','r','S','s','T','t','U','u','V','v','W','w','X','x','Y','y','Z','z'];
            $count = count($signs);
            for ($i = 0; $i < $signCount; $i++) {
                $signIndx = mt_rand(0, $count - 1);
                $code .= $signs[$signIndx];
            }
        } else {
            for ($i = 0; $i < $signCount; $i++) {
                $digit = mt_rand(0, 9);
                $code .= $digit;
            }
        }

        return $code;
    }

    public static function getTimezones() {
        return [
            ['name' => 'Australian Central Daylight Saving Time', 'utcOffset' => 'UTC+10:30', 'abbr' => ''],
            ['name' => 'Australian Central Standard Time', 'utcOffset' => 'UTC+09:30', 'abbr' => ''],
            ['name' => 'Acre Time', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'ASEAN Common Time (proposed)', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Australian Central Western Standard Time (unofficial)', 'utcOffset' => 'UTC+08:45', 'abbr' => ''],
            ['name' => 'Atlantic Daylight Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Australian Eastern Daylight Saving Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Australian Eastern Standard Time', 'utcOffset' => 'UTC+10', 'abbr' => ''],
            ['name' => 'Australian Eastern Time', 'utcOffset' => 'UTC+10 / UTC+11', 'abbr' => ''],
            ['name' => 'Afghanistan Time', 'utcOffset' => 'UTC+04:30', 'abbr' => ''],
            ['name' => 'Alaska Daylight Time', 'utcOffset' => 'UTC−08', 'abbr' => ''],
            ['name' => 'Alaska Standard Time', 'utcOffset' => 'UTC−09', 'abbr' => ''],
            ['name' => 'Alma-Ata Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Amazon Summer Time (Brazil)', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Amazon Time (Brazil)', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Armenia Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'Anadyr Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Aqtobe Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Argentina Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Arabia Standard Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Atlantic Standard Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Australian Western Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Azores Summer Time', 'utcOffset' => 'UTC±00', 'abbr' => ''],
            ['name' => 'Azores Standard Time', 'utcOffset' => 'UTC−01', 'abbr' => ''],
            ['name' => 'Azerbaijan Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'Brunei Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'British Indian Ocean Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Baker Island Time', 'utcOffset' => 'UTC−12', 'abbr' => ''],
            ['name' => 'Bolivia Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Brasília Summer Time', 'utcOffset' => 'UTC−02', 'abbr' => ''],
            ['name' => 'Brasília Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Bangladesh Standard Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Bougainville Standard Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'British Summer Time (British Standard Time from Mar 1968 to Oct 1971)', 'utcOffset' => 'UTC+01', 'abbr' => ''],
            ['name' => 'Bhutan Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Central Africa Time', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Cocos Islands Time', 'utcOffset' => 'UTC+06:30', 'abbr' => ''],
            ['name' => 'Central Daylight Time (North America)', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'Cuba Daylight Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Central European Summer Time', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Central European Time', 'utcOffset' => 'UTC+01', 'abbr' => ''],
            ['name' => 'Chatham Daylight Time', 'utcOffset' => 'UTC+13:45', 'abbr' => ''],
            ['name' => 'Chatham Standard Time', 'utcOffset' => 'UTC+12:45', 'abbr' => ''],
            ['name' => 'Choibalsan Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Choibalsan Summer Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Chamorro Standard Time', 'utcOffset' => 'UTC+10', 'abbr' => ''],
            ['name' => 'Chuuk Time', 'utcOffset' => 'UTC+10', 'abbr' => ''],
            ['name' => 'Clipperton Island Standard Time', 'utcOffset' => 'UTC−08', 'abbr' => ''],
            ['name' => 'Cook Island Time', 'utcOffset' => 'UTC−10', 'abbr' => ''],
            ['name' => 'Chile Summer Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Chile Standard Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Colombia Summer Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Colombia Time', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'Central Standard Time (Central America)', 'utcOffset' => 'UTC−06', 'abbr' => ''],
            ['name' => 'China Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Cuba Standard Time', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'Central Time', 'utcOffset' => 'UTC−06 / UTC−05', 'abbr' => ''],
            ['name' => 'Cape Verde Time', 'utcOffset' => 'UTC−01', 'abbr' => ''],
            ['name' => 'Central Western Standard Time (Australia) unofficial', 'utcOffset' => 'UTC+08:45', 'abbr' => ''],
            ['name' => 'Christmas Island Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'Davis Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'Dumont d\'Urville Time', 'utcOffset' => 'UTC+10', 'abbr' => ''],
            ['name' => 'AIX-specific equivalent of Central European Time', 'utcOffset' => 'UTC+01', 'abbr' => ''],
            ['name' => 'Easter Island Summer Time', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'Easter Island Standard Time', 'utcOffset' => 'UTC−06', 'abbr' => ''],
            ['name' => 'East Africa Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Eastern Caribbean Time (does not recognise DST)', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Ecuador Time', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'Eastern Daylight Time (North America)', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Eastern European Summer Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Eastern European Time', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Eastern Greenland Summer Time', 'utcOffset' => 'UTC±00', 'abbr' => ''],
            ['name' => 'Eastern Greenland Time', 'utcOffset' => 'UTC−01', 'abbr' => ''],
            ['name' => 'Eastern Standard Time (North America)', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'Eastern Time (North America)', 'utcOffset' => 'UTC−05 / UTC−04', 'abbr' => ''],
            ['name' => 'Further-eastern European Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Fiji Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Falkland Islands Summer Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Falkland Islands Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Fernando de Noronha Time', 'utcOffset' => 'UTC−02', 'abbr' => ''],
            ['name' => 'Galápagos Time', 'utcOffset' => 'UTC−06', 'abbr' => ''],
            ['name' => 'Gambier Islands Time', 'utcOffset' => 'UTC−09', 'abbr' => ''],
            ['name' => 'Georgia Standard Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'French Guiana Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Gilbert Island Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Gambier Island Time', 'utcOffset' => 'UTC−09', 'abbr' => ''],
            ['name' => 'Greenwich Mean Time', 'utcOffset' => 'UTC±00', 'abbr' => ''],
            ['name' => 'South Georgia and the South Sandwich Islands Time', 'utcOffset' => 'UTC−02', 'abbr' => ''],
            ['name' => 'Gulf Standard Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'Guyana Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Hawaii–Aleutian Daylight Time', 'utcOffset' => 'UTC−09', 'abbr' => ''],
            ['name' => 'Heure Avancée d\'Europe Centrale French-language name for CEST', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Hawaii–Aleutian Standard Time', 'utcOffset' => 'UTC−10', 'abbr' => ''],
            ['name' => 'Hong Kong Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Heard and McDonald Islands Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Hovd Summer Time (not used from 2017-present)', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Hovd Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'Indochina Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'International Date Line West time zone', 'utcOffset' => 'UTC−12', 'abbr' => ''],
            ['name' => 'Israel Daylight Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Indian Ocean Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Iran Daylight Time', 'utcOffset' => 'UTC+04:30', 'abbr' => ''],
            ['name' => 'Irkutsk Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Iran Standard Time', 'utcOffset' => 'UTC+03:30', 'abbr' => ''],
            ['name' => 'Indian Standard Time', 'utcOffset' => 'UTC+05:30', 'abbr' => ''],
            ['name' => 'Irish Standard Time', 'utcOffset' => 'UTC+01', 'abbr' => ''],
            ['name' => 'Israel Standard Time', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Japan Standard Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Kaliningrad Time', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Kyrgyzstan Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Kosrae Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Krasnoyarsk Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'Korea Standard Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Lord Howe Standard Time', 'utcOffset' => 'UTC+10:30', 'abbr' => ''],
            ['name' => 'Lord Howe Summer Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Line Islands Time', 'utcOffset' => 'UTC+14', 'abbr' => ''],
            ['name' => 'Magadan Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Marquesas Islands Time', 'utcOffset' => 'UTC−09:30', 'abbr' => ''],
            ['name' => 'Mawson Station Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Mountain Daylight Time (North America)', 'utcOffset' => 'UTC−06', 'abbr' => ''],
            ['name' => 'Middle European Time (same zone as CET)', 'utcOffset' => 'UTC+01', 'abbr' => ''],
            ['name' => 'Middle European Summer Time (same zone as CEST)', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Marshall Islands Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Macquarie Island Station Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Marquesas Islands Time', 'utcOffset' => 'UTC−09:30', 'abbr' => ''],
            ['name' => 'Myanmar Standard Time', 'utcOffset' => 'UTC+06:30', 'abbr' => ''],
            ['name' => 'Moscow Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Malaysia Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Mountain Standard Time (North America)', 'utcOffset' => 'UTC−07', 'abbr' => ''],
            ['name' => 'Mountain Time (North America)', 'utcOffset' => 'UTC−07 / UTC−06', 'abbr' => ''],
            ['name' => 'Mauritius Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'Maldives Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Malaysia Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'New Caledonia Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Newfoundland Daylight Time', 'utcOffset' => 'UTC−02:30', 'abbr' => ''],
            ['name' => 'Norfolk Island Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Novosibirsk Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'Nepal Time', 'utcOffset' => 'UTC+05:45', 'abbr' => ''],
            ['name' => 'Newfoundland Standard Time', 'utcOffset' => 'UTC−03:30', 'abbr' => ''],
            ['name' => 'Newfoundland Time', 'utcOffset' => 'UTC−03:30', 'abbr' => ''],
            ['name' => 'Niue Time', 'utcOffset' => 'UTC−11', 'abbr' => ''],
            ['name' => 'New Zealand Daylight Time', 'utcOffset' => 'UTC+13', 'abbr' => ''],
            ['name' => 'New Zealand Standard Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Omsk Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Oral Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Pacific Daylight Time (North America)', 'utcOffset' => 'UTC−07', 'abbr' => ''],
            ['name' => 'Peru Time', 'utcOffset' => 'UTC−05', 'abbr' => ''],
            ['name' => 'Kamchatka Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Papua New Guinea Time', 'utcOffset' => 'UTC+10', 'abbr' => ''],
            ['name' => 'Phoenix Island Time', 'utcOffset' => 'UTC+13', 'abbr' => ''],
            ['name' => 'Philippine Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Philippine Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Pakistan Standard Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Saint Pierre and Miquelon Daylight Time', 'utcOffset' => 'UTC−02', 'abbr' => ''],
            ['name' => 'Saint Pierre and Miquelon Standard Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Pohnpei Standard Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Pacific Standard Time (North America)', 'utcOffset' => 'UTC−08', 'abbr' => ''],
            ['name' => 'Pacific Time (North America)', 'utcOffset' => 'UTC−08 / UTC−07', 'abbr' => ''],
            ['name' => 'Palau Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Paraguay Summer Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Paraguay Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Réunion Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'Rothera Research Station Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Sakhalin Island Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Samara Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'South African Standard Time', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'Solomon Islands Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Seychelles Time', 'utcOffset' => 'UTC+04', 'abbr' => ''],
            ['name' => 'Samoa Daylight Time', 'utcOffset' => 'UTC−10', 'abbr' => ''],
            ['name' => 'Singapore Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Sri Lanka Standard Time', 'utcOffset' => 'UTC+05:30', 'abbr' => ''],
            ['name' => 'Srednekolymsk Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Suriname Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Samoa Standard Time', 'utcOffset' => 'UTC−11', 'abbr' => ''],
            ['name' => 'Showa Station Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Tahiti Time', 'utcOffset' => 'UTC−10', 'abbr' => ''],
            ['name' => 'Thailand Standard Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'French Southern and Antarctic Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Tajikistan Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Tokelau Time', 'utcOffset' => 'UTC+13', 'abbr' => ''],
            ['name' => 'Timor Leste Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Turkmenistan Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Turkey Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Tonga Time', 'utcOffset' => 'UTC+13', 'abbr' => ''],
            ['name' => 'Taiwan Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Tuvalu Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'Ulaanbaatar Summer Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Ulaanbaatar Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Coordinated Universal Time', 'utcOffset' => 'UTC±00', 'abbr' => ''],
            ['name' => 'Uruguay Summer Time', 'utcOffset' => 'UTC−02', 'abbr' => ''],
            ['name' => 'Uruguay Standard Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Uzbekistan Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
            ['name' => 'Venezuelan Standard Time', 'utcOffset' => 'UTC−04', 'abbr' => ''],
            ['name' => 'Vladivostok Time', 'utcOffset' => 'UTC+10', 'abbr' => ''],
            ['name' => 'Volgograd Time', 'utcOffset' => 'UTC+03', 'abbr' => ''],
            ['name' => 'Vostok Station Time', 'utcOffset' => 'UTC+06', 'abbr' => ''],
            ['name' => 'Vanuatu Time', 'utcOffset' => 'UTC+11', 'abbr' => ''],
            ['name' => 'Wake Island Time', 'utcOffset' => 'UTC+12', 'abbr' => ''],
            ['name' => 'West Africa Summer Time', 'utcOffset' => 'UTC+02', 'abbr' => ''],
            ['name' => 'West Africa Time', 'utcOffset' => 'UTC+01', 'abbr' => ''],
            ['name' => 'Western European Summer Time', 'utcOffset' => 'UTC+01', 'abbr' => ''],
            ['name' => 'Western European Time', 'utcOffset' => 'UTC±00', 'abbr' => ''],
            ['name' => 'Western Indonesian Time', 'utcOffset' => 'UTC+07', 'abbr' => ''],
            ['name' => 'Eastern Indonesian Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Central Indonesia Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'West Greenland Summer Time', 'utcOffset' => 'UTC−02', 'abbr' => ''],
            ['name' => 'West Greenland Time', 'utcOffset' => 'UTC−03', 'abbr' => ''],
            ['name' => 'Western Standard Time', 'utcOffset' => 'UTC+08', 'abbr' => ''],
            ['name' => 'Yakutsk Time', 'utcOffset' => 'UTC+09', 'abbr' => ''],
            ['name' => 'Yekaterinburg Time', 'utcOffset' => 'UTC+05', 'abbr' => ''],
        ];
    }

    public function securedEncrypt($user, $data) {
        $first_key = $user->id;
        $second_key = $user->created_at;

        $method = "aes-256-cbc";
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $first_encrypted = openssl_encrypt($data,$method,$first_key, OPENSSL_RAW_DATA ,$iv);
        $second_encrypted = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);

        $output = base64_encode($iv.$second_encrypted.$first_encrypted);

        return $output;
    }

    public function securedDecrypt($user, $input) {
        $first_key = $user->id;
        $second_key = $user->created_at;

        $mix = base64_decode($input);

        $method = "aes-256-cbc";
        $iv_length = openssl_cipher_iv_length($method);

        $iv = substr($mix,0,$iv_length);
        $second_encrypted = substr($mix,$iv_length,64);
        $first_encrypted = substr($mix,$iv_length+64);

        $data = openssl_decrypt($first_encrypted,$method,$first_key,OPENSSL_RAW_DATA,$iv);
        $second_encrypted_new = hash_hmac('sha3-512', $first_encrypted, $second_key, TRUE);

        if (hash_equals($second_encrypted,$second_encrypted_new))
            return $data;

        return false;
    }

    public function googleAuthPairing($user)
    {
        if ($user->first_name) {
            $username = $user->first_name;
        } elseif ($user->last_name) {
            $username = $user->last_name;
        } else {
            $username = $user->name;
        }
        $body = http_build_query([
            'appName' => 'SERPs',
            'appInfo' => $username,
            'secretCode' => $user->two_fa_temp_code,
        ]);
        $bodyLenght = strlen($body);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => self::GOOGLE_API_AUTH . 'api.asmx/Pair',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
                "Content-Length: $bodyLenght",
            ],
        ]);

        $response = curl_exec($curl);

        $err = curl_error($curl);
        if ($err) {
            throw new \Exception("Authenticatorapi cURL Error #:" . $err);
        }

        curl_close($curl);

        $xmlResponse = new \SimpleXMLElement($response);

        return [
            'setupCode' => (string) $xmlResponse->ManualSetupCode,
            'qrHtml' => (string) $xmlResponse->Html,
        ];
    }

    public function googleAuthValidate($user, $pin)
    {
        $body = http_build_query([
            'pin' => $pin,
            'secretCode' => $user->two_fa_temp_code,
        ]);
        $bodyLenght = strlen($body);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => self::GOOGLE_API_AUTH . 'api.asmx/ValidatePin',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
                "Content-Length: $bodyLenght",
            ],
        ]);

        $response = curl_exec($curl);

        $err = curl_error($curl);
        if ($err) {
            throw new \Exception("Authenticatorapi cURL Error #:" . $err);
        }

        curl_close($curl);

        $xmlResponse = new \SimpleXMLElement($response);

        return ((string) $xmlResponse) == 'true';
    }

    public function generateSecret($user)
    {
        if ($user->first_name) {
            $username = $user->first_name;
        } elseif ($user->last_name) {
            $username = $user->last_name;
        } else {
            $username = $user->name;
        }
        $google2fa = new Google2FA();
        $code = $google2fa->generateSecretKey();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'SERPs',
            $username,
            $code
        );

        $renderer = new GDLibRenderer(400);
        $writer = new Writer($renderer);


        $qrPath = Constants::userFolder($user) . '/prqrcode';
        if (!is_dir($qrPath)) {
            mkdir($qrPath);
        }

        $writer->writeFile($qrCodeUrl, $qrPath . '/qrcode.png');

//        $recoveryCodes = $this->generateRecoveryCodes();
//        foreach ($recoveryCodes as $recoveryCode) {
//            User2faRecavery::create([
//                'user_id' => $user->id,
//                'code' => $this->securedEncrypt($user, $recoveryCode),
//            ]);
//        }

        return $code;
    }

    public function generateRecoveryCodes($times = 8, $random = 10)
    {
        return Collection::times($times, function () use ($random) {
            return Str::random($random).'-'.Str::random($random);
        })->toArray();
    }


    public function verify($user, $code)
    {
        $secret = $this->securedDecrypt($user, $user->two_fa_temp_code);
        $google2fa = new Google2FA();
        return $google2fa->verifyKey($secret, $code);
    }
}
