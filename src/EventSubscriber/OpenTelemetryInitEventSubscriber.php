<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Routing\RouteMatch;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The event handler for opentelemetry.
 */
class OpenTelemetryInitEventSubscriber implements EventSubscriberInterface {

  /**
   * The span for the full request.
   *
   * @var \OpenTelemetry\SDK\Trace\Span
   */
  protected $span;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ConsoleEvents::COMMAND => [['onCommand', 31]],
      KernelEvents::REQUEST => [['onKernelRequest', 31]],
      KernelEvents::FINISH_REQUEST => [['onKernelFinishRequest', 1]],
      KernelEvents::EXCEPTION => ['onKernelException', -65],
    ];
  }

  /**
   * Handle the onCommand event.
   *
   * @param \Symfony\Component\Console\Event\ConsoleCommandEvent $event
   *   The console command event.
   */
  public function onCommand(ConsoleCommandEvent $event) :void {
    /** @var \OpenTelemetry\SDK\Trace\Tracer $tracer */
    $tracer = \Drupal::service('opentelemetry')->createTracer();
    $this->span = $tracer->spanBuilder(sprintf('Handle Request %s', $event->getCommand()
      ->getName()))
      ->startSpan();
    $this->span->activate();
  }

  /**
   * Handle the onKernelFinishRequest event.
   *
   * @param \Symfony\Component\HttpKernel\Event\FinishRequestEvent $event
   *   The console command event.
   */
  public function onKernelFinishRequest(FinishRequestEvent $event) :void {
    // @todo handle sub request.
    if (!empty($this->span)) {
      $logs = Database::getLog('monitor_mysql');
      foreach ($logs as $log) {
        if ($log['time'] > 2) {
          \Drupal::logger('db')
            ->critical(sprintf('Slow query %s', $log['query']));

          $this->span->addEvent(
            sprintf('Slow query %s', $log['query']),
            [
              'caller' => $log['caller'],
              'args' => $log['args'],
            ]
          );
        }
      }
      if (is_array($logs)) {
        $this->span->setAttribute('Database Query Count', count($logs));
      }
      $this->span->end();

    }
  }

  /**
   * Handle the onKernelRequest event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The console command event.
   */
  public function onKernelRequest(RequestEvent $event) :void {
    /** @var \OpenTelemetry\SDK\Trace\Tracer $tracer */
    $tracer = \Drupal::service('opentelemetry')->createTracer();
    $route = RouteMatch::createFromRequest($event->getRequest());
    $this->span = $tracer->spanBuilder(sprintf('Handle Request %s:%s', $event->getRequest()
      ->getMethod(), $route->getRouteObject()->getPath()))
      ->startSpan();
    $this->span->activate();
    $this->span->addEvent(sprintf('Handle Request %s:%s', $event->getRequest()
      ->getMethod(), $route->getRouteObject()->getPath()),
      [
        'routeName' => $route->getRouteName(),
        // 'parameters' => $route->getParameters()->all(),
      ]);

    Database::startLog('monitor_mysql');
  }

  /**
   * Handle the onKernelRequest event.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The console command event.
   */
  public function onKernelException(ExceptionEvent $event) :void {
    if (!empty($this->span)) {
      $this->span->end();
    }
  }

}
