<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Http\RequestStack;
use Drupal\opentelemetry\Exporter\DummyExporter;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Otlp\ProtobufSerializer;
use OpenTelemetry\Contrib\OtlpHttp\Exporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * The service to get a opentelemetry tracer.
 *
 * @package Drupal\opentelemetry
 */
class OpenTelemetryService {

  /**
   * The sampler.
   *
   * @var \OpenTelemetry\SDK\Trace\SamplerInterface
   */
  protected $sampler;

  /**
   * The sampling result.
   *
   * @var \OpenTelemetry\SDK\Trace\SamplingResult
   */
  protected $samplingResult;

  /**
   * The exporter.
   *
   * @var \OpenTelemetry\SDK\Trace\SpanExporterInterface
   */
  protected $exporter;

  /**
   * The endpoint for the tracing software.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * Constructs a new OpenTelemetry service.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Drupal config factory.
   * @param \Drupal\Core\Http\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(ConfigFactory $configFactory, RequestStack $requestStack) {
    $this->endpoint = $configFactory->get('opentelemetry.settings')
      ->get('endpoint') ?? 'http://localhost:9411/api/v2/spans';

    $this->sampler = new AlwaysOnSampler();

    $serviceName = $configFactory->get('opentelemetry.settings')
      ->get('service_name') ?? 'Drupal';
    $samplerUniqueId = md5((string) microtime(TRUE));

    $this->samplingResult = $this->sampler->shouldSample(
      Context::getCurrent(),
      $samplerUniqueId,
      'io.opentelemetry.drupal',
      SpanKind::KIND_INTERNAL,
      Attributes::create([]),
      []
    );

    $endpoint_otlp = getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ? getenv('OTEL_EXPORTER_OTLP_ENDPOINT') : 'http://collector:4318';

    $disable_otel = getenv('OTEL_EXPORTER_DISABLE');
    $enabled = $configFactory->get('opentelemetry.settings')
      ->get('enabled') ?? TRUE;
    if ($enabled === '1') {
      $enabled = TRUE;
    }
    else {
      $enabled = FALSE;
    }
    if ($disable_otel) {
      $enabled = FALSE;
    }

    $type = $configFactory->get('opentelemetry.settings')
      ->get('type') ?? 'otlp';

    switch ($type) {
      case 'otlp':
      default:
      $transport = (new \OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory())->create($endpoint_otlp,'application/json');
      $exporter = new \OpenTelemetry\Contrib\Otlp\SpanExporter($transport);
    }

    if ($enabled === FALSE) {
      $exporter = new DummyExporter();
    }

    $this->exporter = $exporter;
  }

  /**
   * Create a Tracer.
   *
   * @return \OpenTelemetry\API\Trace\TracerInterface
   *   The created Tracer.
   */
  public function createTracer() {
    $traceProvider = new TracerProvider(
      new BatchSpanProcessor($this->exporter, ClockFactory::create()->build()),
      $this->sampler
    );

    $tracer = $traceProvider->getTracer('io.opentelemetry.contrib.php');
    return $tracer;
  }

}
