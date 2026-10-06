<x-layouts.app title="Configuración de Compras">
    <style>
        .accounting-config-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .missing-entries-panel { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-top:18px; padding:14px; border:1px solid var(--line); background:#f8fafc; }
        .missing-entries-panel > div { display:grid; gap:4px; }
        @media (max-width:600px) { .accounting-config-grid { grid-template-columns:1fr; } .missing-entries-panel { align-items:stretch; flex-direction:column; } }
        .config-lock-panel {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 14px 18px; border-radius: 10px; background: #f1f5f9;
            border: 1px solid #e2e8f0; margin-bottom: 16px;
        }
        .config-lock-panel.unlocked { background: #fefce8; border-color: #fde047; }
        .config-lock-panel h3 { margin: 0 0 4px; font-size: 15px; }
        .config-lock-panel p { margin: 0; font-size: 13px; color: var(--muted); }
        .config-switch { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; font-size: 22px; }
        .config-switch input[type=checkbox] { display: none; }
        .config-switch-track {
            width: 48px; height: 26px; border-radius: 999px; background: #cbd5e1;
            position: relative; transition: background .2s;
        }
        .config-switch-track::after {
            content: ''; position: absolute; top: 3px; left: 3px;
            width: 20px; height: 20px; border-radius: 50%; background: #fff;
            transition: transform .2s; box-shadow: 0 1px 3px rgb(0 0 0/.2);
        }
        .config-switch input:checked ~ .config-switch-track { background: #f59e0b; }
        .config-switch input:checked ~ .config-switch-track::after { transform: translateX(22px); }
        .config-warning {
            padding: 12px 16px; border-radius: 8px; background: #fef9c3;
            border: 1px solid #fde047; margin-bottom: 16px; font-size: 13px;
        }
        .config-warning strong { display: block; margin-bottom: 4px; }
        .config-fieldset { border: none; padding: 0; margin: 0; }
        .config-fieldset:disabled input,
        .config-fieldset:disabled select,
        .config-fieldset:disabled textarea { background: #f8fafc; color: #6b7280; cursor: not-allowed; }
        .config-fieldset:disabled .btn { opacity: .45; pointer-events: none; }
        .password-row { display: flex; align-items: center; gap: 8px; }
        .password-row input { flex: 1; }
        .eye-btn {
            flex: 0 0 auto; padding: 9px 11px; border-radius: 8px; border: 1px solid var(--line);
            background: #fff; cursor: pointer; font-size: 15px; line-height: 1;
        }
        .eye-btn:disabled { opacity: .45; cursor: not-allowed; }
    </style>

    <div class="top">
        <div>
            <h1>Configuración de Compras</h1>
            <p class="muted">Cuentas contables y conexión de correo para el módulo de compras.</p>
        </div>
    </div>

    <div class="card" style="margin-bottom:18px">
        <div style="margin-bottom:18px">
            <h2 style="margin:0 0 5px">Parámetros contables</h2>
            <p class="muted" style="margin:0">Define las cuentas de los asientos que se generan al aprobar una compra y registrar su pago.</p>
        </div>

        @if(!$purchasePackage || !$payablePackage)
            <div class="config-warning">
                <strong>Paquetes contables no disponibles</strong>
                Ejecute las migraciones pendientes para habilitar los paquetes CP y CXP.
            </div>
        @else
            <form method="POST" action="{{ route('admin.purchase-invoices.settings.accounting') }}">
                @csrf
                <div class="accounting-config-grid">
                    <label>
                        CP · Cuenta de compra (Debe)
                        <select name="purchase_debit_account_id" required>
                            <option value="">Seleccione cuenta</option>
                            @foreach($accountingAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('purchase_debit_account_id', $purchasePackage->debit_account_id) === (string) $account->id)>{{ $account->code }} · {{ $account->name }} ({{ $account->typeLabel() }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        CP · Cuenta por pagar (Haber)
                        <select name="purchase_credit_account_id" required>
                            <option value="">Seleccione cuenta</option>
                            @foreach($accountingAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('purchase_credit_account_id', $purchasePackage->credit_account_id) === (string) $account->id)>{{ $account->code }} · {{ $account->name }} ({{ $account->typeLabel() }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        CP · Centro de costo
                        <select name="purchase_cost_center_id">
                            <option value="">Sin centro de costo</option>
                            @foreach($costCenters as $center)
                                <option value="{{ $center->id }}" @selected((string) old('purchase_cost_center_id', $purchasePackage->cost_center_id) === (string) $center->id)>{{ $center->code }} · {{ $center->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        CXP · Disminución de proveedores (Debe)
                        <select name="payable_debit_account_id" required>
                            <option value="">Seleccione cuenta</option>
                            @foreach($accountingAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('payable_debit_account_id', $payablePackage->debit_account_id) === (string) $account->id)>{{ $account->code }} · {{ $account->name }} ({{ $account->typeLabel() }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        CXP · Banco o caja (Haber)
                        <select name="payable_credit_account_id" required>
                            <option value="">Seleccione cuenta</option>
                            @foreach($accountingAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('payable_credit_account_id', $payablePackage->credit_account_id) === (string) $account->id)>{{ $account->code }} · {{ $account->name }} ({{ $account->typeLabel() }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        CXP · Centro de costo
                        <select name="payable_cost_center_id">
                            <option value="">Sin centro de costo</option>
                            @foreach($costCenters as $center)
                                <option value="{{ $center->id }}" @selected((string) old('payable_cost_center_id', $payablePackage->cost_center_id) === (string) $center->id)>{{ $center->code }} · {{ $center->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:16px">
                    <button type="submit" class="btn">Guardar parámetros contables</button>
                    <span class="muted" style="font-size:12px">CP se genera al aprobar; CXP al marcar la factura como pagada.</span>
                </div>
            </form>

            @if($missingPurchaseEntries || $missingPayableEntries)
                <div class="missing-entries-panel">
                    <div>
                        <strong>Hay asientos pendientes de generar</strong>
                        <span class="muted">{{ $missingPurchaseEntries }} compra(s) aprobada(s) y {{ $missingPayableEntries }} pago(s) sin asiento.</span>
                    </div>
                    <form method="POST" action="{{ route('admin.purchase-invoices.settings.generate-missing-entries') }}">
                        @csrf
                        <button type="submit" class="btn secondary">Generar asientos faltantes</button>
                    </form>
                </div>
            @endif
        @endif
    </div>

    <div class="card" style="max-width:680px">
        <div style="margin-bottom:16px">
            <h2 style="margin:0 0 5px">Configuración de correo</h2>
            <p class="muted" style="margin:0">Parámetros IMAP para extraer facturas de compra desde el buzón.</p>
        </div>

        {{-- Bloque lock/unlock --}}
        <div class="config-lock-panel" id="lockPanel">
            <div>
                <h3 id="lockTitle">Configuración bloqueada</h3>
                <p id="lockDesc">Los campos permanecen protegidos para evitar cambios accidentales.</p>
            </div>
            <label class="config-switch" title="Bloquear o desbloquear configuración">
                <span id="lockIcon">🔒</span>
                <input type="checkbox" id="configToggle">
                <span class="config-switch-track"></span>
            </label>
        </div>

        {{-- Advertencia cuando está desbloqueado --}}
        <div class="config-warning" id="configWarning" style="display:none">
            <strong>Configuración desbloqueada</strong>
            Modifica los parámetros con cuidado. Un valor incorrecto impedirá la extracción de facturas desde el correo.
        </div>

        <form method="POST" action="{{ route('admin.purchase-invoices.settings.update') }}">
            @csrf
            <fieldset class="config-fieldset" id="configFieldset" disabled>
                <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px">

                    <label style="grid-column:1/3">
                        Servidor IMAP (host)
                        <input type="text" name="mailbox_host" value="{{ old('mailbox_host', $host) }}"
                               required maxlength="255" placeholder="imap.gmail.com">
                    </label>

                    <label>
                        Puerto
                        <input type="number" name="mailbox_port" value="{{ old('mailbox_port', $port) }}"
                               required min="1" max="65535" placeholder="993">
                    </label>

                    <label>
                        Límite de correos por extracción
                        <input type="number" name="mailbox_limit" value="{{ old('mailbox_limit', $limit) }}"
                               required min="1" max="100" placeholder="25">
                    </label>

                    <label style="grid-column:1/3">
                        Usuario (correo)
                        <input type="email" name="mailbox_username" value="{{ old('mailbox_username', $username) }}"
                               required maxlength="255" placeholder="facturacion@empresa.com">
                    </label>

                    <label style="grid-column:1/3">
                        Contraseña de aplicación
                        <div class="password-row">
                            <input type="text" id="passwordField" name="mailbox_password"
                                   value="{{ old('mailbox_password', $password) }}"
                                   maxlength="255" placeholder="{{ blank($password) ? 'Sin contraseña configurada' : '(contraseña guardada)' }}"
                                   autocomplete="off">
                            <button type="button" class="eye-btn" id="eyeBtn" disabled
                                    title="Mostrar / ocultar contraseña" onclick="togglePasswordView()">👁</button>
                        </div>
                        @if(!blank($password))
                            <span class="muted" style="font-size:12px;font-weight:400;margin-top:4px;display:block">
                                Contraseña guardada. Déjala vacía para conservarla sin cambios.
                            </span>
                        @endif
                    </label>

                    <label style="grid-column:1/3">
                        Buzón (mailbox)
                        <input type="text" name="mailbox_mailbox" value="{{ old('mailbox_mailbox', $mailbox) }}"
                               required maxlength="100" placeholder="INBOX">
                    </label>

                    <label style="grid-column:1/3; flex-direction:row; align-items:center; gap:10px; display:flex; cursor:pointer">
                        <input type="hidden" name="mailbox_only_unseen" value="0">
                        <input type="checkbox" name="mailbox_only_unseen" value="1" style="width:auto"
                               {{ old('mailbox_only_unseen', $onlyUnseen) ? 'checked' : '' }}>
                        <span style="font-weight:600;font-size:13px">Solo procesar correos no leídos</span>
                    </label>
                </div>

                <div style="display:flex; gap:10px; align-items:center">
                    <button type="submit" class="btn">Guardar configuración</button>
                    <a href="{{ route('admin.purchase-invoices.index') }}" class="btn secondary">Cancelar</a>
                </div>
            </fieldset>
        </form>
    </div>

    <script>
        const toggle    = document.getElementById('configToggle');
        const fieldset  = document.getElementById('configFieldset');
        const lockPanel = document.getElementById('lockPanel');
        const lockTitle = document.getElementById('lockTitle');
        const lockDesc  = document.getElementById('lockDesc');
        const lockIcon  = document.getElementById('lockIcon');
        const warning   = document.getElementById('configWarning');
        const eyeBtn    = document.getElementById('eyeBtn');

        toggle.addEventListener('change', () => {
            const unlocked = toggle.checked;
            fieldset.disabled = !unlocked;
            lockPanel.classList.toggle('unlocked', unlocked);
            lockIcon.textContent  = unlocked ? '🔓' : '🔒';
            lockTitle.textContent = unlocked ? 'Configuración desbloqueada' : 'Configuración bloqueada';
            lockDesc.textContent  = unlocked
                ? 'Edita solo si necesitas corregir los parámetros de conexión al correo.'
                : 'Los campos permanecen protegidos para evitar cambios accidentales.';
            warning.style.display = unlocked ? 'block' : 'none';
            eyeBtn.disabled = !unlocked;
        });

        function togglePasswordView() {
            const field = document.getElementById('passwordField');
            field.type = field.type === 'text' ? 'password' : 'text';
            document.getElementById('eyeBtn').textContent = field.type === 'text' ? '👁' : '🙈';
        }
    </script>
</x-layouts.app>
