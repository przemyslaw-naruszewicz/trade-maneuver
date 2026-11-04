# Project instructions

Before planning or changing code, read and follow:

- [SDLC contract](SDLC.md): follow its task approval, publication, test-first, review, and delivery rules. Apply the MVP process; V2 automation and CD remain planned.
- [Architecture](docs/architecture.md), including its linked [DDD rules](docs/ddd.md) and relevant accepted ADRs.
- [Coding standards](docs/coding-standards.md): readability, comments, PHP usage, typing, and naming.
- [Testing strategy](docs/testing-strategy.md).
- [Domain dictionary](docs/dictionary.md): use its terms consistently in code, tests, APIs, events, and documentation. Update it when introducing or clarifying an agreed domain concept; resolve uncertain business meanings with the project owner.

These documents are mandatory. Distinguish target architecture and planned tooling from what is currently implemented.

## Working rules

- Do not spawn or use subagents, delegate tasks to other agents, or run multi-agent workflows. Complete work in the main agent to limit costs.
- Do not create commits unless the user explicitly requests it, except the local test-first commits on a task branch that SDLC §4 requires.
- Use Conventional Commits without a scope: `<type>: <description>` or `<type>!: <description>` for breaking changes. Do not use the `<type>(<scope>): <description>` format.
- Commit messages describe only the change and contain no authorship information.
- Keep user-facing output concise and factual; omit redundant explanations.
- When you lack knowledge or context needed to proceed, state what is unknown and ask the user before making dependent decisions. Do not guess.
- Use the Docker Compose environment described in [README](README.md). Run PHP, Composer, and Artisan through `docker compose exec app`; use the existing Make targets where appropriate.
- Before relying on package APIs, check installed PHP package versions with `docker compose exec app composer show <vendor/package>` and JS versions in `package.json` and `package-lock.json`.
- Follow existing conventions and the documented target layout; reuse existing components. Do not change dependencies without approval.
- Generate Laravel framework artifacts with Artisan and `--no-interaction`, respecting the documented layer and namespace. Domain and application classes remain plain PHP.
- Use PHPUnit and existing factories or domain builders. Run the narrowest relevant tests with `docker compose exec app php artisan test --compact <path-or-filter>`. Prefer tests over ad hoc verification scripts.
- After modifying PHP, run `docker compose exec app vendor/bin/pint --dirty --format agent`.
- Build frontend assets with `make build` when needed.
- Create documentation only when requested or required by these project rules.
- Humanize English prose: when writing documentation, tasks, PR descriptions, or other English text for people, apply a writing skill such as `humanizer` if one is available.
- Use only clearly fictional instruments, such as `DEMO_A`, in code, tests, documentation, and task drafts; the underscore keeps them from matching real tickers. The same applies to funds, issuers, and cryptocurrencies.

## Agent skills

Configuration for Matt Pocock's engineering skills, shared by Codex and Claude Code. This file, the SDLC contract, and the documents listed above take precedence over skill instructions. When you adapt a skill step to them, tell the user which step changed.

- Issue tracker: published tasks are GitHub Issues; agents only draft `docs/tasks/<TASK-ID>.md`. Adaptations for `to-spec`, `to-tickets`, `wayfinder`, and `triage`: [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md).
- Other skills: adaptations for `implement`, `implement-spec`, `code-review`, `pr`, and `prototype`: [docs/agents/skills.md](docs/agents/skills.md).
- Domain docs: single context. Where a skill names `GLOSSARY.md` or `docs/adr/`, use `docs/dictionary.md` and `docs/ADR/`: [docs/agents/domain.md](docs/agents/domain.md).
- Triage is not used, and no triage labels are configured.

Rules for every skill:

- Where a skill uses subagents, background or parallel agents, or worktrees (`code-review`, `research`, `grilling`, `codebase-design`'s design-it-twice, `improve-codebase-architecture`, `implement-spec`, `wayfinder`), do that work sequentially in the main agent.
- Where a skill commits, pushes, opens a PR, or comments on an Issue (`implement`, `prototype`, `diagnosing-bugs`, `pr`), do it only at the user's explicit request. The test-first commits in SDLC §4 are the one exception. Agents never merge or bypass protections (SDLC §5).
- Choose test levels from the testing strategy and run tools with the commands in the working rules and [README](README.md#quality-checks), not with a skill's generic examples (`tdd`, `implement`, `diagnosing-bugs`).
- Save working specs in the gitignored `docs_internal/specs/`. Save other skill working files (research notes, questionnaires, lessons, handoffs) outside the repository, at a path the user names; ask if none is given. Add them to the repository only when asked.
- `retro` and `wizard` only propose or prepare changes. Changes to CI, GitHub protections, secrets, or dependencies need the owner's approval (working rules; SDLC §6–8).
- Apply the MVP process: V2 automated review, an automatic fix loop, and CD are not part of any skill workflow. Use subscription plans; never configure separately billed model API calls.
