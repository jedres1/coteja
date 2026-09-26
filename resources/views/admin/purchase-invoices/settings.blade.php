<x-layouts.app title="Configuración de correo — Compras">
    <div class="top">
        <div>
            <h1>Configuración de correo</h1>
            <p class="muted">Parámetros IMAP para extraer facturas de compra desde el buzón.</p>
        </div>
    </div>

    <div class="card" style="max-width:680px">
        <form method="POST" action="{{ route('admin.purchase-invoices.settings.update') }}">
            @csrf

            <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px">
                <label style="grid-column:1/3">
                    Servidor IMAP (host)
                    <input type="text" name="mailbox_host" value="{{ old('mailbox_host', $host) }}" required maxlength="255" placeholder="imap.gmail.com">
                </label>

                <label>
                    Puerto
                    <input type="number" name="mailbox_port" value="{{ old('mailbox_port', $port) }}" required min="1" max="65535" placeholder="993">
                </label>

                <label>
                    Límite de correos por extracción
                    <input type="number" name="mailbox_limit" value="{{ old('mailbox_limit', $limit) }}" required min="1" max="100" placeholder="25">
                </label>

                <label style="grid-column:1/3">
                    Usuario (correo)
                    <input type="email" name="mailbox_username" value="{{ old('mailbox_username', $username) }}" required maxlength="255" placeholder="facturacion@empresa.com">
                </label>

                <label style="grid-column:1/3">
                    Contraseña
                    <input type="password" name="mailbox_password" maxlength="255" placeholder="{{ $hasPassword ? '(contraseña guardada — dejar vacío para no cambiar)' : 'Ingresar contraseña' }}" autocomplete="new-password">
                    @if($hasPassword)
                        <span class="muted" style="font-size:12px;font-weight:400">Actualmente hay una contraseña guardada. Déjala vacía para conservarla.</span>
                    @endif
                </label>

                <label style="grid-column:1/3">
                    Buzón (mailbox)
                    <input type="text" name="mailbox_mailbox" value="{{ old('mailbox_mailbox', $mailbox) }}" required maxlength="100" placeholder="INBOX">
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
        </form>
    </div>
</x-layouts.app>
