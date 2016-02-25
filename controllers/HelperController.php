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

use CoreShop\Plugin;
use CoreShop\Controller\Action;
use Pimcore\Model\Object\CoreShopCart;
use Pimcore\Model\Object\CoreShopCartItem;
use Pimcore\Model\Object\CoreShopProduct;
use CoreShop\Model\PriceRule;

class CoreShop_HelperController extends Action
{
    public function changeCurrencyAction() {
        if (\CoreShop\Model\Currency::getById($this->getParam("currency")) instanceof \CoreShop\Model\Currency) {
            $this->session->currencyId = $this->getParam("currency");
        }

        $redirect = $this->getParam("redirect", $this->view->url(array("language" => $this->lang), "coreshop_index"));

        $this->redirect($redirect);
    }
}