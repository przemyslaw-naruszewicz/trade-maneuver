# SDLC

- Status: adopted process contract. Planned automation is identified below.
- Model: human supervision, task preparation in Markdown, delivery through GitHub Issues.
- Agents: Codex or Claude Code, used interchangeably.

Each skill, agent, or tool is a replaceable implementation of a process stage. A replacement must preserve the inputs, output artifacts, approval rules, and quality gates required by this contract.

Examples from Matt Pocock's [mattpocock/skills](https://github.com/mattpocock/skills) repository: `grill-with-docs → to-spec → to-tickets → implement / tdd → code-review`. Open Mercato or other tools can fill the same roles under this contract.

Cost efficiency is a process requirement. Tool selection and automation must limit costs and unnecessary use of AI quotas while preserving the required tests and reviews.

Agent automation, including V2 review, must use the AI provider's subscription plans without separately billed model API calls. Choose an execution method that fits those plans' capabilities and limits. Exhausting a quota must not trigger a switch to paid API calls or skip a quality gate.

## 1. Workflow

`Discovery → Working spec → Markdown tasks → Approval → Issue publication → Implementation/PR → CI + AI review → Merge → Full CI → CD/VPS`

| Stage | Action or outcome |
| --- | --- |
| Discovery | Establish the goal, scope, constraints, and risks. |
| Working spec | The agent prepares a temporary local specification outside the repository. |
| Markdown tasks | The agent creates `docs/tasks/<TASK-ID>.md` with an initial status of `draft`. |
| Approval | A human reviews tasks and manually changes selected tasks to `approved`. No planning PR is required. |
| Publication | A human starts publication of approved tasks to GitHub Issues. |
| Implementation | An agent implements an Issue [test-first](#test-first-implementation) on a [separate branch](#branches-and-prs) and opens a PR. |
| Validation and review | CI and an independent reviewer assess the current change against the Issue's requirements. |
| Merge | A human approves the merge once the gates in section 5 are met. |
| Deployment (planned) | GitHub Actions publishes an image to GHCR and deploys it to the VPS after full CI passes for the revision being deployed. |

## 2. Requirements and source of truth

The working specification helps define tasks. It stays local and does not enter the repository. Transfer every decision needed for implementation and verification into the task before publication.

Before publication, the Markdown task is authoritative. After publication, the GitHub Issue is the complete task specification and the source of its status. Implementation, PRs, and review use the Issue and the project's governing documents. They must not require access to working files or depend on those files being present on `main`. Requirements shared by several tasks must appear in each task or in an explicitly linked parent Issue.

A retained Markdown task records its preparation. Later edits to that file do not change the Issue; there is no two-way synchronization.

The project author or the designated person responsible for the task changes and approves its scope and acceptance criteria (AC). Their edits to the Issue's requirements count as approval. An agent may propose changes, but the person responsible for the task decides whether to accept them.

A substantive change requires updating the implementation and repeating review. It invalidates an earlier `PASS`, even if the code has not changed. This also applies to changes in a parent Issue's shared requirements when they affect the task. Editorial corrections that leave the meaning unchanged do not invalidate review.

The person responsible records any exception that changes scope or AC in the Issue. Review then assesses the revised requirements. Approval of an exception does not replace a `PASS` result.

## 3. Task preparation, approval, and publication

Minimal frontmatter for `docs/tasks/AUTH-002.md`:

```yaml
---
id: AUTH-002
status: draft        # draft | approved | published
github_issue: null
---
```

A task contains a stable ID, goal, scope and exclusions, requirements, constraints, significant decisions, AC, dependencies, and a verification method. The `spec` and `parent_issue` fields are optional; `parent_issue` holds the number of a parent Issue with shared requirements.

### Task slicing

Slice tasks so that their delivery forms milestones and the running application shows incremental development and the gradual delivery of value.

- A milestone is a group of tasks that ends in a result that can be shown in the running application. A result that only tests can show does not end a milestone.
- A single task need not show a result on its own, for example when it is prefactoring, but its milestone must.
- Milestones may stay implicit. They need no field, label, or document.

### Definition of ready

Before setting `approved`:

- [ ] The outcome, scope, and exclusions are clear.
- [ ] AC are testable and have a defined verification method. An AC verified by an automated test names its [test level](docs/testing-strategy.md#seams) and, where the behavior calculates or maps a value, gives concrete expected values taken from the requirements.
- [ ] Dependencies, constraints, and significant risks are documented.
- [ ] The task includes all necessary decisions from the working specification, with a link to shared requirements in a parent Issue where applicable.
- [ ] The task follows [task slicing](#task-slicing): it belongs to a milestone that ends in a result the running application can show.
- [ ] A human has approved the complete content intended for publication.

Approval must cover the final content sent to GitHub Issues, including any edits made to the Markdown before publication. The `approved` status records a human decision. Agents must not approve tasks or start publication themselves.

### Publication

Command: `./scripts/publish-issues.sh [--dry-run] [TASK-ID]`. Without an argument, it publishes all approved tasks. It requires an authenticated GitHub CLI (`gh`). `--dry-run` validates approved tasks and prints the Issues it would create, without contacting GitHub or changing files. Relative links in a task become links to the files on `main`, because relative links do not resolve in an Issue.

The script must:

1. Publish only `approved` tasks and validate required fields. A human assesses the substance of the definition of ready; the script must not assign `approved`.
2. Create an Issue with the full task content, `TASK-ID`, and a link to a parent Issue where applicable.
3. Include `TASK-ID` in the title and check both open and closed Issues before creating a new one.
4. On success, save `github_issue: <number>` and `status: published`. On retry, recover the existing association without creating a duplicate or overwriting the Issue with Markdown content.
5. Report failures and never mark an unpublished task as `published`.

Publication runs must not overlap in the MVP; the script refuses to start while another run holds its lock. The `published` status records publication, not completion of implementation. GitHub Issues track subsequent progress.

## 4. Implementation and AI review

A PR includes its Issue link, a description of the changes, test results, deviations, and risks. Implementation and review must follow the [architecture](docs/architecture.md), [DDD rules](docs/ddd.md), [coding standards](docs/coding-standards.md), accepted ADRs, [domain dictionary](docs/dictionary.md), and [testing strategy](docs/testing-strategy.md).

### Branches and PRs

Every change reaches `main` through a PR from a separate branch, never by a direct push. Create the branch from an up-to-date `main` and name it `<type>/<short-description>`: the Conventional Commits type of the change and a kebab-case description, for example `docs/update-branch-rules`.

A change without an Issue happens only when the owner asks for it directly. The PR description then states the requirements in place of the Issue, and the gates in section 5 apply. With no AC, the AC and red evidence gates have nothing to check.

### Test-first implementation

Every AC verified by an automated test follows one path, in every task:

1. Write the test and a minimal stub, then run the test. It must fail on an assertion about the required behavior. A failure from a missing class, an undefined step, a syntax error, or test setup is not yet red.
2. Commit the red test on the task branch as `test: <description>`. This is the red commit.
3. Write the production code until the test passes, and commit it.
4. Record any later change to that test's assertions in the PR's deviations, with the reason.

When one AC lists several behaviors, each behavior gets its own red commit.

A new test that passes on its first run is not evidence yet. Break the behavior temporarily, confirm that the test fails, restore the behavior, and say so in the PR.

Documentation, CI command, tool output, and manual check AC have no test, so this path does not apply to them. To learn a library first, write a throwaway spike and keep it out of the PR.

For each AC on this path, the PR lists the test and its red commit SHA. Keep red commits in the branch history and refresh the SHAs if a rebase changes them. Merge commits keep them on `main`, so use `git bisect --first-parent` there.

### Independent review

The independent reviewer must not modify code. Its inputs are the current Issue, any shared requirements from a parent Issue, project documents, the diff, `base SHA`, and `head SHA`.

The review report goes into the PR and contains:

- the Issue reference and reviewed `base SHA` and `head SHA`;
- a result of `PASS` (positive) or `FAIL` (negative);
- findings classified as `BLOCKER`, `MAJOR`, or `MINOR`, with reasons;
- the text of each assessed AC, including applicable AC from a parent Issue, and its `PASS` or `FAIL` result, with evidence or a reason why it could not be verified;
- for each AC on the [test-first path](#test-first-implementation), its red commit, whether the test fails on an assertion at that commit, and whether later changes to its assertions appear in the PR's deviations.

`PASS` requires verification of all applicable AC and no open `BLOCKER` or `MAJOR` findings. An unmet or unverifiable AC produces `FAIL`; the explanation must distinguish a defect from missing evidence. Missing or unreproducible red evidence counts as missing evidence. There is no intermediate review result. A failed review run or missing report blocks merge.

A change to `head SHA` or to the requirements under section 2 invalidates the earlier result. Updating the branch against `main` follows section 5.

- MVP: a human starts review in a separate agent session and checks the report. A positive result is a mandatory manual control.
- V2: a `pull_request` workflow starts an independent AI agent in an isolated environment to perform code review. An adapter records the agent's structured report and a required check for `head SHA`. Automation must also invalidate the assessment when applicable Issue requirements change, even without a code change. The same result and validity rules apply as in the MVP.

An automatic fix loop is outside the MVP.

## 5. Definition of done and merge gates

- [ ] All applicable AC are met and verified; agreed changes to scope and AC are recorded in the Issue.
- [ ] Every AC on the [test-first path](#test-first-implementation) has its red evidence in the PR.
- [ ] The task branch includes the current state of `main` at merge time.
- [ ] Required PR CI passes for the current change.
- [ ] Independent AI review has a `PASS` result for the current `head SHA` and requirements.
- [ ] A human has approved the current PR and its documented deviations.
- [ ] Merge takes place through a PR into protected `main`; agents must not merge or bypass protections.

If `main` changes before merge, update the task branch, resolve any conflicts, and rerun CI and independent review for the new `head SHA`. This applies even when the changes appear unrelated to the task. Each further change to `main` before merge requires another update and validation.

## 6. CI

The [testing strategy](docs/testing-strategy.md) defines test scope. [README](README.md#quality-checks) describes the current checks and commands. AI review does not replace CI. Changes to CI policies require separate approval from the owner.

PR CI currently skips Infection. Full CI, which runs on pushes to `main` and on manual dispatch, includes it. The local equivalents are `CI_WITH_MUTATION=0 make ci` and `make ci`.

Planned: PR CI runs Infection on the lines a PR changes, with the local equivalent `CI_WITH_MUTATION=diff make ci`. The [testing strategy](docs/testing-strategy.md#mutation-testing-with-infection) describes the check.

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
