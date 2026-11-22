import { describe, it, expect } from 'vitest'
import { sanitizeHtml } from '@/utils/sanitize'

describe('Security: HTML Sanitizer (DOMPurify Hardening)', () => {
  it('strips inline script tags and dangerous scripts', () => {
    const malicious = '<p>Normal text</p><script>alert("XSS")</script>'
    const sanitized = sanitizeHtml(malicious)
    expect(sanitized).not.toContain('<script>')
    expect(sanitized).not.toContain('alert')
    expect(sanitized).toContain('<p>Normal text</p>')
  })

  it('strips unquoted and quoted event handlers on images and elements', () => {
    const vectors = [
      '<img src="x" onerror="alert(1)">',
      '<img src=x onerror=alert(1)>',
      '<svg onload=alert(document.domain)>',
      '<div onmouseover="evil()">hover me</div>',
    ]

    for (const vector of vectors) {
      const sanitized = sanitizeHtml(vector)
      expect(sanitized).not.toContain('onerror')
      expect(sanitized).not.toContain('onload')
      expect(sanitized).not.toContain('onmouseover')
      expect(sanitized).not.toContain('alert')
    }
  })

  it('strips javascript: URI schemes from hyperlinks', () => {
    const malicious = '<a href="javascript:alert(1)">Click to verify</a>'
    const sanitized = sanitizeHtml(malicious)
    expect(sanitized).not.toContain('javascript:')
  })

  it('preserves legitimate editorial HTML formatting and safe links', () => {
    const editorial =
      '<h3>Key Finding</h3><p>Lithography costs increased by <strong>15%</strong>. See <a href="https://example.com" class="text-blue-500">Source</a>.</p>'
    const sanitized = sanitizeHtml(editorial)
    expect(sanitized).toContain('<h3>Key Finding</h3>')
    expect(sanitized).toContain('<strong>15%</strong>')
    expect(sanitized).toContain('href="https://example.com"')
  })

  it('converts plain text double-newlines into paragraphs safely', () => {
    const plainText = 'First paragraph.\n\nSecond paragraph.'
    const sanitized = sanitizeHtml(plainText)
    expect(sanitized).toContain('<p class="mb-4 leading-relaxed">First paragraph.</p>')
    expect(sanitized).toContain('<p class="mb-4 leading-relaxed">Second paragraph.</p>')
  })
})
