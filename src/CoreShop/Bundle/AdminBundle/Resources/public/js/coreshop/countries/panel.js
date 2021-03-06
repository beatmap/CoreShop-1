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

pimcore.registerNS('pimcore.plugin.coreshop.countries.panel');
pimcore.plugin.coreshop.countries.panel = Class.create(pimcore.plugin.coreshop.abstract.panel, {

    /**
     * @var string
     */
    layoutId: 'coreshop_countries_panel',
    storeId: 'coreshop_countries',
    iconCls: 'coreshop_icon_country',
    type: 'countries',

    url: {
        add: '/admin/coreshop/countries/add',
        delete: '/admin/coreshop/countries/delete',
        get: '/admin/coreshop/countries/get',
        list: '/admin/coreshop/countries/list'
    },

    initialize: function ($super) {
        this.store = new Ext.data.Store({
            restful: false,
            proxy: new Ext.data.HttpProxy({
                url: this.url.list
            }),
            reader: new Ext.data.JsonReader({
                rootProperty: 'data'
            }, [
                {name: 'id'},
                {name: 'name'},
                {name: 'zoneName'}
            ]),
            autoload: true,
            groupField: 'zoneName',
            groupDir: 'ASC'
        });

        $super();
    },

    getGridConfiguration: function () {
        return {
            store: this.store,
            groupField: 'zoneName',
            groupDir: 'ASC',
            features: [{
                ftype: 'grouping',

                // You can customize the group's header.
                groupHeaderTpl: '{name} ({children.length})',
                enableNoGroups: true,
                startCollapsed: true
            }]
        };
    }
});
