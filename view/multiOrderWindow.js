/** Окно для создания мультизаказа.
 *
 * @author  a.zdorenko
 * @class   Aplication.components.multiOrderWindow
 * @extends Ext.Window
 */
Ext.define('Application.components.multiOrderWindow', {
    extend: 'Ext.Window',
    decline_view: 0,
    closable: true,
    priceOrderId: null,
    contragentType: null,
    contragentIds: null,
    initComponent: function () {
        var component = this;
        Ext.apply(component, {
            title: 'Список заказов',
            border: false,
            width: 750,
            height: 300,
            layout: 'border',
            modal: true,
            items: this.getItems(),
            buttons: [
                {
                    text: 'Закрыть',
                    handler: function () {
                        component.close();
                    }
                }, {
                    text: 'Создать заказы',
                    handler: function () {
                        component.createMultiOrder();
                    }
                }
            ]
        });
        Application.components.multiOrderWindow.superclass.initComponent.call(this);
    },
    getItems: function () {
        return {
            region: 'center',
            priceOrderId: this.priceOrderId,
            contragentIds: this.contragentIds,
            ref: 'multiOrder',
            xtype: 'Application.components.multiOrderGrid'
        }
    },

    /**
     * Создание мультизаказа.
     *
     * @return {void} void
     */
    createMultiOrder: function () {
        var arrPosp = [],
            arrPosId = [],
            objPosp = {},
            component = this,
            modifiedRecord = component.multiOrder.getStore().getModifiedRecords();

        if (modifiedRecord.length == 0) {
            Ext.Msg.alert('Ошибка', 'Нeобходимо заполнить поле закупаемое количество.');
            return;
        }

        Ext.each(modifiedRecord, function (rec) {
            Ext.each(component.contragentIds, function(contragent) {
                // Если корректное значение закупаемого кол-ва 
                if ( (!Ext.isEmpty(rec.get('buy_quantity_' + contragent.id)))
                    && (rec.get('buy_quantity_' + contragent.id) > 0 )
                ) {
                    objPosp = {
                        id : rec.get('posp_id_' + contragent.id),
                        quantity : rec.get('buy_quantity_' + contragent.id)
                    };

                    if (arrPosp.hasOwnProperty('sId_' + contragent.id)) {
                        arrPosp['sId_' + contragent.id].push(objPosp);
                    } else {
                        arrPosId['sId_' + contragent.id] = rec.get('pos_id_' + contragent.id);
                        arrPosp['sId_' + contragent.id] = [objPosp];
                    }
                }
            });
        });

        component.close();
        Ext.each(component.contragentIds, function(contragent) {

            if (arrPosp.hasOwnProperty('sId_' + contragent.id)) {

                // оформление заказ
                Application.models.PriceOrder.makeMultiOrder(
                    component.priceOrderId,
                    arrPosId['sId_' + contragent.id],
                    arrPosp['sId_' + contragent.id]
                ).then(function (response) {
                    Ext.Msg.alert('Успешно', 'Заказы оформлены', function() {
                        redirect_to('nsi/order/directCustomer/orderId/' + response.order_id);
                    });
                });
            }
        });

    }
});
