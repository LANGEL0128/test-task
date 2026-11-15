<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait SendsRawJson
{
    /**
     * Envía un cuerpo JSON mal formado (lo que `postJson` no permite, porque lo codifica).
     *
     * @param  array<string, string>  $headers
     */
    protected function sendMalformedJson(string $method, string $uri, array $headers = []): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $uri, [], [], [], $server, '{"title": "roto", ');
    }
}
