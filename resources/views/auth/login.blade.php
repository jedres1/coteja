<x-layouts.app title="Ingresar | Coteja">
    <div style="min-height:100vh;display:grid;place-items:center;padding:24px;">
        <form method="post" action="{{ route('login.store') }}" class="card" style="width:min(420px,100%);">
            @csrf
            <img src="/images/facturacion-electron-logo.png" alt="CONSULTING AND TECH JANDRES" style="width:168px;max-width:100%;height:auto;margin:0 auto 14px;display:block;">
            <p class="muted" style="margin:0 0 18px;">Consulting and Tech Jandres.</p>
            @if($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif
            <div class="form-grid" style="grid-template-columns:1fr;">
                <label>Correo<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
                <label>Clave<input type="password" name="password" required></label>
                <button class="btn">Ingresar</button>
            </div>
        </form>
    </div>
</x-layouts.app>
