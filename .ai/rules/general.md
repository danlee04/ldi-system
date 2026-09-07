---
paths:
  - '**'
---

# General

## Never run git commit — the user commits
Do not run `git commit`, `git push`, or `git tag` in this repo. The user commits everything themselves.

Instead, write the commit message for them to paste. Their cadence is roughly one message covering every two working sessions, so the message should summarise the whole span of work, not a single file change.

Plans in docs/superpowers/plans/ contain "Commit" steps — write the message and hand it over at that point rather than running the command.

## Never run git commit — the user commits
Do not run `git commit`, `git push`, or `git tag` in this repo. The user commits everything themselves.

Write the commit message for them to paste instead. Keep it to ONE sentence — a single subject line, simple and direct, no body paragraphs and no bullet lists. The user asked for this explicitly.

Their cadence is roughly one message covering every two working sessions, so that one sentence should name the span of work, not a single file change.

Plans in docs/superpowers/plans/ have "Commit message" steps that already hold a one-line message — hand it over rather than running the command.
