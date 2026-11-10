# Independent review

This instruction applies to any agent tool that performs the [independent review](../../SDLC.md#independent-review) of a pull request. The implementing agent starts the reviewer as described in the [review flow](../../SDLC.md#review-flow), and its prompt gives the PR number, the task ID or the direct-change note, the base SHA, and the head SHA. The owner may start the same review in a separate session.

## Rules

- Do not edit, create, or delete repository files. Do not commit or push.
- Start no agents. Where a skill runs parallel agents, such as the two axes of Matt Pocock's `code-review`, run them one after the other.
- Use Matt Pocock's `code-review` skill, `mattpocock-skills:code-review` in Claude Code, at the version that [skills.md](skills.md#code-review) names. Never use Claude Code's built-in `code-review` skill or `/code-review ultra`.
- Read AGENTS.md and the documents it makes mandatory, then the approved task from the base SHA, as the [code-review adaptation](skills.md#code-review) describes. After an adaptation the owner approved, read the adapted task from the head SHA.
- Besides the inputs in SDLC §4, read only the owner's PR comments that approve or decline a [task adaptation](../../SDLC.md#task-adaptation-found-in-review). Do not read other PR comments. From the PR description, read only the requirements of a change without a task and, in a full review, the AC/test/red-commit table and the Deviations entries on test changes.
- Memory notes of any agent tool are neither requirements nor evidence. Use only the task, repository documents, the owner's adaptation comments, and what you verify.
- Set the [review scope](../../SDLC.md#review-scope) from the diff alone, never from the task prefix or PR type.
- Check red evidence on the main working tree, because the Docker Compose container mounts it. Return to the starting ref with an empty `git status --short`.
- Laravel's destructive database commands, such as `migrate:fresh` and `db:wipe`, run only against the test database `trade_maneuver_testing`; `AppServiceProvider` refuses them elsewhere. Run raw `TRUNCATE` statements and `psql` commands only against the test database as well. Pass the test environment that `bin/ci` sets as literal `-e` options in the same command or through a script that sets them, not through an unquoted shell variable: zsh does not split it into words, so the options are lost.
- For every rule, script, tool configuration, or generic helper that the change adds, including tooling the task prescribes, check whether an installed package or tool, or a configuration of one, does the same job. Report such a finding as at least `MAJOR`. When the task prescribes the tooling, recommend "adapt the task" and give the proposed change to its requirements or AC.
- Do not report again a finding whose adaptation the owner declined; it counts as accepted.
- Write the report in the [SDLC §4 format](../../SDLC.md#review-report), with the tool, model, and skill in its `reviewer:` field, the scope in its result line, and one recommended action.
- Post the report as a PR comment, whether the implementing agent or the owner started the review.

## Steps

1. Record the starting ref with `git rev-parse --abbrev-ref HEAD` and `git rev-parse HEAD`, and confirm that `git status --short` is empty. If it is not, return that the review cannot run.
2. Confirm with `gh pr view <PR> --json headRefOid` that the PR head matches the head SHA in the prompt. If it does not, return that the review cannot run.
3. Read the owner's adaptation comments: `gh pr view <PR> --json comments --jq '.comments[] | select(.body | startswith("Task adaptation approved:") or startswith("Task adaptation declined:")) | .url + "\n" + .body'`.
4. Set the scope with `git diff --name-only <base-sha> <head-sha>`. It is a documentation review when every changed path ends in `.md`, none is under `.claude/`, and none is a skill file (`SKILL.md` or a file in a skill's directory). Otherwise it is a full review.
5. Review the change with Matt Pocock's `code-review` skill where your tool has it, otherwise with the same steps, as its [adaptation](skills.md#code-review) describes. A documentation review checks the AC of an implementation PR or the definition of ready of a planning PR, compares task files, and checks consistency with AGENTS.md and the documents it makes mandatory. It skips the Standards axis for code, the red-evidence check, and test runs.
6. Post the report as one comment with `gh pr comment <PR> --body-file -`, passing the body on standard input.
7. Check out the starting ref again and confirm that `git status --short` is empty.
8. Return only the result, the number of `BLOCKER` and `MAJOR` findings, the recommendation, and the comment URL.
