# Stage 14.1 — Credential Security Audit & At-Rest Encryption

## 1. Security Architecture
All external source API keys, HTTP Bearer tokens, and basic authentication passwords must remain secure from leakages in logs, databases, and client-facing API responses.

## 2. Encryption Implementation
- **Cipher**: OpenSSL `AES-256-CBC` / `AES-256-GCM` via Laravel's native `Crypt` facade tied to `APP_KEY`.
- **Database Column**: `news_sources.encrypted_credentials` (`text`, nullable).
- **Accessor / Mutator**:
  - `setEncryptedCredentialsAttribute(array|string $value)`: Serializes payload to JSON and encrypts with `Crypt::encryptString()`.
  - `getDecryptedCredentialsAttribute()`: Decrypts via `Crypt::decryptString()` on-demand within trusted service boundaries.

## 3. Masking & Secret Sanitation Rules
When `NewsSource` records are returned to API consumers or rendered in Vue frontend components:
- `encrypted_credentials` is placed in Eloquent `$hidden` array.
- Outbound API responses strip plaintext tokens and replace them with masked representations:
  - Tokens longer than 8 characters: `sec_****9981` (first 4 and last 4 characters preserved, middle obscured).
  - Short tokens: `****` (completely redacted).
- Application logging (`Log::info`, `Log::error`) automatically filters headers containing `Authorization`, `X-API-KEY`, and `Cookie`.

## 4. Verification Test Evidence
Verified in `Stage14SourceNetworkAndGlobalCoverageTest::test_credential_encryption_at_rest_and_response_masking`:
- Stored token `sec_live_bearer_token_9981` verified as encrypted string in SQLite database.
- Direct database query confirms plaintext string does not appear in raw column.
- API endpoint `/api/v1/admin/sources/{id}` returned masked token `sec_****9981`.
- Zero plaintext credential leakage.
