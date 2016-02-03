<?php
/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Model\Product\SpecificPrice\Condition;

use CoreShop\Model;
use CoreShop\Model\Zone as ZoneModel;
use CoreShop\Tool;

class Zone extends AbstractCondition
{
    /**
     * @var int
     */
    public $zone;

    /**
     * @var string
     */
    public $type = "zone";

    /**
     * @return int
     */
    public function getZone()
    {
        if (!$this->zone instanceof ZoneModel) {
            $this->zone = ZoneModel::getById($this->zone);
        }

        return $this->zone;
    }

    /**
     * @param int $zone
     */
    public function setZone($zone)
    {
        $this->zone = $zone;
    }

    /**
     * Check if Product is Valid for Condition
     *
     * @param Model\Product $product
     * @param Model\Product\SpecificPrice $specificPrice
     * @return boolean
     */
    public function checkCondition(Model\Product $product, Model\Product\SpecificPrice $specificPrice)
    {
        if ($this->getZone()->getId() !== Tool::getCountry()->getZoneId()) {
            return false;
        }

        return true;
    }
}