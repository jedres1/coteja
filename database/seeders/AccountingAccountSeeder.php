<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use Illuminate\Database\Seeder;

class AccountingAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ─── 1 ACTIVOS ───────────────────────────────────────────────
            ['code' => '1',      'name' => 'ACTIVOS',                               'type' => 'activo',     'nature' => 'deudora',   'level' => 1, 'parent' => null],
            ['code' => '1.1',    'name' => 'Activos Corrientes',                    'type' => 'activo',     'nature' => 'deudora',   'level' => 2, 'parent' => '1'],
            ['code' => '1.1.01', 'name' => 'Caja General',                          'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.1.02', 'name' => 'Caja Chica',                            'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.1.03', 'name' => 'Bancos',                                'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.1.04', 'name' => 'Cuentas por Cobrar Clientes',           'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.1.05', 'name' => 'Anticipo a Proveedores',                'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.1.06', 'name' => 'IVA Crédito Fiscal',                   'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.1.07', 'name' => 'Anticipo de Impuestos (Pagos a Cta.)', 'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.1.08', 'name' => 'Otros Activos Corrientes',              'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.1'],
            ['code' => '1.2',    'name' => 'Activos No Corrientes',                 'type' => 'activo',     'nature' => 'deudora',   'level' => 2, 'parent' => '1'],
            ['code' => '1.2.01', 'name' => 'Mobiliario y Equipo de Oficina',        'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.2'],
            ['code' => '1.2.02', 'name' => 'Equipo de Cómputo',                    'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.2'],
            ['code' => '1.2.03', 'name' => 'Vehículos',                            'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.2'],
            ['code' => '1.2.04', 'name' => 'Depreciación Acumulada',                'type' => 'activo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '1.2'],
            ['code' => '1.2.05', 'name' => 'Intangibles y Software',                'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.2'],
            ['code' => '1.2.06', 'name' => 'Amortización Acumulada',                'type' => 'activo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '1.2'],
            ['code' => '1.2.07', 'name' => 'Garantías y Depósitos en Garantía',    'type' => 'activo',     'nature' => 'deudora',   'level' => 3, 'parent' => '1.2'],

            // ─── 2 PASIVOS ───────────────────────────────────────────────
            ['code' => '2',      'name' => 'PASIVOS',                               'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 1, 'parent' => null],
            ['code' => '2.1',    'name' => 'Pasivos Corrientes',                    'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 2, 'parent' => '2'],
            ['code' => '2.1.01', 'name' => 'Proveedores por Pagar',                 'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.02', 'name' => 'Cuentas por Pagar Diversas',            'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.03', 'name' => 'IVA Débito Fiscal',                    'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.04', 'name' => 'Retención ISR 10% por Pagar',          'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.05', 'name' => 'Cuota Laboral ISSS por Pagar',         'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.06', 'name' => 'Cuota Laboral AFP por Pagar',          'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.07', 'name' => 'Salarios por Pagar',                   'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.08', 'name' => 'Préstamos Bancarios Corto Plazo',      'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.09', 'name' => 'Ingresos Recibidos por Anticipado',    'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.10', 'name' => 'Otros Pasivos Corrientes',              'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.11', 'name' => 'ISR Empleados Retenido por Pagar',    'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.12', 'name' => 'Cuota Patronal ISSS por Pagar',       'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.1.13', 'name' => 'Cuota Patronal AFP por Pagar',        'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.1'],
            ['code' => '2.2',    'name' => 'Pasivos No Corrientes',                 'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 2, 'parent' => '2'],
            ['code' => '2.2.01', 'name' => 'Préstamos Bancarios Largo Plazo',      'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.2'],
            ['code' => '2.2.02', 'name' => 'Beneficios a Empleados Largo Plazo',   'type' => 'pasivo',     'nature' => 'acreedora', 'level' => 3, 'parent' => '2.2'],

            // ─── 3 PATRIMONIO ────────────────────────────────────────────
            ['code' => '3',      'name' => 'PATRIMONIO',                            'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 1, 'parent' => null],
            ['code' => '3.1',    'name' => 'Capital Social',                        'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 2, 'parent' => '3'],
            ['code' => '3.1.01', 'name' => 'Capital Social Pagado',                 'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 3, 'parent' => '3.1'],
            ['code' => '3.2',    'name' => 'Reservas',                              'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 2, 'parent' => '3'],
            ['code' => '3.2.01', 'name' => 'Reserva Legal',                         'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 3, 'parent' => '3.2'],
            ['code' => '3.3',    'name' => 'Resultados',                            'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 2, 'parent' => '3'],
            ['code' => '3.3.01', 'name' => 'Utilidades Retenidas de Ejercicios Anteriores', 'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 3, 'parent' => '3.3'],
            ['code' => '3.3.02', 'name' => 'Resultado del Ejercicio',               'type' => 'patrimonio', 'nature' => 'acreedora', 'level' => 3, 'parent' => '3.3'],

            // ─── 4 GASTOS ────────────────────────────────────────────────
            ['code' => '4',      'name' => 'GASTOS',                                'type' => 'gasto',      'nature' => 'deudora',   'level' => 1, 'parent' => null],
            ['code' => '4.1',    'name' => 'Gastos de Operación',                   'type' => 'gasto',      'nature' => 'deudora',   'level' => 2, 'parent' => '4'],
            ['code' => '4.1.01', 'name' => 'Sueldos y Salarios',                    'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.02', 'name' => 'Aguinaldo',                             'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.03', 'name' => 'Vacaciones',                            'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.04', 'name' => 'Indemnización',                         'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.05', 'name' => 'Cuota Patronal ISSS',                  'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.06', 'name' => 'Cuota Patronal AFP',                   'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.07', 'name' => 'Honorarios Profesionales',              'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.08', 'name' => 'Alquileres',                            'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.09', 'name' => 'Energía Eléctrica',                    'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.10', 'name' => 'Agua y Alcantarillado',                 'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.11', 'name' => 'Telefonía y Comunicaciones',            'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.12', 'name' => 'Internet y Conectividad',               'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.13', 'name' => 'Materiales y Suministros de Oficina',  'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.14', 'name' => 'Depreciaciones',                        'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.15', 'name' => 'Amortizaciones',                        'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.1.16', 'name' => 'Licencias y Suscripciones de Software', 'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.1'],
            ['code' => '4.2',    'name' => 'Gastos de Administración',              'type' => 'gasto',      'nature' => 'deudora',   'level' => 2, 'parent' => '4'],
            ['code' => '4.2.01', 'name' => 'Honorarios de Auditoría',              'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.2'],
            ['code' => '4.2.02', 'name' => 'Gastos Legales y Notariales',           'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.2'],
            ['code' => '4.2.03', 'name' => 'Papelería y Útiles',                   'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.2'],
            ['code' => '4.2.04', 'name' => 'Gastos de Representación y Viáticos',  'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.2'],
            ['code' => '4.2.05', 'name' => 'Publicidad y Mercadeo',                'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.2'],
            ['code' => '4.2.06', 'name' => 'Seguros',                               'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.2'],
            ['code' => '4.2.07', 'name' => 'Mantenimiento y Reparaciones',          'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.2'],
            ['code' => '4.3',    'name' => 'Gastos Financieros',                    'type' => 'gasto',      'nature' => 'deudora',   'level' => 2, 'parent' => '4'],
            ['code' => '4.3.01', 'name' => 'Intereses Bancarios',                   'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.3'],
            ['code' => '4.3.02', 'name' => 'Comisiones Bancarias',                  'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.3'],
            ['code' => '4.3.03', 'name' => 'Diferencial Cambiario Pérdida',        'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.3'],
            ['code' => '4.4',    'name' => 'Otros Gastos',                          'type' => 'gasto',      'nature' => 'deudora',   'level' => 2, 'parent' => '4'],
            ['code' => '4.4.01', 'name' => 'Gastos No Deducibles',                  'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.4'],
            ['code' => '4.4.02', 'name' => 'Pérdidas Diversas',                    'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.4'],
            ['code' => '4.4.03', 'name' => 'Impuesto sobre la Renta',               'type' => 'gasto',      'nature' => 'deudora',   'level' => 3, 'parent' => '4.4'],

            // ─── 5 INGRESOS ──────────────────────────────────────────────
            ['code' => '5',      'name' => 'INGRESOS',                              'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 1, 'parent' => null],
            ['code' => '5.1',    'name' => 'Ingresos por Servicios',                'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 2, 'parent' => '5'],
            ['code' => '5.1.01', 'name' => 'Servicios Profesionales',               'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.1'],
            ['code' => '5.1.02', 'name' => 'Servicios de Consultoría',              'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.1'],
            ['code' => '5.1.03', 'name' => 'Servicios de Soporte Técnico',         'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.1'],
            ['code' => '5.1.04', 'name' => 'Servicios de Capacitación y Formación', 'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.1'],
            ['code' => '5.1.05', 'name' => 'Servicios de Desarrollo de Software',  'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.1'],
            ['code' => '5.1.06', 'name' => 'Otros Servicios',                       'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.1'],
            ['code' => '5.2',    'name' => 'Ingresos Financieros',                  'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 2, 'parent' => '5'],
            ['code' => '5.2.01', 'name' => 'Intereses Ganados',                     'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.2'],
            ['code' => '5.2.02', 'name' => 'Diferencial Cambiario Ganancia',        'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.2'],
            ['code' => '5.3',    'name' => 'Otros Ingresos',                        'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 2, 'parent' => '5'],
            ['code' => '5.3.01', 'name' => 'Ingresos No Operativos',                'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.3'],
            ['code' => '5.3.02', 'name' => 'Ganancia en Venta de Activos',          'type' => 'ingreso',    'nature' => 'acreedora', 'level' => 3, 'parent' => '5.3'],
        ];

        // First pass: upsert por code, sin parent_id aún
        $idMap = [];
        foreach ($accounts as $data) {
            $account = AccountingAccount::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name'      => $data['name'],
                    'type'      => $data['type'],
                    'nature'    => $data['nature'],
                    'level'     => $data['level'],
                    'is_active' => true,
                ]
            );
            $idMap[$data['code']] = $account->id;
        }

        // Second pass: link parent_id
        foreach ($accounts as $data) {
            if ($data['parent'] !== null && isset($idMap[$data['parent']])) {
                AccountingAccount::where('code', $data['code'])
                    ->update(['parent_id' => $idMap[$data['parent']]]);
            }
        }
    }
}
