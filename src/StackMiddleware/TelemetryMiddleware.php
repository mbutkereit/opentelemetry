<?php

namespace Drupal\opentelemetry\StackMiddleware;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * A middleware to observe requests.
 */
class TelemetryMiddleware implements HttpKernelInterface, TerminableInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $app;

  /**
   * A span.
   *
   * @var \OpenTelemetry\API\Trace\SpanInterface
   */
  protected $span;

  /**
   * Constructs a new TelemetryMiddleware.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $app
   *   The wrapper HTTP kernel.
   */
  public function __construct(HttpKernelInterface $app) {
    $this->app = $app;
  }

  /**
   * {@inheritDoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    /** @var \OpenTelemetry\SDK\Trace\Tracer $tracer */
    $tracer = \Drupal::service('opentelemetry')->createTracer();

    if (NULL !== $qs = $request->getQueryString()) {
      $qs = '?' . $qs;
    }

    $path = $request->getPathInfo() . $qs;
    $this->span = $tracer->spanBuilder(sprintf('drupal::handleRequest: %s : %s', $request->getMethod(), $path))
      ->setAttributes([
        'method' => $request->getMethod(),
        'query_string' => $request->getQueryString(),
        'path' => $request->getPathInfo(),
        'full_path' => $request->getUri(),
      ])
      ->startSpan();
    $this->span->activate();
    return $this->app->handle($request, $type, $catch);
  }

  /**
   * {@inheritDoc}
   */
  public function terminate(Request $request, Response $response) {
    if (!empty($this->span)) {
      $this->span->end();
    }
  }

}
