<?php
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Illuminate\Http\Request;


//use PHPUnit_Framework_Constraint;
use Symfony\Component\DomCrawler\Crawler;
use SebastianBergmann\Comparator\ComparisonFailure;
use PHPUnit_Framework_ExpectationFailedException as FailedExpection;

class TestCase extends PHPUnit_Framework_TestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    public function createResponse($request = null)
    {
	$r = new PODataExample\router();
	$response = $r->route($request);
	return $response;
//	echo $response;
    }
/**
     * The DomCrawler instance.
     *
     * @var \Symfony\Component\DomCrawler\Crawler
     */
    protected $crawler;
    /**
     * Nested crawler instances used by the "within" method.
     *
     * @var array
     */
    protected $subCrawlers = [];
    /**
     * All of the stored inputs for the current page.
     *
     * @var array
     */
    protected $inputs = [];
    /**
     * All of the stored uploads for the current page.
     *
     * @var array
     */
    protected $uploads = [];
    /**
     * Visit the given URI with a GET request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function visit($uri)
    {
        return $this->makeRequest('GET', $uri);
    }
    /**
     * Make a request to the application and create a Crawler instance.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @return $this
     */
    protected function makeRequest($method, $uri, $parameters = [], $cookies = [], $files = [])
    {
        $uri = $this->prepareUrlForRequest($uri);
        $this->call($method, $uri, $parameters, $cookies, $files);
        $this->clearInputs()->followRedirects()->assertPageLoaded($uri);
        $this->currentUri = $uri;//$this->app->make('request')->fullUrl();
//        $this->crawler = new Crawler($this->response->getContent(), $this->currentUri);
        return $this;
    }
    /**
     * Clean the crawler and the subcrawlers values to reset the page context.
     *
     * @return void
     */
    protected function resetPageContext()
    {
        $this->crawler = null;
        $this->subCrawlers = [];
    }
    /**
     * Make a request to the application using the given form.
     *
     * @param  \Symfony\Component\DomCrawler\Form  $form
     * @param  array  $uploads
     * @return $this
     */
    protected function makeRequestUsingForm(Form $form, array $uploads = [])
    {
        $files = $this->convertUploadsForTesting($form, $uploads);
        return $this->makeRequest(
            $form->getMethod(), $form->getUri(), $this->extractParametersFromForm($form), [], $files
        );
    }
    /**
     * Extract the parameters from the given form.
     *
     * @param  \Symfony\Component\DomCrawler\Form  $form
     * @return array
     */
    protected function extractParametersFromForm(Form $form)
    {
        parse_str(http_build_query($form->getValues()), $parameters);
        return $parameters;
    }
    /**
     * Follow redirects from the last response.
     *
     * @return $this
     */
    protected function followRedirects()
    {
        while ($this->response->isRedirect()) {
            $this->makeRequest('GET', $this->response->getTargetUrl());
        }
        return $this;
    }
    /**
     * Clear the inputs for the current page.
     *
     * @return $this
     */
    protected function clearInputs()
    {
        $this->inputs = [];
        $this->uploads = [];
        return $this;
    }
    /**
     * Assert that the current page matches a given URI.
     *
     * @param  string  $uri
     * @return $this
     */
    protected function seePageIs($uri)
    {
        $this->assertPageLoaded($uri = $this->prepareUrlForRequest($uri));
        $this->assertEquals(
            $uri, $this->currentUri, "Did not land on expected page [{$uri}].\n"
        );
        return $this;
    }
    /**
     * Assert that the current page matches a given named route.
     *
     * @param  string  $route
     * @param  array  $parameters
     * @return $this
     */
    protected function seeRouteIs($route, $parameters = [])
    {
        return $this->seePageIs(route($route, $parameters));
    }
    /**
     * Assert that a given page successfully loaded.
     *
     * @param  string  $uri
     * @param  string|null  $message
     * @return void
     *
     * @throws \Illuminate\Foundation\Testing\HttpException
     */
    protected function assertPageLoaded($uri, $message = null)
    {
        $status = $this->response->getStatusCode();
        try {
            $this->assertEquals(200, $status);
        } catch (PHPUnitException $e) {
            $message = $message ?: "A request to [{$uri}] failed. Received status code [{$status}].";
            $responseException = isset($this->response->exception)
                    ? $this->response->exception : null;
            throw new HttpException($message, null, $responseException);
        }
    }
    /**
     * Narrow the test content to a specific area of the page.
     *
     * @param  string  $element
     * @param  \Closure  $callback
     * @return $this
     */
    public function within($element, Closure $callback)
    {
        $this->subCrawlers[] = $this->crawler()->filter($element);
        $callback();
        array_pop($this->subCrawlers);
        return $this;
    }
    /**
     * Get the current crawler according to the test context.
     *
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function crawler()
    {
        if (! empty($this->subCrawlers)) {
            return end($this->subCrawlers);
        }
        return $this->crawler;
    }
    /**
     * Assert the given constraint.
     *
     * @param  \Illuminate\Foundation\Testing\Constraints\PageConstraint  $constraint
     * @param  bool  $reverse
     * @param  string  $message
     * @return $this
     */
    protected function assertInPage(PageConstraint $constraint, $reverse = false, $message = '')
    {
        if ($reverse) {
            $constraint = new ReversePageConstraint($constraint);
        }
        self::assertThat(
            $this->crawler() ?: $this->response->getContent(),
            $constraint, $message
        );
        return $this;
    }
    /**
     * Assert that a given string is seen on the current HTML.
     *
     * @param  string  $text
     * @param  bool  $negate
     * @return $this
     */
    public function see($text, $negate = false)
    {
        return $this->assertInPage(new HasSource($text), $negate);
    }
    /**
     * Assert that a given string is not seen on the current HTML.
     *
     * @param  string  $text
     * @return $this
     */
    public function dontSee($text)
    {
        return $this->assertInPage(new HasSource($text), true);
    }
    /**
     * Assert that an element is present on the page.
     *
     * @param  string  $selector
     * @param  array  $attributes
     * @param  bool  $negate
     * @return $this
     */
    public function seeElement($selector, array $attributes = [], $negate = false)
    {
        return $this->assertInPage(new HasElement($selector, $attributes), $negate);
    }
    /**
     * Assert that an element is not present on the page.
     *
     * @param  string  $selector
     * @param  array  $attributes
     * @return $this
     */
    public function dontSeeElement($selector, array $attributes = [])
    {
        return $this->assertInPage(new HasElement($selector, $attributes), true);
    }
    /**
     * Assert that a given string is seen on the current text.
     *
     * @param  string  $text
     * @param  bool  $negate
     * @return $this
     */
    public function seeText($text, $negate = false)
    {
        return $this->assertInPage(new HasText($text), $negate);
    }
    /**
     * Assert that a given string is not seen on the current text.
     *
     * @param  string  $text
     * @return $this
     */
    public function dontSeeText($text)
    {
        return $this->assertInPage(new HasText($text), true);
    }
    /**
     * Assert that a given string is seen inside an element.
     *
     * @param  string  $element
     * @param  string  $text
     * @param  bool  $negate
     * @return $this
     */
    public function seeInElement($element, $text, $negate = false)
    {
        return $this->assertInPage(new HasInElement($element, $text), $negate);
    }
    /**
     * Assert that a given string is not seen inside an element.
     *
     * @param  string  $element
     * @param  string  $text
     * @return $this
     */
    public function dontSeeInElement($element, $text)
    {
        return $this->assertInPage(new HasInElement($element, $text), true);
    }
    /**
     * Assert that a given link is seen on the page.
     *
     * @param  string $text
     * @param  string|null $url
     * @param  bool  $negate
     * @return $this
     */
    public function seeLink($text, $url = null, $negate = false)
    {
        return $this->assertInPage(new HasLink($text, $url), $negate);
    }
    /**
     * Assert that a given link is not seen on the page.
     *
     * @param  string  $text
     * @param  string|null  $url
     * @return $this
     */
    public function dontSeeLink($text, $url = null)
    {
        return $this->assertInPage(new HasLink($text, $url), true);
    }
    /**
     * Assert that an input field contains the given value.
     *
     * @param  string  $selector
     * @param  string  $expected
     * @param  bool  $negate
     * @return $this
     */
    public function seeInField($selector, $expected, $negate = false)
    {
        return $this->assertInPage(new HasValue($selector, $expected), $negate);
    }
    /**
     * Assert that an input field does not contain the given value.
     *
     * @param  string  $selector
     * @param  string  $value
     * @return $this
     */
    public function dontSeeInField($selector, $value)
    {
        return $this->assertInPage(new HasValue($selector, $value), true);
    }
    /**
     * Assert that the expected value is selected.
     *
     * @param  string  $selector
     * @param  string  $value
     * @param  bool  $negate
     * @return $this
     */
    public function seeIsSelected($selector, $value, $negate = false)
    {
        return $this->assertInPage(new IsSelected($selector, $value), $negate);
    }
    /**
     * Assert that the given value is not selected.
     *
     * @param  string  $selector
     * @param  string  $value
     * @return $this
     */
    public function dontSeeIsSelected($selector, $value)
    {
        return $this->assertInPage(new IsSelected($selector, $value), true);
    }
    /**
     * Assert that the given checkbox is selected.
     *
     * @param  string  $selector
     * @param  bool  $negate
     * @return $this
     */
    public function seeIsChecked($selector, $negate = false)
    {
        return $this->assertInPage(new IsChecked($selector), $negate);
    }
    /**
     * Assert that the given checkbox is not selected.
     *
     * @param  string  $selector
     * @return $this
     */
    public function dontSeeIsChecked($selector)
    {
        return $this->assertInPage(new IsChecked($selector), true);
    }
    /**
     * Click a link with the given body, name, or ID attribute.
     *
     * @param  string  $name
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    protected function click($name)
    {
        $link = $this->crawler()->selectLink($name);
        if (! count($link)) {
            $link = $this->filterByNameOrId($name, 'a');
            if (! count($link)) {
                throw new InvalidArgumentException(
                    "Could not find a link with a body, name, or ID attribute of [{$name}]."
                );
            }
        }
        $this->visit($link->link()->getUri());
        return $this;
    }
    /**
     * Fill an input field with the given text.
     *
     * @param  string  $text
     * @param  string  $element
     * @return $this
     */
    protected function type($text, $element)
    {
        return $this->storeInput($element, $text);
    }
    /**
     * Check a checkbox on the page.
     *
     * @param  string  $element
     * @return $this
     */
    protected function check($element)
    {
        return $this->storeInput($element, true);
    }
    /**
     * Uncheck a checkbox on the page.
     *
     * @param  string  $element
     * @return $this
     */
    protected function uncheck($element)
    {
        return $this->storeInput($element, false);
    }
    /**
     * Select an option from a drop-down.
     *
     * @param  string  $option
     * @param  string  $element
     * @return $this
     */
    protected function select($option, $element)
    {
        return $this->storeInput($element, $option);
    }
    /**
     * Attach a file to a form field on the page.
     *
     * @param  string  $absolutePath
     * @param  string  $element
     * @return $this
     */
    protected function attach($absolutePath, $element)
    {
        $this->uploads[$element] = $absolutePath;
        return $this->storeInput($element, $absolutePath);
    }
    /**
     * Submit a form using the button with the given text value.
     *
     * @param  string  $buttonText
     * @return $this
     */
    protected function press($buttonText)
    {
        return $this->submitForm($buttonText, $this->inputs, $this->uploads);
    }
    /**
     * Submit a form on the page with the given input.
     *
     * @param  string  $buttonText
     * @param  array  $inputs
     * @param  array  $uploads
     * @return $this
     */
    protected function submitForm($buttonText, $inputs = [], $uploads = [])
    {
        $this->makeRequestUsingForm($this->fillForm($buttonText, $inputs), $uploads);
        return $this;
    }
    /**
     * Fill the form with the given data.
     *
     * @param  string  $buttonText
     * @param  array  $inputs
     * @return \Symfony\Component\DomCrawler\Form
     */
    protected function fillForm($buttonText, $inputs = [])
    {
        if (! is_string($buttonText)) {
            $inputs = $buttonText;
            $buttonText = null;
        }
        return $this->getForm($buttonText)->setValues($inputs);
    }
    /**
     * Get the form from the page with the given submit button text.
     *
     * @param  string|null  $buttonText
     * @return \Symfony\Component\DomCrawler\Form
     *
     * @throws \InvalidArgumentException
     */
    protected function getForm($buttonText = null)
    {
        try {
            if ($buttonText) {
                return $this->crawler()->selectButton($buttonText)->form();
            }
            return $this->crawler()->filter('form')->form();
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                "Could not find a form that has submit button [{$buttonText}]."
            );
        }
    }
    /**
     * Store a form input in the local array.
     *
     * @param  string  $element
     * @param  string  $text
     * @return $this
     */
    protected function storeInput($element, $text)
    {
        $this->assertFilterProducesResults($element);
        $element = str_replace(['#', '[]'], '', $element);
        $this->inputs[$element] = $text;
        return $this;
    }
    /**
     * Assert that a filtered Crawler returns nodes.
     *
     * @param  string  $filter
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function assertFilterProducesResults($filter)
    {
        $crawler = $this->filterByNameOrId($filter);
        if (! count($crawler)) {
            throw new InvalidArgumentException(
                "Nothing matched the filter [{$filter}] CSS query provided for [{$this->currentUri}]."
            );
        }
    }
    /**
     * Filter elements according to the given name or ID attribute.
     *
     * @param  string  $name
     * @param  array|string  $elements
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function filterByNameOrId($name, $elements = '*')
    {
        $name = str_replace('#', '', $name);
        $id = str_replace(['[', ']'], ['\\[', '\\]'], $name);
        $elements = is_array($elements) ? $elements : [$elements];
        array_walk($elements, function (&$element) use ($name, $id) {
            $element = "{$element}#{$id}, {$element}[name='{$name}']";
        });
        return $this->crawler()->filter(implode(', ', $elements));
    }
    /**
     * Convert the given uploads to UploadedFile instances.
     *
     * @param  \Symfony\Component\DomCrawler\Form  $form
     * @param  array  $uploads
     * @return array
     */
    protected function convertUploadsForTesting(Form $form, array $uploads)
    {
        $files = $form->getFiles();
        $names = array_keys($files);
        $files = array_map(function (array $file, $name) use ($uploads) {
            return isset($uploads[$name])
                        ? $this->getUploadedFileForTesting($file, $uploads, $name)
                        : $file;
        }, $files, $names);
        $uploads = array_combine($names, $files);
        foreach ($uploads as $key => $file) {
            if (preg_match('/.*?(?:\[.*?\])+/', $key)) {
                $this->prepareArrayBasedFileInput($uploads, $key, $file);
            }
        }
        return $uploads;
    }
    /**
     * Store an array based file upload with the proper nested array structure.
     *
     * @param  array  $uploads
     * @param  string  $key
     * @param  mixed  $file
     */
    protected function prepareArrayBasedFileInput(&$uploads, $key, $file)
    {
        preg_match_all('/([^\[\]]+)/', $key, $segments);
        $segments = array_reverse($segments[1]);
        $newKey = array_pop($segments);
        foreach ($segments as $segment) {
            $file = [$segment => $file];
        }
        $uploads[$newKey] = $file;
        unset($uploads[$key]);
    }
    /**
     * Create an UploadedFile instance for testing.
     *
     * @param  array  $file
     * @param  array  $uploads
     * @param  string  $name
     * @return \Illuminate\Http\UploadedFile
     */
    protected function getUploadedFileForTesting($file, $uploads, $name)
    {
        return new UploadedFile(
            $file['tmp_name'], basename($uploads[$name]), $file['type'], $file['size'], $file['error'], true
        );
    }
