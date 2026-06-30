<?php

namespace PostcodeEu\AddressValidation\Service;

use Magento\Framework\App\CacheInterface;
use PostcodeEu\AddressValidation\Helper\StoreConfigHelper;
use Psr\Log\LoggerInterface;

class ApiAvailabilityMonitor
{
    private const CACHE_KEY = 'postcode_eu_api_status';
    private const CACHE_TAG = 'postcode_eu_api_status';

    // Default values:
    // When the cooldown (30s) ends, the breaker half-opens to allow one probe.
    // A failed probe re-trips and doubles the cooldown, capped at 15min. A success closes the circuit.
    // The failure window (5min) limits how long scattered failures count toward a trip;
    // clearing it on cooldown expiry prevents stale failures from carrying over.
    private const DEFAULT_MAX_FAILURES = 5;
    private const DEFAULT_FAILURE_WINDOW_SECONDS = 300;
    private const DEFAULT_COOLDOWN_SECONDS = 30;
    private const MAX_COOLDOWN_SECONDS = 900;

    /** @var CacheInterface */
    private CacheInterface $_cache;

    /** @var StoreConfigHelper */
    private StoreConfigHelper $_storeConfigHelper;

    /** @var LoggerInterface */
    private LoggerInterface $_logger;

    /**
     * Cached breaker state.
     *
     * @var array{ failure_times: int[], unavailable_since: int|null, trip_count: int, half_open: bool }|null
     * @see _getDefaultState
     */
    private ?array $_loadedState = null;

    /**
     * Cached config values per key.
     *
     * @var array<string, int>
     */
    private array $_configCache = [];

    /**
     * @param CacheInterface $cache
     * @param StoreConfigHelper $storeConfigHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        CacheInterface $cache,
        StoreConfigHelper $storeConfigHelper,
        LoggerInterface $logger
    ) {
        $this->_cache = $cache;
        $this->_storeConfigHelper = $storeConfigHelper;
        $this->_logger = $logger;
    }

    /**
     * Check whether the API is currently available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $state = $this->_loadState();

        return $state['unavailable_since'] === null;
    }

    /**
     * Invalidate the cached state.
     *
     * Re-evaluates state from cache on next access.
     * Useful in long-lived processes (cron, queue consumers) where time advances between calls.
     */
    public function invalidateState(): void
    {
        $this->_loadedState = null;
    }

    /**
     * Record a successful API call.
     */
    public function recordSuccess(): void
    {
        $state = $this->_loadState();

        // A successful request while half-open closes the circuit.
        $state['half_open'] = false;

        if ($state === $this->_getDefaultState()) { // Requires keys in fixed order.
            return;
        }

        $this->_logger->notice('API availability breaker recovered following a successful request.');

        $this->_saveState($this->_getDefaultState());
    }

    /**
     * Record a failed API call.
     */
    public function recordFailure(): void
    {
        $state = $this->_loadState();

        // A half-open failure re-trips immediately, compounding backoff.
        if ($state['half_open']) {
            $state['trip_count']++;
            $state['unavailable_since'] = time();
            $state['failure_times'] = [];
            $state['half_open'] = false;
            $this->_logger->warning(sprintf(
                'API availability breaker re-tripped after a failed half-open probe (trip #%d, cooldown %ds).',
                $state['trip_count'],
                $this->_getEffectiveCooldown($state['trip_count'])
            ));
            $this->_saveState($state);
            return;
        }

        if ($state['unavailable_since'] !== null) {
            return;
        }

        $now = time();
        $state['failure_times'][] = $now; // _loadState already pruned old failures, so just append.

        if (count($state['failure_times']) >= $this->_getMaxFailures()) {
            $state['trip_count']++;
            $state['unavailable_since'] = $now;
            $this->_logger->warning(sprintf(
                'API availability breaker tripped after %d failures within the failure window (trip #%d, cooldown %ds).',
                count($state['failure_times']),
                $state['trip_count'],
                $this->_getEffectiveCooldown($state['trip_count'])
            ));
        }

        $this->_saveState($state);
    }

    /**
     * Load and evaluate the breaker state.
     *
     * Will always re-evaluate state so time-based corrections still work in
     * long-lived processes (cron, queue consumers).
     *
     * @return array
     */
    private function _loadState(): array
    {
        $state = $this->_loadedState ?? ($this->_loadFromCache() ?? $this->_getDefaultState());

        // Sync state with the current time: prune old failures and handle half-open transition.

        $state['failure_times'] = $this->_pruneFailureTimes($state['failure_times']);

        // Half-open: clear outage marker after cooldown to allow a probe.
        // Trip count is kept so a failed probe compounds backoff; only recordSuccess resets it.
        if ($state['unavailable_since'] !== null
            && !$state['half_open']
            && (time() - $state['unavailable_since']) >= $this->_getEffectiveCooldown($state['trip_count'])
        ) {
            $state['unavailable_since'] = null;
            $state['failure_times'] = [];
            $state['half_open'] = true;
            $this->_logger->notice('API availability breaker half-opened after cooldown elapsed.');
        }

        $this->_loadedState = $state;
        return $state;
    }

