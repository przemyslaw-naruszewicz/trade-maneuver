# SDLC

- Status: adopted process contract. Planned automation is identified below.
- Model: human supervision, a Markdown task backlog, and delivery through pull requests.
- Contributions: only the owner works on the project, directly or through agents. The project has no license, accepts no contributions from other people, and does not use GitHub Issues.
- Agents: Codex or Claude Code, used interchangeably.

Each skill, agent, or tool is a replaceable implementation of a process stage. A replacement must preserve the inputs, output artifacts, approval rules, and quality gates required by this contract.

Examples from Matt Pocock's [mattpocock/skills](https://github.com/mattpocock/skills) repository: `grill-with-docs → to-spec → to-tickets → implement / tdd → code-review`. Open Mercato or other tools can fill the same roles under this contract.

Cost efficiency is a process requirement. Tool selection and automation must limit costs and unnecessary use of AI quotas while preserving the required tests and reviews.

Agent automation, including V2 review, must use the AI provider's subscription plans without separately billed model API calls. Choose an execution method that fits those plans' capabilities and limits. Exhausting a quota must not trigger a switch to paid API calls or skip a quality gate.

## 1. Workflow

`Discovery → Working spec → Markdown tasks → Planning PR and approval → Implementation/PR → CI + AI review → Merge → Full CI → CD/VPS`

