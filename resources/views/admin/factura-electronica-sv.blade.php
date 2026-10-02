<x-layouts.app title="Factura Electrónica SV | Coteja">
    <script>
        window.cotejaBilling = {
            customers: @json($billingCustomers),
            settings: @json($billingSettings),
            correlatives: @json($billingCorrelatives),
            clientesVariosId: @json($clientesVariosId),
        };
    </script>
    <div id="factura-electronica-sv" v-cloak></div>
</x-layouts.app>
