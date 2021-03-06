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

namespace CoreShop\Bundle\FrontendBundle\Controller;

use CoreShop\Component\Core\Repository\CurrencyRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class CurrencyController extends FrontendController
{
    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function widgetAction(Request $request)
    {
        $currencies = $this->get('coreshop.repository.currency')->findActiveForStore($this->get('coreshop.context.shopper')->getStore());

        return $this->render('CoreShopFrontendBundle:Currency:_widget.html.twig', [
            'currencies' => $currencies,
        ]);
    }

    /**
     * @param Request $request
     * @param $currencyCode
     *
     * @return RedirectResponse
     */
    public function switchAction(Request $request, $currencyCode)
    {
        $currency = $this->getCurrencyRepository()->getByCode($currencyCode);

        $store = $this->get('coreshop.context.store')->getStore();
        $this->get('coreshop.storage.currency')->set($store, $currency);

        return new RedirectResponse($request->headers->get('referer', $request->getSchemeAndHttpHost()));
    }

    /**
     * @return CurrencyRepositoryInterface
     */
    protected function getCurrencyRepository()
    {
        /**
         * @var CurrencyRepositoryInterface
         */
        $repo = $this->get('coreshop.repository.currency');

        return $repo;
    }
}
