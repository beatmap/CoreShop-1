<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2017 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
*/

namespace CoreShop\Component\Core\Model;

use CoreShop\Component\Store\Model\StoreInterface as BaseStoreInterface;

interface StoreInterface extends BaseStoreInterface
{
    /**
     * @return CurrencyInterface
     */
    public function getBaseCurrency();

    /**
     * @param CurrencyInterface $baseCurrency
     */
    public function setBaseCurrency(CurrencyInterface $baseCurrency);

    /**
     * @return CountryInterface
     */
    public function getBaseCountry();

    /**
     * @param CountryInterface $baseCurrency
     */
    public function setBaseCountry(CountryInterface $baseCurrency);
}
