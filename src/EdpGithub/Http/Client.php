<?php

namespace EdpGithub\Http;

use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Http;
use Zend\ServiceManager\ServiceManager;

class Client implements EventManagerAwareInterface, ClientInterface
{
    /**
     * @var EventManager
     */
    protected $events;

    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var Http\Client
     */
    protected $httpClient;

    /**
     * @var Http\Client\Adapter\Curl
     */
    protected $httpAdapter;

    /**
     * @var Http\Response
     */
    protected $response;

    /**
     * @var Http\Request
     */
    protected $request;

    /**
     * @var array
     */
    private $options = array(
        'base_url'    => 'https://api.github.com/',
        'timeout'     => 10,
        'api_version' => 'beta',
    );

    /**
     * Get Request
     *
     * @param  string $path
     * @param  array  $parameters
     * @param  array  $headers
     * @return Http\Response
     */
    public function get($path, array $parameters = array(), array $headers = array())
    {
        return $this->request($path, $parameters, 'GET', $headers);
    }

    /**
     * Send POST Request
     *
     * @param  string $path
     * @param  array  $parameters
     * @param  array  $headers
     * @return Http\Response
     */
    public function post($path, array $parameters = array(), array $headers = array())
    {
        return $this->request($path, $parameters, 'POST', $headers);
    }

    /**
     * Send Delete Request
     *
     * @param  string $path
     * @param  array  $parameters
     * @param  array  $headers
     * @return Http\Response
     */
    public function delete($path, array $parameters = array(), array $headers = array())
    {
        return $this->request($path, $parameters, 'DELETE', $headers);
    }

    /**
     * Send Put Request
     *
     * @param  string $path
     * @param  array  $headers
     * @return Http\Response
     */
    public function put($path, array $headers = array())
    {
        return $this->request($path, array(), 'PUT', $headers);
    }

    /**
     * Send Request
     *
     * @param  string $path
     * @param  array  $parameters
     * @param  string $httpMethod
     * @param  array  $headers
     * @return Http\Response
     */
    public function request($path, array $parameters = array(), $httpMethod = 'GET', array $headers = array())
    {
        $client = $this->getHttpClient($path);
        $request = $client->getRequest();
        $client->setMethod($httpMethod);
        $client->setHeaders($headers);

        if ($httpMethod == 'GET') {
            $query = $request->getQuery();
            foreach ($parameters as $key => $value) {
                $query->set($key, $value);
            }
        } elseif ($httpMethod == 'POST') {
            $request->setContent(json_encode($parameters));
        }

        //Trigger Pre Send Event to modify Request Object
        $this->getEventManager()->trigger('pre.send', $request);

        $response = $client->dispatch($request);
        $this->response = $response;

        //Trigger Post Send to Modify/Validate Response object
        $result = $this->getEventManager()->trigger('post.send', $response);
        if ($result->stopped()) {
            $response = $result->last();
        }

        $this->request = $request;

        return $response;
    }

    /**
     * Get Http Client
     *
     * @param  string $path
     * @return Http\Client
     */
    public function getHttpClient($path)
    {
        $this->httpClient = new Http\Client();
        $this->httpClient->setAdapter($this->getHttpAdapter());
        $this->httpClient->setUri($this->options['base_url'] . $path);

        return $this->httpClient;
    }

    /**
     * Get Http Adapter
     * @return Http\Client\Adapter\Curl
     */
    public function getHttpAdapter()
    {
        if (null === $this->httpAdapter) {
            $this->httpAdapter = new Http\Client\Adapter\Curl();
            $this->httpAdapter->setOptions(array(
                'curloptions' => array(
                    CURLOPT_SSL_VERIFYPEER => false,
                ),
            ));
        }

        return $this->httpAdapter;
    }

    public function setHttpAdapter($adapter)
    {
        $this->httpAdapter = $adapter;
    }

    /**
     * Set Event Manager
     *
     * @param  EventManagerInterface $events
     * @return self
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_called_class(),
        ));
        $this->events = $events;

        return $this;
    }

    /**
     * Get Event Manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->events;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getRequest()
    {
        return $this->request;
    }
}
