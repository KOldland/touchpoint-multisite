# Membership SLOs and Alerts (MEM-09)

## Service level objectives

These are initial production targets and should be tuned after first full-load staging benchmark.

## Core SLOs

1. **Landing signup-init latency**
   - SLI: `POST /kh-membership/v1/signup-init` response latency
   - Objective: p95 < **250ms**
   - Alert: p95 > 350ms for 15m

2. **Webhook queue admission latency**
   - SLI: webhook endpoint `handle_request` latency
   - Objective: p99 < **500ms**
   - Alert: p99 > 900ms for 10m

3. **Webhook processing latency**
   - SLI: `webhook.processed.latency_ms`
   - Objective: p99 < **2000ms**
   - Alert: p99 > 3500ms for 10m

4. **Transactional email success**
   - SLI: `1 - (membership.email.failed / email attempts)`
   - Objective: success > **99%** and failed <= **5/hour**
   - Alert: failure rate >1% or >5/hour for 30m

5. **DLQ growth stability**
   - SLI: open DLQ count delta over 15m
   - Objective: bounded/flat trend under normal traffic
   - Alert: +20 open rows in 15m

## Error budget policy

- Monthly availability/error budget applies to membership critical paths.
- Any two consecutive SLO burns trigger release freeze for membership changes until mitigated.

## Alert wiring references

- Dashboard config: `observability/dashboards/membership_health.json`
- MEM-08 release gate + canary checks: `docs/membership/release_runbook.md`
- Remediation playbooks:
  - `docs/membership/ops_runbook.md`
  - `docs/membership/troubleshooting.md`

## Breach simulation checklist

- [ ] Simulate webhook invalid signature burst and verify alert route.
- [ ] Simulate email failures above threshold and verify alert route.
- [ ] Simulate DLQ growth and validate replay remediation.
- [ ] Record alert timestamps and acknowledgment evidence in incident log.
