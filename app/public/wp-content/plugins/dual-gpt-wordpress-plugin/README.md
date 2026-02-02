Dual GPT WordPress Plugin

Notes
- DataForSEO SSL issue workaround: If PHP/cURL in Local/Flywheel fails TLS handshake
  (LibreSSL SSL_ERROR_SYSCALL), the plugin falls back to the system `curl` binary.
  This is a temporary workaround; the long-term fix is updating the PHP/cURL/SSL
  stack in the local environment. Remove the fallback once SSL is stable.
- Consider making the 500-word section/pull-quote threshold configurable (currently hard-coded).
