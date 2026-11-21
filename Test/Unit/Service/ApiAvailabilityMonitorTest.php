<?php

namespace PostcodeEu\AddressValidation\Test\Unit\Service;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use PostcodeEu\AddressValidation\Service\ApiAvailabilityMonitor;
use Psr\Log\LoggerInterface;

/**
 * Circuit-breaker state, timing and configuration handling for ApiAvailabilityMonitor.
 */
class ApiAvailabilityMonitorTest extends TestCase
{
    private const CACHE_KEY = 'postcode_eu_api_status';

    /** @var array<int, array> */
    private array $savedStates = [];

    #[Test]
    public function single_failure_below_threshold_stays_available(): void
    {
        $monitor = $this->createMonitor(['api_max_failures' => '5']);

        $monitor->recordFailure();

        $this->assertTrue($monitor->isAvailable());
    }

    #[Test]
    public function breaker_trips_at_configured_failure_threshold(): void
    {
        $monitor = $this->createMonitor(['api_max_failures' => '3']);

        $monitor->recordFailure();
        $monitor->recordFailure();
        $this->assertTrue($monitor->isAvailable());

        $monitor->recordFailure();

        $this->assertFalse($monitor->isAvailable());
    }

    #[Test]
    public function failures_outside_window_are_pruned_and_do_not_trip(): void
    {
        $oldFailure = time() - 100;
        $cache = $this->createCapturingCache(json_encode([
            'failure_times' => [$oldFailure, $oldFailure],
            'unavailable_since' => null,
            'trip_count' => 0,
            'half_open' => false,
        ]));
        $monitor = $this->createMonitor(
            ['api_max_failures' => '3', 'api_failure_window_seconds' => '1'],
            $cache
        );

        $monitor->recordFailure();

        $this->assertTrue($monitor->isAvailable());
        $this->assertCount(1, $this->savedStates[0]['failure_times']);
    }

    #[Test]
    public function outage_half_opens_once_cooldown_elapsed(): void
    {
        $cache = $this->createCapturingCache(json_encode([
            'failure_times' => [time() - 2],
            'unavailable_since' => time() - 10,
            'trip_count' => 3,
            'half_open' => false,
        ]));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('notice')
            ->with($this->stringContains('half-opened'));
        $logger->expects($this->once())->method('warning');
        $monitor = $this->createMonitor(['api_cooldown_seconds' => '1'], $cache, $logger);

        $this->assertTrue($monitor->isAvailable());

        $monitor->recordFailure();

        $saved = $this->savedStates[0];
        $this->assertSame(4, $saved['trip_count']);
        $this->assertFalse($saved['half_open']);
        $this->assertSame([], $saved['failure_times']);
        $this->assertNotNull($saved['unavailable_since']);
    }

    #[Test]
    public function outage_stays_tripped_before_cooldown_elapsed(): void
    {
        $cache = $this->createCacheWithState([
            'failure_times' => [time() - 10],
            'unavailable_since' => time() - 10,
            'trip_count' => 1,
            'half_open' => false,
        ]);
        $monitor = $this->createMonitor(['api_cooldown_seconds' => '30'], $cache);

        $this->assertFalse($monitor->isAvailable());
    }

