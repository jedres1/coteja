function Svg({ size, children }) {
    return (
        <svg
            width={size} height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            style={{ display: 'block', flexShrink: 0 }}
        >
            {children}
        </svg>
    );
}

export function IconEdit({ size = 15 }) {
    return (
        <Svg size={size}>
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
            <path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z" />
        </Svg>
    );
}

export function IconTrash({ size = 15 }) {
    return (
        <Svg size={size}>
            <polyline points="3 6 5 6 21 6" />
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
            <path d="M10 11v6M14 11v6" />
            <path d="M9 6V4h6v2" />
        </Svg>
    );
}

export function IconEye({ size = 15 }) {
    return (
        <Svg size={size}>
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
            <circle cx="12" cy="12" r="3" />
        </Svg>
    );
}

export function IconCheck({ size = 15 }) {
    return (
        <Svg size={size}>
            <polyline points="20 6 9 17 4 12" />
        </Svg>
    );
}

export function IconDownload({ size = 15 }) {
    return (
        <Svg size={size}>
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" y1="15" x2="12" y2="3" />
        </Svg>
    );
}

export function IconX({ size = 15 }) {
    return (
        <Svg size={size}>
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </Svg>
    );
}

export function IconRefresh({ size = 15 }) {
    return (
        <Svg size={size}>
            <polyline points="23 4 23 10 17 10" />
            <polyline points="1 20 1 14 7 14" />
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
        </Svg>
    );
}
