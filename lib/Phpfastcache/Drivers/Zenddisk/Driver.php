<?php
/**
 *
 * This file is part of phpFastCache.
 *
 * @license MIT License (MIT)
 *
 * For full copyright and license information, please see the docs/CREDITS.txt file.
 *
 * @author Lucas Brucksch <support@hammermaps.de>
 *
 */
declare(strict_types=1);

namespace Phpfastcache\Drivers\Zenddisk;

use Phpfastcache\Core\Pool\{
    DriverBaseTrait, ExtendedCacheItemPoolInterface
};
use Phpfastcache\Cluster\AggregatablePoolInterface;
use Phpfastcache\Entities\DriverStatistic;
use Phpfastcache\Exceptions\{
    PhpfastcacheInvalidArgumentException
};
use Psr\Cache\CacheItemInterface;

/**
 * Class Driver (zend disk cache)
 * Requires Zend Data Cache Functions from ZendServer
 * @package phpFastCache\Drivers
 * @property Config $config Config object
 * @method Config getConfig() Return the config object
 */
class Driver implements ExtendedCacheItemPoolInterface, AggregatablePoolInterface
{
    use DriverBaseTrait;

    /**
     * @return bool
     */
    public function driverCheck(): bool
    {
        return \extension_loaded('Zend Data Cache') && \function_exists('zend_disk_cache_store');
    }

    /**
     * @return bool
     */
    protected function driverConnect(): bool
    {
        return true;
    }

    /**
     * @param \Psr\Cache\CacheItemInterface $item
     * @return null|array
     */
    protected function driverRead(CacheItemInterface $item)
    {
        $data = zend_disk_cache_fetch($item->getKey());
        if ($data === false) {
            return null;
        }

        return $data;
    }

    /**
     * @param \Psr\Cache\CacheItemInterface $item
     * @return mixed
     * @throws PhpfastcacheInvalidArgumentException
     */
    protected function driverWrite(CacheItemInterface $item): bool
    {
        /**
         * Check for Cross-Driver type confusion
         */
        if ($item instanceof Item) {
            $ttl = $item->getExpirationDate()->getTimestamp() - \time();

            return zend_disk_cache_store($item->getKey(), $this->driverPreWrap($item), ($ttl > 0 ? $ttl : 0));
        }

        throw new PhpfastcacheInvalidArgumentException('Cross-Driver type confusion detected');
    }

    /**
     * @param \Psr\Cache\CacheItemInterface $item
     * @return bool
     * @throws PhpfastcacheInvalidArgumentException
     */
    protected function driverDelete(CacheItemInterface $item): bool
    {
        /**
         * Check for Cross-Driver type confusion
         */
        if ($item instanceof Item) {
            return (bool)zend_disk_cache_delete($item->getKey());
        }

        throw new PhpfastcacheInvalidArgumentException('Cross-Driver type confusion detected');
    }

    /**
     * @return bool
     */
    protected function driverClear(): bool
    {
        return @zend_disk_cache_clear();
    }

    /********************
     *
     * PSR-6 Extended Methods
     *
     *******************/

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return <<<HELP
<p>
This driver rely on Zend Server 8.5+, see: https://www.zend.com/en/products/zend_server
</p>
HELP;
    }

    /**
     * @return DriverStatistic
     */
    public function getStats(): DriverStatistic
    {
        $stat = new DriverStatistic();
        $stat->setInfo('[ZendDisk] A void info string')
            ->setSize(0)
            ->setData(\implode(', ', \array_keys($this->itemInstances)))
            ->setRawData(false);

        return $stat;
    }
}
