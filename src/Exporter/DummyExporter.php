<?php

namespace Drupal\opentelemetry\Exporter;

use OpenTelemetry\SDK\Trace\Behavior\SpanExporterTrait;
use OpenTelemetry\SDK\Trace\Behavior\UsesSpanConverterTrait;
use OpenTelemetry\SDK\Trace\SpanConverterInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\FriendlySpanConverter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

/**
 * A custom exporter that does nothing.
 */
class DummyExporter implements SpanExporterInterface {

  use SpanExporterTrait;
  use UsesSpanConverterTrait;

  /**
   * The constructor.
   *
   * @param \OpenTelemetry\SDK\Trace\SpanConverterInterface|null $converter
   *   A span converter or null.
   */
  public function __construct(?SpanConverterInterface $converter = NULL) {
    $this->setSpanConverter($converter ?? new FriendlySpanConverter());
  }

  /**
   * {@inheritDoc}
   */
  public function doExport(iterable $spans): bool {
    return SpanExporterInterface::STATUS_SUCCESS;
  }

  /**
   * {@inheritDoc}
   */
  public static function fromConnectionString(string $endpointUrl = NULL, string $name = NULL, $args = NULL) {
    return new self();
  }

}