/**
     * The last response returned by the application.
     *
     * @var \Illuminate\Http\Response
     */
    protected $response;
    /**
     * The current URL being viewed.
     *
     * @var string
     */
    protected $currentUri;
    /**
     * Additional server variables for the request.
     *
     * @var array
     */
    protected $serverVariables = [];
    /**
     * Disable middleware for the test.
     *
     * @return $this
     */
    public function withoutMiddleware()
    {
        $this->app->instance('middleware.disable', true);
        return $this;
    }
    /**
     * Visit the given URI with a JSON request.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function json($method, $uri, array $data = [], array $headers = [])
    {
        $files = $this->extractFilesFromDataArray($data);
        $content = json_encode($data);
        $headers = array_merge([
            'CONTENT_LENGTH' => mb_strlen($content, '8bit'),
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);
        $this->call(
            $method, $uri, [], [], $files, $this->transformHeadersToServerVars($headers), $content
        );
        return $this;
    }
    /**
     * Extract the file uploads from the given data array.
     *
     * @param  array  $data
     * @return array
     */
    protected function extractFilesFromDataArray(&$data)
    {
        $files = [];
        foreach ($data as $key => $value) {
            if ($value instanceof SymfonyUploadedFile) {
                $files[$key] = $value;
                unset($data[$key]);
            }
        }
        return $files;
    }
    /**
     * Visit the given URI with a GET request.
     *
     * @param  string  $uri
     * @param  array  $headers
     * @return $this
     */
    public function get($uri, array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);
        $this->call('GET', $uri, [], [], [], $server);
        return $this;
    }
    /**
     * Visit the given URI with a GET request, expecting a JSON response.
     *
     * @param string $uri
     * @param array $headers
     * @return $this
     */
    public function getJson($uri, array $headers = [])
    {
        return $this->json('GET', $uri, [], $headers);
    }
    /**
     * Visit the given URI with a POST request.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function post($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);
        $this->call('POST', $uri, $data, [], [], $server);
        return $this;
    }
    /**
     * Visit the given URI with a POST request, expecting a JSON response.
     *
     * @param string $uri
     * @param array  $data
     * @param array  $headers
     * @return $this
     */
    public function postJson($uri, array $data = [], array $headers = [])
    {
        return $this->json('POST', $uri, $data, $headers);
    }
    /**
     * Visit the given URI with a PUT request.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function put($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);
        $this->call('PUT', $uri, $data, [], [], $server);
        return $this;
    }
    /**
     * Visit the given URI with a PUT request, expecting a JSON response.
     *
     * @param string $uri
     * @param array  $data
     * @param array  $headers
     * @return $this
     */
    public function putJson($uri, array $data = [], array $headers = [])
    {
        return $this->json('PUT', $uri, $data, $headers);
    }
    /**
     * Visit the given URI with a PATCH request.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function patch($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);
        $this->call('PATCH', $uri, $data, [], [], $server);
        return $this;
    }
    /**
     * Visit the given URI with a PATCH request, expecting a JSON response.
     *
     * @param string $uri
     * @param array  $data
     * @param array  $headers
     * @return $this
     */
    public function patchJson($uri, array $data = [], array $headers = [])
    {
        return $this->json('PATCH', $uri, $data, $headers);
    }
    /**
     * Visit the given URI with a DELETE request.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function delete($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);
        $this->call('DELETE', $uri, $data, [], [], $server);
        return $this;
    }
    /**
     * Visit the given URI with a DELETE request, expecting a JSON response.
     *
     * @param string $uri
     * @param array  $data
     * @param array  $headers
     * @return $this
     */
    public function deleteJson($uri, array $data = [], array $headers = [])
    {
        return $this->json('DELETE', $uri, $data, $headers);
    }
    /**
     * Send the given request through the application.
     *
     * This method allows you to fully customize the entire Request object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return $this
     */
    public function handle(Request $request)
    {
        $this->currentUri = $request->fullUrl();
        $this->response = $this->app->prepareResponse($this->app->handle($request));
        return $this;
    }
    /**
     * Assert that the response contains JSON.
     *
     * @param  array|null  $data
     * @return $this
     */
    protected function shouldReturnJson(array $data = null)
    {
        return $this->receiveJson($data);
    }
    /**
     * Assert that the response contains JSON.
     *
     * @param  array|null  $data
     * @return $this|null
     */
    protected function receiveJson($data = null)
    {
        return $this->seeJson($data);
    }
    /**
     * Assert that the response contains an exact JSON array.
     *
     * @param  array  $data
     * @return $this
     */
    public function seeJsonEquals(array $data)
    {
        $actual = json_encode(Arr::sortRecursive(
            json_decode($this->response->getContent(), true)
        ));
        $this->assertEquals(json_encode(Arr::sortRecursive($data)), $actual);
        return $this;
    }
    /**
     * Assert that the response contains JSON.
     *
     * @param  array|null  $data
     * @param  bool  $negate
     * @return $this
     */
    public function seeJson(array $data = null, $negate = false)
    {
        if (is_null($data)) {
            $this->assertJson(
                $this->response->getContent(), "JSON was not returned from [{$this->currentUri}]."
            );
            return $this;
        }
        try {
            $this->seeJsonEquals($data);
            return $this;
        } catch (PHPUnit_Framework_ExpectationFailedException $e) {
            return $this->seeJsonContains($data, $negate);
        }
    }
    /**
     * Assert that the response doesn't contain JSON.
     *
     * @param  array|null  $data
     * @return $this
     */
    public function dontSeeJson(array $data = null)
    {
        return $this->seeJson($data, true);
    }
    /**
     * Assert that the JSON response has a given structure.
     *
     * @param  array|null  $structure
     * @param  array|null  $responseData
     * @return $this
     */
    public function seeJsonStructure(array $structure = null, $responseData = null)
    {
        if (is_null($structure)) {
            return $this->seeJson();
        }
        if (! $responseData) {
            $responseData = json_decode($this->response->getContent(), true);
        }
        foreach ($structure as $key => $value) {
            if (is_array($value) && $key === '*') {
                $this->assertInternalType('array', $responseData);
                foreach ($responseData as $responseDataItem) {
                    $this->seeJsonStructure($structure['*'], $responseDataItem);
                }
            } elseif (is_array($value)) {
                $this->assertArrayHasKey($key, $responseData);
                $this->seeJsonStructure($structure[$key], $responseData[$key]);
            } else {
                $this->assertArrayHasKey($value, $responseData);
            }
        }
        return $this;
    }
    /**
     * Assert that the response contains the given JSON.
     *
     * @param  array  $data
     * @param  bool  $negate
     * @return $this
     */
    protected function seeJsonContains(array $data, $negate = false)
    {
        $method = $negate ? 'assertFalse' : 'assertTrue';
        $actual = json_encode(Arr::sortRecursive(
            (array) $this->decodeResponseJson()
        ));
        foreach (Arr::sortRecursive($data) as $key => $value) {
            $expected = $this->formatToExpectedJson($key, $value);
            $this->{$method}(
                Str::contains($actual, $expected),
                ($negate ? 'Found unexpected' : 'Unable to find').' JSON fragment'.PHP_EOL."[{$expected}]".PHP_EOL.'within'.PHP_EOL."[{$actual}]."
            );
        }
        return $this;
    }
    /**
     * Assert that the response is a superset of the given JSON.
     *
     * @param  array  $data
     * @return $this
     */
    protected function seeJsonSubset(array $data)
    {
        $this->assertArraySubset($data, $this->decodeResponseJson());
        return $this;
    }
    /**
     * Validate and return the decoded response JSON.
     *
     * @return array
     */
    protected function decodeResponseJson()
    {
        $decodedResponse = json_decode($this->response->getContent(), true);
        if (is_null($decodedResponse) || $decodedResponse === false) {
            $this->fail('Invalid JSON was returned from the route. Perhaps an exception was thrown?');
        }
        return $decodedResponse;
    }
    /**
     * Format the given key and value into a JSON string for expectation checks.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return string
     */
    protected function formatToExpectedJson($key, $value)
    {
        $expected = json_encode([$key => $value]);
        if (Str::startsWith($expected, '{')) {
            $expected = substr($expected, 1);
        }
        if (Str::endsWith($expected, '}')) {
            $expected = substr($expected, 0, -1);
        }
        return trim($expected);
    }
    /**
     * Asserts that the status code of the response matches the given code.
     *
     * @param  int  $status
     * @return $this
     */
    protected function seeStatusCode($status)
    {
        $this->assertEquals($status, $this->response->getStatusCode());
        return $this;
    }
    /**
     * Asserts that the response contains the given header and equals the optional value.
     *
     * @param  string  $headerName
     * @param  mixed  $value
     * @return $this
     */
    protected function seeHeader($headerName, $value = null)
    {
        $headers = $this->response->headers;
        $this->assertTrue($headers->has($headerName), "Header [{$headerName}] not present on response.");
        if (! is_null($value)) {
            $this->assertEquals(
                $headers->get($headerName), $value,
                "Header [{$headerName}] was found, but value [{$headers->get($headerName)}] does not match [{$value}]."
            );
        }
        return $this;
    }
    /**
     * Asserts that the response contains the given cookie and equals the optional value.
     *
     * @param  string  $cookieName
     * @param  mixed  $value
     * @return $this
     */
    protected function seePlainCookie($cookieName, $value = null)
    {
        return $this->seeCookie($cookieName, $value, false);
    }
    /**
     * Asserts that the response contains the given cookie and equals the optional value.
     *
     * @param  string  $cookieName
     * @param  mixed  $value
     * @param  bool  $encrypted
     * @return $this
     */
    protected function seeCookie($cookieName, $value = null, $encrypted = true)
    {
        $headers = $this->response->headers;
        $exist = false;
        foreach ($headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                $exist = true;
                break;
            }
        }
        $this->assertTrue($exist, "Cookie [{$cookieName}] not present on response.");
        if (! $exist || is_null($value)) {
            return $this;
        }
        $cookieValue = $cookie->getValue();
        $actual = $encrypted
            ? $this->app['encrypter']->decrypt($cookieValue) : $cookieValue;
        $this->assertEquals(
            $actual, $value,
            "Cookie [{$cookieName}] was found, but value [{$actual}] does not match [{$value}]."
        );
        return $this;
    }
    /**
     * Define a set of server variables to be sent with the requests.
     *
     * @param  array  $server
     * @return $this
     */
    protected function withServerVariables(array $server)
    {
        $this->serverVariables = $server;
        return $this;
    }
    /**
     * Call the given URI and return the Response.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array   $parameters
     * @param  array   $cookies
     * @param  array   $files
     * @param  array   $server
     * @param  string  $content
     * @return \Illuminate\Http\Response
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->currentUri = $this->prepareUrlForRequest($uri);
        $this->resetPageContext();
        $symfonyRequest = SymfonyRequest::create(
            $this->currentUri, $method, $parameters,
            $cookies, $this->filterFiles($files), array_replace($this->serverVariables, $server), $content
        );
        $request = Request::createFromBase($symfonyRequest);
	$response = $this->createResponse($request);
        return $this->response = $response;
    }
    /**
     * Call the given HTTPS URI and return the Response.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array   $parameters
     * @param  array   $cookies
     * @param  array   $files
     * @param  array   $server
     * @param  string  $content
     * @return \Illuminate\Http\Response
     */
    public function callSecure($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $uri = $this->app['url']->secure(ltrim($uri, '/'));
        return $this->response = $this->call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
    /**
     * Call a controller action and return the Response.
     *
     * @param  string  $method
     * @param  string  $action
     * @param  array   $wildcards
     * @param  array   $parameters
     * @param  array   $cookies
     * @param  array   $files
     * @param  array   $server
     * @param  string  $content
     * @return \Illuminate\Http\Response
     */
    public function action($method, $action, $wildcards = [], $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $uri = $this->app['url']->action($action, $wildcards, true);
        return $this->response = $this->call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
    /**
     * Call a named route and return the Response.
     *
     * @param  string  $method
     * @param  string  $name
     * @param  array   $routeParameters
     * @param  array   $parameters
     * @param  array   $cookies
     * @param  array   $files
     * @param  array   $server
     * @param  string  $content
     * @return \Illuminate\Http\Response
     */
    public function route($method, $name, $routeParameters = [], $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $uri = $this->app['url']->route($name, $routeParameters);
        return $this->response = $this->call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
    /**
     * Turn the given URI into a fully qualified URL.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prepareUrlForRequest($uri)
    {
        if (Str::startsWith($uri, '/')) {
            $uri = substr($uri, 1);
        }
        if (! Str::startsWith($uri, 'http')) {
            $uri = $this->baseUrl.'/'.$uri;
        }
        return trim($uri, '/');
    }
    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @param  array  $headers
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers)
    {
        $server = [];
        $prefix = 'HTTP_';
        foreach ($headers as $name => $value) {
            $name = strtr(strtoupper($name), '-', '_');
            if (! Str::startsWith($name, $prefix) && $name != 'CONTENT_TYPE') {
                $name = $prefix.$name;
            }
            $server[$name] = $value;
        }
        return $server;
    }
    /**
     * Filter the given array of files, removing any empty values.
     *
     * @param  array  $files
     * @return mixed
     */
    protected function filterFiles($files)
    {
        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                continue;
            }
            if (is_array($file)) {
                if (! isset($file['name'])) {
                    $files[$key] = $this->filterFiles($files[$key]);
                } elseif (isset($files[$key]['error']) && $files[$key]['error'] !== 0) {
                    unset($files[$key]);
                }
                continue;
            }
            unset($files[$key]);
        }
        return $files;
    }
    /**
     * Assert that the client response has an OK status code.
     *
     * @return $this
     */
    public function assertResponseOk()
    {
        $actual = $this->response->getStatusCode();
        PHPUnit::assertTrue($this->response->isOk(), "Expected status code 200, got {$actual}.");
        return $this;
    }
    /**
     * Assert that the client response has a given code.
     *
     * @param  int  $code
     * @return $this
     */
    public function assertResponseStatus($code)
    {
        $actual = $this->response->getStatusCode();
        PHPUnit::assertEquals($code, $this->response->getStatusCode(), "Expected status code {$code}, got {$actual}.");
        return $this;
    }
    /**
     * Assert that the response view has a given piece of bound data.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     * @return $this
     */
    public function assertViewHas($key, $value = null)
    {
        if (is_array($key)) {
            return $this->assertViewHasAll($key);
        }
        if (! isset($this->response->original) || ! $this->response->original instanceof View) {
            return PHPUnit::assertTrue(false, 'The response was not a view.');
        }
        if (is_null($value)) {
            PHPUnit::assertArrayHasKey($key, $this->response->original->getData());
        } elseif ($value instanceof \Closure) {
            PHPUnit::assertTrue($value($this->response->original->$key));
        } else {
            PHPUnit::assertEquals($value, $this->response->original->$key);
        }
        return $this;
    }
    /**
     * Assert that the view has a given list of bound data.
     *
     * @param  array  $bindings
     * @return $this
     */
    public function assertViewHasAll(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                $this->assertViewHas($value);
            } else {
                $this->assertViewHas($key, $value);
            }
        }
        return $this;
    }
    /**
     * Assert that the response view is missing a piece of bound data.
     *
     * @param  string  $key
     * @return $this
     */
    public function assertViewMissing($key)
    {
        if (! isset($this->response->original) || ! $this->response->original instanceof View) {
            return PHPUnit::assertTrue(false, 'The response was not a view.');
        }
        PHPUnit::assertArrayNotHasKey($key, $this->response->original->getData());
        return $this;
    }
    /**
     * Assert whether the client was redirected to a given URI.
     *
     * @param  string  $uri
     * @param  array   $with
     * @return $this
     */
    public function assertRedirectedTo($uri, $with = [])
    {
        PHPUnit::assertInstanceOf('Illuminate\Http\RedirectResponse', $this->response);
        PHPUnit::assertEquals($this->app['url']->to($uri), $this->response->headers->get('Location'));
        $this->assertSessionHasAll($with);
        return $this;
    }
    /**
     * Assert whether the client was redirected to a given route.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @param  array   $with
     * @return $this
     */
    public function assertRedirectedToRoute($name, $parameters = [], $with = [])
    {
        return $this->assertRedirectedTo($this->app['url']->route($name, $parameters), $with);
    }
    /**
     * Assert whether the client was redirected to a given action.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @param  array   $with
     * @return $this
     */
    public function assertRedirectedToAction($name, $parameters = [], $with = [])
    {
        return $this->assertRedirectedTo($this->app['url']->action($name, $parameters), $with);
    }
    /**
     * Dump the content from the last response.
     *
     * @return void
     */
    public function dump()
    {
        $content = $this->response->getContent();
        $json = json_decode($content);
        if (json_last_error() === JSON_ERROR_NONE) {
            $content = $json;
        }
        dd($content);
    }
}




