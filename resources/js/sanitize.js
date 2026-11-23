import DOMPurify from 'dompurify';

export function sanitizeHtml(value) {
    return DOMPurify.sanitize(value ?? '', {
        USE_PROFILES: { html: true },
        ADD_ATTR: ['target'],
    });
}
