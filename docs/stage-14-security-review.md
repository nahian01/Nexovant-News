# NEXOVANT — STAGE 14
## Security Review: SSRF, XXE, Cryptography & Input Sanitization

### 1. Server-Side Request Forgery (SSRF) Defense

Because Nexovant fetches external RSS feeds, XML gazettes, and REST APIs, strict SSRF defenses are enforced at both network and application layers:

1. **Protocol Restriction:** Only `http` and `https` schemes are allowed. `file://`, `gopher://`, `dict://`, `ftp://`, and PHP wrappers are rejected with immediate `422 Unprocessable Entity`.
2. **Loopback & Private Address Blocking:**
   - Hostname check: Disallows `localhost`, `*.localhost`, `*.local`, `*.internal`.
   - Direct IP check: Disallows RFC1918 private subnets (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`).
   - Cloud Metadata protection: Blocks link-local address `169.254.169.254` and `169.254.0.0/16`.
   - Carrier-grade NAT (`100.64.0.0/10`), Multicast (`224.0.0.0/4`), and Zero-net (`0.0.0.0/8`) blocked.
   - IPv6 link-local and loopback (`::1`, `fe80::/10`, `fc00::/7`, `fd00::/8`) blocked.
3. **DNS Rebinding & Redirect Protection:**
   - Redirects are inspected manually with depth capped at 3. Every intermediate URL destination is evaluated against SSRF rules before following.

### 2. XML External Entity (XXE) & Quadratic Blowup Prevention

All feed XML payloads are parsed using PHP's `simplexml_load_string` configured with strict isolation flags:
```php
$sxml = simplexml_load_string(
    $cleanXml,
    SimpleXMLElement::class,
    LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
);
```
- `LIBXML_NONET`: Disables all network access during XML parsing, neutralizing XXE file disclosure or outbound SSRF through entity definitions (`<!ENTITY xxe SYSTEM "file:///etc/passwd">`).
- Internal entity loader disabled via `libxml_use_internal_errors(true)`.

### 3. Cryptography at Rest for Source Credentials

1. Credentials (`api_key`, `bearer_token`, basic auth passwords) are encrypted using Laravel's `Crypt::encryptString()` (AES-256-CBC with HMAC-SHA256 authenticated encryption).
2. The model definition includes `$hidden = ['encrypted_credentials']`.
3. In `SourceAdminController`, `transformSource()` strips raw credentials and provides only an unprivileged boolean indicator:
   ```json
   {
     "id": "...",
     "name": "Private REST Feed",
     "has_credentials": true
   }
   ```
   Plaintext secrets are never serialized into API responses, client payloads, or logs.
