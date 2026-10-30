# SDLC

- Version: 1.1
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
| Implementation | An agent implements an Issue on a separate branch and opens a PR. |
| Validation and review | CI and an independent reviewer assess the current change against the Issue's requirements. |
| Merge | A human approves the merge once the gates in section 5 are met. |
| Deployment (planned) | GitHub Actions publishes an image to GHCR and deploys it to the VPS after full CI passes for the revision being deployed. |

## 2. Requirements and source of truth

The working specification helps define tasks. It stays local and does not enter the repository. Transfer every decision needed for implementation and verification into the task before publication.

Before publication, the Markdown task is authoritative. After publication, the GitHub Issue is the complete task specification and the source of its status. Implementation, PRs, and review use the Issue and the project's governing documents. They must not require access to working files or depend on those files being present on `main`. Requirements shared by several tasks must appear in each task or in an explicitly linked parent Issue.

A retained Markdown task records its preparation. Later edits to that file do not change the Issue; there is no two-way synchronization.

The project author or the designated person responsible for the task changes and approves its scope and acceptance criteria (AC). Their edits to the Issue's requirements constitute approval. An agent may propose changes, but the person responsible for the task decides whether to accept them.

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

A task contains a stable ID, goal, scope and exclusions, requirements, constraints, significant decisions, AC, dependencies, and a verification method. The `spec` field is optional.

### Definition of ready

Before setting `approved`:

- [ ] The outcome, scope, and exclusions are clear.
- [ ] AC are testable and have a defined verification method.
- [ ] Dependencies, constraints, and significant risks are documented.
- [ ] The task includes all necessary decisions from the working specification, with a link to shared requirements in a parent Issue where applicable.
- [ ] A human has approved the complete content intended for publication.

Approval must cover the final content sent to GitHub Issues, including any edits made to the Markdown before publication. The `approved` status records a human decision. Agents must not approve tasks or start publication themselves.

### Publication

Planned command: `./scripts/publish-issues.sh [TASK-ID]`. Without an argument, it publishes all approved tasks. The script has not been implemented yet.

The script must:

1. Publish only `approved` tasks and validate required fields. A human assesses the substance of the definition of ready; the script must not assign `approved`.
2. Create an Issue with the full task content, `TASK-ID`, and a link to a parent Issue where applicable.
3. Include `TASK-ID` in the title and check both open and closed Issues before creating a new one.
4. On success, save `github_issue: <number>` and `status: published`. On retry, recover the existing association without creating a duplicate or overwriting the Issue with Markdown content.
5. Report failures and never mark an unpublished task as `published`.

Publication runs must not overlap in the MVP. The `published` status records publication, not completion of implementation. GitHub Issues track subsequent progress.

## 4. Implementation and AI review

A PR includes its Issue link, a description of the changes, test results, deviations, and risks. Implementation and review must follow the [architecture](docs/architecture.md), [DDD rules](docs/ddd.md), accepted ADRs, [domain dictionary](docs/dictionary.md), and [testing strategy](docs/testing-strategy.md).

The independent reviewer must not modify code. Its inputs are the current Issue, any shared requirements from a parent Issue, project documents, the diff, `base SHA`, and `head SHA`.

The review report goes into the PR and contains:

- the Issue reference and reviewed `base SHA` and `head SHA`;
- a result of `PASS` (positive) or `FAIL` (negative);
- findings classified as `BLOCKER`, `MAJOR`, or `MINOR`, with reasons;
- the text of each assessed AC, including applicable AC from a parent Issue, and its `PASS` or `FAIL` result, with evidence or a reason why it could not be verified.

`PASS` requires verification of all applicable AC and no open `BLOCKER` or `MAJOR` findings. An unmet or unverifiable AC produces `FAIL`; the explanation must distinguish a defect from missing evidence. There is no intermediate review result. A failed review run or missing report blocks merge.

A change to `head SHA` or to the requirements under section 2 invalidates the earlier result. Updating the branch against `main` follows section 5.

- MVP: a human starts review in a separate agent session and checks the report. A positive result is a mandatory manual control.
- V2: a `pull_request` workflow starts an independent AI agent in an isolated environment to perform code review. An adapter records the agent's structured report and a required check for `head SHA`. Automation must also invalidate the assessment when applicable Issue requirements change, even without a code change. The same result and validity rules apply as in the MVP.

An automatic fix loop is outside the MVP.

## 5. Definition of done and merge gates

- [ ] All applicable AC are met and verified; agreed changes to scope and AC are recorded in the Issue.
- [ ] The task branch includes the current state of `main` at merge time.
- [ ] Required PR CI passes for the current change.
- [ ] Independent AI review has a `PASS` result for the current `head SHA` and requirements.
- [ ] A human has approved the current PR and its documented deviations.
- [ ] Merge takes place through a PR into protected `main`; agents must not merge or bypass protections.

If `main` changes before merge, update the task branch, resolve any conflicts, and rerun CI and independent review for the new `head SHA`. This applies even when the changes appear unrelated to the task. Each further change to `main` before merge requires another update and validation.

## 6. CI

The [testing strategy](docs/testing-strategy.md) defines test scope. [README](README.md#quality-checks) describes the current checks and commands. AI review does not replace CI. Changes to CI policies require separate approval from the owner.

Current PR CI skips Infection. Full CI on `main` and manual runs includes Infection. Their local equivalents are `CI_WITH_MUTATION=0 make ci` and `make ci`.

### Changes to CI configuration

For changes to workflows, CI scripts, or their Docker configuration:

1. Run `make ci-lint`.
2. If the change affects pipeline execution, run the affected CI variant locally. Run both only when the change affects both variants.
3. After pushing, check the result of the normal GitHub Actions run.

## 7. CD (planned)

CD has not been implemented. The planned path is GitHub Actions → GitHub Container Registry (GHCR) → VPS. GitHub Actions will prepare the application image, publish it to GHCR, and control deployment of that image to the VPS.

Deployment must use an approved revision from protected `main`, referenced directly or by a release tag. Full CI must pass for the exact SHA being deployed. A merge, a tag, or successful CI for a different SHA is insufficient. Failure, cancellation, a missing result, or a skipped required check must block deployment.

The CD design must trace the image and deployment to the code revision and support rollback. Production secrets must remain unavailable to agents and PR workflows. Workflow and VPS configuration details will be defined when CD is implemented.

## 8. Adoption and further development

This contract governs the project workflow. `AGENTS.md`, `CLAUDE.md`, README, and the testing strategy reference it for agent instructions and delivery requirements.

The target GitHub Ruleset must enforce the PR, branch freshness, CI, and human approval requirements in section 5 and restrict bypass. This is a requirement to implement; it does not confirm the repository's current configuration. Agents must not change protections.

The MVP assigns one implementer to each task. The next stages are automated review, described as V2 in section 4, followed by parallel tasks in separate worktrees with explicit dependencies and integration validation.