abstract class PageConstraint extends PHPUnit_Framework_Constraint
{
    /**
     * Make sure we obtain the HTML from the crawler or the response.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler|string  $crawler
     * @return string
     */
    protected function html($crawler)
    {
        return is_object($crawler) ? $crawler->html() : $crawler;
    }
    /**
     * Make sure we obtain the HTML from the crawler or the response.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler|string  $crawler
     * @return string
     */
    protected function text($crawler)
    {
        return is_object($crawler) ? $crawler->text() : strip_tags($crawler);
    }
    /**
     * Create a crawler instance if the given value is not already a Crawler.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler|string  $crawler
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function crawler($crawler)
    {
        return is_object($crawler) ? $crawler : new Crawler($crawler);
    }
    /**
     * Get the escaped text pattern for the constraint.
     *
     * @param  string  $text
     * @return string
     */
    protected function getEscapedPattern($text)
    {
        $rawPattern = preg_quote($text, '/');
        $escapedPattern = preg_quote(e($text), '/');
        return $rawPattern == $escapedPattern
            ? $rawPattern : "({$rawPattern}|{$escapedPattern})";
    }
    /**
     * Throw an exception for the given comparison and test description.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler|string  $crawler
     * @param  string  $description
     * @param  \SebastianBergmann\Comparator\ComparisonFailure|null  $comparisonFailure
     * @return void
     *
     * @throws \PHPUnit_Framework_ExpectationFailedException
     */
    protected function fail($crawler, $description, ComparisonFailure $comparisonFailure = null)
    {
        $html = $this->html($crawler);
        $failureDescription = sprintf(
            "%s\n\n\nFailed asserting that %s",
            $html, $this->getFailureDescription()
        );
        if (! empty($description)) {
            $failureDescription .= ": {$description}";
        }
        if (trim($html) != '') {
            $failureDescription .= '. Please check the content above.';
        } else {
            $failureDescription .= '. The response is empty.';
        }
        throw new FailedExpection($failureDescription, $comparisonFailure);
    }
    /**
     * Get the description of the failure.
     *
     * @return string
     */
    protected function getFailureDescription()
    {
        return 'the page contains '.$this->toString();
    }
    /**
     * Returns the reversed description of the failure.
     *
     * @return string
     */
    protected function getReverseFailureDescription()
    {
        return 'the page does not contain '.$this->toString();
    }
    /**
     * Get a string representation of the object.
     *
     * Placeholder method to avoid forcing definition of this method.
     *
     * @return string
     */
    public function toString()
    {
        return '';
    }
}

