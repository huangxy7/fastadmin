define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'clipboard.min'], function ($, undefined, Backend, Table, Form, ClipboardJS) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'doudian/orders/index' + location.search,
                    add_url: 'doudian/orders/add',
                    edit_url: 'doudian/orders/edit',
                    // del_url: 'doudian/orders/del',
                    multi_url: 'doudian/orders/multi',
                    import_url: 'doudian/orders/import',
                    table: 'payment',
                }
            });
            //绑定复制事件
            var clipboard = new ClipboardJS('.btn-copy');
            clipboard.on('success', function (e) {
                Toastr.success('复制成功');
            });
            clipboard.on('error', function (e) {
                Toastr.error('复制失败，请刷新后重试');
            });
            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'trade_order_no', title: __('平台交易单号'), operate: 'LIKE'},//充值平台交易单号
                        // {field: 'seller_order_no', title: __('商家订单号'), operate: 'LIKE'},//商家订单号
                        {field: 'time_start', title: __('下单时间'), operate: 'LIKE'},//
                        {field: 'pay_amount', title: __('订单金额'), operate: 'LIKE', table: table},
                        {field: 'device', title: "处理设备", operate: 'LIKE', table: table},// 处理设备、
                        {field: 'accountName', title: "账号名称", operate: 'LIKE', table: table},// 账号名称、
                        {
                            field: 'customInfos_value',
                            title: __('充值账号'),
                            operate: 'LIKE',
                            table: table,
                            class: 'autocontent',
                            formatter: function (value, row, index) {
                                return '<a href="javascript:;"  data-clipboard-text="' + value + '" class="btn-copy" data-toggle="tooltip" data-original-title="点击复制">' + value + '</a>';
                            }
                        },
                        {field: 'system_status_name', title: __('处理状态'), operate: 'LIKE', table: table}, // 处理状态
                        {field: 'code', title: __('商品编码'), operate: 'LIKE'},//商品编码（抖店后台）
                        {field: 'pay_amount', title: __('金额'), operate: 'LIKE'},//支付金额
                        // {field: 'status', title: __('状态'), operate: 'LIKE'},//订单状态0：订单充值中，1：订单充值成功，2 订单充值失败,3:平台通知订单取消充值，4：订单超时取消充值
                        {field: 'create_time', title: __('创建时间'), operate: 'LIKE', table: table},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $('.panel-heading .nav-custom-condition a[data-toggle="tab"]', table.closest(".panel-intro")).on('shown.bs.tab', function (e) {
                var value = $(this).data("value");
                var options = table.bootstrapTable('getOptions');
                var queryParams = options.queryParams;
                options.pageNumber = 1;
                options.queryParams = function (params) {
                    //这一行必须要存在,否则在点击下一页时会丢失搜索栏数据
                    params = queryParams(params);

                    //如果希望追加搜索条件,可使用
                    var filter = params.filter ? JSON.parse(params.filter) : {};
                    var op = params.op ? JSON.parse(params.op) : {};
                    if (value) {
                        //这里可以自定义多个筛选条件
                        filter.order_type = value;
                        op.order_type = '=';
                    } else {
                        //选全部时要移除相应的条件
                        delete filter.order_type;
                        delete op.order_type;
                    }

                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);

                    //如果希望忽略搜索栏搜索条件,可使用
                    //params.filter = JSON.stringify(value?{admin_id: value}:{});
                    //params.op = JSON.stringify(value?{admin_id: '='}:{});
                    return params;
                };

                table.trigger("uncheckbox");
                table.bootstrapTable('refresh', {pageNumber: 1});

                return false;
            });
            // 启动和暂停按钮
            $(document).on("click", ".btn-start,.btn-pause,.btn-reload", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                var ids = Table.api.selectedids(table);
                Table.api.multi("changestatus", ids.join(","), table, this);
                setTimeout(function () {
                    location.reload()
                }, 3000)

            });
            setInterval(function () {
                table.bootstrapTable('refresh', {silent: true});
            }, 3000);

        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
