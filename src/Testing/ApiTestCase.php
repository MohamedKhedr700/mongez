<?php

declare(strict_types=1);

namespace HZ\Illuminate\Mongez\Testing;

use HZ\Illuminate\Mongez\Contracts\Testing\ResponseStructure;
use HZ\Illuminate\Mongez\Traits\Testing\Messageable;
use HZ\Illuminate\Mongez\Traits\Testing\WithAccessToken;
use Illuminate\Support\Str;
use Tests\CreatesApplication;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use HZ\Illuminate\Mongez\Traits\Testing\WithRepository;
use Illuminate\Testing\LoggedExceptionCollection;

abstract class ApiTestCase extends TestCase
{
    use CreatesApplication;

    use WithFaker;

    use WithRepository;

    use WithAccessToken;

    use Messageable;

    // use RefreshDatabase;

    /**
     * If marked as true, a bearer token will be passed with Bearer in the Authorization Header
     * 
     * @var bool
     */
    protected bool $isAuthenticated = false;

    /**
     * Add Prefix to all routes
     * 
     * @var string
     */
    protected $apiPrefix = '/api';

    /**
     * Mark the request as authorized request
     * 
     * @param bool $isAuthenticated 
     * @return $this
     */
    public function isAuthorized(bool $isAuthenticated = true): self
    {
        $this->isAuthenticated = $isAuthenticated;

        return $this;
    }

    /**
     * Handle Authorization Header
     * 
     * @param array $headers
     * @return void
     */
    protected function handleAuthorizationHeader(array &$headers)
    {
        if (!empty($headers['Authorization'])) return;

        $headers['Authorization'] = $this->isAuthenticated ? 'Bearer ' . $this->getAccessToken() : 'key ' . env('API_KEY');
    }

    /**
     * Visit the given URI with a GET request, expecting a JSON response.
     *
     * @param  string  $uri
     * @param  array  $headers
     * @return \HZ\Illuminate\Mongez\TestingTestResponse
     */
    public function get($uri, array $headers = [])
    {
        $this->handleAuthorizationHeader($headers);

        return parent::getJson($uri, $headers);
    }

    /**
     * Visit the given URI with a POST request, expecting a JSON response.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return \HZ\Illuminate\Mongez\TestingTestResponse
     */
    public function post($uri, array $data = [], array $headers = [])
    {
        $this->handleAuthorizationHeader($headers);

        return parent::postJson($uri, $data, $headers);
    }

    /**
     * Visit the given URI with a PUT request, expecting a JSON response.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return \HZ\Illuminate\Mongez\TestingTestResponse
     */
    public function put($uri, array $data = [], array $headers = [])
    {
        $this->handleAuthorizationHeader($headers);

        return parent::putJson($uri, $data, $headers);
    }

    /**
     * Visit the given URI with a PATCH request, expecting a JSON response.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return \HZ\Illuminate\Mongez\TestingTestResponse
     */
    public function patch($uri, array $data = [], array $headers = [])
    {
        $this->handleAuthorizationHeader($headers);

        return parent::patchJson($uri, $data, $headers);
    }

    /**
     * Visit the given URI with a DELETE request, expecting a JSON response.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return \HZ\Illuminate\Mongez\TestingTestResponse
     */
    public function delete($uri, array $data = [], array $headers = [])
    {
        $this->handleAuthorizationHeader($headers);

        return parent::deleteJson($uri, $data, $headers);
    }

    /**
     * Visit the given URI with an OPTIONS request, expecting a JSON response.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return \HZ\Illuminate\Mongez\TestingTestResponse
     */
    public function options($uri, array $data = [], array $headers = [])
    {
        $this->handleAuthorizationHeader($headers);

        return parent::optionsJson($uri, $data, $headers);
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param  string  $method
     * @param  string  $route
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @param  array  $server
     * @param  string|null  $content
     * @return \HZ\Illuminate\Mongez\TestingTestResponse
     */
    public function call($method, $route, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $uri = $this->prepareUri($route);

        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        $response->setRoute($route);

        return $response;
    }

    /**
     * Create the test response instance from the given response.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return \Illuminate\Testing\TestResponse
     */
    protected function createTestResponse($response)
    {
        return tap(TestResponse::fromBaseResponse($response), function ($response) {
            $response->withExceptions(
                $this->app->bound(LoggedExceptionCollection::class)
                    ? $this->app->make(LoggedExceptionCollection::class)
                    : new LoggedExceptionCollection()
            );
        });
    }

    /**
     * Prepare the given uri
     * 
     * @param  string $uri
     * @return string
     */
    protected function prepareUri(string $uri): string
    {
        $uri = $this->apiPrefix . '/' . ltrim($uri, '/');

        if (Str::contains($uri, '?')) {
            $uri .= '&';
        } else {
            $uri .= '?';
        }

        $uri .= $this->isAuthenticated ? 'Token=' . $this->getAccessToken() : 'Key=' . env('API_KEY');

        return $uri;
    }

    /**
     * Generate data for the given keys and return corresponding data
     * 
     * @param array $filling
     * @return array
     */
    protected function fill(array $filling)
    {
        $data = [];

        foreach ($filling as $key => $value) {
            if (!is_numeric($key)) {
                $key = $value;
                $data[$key] = $value;
                continue;
            }

            if (Str::contains('password', $key)) {
                $length = null;
                if (Str::contains($key, ':')) {
                    [$key, $length] = explode(':', $key);
                }

                $data[$key] = $this->faker->password($length);
            } else {
                $data[$key] = $this->faker->$value;
            }
        }

        return $data;
    }
}