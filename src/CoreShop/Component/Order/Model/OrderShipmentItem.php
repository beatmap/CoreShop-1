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

namespace CoreShop\Component\Order\Model;

use CoreShop\Component\Resource\ImplementedByPimcoreException;
use CoreShop\Component\Resource\Pimcore\Model\AbstractPimcoreModel;

class OrderShipmentItem extends AbstractPimcoreModel implements OrderShipmentItemInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDocument()
    {
        $parent = $this->getParent();

        do {
            if (is_subclass_of($parent, OrderShipmentInterface::class)) {
                return $parent;
            }
            $parent = $parent->getParent();
        } while ($parent != null);

        throw new \InvalidArgumentException("Order Shipment could not be found!");
    }

    /**
     * {@inheritdoc}
     */
    public function getTotal($withTax = true)
    {
        return $withTax ? $this->getTotalGross() : $this->getTotalNet();
    }

    /**
     * {@inheritdoc}
     */
    public function setTotal($total, $withTax = true)
    {
        return $withTax ? $this->setTotalGross($total) : $this->setTotalNet($total);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderItem()
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderItem($orderItem)
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuantity()
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function setQuantity($quantity)
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalNet()
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalNet($totalNet)
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalGross()
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalGross($totalGross)
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function getWeight()
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }

    /**
     * {@inheritdoc}
     */
    public function setWeight($weight)
    {
        throw new ImplementedByPimcoreException(__CLASS__, __METHOD__);
    }
}
