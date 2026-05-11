<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel\Facades;

use Dunn\QrCode\Builder;
use Illuminate\Support\Facades\Facade;

/**
 * Static-call shortcut for the bound {@see \Dunn\QrCode\Laravel\QrCodeFactory}.
 *
 * @method static Builder create(string|\Stringable $data)
 * @method static Builder url(string $url)
 * @method static Builder text(string $text)
 * @method static Builder phone(string $number)
 * @method static Builder sms(string $number, ?string $body = null, bool $useSmsUri = false)
 * @method static Builder email(string $to, ?string $subject = null, ?string $body = null, list<string> $cc = [], list<string> $bcc = [])
 * @method static Builder geo(float $latitude, float $longitude, ?string $label = null)
 * @method static Builder wifi(string $ssid, ?string $password = null, \Dunn\QrCode\Payload\WifiAuth $auth = \Dunn\QrCode\Payload\WifiAuth::WPA, bool $hidden = false)
 * @method static Builder vCard(\Dunn\QrCode\Payload\VCard $vcard)
 * @method static Builder event(\Dunn\QrCode\Payload\Event $event)
 * @method static string svg(string|\Stringable $data, ?\Dunn\QrCode\Renderer\Renderer $renderer = null)
 * @method static \Dunn\QrCode\Renderer\Renderer renderer()
 * @method static \Dunn\QrCode\Laravel\QrCodeFactory withRenderer(\Dunn\QrCode\Renderer\Renderer $renderer)
 */
final class QrCode extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'qrcode';
    }
}