| Stage | Action or outcome |
| --- | --- |
| Discovery | Establish the goal, scope, constraints, and risks. |
| Working spec | The agent prepares a temporary local specification outside the repository. |
| Markdown tasks | The agent creates `docs/tasks/<TASK-ID>.md` with an initial status of `draft`. |
| Approval | A human reviews tasks in a [planning PR](#planning-prs), sets `approved`, and merges the PR once its gates are met. |
| Implementation | The owner or an agent implements an approved task from `main` [test-first](#test-first-implementation) on a [separate branch](#branches-and-prs) and opens a PR that sets the task to `done`. |
| Validation and review | CI and an independent reviewer assess the current change against the approved task. |
| Merge | A human approves the merge once the gates in section 5 are met. Only then does `done` become the authoritative status on `main`. |
| Deployment (planned) | GitHub Actions publishes an image to GHCR and deploys it to the VPS after full CI passes for the revision being deployed. |

## 2. Requirements and source of truth

The working specification helps define tasks. It stays local and does not enter the repository. Transfer every decision needed for implementation and verification into the task before approval.

An approved task on `main` is the complete task specification and the source of its status. Implementation, PRs, and review use that task and the project's governing documents. They must not require access to working files. Requirements shared by several tasks must appear in each task or in an explicitly linked repository document read at the same base revision.

| Artifact | Role |
| --- | --- |
| Working specification | Local preparation |
| Draft task | Proposed scope and acceptance criteria (AC) for the owner's review |
| Approved task on `main` | Authoritative requirements and permission to begin implementation |
| Implementation PR | Proposed code, test evidence, independent review, and proposed task completion |
| Completed task on `main` | Record of the requirements delivered by that change |

The project owner changes and approves a task's scope and AC through a PR. An agent may propose changes, and the owner decides whether to accept them.

A change to scope or AC goes through its own task-change PR, separate from the implementation, under the [planning PR](#planning-prs) rules. The one exception is a [task adaptation found in review](#task-adaptation-found-in-review), which the owner approves in the implementation PR itself.

A substantive change requires updating the implementation and repeating review. It invalidates an earlier `PASS`, even if the code has not changed. This also applies to changes in shared requirements when they affect the task.

An exception that changes scope or AC follows the same path. Review then assesses the revised requirements. Approval of an exception does not replace a `PASS` result.

## 3. Task preparation and approval

Minimal frontmatter for `docs/tasks/AUTH-002.md`:

```yaml
---
id: AUTH-002
status: draft        # draft | approved | done
---
```

A task contains a stable ID, goal, scope and exclusions, requirements, constraints, significant decisions, AC, dependencies, and a verification method. Dependencies refer to task IDs. The `spec` field is optional.

### Task slicing

Slice tasks so that their delivery forms milestones and the running application shows incremental development and the gradual delivery of value.

- A milestone is a group of tasks that ends in a result that can be shown in the running application. A result that only tests can show does not end a milestone.
- A single task need not show a result on its own, for example when it is prefactoring, but its milestone must.
- Milestones may stay implicit. They need no field, label, or document.
- A task with the `SDLC-` prefix changes the development process or agent tooling. It belongs to no milestone, and the rest of the definition of ready still applies to it.

### Definition of ready

Before setting `approved`:

- [ ] The outcome, scope, and exclusions are clear.
- [ ] AC are testable and have a defined verification method. An AC verified by an automated test names its [test level](docs/testing-strategy.md#seams) and, where the behavior calculates or maps a value, gives concrete expected values taken from the requirements.
- [ ] Dependencies, constraints, and significant risks are documented.
- [ ] The task includes all necessary decisions from the working specification, with a link to shared requirements in a repository document where applicable.
- [ ] The task follows [task slicing](#task-slicing): it belongs to a milestone that ends in a result the running application can show, unless it is an `SDLC-` task.
- [ ] A human has approved the complete final task content.

Approval covers the final task content at the planning PR's head. The `approved` status records a human decision, and agents must not set it.

### Planning PRs

1. Prepare each task with `status: draft`, carrying all implementation decisions from any working specification.
2. Open a planning PR. Related tasks may share one PR to reduce approval overhead.
3. The owner approves the final task content and sets `status: approved`. This moves `head SHA`, so the CI that counts runs after it. A `PASS` for the earlier head stays valid under the [status-only exception](#status-only-change-after-review); any other change requires a new review.
4. The owner merges the PR once the section 5 gates that apply to it are met.

A task-change PR uses the same path to edit an approved task, and merging it records the owner's approval.

Planning PRs keep the same CI and independent review gates as other PRs. Review verifies the task's definition of ready and the requested documentation change. The task's behavioral AC describe the future implementation, and the planning PR does not claim them as delivered.

### Completed tasks

A completed task stays at its stable path with `status: done` and leaves active task selection. It records what was delivered at that time and does not override later requirements or current governing documents. A later fix or extension gets a new task linked to the earlier work.

## 4. Implementation and AI review

An implementation PR identifies its task and describes the change, its test evidence, deviations, and risks as in [Pull request description](#pull-request-description). Implementation and review must follow the [architecture](docs/architecture.md), [DDD rules](docs/ddd.md), [coding standards](docs/coding-standards.md), accepted ADRs, [domain dictionary](docs/dictionary.md), and [testing strategy](docs/testing-strategy.md).

### Branches and PRs

Every change reaches `main` through a PR from a separate branch, never by a direct push. Create the branch from an up-to-date `main`. Name a task branch `<type>/<task-id>-<short-description>` in lowercase, for example `feat/tm-003-signal-intake`, and any other branch `<type>/<short-description>`, for example `docs/update-branch-rules`. The type is the Conventional Commits type of the change, and the description is in kebab case.

Commits on a task branch and the PR title use `<type>: <TASK-ID> <description>`, for example `test: TM-003 reject a trade signal without an instrument`. Automatic merge commits are exempt. A planning PR and its commits name every task they add, as a range for consecutive IDs (`TM-007..TM-010`) or a list. A task-change PR names the changed task. A change without a task carries no ID.

An implementation PR may change one task file: from `approved` to `done` for the task it implements, plus a [task adaptation found in review](#task-adaptation-found-in-review) that the owner approved. Every other requirement change goes through a separate task-change PR. After it merges, update the implementation branch and repeat CI and independent review. An implementation must not weaken governing documents or shared requirements to make its own work pass.

A change without a Markdown task happens only when the owner asks for it directly. The PR description then states the requirements in place of the task, and the gates in section 5 apply. With no AC, the AC and red evidence gates have nothing to check.

### Pull request description

An implementation PR description contains, in this order:

1. the task ID with a link to the task file;
2. a Summary of at most five bullets;
3. a table of AC, test, and red commit SHA;
4. Deviations;
5. Risks.

Command output appears only where an AC requires the PR to show it, inside `<details>`.

### Test-first implementation

Every AC verified by an automated test follows one path, in every task:

1. Write the test and a minimal stub, then run the test. It must fail on an assertion about the required behavior. A failure from a missing class, an undefined step, a syntax error, or test setup is not yet red.
2. Commit the red test on the task branch as `test: <TASK-ID> <description>`. This is the red commit.
3. Write the production code until the test passes, and commit it.
4. Record any later change to that test's assertions in the PR's deviations, with the reason.

When one AC lists several behaviors, each behavior gets its own red commit.

A new test that passes on its first run is not evidence yet. Break the behavior temporarily, confirm that the test fails, restore the behavior, and say so in the PR.

Documentation, CI command, tool output, and manual check AC have no test, so this path does not apply to them. To learn a library first, write a throwaway spike and keep it out of the PR.

For each AC on this path, the PR lists the test and its red commit SHA. Keep red commits in the branch history and refresh the SHAs if a rebase changes them. Merge commits keep them on `main`, so use `git bisect --first-parent` there.

### Independent review

The independent reviewer must not modify code. Its inputs are the approved task and linked shared requirements, project documents, the diff, `base SHA`, `head SHA`, and the owner's PR comments that approve or decline a [task adaptation](#task-adaptation-found-in-review). It does not read other PR comments or the PR description.

The independent review uses the `code-review` skill from Matt Pocock's [mattpocock/skills](https://github.com/mattpocock/skills), `mattpocock-skills:code-review` in Claude Code, with its Standards and Spec axes run one after the other. Claude Code's built-in `code-review` skill, including `/code-review ultra`, is not used for it. [docs/agents/skills.md](docs/agents/skills.md#code-review) gives the version and the steps for a tool that lacks the skill.

For an implementation PR, the reviewer:

1. Confirms that the branch includes current `main`.
2. Reads the approved task and linked shared requirements from `base SHA`, rather than the branch's proposed version. After an adaptation the owner approved, the adapted task at `head SHA` is the spec source.
3. Compares task files between base and head. Only the `approved` to `done` change for the implemented task and an adaptation the owner approved are permitted. Any other change to the task, a change to another task file, or a task that is not `approved` at base produces `FAIL`. A missing task also produces `FAIL`, unless the PR uses the [owner-requested path](#branches-and-prs).

For every rule, script, tool configuration, or generic helper that the change adds, including tooling the task prescribes, the reviewer checks whether an installed package or tool, or a configuration of one, does the same job. Such a finding is at least `MAJOR`. When the task prescribes the custom tooling, the finding recommends adapting the task.

Nothing technically blocks task-file writes in the MVP: independent review and the owner's merge check apply this comparison. A CI check may enforce it later, subject to separate approval.

`PASS` requires verification of all applicable AC and no open `BLOCKER` or `MAJOR` findings. A finding whose adaptation the owner declined counts as accepted and no longer blocks `PASS`. An unmet or unverifiable AC produces `FAIL`; the explanation must distinguish a defect from missing evidence. Missing or unreproducible red evidence counts as missing evidence. There is no intermediate review result. A failed review run or missing report blocks merge.

A change to `head SHA` or to the requirements under section 2 invalidates the earlier result, except for the [status-only change](#status-only-change-after-review). Updating the branch against `main` follows section 5.

#### Review scope

One rule applies to every PR, whatever its task: the content of the diff between base and head sets the review scope.

- A diff that changes only Markdown documentation, with no code, tests, configuration, dependencies, or CI files, gets a documentation review. Only files under `.claude/` and skill files count as agent configuration. AGENTS.md, CLAUDE.md, `docs/agents/*.md`, task files, and other Markdown files count as documentation.
- A documentation review checks the AC of an implementation PR or the definition of ready of a planning PR, compares task files, and checks consistency with AGENTS.md and the documents it makes mandatory. It skips the Standards axis for code, the red-evidence check, and test runs.
- Every other PR gets the full independent review.

Both scopes use the same report, which states the scope in its result line. CI and the `PASS` merge gate apply to both.

#### Task adaptation found in review

A review finding may show that the approved task itself causes the problem, for example a task that prescribes custom tooling an installed tool could replace. The reviewer then recommends "adapt the task" and proposes the change to the task's requirements or AC.

- The owner decides. Without the owner's decision the agent changes nothing, as after any `FAIL`.
- The owner approves in a PR comment that starts with `Task adaptation approved:` and names the proposed change, or declines in a PR comment that starts with `Task adaptation declined:` and gives the reason. Only the owner writes these comments; agents never post them.
- After approval, the implementing agent changes the task file in the same PR, in its own commit `docs: <TASK-ID> adapt the task after review`, and then changes the code. The PR description lists the task change under Deviations, with a link to the owner's approval.
- After a decline, the task and the code stay as they are. The PR description lists the declined finding and the reason under Deviations. The finding counts as accepted, and a later review of the same PR does not report it again.
- The next independent review assesses the adapted task at `head SHA`. It checks that the task diff between base and head contains only the `approved` to `done` change and the approved adaptation.
- A requirement change that the review did not find still goes through a separate task-change PR (section 2).

The Deviations entries are for the owner's check at merge; the reviewer does not read them.

#### Review report

The reviewer posts the report as one PR comment. Its visible part has at most 10 lines:

```
## Independent review: PASS|FAIL · <full|documentation> review
<TASK-ID> · base <short-sha> · head <short-sha> · task files <OK|issue> · reviewer: <tool> <model> <skill>
AC <passed>/<assessed> PASS · red evidence <ok>/<checked> OK
- <BLOCKER|MAJOR> <file:line>: <one-line reason>
- AC <n> FAIL (<defect|missing evidence>): <one-line reason>
Recommendation: <one action>
```

A collapsed `<details>` section holds everything else this section requires:

- the AC table with the text, `PASS` or `FAIL` result, and evidence for each assessed AC, including applicable AC from linked shared requirements, or the reason why an AC could not be verified;
- the red-evidence results: for each AC on the [test-first path](#test-first-implementation), its red commit, whether the test fails on an assertion at that commit, and whether later changes to its assertions appear in the PR's deviations;
- `MINOR` findings, with reasons;
- the task-file comparison.

A change without a task shows the direct-change note in place of the task ID. A documentation review shows `red evidence n/a`.

#### Review flow

- MVP: the implementing agent starts the review, and the owner checks the report. A positive result is a mandatory manual control.
- V2: a `pull_request` workflow starts an independent AI agent in an isolated environment to perform code review. An adapter records the agent's structured report and a required check for `head SHA`. Automation must also invalidate the assessment when the applicable task or shared requirements change on `main`, even without a code change on the branch. The same result and validity rules apply as in the MVP.

In the MVP:

- Invoking `implement` on an approved task, or `to-tickets`, authorizes the agent to push the branch, open the PR, and start the independent review. Agents still never merge.
- `implement` runs the pull-request checks from [README](README.md#quality-checks) before pushing. It does not end with a self-review.
- The implementing agent starts exactly one reviewer subagent of its own tool, with a fresh context. Its prompt contains only the PR number, the task ID or the direct-change note, `base SHA`, and `head SHA`.
- The reviewer follows [the reviewer instruction](docs/agents/independent-review.md), posts its report as a PR comment with `gh pr comment`, and returns the result and recommendation. The implementing agent relays them without editing.
- After the reviewer returns, the implementing agent checks that the checked-out ref is unchanged and `git status --short` is empty. A difference makes the report invalid, and the agent tells the owner.
- After `FAIL`, the agent changes nothing until the owner asks. After every change to `head SHA` that the owner asks for, including an update from `main`, the agent starts a new review without asking again.
- When the reviewer cannot run or does not return a report, for example because of an exhausted quota or a tool error, the agent stops and tells the owner in one line. It does not switch to another reviewer. A missing report still blocks merge.
- An independent review that the owner starts in a separate session remains valid. Its reviewer also posts the report as a PR comment.

An automatic fix loop is outside the MVP.

#### Status-only change after review

A `PASS` stays valid when the only changes after the reviewed `head SHA` set `status: draft` to `approved` in tasks that review assessed. The agent checks this with `git diff <reviewed-sha> HEAD` and adds one line to the PR: "Since `<sha>` only task status changed; PASS holds." Any other change invalidates the result.

## 5. Definition of done and merge gates

- [ ] All applicable AC are met and verified; agreed changes to scope and AC reached `main` through a task-change PR, or through a [task adaptation found in review](#task-adaptation-found-in-review) that the owner approved in this PR.
- [ ] Every AC on the [test-first path](#test-first-implementation) has its red evidence in the PR.
- [ ] An implementation PR's task-file changes are limited to the permitted `approved` to `done` change and an adaptation the owner approved.
- [ ] The task branch includes the current state of `main` at merge time.
- [ ] Required PR CI passes for the current change.
- [ ] Independent AI review has a `PASS` result for the current `head SHA` and requirements, or one that holds under the [status-only exception](#status-only-change-after-review).
- [ ] A human has approved the current PR and its documented deviations.
- [ ] Merge takes place through a PR into protected `main`; agents must not merge or bypass protections.

If `main` changes before merge, including through a task change, update the task branch, resolve any conflicts, and rerun CI and independent review for the new `head SHA`. This applies even when the changes appear unrelated to the task. Each further change to `main` before merge requires another update and validation.

## 6. CI

The [testing strategy](docs/testing-strategy.md) defines test scope. [README](README.md#quality-checks) describes the current checks and commands. AI review does not replace CI. Changes to CI policies require separate approval from the owner.

PR CI runs Infection on the lines a PR changes; the [testing strategy](docs/testing-strategy.md#mutation-testing-with-infection) describes the check. Full CI, which runs on pushes to `main` and on manual dispatch, runs Infection on all configured sources. The local equivalents are `CI_WITH_MUTATION=diff make ci` and `make ci`.

### Changes to CI configuration

For changes to workflows, CI scripts, or their Docker configuration:

1. Run `make ci-lint`.
2. If the change affects pipeline execution, run the affected CI variant locally. Run both only when the change affects both variants.
3. After pushing, check the result of the normal GitHub Actions run.

## 7. CD (planned)

CD has not been implemented. The planned path is GitHub Actions → GitHub Container Registry (GHCR) → VPS. GitHub Actions will prepare the application image, publish it to GHCR, and control deployment of that image to the VPS.

Deployment must use an approved revision from protected `main`, referenced directly or by a release tag. Full CI must pass for the exact SHA being deployed. A merge, a tag, or successful CI for a different SHA is insufficient. Failure, cancellation, a missing result, or a skipped required check must block deployment.

The CD design must trace the image and deployment to the code revision and support rollback. Deployment secrets must remain unavailable to agents and PR workflows. Workflow and VPS configuration details will be defined when CD is implemented.

## 8. Adoption and further development

This contract governs the project workflow. `AGENTS.md`, `CLAUDE.md`, README, and the testing strategy reference it for agent instructions and delivery requirements.

The `Protect main` GitHub Ruleset currently requires a PR and the required CI check, and blocks force pushes and deletion of `main`. It does not yet enforce branch freshness or human approval, so the person merging checks those two gates. The target Ruleset must enforce the PR, branch freshness, CI, and human approval requirements in section 5 and restrict bypass. Agents must not change protections.

The MVP assigns one implementer to each task. The next stages are automated review, described as V2 in section 4, followed by parallel tasks in separate worktrees with explicit dependencies and integration validation.
