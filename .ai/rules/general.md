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

Keep that sentence SHORT — about ten words, the change and nothing more. No "because", no clauses explaining why; the why belongs in the code. Asked for again on 2026-09-21 after messages ran to thirty words. Example: `feat: allow deleting pending trainings and fix My trainings scroll`.

Their cadence is roughly one message covering every two working sessions, so that one sentence should name the span of work, not a single file change.

Plans in docs/superpowers/plans/ have "Commit message" steps that already hold a one-line message — hand it over rather than running the command.

## public/build is committed; the server deploys with deploy.bat
Since 2026-09-21 public/build is NOT in .gitignore: the office server has no Node, and the user wanted `git pull` to be enough. A pre-commit hook in .git/hooks/pre-commit (local to the dev PC, not versioned) runs `npm run build` and `git add -A public/build` before every commit; recreate it if the repo is cloned afresh. On the server, deploy.bat does pull, composer install --no-dev, migrate --force, config/view/event cache and route:clear. .gitattributes keeps *.bat CRLF, which cmd.exe needs for goto.