    #[Test]
    public function failure_during_outage_does_not_retrip_or_extend_cooldown(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode([
            'failure_times' => [],
            'unavailable_since' => time() - 1,
            'trip_count' => 2,
            'half_open' => false,
        ]));
        $cache->expects($this->never())->method('save');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->never())->method('notice');
        $monitor = $this->createMonitor(['api_cooldown_seconds' => '300'], $cache, $logger);

        $monitor->recordFailure();

        $this->assertFalse($monitor->isAvailable());
    }

    #[Test]
    public function cooldown_boundary_half_opens_when_elapsed_equals_cooldown(): void
    {
        $cache = $this->createCacheWithState([
            'failure_times' => [],
            'unavailable_since' => time() - 1,
            'trip_count' => 1,
            'half_open' => false,
        ]);
        $monitor = $this->createMonitor(['api_cooldown_seconds' => '1'], $cache);

        $this->assertTrue($monitor->isAvailable());
    }

    #[Test]
    public function success_while_half_open_closes_circuit_and_resets_trip_count(): void
    {
        $cache = $this->createCapturingCache(json_encode([
            'failure_times' => [],
            'unavailable_since' => null,
            'trip_count' => 3,
            'half_open' => true,
        ]));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('notice');
        $monitor = $this->createMonitor([], $cache, $logger);

        $monitor->recordSuccess();

        $this->assertSame(
            [
                'failure_times' => [],
                'unavailable_since' => null,
                'trip_count' => 0,
                'half_open' => false,
            ],
            $this->savedStates[0]
        );
    }

    #[Test]
    #[DataProvider('failedProbeProvider')]
    public function failed_half_open_probe_retrips_and_compounds_cooldown(
        int $tripCount,
        int $expectedCooldown
    ): void {
        $cache = $this->createCapturingCache(json_encode([
            'failure_times' => [],
            'unavailable_since' => null,
            'trip_count' => $tripCount,
            'half_open' => true,
        ]));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->stringContains('cooldown ' . $expectedCooldown . 's'));
        $monitor = $this->createMonitor(['api_cooldown_seconds' => '30'], $cache, $logger);

        $monitor->recordFailure();

        $saved = $this->savedStates[0];
        $this->assertSame($tripCount + 1, $saved['trip_count']);
        $this->assertFalse($saved['half_open']);
        $this->assertSame([], $saved['failure_times']);
        $this->assertNotNull($saved['unavailable_since']);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function failedProbeProvider(): array
    {
        return [
            'trip count 1' => [1, 60],
            'trip count 2' => [2, 120],
            'trip count capped' => [6, 900],
        ];
    }

    #[Test]
    #[DataProvider('maxFailuresConfigProvider')]
    public function max_failures_config_controls_trip_threshold(?string $configValue, int $threshold): void
    {
        $monitor = $this->createMonitor(['api_max_failures' => $configValue]);

        for ($i = 1; $i < $threshold; $i++) {
            $monitor->recordFailure();
        }
        $this->assertTrue($monitor->isAvailable());

        $monitor->recordFailure();

        $this->assertFalse($monitor->isAvailable());
    }

    /**
     * @return array<string, array{string|null, int}>
     */
    public static function maxFailuresConfigProvider(): array
    {
        return [
            'valid' => ['3', 3],
            'valid single' => ['1', 1],
            'zero uses default' => ['0', 5],
            'negative uses default' => ['-3', 5],
            'non-numeric uses default' => ['abc', 5],
            'float uses default' => ['3.5', 5],
            'missing uses default' => [null, 5],
        ];
    }

    #[Test]
    public function throwing_config_read_falls_back_to_default_max_failures(): void
    {
        $monitor = $this->createMonitor(['api_max_failures' => new \RuntimeException('config down')]);

        for ($i = 0; $i < 4; $i++) {
            $monitor->recordFailure();
        }
        $this->assertTrue($monitor->isAvailable());

        $monitor->recordFailure();

        $this->assertFalse($monitor->isAvailable());
    }

    #[Test]
    #[DataProvider('cooldownConfigProvider')]
    public function cooldown_config_controls_half_open_timing(?string $configValue, bool $expectedAvailable): void
    {
        $cache = $this->createCacheWithState([
            'failure_times' => [time() - 2],
            'unavailable_since' => time() - 2,
            'trip_count' => 1,
            'half_open' => false,
        ]);
        $monitor = $this->createMonitor(['api_cooldown_seconds' => $configValue], $cache);

        $this->assertSame($expectedAvailable, $monitor->isAvailable());
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function cooldownConfigProvider(): array
    {
        return [
            'valid short half-opens' => ['1', true],
            'valid long stays tripped' => ['60', false],
            'zero uses default' => ['0', false],
            'negative uses default' => ['-5', false],
            'non-numeric uses default' => ['abc', false],
            'missing uses default' => [null, false],
        ];
    }

    #[Test]
    #[DataProvider('throwingCooldownProvider')]
    public function throwing_cooldown_config_uses_default(int $elapsed, bool $expectedAvailable): void
    {
        $cache = $this->createCacheWithState([
            'failure_times' => [],
            'unavailable_since' => time() - $elapsed,
            'trip_count' => 1,
            'half_open' => false,
        ]);
        $monitor = $this->createMonitor(
            ['api_cooldown_seconds' => new \RuntimeException('config down')],
            $cache
        );

        $this->assertSame($expectedAvailable, $monitor->isAvailable());
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function throwingCooldownProvider(): array
    {
        return [
            'below default stays tripped' => [10, false],
            'above default half-opens' => [100, true],
        ];
    }

    #[Test]
    #[DataProvider('failureWindowConfigProvider')]
    public function failure_window_config_controls_pruning(?string $configValue, bool $expectedAvailable): void
    {
        $oldFailure = time() - 100;
        $cache = $this->createCacheWithState([
            'failure_times' => array_fill(0, 5, $oldFailure),
            'unavailable_since' => null,
            'trip_count' => 0,
            'half_open' => false,
        ]);
        $monitor = $this->createMonitor([
            'api_max_failures' => '5',
            'api_failure_window_seconds' => $configValue,
        ], $cache);

        $monitor->recordFailure();

        $this->assertSame($expectedAvailable, $monitor->isAvailable());
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function failureWindowConfigProvider(): array
    {
        return [
            'valid short prunes' => ['1', true],
            'valid long keeps' => ['1000', false],
            'zero uses default' => ['0', false],
            'negative uses default' => ['-10', false],
            'non-numeric uses default' => ['abc', false],
            'missing uses default' => [null, false],
        ];
    }

    #[Test]
    public function throwing_failure_window_config_falls_back_to_default(): void
    {
        $oldFailure = time() - 100;
        $cache = $this->createCacheWithState([
            'failure_times' => [$oldFailure, $oldFailure],
            'unavailable_since' => null,
            'trip_count' => 0,
            'half_open' => false,
        ]);
        $monitor = $this->createMonitor([
            'api_max_failures' => '3',
            'api_failure_window_seconds' => new \RuntimeException('config down'),
        ], $cache);

        $monitor->recordFailure();

        $this->assertFalse($monitor->isAvailable());
    }

    #[Test]
    public function cache_miss_uses_default_state(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $monitor = $this->createMonitor([], $cache);

        $this->assertTrue($monitor->isAvailable());
    }

    #[Test]
    public function invalid_json_in_cache_uses_default_state(): void
    {
        $cache = $this->createCacheWithPayload('not-json');
        $monitor = $this->createMonitor([], $cache);

        $this->assertTrue($monitor->isAvailable());
    }

    #[Test]
    #[DataProvider('nonStringCachePayloadProvider')]
    public function non_string_cache_payload_uses_default_state(mixed $payload): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn($payload);
        $monitor = $this->createMonitor([], $cache);

        $this->assertTrue($monitor->isAvailable());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonStringCachePayloadProvider(): array
    {
        return [
            'integer' => [1],
            'zero' => [0],
            'true' => [true],
            'empty array' => [[]],
        ];
    }

    #[Test]
    #[DataProvider('structurallyInvalidStateProvider')]
    public function structurally_invalid_cache_state_uses_default_state(array $invalidState): void
    {
        $monitor = $this->createMonitor([], $this->createCacheWithState($invalidState));

        $this->assertTrue($monitor->isAvailable());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function structurallyInvalidStateProvider(): array
    {
        return [
            'failure_times not an array' => [[
                'failure_times' => 'nope',
                'unavailable_since' => time(),
                'trip_count' => 0,
                'half_open' => false,
            ]],
            'failure_times contains non-int' => [[
                'failure_times' => ['nope'],
                'unavailable_since' => time(),
                'trip_count' => 0,
                'half_open' => false,
            ]],
            'unavailable_since not an int' => [[
                'failure_times' => [],
                'unavailable_since' => 'soon',
                'trip_count' => 0,
                'half_open' => false,
            ]],
            'trip_count not an int' => [[
                'failure_times' => [],
                'unavailable_since' => time(),
                'trip_count' => '3',
                'half_open' => false,
            ]],
            'trip_count negative' => [[
                'failure_times' => [],
                'unavailable_since' => time(),
                'trip_count' => -1,
                'half_open' => false,
            ]],
            'half_open not a bool' => [[
                'failure_times' => [],
                'unavailable_since' => time(),
                'trip_count' => 0,
                'half_open' => 'yes',
            ]],
        ];
    }

    #[Test]
    public function partial_cache_payload_is_merged_with_defaults(): void
    {
        $cache = $this->createCacheWithPayload('{"failure_times":[]}');
        $monitor = $this->createMonitor([], $cache);

        $this->assertTrue($monitor->isAvailable());
    }

    #[Test]
    public function valid_cache_state_is_restored(): void
    {
        $cache = $this->createCacheWithState([
            'failure_times' => [],
            'unavailable_since' => time(),
            'trip_count' => 1,
            'half_open' => false,
        ]);
        $monitor = $this->createMonitor([], $cache);

        $this->assertFalse($monitor->isAvailable());
    }

    #[Test]
    public function cache_load_that_throws_uses_default_state(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willThrowException(new \RuntimeException('cache down'));
        $monitor = $this->createMonitor([], $cache);

        $this->assertTrue($monitor->isAvailable());
    }

    #[Test]
    public function record_success_on_default_state_is_no_op(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->with(self::CACHE_KEY)->willReturn(false);
        $cache->expects($this->never())->method('save');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('notice');
        $logger->expects($this->never())->method('warning');
        $monitor = $this->createMonitor([], $cache, $logger);

        $monitor->recordSuccess();
    }

    #[Test]
    public function record_success_is_no_op_for_reordered_default_state(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->with(self::CACHE_KEY)->willReturn(json_encode([
            'half_open' => false,
            'trip_count' => 0,
            'unavailable_since' => null,
            'failure_times' => [],
        ]));
        $cache->expects($this->never())->method('save');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('notice');
        $logger->expects($this->never())->method('warning');
        $monitor = $this->createMonitor([], $cache, $logger);

        $monitor->recordSuccess();
    }

    #[Test]
    public function invalidate_state_forces_reload_from_cache(): void
    {
        $payload = json_encode([
            'failure_times' => [],
            'unavailable_since' => null,
            'trip_count' => 0,
            'half_open' => false,
        ]);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(function () use (&$payload) {
            return $payload;
        });
        $cache->method('save')->willReturn(true);
        $monitor = $this->createMonitor([], $cache);

        $this->assertTrue($monitor->isAvailable());

        $payload = json_encode([
            'failure_times' => [],
            'unavailable_since' => time(),
            'trip_count' => 1,
            'half_open' => false,
        ]);
        $this->assertTrue($monitor->isAvailable());

        $monitor->invalidateState();

        $this->assertFalse($monitor->isAvailable());
    }

    /**
     * @param array<string, mixed> $config
     * @param CacheInterface|null $cache
     * @param LoggerInterface|null $logger
     * @return ApiAvailabilityMonitor
     */
    private function createMonitor(
        array $config = [],
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null
    ): ApiAvailabilityMonitor {
        $storeConfigHelper = $this->createStub(StoreConfigHelper::class);
        $storeConfigHelper->method('getValue')->willReturnCallback(
            function (string $key) use ($config) {
                if (!array_key_exists($key, $config)) {
                    return null;
                }

                $value = $config[$key];

                if ($value instanceof \Throwable) {
                    throw $value;
                }

                return $value;
            }
        );

        if ($cache === null) {
            $cache = $this->createStub(CacheInterface::class);
            $cache->method('load')->willReturn(false);
            $cache->method('save')->willReturn(true);
        }

        return new ApiAvailabilityMonitor(
            $cache,
            $storeConfigHelper,
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }

    /**
     * @param string $payload
     * @return CacheInterface
     */
    private function createCacheWithPayload(string $payload): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn($payload);
        $cache->method('save')->willReturn(true);

        return $cache;
    }

    /**
     * @param array<string, mixed> $state
     * @return CacheInterface
     */
    private function createCacheWithState(array $state): CacheInterface
    {
        return $this->createCacheWithPayload((string) json_encode($state));
    }

    /**
     * @param string $payload
     * @return CacheInterface
     */
    private function createCapturingCache(string $payload): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn($payload);
        $cache->method('save')->willReturnCallback(
            function ($data) {
                $this->savedStates[] = json_decode($data, true);
                return true;
            }
        );

        return $cache;
    }
}
