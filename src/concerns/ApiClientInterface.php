<?php
/**
 * Interface ApiClientInterface
 * 
 * contract for API client implementations.
 */
interface ApiClientInterface {
    /**
     * send a GET request to the specified URI.
     *
     * @param string $uri    The URI to send the request to.
     * @param array  $params Optional query parameters.
     *
     * @return string|object The API response as a string or object.
     */
    public function get(string $uri, array $params = []): string|object;

    /**
     * send a POST request to the specified URI.
     *
     * @param string $uri  The URI to send the request to.
     * @param array  $data The data to be sent in the request body.
     *
     * @return string|object The API response as a string or object.
     */
    public function post(string $uri, array $data = []): string|object;

    /**
     * send a PUT request to the specified URI.
     *
     * @param string $uri  The URI to send the request to.
     * @param array  $data The data to be sent in the request body.
     *
     * @return string|object The API response as a string or object.
     */
    public function put(string $uri, array $data = []): string|object;

    /**
     * send a DELETE request to the specified URI.
     *
     * @param string $uri The URI to send the request to.
     *
     * @return string|object The API response as a string or object.
     */
    public function delete(string $uri): string|object;

    /**
     * send a request with the specified method to the given URL.
     *
     * @param string $method The HTTP method to use for the request.
     * @param string $url    The full URL to send the request to.
     * @param array  $data   Optional data to be sent with the request.
     *
     * @return string|object The API response as a string or object.
     */
    public function sendRequest(string $method, string $url, array $data = []): string|object;
}