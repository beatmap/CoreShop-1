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

use CoreShop\Component\Product\Model\ProductInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductController extends FrontendController
{
    public function latestAction(Request $request)
    {
        $storeRepository = $this->get('coreshop.repository.store');
        $productRepository = $this->get('coreshop.repository.product');

        return $this->render('CoreShopFrontendBundle:Product:_latest.html.twig', [
            'products' => $productRepository->getLatestByShop($storeRepository->find(1)),
        ]);
    }

    public function detailAction(Request $request, $name, $productId)
    {
        $productRepository = $this->get('coreshop.repository.product');
        $product = $productRepository->find($productId);

        if (!$product instanceof ProductInterface) {
            return $this->redirectToRoute('coreshop_shop_index');
        }

        $this->get('coreshop.tracking.manager')->trackPurchasableView($product);

        return $this->render('CoreShopFrontendBundle:Product:detail.html.twig', [
            'product' => $product,
        ]);
    }
}
