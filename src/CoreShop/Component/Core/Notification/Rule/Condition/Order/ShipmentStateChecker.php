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

namespace CoreShop\Component\Core\Notification\Rule\Condition\Order;

use CoreShop\Component\Notification\Rule\Condition\AbstractConditionChecker;
use CoreShop\Component\Order\Model\OrderInterface;
use CoreShop\Component\Order\Processable\ProcessableInterface;

class ShipmentStateChecker extends AbstractConditionChecker
{
    const SHIPMENT_TYPE_PARTIAL = 1;

    const SHIPMENT_TYPE_FULL = 2;

    const SHIPMENT_TYPE_ALL = 3;

    /**
     * @var ProcessableInterface
     */
    private $processableHelper;

    /**
     * @param ProcessableInterface $processableHelper
     */
    public function __construct(ProcessableInterface $processableHelper)
    {
        $this->processableHelper = $processableHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function isNotificationRuleValid($subject, $params, array $configuration)
    {
        $shipmentState = $configuration['shipmentState'];

        if ($subject instanceof OrderInterface) {
            if ($shipmentState === self::SHIPMENT_TYPE_ALL) {
                return true;
            } elseif ($shipmentState === self::SHIPMENT_TYPE_FULL) {
                if (count($this->processableHelper->getProcessableItems($subject)) === 0) {
                    return true;
                }
            } elseif ($shipmentState === self::SHIPMENT_TYPE_PARTIAL) {
                if (count($this->processableHelper->getProcessableItems($subject)) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
