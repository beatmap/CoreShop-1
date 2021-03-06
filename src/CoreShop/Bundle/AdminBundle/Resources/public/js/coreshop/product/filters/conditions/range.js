/*
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2017 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 *
 */

pimcore.registerNS('pimcore.plugin.coreshop.filters.conditions.range');

pimcore.plugin.coreshop.filters.conditions.range = Class.create(pimcore.plugin.coreshop.filters.conditions.abstract, {

    type: 'range',

    getItems: function () {
        return [
            {
                fieldLabel: t('coreshop_product_filters_step_count'),
                xtype: 'numberfield',
                name: 'stepCount',
                value: this.data.stepCount,
                width: 400,
                decimalPrecision: 2
            },
            {
                xtype: 'combo',
                fieldLabel: t('coreshop_product_filters_value_min'),
                name: 'preSelectMin',
                width: 400,
                store: this.valueStore,
                displayField: 'key',
                valueField: 'value',
                triggerAction: 'all',
                typeAhead: false,
                editable: false,
                forceSelection: true,
                queryMode: 'local',
                value: this.data.preSelectMin
            },
            {
                xtype: 'combo',
                fieldLabel: t('coreshop_product_filters_value_max'),
                name: 'preSelectMax',
                width: 400,
                store: this.valueStore,
                displayField: 'key',
                valueField: 'value',
                triggerAction: 'all',
                typeAhead: false,
                editable: false,
                forceSelection: true,
                queryMode: 'local',
                value: this.data.preSelectMax
            }
        ];
    }
});
