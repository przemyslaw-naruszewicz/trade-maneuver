# Project instructions

Before planning or changing code, read and follow:

- [SDLC contract](SDLC.md): follow its task approval, test-first, review, and delivery rules. Apply the MVP process; V2 automation and CD remain planned.
- [Architecture](docs/architecture.md), including its linked [DDD rules](docs/ddd.md) and relevant accepted ADRs.
- [Coding standards](docs/coding-standards.md): readability, comments, PHP usage, typing, and naming.
- [Testing strategy](docs/testing-strategy.md).
- [Domain dictionary](docs/dictionary.md): use its terms consistently in code, tests, APIs, events, and documentation. Update it when introducing or clarifying an agreed domain concept; resolve uncertain business meanings with the project owner.

These documents are mandatory. Distinguish target architecture and planned tooling from what is currently implemented.

## Working rules

- Do not spawn or use subagents, delegate tasks to other agents, or run multi-agent workflows. Complete work in the main agent to limit costs. Where a skill uses subagents, background or parallel agents, or worktrees (`code-review`, `research`, `grilling`, `codebase-design`'s design-it-twice, `improve-codebase-architecture`, `implement-spec`, `wayfinder`), do that work one step after another in the main agent. The only permitted subagent is the independent reviewer in the [SDLC §4 review flow](SDLC.md#review-flow). At most one runs at a time, and it starts no agents of its own; where a skill runs parallel agents, such as the two axes of Matt Pocock's `code-review`, the reviewer runs them one after the other.
- Commit, push, or open a PR only at the user's explicit request, including where a skill does so (`implement`, `prototype`, `diagnosing-bugs`, `pr`). The exceptions are the test-first commits on a task branch that SDLC §4 requires, and the commits, push, PR, and review that invoking `implement` on an approved task, or `to-tickets`, authorizes under the [SDLC §4 review flow](SDLC.md#review-flow). Agents never merge or bypass protections (SDLC §5).
- Use Conventional Commits without a scope: `<type>: <description>` or `<type>!: <description>` for breaking changes. Do not use the `<type>(<scope>): <description>` format. Commits on a task branch and PR titles add the task ID, `<type>: <TASK-ID> <description>`, and branches follow the naming in [SDLC §4](SDLC.md#branches-and-prs).
- Commit messages describe only the change. Commit messages and PR descriptions contain no authorship information.
- Keep user-facing output concise and factual; omit redundant explanations. Report the outcome and what needs the owner's decision, in at most five lines unless the owner asks for more. After a review, give the result, the number of `BLOCKER` and `MAJOR` findings, the reviewer's recommendation, and the PR link.
- When you lack knowledge or context needed to proceed, state what is unknown and ask the user before making dependent decisions. Do not guess.
- Use the Docker Compose environment described in [README](README.md). Run PHP, Composer, and Artisan through `docker compose exec app`; use the existing Make targets where appropriate.
- Before relying on package APIs, check installed PHP package versions with `docker compose exec app composer show <vendor/package>` and JS versions in `package.json` and `package-lock.json`.
- Follow existing conventions and the documented target layout; reuse existing components. Do not change dependencies without approval.
- Before writing tooling, scripts, infrastructure code, or generic helpers that a task does not specify, check in this order: (1) what is already installed; (2) which packages or tools do the job; (3) how the best candidates compare, testing the leading one in a throwaway spike outside the PR. Then present the option and its cost to the owner; a new dependency needs the owner's approval. Domain logic is exempt. A task that prescribes custom tooling states why no existing tool fits.
- Generate Laravel framework artifacts with Artisan and `--no-interaction`, respecting the documented layer and namespace. Domain and application classes remain plain PHP.
- Use PHPUnit and existing factories or domain builders. Run the narrowest relevant tests with `docker compose exec app php artisan test --compact <path-or-filter>`. Prefer tests over ad hoc verification scripts.
- After modifying PHP, run `docker compose exec app vendor/bin/pint --dirty --format agent`.
- Build frontend assets with `make build` when needed.
- Create documentation only when requested or required by these project rules.
- Governing documents and ADRs state rules and decisions. They do not list what is implemented; a task updates them only when it changes a rule or delivers an item marked Planned.
- Update documents that describe the current process in place. Keep documents that weigh options in their original form, because their starting state and reasoning matter, and add at most a short note on what no longer applies.
- Humanize English prose: when writing documentation, tasks, PR descriptions, or other English text for people, apply a writing skill such as `humanizer` if one is available.
- Use only clearly fictional instruments, such as `DEMO_A`, and the fictional exchange `DEMO`; the underscore keeps the symbols from matching real tickers. Derive every exchange code, ticker, ISIN, and invalid currency code from `DEMO`, for example `DEM0` or `DEMOX`, and never invent a plausible one, because it may belong to a real market, security, or currency. Use real currency codes, such as `EUR` and `USD`, only for the real currencies. The rule covers code, tests, temporary changes such as break checks, documentation, task drafts, commit messages, and PR descriptions. The same applies to funds, issuers, and cryptocurrencies.

## Agent skills

Configuration for Matt Pocock's engineering skills, shared by Codex and Claude Code. This file, the SDLC contract, and the documents listed above take precedence over skill instructions. When you adapt a skill step to them, tell the user which step changed.

- Issue tracker: the project does not use GitHub Issues. The authoritative task backlog is `docs/tasks/<TASK-ID>.md`: agents draft tasks and implement those `approved` on `main`. Adaptations for `to-spec`, `to-tickets`, `wayfinder`, and `triage`: [docs/agents/issue-tracker.md](docs/agents/issue-tracker.md).
- Other skills: adaptations for `implement`, `implement-spec`, `code-review`, `pr`, and `prototype`: [docs/agents/skills.md](docs/agents/skills.md).
- `code-review` in this file, SDLC.md, and `docs/agents/` means Matt Pocock's skill, `mattpocock-skills:code-review` in Claude Code. The independent review never uses Claude Code's built-in `code-review` skill or `/code-review ultra` ([docs/agents/skills.md](docs/agents/skills.md#code-review)).
- Domain docs: single context. Where a skill names `GLOSSARY.md` or `docs/adr/`, use `docs/dictionary.md` and `docs/ADR/`: [docs/agents/domain.md](docs/agents/domain.md).
- Triage is not used, and no triage labels are configured.

Rules for every skill:

- Skip skill steps that read, create, or comment on a GitHub Issue.
- Choose test levels from the testing strategy and run tools with the commands in the working rules and [README](README.md#quality-checks), not with a skill's generic examples (`tdd`, `implement`, `diagnosing-bugs`).
- Save working specs in the gitignored `docs_internal/specs/`. Save other skill working files (research notes, questionnaires, lessons, handoffs) outside the repository, at a path the user names; ask if none is given. Add them to the repository only when asked.
- `retro` and `wizard` only propose or prepare changes. Changes to CI, GitHub protections, secrets, or dependencies need the owner's approval (working rules; SDLC §6–8).
- Apply the MVP process: V2 automated review, an automatic fix loop, and CD are not part of any skill workflow. Use subscription plans; never configure separately billed model API calls.
