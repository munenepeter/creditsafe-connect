<?php

require_once 'concerns/ApiClientInterface.php';
require_once 'concerns/CreditSafeRateLimiting.php';


final class CreditSafeAPI extends CreditSafeRateLimiting implements ApiClientInterface  {

    public $username;
    public $password;

    protected $base_url;
    protected $token;

    public $http_code;
    public $debug = false;

    protected $tokens_file = __DIR__.'/tokens.bin';

    public $request;

    public function __construct(string $env) {
        $this->base_url = ($env === 'production') ? 'https://connect.creditsafe.com/v1' : 'https://connect.sandbox.creditsafe.com/v1';
        $this->debug = ($env === 'production') ? false : true;

        # TODO: handle then an inputs to this constructor
        $this->username = ''; #fill these in
        $this->password = ''; #fill these in
    }
    /**
     * autheticate
     *
     * @return string The auth token from creditsafe
     * 
     * @see https://doc.creditsafe.com/tag/Authentication/operation/Authenticate
     */
    public function authenticate(){

         // in a lockout period
        if ($this->isGloballyLocked()) {
            $this->handleRateLimitResponse("Global rate limit exceeded. Please wait before trying again.");
        }

        $this->incrementGlobalRequestCounter();

        if ($this->hasExceededGlobalThreshold()) {
            $this->setGlobalLockout();
            $this->handleRateLimitResponse("Global rate limit exceeded. Please wait before trying again.");
        }

        // if this specific request is rate limited
        if ($this->isRequestRateLimited()) {
            $this->handleRateLimitResponse("Too many invalid attempts. Please wait before trying again.");
        }


        $response = $this->post('/authenticate', [
            "username" => $this->username,
            "password" => $this->password,
        ]);

        if(isset($response->ValidationErrors)){
            $this->recordInvalidAttempt();
            echo 'There were issues with your credentials, please fix them to continue';
            http_response_code(400);
            exit;
        }

        if (isset($response->token) && !empty($response->token)) {
            $this->token = $response->token;
            $this->write_token_to_file();
        }

        return $this->token;
    }

    private function getToken(): string {
        if (!file_exists($this->tokens_file)) {
            return $this->authenticate();
        }

        $tokens = json_decode(file_get_contents($this->tokens_file), true);

        if (isset($tokens[$this->username]) && $tokens[$this->username]['expiry_time'] > time()) {
            $this->token = $tokens[$this->username]['token'];
            if ($this->debug)
                printf("TOKEN CACHE: HIT\n");
            return $this->token;
        }
        if ($this->debug)
            printf("TOKEN CACHE: MISS\n");

        return $this->authenticate();
    }

    // START KYC API PROTECT (NEW API)

    /**
     * Create a profile.
     *
     * Uses the name and type provided by the user to create a profile.
     *
     * @param string $name The name of the profile being created. Must be unique across profiles.
     * @param string $type The profile type to be created. Enum: "trust", "individual", "soleTrader", "company", "plc", "partnership", "otherEntity".
     * @param array $additional_params Optional additional parameters for profile creation.
     * @return string|object The response from the API.
     * @throws \Exception If the API request fails or returns an error.
     * 
     * @see https://doc.creditsafe.com/tag/KYC-Profile-Management/operation/KYCProtectCreateProfile/
     */
    public function createProfile(string $name, string $type, array $additional_params = []): string|object  {
        $valid_types = ["trust", "individual", "soleTrader", "company", "plc", "partnership", "otherEntity"];

        // make sure they have supplied a correct type
        if (!in_array($type, $valid_types)) {
            throw new \InvalidArgumentException("Invalid profile type provided.");
        }

        $payload = array_merge([
            'name' => $name,
            'type' => $type,
            'details' => $additional_params['details'] ?? null,
            'internalId' => $additional_params['internalId'] ?? null,
            'assignedToId' => $additional_params['assignedToId'] ?? null,
            'kycReviewOn' => $additional_params['kycReviewOn'] ?? null,
            'status' => $additional_params['status'] ?? null,
            'riskRating' => $additional_params['riskRating'] ?? null,
            'kycComments' => $additional_params['kycComments'] ?? null,
        ], $additional_params);

        // ensure 'details' is an array and contains required properties - ie legalName
        if (!isset($payload['details']['legalName']) || empty($payload['details']['legalName'])) {
            throw new \InvalidArgumentException("'legalName' is required in 'details'.");
        }

        $response = $this->post('/compliance/kyc-protect/profiles', $payload);

        // check for api response errors, if not among the ones for this endpoint, fail gracefully
        $this->handleForeignHttpErrors([201, 400, 401, 403, 409]);

        return $response;
    }

   

