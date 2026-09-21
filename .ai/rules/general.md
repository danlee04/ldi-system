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

## Deploying is `git pull` and nothing else
The user's rule: on the office server, `git pull` must be the whole deploy. Do not hand them extra steps to run after it.

- public/build is committed (not in .gitignore since 2026-09-21): the server has no Node. A pre-commit hook on the dev PC, .git/hooks/pre-commit, runs `npm run build` and `git add -A public/build`.
- On the server, .git/hooks/post-merge runs deploy.bat after every pull that changes something: composer install --no-dev, migrate --force, config/view/event cache, route:clear. A pull without it leaves the old cached config against new code — that is how "Rate limiter [login] is not defined" took the site down.
- Neither hook is versioned. Recreate both after a fresh clone. The post-merge hook must call deploy.bat by full Windows path: `exec cmd.exe //c "$(cygpath -w "$PWD")\deploy.bat"` — a bare `deploy.bat` is not found from Git Bash.
- .gitattributes keeps *.bat CRLF, which cmd.exe needs for goto.
