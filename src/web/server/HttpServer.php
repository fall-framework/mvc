<?php

namespace fall\mvc\web\server;

use fall\context\annotation\Autowired;
use fall\context\stereotype\Controller;
use fall\core\lang\stream\impl\StringStream;
use fall\core\net\Socket;
use fall\core\utils\AnnotationUtils;
use fall\mvc\annotation\RequestMapping;
use fall\mvc\web\message\http\HttpRequest;
use fall\mvc\web\message\http\HttpResponse;
use fall\mvc\web\message\http\Uri;
use fall\mvc\web\server\AbstractServer;

/**
 * This class handle http messages
 * @author Angelis <angelis@users.noreply.github.com>
 */
class HttpServer extends AbstractServer
{

  /**
   * @Autowired()
   */
  private $applicationContext;

  private $mappings = [];

  public function __construct(int $port = 80)
  {
    parent::__construct($port);
    $this->addOnSocketMessageCallback([$this, 'onClientMessage']);

    foreach (AnnotationUtils::getAllExtendedReflectionClassesHavingAnnotation(Controller::class) as $extendedReflectionClass) {
      $classBaseMapping = '';
      if ($extendedReflectionClass->isAnnotationPresent(RequestMapping::class)) {
        $classBaseMapping = $extendedReflectionClass->getAnnotation(RequestMapping::class)->value();
      }

      foreach ($extendedReflectionClass->getMethodsAnnotatedWith(RequestMapping::class) as $extendedReflectionMethod) {
        $requestMappingAnnotation = $extendedReflectionMethod->getAnnotation(RequestMapping::class);
        $methodMapping = $requestMappingAnnotation->value();
        $httpMethod = $requestMappingAnnotation->method();
        if ($httpMethod == null) {
          $httpMethod = 'GET';
        }

        $mappingUrl = $httpMethod . ' ' . $classBaseMapping . $methodMapping;
        if (preg_match_all('#\{([a-zA-Z]+)\}#', $mappingUrl, $matches)) {
          foreach ($matches[1] as $match) {
            $mappingUrl = str_replace('{' . $match . '}', '(?<' . $match . '>.*)', $mappingUrl);
          }
        }

        $mapping = new \stdClass();
        $mapping->regexUri = $mappingUrl;
        $mapping->uri = $classBaseMapping . $methodMapping;
        $mapping->controllerClass = $extendedReflectionClass;
        $mapping->controllerMethod = $extendedReflectionMethod;
        $mapping->httpMethod = $httpMethod;
        $this->mappings[] = $mapping;
      }

      foreach ($this->mappings as $mapping) {
        echo "Mapped " . $mapping->httpMethod . ' ' . $mapping->uri . "\r\n";
      }
    }
  }

  public function onClientMessage(Socket $socket, $stream)
  {
    list($headerString, $contentString) = explode("\r\n\r\n", $stream->read(2048));

    $httpRequest = $this->buildHttpRequest(explode("\r\n", $headerString), $contentString);
    $httpResponse = $this->buildHttpResponse($httpRequest);
    $socket->write($httpResponse->__toString());
    $this->removeSocket($socket);
  }

  private function buildHttpRequest($headerArray, $contentString): HttpRequest
  {
    list($method, $path, $protocolVersion) = explode(' ', array_shift($headerArray));

    $uri = (new Uri())
      ->withScheme('http')
      ->withPath($path);

    return (new HttpRequest())
      ->withMethod($method)
      ->withUri($uri)
      ->withProtocolVersion(str_replace('HTTP/', '', $protocolVersion))
      ->withBody(new StringStream($contentString));
  }

  private function buildHttpResponse(HttpRequest $httpRequest): HttpResponse
  {
    $httpResponse = (new HttpResponse())
      ->withProtocolVersion($httpRequest->getProtocolVersion());

    $contentType = 'text/html';
    $requestMethod = $httpRequest->getMethod();
    $requestUri = $httpRequest->getUri()->getPath();

    foreach ($this->mappings as $mapping) {
      if (preg_match('#' . $mapping->regexUri . '#', $requestMethod . ' ' . $requestUri, $matches)) {
        foreach ($matches as $key => $value) {
          if (is_int($key)) {
            unset($matches[$key]);
          }
        }

        if ($this->applicationContext === null) {
          $controller = $mapping->controllerClass->newInstance();
        } else {
          $controller = $this->applicationContext->getBeanByType($mapping->controllerClass->getName());
        }

        if (count($matches) > 0) {
          $return = $mapping->controllerMethod->invokeArgs($controller, []);
        } else {
          $return = $mapping->controllerMethod->invoke($controller);
        }

        $body = null;
        if (is_string($return)) {
          $body = new StringStream($return);
        } else if (\is_object($return) || \is_array($return)) {
          $contentType = 'application/json';
          $body = new StringStream(\json_encode($return));
        } else {
          $body = new StringStream('');
        }

        return $httpResponse
          ->withHeader('Content-Type', $contentType)
          ->withBody($body)
          ->withStatus(200, 'Ok');
      }
    }

    return $httpResponse
      ->withBody(new StringStream('<!DOCTYPE html><html><head><meta charset="UTF-8"/></head><body>Page not found</body></html>'))
      ->withHeader('Content-Type', $contentType)
      ->withStatus(404, 'Not Found');
  }
}
