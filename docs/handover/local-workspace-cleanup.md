# Local Workspace Cleanup After Bucket Split

Date: 2026-03-07
Owner: SMMA/PAID/CIC handover

## What was done
After splitting work into bucket branches/worktrees (`smma/handover-split`, `paid/handover-split`, `cic/handover-split`), remaining local changes in the primary workspace were archived into a local git stash.

Created stash:
- `stash@{0}`
- Message: `post-split leftovers 2026-03-07`
- Source branch: `chore/security-infra-main`

## Why
This keeps the primary workspace clean while preserving any leftover local edits for recovery.

## How to inspect
```bash
git stash list
git stash show --name-only stash@{0}
```

## How to restore
```bash
git stash pop stash@{0}
```

Or apply without dropping the stash:
```bash
git stash apply stash@{0}
```

## Notes for other teams
- Stashes are local to the machine/repo clone and are not pushed to remote.
- Remote source of truth for split work is in PRs:
  - SMMA: https://github.com/KOldland/touchpoint-template/pull/47
  - PAID: https://github.com/KOldland/touchpoint-template/pull/48
  - CIC: https://github.com/KOldland/touchpoint-template/pull/49