    public function amlSearch($data)  {
        if (empty($this->token)) {
            $this->getToken();
        }

        return $this->post('/localSolutions/GB/identitysearch',$data);
    }
    /**
     * Company Search Criteria
     * 
     * Search for Companies according to the provided Search Criteria
     *
     * @param array $query
     * @return string|object
     * 
     * @see https://doc.creditsafe.com/tag/Companies/operation/companySearch
     */
    public function companySearch(array $query){
        # TODO: remove repeating code, handle regeneration of token
        if (empty($this->token)) {
            $this->getToken();
        }
        # TODO: handle this in a better way
        if(!isset($query['countries']) || empty($query['countries']) || !is_string($query['countries'])){
            throw new \InvalidArgumentException("'countries' must be present and a non-empty string");    
        }

        $response = $this->get('/companies', $query);

        $this->handleForeignHttpErrors([200, 400, 401]);

        return $response;
    }
    /**
     * Company Search Criteria
     * 
     * Returns the set of available Company Search parameters/fields for a provided list of countries.
     *
     * @param array $query
     * @return string|object
     * 
     * @example
     * $client = new ApiClient();
     * $response = $client->companySearchCriteria([
     *     'countries' => 'US,GB',
     * ]);
     * print_r($response);
     * 
     * @see https://doc.creditsafe.com/tag/Companies/operation/companySearchCriteria
     */
    public function companySearchCriteria(array $query){
        # TODO: remove repeating code, handle regeneration of token
        if (empty($this->token)) {
            $this->getToken();
        }
        # TODO: handle this in a better way
        if(!isset($query['countries']) || empty($query['countries']) || !is_string($query['countries'])){
            throw new \InvalidArgumentException("'countries' must be present and a non-empty string");    
        }
        $response = $this->get('/companies/searchcriteria', $query);

        $this->handleForeignHttpErrors([200, 400, 401, 403]);

        return $response;
    }

    /*
        ---------------------------------
        HELPER FUNCTIONS FOR THIS CLASS
        ---------------------------------
    */
    private function write_token_to_file() {
        $tokens = [];

        if (!file_exists($this->tokens_file)) {
            touch($this->tokens_file);
            return;
        }
    
        $tokens = json_decode(file_get_contents($this->tokens_file), true);
        
        // check if token for this username exists & if it has not expired
        if (isset($tokens[$this->username]) && $tokens[$this->username]['expiry_time'] > time()) {
           return;
        }

        $tokens[$this->username] = [
            'token' => $this->token,
            'expiry_time' => time() + 3600 // 1 hour see - https://doc.creditsafe.com/tag/Authentication/
        ];

        file_put_contents($this->tokens_file, json_encode($tokens));
    }
    public function get(string $uri, array $params = []): string|object {
        $url = $this->base_url . $uri . '?' . http_build_query($params);
        return $this->sendRequest('GET', $url);
    }

    public function post(string $uri, array $data = []): string|object {
        $url = $this->base_url . $uri;
        return $this->sendRequest('POST', $url, $data);
    }

    public function put(string $uri, array $data = []): string|object {
        $url = $this->base_url . $uri;
        return $this->sendRequest('PUT', $url, $data);
    }

    public function delete(string $uri): string|object {
        $url = $this->base_url . $uri;
        return $this->sendRequest('DELETE', $url);
    }
    /**
     * Handle Foreign HTTP Codes
     *
     * Here we handle any http code that is not specifed by an endpoint
     * known codes are from the api documentation, caller for this fn
     * 
     * @param array $known_http_codes
     * @return void
     * 
     * @throws \Exception If we have a http code that was not speficied by an endpoint
     */
    private function handleForeignHttpErrors(array $known_http_codes){
        // add the debug inside to save mem as we don't need it if we know res code
        if (!in_array($this->http_code, $known_http_codes)) {

            // get the caller fn so that we can print a beautiful msg for user on what failed
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
            $caller = $dbt[1]['function'] ?? 'Action';

            throw new \Exception(sprintf("Failed to %s. Status Code: %d"),$caller, $this->http_code);
        }
    }

    private function sendRequest(string $method, string $url, array $data = []): string|object  {
        $curl = curl_init();

        $headers = [
            'Content-Type: application/json'
        ];

        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                $options[CURLOPT_CUSTOMREQUEST] = 'POST';
                break;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

    
        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $this->http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($error) {
            return $error;
        }
        $this->request = sprintf("METHOD: %s URL: %s", $method, $url);

        return json_decode($response);
    }
}

