<?php

namespace mputkowski\DiscordAlerts\Discord;

use GuzzleHttp\ClientInterface;
use Throwable;

class Client
{
    const API_URL = 'https://discord.com/api/v10';

    private $http;
    private $token;
    private $timeout;
    private $lastError;

    public function __construct(ClientInterface $http, $token, $timeout = 3)
    {
        $this->http = $http;
        $this->token = (string) $token;
        $this->timeout = (float) $timeout;
    }

    /**
     * Why the last postMessage() failed, or null if it succeeded.
     */
    public function lastError()
    {
        return $this->lastError;
    }

    /**
     * Post a message to a channel. Never throws; returns whether Discord accepted it.
     */
    public function postMessage($channelId, array $payload): bool
    {
        $this->lastError = null;

        try {
            $response = $this->http->request('POST', self::API_URL . '/channels/' . rawurlencode($channelId) . '/messages', [
                'headers' => [
                    'Authorization' => 'Bot ' . $this->token,
                    'User-Agent' => 'DiscordBot (https://github.com/mputkowski/laravel-discord-alerts, 0.1)',
                ],
                'json' => $payload,
                'timeout' => $this->timeout,
                'connect_timeout' => $this->timeout,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return true;
            }

            $this->lastError = $this->describe($status, (string) $response->getBody());

            return false;
        } catch (Throwable $e) {
            $this->lastError = 'Request failed: ' . $e->getMessage();

            return false;
        }
    }

    private function describe($status, $body)
    {
        $data = json_decode($body, true);
        $detail = is_array($data) && isset($data['message']) ? ': ' . $data['message'] . (isset($data['code']) ? ' (code ' . $data['code'] . ')' : '') : '';

        $hints = [
            401 => 'the bot token is invalid, check DISCORD_ALERTS_BOT_TOKEN',
            403 => 'the bot cannot post in this channel, grant it View Channel, Send Messages and Embed Links',
            404 => 'the channel was not found, check the channel ID and that the bot is in the server',
            429 => 'rate limited by Discord',
        ];

        return 'Discord responded with HTTP ' . $status . $detail . (isset($hints[$status]) ? ' - ' . $hints[$status] : '');
    }
}
