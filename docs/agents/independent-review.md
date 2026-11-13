# Independent review

This instruction is the procedure for any agent tool that performs the [independent review](../../SDLC.md#independent-review) of a pull request. SDLC §4 holds the review policy: the inputs, the [scope](../../SDLC.md#review-scope), [incremental review](../../SDLC.md#incremental-review), [finding severity](../../SDLC.md#finding-severity), [task adaptation](../../SDLC.md#task-adaptation-found-in-review), and the [report](../../SDLC.md#review-report). The implementing agent starts the reviewer as described in the [review flow](../../SDLC.md#review-flow), and its prompt gives the PR number, the task ID or the direct-change note, the base SHA, and the head SHA. The owner may start the same review in a separate session.

## Rules

- Do not edit, create, or delete repository files. Do not commit or push.
- Start no agents. Where a skill runs parallel agents, such as the two axes of Matt Pocock's `code-review`, run them one after the other.
- Use Matt Pocock's `code-review` skill, `mattpocock-skills:code-review` in Claude Code, at the version that [skills.md](skills.md#code-review) names. Never use Claude Code's built-in `code-review` skill or `/code-review ultra`.
- Read this file, AGENTS.md, the documents it makes mandatory, and `docs/agents/` at the base SHA with `git show <base-sha>:<path>`, never from the checkout. Their head versions are part of the change under review (SDLC §4).
- Read only the inputs that [SDLC §4](../../SDLC.md#independent-review) names, and nothing else from the PR's comments or description.
- Memory notes of any agent tool are neither requirements nor evidence. Use only the task, repository documents, the inputs SDLC §4 names, and what you verify.
- Set the review scope from the diff alone, never from the task prefix or PR type.
- Check red evidence on the main working tree, because the Docker Compose container mounts it. Return to the starting ref with an empty `git status --short`.
- Laravel's destructive database commands, such as `migrate:fresh` and `db:wipe`, run only against the test database `trade_maneuver_testing`; `AppServiceProvider` refuses them elsewhere. Run raw `TRUNCATE` statements and `psql` commands only against the test database as well. Pass the test environment that `bin/ci` sets as literal `-e` options in the same command or through a script that sets them, not through an unquoted shell variable: zsh does not split it into words, so the options are lost.

## Steps

1. Record the starting ref with `git rev-parse --abbrev-ref HEAD` and `git rev-parse HEAD`, and confirm that `git status --short` is empty. If it is not, return that the review cannot run.
2. Confirm with `gh pr view <PR> --json headRefOid` that the PR head matches the head SHA in the prompt. If it does not, return that the review cannot run.
3. Run `git fetch origin`. Confirm that `git merge-base origin/main <head-sha>` is the base SHA in the prompt; if it is not, return that the review cannot run. Confirm with `git merge-base --is-ancestor origin/main <head-sha>` that the branch includes current `main`; if it does not, stop and ask for the branch to be updated.
4. Read the rules at the base SHA, as the Rules above say.
5. Read the spec source:
   - an implementation PR: the approved task and linked shared requirements with `git show <base-sha>:docs/tasks/<TASK-ID>.md`, or the adapted task with `git show <head-sha>:docs/tasks/<TASK-ID>.md` after an adaptation the owner approved;
   - a change without a task: the requirements in the PR description;
   - a planning or task-change PR: the SDLC §3 definition of ready and the requested documentation change.

   Apart from that adapted task, the branch's version of a task and `docs_internal/specs/` are never spec sources. The standards sources are AGENTS.md and the documents it makes mandatory.
6. Read the owner's adaptation comments: `gh pr view <PR> --json comments --jq '.comments[] | select(.body | startswith("Task adaptation approved:") or startswith("Task adaptation declined:")) | .url + "\n" + .body'`. Do not report again a finding whose adaptation the owner declined.
7. Decide between an incremental and a full review. Read the latest report with `gh pr view <PR> --json comments --jq '[.comments[] | select(.body | startswith("## Independent review:"))] | last | {url, includesCreatedEdit, body}'`. Its first line gives the result, and its second line the old base and the reviewed head SHA. Use it as the starting point only when:
   - its result is `PASS`, and its result line follows the SDLC §4 format;
   - `includesCreatedEdit` is `false`;
   - `git rev-parse --verify <reviewed-sha>^{commit}` resolves the reviewed head SHA;
   - none of the full-review cases in SDLC §4 applies. The requirements changed when `git diff <old-base> <base-sha>` or `git diff <reviewed-sha> <head-sha>` changes the task outside its own planning or task-change PR, or changes linked shared requirements, or when the PR description of a change without a task was edited after the report. An update from `main` changed a governing document when `git diff --name-only <old-base> <base-sha>` lists AGENTS.md, a document it makes mandatory, or a file under `docs/agents/`.

   Otherwise run a full review and state the reason in the report.
8. Set the scope with `git diff --name-only <base-sha> <head-sha>`. It is a documentation review when every changed path ends in `.md`, none is under `.claude/`, and none is a skill file (`SKILL.md` or a file in a skill's directory). Otherwise it is a full review.
9. Review the change with Matt Pocock's `code-review` skill where your tool has it, otherwise with the same steps, as its [adaptation](skills.md#code-review) describes. Use the spec source from step 5 in place of the skill's spec search. A full review runs the Standards and Spec axes one after the other against `git diff <base-sha> <head-sha>`. A documentation review checks the AC of an implementation PR or the definition of ready of a planning PR, compares task files, and checks consistency with AGENTS.md and the documents it makes mandatory; it skips the Standards axis for code, the red-evidence check, and test runs. An incremental review uses the fixed point and diff that the adaptation sets and checks how that change interacts with the rest of the PR.
10. For an implementation PR, compare task files with `git diff <base-sha> <head-sha> -- docs/tasks/` and apply the SDLC §4 rule on permitted task-file changes, including the ticked AC. Report any change to governing documents or linked shared requirements as a finding.
11. In a full review, check the red evidence of each AC on the test-first path: check out its red commit, run the listed test with the narrowest command, confirm that it fails on an assertion, and return to the starting ref. Compare `git diff <red-sha> <head-sha> -- tests/` with the PR's deviations. In an incremental review, do this only for AC whose red commit SHA differs from the reviewed report's red-evidence results, or whose test assertions `git diff <reviewed-sha> <head-sha> -- tests/` changes.
12. Write the report in the [SDLC §4 format](../../SDLC.md#review-report), with one overall result in place of the skill's side-by-side summary and its rule against picking a single result. Give the scope and, for an incremental review, `incremental since <short-sha>` in its result line, the tool, model, and skill in its `reviewer:` field, and one recommended action.
13. Post the report as one comment with `gh pr comment <PR> --body-file -`, passing the body on standard input, whether the implementing agent or the owner started the review.
14. Check out the starting ref again and confirm that `git status --short` is empty.
15. Return only the result, the number of `BLOCKER` and `MAJOR` findings, the recommendation, and the comment URL.
