import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';

const dismissedKey = 'purchase-invoice-extraction-dismissed';

export default function PurchaseInvoiceExtractionWidget({ activeExtraction }) {
    const [run, setRun] = useState(null);
    const [hidden, setHidden] = useState(false);

    useEffect(() => {
        if (!activeExtraction?.id) return;
        if (window.localStorage.getItem(dismissedKey) === activeExtraction.id) {
            setHidden(true);
            return;
        }
        setRun(activeExtraction);
        setHidden(false);
    }, [activeExtraction?.id]);

    useEffect(() => {
        if (!run?.id || ['completed', 'failed'].includes(run.status)) return undefined;

        let cancelled = false;
        const poll = async () => {
            try {
                const response = await window.axios.get(route('admin.purchase-invoices.extractions.progress', run.id));
                if (!cancelled) setRun(response.data);
            } catch {
                // Keep showing the last known progress during brief network interruptions.
            }
        };

        poll();
        const timer = window.setInterval(poll, 1500);
        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [run?.id, run?.status]);

    useEffect(() => {
        if (!run || !['completed', 'failed'].includes(run.status)) return undefined;
        const timer = window.setTimeout(() => {
            window.localStorage.setItem(dismissedKey, run.id);
            setHidden(true);
        }, 8000);
        return () => window.clearTimeout(timer);
    }, [run?.id, run?.status]);

    if (!run || hidden) return null;

    const total = Number(run.total ?? 0);
    const reviewed = Number(run.reviewed ?? 0);
    const percent = total > 0 ? Math.min(100, Math.round((reviewed / total) * 100)) : 0;
    const finished = run.status === 'completed';
    const failed = run.status === 'failed';
    const dateRange = `${run.from || 'Inicio'} a ${run.to || 'Hoy'}`;

    return (
        <aside style={{
            position: 'fixed', right: 20, bottom: 20, zIndex: 10000, width: 'min(360px, calc(100vw - 32px))',
            padding: 16, borderRadius: 12, background: '#172554', color: '#fff',
            border: '1px solid #1e3a8a', boxShadow: '0 12px 36px rgba(15,23,42,.42)',
        }} role="status" aria-live="polite">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, marginBottom: 10 }}>
                <strong>{failed ? 'Extracción con error' : finished ? 'Extracción finalizada' : 'Extrayendo facturas'}</strong>
                {(finished || failed) && (
                    <button type="button" aria-label="Cerrar resumen" onClick={() => {
                        window.localStorage.setItem(dismissedKey, run.id);
                        setHidden(true);
                    }} style={{ border: 0, background: 'transparent', cursor: 'pointer', color: 'inherit', fontSize: 18 }}>×</button>
                )}
            </div>
            <div style={{ fontSize: 12, color: '#dbeafe', marginBottom: 12 }}>Fechas revisadas: {dateRange}</div>
            {!finished && !failed && (
                <div style={{ height: 6, background: '#334155', borderRadius: 99, overflow: 'hidden', marginBottom: 12 }}>
                    <div style={{ height: '100%', width: `${percent}%`, background: '#60a5fa', transition: 'width .25s' }} />
                </div>
            )}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: '6px 16px', fontSize: 13 }}>
                <span>Correos revisados</span><strong>{reviewed}{total > 0 ? ` / ${total}` : ''}</strong>
                <span>Facturas nuevas</span><strong>{run.imported ?? 0}</strong>
                <span>Duplicados</span><strong>{run.duplicates ?? 0}</strong>
            </div>
            {failed && <div style={{ color: '#fecaca', fontSize: 12, marginTop: 10 }}>No se pudo completar la extracción.</div>}
            {(finished || failed) && <div style={{ color: '#bfdbfe', fontSize: 11, marginTop: 10 }}>Este resumen se cerrará automáticamente.</div>}
        </aside>
    );
}
