import { useEffect } from 'react';

export default function Modal({ title, onClose, children, maxWidth }) {
    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [onClose]);

    return (
        <div className="overlay-layer" role="dialog" aria-modal="true" aria-label={title}>
            <button className="overlay-backdrop" type="button" aria-label="Cerrar" onClick={onClose} />
            <div className="overlay-panel card" style={maxWidth ? { maxWidth } : undefined}>
                <div className="overlay-header">
                    <div><h3>{title}</h3></div>
                    <button type="button" className="btn secondary overlay-close" onClick={onClose}>✕</button>
                </div>
                {children}
            </div>
        </div>
    );
}
