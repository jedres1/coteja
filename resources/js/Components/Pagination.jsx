import { Link } from '@inertiajs/react';

export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return (
        <div className="pagination">
            {links.map((link, i) => {
                const label = link.label.replace('&laquo;', '«').replace('&raquo;', '»');
                if (!link.url) {
                    return <span key={i} className="disabled-page" dangerouslySetInnerHTML={{ __html: label }} />;
                }
                return (
                    <Link
                        key={i}
                        href={link.url}
                        className={link.active ? 'active-page' : ''}
                        dangerouslySetInnerHTML={{ __html: label }}
                        preserveScroll
                    />
                );
            })}
        </div>
    );
}