class HasSource extends PageConstraint
{
    /**
     * The expected HTML source.
     *
     * @var string
     */
    protected $source;
    /**
     * Create a new constraint instance.
     *
     * @param  string  $source
     * @return void
     */
    public function __construct($source)
    {
        $this->source = $source;
    }
    /**
     * Check if the source is found in the given crawler.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler|string  $crawler
     * @return bool
     */
    protected function matches($crawler)
    {
        $pattern = $this->getEscapedPattern($this->source);
        return preg_match("/{$pattern}/i", $this->html($crawler));
    }
    /**
     * Returns a string representation of the object.
     *
     * @return string
     */
    public function toString()
    {
        return "the HTML [{$this->source}]";
    }
}

class ReversePageConstraint extends PageConstraint
{
    /**
     * The page constraint instance.
     *
     * @var \Illuminate\Foundation\Testing\Constraints\PageConstraint
     */
    protected $pageConstraint;
    /**
     * Create a new reverse page constraint instance.
     *
     * @param  \Illuminate\Foundation\Testing\Constraints\PageConstraint  $pageConstraint
     * @return void
     */
    public function __construct(PageConstraint $pageConstraint)
    {
        $this->pageConstraint = $pageConstraint;
    }
    /**
     * Reverse the original page constraint result.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler  $crawler
     * @return bool
     */
    public function matches($crawler)
    {
        return ! $this->pageConstraint->matches($crawler);
    }
    /**
     * Get the description of the failure.
     *
     * This method will attempt to negate the original description.
     *
     * @return string
     */
    protected function getFailureDescription()
    {
        return $this->pageConstraint->getReverseFailureDescription();
    }
    /**
     * Get a string representation of the object.
     *
     * @return string
     */
    public function toString()
    {
        return $this->pageConstraint->toString();
    }
}
