import DOMPurify from 'dompurify'

/**
 * Sanitize untrusted HTML or text content to prevent Cross-Site Scripting (XSS).
 *
 * Strips active scripting vectors, event handlers, javascript: protocols,
 * and dangerous elements (script, style, iframe, object, embed),
 * while preserving safe semantic formatting tags for executive reading.
 */
export function sanitizeHtml(dirty: string): string {
  if (!dirty || typeof dirty !== 'string') {
    return ''
  }

  // If plain text without HTML tags, convert double newlines to paragraph tags
  const hasHtml = /<[a-z][\s\S]*>/i.test(dirty)
  const inputToSanitize = hasHtml
    ? dirty
    : dirty
        .split(/\n\s*\n/)
        .map((para) => `<p class="mb-4 leading-relaxed">${para.trim()}</p>`)
        .join('')

  return DOMPurify.sanitize(inputToSanitize, {
    ALLOWED_TAGS: [
      'p',
      'br',
      'b',
      'strong',
      'i',
      'em',
      'u',
      'a',
      'ul',
      'ol',
      'li',
      'blockquote',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'span',
      'div',
      'table',
      'thead',
      'tbody',
      'tr',
      'th',
      'td',
      'code',
      'pre',
    ],
    ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'class'],
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
    ADD_ATTR: ['target', 'rel'],
  })
}
