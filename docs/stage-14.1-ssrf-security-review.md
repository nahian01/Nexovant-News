# Stage 14.1 — SSRF & XXE Defense Hardening

## 1. Attack Vectors & Defense Surface
Allowing user or administrator configuration of external feed URLs (`feed_url`, `website_url`, `api_endpoint`) introduces Server-Side Request Forgery (SSRF) and XML External Entity (XXE) risks:
1. Internal port scanning (probing SSH on 22, Redis on 6379, MySQL on 3306).
2. Cloud metadata extraction (`http://169.254.169.254/latest/meta-data`).
3. Loopback / local host probing (`127.0.0.1`, `localhost`, `::1`).
4. Alternate IP encoding bypasses:
   - Decimal integer IP: `http://2130706433` ($\equiv 127.0.0.1$)
   - Hexadecimal IP: `http://0x7f000001` ($\equiv 127.0.0.1$)
   - Octal IP: `http://0177.0.0.1` ($\equiv 127.0.0.1$)
   - IPv4-mapped IPv6: `http://[::ffff:127.0.0.1]`
5. XXE local file disclosure (`<!ENTITY xxe SYSTEM "file:///etc/passwd">`).

## 2. Hardened SSRF Implementation (`FeedCollectorService` & `RestApiSourceConnector`)

```php
protected function assertUrlSafeFromSSRF(string $url): void
{
    $parsed = parse_url($url);
    $scheme = strtolower($parsed['scheme'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new Exception("SSRF Protection: Only HTTP and HTTPS protocols are permitted.");
    }

    // 1. Strict Port Whitelist
    $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
    $allowedPorts = [80, 443, 8080, 8443];
    if (!in_array($port, $allowedPorts, true)) {
        throw new Exception("SSRF Protection: Access to port '{$port}' is strictly prohibited.");
    }

    $host = $parsed['host'] ?? '';

    // 2. Alternate IP Representation Defenses
    if (preg_match('/^\d+$/', $host)) {
        throw new Exception("SSRF Protection: Decimal integer IP representation '{$host}' is prohibited.");
    }
    if (preg_match('/^0x[0-9a-f]+$/i', $host)) {
        throw new Exception("SSRF Protection: Hexadecimal IP representation '{$host}' is prohibited.");
    }
    // Reject octal IP notations (e.g. 0177.0.0.1)
    $octets = explode('.', $host);
    if (count($octets) === 4) {
        foreach ($octets as $oct) {
            if (strlen($oct) > 1 && str_starts_with($oct, '0')) {
                throw new Exception("SSRF Protection: Octal IP representation '{$host}' is prohibited.");
            }
        }
    }

    // 3. DNS Resolution & CIDR Filtering
    $ips = @gethostbynamel($host);
    if ($ips) {
        foreach ($ips as $ip) {
            $this->validateIpAddress($ip);
        }
    }
}
```

## 3. XXE XML Parser Defense
In `FeedCollectorService::parseXmlFeed`:
- `libxml_use_internal_errors(true)`
- Loaded using `LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING` flags.
- External network entity lookups and external DTD expansions are strictly prevented.

## 4. Test Verification Evidence
Verified in `test_group_f_ssrf_port_and_alternate_ip_defense_and_tenant_isolation`:
- Port 22 rejection verified.
- Port 6379 rejection verified.
- Port 3306 rejection verified.
- Decimal integer IP (`2130706433`) rejection verified.
- Hex IP (`0x7f000001`) rejection verified.
- Octal IP (`0177.0.0.1`) rejection verified.
- Loopback `127.0.0.1` and `169.254.169.254` rejection verified.