    /**
     * Prune failure times
     *
     * Drop failures outside the sliding window so scattered ones don't cause a trip.
     *
     * @param int[] $failureTimes
     * @return int[]
     */
    private function _pruneFailureTimes(array $failureTimes): array
    {
        $cutoff = time() - $this->_getFailureWindowSeconds();

        return array_values(array_filter($failureTimes, fn ($t) => $t >= $cutoff));
    }

    /**
     * Load the breaker state from cache.
     *
     * @return array|null
     */
    private function _loadFromCache(): ?array
    {
        try {
            $data = $this->_cache->load(self::CACHE_KEY);

            if ($data !== false && is_string($data)) {
                $decoded = json_decode($data, true);

                if (is_array($decoded) && $this->_isValidState($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            $this->_logger->warning(sprintf(
                'Failed to load API availability state from cache: %s',
                $e->getMessage()
            ));
        }

        return null;
    }

    /**
     * Validate the decoded cache state structure.
     *
     * @param array $decoded
     * @return bool
     */
    private function _isValidState(array $decoded): bool
    {
        if (!is_array($decoded['failure_times'] ?? null)) {
            return false;
        }

        if (array_filter($decoded['failure_times'], 'is_int') !== $decoded['failure_times']) {
            return false;
        }

        $unavailableSince = $decoded['unavailable_since'] ?? null;

        if ($unavailableSince !== null && !is_int($unavailableSince)) {
            return false;
        }

        $tripCount = $decoded['trip_count'] ?? 0;

        if (!is_int($tripCount) || $tripCount < 0) {
            return false;
        }

        return is_bool($decoded['half_open'] ?? false);
    }

    /**
     * Save the breaker state to cache.
     *
     * @param array $state
     */
    private function _saveState(array $state): void
    {
        // NB. no cross-process lock; concurrent writes may clash.
        // Acceptable because the state self-heals on the next cycle.
        $this->_loadedState = $state;

        // - Healthy state: no TTL (keep cached for fast reads).
        // - Unavailable state: TTL set to MAX_COOLDOWN_SECONDS (safety net, in case a successful request never resets it).
        // TTL always uses MAX_COOLDOWN_SECONDS, not the effective cooldown,
        // so trip count survives across cooldown boundaries for compounding.
        $ttl = $state['unavailable_since'] === null ? 0 : self::MAX_COOLDOWN_SECONDS;

        try {
            $saved = $this->_cache->save(
                json_encode($state, JSON_THROW_ON_ERROR),
                self::CACHE_KEY,
                [self::CACHE_TAG],
                $ttl
            );

            if (!$saved) {
                $this->_logger->warning(
                    'Failed to save API availability state to cache: cache backend refused the write.',
                );
            }
        } catch (\Throwable $e) {
            $this->_logger->warning(sprintf(
                'Failed to save API availability state to cache: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Get the maximum number of failures before tripping the breaker.
     *
     * @return int
     */
    private function _getMaxFailures(): int
    {
        return $this->_getConfigPositiveInt('api_max_failures', self::DEFAULT_MAX_FAILURES);
    }

    /**
     * Get the base cooldown duration in seconds.
     *
     * @return int
     */
    private function _getCooldownSeconds(): int
    {
        return $this->_getConfigPositiveInt('api_cooldown_seconds', self::DEFAULT_COOLDOWN_SECONDS);
    }

    /**
     * Effective cooldown for the current trip count.
     *
     * Base multiplied by the backoff factor for each following trip beyond the first,
     * capped at {@see MAX_COOLDOWN_SECONDS}.
     *
     * @param int $tripCount
     * @return int
     */
    private function _getEffectiveCooldown(int $tripCount): int
    {
        $base = $this->_getCooldownSeconds();
        $exponent = max(0, $tripCount - 1);

        return (int) min($base * (2 ** $exponent), self::MAX_COOLDOWN_SECONDS);
    }

    /**
     * Get the failure window duration in seconds.
     *
     * @return int
     */
    private function _getFailureWindowSeconds(): int
    {
        return $this->_getConfigPositiveInt('api_failure_window_seconds', self::DEFAULT_FAILURE_WINDOW_SECONDS);
    }

    /**
     * Resolve a numeric store-config value with a sane default.
     *
     * Cached per key in {@see $_configCache}.
     *
     * @param string $key
     * @param int $default
     * @return int
     */
    private function _getConfigPositiveInt(string $key, int $default): int
    {
        if (!isset($this->_configCache[$key])) {
            try {
                $value = $this->_storeConfigHelper->getValue($key, 0);
            } catch (\Throwable $e) {
                $this->_logger->warning(sprintf(
                    'Failed to read config "%s", using default: %s',
                    $key,
                    $e->getMessage()
                ));
                $value = null;
            }

            $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $this->_configCache[$key] = $int ?: $default;
        }

        return $this->_configCache[$key];
    }

    /**
     * Get the default breaker state.
     *
     * @return array
     */
    private function _getDefaultState(): array
    {
        return [
            'failure_times' => [],
            'unavailable_since' => null,
            'trip_count' => 0,
            'half_open' => false,
        ];
    }
}
