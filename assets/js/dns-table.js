(function($) {

    wu_dns_table = new Vue({
        el: '#wu-dns-table',
        data: {
            error: null,
            results: {},
            loading: true,
        },
        updated() {
            this.$nextTick(function() {

                window.wu_initialize_tooltip();

            });
        }
    })

    $(document).ready(function() {

        $.ajax({
            url: ajaxurl,
            data: {
                action: 'wu_get_dns_records',
                domain: window.wu_dns_table_config.domain,
            },
            success: function(data) {

                Vue.set(wu_dns_table, 'loading', false);

                if (data.success) {

                    Vue.set(wu_dns_table, 'results', data.data);

                } else {

                    Vue.set(wu_dns_table, 'error', data.data);

                }

            },
        })

    });
})(jQuery);
