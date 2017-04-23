/** Редактируемая таблица со списком позиций ЦЗ и выбраными поставщиками.
 *  Необходима для создания мультизаказа.
 *
 * @author  a.zdorenko
 * @class   Aplication.components.multiOrderGrid
 * @extends Ext.grid.EditorGridPanel
 */
Ext.define('Application.components.multiOrderGrid', {
    extend: 'Ext.grid.EditorGridPanel',
    priceOrderId: null,
    contragentIds: null,
    resultQuantity: 0,
    limit: 100,
    frame: false,
    border: false,
    sm: new Ext.grid.RowSelectionModel({singleSelect: false, moveEditorOnEnter: true}),
    clicksToEdit: 1,
    initComponent: function () {
        var components = this,
            columns = [],
            columnsSupplier = [],
            columnHeader = [],
            suppliers = this.contragentIds;

        if (!this.priceOrderId) {
            throw new Error('Не задан идентификатор ценового запроса.');
        }

        if (!suppliers) {
            throw new Error('Не выбран поставщик.');
        }

        this.store = this.createStore();
        generateColumns();

        var group = new Ext.extension.grid.ColumnHeaderGroup({
          rows: [columnHeader]
        });

        Ext.apply(components, {
            bbar: {
                items: []
            },
            listeners: {},
            colModel: new Ext.grid.ColumnModel({

                /**
                 * Определение возможности редактирования ячейки.
                 *
                 * @param {int} col Номер колонки
                 * @param {int} row Номер строки
                 *
                 * @return {bool} true|false
                 */
                isCellEditable: function(col, row) {
                    var grid = components,
                        rec = grid.getStore().getAt(row),
                        dataIndex = this.getDataIndex(col),
                        pos = dataIndex.lastIndexOf('_'),
                        supplierId = dataIndex.substr(pos + 1);
                    
                    if (dataIndex == 'buy_quantity_' + supplierId) {
                        // У поставщика отсутсвует данная позиция.
                        if (rec.get('posp_deliverable_' + supplierId) === false) {
                            Ext.Msg.alert('Ошибка', 'Данной позиции у поcтавщика нет в наличии.');
                            return;
                        } else {
                            var quantity = rec.get('quantity') - rec.get('buy_quantity');

                            if (quantity > 0) {

                                Ext.each(grid.contragentIds, function(supplier) {
                                    if (supplierId != supplier.id) {
                                        quantity = quantity - rec.get('buy_quantity_' + supplier.id);
                                    }
                                });
                                if (quantity > 0) {
                                    components.resultQuantity = quantity;
                                    return true;
                                } else {
                                    Ext.Msg.alert('Ошибка', 'Данная позиция выбрана у другого поставщика.');
                                    return;
                                }
                            } else {
                                Ext.Msg.alert('Ошибка', 'Данную позицию больше нельзя заказать.');
                                return;
                            }
                        }
                    }
                },
                defaults: {
                    width: 80,
                    sortable: false,
                    editor: false
                },
                columns: columns
            }),
            plugins: group
        });

        Application.components.multiOrderGrid.superclass.initComponent.call(components);

        /**
         * Сгенерировать столбцы
         *
         * @return {*} columns
         */
        function generateColumns() {

            var grid = components;

            columns = [
                new Ext.grid.RowNumberer({header: '№ п/п', width: 40}),
                {
                    header: 'Описание запроса',
                    width: 300,
                    renderer: Application.models.PriceOrderItem.requestDescriptionСolumnRenderer()
                }, {
                    header: 'Оставшееся <br> кол-во',
                    dataIndex: 'remain_quantity',
                    renderer: function(name, cell, record) {
                        return (record.get('quantity') - record.get('buy_quantity'));
                    }
                }
            ];

            columnHeader = [
                {header: 'Оставшиеся <br> позиции', colspan: columns.length, align: 'center'}
            ];

            Ext.each(suppliers, function(supplier) {
                columnsSupplier = [
                    {
                        header: 'Цена за ед.',
                        dataIndex: 'posp_price_' + supplier.id,
                        renderer: function (value, meta, record) {
                            return grid.canChosenRenderer(
                                record.get('posp_deliverable_' + supplier.id),
                                record.get('posp_price_' + supplier.id)
                            );
                        }
                    }, {
                        header: 'Закупаемое <br> кол-во',
                        dataIndex: 'buy_quantity_' + supplier.id,
                        renderer: function (value, meta, record) {
                            return grid.canChosenRenderer(
                                record.get('posp_deliverable_' + supplier.id),
                                record.get('buy_quantity_' + supplier.id)
                            );
                        },
                        editor: new Ext.form.NumberField({
                            allowDecimals: true,
                            minValue: 0,
                            listeners: {
                                focus: function() {
                                    if (this.getValue() == 0) {
                                            this.setValue(null);
                                        }
                                    },

                                /**
                                 * Валидация.
                                 *
                                 * @param {String} value Значение.
                                 *
                                 * @return {*} true|false
                                 */
                                valid: function() {
                                    res = components.resultQuantity - this.getValue();
                                    if (res < 0) {
                                        this.setValue(null);
                                        Ext.Msg.alert('Ошибка', 'Некорректное количество позиций.');
                                        return;
                                    }
                                }
                            }
                        })
                    }
                ];

                columnHeader.push({
                    header: supplier.name,
                    align: 'center',
                    colspan: columnsSupplier.length
                });
                columns = columns.concat(columnsSupplier);
            });
        }
    },

    createStore: function () {
        var arrContragentIds = fields = [];

        fields = [
            {name: 'id', type: 'int', hidden: true},
            {name: 'dictionary_position_name', type: 'string'},
            {name: 'category_name', type: 'string'},
            {name: 'attributes', type: 'string'},
            {name: 'quantity', type: 'numeric'},
            {name: 'buy_quantity', type: 'numeric'},
            {name: 'remain_quantity', type: 'numeric'}
        ];

        Ext.each(this.contragentIds, function(supplier) {
            fields.push(
                {name: 'posp_price_' + supplier.id, type: 'numeric'},
                {name: 'posp_deliverable_' + supplier.id, type: 'bool'},
                {name: 'posp_id_' + supplier.id, type: 'int'},
                {name: 'buy_quantity_' + supplier.id, type: 'numeric'},
                {name: 'pos_id_' + supplier.id, type: 'int'}
            );
            arrContragentIds.push(supplier.id);
        });

        var store = new Ext.data.DirectStore({
            autoLoad: true,
            autoDestroy: true,
            autoSave: false,
            api: {
                read: RPC_nsi.Priceorder.getMultiOrderData
            },
            baseParams: {
                priceOrderId: this.priceOrderId,
                contragentIds: arrContragentIds
            },
            paramsAsHash: true,
            idProperty: 'id',
            root: 'rows',
            fields: fields
        });
        return store;
    },

    /*
     * Определяет можно ли заказать позицию.
     *
     * @param {Boolean} deliverable Доступность у поставщика.
     * @param {Numeric} recordGet record.
     *
     * @return
     */
    canChosenRenderer: function (deliverable, recordGet) {
        return (deliverable) ? recordGet : '-';
    }
});

